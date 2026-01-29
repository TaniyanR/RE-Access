<?php
/**
 * Access tracking functionality
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Tracker {
    
    /**
     * Initialize tracking hooks
     */
    public static function init() {
        // Track page views on every page load
        add_action('wp', [__CLASS__, 'track_pageview']);

        // Register query vars for redirect endpoint
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        
        // Handle redirect endpoint
        add_action('template_redirect', [__CLASS__, 'handle_redirect_out']);
    }
    
    /**
     * Track page view and unique visitor
     */
    public static function track_pageview() {
        if (self::should_skip_tracking()) {
            return;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        
        // Track PV
        $table = $wpdb->prefix . 'reaccess_daily';
        self::increment_daily_metric($table, 'pv', $today);
        
        // Track UU
        if (!self::has_daily_uu_cookie($today)) {
            self::increment_daily_metric($table, 'uu', $today);
            self::set_daily_uu_cookie($today);
        }
        
        // Track IN (referrer)
        self::track_referrer();
    }
    
    /**
     * Track referrer as IN
     */
    private static function track_referrer() {
        $referer = wp_get_raw_referer();
        if (empty($referer)) {
            return;
        }
        
        $referer = sanitize_text_field(wp_unslash($referer));
        $normalized_referer = RE_Access_Database::resolve_url_alias($referer);
        $normalized_home = RE_Access_Database::resolve_url_alias(home_url());

        if (empty($normalized_referer) || empty($normalized_home)) {
            return;
        }

        $referer_host = self::get_base_domain($normalized_referer);
        $home_host = self::get_base_domain($normalized_home);

        // Skip if referrer is from the same site (compare hosts only)
        if ($referer_host && $home_host && strtolower($referer_host) === strtolower($home_host)) {
            return;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'reaccess_daily';
        
        // Increment IN count
        self::increment_daily_metric($table, 'total', $today);
        
        // Track site-specific IN if it's a registered site
        self::track_site_referrer($normalized_referer, $today);
    }
    
    /**
     * Track site-specific referrer
     */
    private static function track_site_referrer($referer, $date) {
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        
        // Get cached approved sites (cache for 1 hour)
        $cache_key = 're_access_approved_sites';
        $sites = get_transient($cache_key);
        
        if (false === $sites) {
            $sites = $wpdb->get_results($wpdb->prepare("SELECT id, site_url FROM $sites_table WHERE status = %s", 'approved'));
            set_transient($cache_key, $sites, HOUR_IN_SECONDS);
        }
        
        foreach ($sites as $site) {
            $site_normalized = RE_Access_Database::resolve_url_alias($site->site_url);
            if (empty($site_normalized)) {
                continue;
            }
            $referer_host = self::get_base_domain($referer);
            $site_host = self::get_base_domain($site_normalized);

            if ($referer_host && $site_host && strtolower($referer_host) === strtolower($site_host)) {
                self::increment_site_metric($tracking_table, $site->id, $date, 'in');
                break;
            }
        }
    }
    
    /**
     * Track site-specific outbound click
     */
    private static function track_site_out($url, $date) {
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        
        // Get cached approved sites (cache for 1 hour)
        $cache_key = 're_access_approved_sites';
        $sites = get_transient($cache_key);
        
        if (false === $sites) {
            $sites = $wpdb->get_results($wpdb->prepare("SELECT id, site_url FROM $sites_table WHERE status = %s", 'approved'));
            set_transient($cache_key, $sites, HOUR_IN_SECONDS);
        }
        
        foreach ($sites as $site) {
            $site_normalized = RE_Access_Database::resolve_url_alias($site->site_url);
            if (empty($site_normalized)) {
                continue;
            }
            $url_host = self::get_base_domain($url);
            $site_host = self::get_base_domain($site_normalized);

            if ($url_host && $site_host && strtolower($url_host) === strtolower($site_host)) {
                self::increment_site_metric($tracking_table, $site->id, $date, 'out');
                break;
            }
        }
    }
    
    /**
     * Register query vars for redirect endpoint
     */
    public static function register_query_vars($vars) {
        $vars[] = 'reaccess_out';
        $vars[] = 'to';
        return $vars;
    }
    
    /**
     * Handle redirect OUT endpoint
     * Supports both plain URL in 'to' query var and base64url-encoded destination. 
     * Usage examples:
     *  - /?reaccess_out=1&to=https%3A%2F%2Fexample.com
     *  - /?reaccess_out=1&to=aBase64UrlEncodedString
     */
    public static function handle_redirect_out() {
        // Only on front-end template redirects
        $reaccess_out = get_query_var('reaccess_out', '');
        $to_url = get_query_var('to', '');
        
        if ($reaccess_out !== '1' || empty($to_url)) {
            return;
        }
        
        // If 'to' is base64url encoded, try decoding; otherwise treat as raw URL. 
        $decoded = self::base64url_decode($to_url);
        if ($decoded && wp_parse_url($decoded, PHP_URL_SCHEME)) {
            $candidate = $decoded;
        } else {
            $candidate = $to_url;
        }
        
        // Validate and sanitize the redirect URL
        $safe_url = self::validate_redirect_url($candidate);
        
        if (! $safe_url) {
            wp_die(
                esc_html__('Invalid redirect URL', 're-access'),
                esc_html__('Invalid URL', 're-access'),
                ['response' => 400]
            );
        }
        
        // Track the OUT click
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'reaccess_daily';
        
        // Increment OUT count
        self::increment_daily_metric($table, 'out', $today);
        
        // Track site-specific OUT if it's a registered site
        $normalized_target = RE_Access_Database::resolve_url_alias($safe_url);
        if (!empty($normalized_target)) {
            self::track_site_out($normalized_target, $today);
        }
        
        // Perform 302 redirect
        wp_redirect($safe_url, 302);
        exit;
    }
    
    /**
     * Decode base64url encoded string
     * base64url uses - and _ instead of + and / and may omit padding
     *
     * @param string $input
     * @return string|null Decoded string or null on failure
     */
    private static function base64url_decode($input) {
        if (! is_string($input) || $input === '') {
            return null;
        }
        // Replace URL-safe characters with standard base64 characters
        $base64 = strtr($input, '-_', '+/');
        
        // Add padding if needed
        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }
        
        // Decode
        $decoded = base64_decode($base64, true);
        
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Encode URL in base64url format.
     *
     * @param string $input
     * @return string
     */
    private static function base64url_encode($input) {
        $encoded = base64_encode($input);
        $encoded = rtrim($encoded, '=');
        return strtr($encoded, '+/', '-_');
    }

    /**
     * Generate outbound tracking URL.
     *
     * @param string $url
     * @return string
     */
    public static function get_outgoing_url($url) {
        $encoded = self::base64url_encode($url);
        $out_url = add_query_arg(
            [
                'reaccess_out' => '1',
                'to' => $encoded,
            ],
            home_url('/')
        );

        return apply_filters('re_access_outgoing_url', $out_url, $url);
    }
    
    /**
     * Validate redirect URL to prevent open redirect vulnerabilities
     *
     * @param string $url The URL to validate
     * @return string|false The validated/sanitized URL or false if invalid
     */
    private static function validate_redirect_url($url) {
        // Basic sanitize/trim
        $url = trim(sanitize_text_field($url));
        if (empty($url)) {
            return false;
        }
        
        // Parse the URL
        $parsed = wp_parse_url($url);
        
        // URL must have a scheme (http or https)
        if (empty($parsed['scheme']) || ! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }
        
        // URL must have a host
        if (empty($parsed['host'])) {
            return false;
        }
        
        // Normalize host
        $host = $parsed['host'];
        $host_for_validation = trim(strtolower($host), '[]');
        
        // Block localhost and common aliases
        $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host_for_validation, $blocked_hosts, true)) {
            return false;
        }
        
        // If host is an IP address, ensure it's not private/reserved
        if (filter_var($host_for_validation, FILTER_VALIDATE_IP)) {
            if (filter_var($host_for_validation, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        
        // Block domains that start with common private patterns (helps avoid crafty subdomain bypasses)
        if (preg_match('/^(10|127|172\\.(?:1[6-9]|2[0-9]|3[01])|192\\.168|localhost)\\./i', $host_for_validation)) {
            return false;
        }
        
        // Prevent redirect back to this site
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($site_host && strtolower($site_host) === $host_for_validation) {
            return false;
        }
        
        // Final sanitization using WordPress
        $safe_url = esc_url_raw($url);
        if (empty($safe_url)) {
            return false;
        }
        
        return $safe_url;
    }

    /**
     * Determine if tracking should be skipped.
     *
     * @return bool
     */
    private static function should_skip_tracking() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        return false;
    }

    /**
     * Check if UU cookie exists for today.
     *
     * @param string $date
     * @return bool
     */
    private static function has_daily_uu_cookie($date) {
        $cookie_name = self::get_daily_cookie_name($date);
        return isset($_COOKIE[$cookie_name]);
    }

    /**
     * Set UU cookie for today.
     *
     * @param string $date
     */
    private static function set_daily_uu_cookie($date) {
        $cookie_name = self::get_daily_cookie_name($date);
        $timezone = wp_timezone();
        $end_of_day = (new DateTimeImmutable('now', $timezone))->setTime(23, 59, 59);
        $expiry = $end_of_day->getTimestamp();
        setcookie($cookie_name, '1', [
            'expires' => $expiry,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$cookie_name] = '1';
    }

    /**
     * Get daily cookie name.
     *
     * @param string $date
     * @return string
     */
    private static function get_daily_cookie_name($date) {
        return 'reaccess_uu_' . str_replace('-', '', $date);
    }

    /**
     * Increment a daily metric.
     *
     * @param string $table
     * @param string $metric
     * @param string $date
     */
    private static function increment_daily_metric($table, $metric, $date) {
        global $wpdb;

        $allowed = ['total', 'uu', 'pv', 'out'];
        if (!in_array($metric, $allowed, true)) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, $metric) VALUES (%s, 1)
             ON DUPLICATE KEY UPDATE $metric = $metric + 1",
            $date
        ));
    }

    /**
     * Increment a site-specific metric.
     *
     * @param string $table
     * @param int $site_id
     * @param string $date
     * @param string $metric
     */
    private static function increment_site_metric($table, $site_id, $date, $metric) {
        global $wpdb;

        $allowed = ['in', 'out'];
        if (!in_array($metric, $allowed, true)) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (site_id, date, $metric) VALUES (%d, %s, 1)
             ON DUPLICATE KEY UPDATE $metric = $metric + 1",
            $site_id,
            $date
        ));
    }

    /**
     * Extract base domain from normalized URL.
     *
     * @param string $normalized_url
     * @return string
     */
    private static function get_base_domain($normalized_url) {
        $parts = explode('/', $normalized_url, 2);
        return $parts[0] ?? '';
    }
}
