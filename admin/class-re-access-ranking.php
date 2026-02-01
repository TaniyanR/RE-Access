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
                            <th><?php esc_html_e('集計期間', 're-access'); ?></th>
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
                            <th><?php esc_html_e('流入', 're-access'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_in" value="1" <?php checked($settings['show_in'], '1'); ?>>
                                    <?php esc_html_e('表示', 're-access'); ?>
                                </label>
                                <label style="margin-left: 10px;">
                                    <?php esc_html_e('何件以上表示', 're-access'); ?>
                                    <select name="min_in">
                                        <?php for ($i = 0; $i <= 50; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php selected($settings['min_in'], (string) $i); ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('流出', 're-access'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_out" value="1" <?php checked($settings['show_out'], '1'); ?>>
                                    <?php esc_html_e('表示', 're-access'); ?>
                                </label>
                                <label style="margin-left: 10px;">
                                    <?php esc_html_e('何件以上表示', 're-access'); ?>
                                    <select name="min_out">
                                        <?php for ($i = 0; $i <= 50; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php selected($settings['min_out'], (string) $i); ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('テーブル幅(px)', 're-access'); ?></th>
                            <td><input type="number" name="width" value="<?php echo esc_attr($settings['width']); ?>" min="1"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('テーブル高さ(px)', 're-access'); ?></th>
                            <td><input type="number" name="height" value="<?php echo esc_attr($settings['height']); ?>" min="1"></td>
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
        $period = max(1, (int) $period);
        $interval = $period - 1;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.id,
                s.site_name,
                s.site_url,
                SUM(t.`in`) as total_in,
                SUM(t.`out`) as total_out
             FROM $sites_table s
             LEFT JOIN $tracking_table t ON s.id = t.site_id
             WHERE s.status = 'approved'
             AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY s.id
             ORDER BY total_in DESC
             LIMIT %d",
            $interval,
            $limit
        ));
        
        return $results ?: [];
    }

    /**
     * Get return priorities for sites based on IN-OUT over a period.
     *
     * @param int|null $period
     * @return array<int,int>
     */
    public static function get_return_priorities($period = null) {
        $settings = self::get_settings();
        $period = $period === null ? (int) $settings['period'] : (int) $period;
        $period = max(1, $period);
        $cache_key = 're_access_return_priority_' . $period;

        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        $interval = $period - 1;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT site_id, SUM(`in`) as total_in, SUM(`out`) as total_out
             FROM $tracking_table
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY site_id",
            $interval
        ));

        $priorities = [];
        if ($rows) {
            foreach ($rows as $row) {
                $total_in = (int) $row->total_in;
                $total_out = (int) $row->total_out;
                $priority = max(0, $total_in - $total_out);
                $priorities[(int) $row->site_id] = $priority;
            }
        }

        set_transient($cache_key, $priorities, 10 * MINUTE_IN_SECONDS);

        return $priorities;
    }
    
    /**
     * Render ranking table
     */
    public static function render_ranking_table($ranking, $settings) {
        if (!$settings['show_in'] && !$settings['show_out']) {
            return '';
        }

        $min_in = isset($settings['min_in']) ? (int) $settings['min_in'] : 0;
        $min_out = isset($settings['min_out']) ? (int) $settings['min_out'] : 0;
        $filtered_ranking = [];

        foreach ($ranking as $site) {
            $total_in = (int) $site->total_in;
            $total_out = (int) $site->total_out;

            if ($settings['show_in'] && $total_in < $min_in) {
                continue;
            }
            if ($settings['show_out'] && $total_out < $min_out) {
                continue;
            }

            $filtered_ranking[] = $site;
        }

        $table_width = absint($settings['width']);
        $table_height = absint($settings['height']);
        $wrapper_style = 'width: ' . $table_width . 'px; max-height: ' . $table_height . 'px; overflow: auto;';

        $output = '<div class="re-access-ranking-table-wrapper" style="' . esc_attr($wrapper_style) . '">';
        $output .= '<table class="re-access-ranking-table" style="width: 100%; border-collapse: collapse;">';
        $output .= '<thead>';
        $output .= '<tr style="background: ' . esc_attr($settings['head_bg']) . '; color: ' . esc_attr($settings['text']) . ';">';
        $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('Rank', 're-access') . '</th>';
        $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('Site', 're-access') . '</th>';
        
        if ($settings['show_in']) {
            $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('流入', 're-access') . '</th>';
        }
        if ($settings['show_out']) {
            $output .= '<th style="padding: 10px; border: 1px solid #ddd;">' . esc_html__('流出', 're-access') . '</th>';
        }
        
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';
        
        if (!empty($filtered_ranking)) {
            $rank = 1;
            foreach ($filtered_ranking as $site) {
                $site_url = $site->site_url;
                if (class_exists('RE_Access_Tracker')) {
                    $site_url = RE_Access_Tracker::get_outgoing_url($site->site_url);
                }

                $output .= '<tr>';
                $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $rank . '</td>';
                $output .= '<td style="padding: 10px; border: 1px solid #ddd;"><a href="' . esc_url($site_url) . '" target="_blank" style="color: ' . esc_attr($settings['accent']) . ';">' . esc_html($site->site_name) . '</a></td>';
                
                if ($settings['show_in']) {
                    $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html(number_format((int) $site->total_in)) . '</td>';
                }
                if ($settings['show_out']) {
                    $output .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html(number_format((int) $site->total_out)) . '</td>';
                }
                
                $output .= '</tr>';
                $rank++;
            }
        } else {
            $colspan = 2 + ($settings['show_in'] ? 1 : 0) + ($settings['show_out'] ? 1 : 0);
            $output .= '<tr><td colspan="' . $colspan . '" style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . esc_html__('No data available', 're-access') . '<br><span class="description">' . esc_html__('計測データがまだ無いため表示されません。アクセス計測後に反映されます。', 're-access') . '</span></td></tr>';
        }
        
        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get settings
     */
    private static function get_settings() {
        $defaults = [
            'period' => '7',
            'limit' => '10',
            'show_in' => '1',
            'show_out' => '1',
            'min_in' => '0',
            'min_out' => '0',
            'width' => '600',
            'height' => '400',
            'accent' => '#0073aa',
            'head_bg' => '#333333',
            'text' => '#ffffff'
        ];

        $saved = get_option('re_access_ranking_settings', []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($defaults, $saved);
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        $settings = [
            'period' => sanitize_text_field($_POST['period']),
            'limit' => (int)$_POST['limit'],
            'show_in' => isset($_POST['show_in']) ? '1' : '0',
            'show_out' => isset($_POST['show_out']) ? '1' : '0',
            'min_in' => isset($_POST['min_in']) ? (int) $_POST['min_in'] : 0,
            'min_out' => isset($_POST['min_out']) ? (int) $_POST['min_out'] : 0,
            'width' => absint($_POST['width']),
            'height' => absint($_POST['height']),
            'accent' => sanitize_hex_color($_POST['accent']),
            'head_bg' => sanitize_hex_color($_POST['head_bg']),
            'text' => sanitize_hex_color($_POST['text']),
        ];

        update_option('re_access_ranking_settings', $settings);
    }
    
    /**
     * Shortcode: [reaccess_ranking]
     */
    public static function shortcode_ranking($atts) {
        $settings = self::get_settings();
        
        $ranking = self::get_ranking_data($settings['period'], $settings['limit']);
        $output = self::render_ranking_table($ranking, $settings);
        
        return apply_filters('re_access_ranking_output', $output, $settings, $ranking);
    }
}
