<?php
/**
 * Diagnostics page for troubleshooting
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Diagnostics {
    
    /**
     * Render diagnostics page
     */
    public static function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('RE:Access Diagnostics', 're-access'); ?></h1>
            
            <style>
                .re-access-diagnostics-section {
                    background: #fff;
                    padding: 20px;
                    margin: 20px 0;
                    border: 1px solid #ccc;
                }
            </style>
            
            <div class="re-access-diagnostics-section">
                <h2><?php esc_html_e('System Information', 're-access'); ?></h2>
                
                <?php self::render_system_info(); ?>
            </div>
            
            <div class="re-access-diagnostics-section">
                <h2><?php esc_html_e('File Checks', 're-access'); ?></h2>
                
                <?php self::render_file_checks(); ?>
            </div>
            
            <div class="re-access-diagnostics-section">
                <h2><?php esc_html_e('Database Tables', 're-access'); ?></h2>
                
                <?php self::render_database_checks(); ?>
            </div>
            
            <div class="re-access-diagnostics-section">
                <h2><?php esc_html_e('Class Loading Status', 're-access'); ?></h2>
                
                <?php self::render_class_checks(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system information
     */
    private static function render_system_info() {
        ?>
        <table class="widefat">
            <tr>
                <th><?php esc_html_e('PHP Version', 're-access'); ?></th>
                <td><?php echo esc_html(phpversion()); ?> 
                    <?php echo version_compare(phpversion(), '8.1.0', '>=') ? '✅' : esc_html('❌ (Requires 8.1+)'); ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('WordPress Version', 're-access'); ?></th>
                <td><?php echo esc_html(get_bloginfo('version')); ?>
                    <?php echo version_compare(get_bloginfo('version'), '6.0', '>=') ? '✅' : esc_html('❌ (Requires 6.0+)'); ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Plugin Version', 're-access'); ?></th>
                <td><?php echo esc_html(defined('RE_ACCESS_VERSION') ? RE_ACCESS_VERSION : 'Not defined'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Plugin Directory', 're-access'); ?></th>
                <td><?php echo esc_html(defined('RE_ACCESS_PLUGIN_DIR') ? RE_ACCESS_PLUGIN_DIR : 'Not defined'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('WP_DEBUG', 're-access'); ?></th>
                <td><?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled'; ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render file checks
     */
    private static function render_file_checks() {
        $files = [
            'vendor/autoload.php' => 'Composer Autoloader',
            'includes/class-re-access-database.php' => 'Database Class',
            'includes/class-re-access-tracker.php' => 'Tracker Class',
            'admin/class-re-access-dashboard.php' => 'Dashboard Class',
            'admin/class-re-access-sites.php' => 'Sites Class',
            'admin/class-re-access-ranking.php' => 'Ranking Class',
            'admin/class-re-access-link-slots.php' => 'Link Slots Class',
            'admin/class-re-access-rss-slots.php' => 'RSS Slots Class',
            'languages/re-access-ja.mo' => 'Japanese Translation (MO)',
            'languages/re-access-ja.po' => 'Japanese Translation (PO)',
        ];
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('File', 're-access'); ?></th>
                    <th><?php esc_html_e('Status', 're-access'); ?></th>
                    <th><?php esc_html_e('Size', 're-access'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file => $description): 
                    $full_path = RE_ACCESS_PLUGIN_DIR . $file;
                    $exists = file_exists($full_path);
                    $size = $exists ? filesize($full_path) : 0;
                ?>
                <tr>
                    <td><?php echo esc_html($description); ?><br><code><?php echo esc_html($file); ?></code></td>
                    <td><?php echo $exists ? '✅ Found' : '❌ Missing'; ?></td>
                    <td><?php echo $exists ? esc_html(number_format($size) . ' bytes') : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render database checks
     */
    private static function render_database_checks() {
        global $wpdb;
        
        $tables = [
            'reaccess_daily' => 'Daily Access Metrics',
            'reaccess_sites' => 'Site Registrations',
            'reaccess_site_daily' => 'Site-Specific Tracking',
            'reaccess_notice' => 'Notices/Announcements',
            'reaccess_settings' => 'Plugin Settings',
        ];
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Table', 're-access'); ?></th>
                    <th><?php esc_html_e('Status', 're-access'); ?></th>
                    <th><?php esc_html_e('Rows', 're-access'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table => $description): 
                    $full_table = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) === $full_table;
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe
                    $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table}`") : 0;
                ?>
                <tr>
                    <td><?php echo esc_html($description); ?><br><code><?php echo esc_html($full_table); ?></code></td>
                    <td><?php echo $exists ? '✅ Exists' : '❌ Missing'; ?></td>
                    <td><?php echo $exists ? esc_html($count) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render class checks
     */
    private static function render_class_checks() {
        $classes = [
            'RE_Access_Database' => 'Database Management',
            'RE_Access_Tracker' => 'Access Tracking',
            'RE_Access_Dashboard' => 'Dashboard',
            'RE_Access_Sites' => 'Site Management',
            'RE_Access_Ranking' => 'Ranking',
            'RE_Access_Link_Slots' => 'Link Slots',
            'RE_Access_RSS_Slots' => 'RSS Slots',
            'RE_Access_Notices' => 'Notices',
            'RE_Access_Frontend_Registration' => 'Frontend Registration',
            'Puc_v4_Factory' => 'Plugin Update Checker',
        ];
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Class', 're-access'); ?></th>
                    <th><?php esc_html_e('Description', 're-access'); ?></th>
                    <th><?php esc_html_e('Status', 're-access'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classes as $class => $description): ?>
                <tr>
                    <td><code><?php echo esc_html($class); ?></code></td>
                    <td><?php echo esc_html($description); ?></td>
                    <td><?php echo class_exists($class) ? '✅ Loaded' : '❌ Not Loaded'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
