<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Sync_Log
{
    public static function record(array $data): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . V24_SMH_TABLE_PREFIX . 'sync_log', [
            'account_id' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'folder_id' => isset($data['folder_id']) ? (int) $data['folder_id'] : null,
            'job_id' => isset($data['job_id']) ? (int) $data['job_id'] : null,
            'sync_type' => sanitize_key($data['sync_type'] ?? 'manual'),
            'status' => sanitize_key($data['status'] ?? 'done'),
            'items_seen' => (int) ($data['items_seen'] ?? 0),
            'items_created' => (int) ($data['items_created'] ?? 0),
            'items_updated' => (int) ($data['items_updated'] ?? 0),
            'items_deleted' => (int) ($data['items_deleted'] ?? 0),
            'duration_ms' => isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            'error_message' => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
            'created_at' => current_time('mysql'),
        ]);
    }

    public static function last_account_sync_at(int $account_id): ?string
    {
        global $wpdb;
        $table = $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'sync_log';
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$table} WHERE account_id = %d AND sync_type = 'mail_sync' AND status = 'done'",
            $account_id
        ));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function delete_older_than_days(int $days): int
    {
        global $wpdb;
        $days = max(1, $days);
        $threshold = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM " . $wpdb->prefix . V24_SMH_TABLE_PREFIX . "sync_log WHERE created_at < %s",
            $threshold
        ));
    }
}
