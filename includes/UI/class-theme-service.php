<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Theme_Service
{
    private const USER_META_KEY = 'v24_smh_ui_theme';

    public function current_theme_for_user(?int $user_id = null): string
    {
        $resolved_user_id = $user_id ?: get_current_user_id();
        if ($resolved_user_id <= 0) {
            return 'dark';
        }

        return $this->normalize_theme(get_user_meta($resolved_user_id, self::USER_META_KEY, true));
    }

    public function save_theme(array $input): array
    {
        $user_id = get_current_user_id();
        $theme = $this->normalize_theme($input['theme'] ?? 'dark');

        if ($user_id > 0) {
            update_user_meta($user_id, self::USER_META_KEY, $theme);
        }

        return [
            'saved' => $user_id > 0,
            'theme' => $theme,
            'storage_key' => $this->storage_key_for_user($user_id),
        ];
    }

    public function storage_key_for_user(?int $user_id = null): string
    {
        $resolved_user_id = $user_id ?: get_current_user_id();
        return sprintf('v24-smh-theme:%s', $resolved_user_id > 0 ? $resolved_user_id : 'guest');
    }

    public function normalize_theme($value): string
    {
        return sanitize_key((string) $value) === 'light' ? 'light' : 'dark';
    }
}
