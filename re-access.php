<?php
/**
 * Plugin Name: RE:Access
 * Plugin URI: https://github.com/TaniyanR/RE-Access
 * Description: WordPress plugin for visualizing and reciprocating access circulation through mutual RSS/links
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: TaniyanR
 * Author URI: https://github.com/TaniyanR
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: re-access
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin constants
define('RE_ACCESS_VERSION', '1.0.0');
define('RE_ACCESS_PLUGIN_FILE', __FILE__);
define('RE_ACCESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RE_ACCESS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
require_once RE_ACCESS_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Activation hook: Save plugin version
 */
function re_access_activate() {
    update_option('re_access_version', RE_ACCESS_VERSION);
}
register_activation_hook(__FILE__, 're_access_activate');

/**
 * Add admin menu
 */
function re_access_admin_menu() {
    add_menu_page(
        __('RE:Access', 're-access'),           // Page title
        __('RE:Access', 're-access'),           // Menu title
        'manage_options',                       // Capability
        're-access',                            // Menu slug
        're_access_dashboard_page',             // Callback function
        'dashicons-chart-line',                 // Icon
        79                                      // Position (above Settings which is 80)
    );
}
add_action('admin_menu', 're_access_admin_menu');

/**
 * Dashboard page callback
 */
function re_access_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('RE:Access Dashboard', 're-access'); ?></h1>
        <p><?php echo esc_html__('Welcome to RE:Access version', 're-access') . ' ' . RE_ACCESS_VERSION; ?></p>
    </div>
    <?php
}

/**
 * Initialize plugin update checker
 */
// function re_access_init_update_checker() {
//     if (!is_admin()) {
//         return;
//     }
//     
//     $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
//         'https://github.com/TaniyanR/RE-Access',
//         __FILE__,
//         're-access'
//     );
//     
//     // Set the branch for updates (defaults to 'main')
//     $updateChecker->setBranch('main');
//     
//     // Enable release assets (for GitHub Releases)
//     $updateChecker->getVcsApi()->enableReleaseAssets();
// }
// add_action('plugins_loaded', 're_access_init_update_checker');
