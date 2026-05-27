<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Events_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'calendar_events';
    }

    private function calendars(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'calendars';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'owner_type' => 'user',
            'owner_id' => (int) ($data['owner_id'] ?? get_current_user_id()),
            'account_id' => !empty($data['account_id']) ? (int) $data['account_id'] : null,
            'calendar_id' => !empty($data['calendar_id']) ? (int) $data['calendar_id'] : null,
            'parent_event_id' => !empty($data['parent_event_id']) ? (int) $data['parent_event_id'] : null,
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'location' => sanitize_text_field($data['location'] ?? ''),
            'start_at' => sanitize_text_field($data['start_at'] ?? current_time('mysql')),
            'end_at' => sanitize_text_field($data['end_at'] ?? current_time('mysql')),
            'timezone' => sanitize_text_field($data['timezone'] ?? wp_timezone_string()),
            'is_all_day' => !empty($data['is_all_day']) ? 1 : 0,
            'status' => sanitize_key($data['status'] ?? 'confirmed'),
            'busy_status' => sanitize_key($data['busy_status'] ?? 'busy'),
            'reminder_minutes' => isset($data['reminder_minutes']) && $data['reminder_minutes'] !== '' ? max(0, (int) $data['reminder_minutes']) : null,
            'categories_json' => $this->json_array($data['categories'] ?? []),
            'recurrence_json' => !empty($data['recurrence']) ? wp_json_encode($data['recurrence']) : null,
            'recurrence_exceptions_json' => $this->json_array($data['recurrence_exceptions'] ?? []),
            'recurrence_instance_start' => !empty($data['recurrence_instance_start']) ? sanitize_text_field($data['recurrence_instance_start']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $event_id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'calendar_id' => !empty($data['calendar_id']) ? (int) $data['calendar_id'] : null,
                'title' => sanitize_text_field($data['title'] ?? ''),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'location' => sanitize_text_field($data['location'] ?? ''),
                'start_at' => sanitize_text_field($data['start_at'] ?? current_time('mysql')),
                'end_at' => sanitize_text_field($data['end_at'] ?? current_time('mysql')),
                'timezone' => sanitize_text_field($data['timezone'] ?? wp_timezone_string()),
                'is_all_day' => !empty($data['is_all_day']) ? 1 : 0,
                'status' => sanitize_key($data['status'] ?? 'confirmed'),
                'busy_status' => sanitize_key($data['busy_status'] ?? 'busy'),
                'reminder_minutes' => isset($data['reminder_minutes']) && $data['reminder_minutes'] !== '' ? max(0, (int) $data['reminder_minutes']) : null,
                'categories_json' => $this->json_array($data['categories'] ?? []),
                'recurrence_json' => array_key_exists('recurrence', $data) ? (!empty($data['recurrence']) ? wp_json_encode($data['recurrence']) : null) : null,
                'recurrence_exceptions_json' => array_key_exists('recurrence_exceptions', $data) ? $this->json_array($data['recurrence_exceptions'] ?? []) : null,
                'recurrence_instance_start' => array_key_exists('recurrence_instance_start', $data) ? (!empty($data['recurrence_instance_start']) ? sanitize_text_field($data['recurrence_instance_start']) : null) : null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $event_id]
        );

        return $updated !== false;
    }

    public function delete(int $event_id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $event_id]
        );

        return $updated !== false;
    }

    public function find_visible(int $event_id, array $calendar_ids): ?array
    {
        global $wpdb;

        if (!$calendar_ids) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($calendar_ids), '%d'));
        $params = array_merge([$event_id], $calendar_ids);

        $sql = $wpdb->prepare(
            "SELECT e.*, c.name AS calendar_name, c.color AS calendar_color
            FROM {$this->table()} e
            LEFT JOIN {$this->calendars()} c ON c.id = e.calendar_id
            WHERE e.id = %d
                AND e.deleted_at IS NULL
                AND e.calendar_id IN ($placeholders)
            LIMIT 1",
            $params
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    public function list_visible(array $calendar_ids, string $start, string $end): array
    {
        global $wpdb;

        if (!$calendar_ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($calendar_ids), '%d'));
        $params = array_merge($calendar_ids, [
            $end ?: '2999-12-31 23:59:59',
            $start ?: '1970-01-01 00:00:00',
        ]);

        $sql = $wpdb->prepare(
            "SELECT e.*, c.name AS calendar_name, c.color AS calendar_color
            FROM {$this->table()} e
            LEFT JOIN {$this->calendars()} c ON c.id = e.calendar_id
            WHERE e.deleted_at IS NULL
                AND e.calendar_id IN ($placeholders)
                AND (
                    (e.parent_event_id IS NOT NULL AND (e.recurrence_instance_start BETWEEN %s AND %s OR (e.start_at <= %s AND e.end_at >= %s)))
                    OR
                    (e.parent_event_id IS NULL AND ((e.start_at <= %s AND e.end_at >= %s) OR (e.recurrence_json IS NOT NULL AND e.recurrence_json <> '')))
                )
            ORDER BY e.start_at ASC, e.id ASC",
            array_merge($calendar_ids, [
                $start ?: '1970-01-01 00:00:00',
                $end ?: '2999-12-31 23:59:59',
                $end ?: '2999-12-31 23:59:59',
                $start ?: '1970-01-01 00:00:00',
                $end ?: '2999-12-31 23:59:59',
                $start ?: '1970-01-01 00:00:00',
            ])
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function list_detached_instances(array $parent_ids, array $calendar_ids, string $start, string $end): array
    {
        global $wpdb;

        if (!$parent_ids || !$calendar_ids) {
            return [];
        }

        $parent_placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
        $calendar_placeholders = implode(',', array_fill(0, count($calendar_ids), '%d'));
        $params = array_merge(
            $parent_ids,
            $calendar_ids,
            [
                $start ?: '1970-01-01 00:00:00',
                $end ?: '2999-12-31 23:59:59',
                $end ?: '2999-12-31 23:59:59',
                $start ?: '1970-01-01 00:00:00',
            ]
        );

        $sql = $wpdb->prepare(
            "SELECT e.*, c.name AS calendar_name, c.color AS calendar_color
            FROM {$this->table()} e
            LEFT JOIN {$this->calendars()} c ON c.id = e.calendar_id
            WHERE e.deleted_at IS NULL
                AND e.parent_event_id IN ($parent_placeholders)
                AND e.calendar_id IN ($calendar_placeholders)
                AND (
                    e.recurrence_instance_start BETWEEN %s AND %s
                    OR (e.start_at <= %s AND e.end_at >= %s)
                )
            ORDER BY e.start_at ASC, e.id ASC",
            $params
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function find_override(int $parent_event_id, string $occurrence_start, array $calendar_ids): ?array
    {
        global $wpdb;

        if (!$calendar_ids) {
            return null;
        }

        $calendar_placeholders = implode(',', array_fill(0, count($calendar_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT e.*, c.name AS calendar_name, c.color AS calendar_color
            FROM {$this->table()} e
            LEFT JOIN {$this->calendars()} c ON c.id = e.calendar_id
            WHERE e.deleted_at IS NULL
                AND e.parent_event_id = %d
                AND e.recurrence_instance_start = %s
                AND e.calendar_id IN ($calendar_placeholders)
            LIMIT 1",
            array_merge([$parent_event_id, $occurrence_start], $calendar_ids)
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    public function add_exception(int $event_id, string $occurrence_start): void
    {
        $event = $this->find_by_id($event_id);
        if (!$event) {
            return;
        }

        $existing = json_decode((string) ($event['recurrence_exceptions_json'] ?? '[]'), true);
        if (!is_array($existing)) {
            $existing = [];
        }

        if (!in_array($occurrence_start, $existing, true)) {
            $existing[] = $occurrence_start;
        }

        $this->update($event_id, [
            'calendar_id' => (int) ($event['calendar_id'] ?? 0),
            'title' => $event['title'] ?? '',
            'description' => $event['description'] ?? '',
            'location' => $event['location'] ?? '',
            'start_at' => $event['start_at'] ?? current_time('mysql'),
            'end_at' => $event['end_at'] ?? current_time('mysql'),
            'timezone' => $event['timezone'] ?? wp_timezone_string(),
            'is_all_day' => !empty($event['is_all_day']),
            'status' => $event['status'] ?? 'confirmed',
            'busy_status' => $event['busy_status'] ?? 'busy',
            'reminder_minutes' => $event['reminder_minutes'] ?? null,
            'categories' => json_decode((string) ($event['categories_json'] ?? '[]'), true) ?: [],
            'recurrence' => json_decode((string) ($event['recurrence_json'] ?? 'null'), true),
            'recurrence_exceptions' => $existing,
            'recurrence_instance_start' => $event['recurrence_instance_start'] ?? null,
        ]);
    }

    public function find_by_id(int $event_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $event_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    private function json_array(array $values): string
    {
        return wp_json_encode(array_values(array_filter($values, static function ($value) {
            return $value !== null && $value !== '';
        })));
    }
}
