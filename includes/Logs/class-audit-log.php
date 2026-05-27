<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Audit_Log
{
    public static function record(string $event_type, array $data = []): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . V24_SMH_TABLE_PREFIX . 'audit_log', [
            'user_id' => get_current_user_id() ?: null,
            'account_id' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'event_type' => sanitize_key($event_type),
            'entity_type' => isset($data['entity_type']) ? sanitize_key($data['entity_type']) : null,
            'entity_id' => isset($data['entity_id']) ? (int) $data['entity_id'] : null,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null,
            'details_json' => isset($data['details']) ? wp_json_encode($data['details']) : null,
            'created_at' => current_time('mysql'),
        ]);
    }

    public static function delete_older_than_days(int $days): int
    {
        global $wpdb;
        $days = max(1, $days);
        $threshold = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM " . $wpdb->prefix . V24_SMH_TABLE_PREFIX . "audit_log WHERE created_at < %s",
            $threshold
        ));
    }
}
