<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_AI_Response_Repository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'ai_responses';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert($this->table(), [
            'account_id' => (int) ($data['account_id'] ?? 0),
            'message_id' => !empty($data['message_id']) ? (int) $data['message_id'] : null,
            'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : get_current_user_id(),
            'source_response_id' => !empty($data['source_response_id']) ? (int) $data['source_response_id'] : null,
            'compose_mode' => sanitize_key($data['compose_mode'] ?? 'new'),
            'response_mode' => sanitize_key($data['response_mode'] ?? 'generate'),
            'tenant_key' => sanitize_key($data['tenant_key'] ?? 'default'),
            'remote_chat_id' => sanitize_text_field($data['remote_chat_id'] ?? ''),
            'remote_generation_id' => sanitize_text_field($data['remote_generation_id'] ?? ''),
            'prompt_text' => sanitize_textarea_field($data['prompt_text'] ?? ''),
            'original_subject' => sanitize_text_field($data['original_subject'] ?? ''),
            'original_body_text' => sanitize_textarea_field($data['original_body_text'] ?? ''),
            'generated_subject' => sanitize_text_field($data['generated_subject'] ?? ''),
            'generated_html' => wp_kses_post($data['generated_html'] ?? ''),
            'generated_text' => sanitize_textarea_field($data['generated_text'] ?? ''),
            'hidden_html' => wp_kses_post($data['hidden_html'] ?? ''),
            'assistant_id' => sanitize_text_field($data['assistant_id'] ?? ''),
            'assistant_name' => sanitize_text_field($data['assistant_name'] ?? ''),
            'privacy_mode' => !empty($data['privacy_mode']) ? 1 : 0,
            'is_concise' => !empty($data['is_concise']) ? 1 : 0,
            'is_ignored' => !empty($data['is_ignored']) ? 1 : 0,
            'audio_url' => esc_url_raw($data['audio_url'] ?? ''),
            'rating_value' => !empty($data['rating_value']) ? (int) $data['rating_value'] : null,
            'rating_comment' => sanitize_textarea_field($data['rating_comment'] ?? ''),
            'preferences_json' => !empty($data['preferences_json']) ? wp_json_encode($data['preferences_json']) : null,
            'metrics_json' => !empty($data['metrics_json']) ? wp_json_encode($data['metrics_json']) : null,
            'raw_response_json' => !empty($data['raw_response_json']) ? wp_json_encode($data['raw_response_json']) : null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;

        $payload = [];
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'generated_subject':
                case 'assistant_id':
                case 'assistant_name':
                case 'remote_chat_id':
                case 'remote_generation_id':
                case 'tenant_key':
                case 'compose_mode':
                case 'response_mode':
                    $payload[$key] = sanitize_text_field((string) $value);
                    break;
                case 'generated_html':
                case 'hidden_html':
                    $payload[$key] = wp_kses_post((string) $value);
                    break;
                case 'generated_text':
                case 'prompt_text':
                case 'original_body_text':
                case 'rating_comment':
                    $payload[$key] = sanitize_textarea_field((string) $value);
                    break;
                case 'audio_url':
                    $payload[$key] = esc_url_raw((string) $value);
                    break;
                case 'is_ignored':
                case 'privacy_mode':
                case 'is_concise':
                    $payload[$key] = !empty($value) ? 1 : 0;
                    break;
                case 'rating_value':
                case 'source_response_id':
                case 'message_id':
                case 'account_id':
                case 'user_id':
                    $payload[$key] = $value === null ? null : (int) $value;
                    break;
                case 'preferences_json':
                case 'metrics_json':
                case 'raw_response_json':
                    $payload[$key] = is_string($value) ? $value : wp_json_encode($value);
                    break;
            }
        }

        if (!$payload) {
            return;
        }

        $payload['updated_at'] = current_time('mysql');
        $wpdb->update($this->table(), $payload, ['id' => $id]);
    }

    public function list_for_chat(int $account_id, string $chat_id, int $user_id = 0): array
    {
        global $wpdb;

        $where = ['account_id = %d', 'remote_chat_id = %s'];
        $params = [$account_id, $chat_id];

        if ($user_id > 0) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . ' ORDER BY id ASC',
            $params
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function latest_for_context(int $account_id, int $message_id, string $compose_mode, int $user_id = 0): ?array
    {
        global $wpdb;

        $where = ['account_id = %d', 'compose_mode = %s'];
        $params = [$account_id, sanitize_key($compose_mode)];

        if ($message_id > 0) {
            $where[] = 'message_id = %d';
            $params[] = $message_id;
        } else {
            $where[] = '(message_id IS NULL OR message_id = 0)';
        }

        if ($user_id > 0) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1',
            $params
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }
}
