<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Contacts_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'contacts';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'owner_type' => 'user',
            'owner_id' => get_current_user_id(),
            'source' => 'local',
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'display_name' => sanitize_text_field($data['display_name'] ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))),
            'company' => sanitize_text_field($data['company'] ?? ''),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'job_title' => sanitize_text_field($data['job_title'] ?? ''),
            'primary_email' => sanitize_email($data['primary_email'] ?? ''),
            'website_url' => esc_url_raw($data['website_url'] ?? ''),
            'mobile_phone' => sanitize_text_field($data['mobile_phone'] ?? ''),
            'business_phone' => sanitize_text_field($data['business_phone'] ?? ''),
            'emails_json' => wp_json_encode(array_filter(array_map('sanitize_email', (array) ($data['emails'] ?? [])))),
            'phones_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['phones'] ?? [])))),
            'addresses_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['addresses'] ?? [])))),
            'categories_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['categories'] ?? [])))),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function update(int $contact_id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'first_name' => sanitize_text_field($data['first_name'] ?? ''),
                'last_name' => sanitize_text_field($data['last_name'] ?? ''),
                'display_name' => sanitize_text_field($data['display_name'] ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))),
                'company' => sanitize_text_field($data['company'] ?? ''),
                'department' => sanitize_text_field($data['department'] ?? ''),
                'job_title' => sanitize_text_field($data['job_title'] ?? ''),
                'primary_email' => sanitize_email($data['primary_email'] ?? ''),
                'website_url' => esc_url_raw($data['website_url'] ?? ''),
                'mobile_phone' => sanitize_text_field($data['mobile_phone'] ?? ''),
                'business_phone' => sanitize_text_field($data['business_phone'] ?? ''),
                'emails_json' => wp_json_encode(array_filter(array_map('sanitize_email', (array) ($data['emails'] ?? [])))),
                'phones_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['phones'] ?? [])))),
                'addresses_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['addresses'] ?? [])))),
                'categories_json' => wp_json_encode(array_filter(array_map('sanitize_text_field', (array) ($data['categories'] ?? [])))),
                'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $contact_id,
                'owner_id' => get_current_user_id(),
                'deleted_at' => null,
            ]
        );

        return $updated !== false;
    }

    public function delete(int $contact_id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $contact_id,
                'owner_id' => get_current_user_id(),
                'deleted_at' => null,
            ]
        );

        return $updated !== false;
    }

    public function find(int $contact_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE id = %d AND owner_id = %d AND deleted_at IS NULL",
                $contact_id,
                get_current_user_id()
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function find_by_email(string $email): ?array
    {
        global $wpdb;

        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE owner_id = %d AND deleted_at IS NULL AND (primary_email = %s OR emails_json LIKE %s) ORDER BY id ASC LIMIT 1",
                get_current_user_id(),
                $email,
                '%' . $wpdb->esc_like($email) . '%'
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function search(string $query = ''): array
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE deleted_at IS NULL AND owner_id = %d AND (display_name LIKE %s OR primary_email LIKE %s OR company LIKE %s OR department LIKE %s OR mobile_phone LIKE %s OR business_phone LIKE %s OR notes LIKE %s) ORDER BY display_name ASC LIMIT 100",
                get_current_user_id(),
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like
            ),
            ARRAY_A
        );
    }
}
