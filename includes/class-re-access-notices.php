<?php
/**
 * Notices/announcements system
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Notices {
    
    private static $max_notices = 100; // Keep only latest 100 notices
    
    /**
     * Add a new notice
     */
    public static function add_notice($type, $content, $site_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_notices';
        
        $wpdb->insert($table, [
            'notice_type' => sanitize_text_field($type),
            'notice_content' => sanitize_text_field($content),
            'site_id' => $site_id ? (int)$site_id : null
        ]);
        
        // Clean up old notices
        self::cleanup_old_notices();
    }
    
    /**
     * Clean up old notices (keep only latest N)
     */
    private static function cleanup_old_notices() {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_notices';
        
        // Get count of notices
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        // Only cleanup if we have more than max_notices
        if ($count <= self::$max_notices) {
            return;
        }
        
        // Get the created_at threshold (oldest created_at to keep)
        $threshold_date = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table ORDER BY created_at DESC LIMIT 1 OFFSET %d",
            self::$max_notices - 1
        ));
        
        if ($threshold_date) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s",
                $threshold_date
            ));
        }
    }
    
    /**
     * Get recent notices
     */
    public static function get_notices($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_notices';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get latest notice
     */
    public static function get_latest_notice() {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_notices';
        
        return $wpdb->get_row("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1");
    }
    
    /**
     * Shortcode: [reaccess_notice]
     */
    public static function shortcode_notice($atts) {
        $atts = shortcode_atts([
            'limit' => 10
        ], $atts);
        
        $notices = self::get_notices((int)$atts['limit']);
        
        if (empty($notices)) {
            return '<p>' . esc_html__('No notices available', 're-access') . '</p>';
        }
        
        $output = '<div class="re-access-notices">';
        $output .= '<ul>';
        foreach ($notices as $notice) {
            $output .= '<li>';
            $output .= '<span class="notice-date">' . esc_html(date('Y-m-d H:i', strtotime($notice->created_at))) . '</span> ';
            $output .= '<span class="notice-content">' . esc_html($notice->notice_content) . '</span>';
            $output .= '</li>';
        }
        $output .= '</ul>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode: [reaccess_notice_latest]
     */
    public static function shortcode_notice_latest() {
        $notice = self::get_latest_notice();
        
        if (!$notice) {
            return '<p>' . esc_html__('No notices available', 're-access') . '</p>';
        }
        
        $output = '<div class="re-access-notice-latest">';
        $output .= '<span class="notice-date">' . esc_html(date('Y-m-d H:i', strtotime($notice->created_at))) . '</span> ';
        $output .= '<span class="notice-content">' . esc_html($notice->notice_content) . '</span>';
        $output .= '</div>';
        
        return $output;
    }
}
