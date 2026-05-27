<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_REST_AI_Controller
{
    public function register_routes(): void
    {
        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/bootstrap', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'bootstrap'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'account_id' => V24_SMH_REST_Bootstrap::arg_optional_id('ID account email.'),
                'message_id' => V24_SMH_REST_Bootstrap::arg_optional_id('ID messaggio.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/preferences', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_preferences'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/generate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generate'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/improve', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'improve'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/translate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'translate'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/export', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'export'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/preview-email', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'preview_email'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/send-email', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'send_email'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/tts', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'tts'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/stt', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'stt'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/responses/(?P<id>\d+)/rate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rate'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID risposta AI.'),
                'rating_value' => [
                    'description' => 'Valutazione della risposta AI.',
                    'type' => 'integer',
                    'required' => false,
                    'minimum' => 1,
                    'maximum' => 5,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/responses/(?P<id>\d+)/ignore', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'ignore'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID risposta AI.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/ai/responses/(?P<id>\d+)/update', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'update'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID risposta AI.'),
            ],
        ]);
    }

    public function bootstrap(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->bootstrap($request->get_params());
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function save_preferences(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->save_preferences($request->get_json_params() ?: []);
        return V24_SMH_REST_Bootstrap::success($result);
    }

    public function generate(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->generate($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function improve(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->improve($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function translate(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->translate($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function export(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->export($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function preview_email(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->preview_email($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function send_email(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->send_result_email($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function tts(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->text_to_speech($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function stt(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->audio_to_text($request->get_file_params(), $request->get_params());
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function rate(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->rate_response((int) $request['id'], $request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function ignore(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->ignore_response((int) $request['id'], $request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function update(WP_REST_Request $request)
    {
        $result = (new V24_SMH_AI_Service())->update_response((int) $request['id'], $request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }
}
