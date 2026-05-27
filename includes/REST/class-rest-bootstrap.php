<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_REST_Bootstrap
{
    public const NAMESPACE = 'v24-ai-office/v1/smartmail';

    public static function register_routes(): void
    {
        (new V24_SMH_REST_Accounts_Controller())->register_routes();
        (new V24_SMH_REST_Mail_Controller())->register_routes();
        (new V24_SMH_REST_Contacts_Controller())->register_routes();
        (new V24_SMH_REST_Calendar_Controller())->register_routes();
        (new V24_SMH_REST_Tasks_Controller())->register_routes();
        (new V24_SMH_REST_Rules_Controller())->register_routes();
        (new V24_SMH_REST_AI_Controller())->register_routes();
        (new V24_SMH_REST_UI_Controller())->register_routes();
    }

    public static function success($data = null, array $extra = []): WP_REST_Response
    {
        return rest_ensure_response(array_merge(['success' => true, 'data' => $data], $extra));
    }

    public static function arg_id(string $description = 'ID'): array
    {
        return [
            'description' => $description,
            'type' => 'integer',
            'required' => true,
            'minimum' => 1,
            'sanitize_callback' => 'absint',
            'validate_callback' => static fn($value) => (int) $value > 0,
        ];
    }

    public static function arg_optional_id(string $description = 'ID'): array
    {
        $arg = self::arg_id($description);
        $arg['required'] = false;
        return $arg;
    }

    public static function arg_search(): array
    {
        return [
            'description' => 'Testo di ricerca.',
            'type' => 'string',
            'required' => false,
            'sanitize_callback' => 'sanitize_text_field',
        ];
    }

    public static function arg_bool(string $description): array
    {
        return [
            'description' => $description,
            'type' => 'boolean',
            'required' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ];
    }

    public static function arg_text(string $description, bool $required = false): array
    {
        return [
            'description' => $description,
            'type' => 'string',
            'required' => $required,
            'sanitize_callback' => 'sanitize_text_field',
        ];
    }

    public static function arg_date(string $description): array
    {
        return [
            'description' => $description,
            'type' => 'string',
            'required' => false,
            'sanitize_callback' => 'sanitize_text_field',
        ];
    }

    public static function pagination_args(): array
    {
        return [
            'page' => [
                'description' => 'Pagina dei risultati.',
                'type' => 'integer',
                'required' => false,
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description' => 'Numero elementi per pagina.',
                'type' => 'integer',
                'required' => false,
                'default' => 25,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
