<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Mail_Rules_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'mail_rules';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'account_id' => (int) $data['account_id'],
            'owner_user_id' => (int) $data['owner_user_id'],
            'name' => sanitize_text_field($data['name'] ?? ''),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'match_mode' => sanitize_key($data['match_mode'] ?? 'all'),
            'stop_processing' => !empty($data['stop_processing']) ? 1 : 0,
            'conditions_json' => wp_json_encode($data['conditions'] ?? []),
            'actions_json' => wp_json_encode($data['actions'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $rule_id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'name' => sanitize_text_field($data['name'] ?? ''),
                'is_active' => !empty($data['is_active']) ? 1 : 0,
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
                'match_mode' => sanitize_key($data['match_mode'] ?? 'all'),
                'stop_processing' => !empty($data['stop_processing']) ? 1 : 0,
                'conditions_json' => wp_json_encode($data['conditions'] ?? []),
                'actions_json' => wp_json_encode($data['actions'] ?? []),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $rule_id]
        );

        return $updated !== false;
    }

    public function delete(int $rule_id): bool
    {
        global $wpdb;
        return false !== $wpdb->delete($this->table(), ['id' => $rule_id]);
    }

    public function find(int $rule_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $rule_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function find_visible(int $rule_id, int $user_id, int $account_id = 0): ?array
    {
        global $wpdb;

        $where = ['id = %d', 'owner_user_id = %d'];
        $params = [$rule_id, $user_id];

        if ($account_id > 0) {
            $where[] = 'account_id = %d';
            $params[] = $account_id;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . " LIMIT 1",
                $params
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function list_for_user(int $user_id, int $account_id): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE owner_user_id = %d AND account_id = %d ORDER BY sort_order ASC, id ASC",
                $user_id,
                $account_id
            ),
            ARRAY_A
        );
    }

    public function active_for_account(int $account_id, ?int $rule_id = null): array
    {
        global $wpdb;

        $where = ['account_id = %d', 'is_active = 1'];
        $params = [$account_id];

        if ($rule_id) {
            $where[] = 'id = %d';
            $params[] = $rule_id;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . " ORDER BY sort_order ASC, id ASC",
                $params
            ),
            ARRAY_A
        );
    }

    public function touch_run(int $rule_id, bool $matched): void
    {
        global $wpdb;

        if ($matched) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table()} SET last_run_at = %s, last_matched_at = %s, times_applied = times_applied + 1, updated_at = %s WHERE id = %d",
                    current_time('mysql'),
                    current_time('mysql'),
                    current_time('mysql'),
                    $rule_id
                )
            );
            return;
        }

        $wpdb->update($this->table(), [
            'last_run_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $rule_id]);
    }
}
