<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Attachment_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'attachments';
    }

    public function replace_for_message(int $message_id, int $account_id, array $attachments): void
    {
        global $wpdb;

        $wpdb->delete($this->table(), ['message_id' => $message_id]);

        foreach ($attachments as $attachment) {
            $wpdb->insert($this->table(), [
                'message_id' => $message_id,
                'account_id' => $account_id,
                'part_id' => sanitize_text_field($attachment['part_id'] ?? ''),
                'filename' => sanitize_file_name($attachment['filename'] ?? ''),
                'stored_filename' => null,
                'mime_type' => sanitize_text_field($attachment['mime_type'] ?? 'application/octet-stream'),
                'size_bytes' => (int) ($attachment['size_bytes'] ?? 0),
                'storage_driver' => 'remote',
                'storage_path' => null,
                'content_id' => sanitize_text_field($attachment['content_id'] ?? ''),
                'is_inline' => !empty($attachment['is_inline']) ? 1 : 0,
                'downloaded' => !empty($attachment['downloaded']) ? 1 : 0,
                'checksum' => null,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    public function list_by_message(int $message_id): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, message_id, part_id, filename, mime_type, size_bytes, content_id, is_inline, downloaded FROM {$this->table()} WHERE message_id = %d ORDER BY id ASC",
                $message_id
            ),
            ARRAY_A
        );
    }

    public function find(int $attachment_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $attachment_id), ARRAY_A);
        return $row ?: null;
    }

    public function mark_downloaded(int $attachment_id): void
    {
        global $wpdb;
        $wpdb->update($this->table(), ['downloaded' => 1], ['id' => $attachment_id]);
    }
}
