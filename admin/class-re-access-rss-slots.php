<?php
/**
 * RSS slot management
 *
 * Provides 8 configurable RSS feed slots with:
 * - Tab-based admin interface
 * - Configurable item count (1-50)
 * - HTML/CSS template editors
 * - Image extraction via DOMDocument
 * - Variable replacement for item data
 * - Transient caching (10-1440 minutes)
 * - Shortcode: [reaccess_rss_slot slot="1" site_id="X"]
 * 
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_RSS_Slots {

    private const MAX_SITES_PER_SLOT = 3;
    
    /**
     * Render RSS slots page
     */
    public static function render() {
        // Handle save
        if (isset($_POST['re_access_save_rss_slot'])) {
            check_admin_referer('re_access_rss_slot');
            self::save_slot();
        }
        
        $current_slot = isset($_GET['slot']) ? (int)$_GET['slot'] : 1;
        $current_slot = max(1, min(8, $current_slot));
        
        $slot_data = self::get_slot_data($current_slot);
        
        ?>
        <div class="wrap re-access-rss-slots">
            <h1><?php echo esc_html__('RSS Slots', 're-access'); ?></h1>
            
            <!-- Warning Note -->
            <div class="notice notice-warning" style="margin: 20px 0; padding: 10px;">
                <p><strong><?php esc_html_e('Important Note:', 're-access'); ?></strong></p>
                <p><?php esc_html_e('RSS feeds are fetched from external sites. Heavy usage may impact your site performance. The plugin uses caching to minimize load, but please monitor your site\'s performance when using multiple RSS slots.', 're-access'); ?></p>
                <p><?php esc_html_e('Cached RSS feeds are automatically refreshed based on the cache duration setting.', 're-access'); ?></p>
                <p><?php esc_html_e('If an RSS item has no image, no substitute image is shown and only text is displayed.', 're-access'); ?></p>
            </div>
            
            <!-- Slot Tabs -->
            <div class="nav-tab-wrapper" style="margin: 20px 0;">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <a href="?page=re-access-rss-slots&slot=<?php echo $i; ?>" 
                       class="nav-tab <?php echo $current_slot == $i ? 'nav-tab-active' : ''; ?>">
                        <?php printf(esc_html__('Slot %d', 're-access'), $i); ?>
                    </a>
                <?php endfor; ?>
            </div>
            
            <!-- Slot Configuration -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php printf(esc_html__('Configure Slot %d', 're-access'), $current_slot); ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('re_access_rss_slot'); ?>
                    <input type="hidden" name="re_access_save_rss_slot" value="1">
                    <input type="hidden" name="slot_number" value="<?php echo esc_attr($current_slot); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Description', 're-access'); ?></th>
                            <td><input type="text" name="description" value="<?php echo esc_attr($slot_data['description']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Items to Display', 're-access'); ?></th>
                            <td><input type="number" name="item_count" value="<?php echo esc_attr($slot_data['item_count']); ?>" min="1" max="50"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Cache Duration (minutes)', 're-access'); ?></th>
                            <td>
                                <input type="number" name="cache_duration" value="<?php echo esc_attr($slot_data['cache_duration']); ?>" min="10" max="1440">
                                <p class="description"><?php esc_html_e('How long to cache RSS feed data (10-1440 minutes). Default: 30 minutes.', 're-access'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('HTML Template', 're-access'); ?></th>
                            <td>
                                <textarea name="html_template" rows="10" class="large-text code"><?php echo esc_textarea($slot_data['html_template']); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Available variables:', 're-access'); ?> 
                                    <code>[rr_item_image]</code>, <code>[rr_site_name]</code>, <code>[rr_item_title]</code>, 
                                    <code>[rr_item_url]</code>, <code>[rr_item_date]</code>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('Note: If RSS item has no image, [rr_item_image] will be removed and only text link is shown.', 're-access'); ?>
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
                <code>[reaccess_rss_slot slot="<?php echo $current_slot; ?>"]</code>
                <p class="description"><?php esc_html_e('Use site_id parameter to select the site:', 're-access'); ?> <code>[reaccess_rss_slot slot="<?php echo $current_slot; ?>" site_id="X"]</code></p>
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
            'item_count' => 5,
            'cache_duration' => 30,
            'html_template' => '<div class="re-rss-item">
    [rr_item_image]
    <div class="re-rss-content">
        <h4><a href="[rr_item_url]" target="_blank">[rr_item_title]</a></h4>
        <p class="re-rss-meta">[rr_site_name] - [rr_item_date]</p>
    </div>
</div>',
            'css_template' => '.re-rss-item {
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
    display: flex;
    gap: 15px;
}

.re-rss-item img {
    max-width: 100px;
    height: auto;
    border-radius: 3px;
}

.re-rss-content {
    flex: 1;
}

.re-rss-item h4 {
    margin: 0 0 5px;
}

.re-rss-item a {
    color: #0073aa;
    text-decoration: none;
}

.re-rss-item a:hover {
    text-decoration: underline;
}

.re-rss-meta {
    color: #666;
    font-size: 12px;
    margin: 0;
}'
        ];

        $saved = get_option('re_access_rss_slot_' . $slot, []);
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
        
        $item_count = max(1, min(50, (int) $_POST['item_count']));
        $data = [
            'description' => sanitize_text_field($_POST['description']),
            'item_count' => $item_count,
            'cache_duration' => max(10, min(1440, (int)$_POST['cache_duration'])),
            'html_template' => wp_kses_post($_POST['html_template']),
            'css_template' => self::sanitize_css($_POST['css_template']),
        ];

        update_option('re_access_rss_slot_' . $slot, $data);
    }
    
    /**
     * Render preview
     */
    private static function render_preview($slot) {
        $output = do_shortcode('[reaccess_rss_slot slot="' . absint($slot) . '"]');

        if ($output === '') {
            return '<p>' . esc_html__('RSSが取得できません。サイトのRSS URLとスロット割り当てを確認してください。', 're-access') . '</p>';
        }

        return $output;
    }
    
    /**
     * Shortcode: [reaccess_rss_slot]
     */
    public static function shortcode_rss_slot($atts) {
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
        
        // Get site data
        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        if ($site_id > 0) {
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE id = %d AND status = 'approved'",
                $site_id
            ));
            $sites = $site ? [$site] : [];
        } else {
            $sites = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE status = 'approved' AND FIND_IN_SET(%d, rss_slots) ORDER BY id DESC",
                $slot
            ));
            if (!empty($sites) && class_exists('RE_Access_Ranking')) {
                $priorities = RE_Access_Ranking::get_return_priorities();
                usort($sites, static function ($a, $b) use ($priorities) {
                    $priority_a = $priorities[$a->id] ?? 0;
                    $priority_b = $priorities[$b->id] ?? 0;
                    if ($priority_a === $priority_b) {
                        return $b->id <=> $a->id;
                    }
                    return $priority_b <=> $priority_a;
                });
            }
            $sites = array_values(array_filter($sites, static function ($site) {
                return !empty($site->rss_url);
            }));
            if (count($sites) > self::MAX_SITES_PER_SLOT) {
                $sites = array_slice($sites, 0, self::MAX_SITES_PER_SLOT);
            }
        }
        
        if (empty($sites)) {
            return '';
        }
        
        $css = $slot_data['css_template'];
        $css_output = '<style>' . self::sanitize_css($css) . '</style>';
        $output = '';
        $feed_items_by_site = [];
        $merged_items = [];
        $unique_items = [];
        
        foreach ($sites as $site) {
            if (empty($site->rss_url)) {
                continue;
            }

            // Fetch RSS feed with caching
            $feed_items = self::fetch_rss_feed($site->rss_url, $slot_data['item_count'], $slot_data['cache_duration']);
        
            if (empty($feed_items)) {
                continue;
            }

            if ($output === '') {
                // Sanitize CSS before output
                // Note: CSS is not HTML-escaped as it would break valid CSS syntax
                // The sanitize_css() method already strips tags and removes dangerous patterns
                $output = $css_output;
            }

            $feed_items_by_site[$site->id] = $feed_items;

            foreach ($feed_items as $item) {
                $item['site_name'] = $site->site_name;
                $item['site_url'] = $site->site_url;
                $item_url = $item['url'];
                if (!empty($item_url)) {
                    if (!isset($unique_items[$item_url]) || $item['timestamp'] > $unique_items[$item_url]['timestamp']) {
                        $unique_items[$item_url] = $item;
                    }
                } else {
                    $merged_items[] = $item;
                }
            }
        }

        if (!empty($unique_items)) {
            $merged_items = array_merge($merged_items, array_values($unique_items));
        }

        if (empty($merged_items)) {
            return '';
        }

        usort($merged_items, static function ($a, $b) {
            $time_a = (int) ($a['timestamp'] ?? 0);
            $time_b = (int) ($b['timestamp'] ?? 0);
            return $time_b <=> $time_a;
        });

        $merged_items = array_slice($merged_items, 0, (int) $slot_data['item_count']);

        foreach ($merged_items as $item) {
            $html = $slot_data['html_template'];

            // Replace variables
            if (!empty($item['image'])) {
                $html = str_replace('[rr_item_image]', '<img src="' . esc_url($item['image']) . '" alt="' . esc_attr($item['title']) . '">', $html);
            } else {
                // Remove image placeholder if no image
                $html = str_replace('[rr_item_image]', '', $html);
            }

            $html = str_replace('[rr_site_name]', esc_html($item['site_name']), $html);
            $html = str_replace('[rr_item_title]', esc_html($item['title']), $html);
            $item_url = $item['url'];
            if (class_exists('RE_Access_Tracker')) {
                $item_url = RE_Access_Tracker::get_outgoing_url($item['url']);
            }
            $html = str_replace('[rr_item_url]', esc_url($item_url), $html);
            $html = str_replace('[rr_item_date]', esc_html($item['date']), $html);

            $output .= $html;
        }
        
        if ($output === '') {
            return '';
        }
        
        return apply_filters('re_access_rss_slot_output', $output, $atts, $sites, $feed_items_by_site);
    }
    
    /**
     * Fetch RSS feed with caching
     */
    private static function fetch_rss_feed($feed_url, $limit, $cache_duration = 30) {
        // Create a unique cache key for this feed
        $cache_key = 're_access_rss_' . md5($feed_url . '_' . $limit);
        
        // Try to get cached data
        $cached_items = get_transient($cache_key);
        if ($cached_items !== false) {
            return $cached_items;
        }
        
        // Fetch fresh feed data
        $feed = fetch_feed($feed_url);
        
        if (is_wp_error($feed)) {
            return [];
        }
        
        $items = [];
        $feed_items = $feed->get_items(0, $limit);
        
        foreach ($feed_items as $item) {
            $image = '';
            
            // Try to get image from enclosure
            $enclosure = $item->get_enclosure();
            if ($enclosure && $enclosure->get_thumbnail()) {
                $image = $enclosure->get_thumbnail();
            }
            
            // Try to extract image from content using DOMDocument
            if (empty($image)) {
                $content = $item->get_content();
                if (!empty($content)) {
                    // Use DOMDocument to extract first image
                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument();
                    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
                    libxml_clear_errors();
                    
                    $images = $dom->getElementsByTagName('img');
                    if ($images->length > 0) {
                        $image = $images->item(0)->getAttribute('src');
                    }
                }
            }
            
            $items[] = [
                'title' => $item->get_title(),
                'url' => $item->get_permalink(),
                'date' => $item->get_date('Y-m-d'),
                'timestamp' => (int) $item->get_date('U'),
                'image' => $image
            ];
        }
        
        // Cache the results (convert minutes to seconds)
        set_transient($cache_key, $items, $cache_duration * 60);
        
        return $items;
    }
}
