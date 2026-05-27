<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_AI_Tenant_Resolver
{
    public static function default_allowed_tenants(): array
    {
        return ['24aioffice', 'aioffice24', 'sfconsulting', 'studioregni'];
    }

    public static function default_profiles(): array
    {
        return [
            'default' => [
                'display_name' => 'Secure E-mail AI',
                'product_name' => 'Valore 24 AI Office',
                'accent' => '#0d5c63',
                'surface' => '#eef6f6',
                'badge' => 'Assistente Outlook-like',
            ],
            '24aioffice' => [
                'display_name' => 'Secure E-mail AI',
                'product_name' => 'Valore 24 AI Office',
                'accent' => '#132740',
                'surface' => '#eef2fb',
                'badge' => 'Tenant 24aioffice',
            ],
            'aioffice24' => [
                'display_name' => 'Secure E-mail AI',
                'product_name' => 'Valore 24 AI Office',
                'accent' => '#132740',
                'surface' => '#eef2fb',
                'badge' => 'Tenant aioffice24',
            ],
            'sfconsulting' => [
                'display_name' => 'Secure E-mail AI',
                'product_name' => 'SF Consulting Mail Workspace',
                'accent' => '#3657a6',
                'surface' => '#eef2fb',
                'badge' => 'Tenant SF Consulting',
            ],
            'studioregni' => [
                'display_name' => 'Secure E-mail AI',
                'product_name' => 'Studio Regni Mail Workspace',
                'accent' => '#6a4c2f',
                'surface' => '#f7f0e9',
                'badge' => 'Tenant Studio Regni',
            ],
        ];
    }

    public static function default_profiles_json(): string
    {
        return wp_json_encode(self::default_profiles(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function parse_profiles($profiles): array
    {
        if (is_array($profiles)) {
            return $profiles;
        }

        if (!is_string($profiles) || trim($profiles) === '') {
            return self::default_profiles();
        }

        $decoded = json_decode($profiles, true);
        if (!is_array($decoded)) {
            return self::default_profiles();
        }

        return array_replace_recursive(self::default_profiles(), $decoded);
    }

    public static function resolve(array $settings = [], ?string $host = null): array
    {
        $host = $host ?: self::current_host();
        $normalized_host = self::normalize_host($host);
        $allowed = self::parse_allowed_tenants($settings['ai_allowed_tenants'] ?? '');
        $profiles = self::parse_profiles($settings['ai_branding_profiles'] ?? '');

        $tenant_key = 'default';
        foreach ($allowed as $candidate) {
            if ($candidate !== '' && str_contains($normalized_host, strtolower($candidate))) {
                $tenant_key = $candidate;
                break;
            }
        }

        $profile = $profiles[$tenant_key] ?? $profiles['default'] ?? self::default_profiles()['default'];
        $endpoint = '';
        if (!empty($profile['endpoint'])) {
            $endpoint = self::normalize_endpoint((string) $profile['endpoint']);
        }

        if ($endpoint === '') {
            $endpoint = $tenant_key === 'default'
                ? self::normalize_endpoint((string) ($settings['ai_endpoint_default'] ?? ''))
                : self::normalize_endpoint((string) ($settings['ai_endpoint_tenant'] ?? ''));
        }

        return [
            'tenant_key' => $tenant_key,
            'matched' => $tenant_key !== 'default',
            'host' => $normalized_host,
            'endpoint' => $endpoint,
            'profile' => array_merge(self::default_profiles()['default'], is_array($profile) ? $profile : []),
        ];
    }

    public static function parse_allowed_tenants($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,;]+/', (string) $value) ?: [];
        }

        $items = array_values(array_filter(array_map(static function ($item) {
            $item = sanitize_key((string) $item);
            return $item !== '' ? $item : null;
        }, $items)));

        return $items ?: self::default_allowed_tenants();
    }

    public static function current_host(): string
    {
        $host = '';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = (string) wp_unslash($_SERVER['HTTP_HOST']);
        }

        if ($host === '') {
            $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        }

        return self::normalize_host($host);
    }

    public static function normalize_endpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }

        return trailingslashit($endpoint);
    }

    private static function normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        return preg_replace('/^www\./', '', $host) ?: '';
    }
}
