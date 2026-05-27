<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Encryption
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return '';
        }

        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        if (!$payload || strpos($payload, 'v1:') !== 0 || !function_exists('openssl_decrypt')) {
            return '';
        }

        $raw = base64_decode(substr($payload, 3), true);
        if (!$raw || strlen($raw) < 28) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    public static function mask(?string $value): string
    {
        if (!$value) {
            return '';
        }
        return '********';
    }

    private static function key(): string
    {
        $material = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
            $material .= defined($constant) ? constant($constant) : $constant;
        }
        $site_url = function_exists('get_option') ? (string) get_option('siteurl', '') : '';
        if ($site_url === '') {
            $site_url = function_exists('home_url') ? (string) home_url('/') : '';
        }
        $material .= untrailingslashit($site_url);
        return hash('sha256', $material, true);
    }
}
