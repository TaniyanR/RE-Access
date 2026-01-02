<?php
/**
 * RSS slot management
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_RSS_Slots {
    
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
        $current_slot = max(1, min(10, $current_slot));
        
        $slot_data = self::get_slot_data($current_slot);
        
        ?>
        <div class="wrap re-access-rss-slots">
            <h1><?php echo esc_html__('RSS Slots', 're-access'); ?></h1>
            
            <!-- Warning Note -->
            <div class="notice notice-warning" style="margin: 20px 0; padding: 10px;">
                <p><strong><?php esc_html_e('Important Note:', 're-access'); ?></strong></p>
                <p><?php esc_html_e('RSS feeds are fetched from external sites. Heavy usage may impact your site performance. The plugin uses caching to minimize load, but please monitor your site\'s performance when using multiple RSS slots.', 're-access'); ?></p>
                <p><?php esc_html_e('Cached RSS feeds are automatically refreshed based on the cache duration setting.', 're-access'); ?></p>
            </div>
            
            <!-- Slot Tabs -->
            <div class="nav-tab-wrapper" style="margin: 20px 0;">
                <?php for ($i = 1; $i <= 10; $i++): ?>
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
                <code>[reaccess_rss_slot slot="<?php echo $current_slot; ?>" site_id="X"]</code>
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
        
        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            'rss_slot_' . $slot
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
            'item_count' => (int)$_POST['item_count'],
            'cache_duration' => max(10, min(1440, (int)$_POST['cache_duration'])),
            'html_template' => wp_kses_post($_POST['html_template']),
            'css_template' => sanitize_textarea_field($_POST['css_template'])
        ];
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (setting_key, setting_value) VALUES (%s, %s) 
             ON DUPLICATE KEY UPDATE setting_value = %s",
            'rss_slot_' . $slot,
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
        
        // Sample data for preview (with image)
        $html1 = str_replace('[rr_item_image]', '<img src="https://via.placeholder.com/100" alt="Sample">', $html);
        $html1 = str_replace('[rr_site_name]', 'Example Site', $html1);
        $html1 = str_replace('[rr_item_title]', 'Sample RSS Article with Image', $html1);
        $html1 = str_replace('[rr_item_url]', 'https://example.com/article', $html1);
        $html1 = str_replace('[rr_item_date]', date('Y-m-d'), $html1);
        
        // Sample data for preview (without image - text only)
        $html2 = str_replace('[rr_item_image]', '', $html);
        $html2 = str_replace('[rr_site_name]', 'Example Site', $html2);
        $html2 = str_replace('[rr_item_title]', 'Sample RSS Article without Image', $html2);
        $html2 = str_replace('[rr_item_url]', 'https://example.com/article2', $html2);
        $html2 = str_replace('[rr_item_date]', date('Y-m-d'), $html2);
        
        $output = '<style>' . esc_html($css) . '</style>';
        $output .= $html1;
        $output .= $html2;
        
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
        
        if (!$site || empty($site->site_rss)) {
            return '<p>' . esc_html__('RSS feed not available', 're-access') . '</p>';
        }
        
        // Get slot template
        $slot_data = self::get_slot_data($slot);
        
        // Fetch RSS feed with caching
        $feed_items = self::fetch_rss_feed($site->site_rss, $slot_data['item_count'], $slot_data['cache_duration']);
        
        if (empty($feed_items)) {
            return '<p>' . esc_html__('No RSS items available', 're-access') . '</p>';
        }
        
        $css = $slot_data['css_template'];
        $output = '<style>' . esc_html($css) . '</style>';
        
        foreach ($feed_items as $item) {
            $html = $slot_data['html_template'];
            
            // Replace variables
            if (!empty($item['image'])) {
                $html = str_replace('[rr_item_image]', '<img src="' . esc_url($item['image']) . '" alt="' . esc_attr($item['title']) . '">', $html);
            } else {
                // Remove image placeholder if no image
                $html = str_replace('[rr_item_image]', '', $html);
            }
            
            $html = str_replace('[rr_site_name]', esc_html($site->site_name), $html);
            $html = str_replace('[rr_item_title]', esc_html($item['title']), $html);
            $html = str_replace('[rr_item_url]', esc_url($item['url']), $html);
            $html = str_replace('[rr_item_date]', esc_html($item['date']), $html);
            
            $output .= $html;
        }
        
        return $output;
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
                'image' => $image
            ];
        }
        
        // Cache the results (convert minutes to seconds)
        set_transient($cache_key, $items, $cache_duration * 60);
        
        return $items;
    }
}
