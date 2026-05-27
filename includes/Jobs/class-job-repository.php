<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Job_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'sync_jobs';
    }

    public function enqueue(string $type, array $payload = [], ?int $account_id = null, ?int $folder_id = null): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'job_type' => sanitize_key($type),
            'account_id' => $account_id,
            'folder_id' => $folder_id,
            'payload_json' => wp_json_encode($payload),
            'status' => 'pending',
            'scheduled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function has_open_job(string $type, ?int $account_id = null, ?int $folder_id = null): bool
    {
        global $wpdb;
        $table = $this->table();
        $where = [
            "job_type = %s",
            "status IN ('pending', 'running')",
        ];
        $params = [sanitize_key($type)];

        if ($account_id !== null) {
            $where[] = 'account_id = %d';
            $params[] = $account_id;
        }

        if ($folder_id !== null) {
            $where[] = 'folder_id = %d';
            $params[] = $folder_id;
        }

        $sql = "SELECT id FROM {$table} WHERE " . implode(' AND ', $where) . ' LIMIT 1';
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public function claim_next(): ?array
    {
        global $wpdb;
        $table = $this->table();
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 1",
            current_time('mysql')
        ), ARRAY_A);
        if (!$job) {
            return null;
        }
        $wpdb->update($table, [
            'status' => 'running',
            'locked_at' => current_time('mysql'),
            'locked_by' => 'wp-cron',
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $job['id']]);
        return $job;
    }

    public function done(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'status' => 'done',
            'finished_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public function failed(int $id, string $error): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET status = IF(attempts + 1 >= max_attempts, 'failed', 'pending'), attempts = attempts + 1, last_error = %s, updated_at = %s WHERE id = %d",
            sanitize_textarea_field($error),
            current_time('mysql'),
            $id
        ));
    }

    public function release_stale_running_jobs(int $minutes = 30): int
    {
        global $wpdb;
        $minutes = max(5, $minutes);
        $threshold = date('Y-m-d H:i:s', current_time('timestamp') - ($minutes * MINUTE_IN_SECONDS));

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET status = 'pending', locked_at = NULL, locked_by = NULL, updated_at = %s WHERE status = 'running' AND locked_at < %s",
            current_time('mysql'),
            $threshold
        ));
    }
}
