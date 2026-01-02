<?php
/**
 * Plugin Name: RE:Access
 * Plugin URI: https://github.com/TaniyanR/RE-Access
 * Description: A WordPress plugin for access tracking and management.
 * Version: 1.0.0
 * Author: TaniyanR
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: re-access
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('RE_ACCESS_VERSION', '1.0.0');
define('RE_ACCESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RE_ACCESS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activation hook
register_activation_hook(__FILE__, 're_access_activate');

function re_access_activate() {
    // Save version for future migrations
    update_option('re_access_version', RE_ACCESS_VERSION);
}

// Add admin menu
add_action('admin_menu', 're_access_add_admin_menu');

function re_access_add_admin_menu() {
    add_menu_page(
        'RE:Access',
        'RE:Access',
        'manage_options',
        're-access',
        're_access_admin_page',
        'dashicons-chart-line',
        80 // Position above Settings (Settings is 81)
    );
}

function re_access_admin_page() {
    echo '<div class="wrap"><h1>RE:Access</h1><p>Welcome to RE:Access plugin.</p></div>';
}

// Initialize plugin update checker
require_once RE_ACCESS_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
$update_checker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/TaniyanR/RE-Access', // Placeholder GitHub repo URL
    __FILE__,
    're-access'
);
$update_checker->getVcsApi()->enableReleaseAssets();
