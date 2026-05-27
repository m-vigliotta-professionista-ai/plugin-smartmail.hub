<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Account_Service
{
    private $repository;

    public function __construct(?V24_SMH_Account_Repository $repository = null)
    {
        $this->repository = $repository ?: new V24_SMH_Account_Repository();
    }

    public function create(array $input)
    {
        $payload = $this->validate_account_input($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $account_id = $this->repository->create($payload);

        V24_SMH_Audit_Log::record('account_created', [
            'account_id' => $account_id,
            'entity_type' => 'account',
            'entity_id' => $account_id,
        ]);

        return $this->safe_account($this->repository->find($account_id));
    }

    public function update(int $account_id, array $input)
    {
        $existing = $this->repository->find($account_id);
        if (!$existing) {
            return new WP_Error('invalid_account', 'Account non trovato.', ['status' => 404]);
        }

        if (!V24_SMH_Permissions::current_user_can_account($account_id, 'v24_smh_manage_accounts')) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        $payload = $this->validate_account_input($input, $existing);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $this->repository->update($account_id, $payload);

        V24_SMH_Audit_Log::record('account_updated', [
            'account_id' => $account_id,
            'entity_type' => 'account',
            'entity_id' => $account_id,
        ]);

        return $this->safe_account($this->repository->find($account_id));
    }

    public function save(array $input)
    {
        $account_id = (int) ($input['account_id'] ?? $input['id'] ?? 0);
        return $account_id > 0 ? $this->update($account_id, $input) : $this->create($input);
    }

    public function test(int $account_id)
    {
        $account = $this->repository->find($account_id);
        if (!$account) {
            return new WP_Error('invalid_account', 'Account non trovato.', ['status' => 404]);
        }
        if (!V24_SMH_Permissions::current_user_can_account($account_id, 'v24_smh_manage_accounts')) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        $imap = new V24_SMH_IMAP_Client();
        $smtp = new V24_SMH_SMTP_Client();
        $imap_result = $imap->test($account);
        $smtp_result = $smtp->test($account);

        if (is_wp_error($imap_result) || is_wp_error($smtp_result)) {
            $message = is_wp_error($imap_result) ? $imap_result->get_error_message() : $smtp_result->get_error_message();
            $this->repository->set_status($account_id, 'error', $message);
            return new WP_Error('connection_failed', $message, ['status' => 502]);
        }

        $this->repository->set_status($account_id, 'active');
        $transport = is_array($smtp_result) ? (string) ($smtp_result['transport'] ?? 'smtp') : 'smtp';
        $smtp_direct = is_array($smtp_result) ? !empty($smtp_result['smtp']) : true;
        $warning = is_array($smtp_result) ? (string) ($smtp_result['warning'] ?? '') : '';

        V24_SMH_Audit_Log::record('account_connection_tested', [
            'account_id' => $account_id,
            'details' => [
                'transport' => $transport,
                'smtp_direct' => $smtp_direct ? 1 : 0,
                'warning' => $warning,
            ],
        ]);

        return [
            'imap' => true,
            'smtp' => $smtp_direct,
            'transport' => $transport,
            'status' => 'active',
            'warning' => $warning,
        ];
    }

    public function list_visible(): array
    {
        $accounts = $this->repository->visible_for_user(get_current_user_id());
        return array_map([$this, 'safe_account'], $accounts);
    }

    public function safe_account(?array $account): ?array
    {
        if (!$account) {
            return null;
        }

        unset($account['encrypted_username'], $account['encrypted_secret'], $account['last_error']);
        return $account;
    }

    private function validate_account_input(array $input, ?array $existing = null)
    {
        $email = sanitize_email($input['email_address'] ?? ($existing['email_address'] ?? ''));
        if ($email === '') {
            return new WP_Error('validation_error', 'Indirizzo email non valido.', ['status' => 400]);
        }

        $imap_host = sanitize_text_field($input['imap_host'] ?? ($existing['imap_host'] ?? ''));
        $smtp_host = sanitize_text_field($input['smtp_host'] ?? ($existing['smtp_host'] ?? ''));
        if ($imap_host === '' || $smtp_host === '') {
            return new WP_Error('validation_error', 'Host IMAP e SMTP obbligatori.', ['status' => 400]);
        }

        $owner_type = sanitize_key($input['owner_type'] ?? ($existing['owner_type'] ?? 'user'));
        if (!in_array($owner_type, ['user', 'shared'], true)) {
            $owner_type = 'user';
        }

        $payload = [
            'owner_type' => $owner_type,
            'owner_id' => $owner_type === 'shared' ? 0 : get_current_user_id(),
            'label' => sanitize_text_field($input['label'] ?? ($existing['label'] ?? $email)),
            'email_address' => $email,
            'display_name' => sanitize_text_field($input['display_name'] ?? ($existing['display_name'] ?? '')),
            'provider_type' => 'imap_smtp',
            'imap_host' => $imap_host,
            'imap_port' => $this->sanitize_port($input['imap_port'] ?? ($existing['imap_port'] ?? 993), 993),
            'imap_encryption' => $this->sanitize_encryption($input['imap_encryption'] ?? ($existing['imap_encryption'] ?? 'ssl_tls')),
            'imap_auth_mode' => 'password',
            'smtp_host' => $smtp_host,
            'smtp_port' => $this->sanitize_port($input['smtp_port'] ?? ($existing['smtp_port'] ?? 465), 465),
            'smtp_encryption' => $this->sanitize_encryption($input['smtp_encryption'] ?? ($existing['smtp_encryption'] ?? 'ssl_tls')),
            'smtp_auth_mode' => 'password',
            'status' => 'pending',
        ];

        if (!empty($input['username'])) {
            $payload['encrypted_username'] = V24_SMH_Encryption::encrypt((string) $input['username']);
        } elseif (!$existing) {
            $payload['encrypted_username'] = V24_SMH_Encryption::encrypt($email);
        }

        if (array_key_exists('secret', $input) && (string) $input['secret'] !== '') {
            $payload['encrypted_secret'] = V24_SMH_Encryption::encrypt((string) $input['secret']);
        } elseif (!$existing) {
            return new WP_Error('validation_error', 'La password o app password e obbligatoria.', ['status' => 400]);
        }

        return $payload;
    }

    private function sanitize_port($value, int $fallback): int
    {
        $port = (int) $value;
        return ($port >= 1 && $port <= 65535) ? $port : $fallback;
    }

    private function sanitize_encryption($value): string
    {
        $value = sanitize_key((string) $value);
        return in_array($value, ['ssl_tls', 'tls', 'none'], true) ? $value : 'ssl_tls';
    }
}
