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
        
        // Track outbound links via JavaScript
        add_action('wp_footer', [__CLASS__, 'add_outbound_tracking_script']);
        
        // AJAX handler for outbound tracking
        add_action('wp_ajax_re_access_track_out', [__CLASS__, 'ajax_track_out']);
        add_action('wp_ajax_nopriv_re_access_track_out', [__CLASS__, 'ajax_track_out']);
        
        // Register query vars for redirect endpoint
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        
        // Handle redirect endpoint
        add_action('template_redirect', [__CLASS__, 'handle_redirect_out']);
    }
    
    /**
     * Track page view and unique visitor
     */
    public static function track_pageview() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        
        // Track PV
        $table = $wpdb->prefix . 'reaccess_daily';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, pv_count) VALUES (%s, 1) 
             ON DUPLICATE KEY UPDATE pv_count = pv_count + 1",
            $today
        ));
        
        // Track UU
        $visitor_hash = self::get_visitor_hash();
        $cache_key = 'reaccess_visitor_' . $visitor_hash . '_' . $today;
        
        // Check if visitor already counted today (using transient cache)
        if (!get_transient($cache_key)) {
            // Increment UU count
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (date, uu_count) VALUES (%s, 1) 
                 ON DUPLICATE KEY UPDATE uu_count = uu_count + 1",
                $today
            ));
            
            // Cache for rest of the day
            set_transient($cache_key, true, DAY_IN_SECONDS);
        }
        
        // Track IN (referrer)
        self::track_referrer();
    }
    
    /**
     * Track referrer as IN
     */
    private static function track_referrer() {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return;
        }
        
        $referer = sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER']));
        $site_url = home_url();
        
        // Skip if referrer is from the same site
        if (strpos($referer, $site_url) === 0) {
            return;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'reaccess_daily';
        
        // Increment IN count
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, in_count) VALUES (%s, 1) 
             ON DUPLICATE KEY UPDATE in_count = in_count + 1",
            $today
        ));
        
        // Track site-specific IN if it's a registered site
        self::track_site_referrer($referer, $today);
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
            $sites = $wpdb->get_results("SELECT id, site_url FROM $sites_table WHERE status = 'approved'");
            set_transient($cache_key, $sites, HOUR_IN_SECONDS);
        }
        
        foreach ($sites as $site) {
            if (strpos($referer, $site->site_url) === 0) {
                // Increment site IN count
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $tracking_table (site_id, date, in_count) VALUES (%d, %s, 1) 
                     ON DUPLICATE KEY UPDATE in_count = in_count + 1",
                    $site->id,
                    $date
                ));
                break;
            }
        }
    }
    
    /**
     * Add outbound link tracking script
     */
    public static function add_outbound_tracking_script() {
        if (is_admin()) {
            return;
        }
        ?>
        <script>
        (function() {
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a');
                if (!link) return;
                
                var href = link.getAttribute('href');
                if (!href || href.indexOf('#') === 0) return;
                
                var siteUrl = '<?php echo esc_js(home_url()); ?>';
                var isExternal = href.indexOf('http') === 0 && href.indexOf(siteUrl) !== 0;
                
                if (isExternal) {
                    // Track outbound click
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo esc_js(admin_url('admin-ajax.php')); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('action=re_access_track_out&url=' + encodeURIComponent(href));
                }
            });
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX handler for outbound tracking
     */
    public static function ajax_track_out() {
        if (empty($_POST['url'])) {
            wp_die();
        }
        
        $url = sanitize_text_field($_POST['url']);
        
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'reaccess_daily';
        
        // Increment OUT count
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, out_count) VALUES (%s, 1) 
             ON DUPLICATE KEY UPDATE out_count = out_count + 1",
            $today
        ));
        
        // Track site-specific OUT if it's a registered site
        self::track_site_out($url, $today);
        
        wp_die();
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
            $sites = $wpdb->get_results("SELECT id, site_url FROM $sites_table WHERE status = 'approved'");
            set_transient($cache_key, $sites, HOUR_IN_SECONDS);
        }
        
        foreach ($sites as $site) {
            if (strpos($url, $site->site_url) === 0) {
                // Increment site OUT count
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $tracking_table (site_id, date, out_count) VALUES (%d, %s, 1) 
                     ON DUPLICATE KEY UPDATE out_count = out_count + 1",
                    $site->id,
                    $date
                ));
                break;
            }
        }
    }
    
    /**
     * Get visitor hash for unique user tracking
     * Uses MD5 (per specification) of IP, user agent, and date with delimiters
     * Note: MD5 is sufficient for non-cryptographic visitor identification
     */
    private static function get_visitor_hash() {
        $ip = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $date = current_time('Y-m-d');
        // MD5 is used per specification for visitor tracking (non-cryptographic purpose)
        return md5($ip . '|' . $user_agent . '|' . $date);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
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
     *  - /?reaccess_out=1&to= aBase64UrlEncodedString
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
        
        if (!$safe_url) {
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
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, out_count) VALUES (%s, 1) 
             ON DUPLICATE KEY UPDATE out_count = out_count + 1",
            $today
        ));
        
        // Track site-specific OUT if it's a registered site
        self::track_site_out($safe_url, $today);
        
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
        if (!is_string($input) || $input === '') {
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
        if (empty($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
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
}