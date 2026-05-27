<?php
if (!defined('ABSPATH')) {
    exit;
}

final class V24_SMH_Plugin
{
    private static $instance = null;
    private $loaded = false;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->load_dependencies();
        $this->maybe_upgrade();
        $this->register_hooks();
        $this->loaded = true;
    }

    private function load_dependencies(): void
    {
        $files = [
            'includes/Security/class-capabilities.php',
            'includes/Security/class-encryption.php',
            'includes/Security/class-permissions.php',
            'includes/Logs/class-audit-log.php',
            'includes/Logs/class-sync-log.php',
            'includes/AI/class-ai-tenant-resolver.php',
            'includes/AI/class-ai-response-repository.php',
            'includes/AI/class-ai-service.php',
            'includes/UI/class-theme-service.php',
            'includes/UI/class-dashboard-navigation-widget.php',
            'includes/Accounts/class-account-repository.php',
            'includes/Accounts/class-account-service.php',
            'includes/Mail/class-folder-repository.php',
            'includes/Mail/class-mail-repository.php',
            'includes/Mail/class-attachment-repository.php',
            'includes/Mail/class-message-parser.php',
            'includes/Mail/class-imap-client.php',
            'includes/Mail/class-smtp-client.php',
            'includes/Mail/class-compose-service.php',
            'includes/Mail/class-mail-sync-service.php',
            'includes/Contacts/class-contacts-repository.php',
            'includes/Contacts/class-contacts-service.php',
            'includes/Calendar/class-calendars-repository.php',
            'includes/Calendar/class-events-repository.php',
            'includes/Calendar/class-event-attendees-repository.php',
            'includes/Calendar/class-calendar-recurrence.php',
            'includes/Calendar/class-calendar-service.php',
            'includes/Tasks/class-tasks-repository.php',
            'includes/Tasks/class-tasks-service.php',
            'includes/Rules/class-mail-rules-repository.php',
            'includes/Rules/class-mail-rules-service.php',
            'includes/Rules/class-mail-rules-engine.php',
            'includes/Jobs/class-job-repository.php',
            'includes/Jobs/class-cron.php',
            'includes/Jobs/class-sync-job-runner.php',
            'includes/REST/class-rest-bootstrap.php',
            'includes/REST/class-rest-accounts-controller.php',
            'includes/REST/class-rest-mail-controller.php',
            'includes/REST/class-rest-contacts-controller.php',
            'includes/REST/class-rest-calendar-controller.php',
            'includes/REST/class-rest-tasks-controller.php',
            'includes/REST/class-rest-rules-controller.php',
            'includes/REST/class-rest-ai-controller.php',
            'includes/REST/class-rest-ui-controller.php',
            'admin/class-admin-page.php',
            'public/class-shortcodes.php',
        ];

        foreach ($files as $file) {
            require_once V24_SMH_PLUGIN_DIR . $file;
        }
    }

    private function register_hooks(): void
    {
        V24_SMH_Admin_Page::register_hooks();
        V24_SMH_Dashboard_Navigation_Widget::register_hooks();
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_public_assets']);
        add_action('rest_api_init', ['V24_SMH_REST_Bootstrap', 'register_routes']);
        add_action('init', ['V24_SMH_Shortcodes', 'register']);
        V24_SMH_Cron::register_hooks();
    }

    private function maybe_upgrade(): void
    {
        $installed = (string) get_option('v24_smh_db_version', '');
        if ($installed === V24_SMH_VERSION) {
            return;
        }

        V24_SMH_Activator::create_tables();
        V24_SMH_Capabilities::add();
    }

    public function register_admin_assets(): void
    {
        wp_register_style('v24-smh-app', V24_SMH_PLUGIN_URL . 'assets/css/app.css', [], V24_SMH_VERSION);
        wp_register_script('v24-smh-app', V24_SMH_PLUGIN_URL . 'assets/js/app.js', ['wp-api-fetch'], V24_SMH_VERSION, true);
        wp_register_style('v24-smh-ai', V24_SMH_PLUGIN_URL . 'assets/css/ai.css', ['v24-smh-app'], V24_SMH_VERSION);
        wp_register_script('v24-smh-ai', V24_SMH_PLUGIN_URL . 'assets/js/ai.js', ['v24-smh-app'], V24_SMH_VERSION, true);
    }

    public function register_public_assets(): void
    {
        wp_register_style('v24-smh-app', V24_SMH_PLUGIN_URL . 'assets/css/app.css', [], V24_SMH_VERSION);
        wp_register_script('v24-smh-app', V24_SMH_PLUGIN_URL . 'assets/js/app.js', ['wp-api-fetch'], V24_SMH_VERSION, true);
        wp_register_style('v24-smh-ai', V24_SMH_PLUGIN_URL . 'assets/css/ai.css', ['v24-smh-app'], V24_SMH_VERSION);
        wp_register_script('v24-smh-ai', V24_SMH_PLUGIN_URL . 'assets/js/ai.js', ['v24-smh-app'], V24_SMH_VERSION, true);
    }
}
