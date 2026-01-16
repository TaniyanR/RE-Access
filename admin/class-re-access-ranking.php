<?php
/**
 * Reverse access ranking
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Ranking {
    
    /**
     * Sanitize CSS to prevent XSS attacks
     */
    private static function sanitize_css($css) {
        $css = wp_strip_all_tags($css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/vbscript\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding/i', '', $css);
        $css = preg_replace('/@import/i', '', $css);
        return $css;
    }
    
    /**
     * Render ranking page
     */
    public static function render() {
        // Handle settings save
        if (isset($_POST['re_access_save_ranking_settings'])) {
            check_admin_referer('re_access_ranking_settings');
            self::save_settings();
        }
        
        $settings = self::get_settings();
        $period = isset($_GET['preview_period']) ? sanitize_text_field($_GET['preview_period']) : $settings['period'];
        $limit = isset($_GET['preview_limit']) ? (int)$_GET['preview_limit'] : $settings['limit'];
        
        $ranking = self::get_ranking_data($period, $limit);
        
        ?>
        <div class="wrap re-access-ranking">
            <h1><?php echo esc_html__('Reverse Access Ranking', 're-access'); ?></h1>
            
            <!-- Settings Form -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Ranking Settings', 're-access'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('re_access_ranking_settings'); ?>
                    <input type="hidden" name="re_access_save_ranking_settings" value="1">
                    
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Default Period', 're-access'); ?></th>
                            <td>
                                <select name="period">
                                    <option value="1" <?php selected($settings['period'], '1'); ?>><?php esc_html_e('1 Day', 're-access'); ?></option>
                                    <option value="7" <?php selected($settings['period'], '7'); ?>><?php esc_html_e('1 Week', 're-access'); ?></option>
                                    <option value="30" <?php selected($settings['period'], '30'); ?>><?php esc_html_e('1 Month', 're-access'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Display Limit', 're-access'); ?></th>
                            <td><input type="number" name="limit" value="<?php echo esc_attr($settings['limit']); ?>" min="1" max="100"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Show IN', 're-access'); ?></th>
                            <td><input type="checkbox" name="show_in" value="1" <?php checked($settings['show_in'], '1'); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Show OUT', 're-access'); ?></th>
                            <td><input type="checkbox" name="show_out" value="1" <?php checked($settings['show_out'], '1'); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Table Width', 're-access'); ?></th>
                            <td><input type="text" name="width" value="<?php echo esc_attr($settings['width']); ?>" placeholder="100%"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Accent Color', 're-access'); ?></th>
                            <td><input type="color" name="accent" value="<?php echo esc_attr($settings['accent']); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Header Background', 're-access'); ?></th>
                            <td><input type="color" name="head_bg" value="<?php echo esc_attr($settings['head_bg']); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Text Color', 're-access'); ?></th>
                            <td><input type="color" name="text" value="<?php echo esc_attr($settings['text']); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('HTML Template', 're-access'); ?></th>
                            <td>
                                <textarea name="html_template" rows="5" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($settings['html_template']); ?></textarea>
                                <p class="description"><?php esc_html_e('Use [ranking_items] for dynamic content.', 're-access'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('CSS Template', 're-access'); ?></th>
                            <td>
                                <textarea name="css_template" rows="5" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($settings['css_template']); ?></textarea>
                                <p class="description"><?php esc_html_e('Custom CSS styles for the ranking display.', 're-access'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 're-access'); ?>">
                    </p>
                </form>
                
                <h3><?php esc_html_e('Shortcode', 're-access'); ?></h3>
                <code>[reaccess_ranking]</code>
                <p><?php esc_html_e('The shortcode uses the default settings configured above.', 're-access'); ?></p>
            </div>
            
            <!-- Preview -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Preview', 're-access'); ?></h2>
                
                <div style="margin: 10px 0;">
                    <a href="?page=re-access-ranking&preview_period=1&preview_limit=<?php echo esc_attr($limit); ?>" 
                       class="button <?php echo $period == '1' ? 'button-primary' : ''; ?>">
                        <?php esc_html_e('1 Day', 're-access'); ?>
                    </a>
                    <a href="?page=re-access-ranking&preview_period=7&preview_limit=<?php echo esc_attr($limit); ?>" 
                       class="button <?php echo $period == '7' ? 'button-primary' : ''; ?>">
                        <?php esc_html_e('1 Week', 're-access'); ?>
                    </a>
                    <a href="?page=re-access-ranking&preview_period=30&preview_limit=<?php echo esc_attr($limit); ?>" 
                       class="button <?php echo $period == '30' ? 'button-primary' : ''; ?>">
                        <?php esc_html_e('1 Month', 're-access'); ?>
                    </a>
                </div>
                
                <?php echo self::render_ranking_table($ranking, $settings); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get ranking data
     */
    public static function get_ranking_data($period, $limit) {
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.id,
                s.site_name,
                s.site_url,
                SUM(t.in_count) as total_in,
                SUM(t.out_count) as total_out
             FROM $sites_table s
             LEFT JOIN $tracking_table t ON s.id = t.site_id
             WHERE s.status = 'approved'
             AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY s.id
             ORDER BY total_in DESC
             LIMIT %d",
            $period,
            $limit
        ));
        
        return $results ?: [];
    }
    
    /**
     * Render ranking table
     */
    public static function render_ranking_table($ranking, $settings) {
        $output = '<table class="re-access-ranking-table" style="width: ' . esc_attr($settings['width']) . '; border-collapse: collapse;">';
        $output .= '<thead>';
        $output .= '<tr style="background: ' . esc_attr($settings['head_bg']) . '; color: ' . esc_attr($settings['text']) . ';">';
        $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('Rank', 're-access') . '</th>';
        $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('Site', 're-access') . '</th>';
        
        if ($settings['show_in']) {
            $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('IN', 're-access') . '</th>';
        }
        if ($settings['show_out']) {
            $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('OUT', 're-access') . '</th>';
        }
        
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';
        
        if (!empty($ranking)) {
            $rank = 1;
            foreach ($ranking as $site) {
                $output .= '<tr>';
                $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $rank . '</td>';
                $output .= '<td style="padding: 10px; border: 1px solid #ddd;"><a href="' . esc_url($site->site_url) . '" target="_blank" style="color: ' . esc_attr($settings['accent']) . ';">' . esc_html($site->site_name) . '</a></td>';
                
                if ($settings['show_in']) {
                    $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html(number_format($site->total_in)) . '</td>';
                }
                if ($settings['show_out']) {
                    $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html(number_format($site->total_out)) . '</td>';
                }
                
                $output .= '</tr>';
                $rank++;
            }
        } else {
            $colspan = 2 + ($settings['show_in'] ? 1 : 0) + ($settings['show_out'] ? 1 : 0);
            $output .= '<tr><td colspan="' . $colspan . '" style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html__('No data available', 're-access') . '</td></tr>';
        }
        
        $output .= '</tbody>';
        $output .= '</table>';
        
        return $output;
    }
    
    /**
     * Get settings
     */
    private static function get_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_settings';
        
        $defaults = [
            'period' => '7',
            'limit' => '10',
            'show_in' => '1',
            'show_out' => '1',
            'width' => '100%',
            'accent' => '#0073aa',
            'head_bg' => '#333333',
            'text' => '#ffffff',
            'html_template' => '<div class="ranking-list">[ranking_items]</div>',
            'css_template' => '.re-access-ranking-item { padding: 10px; border-bottom: 1px solid #ddd; }'
        ];
        
        $saved = $wpdb->get_row($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'ranking_settings'));
        
        if ($saved) {
            return array_merge($defaults, json_decode($saved->setting_value, true));
        }
        
        return $defaults;
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_settings';
        
        $settings = [
            'period' => sanitize_text_field($_POST['period']),
            'limit' => (int)$_POST['limit'],
            'show_in' => isset($_POST['show_in']) ? '1' : '0',
            'show_out' => isset($_POST['show_out']) ? '1' : '0',
            'width' => sanitize_text_field($_POST['width']),
            'accent' => sanitize_hex_color($_POST['accent']),
            'head_bg' => sanitize_hex_color($_POST['head_bg']),
            'text' => sanitize_hex_color($_POST['text']),
            'html_template' => isset($_POST['html_template']) ? wp_kses_post($_POST['html_template']) : '<div class="ranking-list">[ranking_items]</div>',
            'css_template' => isset($_POST['css_template']) ? self::sanitize_css($_POST['css_template']) : '.re-access-ranking-item { padding: 10px; border-bottom: 1px solid #ddd; }',
        ];
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (setting_key, setting_value) VALUES (%s, %s) 
             ON DUPLICATE KEY UPDATE setting_value = %s",
            'ranking_settings',
            json_encode($settings),
            json_encode($settings)
        ));
    }
    
    /**
     * Render ranking with templates
     */
    private static function render_ranking_with_templates($ranking, $settings) {
        // Generate ranking items HTML
        $items_html = '';
        
        if (!empty($ranking)) {
            $rank = 1;
            foreach ($ranking as $site) {
                // Create individual item HTML with generic wrapper
                $item = '<div class="re-access-ranking-item" data-rank="' . esc_attr($rank) . '">';
                $item .= '<span class="rank">' . esc_html($rank) . '</span> ';
                $item .= '<a href="' . esc_url($site->site_url) . '" target="_blank" style="color: ' . esc_attr($settings['accent']) . ';">';
                $item .= esc_html($site->site_name);
                $item .= '</a>';
                
                if ($settings['show_in']) {
                    $item .= ' <span class="in-count">IN: ' . esc_html(number_format($site->total_in)) . '</span>';
                }
                if ($settings['show_out']) {
                    $item .= ' <span class="out-count">OUT: ' . esc_html(number_format($site->total_out)) . '</span>';
                }
                
                $item .= '</div>';
                $items_html .= $item;
                $rank++;
            }
        } else {
            $items_html = '<div class="no-data">' . esc_html__('No data available', 're-access') . '</div>';
        }
        
        // Replace [ranking_items] placeholder (only first occurrence for safety)
        $html = $settings['html_template'];
        $placeholder_pos = strpos($html, '[ranking_items]');
        if ($placeholder_pos !== false) {
            $html = substr_replace($html, $items_html, $placeholder_pos, strlen('[ranking_items]'));
        } else {
            // Fallback: if placeholder not found, wrap items in default container
            $html = '<div class="ranking-list">' . $items_html . '</div>';
        }
        
        // Add CSS (already sanitized and stripped of tags, safe to output directly)
        $css = '<style>' . $settings['css_template'] . '</style>';
        
        // Wrap in a container with class (HTML already sanitized during save)
        $output = '<div class="re-access-ranking">';
        $output .= $css;
        $output .= $html;
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode: [reaccess_ranking]
     */
    public static function shortcode_ranking($atts) {
        $settings = self::get_settings();
        
        $ranking = self::get_ranking_data($settings['period'], $settings['limit']);
        $output = self::render_ranking_with_templates($ranking, $settings);
        
        return apply_filters('re_access_ranking_output', $output, $settings, $ranking);
    }
}
