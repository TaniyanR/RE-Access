<?php
/**
 * Frontend registration form
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Frontend_Registration {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }
    
    /**
     * Enqueue frontend styles
     */
    public static function enqueue_styles() {
        // Only enqueue if shortcode is present on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'reaccess_register')) {
            wp_add_inline_style('wp-block-library', self::get_custom_css());
        }
    }
    
    /**
     * Get custom CSS from settings
     */
    private static function get_custom_css() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'reaccess_settings';
        
        $css = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_css'
        ));
        
        if (empty($css)) {
            $css = self::get_default_css();
        }
        
        return $css;
    }
    
    /**
     * Get default CSS template
     */
    private static function get_default_css() {
        return '
.reaccess-registration-form {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.reaccess-registration-form h2 {
    margin-top: 0;
    color: #333;
}

.reaccess-form-field {
    margin-bottom: 15px;
}

.reaccess-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}

.reaccess-form-field input[type="text"],
.reaccess-form-field input[type="url"],
.reaccess-form-field textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 3px;
    box-sizing: border-box;
}

.reaccess-form-field textarea {
    min-height: 100px;
    resize: vertical;
}

.reaccess-form-field small {
    display: block;
    margin-top: 3px;
    color: #666;
    font-size: 0.9em;
}

.reaccess-submit-button {
    background: #0073aa;
    color: #fff;
    padding: 10px 20px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 16px;
}

.reaccess-submit-button:hover {
    background: #005177;
}

.reaccess-error-message {
    padding: 10px;
    margin: 15px 0;
    background: #ffebee;
    border: 1px solid #f44336;
    border-radius: 3px;
    color: #c62828;
}

.reaccess-success-message {
    padding: 10px;
    margin: 15px 0;
    background: #e8f5e9;
    border: 1px solid #4caf50;
    border-radius: 3px;
    color: #2e7d32;
}

