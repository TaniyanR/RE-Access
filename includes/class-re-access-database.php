<?php
/**
 * Database setup and management
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Site registrations table (for managing registered sites with approval workflow)
        $table_sites = $wpdb->prefix . 'reaccess_sites';
        $sql_sites = "CREATE TABLE $table_sites (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_name varchar(255) NOT NULL,
            site_url varchar(512) NOT NULL,
            rss_url varchar(512) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_sites);
        
        // Daily access metrics table (for IN/OUT/PV/UU)
        $table_daily = $wpdb->prefix . 'reaccess_daily';
        $sql_daily = "CREATE TABLE $table_daily (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            pv_count int(11) DEFAULT 0,
            uu_count int(11) DEFAULT 0,
            in_count int(11) DEFAULT 0,
            out_count int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY date (date),
            KEY date_index (date)
        ) $charset_collate;";
        dbDelta($sql_daily);
        
        // Site-specific daily tracking table (for site-specific IN/OUT tracking and ranking)
        $table_site_daily = $wpdb->prefix . 'reaccess_site_daily';
        $sql_site_daily = "CREATE TABLE $table_site_daily (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            date date NOT NULL,
            in_count int(11) DEFAULT 0,
            out_count int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY site_date (site_id, date),
            KEY site_id (site_id),
            KEY date (date),
            KEY site_id_date (site_id, date)
        ) $charset_collate;";
        dbDelta($sql_site_daily);
        
        // Notices/announcements table (for auto-generated announcements)
        $table_notice = $wpdb->prefix . 'reaccess_notice';
        $sql_notice = "CREATE TABLE $table_notice (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notice_type varchar(50) NOT NULL,
            notice_content text NOT NULL,
            site_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY notice_type (notice_type),
            KEY site_id (site_id)
        ) $charset_collate;";
        dbDelta($sql_notice);
    }
    
    /**
     * Drop database tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        // Define table names - these are safe as they're hardcoded
        $table_names = [
            'reaccess_sites',
            'reaccess_daily',
            'reaccess_site_daily',
            'reaccess_notice',
        ];
        
        foreach ($table_names as $table_name) {
            // Use wpdb prefix and proper escaping
            $table = $wpdb->prefix . $table_name;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
    
    /**
     * Check and run database migrations if needed
     * This function compares the saved version with the current version
     * and runs migrations for any intermediate versions.
     */
    public static function check_migrations() {
        $saved_version = get_option('reaccess_version', '0.0.0');
        $current_version = RE_ACCESS_VERSION;
        
        // If versions match, no migration needed
        if (version_compare($saved_version, $current_version, '>=')) {
            return;
        }
        
        // Run migrations based on version
        // Example: if upgrading from 1.0.0 to 1.1.0
        // if (version_compare($saved_version, '1.1.0', '<')) {
        //     self::migrate_to_1_1_0();
        // }
        
        // Update version after migrations
        update_option('reaccess_version', $current_version);
    }
    
    /**
     * Example migration function for future use
     * 
     * private static function migrate_to_1_1_0() {
     *     global $wpdb;
     *     
     *     // Example: Add a new column to existing table
     *     $table_sites = $wpdb->prefix . 'reaccess_sites';
     *     $wpdb->query("ALTER TABLE $table_sites ADD COLUMN new_field varchar(255) DEFAULT ''");
     * }
     */
}
