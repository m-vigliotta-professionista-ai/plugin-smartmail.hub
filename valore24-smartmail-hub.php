<?php
/**
 * Plugin Name: Valore 24 AI Office - SmartMail Hub
 * Plugin URI: https://aioffice24.it
 * Description: Centrale intelligente per email, contatti e calendario aziendale nella piattaforma Valore 24 AI Office.
 * Version: 0.4.28
 * Author: Valore 24 AI Office
 * Text Domain: valore24-smartmail-hub
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('V24_SMH_VERSION', '0.4.28');
define('V24_SMH_PLUGIN_FILE', __FILE__);
define('V24_SMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('V24_SMH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('V24_SMH_TABLE_PREFIX', 'v24_smh_');

require_once V24_SMH_PLUGIN_DIR . 'includes/Core/class-plugin.php';
require_once V24_SMH_PLUGIN_DIR . 'includes/Core/class-activator.php';
require_once V24_SMH_PLUGIN_DIR . 'includes/Core/class-deactivator.php';

register_activation_hook(__FILE__, ['V24_SMH_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['V24_SMH_Deactivator', 'deactivate']);

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('valore24-smartmail-hub', false, dirname(plugin_basename(__FILE__)) . '/languages');
    V24_SMH_Plugin::instance()->boot();
});