.reaccess-form-hidden {
    display: none !important;
}
';
    }
    
    /**
     * Get custom HTML from settings
     */
    private static function get_custom_html() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'reaccess_settings';
        
        $html = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_html'
        ));
        
        if (empty($html)) {
            $html = self::get_default_html();
        }
        
        return $html;
    }
    
    /**
     * Get default HTML template
     */
    private static function get_default_html() {
        return '<div class="reaccess-registration-form">
    <h2>' . esc_html__('Register Your Site', 're-access') . '</h2>
    [error_message]
    [success_message]
    <form method="post">
        <div class="reaccess-form-field">
            <label for="site_name">' . esc_html__('Site Name *', 're-access') . '</label>
            [site_name_field]
        </div>
        <div class="reaccess-form-field">
            <label for="site_url">' . esc_html__('Site URL *', 're-access') . '</label>
            [site_url_field]
            <small>' . esc_html__('Your website address (e.g., https://example.com)', 're-access') . '</small>
        </div>
        <div class="reaccess-form-field">
            <label for="rss_url">' . esc_html__('RSS Feed URL', 're-access') . '</label>
            [rss_url_field]
            <small>' . esc_html__('Optional: Your RSS feed URL', 're-access') . '</small>
        </div>
        <div class="reaccess-form-field">
            <label for="description">' . esc_html__('Description', 're-access') . '</label>
            [description_field]
            <small>' . esc_html__('Tell us about your site', 're-access') . '</small>
        </div>
        [submit_button]
    </form>
</div>';
    }
    
    /**
     * Shortcode handler for registration form
     */
    public static function shortcode_register($atts) {
        $atts = shortcode_atts([], $atts);
        
        // Handle form submission
        $error_message = '';
        $success_message = '';
        
        if (isset($_POST['reaccess_register_submit']) && isset($_POST['reaccess_register_nonce'])) {
            if (!wp_verify_nonce($_POST['reaccess_register_nonce'], 'reaccess_register_form')) {
                $error_message = __('Security check failed. Please try again.', 're-access');
            } else {
                $result = self::process_registration();
                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                } else {
                    $success_message = __('Thank you! Your site has been submitted for approval.', 're-access');
                }
            }
        }
        
        // Get custom HTML template
        $html = self::get_custom_html();
        
        // Replace error message placeholder
        if (!empty($error_message)) {
            $error_html = '<div class="reaccess-error-message">' . esc_html($error_message) . '</div>';
            $html = str_replace('[error_message]', $error_html, $html);
        } else {
            $html = str_replace('[error_message]', '', $html);
        }
        
        // Replace success message placeholder
        if (!empty($success_message)) {
            $success_html = '<div class="reaccess-success-message">' . esc_html($success_message) . '</div>';
            $html = str_replace('[success_message]', $success_html, $html);
            
            // Hide form on success by adding a hidden class
            $html = str_replace('<form method="post">', '<form method="post" class="reaccess-form-hidden" style="display:none;">', $html);
        } else {
            $html = str_replace('[success_message]', '', $html);
        }
        
        // Get submitted values (for repopulating form on error)
        $site_name = isset($_POST['site_name']) ? sanitize_text_field($_POST['site_name']) : '';
        $site_url = isset($_POST['site_url']) ? esc_url($_POST['site_url']) : '';
        $rss_url = isset($_POST['rss_url']) ? esc_url($_POST['rss_url']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // Replace form field placeholders
        $html = str_replace('[site_name_field]', 
            '<input type="text" id="site_name" name="site_name" value="' . esc_attr($site_name) . '" required>', 
            $html);
        
        $html = str_replace('[site_url_field]', 
            '<input type="url" id="site_url" name="site_url" value="' . esc_attr($site_url) . '" required>', 
            $html);
        
        $html = str_replace('[rss_url_field]', 
            '<input type="url" id="rss_url" name="rss_url" value="' . esc_attr($rss_url) . '">', 
            $html);
        
        $html = str_replace('[description_field]', 
            '<textarea id="description" name="description">' . esc_textarea($description) . '</textarea>', 
            $html);
        
        $html = str_replace('[submit_button]', 
            wp_nonce_field('reaccess_register_form', 'reaccess_register_nonce', true, false) . 
            '<button type="submit" name="reaccess_register_submit" class="reaccess-submit-button">' . 
            esc_html__('Submit Registration', 're-access') . '</button>', 
            $html);
        
        return $html;
    }
    
    /**
     * Process registration form submission
     */
    private static function process_registration() {
        global $wpdb;
        
        // Validate required fields
        if (empty($_POST['site_name']) || empty($_POST['site_url'])) {
            return new WP_Error('missing_fields', __('Please fill in all required fields.', 're-access'));
        }
        
        // Sanitize inputs
        $site_name = sanitize_text_field($_POST['site_name']);
        $site_url = esc_url_raw($_POST['site_url']);
        $rss_url = !empty($_POST['rss_url']) ? esc_url_raw($_POST['rss_url']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // Validate URLs
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Please enter a valid site URL.', 're-access'));
        }
        
        if (!empty($rss_url) && !filter_var($rss_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_rss', __('Please enter a valid RSS URL.', 're-access'));
        }
        
        // Normalize URLs for consistency (remove trailing slashes for comparison)
        // This ensures URLs like "https://example.com" and "https://example.com/" are treated as the same
        $site_url = untrailingslashit($site_url);
        $rss_url = !empty($rss_url) ? untrailingslashit($rss_url) : '';
        
        // Check for duplicates
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sites_table WHERE site_url = %s",
            $site_url
        ));
        
        if ($existing > 0) {
            return new WP_Error('duplicate_site', __('This site has already been registered.', 're-access'));
        }
        
        // Insert into database
        $result = $wpdb->insert($sites_table, [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'site_rss' => $rss_url,
            'site_desc' => $description,
            'status' => 'pending'
        ]);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save registration. Please try again.', 're-access'));
        }
        
        // Create notice if class exists
        if (class_exists('RE_Access_Notices')) {
            RE_Access_Notices::add_notice('site_registered', sprintf(
                __('New site registered: %s', 're-access'),
                $site_name
            ), $wpdb->insert_id);
        }
        
        return true;
    }
}
