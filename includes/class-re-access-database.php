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
        
        // Access tracking table
        $table_access = $wpdb->prefix . 're_access_tracking';
        $sql_access = "CREATE TABLE $table_access (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            in_count int(11) DEFAULT 0,
            out_count int(11) DEFAULT 0,
            pv_count int(11) DEFAULT 0,
            uu_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY date (date),
            KEY date_index (date)
        ) $charset_collate;";
        dbDelta($sql_access);
        
        // Site registrations table
        $table_sites = $wpdb->prefix . 're_access_sites';
        $sql_sites = "CREATE TABLE $table_sites (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_name varchar(255) NOT NULL,
            site_url varchar(512) NOT NULL,
            site_rss varchar(512) DEFAULT '',
            site_desc text DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_sites);
        
        // Site access details table (for ranking)
        $table_site_access = $wpdb->prefix . 're_access_site_tracking';
        $sql_site_access = "CREATE TABLE $table_site_access (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            date date NOT NULL,
            in_count int(11) DEFAULT 0,
            out_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY site_date (site_id, date),
            KEY site_id (site_id),
            KEY date (date)
        ) $charset_collate;";
        dbDelta($sql_site_access);
        
        // Settings table
        $table_settings = $wpdb->prefix . 're_access_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        dbDelta($sql_settings);
        
        // Notices/announcements table
        $table_notices = $wpdb->prefix . 're_access_notices';
        $sql_notices = "CREATE TABLE $table_notices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notice_type varchar(50) NOT NULL,
            notice_content text NOT NULL,
            site_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY notice_type (notice_type)
        ) $charset_collate;";
        dbDelta($sql_notices);
        
        // Unique visitors tracking (daily)
        $table_visitors = $wpdb->prefix . 're_access_visitors';
        $sql_visitors = "CREATE TABLE $table_visitors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_hash varchar(64) NOT NULL,
            date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY visitor_date (visitor_hash, date),
            KEY date (date)
        ) $charset_collate;";
        dbDelta($sql_visitors);
    }
    
    /**
     * Drop database tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 're_access_tracking',
            $wpdb->prefix . 're_access_sites',
            $wpdb->prefix . 're_access_site_tracking',
            $wpdb->prefix . 're_access_settings',
            $wpdb->prefix . 're_access_notices',
            $wpdb->prefix . 're_access_visitors',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
