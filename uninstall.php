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

// Clean up transients if any
delete_transient('re_access_*');
