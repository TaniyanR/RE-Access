<?php
/**
 * Link slot management
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Link_Slots {
    
    /**
     * Render link slots page
     */
    public static function render() {
        // Handle save
        if (isset($_POST['re_access_save_link_slot'])) {
            check_admin_referer('re_access_link_slot');
            self::save_slot();
        }
        
        $current_slot = isset($_GET['slot']) ? (int)$_GET['slot'] : 1;
        $current_slot = max(1, min(10, $current_slot));
        
        $slot_data = self::get_slot_data($current_slot);
        
        ?>
        <div class="wrap re-access-link-slots">
            <h1><?php echo esc_html__('Link Slots', 're-access'); ?></h1>
            
            <!-- Slot Tabs -->
            <div class="nav-tab-wrapper" style="margin: 20px 0;">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <a href="?page=re-access-link-slots&slot=<?php echo $i; ?>" 
                       class="nav-tab <?php echo $current_slot == $i ? 'nav-tab-active' : ''; ?>">
                        <?php printf(esc_html__('Slot %d', 're-access'), $i); ?>
                    </a>
                <?php endfor; ?>
            </div>
            
            <!-- Slot Configuration -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php printf(esc_html__('Configure Slot %d', 're-access'), $current_slot); ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('re_access_link_slot'); ?>
                    <input type="hidden" name="re_access_save_link_slot" value="1">
                    <input type="hidden" name="slot_number" value="<?php echo esc_attr($current_slot); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Description', 're-access'); ?></th>
                            <td><input type="text" name="description" value="<?php echo esc_attr($slot_data['description']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('HTML Template', 're-access'); ?></th>
                            <td>
                                <textarea name="html_template" rows="10" class="large-text code"><?php echo esc_textarea($slot_data['html_template']); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Available variables:', 're-access'); ?> 
                                    <code>[rr_site_name]</code>, <code>[rr_site_url]</code>, <code>[rr_site_desc]</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('CSS Template', 're-access'); ?></th>
                            <td>
                                <textarea name="css_template" rows="10" class="large-text code"><?php echo esc_textarea($slot_data['css_template']); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Slot', 're-access'); ?>">
                    </p>
                </form>
                
                <h3><?php esc_html_e('Shortcode', 're-access'); ?></h3>
                <code>[reaccess_link_slot slot="<?php echo $current_slot; ?>" site_id="X"]</code>
            </div>
            
            <!-- Preview -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Preview', 're-access'); ?></h2>
                <?php echo self::render_preview($slot_data); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get slot data
     */
    private static function get_slot_data($slot) {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_settings';
        
        $defaults = [
            'description' => '',
            'html_template' => '<div class="re-link-slot">
    <h3><a href="[rr_site_url]" target="_blank">[rr_site_name]</a></h3>
    <p>[rr_site_desc]</p>
</div>',
            'css_template' => '.re-link-slot {
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
}

.re-link-slot h3 {
    margin: 0 0 10px;
}

.re-link-slot a {
    color: #0073aa;
    text-decoration: none;
}

.re-link-slot a:hover {
    text-decoration: underline;
}'
        ];
        
        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            'link_slot_' . $slot
        ));
        
        if ($saved) {
            return array_merge($defaults, json_decode($saved->setting_value, true));
        }
        
        return $defaults;
    }
    
    /**
     * Save slot
     */
    private static function save_slot() {
        global $wpdb;
        $table = $wpdb->prefix . 're_access_settings';
        
        $slot = (int)$_POST['slot_number'];
        
        $data = [
            'description' => sanitize_text_field($_POST['description']),
            'html_template' => wp_kses_post($_POST['html_template']),
            'css_template' => sanitize_textarea_field($_POST['css_template'])
        ];
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (setting_key, setting_value) VALUES (%s, %s) 
             ON DUPLICATE KEY UPDATE setting_value = %s",
            'link_slot_' . $slot,
            json_encode($data),
            json_encode($data)
        ));
    }
    
    /**
     * Render preview
     */
    private static function render_preview($slot_data) {
        $html = $slot_data['html_template'];
        $css = $slot_data['css_template'];
        
        // Sample data for preview
        $html = str_replace('[rr_site_name]', 'Example Site', $html);
        $html = str_replace('[rr_site_url]', 'https://example.com', $html);
        $html = str_replace('[rr_site_desc]', 'This is an example site description for preview purposes.', $html);
        
        $output = '<style>' . esc_html($css) . '</style>';
        $output .= $html;
        
        return $output;
    }
    
    /**
     * Shortcode: [reaccess_link_slot]
     */
    public static function shortcode_link_slot($atts) {
        $atts = shortcode_atts([
            'slot' => 1,
            'site_id' => 0
        ], $atts);
        
        $slot = max(1, min(10, (int)$atts['slot']));
        $site_id = (int)$atts['site_id'];
        
        if (!$site_id) {
            return '<p>' . esc_html__('Site ID is required', 're-access') . '</p>';
        }
        
        // Get site data
        global $wpdb;
        $sites_table = $wpdb->prefix . 're_access_sites';
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sites_table WHERE id = %d AND status = 'approved'",
            $site_id
        ));
        
        if (!$site) {
            return '<p>' . esc_html__('Site not found', 're-access') . '</p>';
        }
        
        // Get slot template
        $slot_data = self::get_slot_data($slot);
        
        $html = $slot_data['html_template'];
        $css = $slot_data['css_template'];
        
        // Replace variables
        $html = str_replace('[rr_site_name]', esc_html($site->site_name), $html);
        $html = str_replace('[rr_site_url]', esc_url($site->site_url), $html);
        $html = str_replace('[rr_site_desc]', esc_html($site->site_desc), $html);
        
        $output = '<style>' . esc_html($css) . '</style>';
        $output .= $html;
        
        return $output;
    }
}
