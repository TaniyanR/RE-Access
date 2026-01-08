<?php
/**
 * Uninstall script for RE:Access plugin
 *
 * @package ReAccess
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include database class
require_once plugin_dir_path(__FILE__) . 'includes/class-re-access-database.php';

// Drop all database tables
RE_Access_Database::drop_tables();

// Remove plugin options
delete_option('re_access_version');
delete_option('re_access_url_aliases');
delete_option('re_access_github_url');

// Clean up transients - WordPress doesn't support wildcard deletion
// So we delete specific known transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_reaccess_%' OR option_name LIKE '_transient_timeout_reaccess_%'");
