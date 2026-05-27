<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Calendars_Repository
{
    private function calendars(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'calendars';
    }

    private function shares(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'calendar_shares';
    }

    private function events(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'calendar_events';
    }

    public function ensure_default_for_user(int $user_id): array
    {
        global $wpdb;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->calendars()} WHERE owner_user_id = %d AND is_default = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        if ($existing) {
            return $this->decorate($existing, $user_id);
        }

        $user = get_userdata($user_id);
        $name = $user ? sprintf('Calendario di %s', $user->display_name ?: $user->user_login) : 'Calendario personale';
        $now = current_time('mysql');

        $wpdb->insert($this->calendars(), [
            'owner_user_id' => $user_id,
            'name' => $name,
            'slug' => 'default-' . $user_id,
            'color' => '#0d5c63',
            'description' => 'Calendario principale personale.',
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $calendar = $this->find_owned((int) $wpdb->insert_id, $user_id);
        return $calendar ?: [
            'id' => (int) $wpdb->insert_id,
            'owner_user_id' => $user_id,
            'name' => $name,
            'slug' => 'default-' . $user_id,
            'color' => '#0d5c63',
            'description' => 'Calendario principale personale.',
            'is_default' => 1,
            'permission_level' => 'owner',
            'can_edit' => true,
            'can_manage_shares' => true,
        ];
    }

    public function list_visible_for_user(int $user_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, CASE WHEN c.owner_user_id = %d THEN 'owner' ELSE COALESCE(s.permission_level, 'read') END AS permission_level
                FROM {$this->calendars()} c
                LEFT JOIN {$this->shares()} s
                    ON s.calendar_id = c.id
                    AND s.user_id = %d
                WHERE c.deleted_at IS NULL
                    AND (c.owner_user_id = %d OR s.user_id = %d)
                ORDER BY c.is_default DESC, c.name ASC",
                $user_id,
                $user_id,
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        return array_map(function (array $row) use ($user_id) {
            return $this->decorate($row, $user_id);
        }, $rows);
    }

    public function find_visible(int $calendar_id, int $user_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, CASE WHEN c.owner_user_id = %d THEN 'owner' ELSE COALESCE(s.permission_level, 'read') END AS permission_level
                FROM {$this->calendars()} c
                LEFT JOIN {$this->shares()} s
                    ON s.calendar_id = c.id
                    AND s.user_id = %d
                WHERE c.id = %d
                    AND c.deleted_at IS NULL
                    AND (c.owner_user_id = %d OR s.user_id = %d)
                LIMIT 1",
                $user_id,
                $user_id,
                $calendar_id,
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        return $row ? $this->decorate($row, $user_id) : null;
    }

    public function find_owned(int $calendar_id, int $user_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->calendars()} WHERE id = %d AND owner_user_id = %d AND deleted_at IS NULL LIMIT 1",
                $calendar_id,
                $user_id
            ),
            ARRAY_A
        );

        return $row ? $this->decorate($row, $user_id) : null;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert($this->calendars(), [
            'owner_user_id' => (int) $data['owner_user_id'],
            'name' => sanitize_text_field($data['name'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? ($data['name'] ?? 'calendar')),
            'color' => sanitize_text_field($data['color'] ?? '#0d5c63'),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $calendar_id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->calendars(),
            [
                'name' => sanitize_text_field($data['name'] ?? ''),
                'slug' => sanitize_title($data['slug'] ?? ($data['name'] ?? 'calendar')),
                'color' => sanitize_text_field($data['color'] ?? '#0d5c63'),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $calendar_id]
        );

        return $updated !== false;
    }

    public function delete(int $calendar_id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->calendars(),
            [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $calendar_id]
        );

        return $updated !== false;
    }

    public function reassign_calendar_events(int $calendar_id, int $target_calendar_id): void
    {
        global $wpdb;

        $wpdb->update(
            $this->events(),
            [
                'calendar_id' => $target_calendar_id,
                'updated_at' => current_time('mysql'),
            ],
            ['calendar_id' => $calendar_id]
        );
    }

    public function assign_legacy_events_to_default(int $user_id, int $calendar_id): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->events()}
                SET calendar_id = %d, updated_at = %s
                WHERE owner_id = %d
                    AND deleted_at IS NULL
                    AND (calendar_id IS NULL OR calendar_id = 0)",
                $calendar_id,
                current_time('mysql'),
                $user_id
            )
        );
    }

    public function replace_shares(int $calendar_id, array $shares): void
    {
        global $wpdb;

        $wpdb->delete($this->shares(), ['calendar_id' => $calendar_id]);
        $now = current_time('mysql');

        foreach ($shares as $share) {
            $user_id = (int) ($share['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $permission_level = sanitize_key($share['permission_level'] ?? 'read');
            if (!in_array($permission_level, ['read', 'edit'], true)) {
                $permission_level = 'read';
            }

            $wpdb->insert($this->shares(), [
                'calendar_id' => $calendar_id,
                'user_id' => $user_id,
                'permission_level' => $permission_level,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function list_shares(int $calendar_id): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, calendar_id, user_id, permission_level FROM {$this->shares()} WHERE calendar_id = %d ORDER BY id ASC",
                $calendar_id
            ),
            ARRAY_A
        );
    }

    private function decorate(array $row, int $user_id): array
    {
        $permission_level = sanitize_key($row['permission_level'] ?? (($row['owner_user_id'] ?? 0) == $user_id ? 'owner' : 'read'));
        $row['permission_level'] = $permission_level;
        $row['can_edit'] = in_array($permission_level, ['owner', 'edit'], true);
        $row['can_manage_shares'] = $permission_level === 'owner';
        return $row;
    }
}
