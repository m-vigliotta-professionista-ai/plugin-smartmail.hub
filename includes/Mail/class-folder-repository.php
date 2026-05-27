<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Folder_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'folders';
    }

    public function upsert(int $account_id, array $folder): int
    {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE account_id = %d AND remote_id = %s", $account_id, $folder['remote_id']));
        $data = [
            'account_id' => $account_id,
            'remote_id' => sanitize_text_field($folder['remote_id']),
            'name' => sanitize_text_field($folder['name']),
            'full_name' => sanitize_text_field($folder['full_name']),
            'delimiter' => $folder['delimiter'] ?? null,
            'special_use' => $folder['special_use'] ?? null,
            'updated_at' => current_time('mysql'),
        ];
        if ($existing) {
            $wpdb->update($this->table(), $data, ['id' => (int) $existing]);
            return (int) $existing;
        }
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($this->table(), $data);
        return (int) $wpdb->insert_id;
    }

    public function list_by_account(int $account_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE account_id = %d ORDER BY special_use DESC, name ASC", $account_id), ARRAY_A);
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public function find_by_account_remote(int $account_id, string $remote_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE account_id = %d AND remote_id = %s",
                $account_id,
                $remote_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function update_folder(int $folder_id, array $folder): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'remote_id' => sanitize_text_field($folder['remote_id'] ?? ''),
            'name' => sanitize_text_field($folder['name'] ?? ''),
            'full_name' => sanitize_text_field($folder['full_name'] ?? ''),
            'delimiter' => $folder['delimiter'] ?? null,
            'special_use' => $folder['special_use'] ?? null,
            'updated_at' => current_time('mysql'),
        ], ['id' => $folder_id]);
    }

    public function delete(int $folder_id): void
    {
        global $wpdb;
        $wpdb->delete($this->table(), ['id' => $folder_id]);
    }

    public function delete_missing(int $account_id, array $remote_ids): void
    {
        global $wpdb;

        $remote_ids = array_values(array_filter(array_map('strval', $remote_ids), static function ($value) {
            return $value !== '';
        }));

        if (!$remote_ids) {
            $wpdb->delete($this->table(), ['account_id' => $account_id]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($remote_ids), '%s'));
        $params = array_merge([$account_id], $remote_ids);
        $sql = $wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE account_id = %d AND remote_id NOT IN ($placeholders)",
            $params
        );
        $wpdb->query($sql);
    }

    public function update_sync_state(int $folder_id, int $highest_uid, ?int $message_count = null, ?int $unseen_count = null): void
    {
        global $wpdb;
        $data = [
            'highest_uid' => $highest_uid,
            'last_synced_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        if ($message_count !== null) {
            $data['message_count'] = max(0, $message_count);
        }

        if ($unseen_count !== null) {
            $data['unseen_count'] = max(0, $unseen_count);
        }

        $wpdb->update($this->table(), $data, ['id' => $folder_id]);
    }

    public function update_counts(int $folder_id, int $message_count, int $unseen_count): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'message_count' => max(0, $message_count),
            'unseen_count' => max(0, $unseen_count),
            'updated_at' => current_time('mysql'),
        ], ['id' => $folder_id]);
    }
}
