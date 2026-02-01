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

        // Site registrations table (mutual site info + approval status)
        $table_sites = $wpdb->prefix . 'reaccess_sites';
        $sql_sites = "CREATE TABLE $table_sites (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_name varchar(255) NOT NULL,
            site_url varchar(512) NOT NULL,
            rss_url varchar(512) DEFAULT '',
            link_slots varchar(255) NOT NULL DEFAULT '',
            rss_slots varchar(255) NOT NULL DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY site_url (site_url)
        ) $charset_collate;";
        dbDelta($sql_sites);

        // Daily access metrics table (site-wide totals)
        $table_daily = $wpdb->prefix . 'reaccess_daily';
        $sql_daily = "CREATE TABLE $table_daily (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            total int(11) DEFAULT 0,
            uu int(11) DEFAULT 0,
            pv int(11) DEFAULT 0,
            `out` int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY date (date),
            KEY date_index (date)
        ) $charset_collate;";
        dbDelta($sql_daily);

        // Site-specific daily tracking table (for rankings)
        $table_site_daily = $wpdb->prefix . 'reaccess_site_daily';
        $sql_site_daily = "CREATE TABLE $table_site_daily (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            date date NOT NULL,
            `in` int(11) DEFAULT 0,
            `out` int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY site_date (site_id, date),
            KEY site_id (site_id),
            KEY date (date),
            KEY site_id_date (site_id, date)
        ) $charset_collate;";
        dbDelta($sql_site_daily);

        // Notices/announcements table
        $table_notice = $wpdb->prefix . 'reaccess_notice';
        $sql_notice = "CREATE TABLE $table_notice (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY type (type)
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
     * Normalize URL for consistent site matching
     *
     * Handles:
     * - Protocol normalization (adds http if missing for parsing)
     * - Protocol removal (http/https)
     * - www subdomain removal
     * - Trailing slash removal
     * - Query and fragment removal
     * - Default port removal (80, 443)
     * - URL decoding
     * - Lowercase conversion (host and path)
     *
     * Returns a string in the form "host[:port][/path]" (no protocol).
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL (or empty string on parse failure)
     */
    public static function normalize_url($url) {
        if (empty($url) || !is_string($url)) {
            return '';
        }

        // Ensure we have a scheme for parse_url to reliably get host
        $candidate = $url;
        if (strpos($candidate, '://') === false) {
            $candidate = 'http://' . $candidate;
        }

        $parsed = parse_url($candidate);
        if ($parsed === false || !isset($parsed['host'])) {
            return '';
        }

        $host = strtolower($parsed['host']);

        // Remove leading www.
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        // Handle port: omit default ports 80 and 443
        if (isset($parsed['port']) && is_numeric($parsed['port'])) {
            $port = (int) $parsed['port'];
            if ($port !== 80 && $port !== 443) {
                $host .= ':' . $port;
            }
        }

        // Path: decode, lowercase, remove trailing slash
        $path = isset($parsed['path']) ? urldecode($parsed['path']) : '';
        $path = strtolower($path);
        $path = rtrim($path, '/');

        // Ensure leading slash for non-empty paths
        if ($path !== '' && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        // We intentionally ignore query and fragment for normalization
        $normalized = $host . $path;

        return $normalized;
    }

    /**
     * Sanitize a URL for storage/display while removing query strings and fragments.
     *
     * Keeps scheme/host/path for display, but removes query/fragment and trailing slash.
     *
     * @param string $url
     * @return string
     */
    public static function sanitize_url_for_storage($url) {
        if (empty($url) || !is_string($url)) {
            return '';
        }

        $sanitized = esc_url_raw($url);
        if (empty($sanitized)) {
            return '';
        }

        $parts = wp_parse_url($sanitized);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        $path = rtrim($path, '/');

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':' . (int) $parts['port'];
        }
        if ($path !== '') {
            $rebuilt .= $path;
        }

        return $rebuilt;
    }

    /**
     * Get URL aliases from WordPress options
     *
     * Stored option key: re_access_url_aliases
     * Expected format: associative array [alias_normalized => canonical_string]
     *
     * @return array Array of alias mappings [alias => canonical]
     */
    public static function get_url_aliases() {
        $aliases = get_option('re_access_url_aliases', []);

        if (!is_array($aliases)) {
            return [];
        }

        return $aliases;
    }

    /**
     * Set URL aliases in WordPress options
     *
     * @param array $aliases Array of alias mappings [alias => canonical]
     * @return bool True on success, false on failure
     */
    public static function set_url_aliases($aliases) {
        if (!is_array($aliases)) {
            return false;
        }

        return update_option('re_access_url_aliases', $aliases);
    }

    /**
     * Resolve URL alias to canonical URL
     *
     * If the normalized URL matches an alias, returns the canonical URL.
     * Otherwise, returns the normalized URL as-is.
     *
     * Supports:
     * - exact normalized match
     * - domain-level aliasing: alias keys that are domains (without path) will be appended with the original path
     *
     * @param string $url The URL to resolve
     * @return string The resolved canonical URL (normalized form) or empty string on failure
     */
    public static function resolve_url_alias($url) {
        $normalized = self::normalize_url($url);

        if (empty($normalized)) {
            return '';
        }

        $aliases = self::get_url_aliases();

        // Exact normalized match
        if (isset($aliases[$normalized]) && !empty($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        // Domain-level match: split into base domain and path
        $parts = explode('/', $normalized, 2);
        $base_domain = $parts[0];
        $path = isset($parts[1]) && $parts[1] !== '' ? '/' . $parts[1] : '';

        if (isset($aliases[$base_domain]) && !empty($aliases[$base_domain])) {
            $canonical = $aliases[$base_domain];

            // Ensure canonical does not end with a trailing slash when appending path
            if ($path !== '') {
                $canonical = rtrim($canonical, '/');
                $canonical .= $path;
            }

            return $canonical;
        }

        // No alias found, return normalized form
        return $normalized;
    }

    /**
     * Check and run database migrations if needed
     *
     * Compares the saved plugin version with the current RE_ACCESS_VERSION constant
     * and runs migrations for any intermediate versions. After successful migrations,
     * updates the saved version.
     */
    public static function check_migrations() {
        $saved_version = get_option('re_access_version', '0.0.0');

        // If RE_ACCESS_VERSION is not defined, skip migrations (caller/plugin bootstrap should define it)
        if (!defined('RE_ACCESS_VERSION')) {
            return;
        }

        $current_version = RE_ACCESS_VERSION;

        // If versions match or saved is newer, no migration needed
        if (version_compare($saved_version, $current_version, '>=')) {
            return;
        }

        // Update version after migrations
        update_option('re_access_version', $current_version);
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
