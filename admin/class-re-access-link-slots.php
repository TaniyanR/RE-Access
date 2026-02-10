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

    private const MAX_SITES_PER_SLOT = 3;
    private const DEFAULT_DISPLAY_LIMIT = 3;
    private const DEFAULT_ORDER_MODE = 2;
    
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
        $current_slot = max(1, min(8, $current_slot));
        
        $slot_data = self::get_slot_data($current_slot);
        
        ?>
        <div class="wrap re-access-link-slots">
            <h1><?php echo esc_html__('Link Slots', 're-access'); ?></h1>
            
            <!-- Slot Tabs -->
            <div class="nav-tab-wrapper" style="margin: 20px 0;">
                <?php for ($i = 1; $i <= 8; $i++): ?>
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
                        <tr>
                            <th><?php esc_html_e('表示件数', 're-access'); ?></th>
                            <td>
                                <select name="display_limit">
                                    <?php for ($i = 0; $i <= 20; $i++): ?>
                                        <option value="<?php echo esc_attr($i); ?>" <?php selected((int) $slot_data['display_limit'], $i); ?>><?php echo esc_html($i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <p class="description"><?php esc_html_e('0 を選択すると表示しません。', 're-access'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('表示順 (1〜5)', 're-access'); ?></th>
                            <td>
                                <select name="order_mode">
                                    <option value="1" <?php selected((int) $slot_data['order_mode'], 1); ?>><?php esc_html_e('1：あいうえお順', 're-access'); ?></option>
                                    <option value="2" <?php selected((int) $slot_data['order_mode'], 2); ?>><?php esc_html_e('2：新着順', 're-access'); ?></option>
                                    <option value="3" <?php selected((int) $slot_data['order_mode'], 3); ?>><?php esc_html_e('3：古い順', 're-access'); ?></option>
                                    <option value="4" <?php selected((int) $slot_data['order_mode'], 4); ?>><?php esc_html_e('4：ランダム', 're-access'); ?></option>
                                    <option value="5" <?php selected((int) $slot_data['order_mode'], 5); ?>><?php esc_html_e('5：還元優先', 're-access'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Slot', 're-access'); ?>">
                    </p>
                </form>
                
                <h3><?php esc_html_e('Shortcode', 're-access'); ?></h3>
                <code>[reaccess_link_slot slot="<?php echo $current_slot; ?>"]</code>
                <p class="description"><?php esc_html_e('Use site_id parameter to select the site:', 're-access'); ?> <code>[reaccess_link_slot slot="<?php echo $current_slot; ?>" site_id="X"]</code></p>
            </div>
            
            <!-- Preview -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Preview', 're-access'); ?></h2>
                <?php echo self::render_preview($current_slot); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get slot data
     */
    private static function get_slot_data($slot) {
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
}',
            'display_limit' => self::DEFAULT_DISPLAY_LIMIT,
            'order_mode' => self::DEFAULT_ORDER_MODE,
        ];

        $saved = get_option('re_access_link_slot_' . $slot, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($defaults, $saved);
    }
    
    /**
     * Sanitize CSS to prevent XSS attacks
     * 
     * This method removes dangerous CSS patterns that could be used for XSS attacks.
     * Note: CSS content is NOT HTML-escaped as that would break valid CSS syntax.
     * Instead, we strip all HTML tags and remove dangerous CSS features.
     */
    private static function sanitize_css($css) {
        // Strip all tags first
        $css = wp_strip_all_tags($css);
        
        // Remove dangerous CSS patterns (with whitespace handling)
        $css = preg_replace('/expression\s*\(\s*/i', '', $css);
        $css = preg_replace('/javascript\s*:\s*/i', '', $css);
        $css = preg_replace('/vbscript\s*:\s*/i', '', $css);
        $css = preg_replace('/-moz-binding\s*/i', '', $css);
        $css = preg_replace('/@import\s*/i', '', $css);
        $css = preg_replace('/behavior\s*:\s*/i', '', $css);
        
        return $css;
    }
    
    /**
     * Save slot
     */
    private static function save_slot() {
        $slot = (int)$_POST['slot_number'];
        if ($slot < 1 || $slot > 8) {
            return;
        }
        
        $data = [
            'description' => sanitize_text_field($_POST['description']),
            'html_template' => wp_kses_post($_POST['html_template']),
            'css_template' => self::sanitize_css($_POST['css_template']),
            'display_limit' => self::sanitize_display_limit($_POST['display_limit'] ?? self::DEFAULT_DISPLAY_LIMIT),
            'order_mode' => self::sanitize_order_mode($_POST['order_mode'] ?? self::DEFAULT_ORDER_MODE),
        ];

        update_option('re_access_link_slot_' . $slot, $data);
    }
    
    /**
     * Render preview
     */
    private static function render_preview($slot) {
        $output = do_shortcode('[reaccess_link_slot slot="' . absint($slot) . '"]');

        if ($output === '') {
            return '<p>' . esc_html(self::get_preview_notice($slot)) . '</p>';
        }

        return $output;
    }

    /**
     * Get preview notice message.
     *
     * @param int $slot
     * @return string
     */
    private static function get_preview_notice($slot) {
        return 'このスロットに割り当てられたサイトがありません。サイト編集でリンクスロットを割り当ててください。';
    }
    
    /**
     * Shortcode: [reaccess_link_slot]
     */
    public static function shortcode_link_slot($atts) {
        $atts = shortcode_atts([
            'slot' => 1,
            'site_id' => 0
        ], $atts);
        
        $slot = absint($atts['slot']);
        if ($slot < 1 || $slot > 8) {
            return '';
        }
        $site_id = absint($atts['site_id']);
        
        // Get slot template
        $slot_data = self::get_slot_data($slot);
        $display_limit = self::sanitize_display_limit($slot_data['display_limit'] ?? self::DEFAULT_DISPLAY_LIMIT);
        $order_mode = self::sanitize_order_mode($slot_data['order_mode'] ?? self::DEFAULT_ORDER_MODE);

        if ($display_limit === 0 && $site_id === 0) {
            return '';
        }
        
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        
        // If site_id is provided explicitly, use it (backward compatibility)
        if ($site_id > 0) {
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE id = %d AND status = 'approved'",
                $site_id
            ));
            $sites = $site ? [$site] : [];
        } else {
            $sites = self::get_slot_sites($slot, $display_limit, $order_mode);
        }
        
        if (empty($sites)) {
            return '';  // Return empty string if no site is assigned to this slot
        }
        
        $css = $slot_data['css_template'];
        
        // Sanitize CSS before output
        // Note: CSS is not HTML-escaped as it would break valid CSS syntax
        // The sanitize_css() method already strips tags and removes dangerous patterns
        $output = '<style>' . self::sanitize_css($css) . '</style>';

        foreach ($sites as $site) {
            $html = $slot_data['html_template'];

            $site_url = $site->site_url;
            if (class_exists('RE_Access_Tracker')) {
                $site_url = RE_Access_Tracker::get_outgoing_url($site->site_url);
            }

            // Replace variables
            $html = str_replace('[rr_site_name]', esc_html($site->site_name), $html);
            $html = str_replace('[rr_site_url]', esc_url($site_url), $html);
            $html = str_replace('[rr_site_desc]', '', $html);

            $output .= $html;
        }
        
        return apply_filters('re_access_link_slot_output', $output, $atts, $sites);
    }

    /**
     * Get sites assigned to a slot.
     *
     * @param int $slot
     * @param int $display_limit
     * @param int $order_mode
     * @return array<int,object>
     */
    private static function get_slot_sites($slot, $display_limit, $order_mode) {
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';

        if ($display_limit <= 0) {
            return [];
        }

        if ($order_mode === 1) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE status = 'approved' AND FIND_IN_SET(%d, link_slots) ORDER BY site_name ASC LIMIT %d",
                $slot,
                $display_limit
            ));
        }

        if ($order_mode === 2) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE status = 'approved' AND FIND_IN_SET(%d, link_slots) ORDER BY id DESC LIMIT %d",
                $slot,
                $display_limit
            ));
        }

        if ($order_mode === 3) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE status = 'approved' AND FIND_IN_SET(%d, link_slots) ORDER BY id ASC LIMIT %d",
                $slot,
                $display_limit
            ));
        }

        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sites_table WHERE status = 'approved' AND FIND_IN_SET(%d, link_slots)",
            $slot
        ));

        if (empty($candidates)) {
            return [];
        }

        if ($order_mode === 4) {
            shuffle($candidates);
            return array_slice($candidates, 0, $display_limit);
        }

        $site_ids = array_map(static function ($site) {
            return (int) $site->id;
        }, $candidates);
        $priorities = self::get_return_priorities_for_sites($site_ids);
        $tie_breakers = [];
        foreach ($site_ids as $site_id) {
            $tie_breakers[$site_id] = wp_rand(1, 1000000);
        }

        usort($candidates, static function ($left, $right) use ($priorities, $tie_breakers) {
            $left_id = (int) $left->id;
            $right_id = (int) $right->id;
            $left_priority = $priorities[$left_id] ?? 0;
            $right_priority = $priorities[$right_id] ?? 0;

            if ($left_priority === $right_priority) {
                return ($tie_breakers[$left_id] ?? 0) <=> ($tie_breakers[$right_id] ?? 0);
            }

            return $right_priority <=> $left_priority;
        });

        return array_slice($candidates, 0, $display_limit);
    }

    /**
     * Get return priority values only for the provided candidate site IDs.
     *
     * @param array<int,int> $site_ids
     * @return array<int,int>
     */
    private static function get_return_priorities_for_sites($site_ids) {
        $site_ids = array_values(array_unique(array_filter(array_map('absint', $site_ids))));
        if (empty($site_ids)) {
            return [];
        }

        $period = 7;
        if (class_exists('RE_Access_Ranking') && method_exists('RE_Access_Ranking', 'get_aggregation_period')) {
            $period = (int) RE_Access_Ranking::get_aggregation_period();
        }
        $period = max(1, $period);

        sort($site_ids);
        $cache_key = 're_access_link_slot_return_' . $period . '_' . md5(implode(',', $site_ids));
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        $interval = $period - 1;
        $placeholders = implode(',', array_fill(0, count($site_ids), '%d'));
        $params = array_merge([$interval], $site_ids);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT site_id, SUM(`in`) as total_in, SUM(`out`) as total_out
             FROM $tracking_table
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
               AND site_id IN ($placeholders)
             GROUP BY site_id",
            ...$params
        ));

        $priorities = [];
        foreach ($site_ids as $site_id) {
            $priorities[$site_id] = 0;
        }

        if ($rows) {
            foreach ($rows as $row) {
                $site_id = (int) $row->site_id;
                $total_in = (int) $row->total_in;
                $total_out = (int) $row->total_out;
                $priorities[$site_id] = max(0, $total_in - $total_out);
            }
        }

        set_transient($cache_key, $priorities, 20 * MINUTE_IN_SECONDS);

        return $priorities;
    }

    /**
     * @param mixed $value
     * @return int
     */
    private static function sanitize_display_limit($value) {
        return max(0, min(20, absint($value)));
    }

    /**
     * @param mixed $value
     * @return int
     */
    private static function sanitize_order_mode($value) {
        $order_mode = absint($value);
        if ($order_mode < 1 || $order_mode > 5) {
            return self::DEFAULT_ORDER_MODE;
        }

        return $order_mode;
    }
}
