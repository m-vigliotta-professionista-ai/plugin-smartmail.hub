<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Event_Attendees_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'event_attendees';
    }

    public function list_by_event(int $event_id): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, event_id, contact_id, email, name, role, response_status FROM {$this->table()} WHERE event_id = %d ORDER BY id ASC",
                $event_id
            ),
            ARRAY_A
        );
    }

    public function replace_for_event(int $event_id, array $attendees): void
    {
        global $wpdb;

        $wpdb->delete($this->table(), ['event_id' => $event_id]);
        $now = current_time('mysql');

        foreach ($attendees as $attendee) {
            $email = sanitize_email($attendee['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $wpdb->insert($this->table(), [
                'event_id' => $event_id,
                'contact_id' => !empty($attendee['contact_id']) ? (int) $attendee['contact_id'] : null,
                'email' => $email,
                'name' => sanitize_text_field($attendee['name'] ?? ''),
                'role' => sanitize_key($attendee['role'] ?? 'required'),
                'response_status' => sanitize_key($attendee['response_status'] ?? 'needs_action'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
