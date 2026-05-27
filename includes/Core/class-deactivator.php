<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('v24_smh_cron_jobs');
        wp_clear_scheduled_hook('v24_smh_cron_cleanup');
    }
}
