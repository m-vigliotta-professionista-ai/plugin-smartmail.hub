<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Compose_Service
{
    public function send(array $input)
    {
        $context = $this->load_account_context((int) ($input['account_id'] ?? 0));
        if (is_wp_error($context)) {
            return $context;
        }

        $account = $context['account'];
        $message = $this->build_outgoing_message($input);
        if (is_wp_error($message)) {
            return $message;
        }

        $result = (new V24_SMH_SMTP_Client())->send($account, $message);
        if (is_wp_error($result)) {
            V24_SMH_Audit_Log::record('mail_send_failed', [
                'account_id' => $account['id'],
                'details' => ['error' => $result->get_error_message()],
            ]);
            return $result;
        }

        V24_SMH_Audit_Log::record('mail_sent', [
            'account_id' => $account['id'],
            'entity_type' => 'message',
            'details' => ['to_count' => count($message['to'])],
        ]);

        return [
            'sent' => true,
            'message_id' => $result['message_id'] ?? '',
        ];
    }

    public function reply(int $message_id, array $input)
    {
        $context = $this->load_message_context($message_id, 'v24_smh_send_mail');
        if (is_wp_error($context)) {
            return $context;
        }

        $message = $context['message'];
        $account = $context['account'];
        $body_html = wp_kses_post($input['body'] ?? '');
        if ($body_html === '') {
            return new WP_Error('validation_error', 'Il corpo della risposta e obbligatorio.', ['status' => 400]);
        }

        $reply_all = !empty($input['reply_all']);
        $to = $this->build_reply_recipients($account, $message, $context['recipients'], $reply_all);
        if (!$to) {
            return new WP_Error('validation_error', 'Destinatario della risposta non disponibile.', ['status' => 400]);
        }

        $cc = $reply_all ? $this->build_reply_cc($account, $context['recipients']) : [];
        $subject = $this->prefix_subject($message['subject'] ?? '', 'Re');
        $quoted_body = $this->build_quoted_body($message, $context['body']);

        $result = (new V24_SMH_SMTP_Client())->send($account, [
            'to' => $to,
            'cc' => $cc,
            'bcc' => [],
            'subject' => $subject,
            'body' => $body_html . $quoted_body,
            'body_plain' => wp_strip_all_tags($body_html) . "\n\n" . $this->quoted_plain_body($message, $context['body']),
            'body_format' => 'html',
            'headers' => $this->reply_headers($message),
        ]);

        if (is_wp_error($result)) {
            V24_SMH_Audit_Log::record('mail_reply_failed', [
                'account_id' => $account['id'],
                'entity_type' => 'message',
                'entity_id' => $message['id'],
                'details' => ['error' => $result->get_error_message()],
            ]);
            return $result;
        }

        V24_SMH_Audit_Log::record('mail_replied', [
            'account_id' => $account['id'],
            'entity_type' => 'message',
            'entity_id' => $message['id'],
            'details' => ['reply_all' => $reply_all ? 1 : 0],
        ]);

        return [
            'sent' => true,
            'message_id' => $result['message_id'] ?? '',
        ];
    }

    public function forward(int $message_id, array $input)
    {
        $context = $this->load_message_context($message_id, 'v24_smh_send_mail');
        if (is_wp_error($context)) {
            return $context;
        }

        $account = $context['account'];
        $message = $context['message'];
        $to = $this->sanitize_addresses((array) ($input['to'] ?? []));
        if (!$to) {
            return new WP_Error('validation_error', 'Inserisci almeno un destinatario per l inoltro.', ['status' => 400]);
        }

        $body_html = wp_kses_post($input['body'] ?? '');
        $subject = $this->prefix_subject($message['subject'] ?? '', 'Fwd');
        $quoted_body = $this->build_quoted_body($message, $context['body']);
        $temp_files = [];
        $attachments = [];

        if (!array_key_exists('include_original_attachments', $input) || filter_var($input['include_original_attachments'], FILTER_VALIDATE_BOOLEAN)) {
            $attachments = $this->build_forward_attachments($context, $temp_files);
        }

        try {
            $result = (new V24_SMH_SMTP_Client())->send($account, [
                'to' => $to,
                'cc' => $this->sanitize_addresses((array) ($input['cc'] ?? [])),
                'bcc' => $this->sanitize_addresses((array) ($input['bcc'] ?? [])),
                'subject' => $subject,
                'body' => $body_html . $quoted_body,
                'body_plain' => wp_strip_all_tags($body_html) . "\n\n" . $this->quoted_plain_body($message, $context['body']),
                'body_format' => 'html',
                'attachments' => $attachments,
            ]);
        } finally {
            $this->cleanup_temp_files($temp_files);
        }

        if (is_wp_error($result)) {
            V24_SMH_Audit_Log::record('mail_forward_failed', [
                'account_id' => $account['id'],
                'entity_type' => 'message',
                'entity_id' => $message['id'],
                'details' => ['error' => $result->get_error_message()],
            ]);
            return $result;
        }

        V24_SMH_Audit_Log::record('mail_forwarded', [
            'account_id' => $account['id'],
            'entity_type' => 'message',
            'entity_id' => $message['id'],
            'details' => ['to_count' => count($to)],
        ]);

        return [
            'sent' => true,
            'message_id' => $result['message_id'] ?? '',
        ];
    }

    private function build_outgoing_message(array $input)
    {
        $to = $this->sanitize_addresses((array) ($input['to'] ?? []));
        if (!$to) {
            return new WP_Error('validation_error', 'Almeno un destinatario e richiesto.', ['status' => 400]);
        }

        $subject = sanitize_text_field($input['subject'] ?? '');
        $body = wp_kses_post($input['body'] ?? '');

        return [
            'to' => $to,
            'cc' => $this->sanitize_addresses((array) ($input['cc'] ?? [])),
            'bcc' => $this->sanitize_addresses((array) ($input['bcc'] ?? [])),
            'subject' => $subject,
            'body' => $body,
            'body_plain' => wp_strip_all_tags($body),
            'body_format' => sanitize_key($input['body_format'] ?? 'html'),
        ];
    }

    private function load_account_context(int $account_id)
    {
        if (!V24_SMH_Permissions::current_user_can_account($account_id, 'v24_smh_send_mail')) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        $account = (new V24_SMH_Account_Repository())->find($account_id);
        if (!$account) {
            return new WP_Error('invalid_account', 'Account non trovato.', ['status' => 404]);
        }

        return ['account' => $account];
    }

    private function load_message_context(int $message_id, string $capability)
    {
        $message = (new V24_SMH_Mail_Repository())->find($message_id);
        if (!$message) {
            return new WP_Error('message_not_found', 'Messaggio non trovato.', ['status' => 404]);
        }

        if (!V24_SMH_Permissions::current_user_can_account((int) $message['account_id'], $capability)) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        $account = (new V24_SMH_Account_Repository())->find((int) $message['account_id']);
        $mail_repository = new V24_SMH_Mail_Repository();
        $body = $mail_repository->get_body($message_id);
        $recipients = $mail_repository->get_recipients($message_id);
        if (!$account) {
            return new WP_Error('invalid_account', 'Account non trovato.', ['status' => 404]);
        }

        if (!$body || !$recipients) {
            $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
            if ($folder) {
                $payload = (new V24_SMH_IMAP_Client())->fetch_message_payload($account, $folder['full_name'], (int) $message['uid']);
                if (!is_wp_error($payload)) {
                    $mail_repository->save_body($message_id, $payload['body']);
                    $mail_repository->replace_recipients($message_id, $payload['recipients'] ?? []);
                    (new V24_SMH_Attachment_Repository())->replace_for_message((int) $message['id'], (int) $message['account_id'], $payload['attachments'] ?? []);
                    $body = $mail_repository->get_body($message_id);
                    $recipients = $mail_repository->get_recipients($message_id);
                }
            }
        }

        return [
            'account' => $account,
            'message' => $message,
            'body' => $body ?: [],
            'recipients' => $recipients,
        ];
    }

    private function sanitize_addresses(array $addresses): array
    {
        return array_values(array_unique(array_filter(array_map('sanitize_email', $addresses))));
    }

    private function build_reply_recipients(array $account, array $message, array $recipients, bool $reply_all): array
    {
        $primary = [];
        if (!empty($message['reply_to_email'])) {
            $primary[] = sanitize_email($message['reply_to_email']);
        } elseif (!empty($message['from_email'])) {
            $primary[] = sanitize_email($message['from_email']);
        }

        if ($reply_all) {
            foreach ($recipients as $recipient) {
                if (($recipient['type'] ?? '') !== 'to') {
                    continue;
                }

                $email = sanitize_email($recipient['email'] ?? '');
                if ($email !== '' && strtolower($email) !== strtolower($account['email_address'])) {
                    $primary[] = $email;
                }
            }
        }

        return array_values(array_unique(array_filter($primary)));
    }

    private function build_reply_cc(array $account, array $recipients): array
    {
        $cc = [];
        foreach ($recipients as $recipient) {
            if (!in_array($recipient['type'] ?? '', ['cc'], true)) {
                continue;
            }

            $email = sanitize_email($recipient['email'] ?? '');
            if ($email !== '' && strtolower($email) !== strtolower($account['email_address'])) {
                $cc[] = $email;
            }
        }

        return array_values(array_unique($cc));
    }

    private function reply_headers(array $message): array
    {
        $headers = [];
        if (!empty($message['message_id'])) {
            $headers[] = [
                'name' => 'In-Reply-To',
                'value' => '<' . trim((string) $message['message_id'], '<>') . '>',
            ];

            $references = trim((string) ($message['references_header'] ?? ''));
            if ($references !== '') {
                $headers[] = [
                    'name' => 'References',
                    'value' => $references . ' <' . trim((string) $message['message_id'], '<>') . '>',
                ];
            } else {
                $headers[] = [
                    'name' => 'References',
                    'value' => '<' . trim((string) $message['message_id'], '<>') . '>',
                ];
            }
        }

        return $headers;
    }

    private function prefix_subject(string $subject, string $prefix): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return $prefix . ':';
        }

        if (stripos($subject, $prefix . ':') === 0) {
            return $subject;
        }

        return $prefix . ': ' . $subject;
    }

    private function build_quoted_body(array $message, array $body): string
    {
        $source = $body['body_html_sanitized'] ?? '';
        if ($source === '') {
            $source = nl2br(esc_html($body['body_plain'] ?? ''));
        }

        $from = esc_html($message['from_name'] ?: $message['from_email']);
        $date = esc_html($message['date_received'] ?? '');
        $subject = esc_html($message['subject'] ?? '');

        return sprintf(
            '<hr><p><strong>Messaggio originale</strong><br>Da: %s<br>Data: %s<br>Oggetto: %s</p><blockquote>%s</blockquote>',
            $from,
            $date,
            $subject,
            $source
        );
    }

    private function quoted_plain_body(array $message, array $body): string
    {
        $source = trim((string) ($body['body_plain'] ?? wp_strip_all_tags($body['body_html_sanitized'] ?? '')));
        $lines = array_map(static function ($line) {
            return '> ' . $line;
        }, preg_split("/\r\n|\r|\n/", $source) ?: []);

        return sprintf(
            "Messaggio originale\nDa: %s\nData: %s\nOggetto: %s\n\n%s",
            $message['from_name'] ?: $message['from_email'],
            $message['date_received'] ?? '',
            $message['subject'] ?? '',
            implode("\n", $lines)
        );
    }

    private function build_forward_attachments(array $context, array &$temp_files): array
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $message = $context['message'];
        $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
        if (!$folder) {
            return [];
        }

        $settings = get_option('v24_smh_settings', []);
        $max_attachment_size_mb = max(1, (int) (is_array($settings) ? ($settings['max_attachment_size_mb'] ?? 15) : 15));
        $max_bytes = $max_attachment_size_mb * 1024 * 1024;
        $attachments = (new V24_SMH_Attachment_Repository())->list_by_message((int) $message['id']);
        $prepared = [];
        $imap = new V24_SMH_IMAP_Client();

        foreach ($attachments as $attachment) {
            if (!empty($attachment['is_inline'])) {
                continue;
            }

            if (!empty($attachment['size_bytes']) && (int) $attachment['size_bytes'] > $max_bytes) {
                continue;
            }

            $payload = $imap->fetch_attachment_content(
                $context['account'],
                $folder['full_name'],
                (int) $message['uid'],
                (string) $attachment['part_id']
            );

            if (is_wp_error($payload)) {
                continue;
            }

            $filename = sanitize_file_name($payload['filename'] ?: ($attachment['filename'] ?? 'forwarded-attachment.bin'));
            if ($filename === '') {
                $filename = 'forwarded-attachment.bin';
            }

            $temp_dir = rtrim(get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'v24-smh-' . wp_generate_uuid4();
            if (!wp_mkdir_p($temp_dir)) {
                continue;
            }

            $temp_path = $temp_dir . DIRECTORY_SEPARATOR . wp_unique_filename($temp_dir, $filename);

            if (file_put_contents($temp_path, $payload['content']) === false) {
                @unlink($temp_path);
                @rmdir($temp_dir);
                continue;
            }

            $temp_files[] = $temp_path;
            $temp_files[] = $temp_dir;
            $prepared[] = [
                'path' => $temp_path,
                'name' => $filename,
            ];
        }

        return $prepared;
    }

    private function cleanup_temp_files(array $temp_files): void
    {
        foreach ($temp_files as $temp_file) {
            if (!is_string($temp_file) || $temp_file === '') {
                continue;
            }

            if (is_file($temp_file)) {
                @unlink($temp_file);
                continue;
            }

            if (is_dir($temp_file)) {
                @rmdir($temp_file);
            }
        }
    }
}
