<?php
/**
 * Site registration management
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Sites {

    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_post_re_access_add_site', [__CLASS__, 'handle_add_site']);
        add_action('admin_post_re_access_approve_site', [__CLASS__, 'handle_approve_site']);
        add_action('admin_post_re_access_delete_site', [__CLASS__, 'handle_delete_site']);
        add_action('admin_post_re_access_update_site', [__CLASS__, 'handle_update_site']);
        add_action('admin_init', [__CLASS__, 'migrate_slot_assignments']);
    }
    
    /**
     * Render sites page
     */
    public static function render() {
        global $wpdb;
        
        // Get current status tab (approved or pending)
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'approved';
        if (!in_array($current_status, ['approved', 'pending'], true)) {
            $current_status = 'approved';
        }
        
        // Get current page for pagination
        $current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = 30;
        $offset = ($current_page - 1) * $per_page;
        
        // Get sites for current status
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $total_sites = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sites_table WHERE status = %s",
            $current_status
        ));
        $total_pages = ceil($total_sites / $per_page);
        
        $sites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sites_table WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $current_status,
            $per_page,
            $offset
        ));
        
        // Get counts for tabs
        $approved_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sites_table WHERE status = %s", 'approved'));
        $pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sites_table WHERE status = %s", 'pending'));
        // Handle edit mode
        $edit_site = null;
        if (isset($_GET['action'], $_GET['site_id']) && $_GET['action'] === 'edit' && is_numeric($_GET['site_id'])) {
            $edit_site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE id = %d",
                (int)$_GET['site_id']
            ));
        }
        
        ?>
        <div class="wrap re-access-sites">
            <h1><?php echo esc_html__('Site Registration', 're-access'); ?></h1>
            
            <?php
            // Display success messages
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
                $messages = [
                    'added' => __('Site added successfully and is pending approval.', 're-access'),
                    'approved' => __('Site approved successfully.', 're-access'),
                    'deleted' => __('Site deleted successfully.', 're-access'),
                    'updated' => __('Site updated successfully.', 're-access'),
                ];
                if (isset($messages[$message])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
                }
            }
            ?>
            
            <?php
            $link_selected = $edit_site ? self::parse_slot_csv($edit_site->link_slots) : [];
            $rss_selected = $edit_site ? self::parse_slot_csv($edit_site->rss_slots) : [];
            ?>

            <?php if ($edit_site): ?>
                <!-- Edit Form -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                    <h2><?php esc_html_e('Edit Site', 're-access'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="re_access_update_site">
                        <input type="hidden" name="site_id" value="<?php echo esc_attr($edit_site->id); ?>">
                        <?php wp_nonce_field('re_access_update_site'); ?>

                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Site Name', 're-access'); ?></th>
                                <td><input type="text" name="site_name" value="<?php echo esc_attr($edit_site->site_name); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Site URL', 're-access'); ?></th>
                                <td><input type="url" name="site_url" value="<?php echo esc_attr($edit_site->site_url); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('RSS URL', 're-access'); ?></th>
                                <td><input type="url" name="rss_url" value="<?php echo esc_attr($edit_site->rss_url); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Link Slot Assignments', 're-access'); ?></th>
                                <td>
                                    <?php for ($slot = 1; $slot <= 10; $slot++): ?>
                                        <label style="margin-right: 10px;">
                                            <input type="checkbox" name="link_slots[]" value="<?php echo esc_attr($slot); ?>" <?php checked(in_array($slot, $link_selected, true)); ?>>
                                            <?php echo esc_html($slot); ?>
                                        </label>
                                    <?php endfor; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('RSS Slot Assignments', 're-access'); ?></th>
                                <td>
                                    <?php for ($slot = 1; $slot <= 10; $slot++): ?>
                                        <label style="margin-right: 10px;">
                                            <input type="checkbox" name="rss_slots[]" value="<?php echo esc_attr($slot); ?>" <?php checked(in_array($slot, $rss_selected, true)); ?>>
                                            <?php echo esc_html($slot); ?>
                                        </label>
                                    <?php endfor; ?>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Update Site', 're-access'); ?>">
                            <a href="?page=re-access-sites&status=<?php echo esc_attr($current_status); ?>" class="button"><?php esc_html_e('Cancel', 're-access'); ?></a>
                        </p>
                    </form>
                </div>
            <?php else: ?>
                <!-- Add New Site Form -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                    <h2><?php esc_html_e('Add New Site', 're-access'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="re_access_add_site">
                        <?php wp_nonce_field('re_access_add_site'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Site Name', 're-access'); ?></th>
                                <td><input type="text" name="site_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Site URL', 're-access'); ?></th>
                                <td><input type="url" name="site_url" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('RSS URL', 're-access'); ?></th>
                                <td><input type="url" name="rss_url" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Link Slot Assignments', 're-access'); ?></th>
                                <td>
                                    <?php for ($slot = 1; $slot <= 10; $slot++): ?>
                                        <label style="margin-right: 10px;">
                                            <input type="checkbox" name="link_slots[]" value="<?php echo esc_attr($slot); ?>">
                                            <?php echo esc_html($slot); ?>
                                        </label>
                                    <?php endfor; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('RSS Slot Assignments', 're-access'); ?></th>
                                <td>
                                    <?php for ($slot = 1; $slot <= 10; $slot++): ?>
                                        <label style="margin-right: 10px;">
                                            <input type="checkbox" name="rss_slots[]" value="<?php echo esc_attr($slot); ?>">
                                            <?php echo esc_html($slot); ?>
                                        </label>
                                    <?php endfor; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Site', 're-access'); ?>">
                        </p>
                    </form>
                </div>
                
                <!-- Status Tabs -->
                <div class="nav-tab-wrapper" style="margin: 20px 0;">
                    <a href="?page=re-access-sites&status=approved" 
                       class="nav-tab <?php echo $current_status === 'approved' ? 'nav-tab-active' : ''; ?>">
                        <?php printf(esc_html__('Approved (%d)', 're-access'), $approved_count); ?>
                    </a>
                    <a href="?page=re-access-sites&status=pending" 
                       class="nav-tab <?php echo $current_status === 'pending' ? 'nav-tab-active' : ''; ?>">
                        <?php printf(esc_html__('Pending (%d)', 're-access'), $pending_count); ?>
                    </a>
                </div>
                
                <!-- Pagination Tabs -->
                <?php if ($total_pages > 1): ?>
                    <div class="nav-tab-wrapper" style="margin: 10px 0;">
                        <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $page, 'status' => $current_status])); ?>"
                               class="nav-tab <?php echo $current_page === $page ? 'nav-tab-active' : ''; ?>">
                                <?php echo esc_html($page); ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Sites List -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                    <h2><?php esc_html_e('Registered Sites', 're-access'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Site Name', 're-access'); ?></th>
                                <th><?php esc_html_e('URL', 're-access'); ?></th>
                                <th><?php esc_html_e('RSS', 're-access'); ?></th>
                                <th><?php esc_html_e('Status', 're-access'); ?></th>
                                <th><?php esc_html_e('Created', 're-access'); ?></th>
                                <th><?php esc_html_e('Actions', 're-access'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sites)): ?>
                                <?php foreach ($sites as $site): ?>
                                    <tr>
                                        <td><?php echo esc_html($site->site_name); ?></td>
                                        <td><a href="<?php echo esc_url($site->site_url); ?>" target="_blank"><?php echo esc_html($site->site_url); ?></a></td>
                                        <td>
                                            <?php if (!empty($site->rss_url)) : ?>
                                                <a href="<?php echo esc_url($site->rss_url); ?>" target="_blank"><?php echo esc_html($site->rss_url); ?></a>
                                            <?php else : ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($site->status === 'pending'): ?>
                                                <span style="color: orange;">⏳ <?php esc_html_e('Pending', 're-access'); ?></span>
                                            <?php else: ?>
                                                <span style="color: green;">✓ <?php esc_html_e('Approved', 're-access'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($site->created_at); ?></td>
                                        <td>
                                            <a href="?page=re-access-sites&action=edit&site_id=<?php echo esc_attr($site->id); ?>&status=<?php echo esc_attr($current_status); ?>" class="button button-small">
                                                <?php esc_html_e('Edit', 're-access'); ?>
                                            </a>
                                            
                                            <?php if ($site->status === 'pending'): ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                    <input type="hidden" name="action" value="re_access_approve_site">
                                                    <input type="hidden" name="site_id" value="<?php echo esc_attr($site->id); ?>">
                                                    <?php wp_nonce_field('re_access_approve_site'); ?>
                                                    <input type="submit" class="button button-small" value="<?php esc_attr_e('Approve', 're-access'); ?>">
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;" 
                                                  onsubmit="return confirm('<?php esc_attr_e('Are you sure?', 're-access'); ?>');">
                                                <input type="hidden" name="action" value="re_access_delete_site">
                                                <input type="hidden" name="site_id" value="<?php echo esc_attr($site->id); ?>">
                                                <?php wp_nonce_field('re_access_delete_site'); ?>
                                                <input type="submit" class="button button-small" value="<?php esc_attr_e('Delete', 're-access'); ?>">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><?php esc_html_e('No sites registered yet', 're-access'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle add site
     */
    public static function handle_add_site() {
        check_admin_referer('re_access_add_site');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';
        
        $site_url = isset($_POST['site_url']) ? RE_Access_Database::sanitize_url_for_storage(wp_unslash($_POST['site_url'])) : '';
        $rss_url = isset($_POST['rss_url']) ? RE_Access_Database::sanitize_url_for_storage(wp_unslash($_POST['rss_url'])) : '';
        $site_name = isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '';
        $link_slots = self::sanitize_slots(isset($_POST['link_slots']) ? $_POST['link_slots'] : []);
        $rss_slots = self::sanitize_slots(isset($_POST['rss_slots']) ? $_POST['rss_slots'] : []);
        
        $wpdb->insert($table, [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'rss_url' => $rss_url,
            'link_slots' => self::slots_to_csv($link_slots),
            'rss_slots' => self::slots_to_csv($rss_slots),
            'status' => 'pending'
        ]);

        if (!empty($wpdb->insert_id)) {
            self::enforce_slot_exclusivity((int) $wpdb->insert_id, $link_slots, $rss_slots);
        }
        
        // Create notice
        RE_Access_Notices::add_notice('site_registered', sprintf(
            __('New site registered: %s', 're-access'),
            $site_name
        ), $wpdb->insert_id);
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=pending&message=added'));
        exit;
    }
    
    /**
     * Handle approve site
     */
    public static function handle_approve_site() {
        check_admin_referer('re_access_approve_site');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';
        $site_id = (int)$_POST['site_id'];
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $site_id));
        
        $wpdb->update($table, ['status' => 'approved'], ['id' => $site_id]);
        
        // Clear approved sites cache
        delete_transient('re_access_approved_sites');
        
        // Create notice
        RE_Access_Notices::add_notice('site_approved', sprintf(
            __('Site approved: %s', 're-access'),
            $site->site_name
        ), $site_id);
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=approved&message=approved'));
        exit;
    }
    
    /**
     * Handle delete site
     */
    public static function handle_delete_site() {
        check_admin_referer('re_access_delete_site');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';
        $site_id = (int)$_POST['site_id'];
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $site_id));
        $site_status = $site ? $site->status : 'approved';
        
        $wpdb->delete($table, ['id' => $site_id]);
        
        // Clear approved sites cache
        delete_transient('re_access_approved_sites');
        
        // Create notice
        RE_Access_Notices::add_notice('site_deleted', sprintf(
            __('Site deleted: %s', 're-access'),
            $site->site_name
        ), $site_id);
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=' . $site_status . '&message=deleted'));
        exit;
    }
    
    /**
     * Handle update site
     */
    public static function handle_update_site() {
        check_admin_referer('re_access_update_site');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';
        $site_id = (int)$_POST['site_id'];
        
        // Get current status before update
        $current_site = $wpdb->get_row($wpdb->prepare("SELECT status FROM $table WHERE id = %d", $site_id));
        $site_status = $current_site ? $current_site->status : 'approved';
        
        $site_url = isset($_POST['site_url']) ? RE_Access_Database::sanitize_url_for_storage(wp_unslash($_POST['site_url'])) : '';
        $rss_url = isset($_POST['rss_url']) ? RE_Access_Database::sanitize_url_for_storage(wp_unslash($_POST['rss_url'])) : '';
        $site_name = isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '';
        $link_slots = self::sanitize_slots(isset($_POST['link_slots']) ? $_POST['link_slots'] : []);
        $rss_slots = self::sanitize_slots(isset($_POST['rss_slots']) ? $_POST['rss_slots'] : []);
        
        $wpdb->update($table, [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'rss_url' => $rss_url,
            'link_slots' => self::slots_to_csv($link_slots),
            'rss_slots' => self::slots_to_csv($rss_slots),
        ], ['id' => $site_id]);

        self::enforce_slot_exclusivity($site_id, $link_slots, $rss_slots);
        
        // Clear approved sites cache
        delete_transient('re_access_approved_sites');
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=' . $site_status . '&message=updated'));
        exit;
    }

    /**
     * Sanitize slot input.
     *
     * @param array $slots
     * @return array
     */
    private static function sanitize_slots($slots) {
        if (!is_array($slots)) {
            return [];
        }

        $sanitized = [];
        foreach ($slots as $slot) {
            $value = absint(wp_unslash($slot));
            if ($value >= 1 && $value <= 10) {
                $sanitized[] = $value;
            }
        }

        $sanitized = array_values(array_unique($sanitized));
        sort($sanitized, SORT_NUMERIC);

        return $sanitized;
    }

    /**
     * Convert slot array to CSV string.
     *
     * @param array $slots
     * @return string
     */
    private static function slots_to_csv($slots) {
        if (empty($slots)) {
            return '';
        }

        return implode(',', $slots);
    }

    /**
     * Parse CSV string into slot array.
     *
     * @param string $csv
     * @return array
     */
    private static function parse_slot_csv($csv) {
        if (empty($csv) || !is_string($csv)) {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $csv)), 'strlen');
        return self::sanitize_slots($parts);
    }

    /**
     * Remove a single slot from a CSV list.
     *
     * @param string $csv
     * @param int $slot
     * @return string
     */
    private static function remove_slot_from_csv($csv, $slot) {
        $slots = self::parse_slot_csv($csv);
        $slot = absint($slot);
        if ($slot < 1 || $slot > 10) {
            return self::slots_to_csv($slots);
        }

        $slots = array_values(array_diff($slots, [$slot]));
        sort($slots, SORT_NUMERIC);

        return self::slots_to_csv($slots);
    }

    /**
     * Ensure slot exclusivity across sites.
     *
     * @param int $site_id
     * @param array $link_slots
     * @param array $rss_slots
     */
    private static function enforce_slot_exclusivity($site_id, $link_slots, $rss_slots) {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';

        foreach ($link_slots as $slot) {
            $sites = $wpdb->get_results($wpdb->prepare(
                "SELECT id, link_slots FROM $table WHERE id != %d AND FIND_IN_SET(%d, link_slots)",
                $site_id,
                $slot
            ));

            foreach ($sites as $site) {
                $updated = self::remove_slot_from_csv($site->link_slots, $slot);
                if ($updated !== $site->link_slots) {
                    $wpdb->update($table, ['link_slots' => $updated], ['id' => $site->id]);
                }
            }
        }

        foreach ($rss_slots as $slot) {
            $sites = $wpdb->get_results($wpdb->prepare(
                "SELECT id, rss_slots FROM $table WHERE id != %d AND FIND_IN_SET(%d, rss_slots)",
                $site_id,
                $slot
            ));

            foreach ($sites as $site) {
                $updated = self::remove_slot_from_csv($site->rss_slots, $slot);
                if ($updated !== $site->rss_slots) {
                    $wpdb->update($table, ['rss_slots' => $updated], ['id' => $site->id]);
                }
            }
        }
    }

    /**
     * Migrate legacy slot assignments stored in options.
     */
    public static function migrate_slot_assignments() {
        if (get_option('re_access_migrated_slot_assignments')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';

        $link_map = [];
        $rss_map = [];

        for ($slot = 1; $slot <= 10; $slot++) {
            $link_option = get_option('re_access_link_slot_' . $slot);
            $rss_option = get_option('re_access_rss_slot_' . $slot);

            $link_site_id = 0;
            if (is_array($link_option) && isset($link_option['site_id'])) {
                $link_site_id = absint($link_option['site_id']);
            } elseif (is_numeric($link_option)) {
                $link_site_id = absint($link_option);
            }

            if ($link_site_id > 0) {
                $link_map[$link_site_id][] = $slot;
            }

            $rss_site_id = 0;
            if (is_array($rss_option) && isset($rss_option['site_id'])) {
                $rss_site_id = absint($rss_option['site_id']);
            } elseif (is_numeric($rss_option)) {
                $rss_site_id = absint($rss_option);
            }

            if ($rss_site_id > 0) {
                $rss_map[$rss_site_id][] = $slot;
            }
        }

        $site_ids = array_values(array_unique(array_merge(array_keys($link_map), array_keys($rss_map))));
        foreach ($site_ids as $site_id) {
            $site = $wpdb->get_row($wpdb->prepare(
                "SELECT id, link_slots, rss_slots FROM $table WHERE id = %d",
                $site_id
            ));

            if (!$site) {
                continue;
            }

            $link_slots = self::parse_slot_csv($site->link_slots);
            $rss_slots = self::parse_slot_csv($site->rss_slots);

            if (isset($link_map[$site_id])) {
                $link_slots = array_merge($link_slots, $link_map[$site_id]);
                $link_slots = self::sanitize_slots($link_slots);
            }

            if (isset($rss_map[$site_id])) {
                $rss_slots = array_merge($rss_slots, $rss_map[$site_id]);
                $rss_slots = self::sanitize_slots($rss_slots);
            }

            $wpdb->update($table, [
                'link_slots' => self::slots_to_csv($link_slots),
                'rss_slots' => self::slots_to_csv($rss_slots),
            ], ['id' => $site_id]);
        }

        update_option('re_access_migrated_slot_assignments', 1);
    }
}
