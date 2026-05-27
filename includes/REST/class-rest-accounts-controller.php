<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_REST_Accounts_Controller
{
    public function register_routes(): void
    {
        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/accounts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => static fn() => current_user_can('v24_smh_read_mail'),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => static fn() => current_user_can('v24_smh_manage_accounts'),
                'args' => $this->account_payload_args(),
            ],
        ]);
        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/accounts/(?P<id>\d+)/test', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'test'],
            'permission_callback' => static fn() => current_user_can('v24_smh_manage_accounts'),
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID account da testare.'),
            ],
        ]);
        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/accounts/(?P<id>\d+)/sync', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync'],
            'permission_callback' => static fn(WP_REST_Request $request) => V24_SMH_Permissions::current_user_can_account((int) $request['id']),
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID account da sincronizzare.'),
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        return V24_SMH_REST_Bootstrap::success((new V24_SMH_Account_Service())->list_visible());
    }

    public function create(WP_REST_Request $request)
    {
        $result = (new V24_SMH_Account_Service())->create($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function test(WP_REST_Request $request)
    {
        $result = (new V24_SMH_Account_Service())->test((int) $request['id']);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        $result = (new V24_SMH_Mail_Sync_Service())->sync_account((int) $request['id']);
        if (is_wp_error($result)) {
            return $result;
        }

        return V24_SMH_REST_Bootstrap::success([
            'status' => 'done',
            'result' => $result,
        ]);
    }

    private function account_payload_args(): array
    {
        return [
            'label' => V24_SMH_REST_Bootstrap::arg_text('Etichetta account.'),
            'email_address' => [
                'description' => 'Indirizzo email account.',
                'type' => 'string',
                'required' => true,
                'format' => 'email',
                'sanitize_callback' => 'sanitize_email',
            ],
            'display_name' => V24_SMH_REST_Bootstrap::arg_text('Nome mittente visualizzato.'),
            'imap_host' => V24_SMH_REST_Bootstrap::arg_text('Host IMAP.', true),
            'imap_port' => [
                'description' => 'Porta IMAP.',
                'type' => 'integer',
                'required' => false,
                'minimum' => 1,
                'maximum' => 65535,
                'sanitize_callback' => 'absint',
            ],
            'imap_encryption' => [
                'description' => 'Crittografia IMAP.',
                'type' => 'string',
                'required' => false,
                'enum' => ['ssl_tls', 'tls', 'none'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'smtp_host' => V24_SMH_REST_Bootstrap::arg_text('Host SMTP.', true),
            'smtp_port' => [
                'description' => 'Porta SMTP.',
                'type' => 'integer',
                'required' => false,
                'minimum' => 1,
                'maximum' => 65535,
                'sanitize_callback' => 'absint',
            ],
            'smtp_encryption' => [
                'description' => 'Crittografia SMTP.',
                'type' => 'string',
                'required' => false,
                'enum' => ['ssl_tls', 'tls', 'none'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'username' => V24_SMH_REST_Bootstrap::arg_text('Username IMAP/SMTP.'),
            'secret' => V24_SMH_REST_Bootstrap::arg_text('Password o app password.'),
            'owner_type' => [
                'description' => 'Visibilita account.',
                'type' => 'string',
                'required' => false,
                'enum' => ['user', 'shared'],
                'sanitize_callback' => 'sanitize_key',
            ],
        ];
    }
}
