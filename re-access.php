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

// Load Composer autoloader (safe check)
$composer_autoload = RE_ACCESS_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

/*
 * Load plugin classes only when files exist to avoid fatal errors.
 * This keeps the bootstrap resilient while features are incrementally added.
 */
$maybe_require = function (string $path) {
    $full = RE_ACCESS_PLUGIN_DIR . $path;
    if (file_exists($full)) {
        require_once $full;
        return true;
    }
    return false;
};

$maybe_require('includes/class-re-access-database.php');
$maybe_require('includes/class-re-access-tracker.php');
$maybe_require('includes/class-re-access-notices.php');
$maybe_require('admin/class-re-access-dashboard.php');
$maybe_require('admin/class-re-access-sites.php');
$maybe_require('admin/class-re-access-ranking.php');
$maybe_require('admin/class-re-access-link-slots.php');
$maybe_require('admin/class-re-access-rss-slots.php');

/**
 * Activation hook: Create tables and save plugin version
 */
function re_access_activate() {
    try {
        // Create DB tables if the DB helper exists
        if (class_exists('RE_Access_Database')) {
            RE_Access_Database::create_tables();
        } else {
            throw new Exception('RE_Access_Database class not found. Check if includes/class-re-access-database.php exists and is valid.');
        }

        // Save plugin version (consistent option key)
        update_option('re_access_version', RE_ACCESS_VERSION);
        
        // Log successful activation
        error_log('RE:Access plugin activated successfully. Version: ' . RE_ACCESS_VERSION);
        
    } catch (Exception $e) {
        // Log the error
        error_log('RE:Access activation error: ' . $e->getMessage());
        
        // Show user-friendly error message
        wp_die(
            '<h1>RE:Access Activation Error</h1>' .
            '<p><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</p>' .
            '<p>Please check your PHP error log for more details.</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">Back to Plugins</a></p>',
            'Plugin Activation Error',
            ['response' => 500]
        );
    }
}
register_activation_hook(__FILE__, 're_access_activate');

/**
 * Load plugin text domain for translations
 */
function re_access_load_textdomain() {
    load_plugin_textdomain('re-access', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 're_access_load_textdomain');

/**
 * Initialize plugin
 */
function re_access_init() {
    // Run migrations if needed (class exists)
    if (class_exists('RE_Access_Database') && method_exists('RE_Access_Database', 'check_migrations')) {
        RE_Access_Database::check_migrations();
    }

    // Initialize tracking if available
    if (class_exists('RE_Access_Tracker')) {
        RE_Access_Tracker::init();
    }

    // Register shortcodes only when their handler classes exist
    if (class_exists('RE_Access_Notices')) {
        add_shortcode('reaccess_notice', ['RE_Access_Notices', 'shortcode_notice']);
        add_shortcode('reaccess_notice_latest', ['RE_Access_Notices', 'shortcode_notice_latest']);
    }

    if (class_exists('RE_Access_Ranking')) {
        add_shortcode('reaccess_ranking', ['RE_Access_Ranking', 'shortcode_ranking']);
    }

    if (class_exists('RE_Access_Link_Slots')) {
        add_shortcode('reaccess_link_slot', ['RE_Access_Link_Slots', 'shortcode_link_slot']);
    }

    if (class_exists('RE_Access_RSS_Slots')) {
        add_shortcode('reaccess_rss_slot', ['RE_Access_RSS_Slots', 'shortcode_rss_slot']);
    }

    if (class_exists('RE_Access_Sites') && method_exists('RE_Access_Sites', 'init')) {
        RE_Access_Sites::init();
    }
    
}
add_action('init', 're_access_init');

/**
 * Add admin menu (only register pages for classes that exist)
 */
function re_access_admin_menu() {
    global $menu;

    $menu_position = 79;
    if (is_array($menu)) {
        foreach ($menu as $index => $item) {
            if (isset($item[2]) && $item[2] === 'options-general.php') {
                $menu_position = max(0, (int) $index - 1);
                break;
            }
        }
    }

    // Main dashboard (fallback to simple callback if class missing)
    if (class_exists('RE_Access_Dashboard') && method_exists('RE_Access_Dashboard', 'render')) {
        $callback = ['RE_Access_Dashboard', 'render'];
    } else {
        $callback = 're_access_dashboard_page';
    }

    add_menu_page(
        __('RE:Access', 're-access'),
        __('RE:Access', 're-access'),
        'manage_options',
        're-access',
        $callback,
        'dashicons-chart-line',
        $menu_position
    );

    if (class_exists('RE_Access_Ranking') && method_exists('RE_Access_Ranking', 'render')) {
        add_submenu_page(
            're-access',
            __('Reverse Access Ranking', 're-access'),
            __('Ranking', 're-access'),
            'manage_options',
            're-access-ranking',
            ['RE_Access_Ranking', 'render']
        );
    }

    if (class_exists('RE_Access_Sites') && method_exists('RE_Access_Sites', 'render')) {
        add_submenu_page(
            're-access',
            __('Sites', 're-access'),
            __('Sites', 're-access'),
            'manage_options',
            're-access-sites',
            ['RE_Access_Sites', 'render']
        );
    }

    if (class_exists('RE_Access_Link_Slots') && method_exists('RE_Access_Link_Slots', 'render')) {
        add_submenu_page(
            're-access',
            __('Link Slots', 're-access'),
            __('Link Slots', 're-access'),
            'manage_options',
            're-access-link-slots',
            ['RE_Access_Link_Slots', 'render']
        );
    }

    if (class_exists('RE_Access_RSS_Slots') && method_exists('RE_Access_RSS_Slots', 'render')) {
        add_submenu_page(
            're-access',
            __('RSS Slots', 're-access'),
            __('RSS Slots', 're-access'),
            'manage_options',
            're-access-rss-slots',
            ['RE_Access_RSS_Slots', 'render']
        );
    }
    
}
add_action('admin_menu', 're_access_admin_menu');

/**
 * Dashboard page callback (fallback)
 */
function re_access_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('RE:Access Dashboard', 're-access'); ?></h1>
        <p><?php printf(esc_html__('Welcome to RE:Access version %s', 're-access'), RE_ACCESS_VERSION); ?></p>
        <p><?php esc_html_e('The full dashboard is available when all required classes are loaded.', 're-access'); ?></p>
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

    // Get GitHub URL from options (default to hardcoded URL)
    $github_url = get_option('re_access_github_url', 'https://github.com/TaniyanR/RE-Access');

    // Validate GitHub URL format
    if (!filter_var($github_url, FILTER_VALIDATE_URL) ||
        !preg_match('#^https://github\.com/[\w-]+/[\w-]+$#i', $github_url)) {
        // Fall back to default if invalid
        $github_url = 'https://github.com/TaniyanR/RE-Access';
    }

    if (class_exists('\\YahnisElsts\\PluginUpdateChecker\\v5p6\\PucFactory')) {
        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
            $github_url,
            __FILE__,
            're-access'
        );

        // Set the branch for updates (defaults to 'main')
        $updateChecker->setBranch('main');

        // Enable release assets (for GitHub Releases)
        $updateChecker->getVcsApi()->enableReleaseAssets();

        // Set authentication if token is defined and valid
        if (defined('REACCESS_GITHUB_TOKEN') && is_string(REACCESS_GITHUB_TOKEN) && !empty(REACCESS_GITHUB_TOKEN)) {
            $updateChecker->setAuthentication(REACCESS_GITHUB_TOKEN);
        }
    }
}
add_action('plugins_loaded', 're_access_init_update_checker');
