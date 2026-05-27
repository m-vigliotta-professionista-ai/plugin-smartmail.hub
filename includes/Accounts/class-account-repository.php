<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Account_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'accounts';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'owner_type' => sanitize_key($data['owner_type'] ?? 'user'),
            'owner_id' => (int) ($data['owner_id'] ?? get_current_user_id()),
            'label' => sanitize_text_field($data['label'] ?? ''),
            'email_address' => sanitize_email($data['email_address'] ?? ''),
            'display_name' => sanitize_text_field($data['display_name'] ?? ''),
            'provider_type' => sanitize_key($data['provider_type'] ?? 'imap_smtp'),
            'imap_host' => sanitize_text_field($data['imap_host'] ?? ''),
            'imap_port' => (int) ($data['imap_port'] ?? 993),
            'imap_encryption' => sanitize_key($data['imap_encryption'] ?? 'ssl_tls'),
            'imap_auth_mode' => sanitize_key($data['imap_auth_mode'] ?? 'password'),
            'smtp_host' => sanitize_text_field($data['smtp_host'] ?? ''),
            'smtp_port' => (int) ($data['smtp_port'] ?? 465),
            'smtp_encryption' => sanitize_key($data['smtp_encryption'] ?? 'ssl_tls'),
            'smtp_auth_mode' => sanitize_key($data['smtp_auth_mode'] ?? 'password'),
            'encrypted_username' => $data['encrypted_username'] ?? null,
            'encrypted_secret' => $data['encrypted_secret'] ?? null,
            'status' => sanitize_key($data['status'] ?? 'pending'),
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return false !== $wpdb->update($this->table(), $data, ['id' => $id]);
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public function visible_for_user(int $user_id): array
    {
        global $wpdb;
        if (current_user_can('v24_smh_manage_accounts')) {
            return $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY label ASC", ARRAY_A);
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE owner_type = 'shared' OR (owner_type = 'user' AND owner_id = %d) ORDER BY label ASC",
            $user_id
        ), ARRAY_A);
    }

    public function active(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table()} WHERE status = 'active' ORDER BY id ASC", ARRAY_A);
    }

    public function set_status(int $id, string $status, ?string $error = null): bool
    {
        return $this->update($id, [
            'status' => sanitize_key($status),
            'last_error' => $error ? sanitize_textarea_field($error) : null,
            'last_connected_at' => $status === 'active' ? current_time('mysql') : null,
        ]);
    }
}
