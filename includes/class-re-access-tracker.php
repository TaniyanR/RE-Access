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
        $table = $wpdb->prefix . 're_access_tracking';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (date, pv_count) VALUES (%s, 1) 
             ON DUPLICATE KEY UPDATE pv_count = pv_count + 1",
            $today
        ));
        
        // Track UU
        $visitor_hash = self::get_visitor_hash();
        $visitor_table = $wpdb->prefix . 're_access_visitors';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $visitor_table WHERE visitor_hash = %s AND date = %s",
            $visitor_hash,
            $today
        ));
        
        if (!$existing) {
            $wpdb->insert($visitor_table, [
                'visitor_hash' => $visitor_hash,
                'date' => $today
            ]);
            
            // Increment UU count
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (date, uu_count) VALUES (%s, 1) 
                 ON DUPLICATE KEY UPDATE uu_count = uu_count + 1",
                $today
            ));
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
        $table = $wpdb->prefix . 're_access_tracking';
        
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
        $sites_table = $wpdb->prefix . 're_access_sites';
        $tracking_table = $wpdb->prefix . 're_access_site_tracking';
        
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
        $table = $wpdb->prefix . 're_access_tracking';
        
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
        $sites_table = $wpdb->prefix . 're_access_sites';
        $tracking_table = $wpdb->prefix . 're_access_site_tracking';
        
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
     */
    public static function handle_redirect_out() {
        // Check if this is a redirect request
        $reaccess_out = get_query_var('reaccess_out', '');
        $to_url = get_query_var('to', '');
        
        if ($reaccess_out !== '1' || empty($to_url)) {
            return;
        }
        
        // Validate and sanitize the redirect URL
        $safe_url = self::validate_redirect_url($to_url);
        
        if (!$safe_url) {
            // Invalid URL, show error and exit
            wp_die(
                esc_html__('Invalid redirect URL', 're-access'),
                esc_html__('Invalid URL', 're-access'),
                ['response' => 400]
            );
        }
        
        // Track the OUT click
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 're_access_tracking';
        
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
     * Validate redirect URL to prevent open redirect vulnerabilities
     * 
     * @param string $url The URL to validate
     * @return string|false The validated URL or false if invalid
     */
    private static function validate_redirect_url($url) {
        // Sanitize the URL
        $url = sanitize_text_field($url);
        
        // Check if URL is empty
        if (empty($url)) {
            return false;
        }
        
        // Parse the URL
        $parsed = wp_parse_url($url);
        
        // URL must have a scheme (http or https)
        if (empty($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }
        
        // URL must have a host
        if (empty($parsed['host'])) {
            return false;
        }
        
        // Prevent redirects to localhost or internal IPs
        $host = strtolower($parsed['host']);
        
        // Block localhost and common localhost aliases
        $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host, $blocked_hosts, true)) {
            return false;
        }
        
        // Block private IP ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        
        // Use WordPress esc_url_raw for final validation and sanitization
        $safe_url = esc_url_raw($url);
        
        // Verify the sanitized URL is not empty
        if (empty($safe_url)) {
            return false;
        }
        
        return $safe_url;
    }
}
