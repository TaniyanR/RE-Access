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
        
        $referer = $_SERVER['HTTP_REFERER'];
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
        
        // Find matching site
        $sites = $wpdb->get_results("SELECT id, site_url FROM $sites_table WHERE status = 'approved'");
        
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
        
        // Find matching site
        $sites = $wpdb->get_results("SELECT id, site_url FROM $sites_table WHERE status = 'approved'");
        
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
     * Get visitor hash (based on IP and user agent)
     */
    private static function get_visitor_hash() {
        $ip = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return hash('sha256', $ip . $user_agent);
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
}
