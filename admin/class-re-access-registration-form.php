<?php
/**
 * Registration form settings page
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Registration_Form {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_post_re_access_save_registration_form_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ($hook !== 're-access_page_re-access-registration-form') {
            return;
        }
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $("#reset-html-template").on("click", function(e) {
                    e.preventDefault();
                    var defaultHtml = ' . wp_json_encode(self::get_default_html()) . ';
                    $("[name=html_template]").val(defaultHtml);
                });
                
                $("#reset-css-template").on("click", function(e) {
                    e.preventDefault();
                    var defaultCss = ' . wp_json_encode(self::get_default_css()) . ';
                    $("[name=css_template]").val(defaultCss);
                });
            });
        ');
    }
    
    /**
     * Render registration form settings page
     */
    public static function render() {
        global $wpdb;
        
        // Handle success messages
        $message = '';
        if (isset($_GET['message']) && $_GET['message'] === 'saved') {
            $message = '<div class="notice notice-success is-dismissible"><p>' . 
                       esc_html__('Settings saved successfully.', 're-access') . '</p></div>';
        }
        
        // Get current settings
        $settings_table = $wpdb->prefix . 'reaccess_settings';
        
        $html_template = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_html'
        ));
        
        $css_template = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_css'
        ));
        
        // Use defaults if not set
        if (empty($html_template)) {
            $html_template = self::get_default_html();
        }
        
        if (empty($css_template)) {
            $css_template = self::get_default_css();
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Registration Form Settings', 're-access'); ?></h1>
            
            <?php echo $message; ?>
            
            <p><?php echo esc_html__('Customize the HTML and CSS templates for the frontend registration form.', 're-access'); ?></p>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Available Template Variables', 're-access'); ?></h2>
                <p><?php esc_html_e('Use these variables in your HTML template:', 're-access'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>[site_name_field]</code> - <?php esc_html_e('Site name input field', 're-access'); ?></li>
                    <li><code>[site_url_field]</code> - <?php esc_html_e('Site URL input field', 're-access'); ?></li>
                    <li><code>[rss_url_field]</code> - <?php esc_html_e('RSS URL input field', 're-access'); ?></li>
                    <li><code>[description_field]</code> - <?php esc_html_e('Description textarea field', 're-access'); ?></li>
                    <li><code>[submit_button]</code> - <?php esc_html_e('Submit button with nonce', 're-access'); ?></li>
                    <li><code>[error_message]</code> - <?php esc_html_e('Error message placeholder', 're-access'); ?></li>
                    <li><code>[success_message]</code> - <?php esc_html_e('Success message placeholder', 're-access'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="re_access_save_registration_form_settings">
                <?php wp_nonce_field('re_access_registration_form_settings'); ?>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                    <h2><?php esc_html_e('HTML Template', 're-access'); ?></h2>
                    <p><?php esc_html_e('Customize the HTML structure of the registration form:', 're-access'); ?></p>
                    <textarea name="html_template" rows="20" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($html_template); ?></textarea>
                    <p>
                        <button type="button" id="reset-html-template" class="button">
                            <?php esc_html_e('Reset to Default', 're-access'); ?>
                        </button>
                    </p>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                    <h2><?php esc_html_e('CSS Template', 're-access'); ?></h2>
                    <p><?php esc_html_e('Customize the styling of the registration form:', 're-access'); ?></p>
                    <textarea name="css_template" rows="20" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($css_template); ?></textarea>
                    <p>
                        <button type="button" id="reset-css-template" class="button">
                            <?php esc_html_e('Reset to Default', 're-access'); ?>
                        </button>
                    </p>
                </div>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 're-access'); ?>">
                </p>
            </form>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Shortcode Usage', 're-access'); ?></h2>
                <p><?php esc_html_e('Add the registration form to any page or post using this shortcode:', 're-access'); ?></p>
                <code style="display: block; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">[reaccess_register]</code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle save settings
     */
    public static function handle_save_settings() {
        check_admin_referer('re_access_registration_form_settings');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $settings_table = $wpdb->prefix . 'reaccess_settings';
        
        // Sanitize templates - allow basic HTML but restrict dangerous tags
        $allowed_html = [
            'div' => ['class' => [], 'id' => [], 'style' => []],
            'h1' => ['class' => [], 'id' => []],
            'h2' => ['class' => [], 'id' => []],
            'h3' => ['class' => [], 'id' => []],
            'p' => ['class' => [], 'id' => []],
            'span' => ['class' => [], 'id' => [], 'style' => []],
            'label' => ['for' => [], 'class' => [], 'id' => []],
            'small' => ['class' => [], 'id' => []],
            'form' => ['method' => [], 'action' => [], 'class' => [], 'id' => []],
            'input' => ['type' => [], 'name' => [], 'id' => [], 'class' => [], 'value' => [], 'required' => []],
            'textarea' => ['name' => [], 'id' => [], 'class' => [], 'rows' => []],
            'button' => ['type' => [], 'name' => [], 'class' => [], 'id' => []],
            'br' => [],
            'strong' => [],
            'em' => [],
        ];
        
        $html_template = wp_kses($_POST['html_template'], $allowed_html);
        $css_template = sanitize_textarea_field($_POST['css_template']);
        
        // Save or update HTML template
        $existing_html = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_html'
        ));
        
        if ($existing_html !== null) {
            $wpdb->update($settings_table, 
                ['setting_value' => $html_template],
                ['setting_key' => 'registration_form_html']
            );
        } else {
            $wpdb->insert($settings_table, [
                'setting_key' => 'registration_form_html',
                'setting_value' => $html_template
            ]);
        }
        
        // Save or update CSS template
        $existing_css = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settings_table WHERE setting_key = %s",
            'registration_form_css'
        ));
        
        if ($existing_css !== null) {
            $wpdb->update($settings_table, 
                ['setting_value' => $css_template],
                ['setting_key' => 'registration_form_css']
            );
        } else {
            $wpdb->insert($settings_table, [
                'setting_key' => 'registration_form_css',
                'setting_value' => $css_template
            ]);
        }
        
        wp_redirect(admin_url('admin.php?page=re-access-registration-form&message=saved'));
        exit;
    }
    
    /**
     * Get default HTML template
     */
    private static function get_default_html() {
        return '<div class="reaccess-registration-form">
    <h2>Register Your Site</h2>
    [error_message]
    [success_message]
    <form method="post">
        <div class="reaccess-form-field">
            <label for="site_name">Site Name *</label>
            [site_name_field]
        </div>
        <div class="reaccess-form-field">
            <label for="site_url">Site URL *</label>
            [site_url_field]
            <small>Your website address (e.g., https://example.com)</small>
        </div>
        <div class="reaccess-form-field">
            <label for="rss_url">RSS Feed URL</label>
            [rss_url_field]
            <small>Optional: Your RSS feed URL</small>
        </div>
        <div class="reaccess-form-field">
            <label for="description">Description</label>
            [description_field]
            <small>Tell us about your site</small>
        </div>
        [submit_button]
    </form>
</div>';
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
';
    }
}
