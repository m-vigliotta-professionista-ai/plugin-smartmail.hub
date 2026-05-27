<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Shortcodes
{
    public static function register(): void
    {
        add_shortcode('valore24_smartmail_hub', [self::class, 'render_app']);
    }

    public static function render_app(): string
    {
        wp_enqueue_style('v24-smh-app');
        wp_enqueue_style('v24-smh-ai');

        $login_state = self::handle_frontend_login();
        $login_error = $login_state['error'];

        ob_start();

        if (!is_user_logged_in()) {
            include V24_SMH_PLUGIN_DIR . 'templates/login.php';
            return (string) ob_get_clean();
        }

        if (!current_user_can('v24_smh_read_mail')) {
            include V24_SMH_PLUGIN_DIR . 'templates/forbidden.php';
            return (string) ob_get_clean();
        }

        $theme_service = new V24_SMH_Theme_Service();
        $theme_preference = $theme_service->current_theme_for_user(get_current_user_id());
        $theme_storage_key = $theme_service->storage_key_for_user(get_current_user_id());

        wp_enqueue_script('v24-smh-app');
        wp_enqueue_script('v24-smh-ai');

        self::render_theme_bootstrap($theme_preference, $theme_storage_key);
        include V24_SMH_PLUGIN_DIR . 'templates/app.php';
        return (string) ob_get_clean();
    }

    private static function render_theme_bootstrap(string $theme_preference, string $theme_storage_key): void
    {
        $payload = wp_json_encode([
            'serverTheme' => $theme_preference,
            'storageKey' => $theme_storage_key,
        ]);

        if (!$payload) {
            return;
        }

        echo '<script>(function(){var config=' . $payload . ';var theme=config.serverTheme==="light"?"light":"dark";try{var stored=window.localStorage.getItem(config.storageKey);if(stored==="light"||stored==="dark"){theme=stored;}}catch(error){}document.documentElement.setAttribute("data-v24-smh-theme",theme);document.documentElement.style.colorScheme=theme==="light"?"light":"dark";if(document.body){document.body.setAttribute("data-v24-smh-theme",theme);document.body.style.colorScheme=theme==="light"?"light":"dark";}else{document.addEventListener("DOMContentLoaded",function(){if(document.body){document.body.setAttribute("data-v24-smh-theme",theme);document.body.style.colorScheme=theme==="light"?"light":"dark";}},{once:true});}})();</script>';
    }

    private static function handle_frontend_login(): array
    {
        $state = [
            'error' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $state;
        }

        if (empty($_POST['v24_smh_frontend_login'])) {
            return $state;
        }

        $nonce = isset($_POST['v24_smh_login_nonce']) ? sanitize_text_field(wp_unslash($_POST['v24_smh_login_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'v24_smh_frontend_login')) {
            $state['error'] = 'Sessione non valida. Riprova.';
            return $state;
        }

        $credentials = [
            'user_login' => isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '',
            'user_password' => isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '',
            'remember' => !empty($_POST['rememberme']),
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            $state['error'] = $user->get_error_message();
            return $state;
        }

        wp_safe_redirect(get_permalink());
        exit;
    }
}
