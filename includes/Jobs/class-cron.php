<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Cron
{
    public static function register_hooks(): void
    {
        add_filter('cron_schedules', [self::class, 'schedules']);
        add_action('init', [self::class, 'ensure_scheduled']);
        add_action('v24_smh_cron_jobs', [self::class, 'run_jobs']);
        add_action('v24_smh_cron_cleanup', [self::class, 'cleanup']);
    }

    public static function schedules(array $schedules): array
    {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => 'Every five minutes',
        ];
        return $schedules;
    }

    public static function run_jobs(): void
    {
        self::enqueue_due_account_syncs();
        (new V24_SMH_Sync_Job_Runner())->run(3);
    }

    public static function cleanup(): void
    {
        $settings = get_option('v24_smh_settings', []);
        $log_retention_days = max(1, (int) (is_array($settings) ? ($settings['log_retention_days'] ?? 180) : 180));

        V24_SMH_Sync_Log::delete_older_than_days($log_retention_days);
        V24_SMH_Audit_Log::delete_older_than_days($log_retention_days);
    }

    public static function ensure_scheduled(): void
    {
        $schedule = wp_get_schedule('v24_smh_cron_jobs');
        if ($schedule !== 'five_minutes') {
            wp_clear_scheduled_hook('v24_smh_cron_jobs');
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'five_minutes', 'v24_smh_cron_jobs');
        }

        if (!wp_next_scheduled('v24_smh_cron_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'v24_smh_cron_cleanup');
        }
    }

    private static function enqueue_due_account_syncs(): void
    {
        $settings = get_option('v24_smh_settings', []);
        $interval_minutes = max(5, (int) (is_array($settings) ? ($settings['sync_interval_minutes'] ?? 15) : 15));
        $threshold = current_time('timestamp') - ($interval_minutes * MINUTE_IN_SECONDS);
        $jobs = new V24_SMH_Job_Repository();

        foreach ((new V24_SMH_Account_Repository())->active() as $account) {
            $account_id = (int) ($account['id'] ?? 0);
            if ($account_id <= 0 || $jobs->has_open_job('mail_sync', $account_id)) {
                continue;
            }

            $last_sync = V24_SMH_Sync_Log::last_account_sync_at($account_id);
            if ($last_sync !== null && mysql2date('U', $last_sync) > $threshold) {
                continue;
            }

            $jobs->enqueue('mail_sync', ['source' => 'cron'], $account_id);
        }
    }
}
