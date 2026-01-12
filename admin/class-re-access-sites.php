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
     * Normalize URL by removing trailing slashes and ensuring proper format
     */
    private static function normalize_url($url) {
        if (empty($url)) {
            return '';
        }
        $url = esc_url_raw($url);
        $url = untrailingslashit($url);
        return $url;
    }
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_post_re_access_add_site', [__CLASS__, 'handle_add_site']);
        add_action('admin_post_re_access_approve_site', [__CLASS__, 'handle_approve_site']);
        add_action('admin_post_re_access_reject_site', [__CLASS__, 'handle_reject_site']);
        add_action('admin_post_re_access_delete_site', [__CLASS__, 'handle_delete_site']);
        add_action('admin_post_re_access_update_site', [__CLASS__, 'handle_update_site']);
    }
    
    /**
     * Render sites page
     */
    public static function render() {
        global $wpdb;
        
        // Get current status tab (approved, pending, or rejected)
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'approved';
        if (!in_array($current_status, ['approved', 'pending', 'rejected'], true)) {
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
        $rejected_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sites_table WHERE status = %s", 'rejected'));
        
        // Handle edit mode
        $edit_site = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $edit_site = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $sites_table WHERE id = %d",
                (int)$_GET['edit']
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
                    'rejected' => __('Site rejected successfully.', 're-access'),
                    'deleted' => __('Site deleted successfully.', 're-access'),
                    'updated' => __('Site updated successfully.', 're-access'),
                ];
                if (isset($messages[$message])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
                }
            }
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
                                <td><input type="url" name="site_rss" value="<?php echo esc_attr($edit_site->site_rss); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Description', 're-access'); ?></th>
                                <td><textarea name="site_desc" class="large-text" rows="3"><?php echo esc_textarea($edit_site->site_desc); ?></textarea></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Update Site', 're-access'); ?>">
                            <a href="?page=re-access-sites" class="button"><?php esc_html_e('Cancel', 're-access'); ?></a>
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
                                <td><input type="url" name="site_rss" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Description', 're-access'); ?></th>
                                <td><textarea name="site_desc" class="large-text" rows="3"></textarea></td>
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
                    <a href="?page=re-access-sites&status=rejected" 
                       class="nav-tab <?php echo $current_status === 'rejected' ? 'nav-tab-active' : ''; ?>">
                        <?php printf(esc_html__('Rejected (%d)', 're-access'), $rejected_count); ?>
                    </a>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav" style="margin: 10px 0;">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo; Previous', 're-access'),
                                'next_text' => __('Next &raquo;', 're-access'),
                                'total' => $total_pages,
                                'current' => $current_page,
                                'add_args' => ['status' => $current_status]
                            ]);
                            ?>
                        </div>
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
                                            <?php if ($site->status === 'pending'): ?>
                                                <span style="color: orange;">⏳ <?php esc_html_e('Pending', 're-access'); ?></span>
                                            <?php elseif ($site->status === 'rejected'): ?>
                                                <span style="color: red;">✗ <?php esc_html_e('Rejected', 're-access'); ?></span>
                                            <?php else: ?>
                                                <span style="color: green;">✓ <?php esc_html_e('Approved', 're-access'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($site->created_at); ?></td>
                                        <td>
                                            <a href="?page=re-access-sites&edit=<?php echo esc_attr($site->id); ?>" class="button button-small">
                                                <?php esc_html_e('Edit', 're-access'); ?>
                                            </a>
                                            
                                            <?php if ($site->status === 'pending'): ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                    <input type="hidden" name="action" value="re_access_approve_site">
                                                    <input type="hidden" name="site_id" value="<?php echo esc_attr($site->id); ?>">
                                                    <?php wp_nonce_field('re_access_approve_site'); ?>
                                                    <input type="submit" class="button button-small" value="<?php esc_attr_e('Approve', 're-access'); ?>">
                                                </form>
                                                
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                    <input type="hidden" name="action" value="re_access_reject_site">
                                                    <input type="hidden" name="site_id" value="<?php echo esc_attr($site->id); ?>">
                                                    <?php wp_nonce_field('re_access_reject_site'); ?>
                                                    <input type="submit" class="button button-small" value="<?php esc_attr_e('Reject', 're-access'); ?>">
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
                                    <td colspan="5"><?php esc_html_e('No sites registered yet', 're-access'); ?></td>
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
        
        $site_url = self::normalize_url($_POST['site_url']);
        $site_rss = isset($_POST['site_rss']) ? self::normalize_url($_POST['site_rss']) : '';
        
        $wpdb->insert($table, [
            'site_name' => sanitize_text_field($_POST['site_name']),
            'site_url' => $site_url,
            'site_rss' => $site_rss,
            'site_desc' => sanitize_textarea_field($_POST['site_desc'] ?? ''),
            'status' => 'pending'
        ]);
        
        // Create notice
        RE_Access_Notices::add_notice('site_registered', sprintf(
            __('New site registered: %s', 're-access'),
            sanitize_text_field($_POST['site_name'])
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
     * Handle reject site
     */
    public static function handle_reject_site() {
        check_admin_referer('re_access_reject_site');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_sites';
        $site_id = (int)$_POST['site_id'];
        
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $site_id));
        
        $wpdb->update($table, ['status' => 'rejected'], ['id' => $site_id]);
        
        // Create notice
        if (class_exists('RE_Access_Notices') && $site) {
            RE_Access_Notices::add_notice('site_rejected', sprintf(
                __('Site rejected: %s', 're-access'),
                $site->site_name
            ), $site_id);
        }
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=rejected&message=rejected'));
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
        
        $site_url = self::normalize_url($_POST['site_url']);
        $site_rss = isset($_POST['site_rss']) ? self::normalize_url($_POST['site_rss']) : '';
        
        $wpdb->update($table, [
            'site_name' => sanitize_text_field($_POST['site_name']),
            'site_url' => $site_url,
            'site_rss' => $site_rss,
            'site_desc' => sanitize_textarea_field($_POST['site_desc'] ?? ''),
        ], ['id' => $site_id]);
        
        // Clear approved sites cache
        delete_transient('re_access_approved_sites');
        
        wp_redirect(admin_url('admin.php?page=re-access-sites&status=' . $site_status . '&message=updated'));
        exit;
    }
}
