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
// require_once RE_ACCESS_PLUGIN_DIR . 'vendor/autoload.php';

// Load plugin classes
// require_once RE_ACCESS_PLUGIN_DIR . 'includes/class-re-access-database.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'includes/class-re-access-tracker.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'includes/class-re-access-notices.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'admin/class-re-access-dashboard.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'admin/class-re-access-sites.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'admin/class-re-access-ranking.php';
// require_once RE_ACCESS_PLUGIN_DIR . 'admin/class-re-access-link-slots.php';
require_once RE_ACCESS_PLUGIN_DIR . 'admin/class-re-access-rss-slots.php';

/**
 * Activation hook: Create tables and save plugin version
 */
function re_access_activate() {
    // RE_Access_Database::create_tables();
    update_option('re_access_version', RE_ACCESS_VERSION);
    // flush_rewrite_rules();
}
register_activation_hook(__FILE__, 're_access_activate');

/**
 * Initialize plugin
 */
function re_access_init() {
    // Initialize tracking
    // RE_Access_Tracker::init();
    
    // Initialize site management
    // RE_Access_Sites::init();
    
    // Register shortcodes
    // add_shortcode('reaccess_notice', ['RE_Access_Notices', 'shortcode_notice']);
    // add_shortcode('reaccess_notice_latest', ['RE_Access_Notices', 'shortcode_notice_latest']);
    // add_shortcode('reaccess_ranking', ['RE_Access_Ranking', 'shortcode_ranking']);
    // add_shortcode('reaccess_link_slot', ['RE_Access_Link_Slots', 'shortcode_link_slot']);
    // add_shortcode('reaccess_rss_slot', ['RE_Access_RSS_Slots', 'shortcode_rss_slot']);
}
// add_action('init', 're_access_init');

/**
 * Initialize shortcodes (RSS slots only)
 */
function re_access_init_rss() {
    add_shortcode('reaccess_rss_slot', ['RE_Access_RSS_Slots', 'shortcode_rss_slot']);
}
add_action('init', 're_access_init_rss');

/**
 * Add admin menu
 */
function re_access_admin_menu() {
    // Main dashboard
    add_menu_page(
        __('RE:Access', 're-access'),
        __('RE:Access', 're-access'),
        'manage_options',
        're-access',
        're_access_dashboard_page',
        'dashicons-chart-line',
        79
    );
    
    // Sites submenu
    // add_submenu_page(
    //     're-access',
    //     __('Site Registration', 're-access'),
    //     __('Sites', 're-access'),
    //     'manage_options',
    //     're-access-sites',
    //     ['RE_Access_Sites', 'render']
    // );
    
    // Ranking submenu
    // add_submenu_page(
    //     're-access',
    //     __('Reverse Access Ranking', 're-access'),
    //     __('Ranking', 're-access'),
    //     'manage_options',
    //     're-access-ranking',
    //     ['RE_Access_Ranking', 'render']
    // );
    
    // Link Slots submenu
    // add_submenu_page(
    //     're-access',
    //     __('Link Slots', 're-access'),
    //     __('Link Slots', 're-access'),
    //     'manage_options',
    //     're-access-link-slots',
    //     ['RE_Access_Link_Slots', 'render']
    // );
    
    // RSS Slots submenu
    add_submenu_page(
        're-access',
        __('RSS Slots', 're-access'),
        __('RSS Slots', 're-access'),
        'manage_options',
        're-access-rss-slots',
        ['RE_Access_RSS_Slots', 'render']
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
function re_access_init_update_checker() {
    if (!is_admin()) {
        return;
    }
    
    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/TaniyanR/RE-Access',
        __FILE__,
        're-access'
    );
    
    // Set the branch for updates (defaults to 'main')
    $updateChecker->setBranch('main');
    
    // Enable release assets (for GitHub Releases)
    $updateChecker->getVcsApi()->enableReleaseAssets();
}
// add_action('plugins_loaded', 're_access_init_update_checker');
