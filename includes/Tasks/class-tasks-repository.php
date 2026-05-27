<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Tasks_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'tasks';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'owner_user_id' => (int) $data['owner_user_id'],
            'created_by' => (int) $data['created_by'],
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_key($data['status'] ?? 'not_started'),
            'priority' => sanitize_key($data['priority'] ?? 'normal'),
            'start_at' => !empty($data['start_at']) ? sanitize_text_field($data['start_at']) : null,
            'due_at' => !empty($data['due_at']) ? sanitize_text_field($data['due_at']) : null,
            'reminder_at' => !empty($data['reminder_at']) ? sanitize_text_field($data['reminder_at']) : null,
            'completed_at' => !empty($data['completed_at']) ? sanitize_text_field($data['completed_at']) : null,
            'percent_complete' => max(0, min(100, (int) ($data['percent_complete'] ?? 0))),
            'related_message_id' => !empty($data['related_message_id']) ? (int) $data['related_message_id'] : null,
            'related_contact_id' => !empty($data['related_contact_id']) ? (int) $data['related_contact_id'] : null,
            'related_event_id' => !empty($data['related_event_id']) ? (int) $data['related_event_id'] : null,
            'categories_json' => wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array) ($data['categories'] ?? []))))),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $task_id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'assigned_user_id' => !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
                'title' => sanitize_text_field($data['title'] ?? ''),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'status' => sanitize_key($data['status'] ?? 'not_started'),
                'priority' => sanitize_key($data['priority'] ?? 'normal'),
                'start_at' => !empty($data['start_at']) ? sanitize_text_field($data['start_at']) : null,
                'due_at' => !empty($data['due_at']) ? sanitize_text_field($data['due_at']) : null,
                'reminder_at' => !empty($data['reminder_at']) ? sanitize_text_field($data['reminder_at']) : null,
                'completed_at' => !empty($data['completed_at']) ? sanitize_text_field($data['completed_at']) : null,
                'percent_complete' => max(0, min(100, (int) ($data['percent_complete'] ?? 0))),
                'related_message_id' => !empty($data['related_message_id']) ? (int) $data['related_message_id'] : null,
                'related_contact_id' => !empty($data['related_contact_id']) ? (int) $data['related_contact_id'] : null,
                'related_event_id' => !empty($data['related_event_id']) ? (int) $data['related_event_id'] : null,
                'categories_json' => wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array) ($data['categories'] ?? []))))),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $task_id]
        );

        return $updated !== false;
    }

    public function delete(int $task_id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $task_id]
        );

        return $updated !== false;
    }

    public function find_visible(int $task_id, int $user_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE id = %d AND deleted_at IS NULL AND (owner_user_id = %d OR assigned_user_id = %d) LIMIT 1",
                $task_id,
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function list_visible(int $user_id, array $filters = []): array
    {
        global $wpdb;

        $where = ['deleted_at IS NULL', '(owner_user_id = %d OR assigned_user_id = %d)'];
        $params = [$user_id, $user_id];

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_key((string) $filters['status']);
        }

        if (!empty($filters['assigned_user_id'])) {
            $where[] = 'assigned_user_id = %d';
            $params[] = (int) $filters['assigned_user_id'];
        }

        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . " ORDER BY 
                CASE WHEN due_at IS NULL THEN 1 ELSE 0 END ASC,
                due_at ASC,
                updated_at DESC",
            $params
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }
}
