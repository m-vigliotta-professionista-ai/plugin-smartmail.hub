<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_SMTP_Client
{
    public function test(array $account)
    {
        $transport = $this->transport_mode();
        if ($transport === 'wp_mail') {
            return [
                'transport' => 'wp_mail',
                'smtp' => false,
            ];
        }

        $smtp_result = $this->test_smtp($account);
        if (!is_wp_error($smtp_result)) {
            return [
                'transport' => 'smtp',
                'smtp' => true,
            ];
        }

        if ($transport === 'auto') {
            return [
                'transport' => 'wp_mail',
                'smtp' => false,
                'warning' => $smtp_result->get_error_message(),
            ];
        }

        return $smtp_result;
    }

    public function send(array $account, array $message)
    {
        $transport = $this->transport_mode();
        $smtp_error = null;

        if ($transport !== 'wp_mail') {
            $smtp_result = $this->send_via_smtp($account, $message);
            if (!is_wp_error($smtp_result)) {
                $smtp_result['transport_used'] = 'smtp';
                return $smtp_result;
            }

            $smtp_error = $smtp_result;
            if ($transport === 'smtp') {
                return $smtp_result;
            }
        }

        $wp_mail_result = $this->send_via_wp_mail($account, $message);
        if (!is_wp_error($wp_mail_result)) {
            $wp_mail_result['transport_used'] = 'wp_mail';
            return $wp_mail_result;
        }

        if ($smtp_error) {
            return new WP_Error(
                'smtp_failed',
                $smtp_error->get_error_message() . ' Fallback WordPress non riuscito: ' . $wp_mail_result->get_error_message(),
                ['status' => 502]
            );
        }

        return $wp_mail_result;
    }

    private function test_smtp(array $account)
    {
        $mailer = $this->mailer($account);
        if (is_wp_error($mailer)) {
            return $mailer;
        }

        try {
            return $mailer->smtpConnect() ? true : new WP_Error('smtp_failed', 'Connessione SMTP non riuscita.');
        } catch (Exception $e) {
            return new WP_Error('smtp_failed', $e->getMessage());
        } finally {
            if (method_exists($mailer, 'smtpClose')) {
                $mailer->smtpClose();
            }
        }
    }

    private function send_via_smtp(array $account, array $message)
    {
        $mailer = $this->mailer($account);
        if (is_wp_error($mailer)) {
            return $mailer;
        }

        try {
            $from_name = $account['display_name'] ?: $account['email_address'];
            $mailer->setFrom($account['email_address'], $from_name);

            foreach ((array) ($message['reply_to'] ?? []) as $reply_to) {
                if (empty($reply_to['email'])) {
                    continue;
                }

                $mailer->addReplyTo(
                    sanitize_email($reply_to['email']),
                    sanitize_text_field($reply_to['name'] ?? '')
                );
            }

            foreach ((array) ($message['to'] ?? []) as $to) {
                $mailer->addAddress($to);
            }
            foreach ((array) ($message['cc'] ?? []) as $cc) {
                $mailer->addCC($cc);
            }
            foreach ((array) ($message['bcc'] ?? []) as $bcc) {
                $mailer->addBCC($bcc);
            }
            foreach ((array) ($message['headers'] ?? []) as $header) {
                if (empty($header['name']) || !array_key_exists('value', $header)) {
                    continue;
                }

                $mailer->addCustomHeader((string) $header['name'], (string) $header['value']);
            }
            foreach ((array) ($message['attachments'] ?? []) as $attachment) {
                if (!empty($attachment['path'])) {
                    $mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }

            $mailer->Subject = (string) ($message['subject'] ?? '');
            $mailer->isHTML(($message['body_format'] ?? 'html') === 'html');
            $mailer->Body = (string) ($message['body'] ?? '');
            $mailer->AltBody = wp_strip_all_tags((string) ($message['body_plain'] ?? $message['body'] ?? ''));
            $mailer->send();

            return [
                'message_id' => trim((string) $mailer->getLastMessageID(), '<>'),
            ];
        } catch (Exception $e) {
            return new WP_Error('smtp_failed', $e->getMessage(), ['status' => 502]);
        }
    }

    private function send_via_wp_mail(array $account, array $message)
    {
        if (!function_exists('wp_mail')) {
            return new WP_Error('wp_mail_missing', 'Funzione WordPress wp_mail non disponibile.', ['status' => 500]);
        }

        $to = array_values(array_filter(array_map('sanitize_email', (array) ($message['to'] ?? []))));
        if (!$to) {
            return new WP_Error('validation_error', 'Almeno un destinatario e richiesto.', ['status' => 400]);
        }

        $from_email = sanitize_email($account['email_address'] ?? '');
        if ($from_email === '') {
            return new WP_Error('validation_error', 'Mittente non valido.', ['status' => 400]);
        }

        $from_name = sanitize_text_field($account['display_name'] ?: $from_email);
        $headers = [
            'From: ' . $this->format_address_header($from_email, $from_name),
            (($message['body_format'] ?? 'html') === 'html')
                ? 'Content-Type: text/html; charset=UTF-8'
                : 'Content-Type: text/plain; charset=UTF-8',
        ];

        $cc = array_values(array_filter(array_map('sanitize_email', (array) ($message['cc'] ?? []))));
        if ($cc) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        $bcc = array_values(array_filter(array_map('sanitize_email', (array) ($message['bcc'] ?? []))));
        if ($bcc) {
            $headers[] = 'Bcc: ' . implode(', ', $bcc);
        }

        foreach ((array) ($message['reply_to'] ?? []) as $reply_to) {
            $reply_to_email = sanitize_email($reply_to['email'] ?? '');
            if ($reply_to_email === '') {
                continue;
            }

            $headers[] = 'Reply-To: ' . $this->format_address_header(
                $reply_to_email,
                sanitize_text_field($reply_to['name'] ?? '')
            );
        }

        foreach ((array) ($message['headers'] ?? []) as $header) {
            $name = trim((string) ($header['name'] ?? ''));
            if ($name === '' || !array_key_exists('value', $header)) {
                continue;
            }

            $headers[] = $name . ': ' . (string) $header['value'];
        }

        $attachments = [];
        foreach ((array) ($message['attachments'] ?? []) as $attachment) {
            $path = isset($attachment['path']) ? (string) $attachment['path'] : '';
            if ($path !== '' && file_exists($path)) {
                $attachments[] = $path;
            }
        }

        $body = (($message['body_format'] ?? 'html') === 'html')
            ? (string) ($message['body'] ?? '')
            : (string) ($message['body_plain'] ?? $message['body'] ?? '');

        $from_filter = static function () use ($from_email) {
            return $from_email;
        };
        $from_name_filter = static function () use ($from_name) {
            return $from_name;
        };

        add_filter('wp_mail_from', $from_filter);
        add_filter('wp_mail_from_name', $from_name_filter);

        try {
            $sent = wp_mail($to, (string) ($message['subject'] ?? ''), $body, $headers, $attachments);
            global $phpmailer;

            if (!$sent) {
                $error = (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo))
                    ? trim((string) $phpmailer->ErrorInfo)
                    : '';
                return new WP_Error('wp_mail_failed', $error ?: 'Invio WordPress non riuscito.', ['status' => 502]);
            }

            $message_id = '';
            if (isset($phpmailer) && is_object($phpmailer) && method_exists($phpmailer, 'getLastMessageID')) {
                $message_id = trim((string) $phpmailer->getLastMessageID(), '<>');
            }

            return [
                'message_id' => $message_id,
            ];
        } finally {
            remove_filter('wp_mail_from', $from_filter);
            remove_filter('wp_mail_from_name', $from_name_filter);
        }
    }

    private function mailer(array $account)
    {
        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $account['smtp_host'];
        $mailer->Port = (int) $account['smtp_port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = V24_SMH_Encryption::decrypt($account['encrypted_username'] ?? '');
        $mailer->Password = V24_SMH_Encryption::decrypt($account['encrypted_secret'] ?? '');
        $mailer->SMTPSecure = ((int) $account['smtp_port'] === 465)
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 20;
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mailer->CharSet = 'UTF-8';
        return $mailer;
    }

    private function transport_mode(): string
    {
        $settings = get_option('v24_smh_settings', []);
        $transport = sanitize_key(is_array($settings) ? ($settings['outbound_transport'] ?? 'auto') : 'auto');
        return in_array($transport, ['auto', 'smtp', 'wp_mail'], true) ? $transport : 'auto';
    }

    private function format_address_header(string $email, string $name): string
    {
        $email = sanitize_email($email);
        $name = trim($name);
        if ($name === '') {
            return $email;
        }

        if (function_exists('mb_encode_mimeheader')) {
            $name = mb_encode_mimeheader($name, 'UTF-8', 'B', "\r\n");
        }

        return sprintf('%s <%s>', $name, $email);
    }
}
