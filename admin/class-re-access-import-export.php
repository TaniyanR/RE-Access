<?php
/**
 * Import/Export management
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Import_Export {

    private const MAX_FILE_SIZE = 10485760; // 10MB

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_post_re_access_export', [__CLASS__, 'handle_export']);
        add_action('admin_post_re_access_import', [__CLASS__, 'handle_import']);
    }

    /**
     * Render import/export page
     */
    public static function render() {
        $message = isset($_GET['re_access_message']) ? sanitize_text_field($_GET['re_access_message']) : '';
        $notice = self::get_notice_message($message);
        ?>
        <div class="wrap re-access-import-export">
            <h1><?php echo esc_html__('インポート/エクスポート', 're-access'); ?></h1>

            <?php if ($notice): ?>
                <div class="notice <?php echo esc_attr($notice['class']); ?> is-dismissible"><p><?php echo esc_html($notice['text']); ?></p></div>
            <?php endif; ?>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php echo esc_html__('エクスポート', 're-access'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="re_access_export">
                    <?php wp_nonce_field('re_access_export'); ?>
                    <label>
                        <input type="checkbox" name="include_metrics" value="1">
                        <?php echo esc_html__('計測データも含める', 're-access'); ?>
                    </label>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php echo esc_attr__('エクスポート', 're-access'); ?>">
                    </p>
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php echo esc_html__('インポート', 're-access'); ?></h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="re_access_import">
                    <?php wp_nonce_field('re_access_import'); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__('JSONファイル', 're-access'); ?></th>
                            <td>
                                <input type="file" name="import_file" accept=".json" required>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('インポート方式', 're-access'); ?></th>
                            <td>
                                <label style="margin-right: 20px;">
                                    <input type="radio" name="import_mode" value="merge" checked>
                                    <?php echo esc_html__('マージ（既存を残しつつ追加/更新）', 're-access'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="import_mode" value="replace">
                                    <?php echo esc_html__('全置換（既存削除して復元）', 're-access'); ?>
                                </label>
                                <div id="re-access-replace-confirm" style="margin-top: 10px; display: none;">
                                    <label>
                                        <input type="checkbox" name="confirm_replace" value="1">
                                        <?php echo esc_html__('既存データを削除して復元する（元に戻せません）', 're-access'); ?>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php echo esc_attr__('インポート', 're-access'); ?>">
                    </p>
                </form>
            </div>
        </div>
        <script>
            (function() {
                var radios = document.querySelectorAll('input[name="import_mode"]');
                var confirmBox = document.getElementById('re-access-replace-confirm');
                if (!confirmBox || radios.length === 0) {
                    return;
                }
                var toggleConfirm = function() {
                    var selected = document.querySelector('input[name="import_mode"]:checked');
                    confirmBox.style.display = selected && selected.value === 'replace' ? 'block' : 'none';
                };
                Array.prototype.forEach.call(radios, function(radio) {
                    radio.addEventListener('change', toggleConfirm);
                });
                toggleConfirm();
            })();
        </script>
        <?php
    }

    /**
     * Handle export request
     */
    public static function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 're-access'));
        }

        check_admin_referer('re_access_export');

        $include_metrics = !empty($_POST['include_metrics']);
        $payload = self::build_export_payload($include_metrics);
        if (empty($payload)) {
            self::redirect_with_message('export_failed');
        }

        $filename = 're-access-export-' . wp_date('Ymd-His') . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Handle import request
     */
    public static function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 're-access'));
        }

        check_admin_referer('re_access_import');

        if (empty($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
            self::redirect_with_message('import_file_missing');
        }

        $file = $_FILES['import_file'];
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            self::redirect_with_message('import_upload_failed');
        }

        if (empty($file['size']) || $file['size'] > self::MAX_FILE_SIZE) {
            self::redirect_with_message('import_file_too_large');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            self::redirect_with_message('import_invalid_file');
        }

        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (!empty($filetype['type'])) {
            $allowed_types = ['application/json', 'text/json', 'text/plain', 'application/octet-stream'];
            if (!in_array($filetype['type'], $allowed_types, true)) {
                self::redirect_with_message('import_invalid_file');
            }
        }

        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false || strlen($contents) > self::MAX_FILE_SIZE) {
            self::redirect_with_message('import_invalid_file');
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            self::redirect_with_message('import_invalid_json');
        }

        $mode = isset($_POST['import_mode']) ? sanitize_text_field($_POST['import_mode']) : 'merge';
        if (!in_array($mode, ['merge', 'replace'], true)) {
            $mode = 'merge';
        }

        if ($mode === 'replace' && empty($_POST['confirm_replace'])) {
            self::redirect_with_message('import_replace_confirm');
        }

        $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
        $sites = isset($data['sites']) && is_array($data['sites']) ? $data['sites'] : [];
        $metrics = isset($data['metrics']) && is_array($data['metrics']) ? $data['metrics'] : [];

        self::import_options($options);
        self::import_sites($sites, $mode);
        if (!empty($metrics)) {
            self::import_metrics($metrics, $mode);
        }

        self::redirect_with_message('import_success');
    }

    /**
     * Build export payload
     *
     * @param bool $include_metrics
     * @return array
     */
    private static function build_export_payload($include_metrics) {
        $payload = [
            'meta' => [
                'plugin' => 're-access',
                'export_version' => 1,
                'exported_at' => wp_date('c'),
                'site_url' => home_url(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
            ],
            'options' => self::collect_options(),
            'sites' => self::collect_table_rows('reaccess_sites'),
        ];

        if ($include_metrics) {
            $payload['metrics'] = [
                'daily' => self::collect_table_rows('reaccess_daily'),
                'site_daily' => self::collect_table_rows('reaccess_site_daily'),
                'notice' => self::collect_table_rows('reaccess_notice'),
            ];
        }

        return $payload;
    }

    /**
     * Collect option data
     *
     * @return array
     */
    private static function collect_options() {
        $options = [];

        $base_keys = [
            're_access_ranking_settings',
            're_access_url_aliases',
        ];

        foreach ($base_keys as $key) {
            $value = get_option($key, null);
            if ($value !== null) {
                $options[$key] = $value;
            }
        }

        for ($slot = 1; $slot <= 10; $slot++) {
            $link_key = 're_access_link_slot_' . $slot;
            $rss_key = 're_access_rss_slot_' . $slot;

            $link_value = get_option($link_key, null);
            if ($link_value !== null) {
                $options[$link_key] = $link_value;
            }

            $rss_value = get_option($rss_key, null);
            if ($rss_value !== null) {
                $options[$rss_key] = $rss_value;
            }
        }

        return $options;
    }

    /**
     * Collect rows from a table
     *
     * @param string $table_name
     * @return array
     */
    private static function collect_table_rows($table_name) {
        global $wpdb;

        $table = $wpdb->prefix . $table_name;
        if (!self::table_exists($table)) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return $rows;
    }

    /**
     * Import options
     *
     * @param array $options
     */
    private static function import_options(array $options) {
        $allowed_keys = [
            're_access_ranking_settings',
            're_access_url_aliases',
        ];

        for ($slot = 1; $slot <= 10; $slot++) {
            $allowed_keys[] = 're_access_link_slot_' . $slot;
            $allowed_keys[] = 're_access_rss_slot_' . $slot;
        }

        foreach ($allowed_keys as $key) {
            if (array_key_exists($key, $options)) {
                update_option($key, $options[$key]);
            }
        }
    }

    /**
     * Import site data
     *
     * @param array $sites
     * @param string $mode
     */
    private static function import_sites(array $sites, $mode) {
        global $wpdb;

        $table = $wpdb->prefix . 'reaccess_sites';
        if (!self::table_exists($table) || empty($sites)) {
            return;
        }

        $columns = self::get_table_columns($table);
        if (empty($columns)) {
            return;
        }

        if ($mode === 'replace') {
            self::replace_sites($table, $columns, $sites);
            return;
        }

        $existing = $wpdb->get_results("SELECT id, site_url FROM {$table}", ARRAY_A);
        $existing_map = [];
        if (is_array($existing)) {
            foreach ($existing as $row) {
                $normalized = self::normalize_url($row['site_url'] ?? '');
                if ($normalized !== '') {
                    $existing_map[$normalized] = (int) $row['id'];
                }
            }
        }

        foreach ($sites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $site_url_raw = isset($site['site_url']) ? $site['site_url'] : '';
            $normalized = self::normalize_url($site_url_raw);
            if ($normalized === '') {
                continue;
            }

            $update_data = self::prepare_site_data($site, $columns, false);
            if (empty($update_data)) {
                continue;
            }

            if (isset($existing_map[$normalized])) {
                $wpdb->update(
                    $table,
                    $update_data,
                    ['id' => $existing_map[$normalized]]
                );
                continue;
            }

            $insert_data = self::prepare_site_data($site, $columns, true);
            if (empty($insert_data)) {
                continue;
            }

            $wpdb->insert($table, $insert_data);
        }
    }

    /**
     * Replace sites table data
     *
     * @param string $table
     * @param array $columns
     * @param array $sites
     */
    private static function replace_sites($table, array $columns, array $sites) {
        global $wpdb;

        $transaction_started = self::start_transaction();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");

        $has_error = false;
        foreach ($sites as $site) {
            if (!is_array($site)) {
                continue;
            }

            $insert_data = self::prepare_site_data($site, $columns, true);
            if (empty($insert_data)) {
                continue;
            }

            $result = $wpdb->insert($table, $insert_data);
            if ($result === false) {
                $has_error = true;
                break;
            }
        }

        self::complete_transaction($transaction_started, $has_error);
    }

    /**
     * Import metrics data
     *
     * @param array $metrics
     * @param string $mode
     */
    private static function import_metrics(array $metrics, $mode) {
        global $wpdb;

        $tables = [
            'daily' => 'reaccess_daily',
            'site_daily' => 'reaccess_site_daily',
            'notice' => 'reaccess_notice',
        ];

        foreach ($tables as $key => $table_name) {
            if (empty($metrics[$key]) || !is_array($metrics[$key])) {
                continue;
            }

            $table = $wpdb->prefix . $table_name;
            if (!self::table_exists($table)) {
                continue;
            }

            $columns = self::get_table_columns($table);
            if (empty($columns)) {
                continue;
            }

            if ($mode === 'replace') {
                $transaction_started = self::start_transaction();
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query("TRUNCATE TABLE {$table}");
            }

            $has_error = false;
            foreach ($metrics[$key] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $data = self::prepare_table_row($row, $columns);
                if (empty($data)) {
                    continue;
                }

                if ($mode === 'merge') {
                    if ($table_name === 'reaccess_notice') {
                        $result = $wpdb->insert($table, $data);
                    } else {
                        $result = $wpdb->replace($table, $data);
                    }
                } else {
                    $result = $wpdb->insert($table, $data);
                }

                if ($result === false) {
                    $has_error = true;
                    break;
                }
            }

            if ($mode === 'replace') {
                self::complete_transaction($transaction_started, $has_error);
            }
        }
    }

    /**
     * Prepare site row data
     *
     * @param array $site
     * @param array $columns
     * @param bool $include_created_at
     * @return array
     */
    private static function prepare_site_data(array $site, array $columns, $include_created_at) {
        $data = [];

        if (in_array('site_name', $columns, true)) {
            $data['site_name'] = sanitize_text_field($site['site_name'] ?? '');
        }

        if (in_array('site_url', $columns, true)) {
            $data['site_url'] = self::sanitize_site_url($site['site_url'] ?? '');
            if ($data['site_url'] === '') {
                return [];
            }
        }

        if (in_array('rss_url', $columns, true)) {
            $data['rss_url'] = self::sanitize_site_url($site['rss_url'] ?? '');
        }

        if (in_array('link_slots', $columns, true)) {
            $data['link_slots'] = self::sanitize_slot_value($site['link_slots'] ?? '');
        }

        if (in_array('status', $columns, true)) {
            $data['status'] = sanitize_text_field($site['status'] ?? 'approved');
        }

        if ($include_created_at && in_array('created_at', $columns, true) && !empty($site['created_at'])) {
            $data['created_at'] = sanitize_text_field($site['created_at']);
        }

        return $data;
    }

    /**
     * Prepare generic table row
     *
     * @param array $row
     * @param array $columns
     * @return array
     */
    private static function prepare_table_row(array $row, array $columns) {
        $data = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $data[$column] = $row[$column];
            }
        }

        return $data;
    }

    /**
     * Normalize URL for matching
     *
     * @param string $url
     * @return string
     */
    private static function normalize_url($url) {
        if (class_exists('RE_Access_Database') && method_exists('RE_Access_Database', 'normalize_url')) {
            return RE_Access_Database::normalize_url($url);
        }

        $trimmed = trim((string) $url);
        return strtolower(rtrim($trimmed, '/'));
    }

    /**
     * Sanitize URL for storage
     *
     * @param string $url
     * @return string
     */
    private static function sanitize_site_url($url) {
        if (class_exists('RE_Access_Database') && method_exists('RE_Access_Database', 'sanitize_url_for_storage')) {
            return RE_Access_Database::sanitize_url_for_storage($url);
        }

        return esc_url_raw($url);
    }

    /**
     * Sanitize slot values
     *
     * @param mixed $value
     * @return string
     */
    private static function sanitize_slot_value($value) {
        if (is_array($value)) {
            $slots = array_filter(array_map('absint', $value));
            return implode(',', $slots);
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Check table existence
     *
     * @param string $table
     * @return bool
     */
    private static function table_exists($table) {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table
        ));

        return $found === $table;
    }

    /**
     * Get table columns
     *
     * @param string $table
     * @return array
     */
    private static function get_table_columns($table) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (!is_array($columns)) {
            return [];
        }

        return $columns;
    }

    /**
     * Start transaction if possible
     *
     * @return bool
     */
    private static function start_transaction() {
        global $wpdb;

        $result = $wpdb->query('START TRANSACTION');
        return $result !== false;
    }

    /**
     * Complete transaction
     *
     * @param bool $transaction_started
     * @param bool $has_error
     */
    private static function complete_transaction($transaction_started, $has_error) {
        global $wpdb;

        if (!$transaction_started) {
            return;
        }

        if ($has_error) {
            $wpdb->query('ROLLBACK');
        } else {
            $wpdb->query('COMMIT');
        }
    }

    /**
     * Redirect with message
     *
     * @param string $message
     */
    private static function redirect_with_message($message) {
        $url = add_query_arg(
            're_access_message',
            $message,
            admin_url('admin.php?page=re-access-import-export')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Get notice message
     *
     * @param string $message
     * @return array|null
     */
    private static function get_notice_message($message) {
        $messages = [
            'export_failed' => [
                'class' => 'notice-error',
                'text' => 'エクスポートに失敗しました。',
            ],
            'import_success' => [
                'class' => 'notice-success',
                'text' => 'インポートが完了しました。',
            ],
            'import_file_missing' => [
                'class' => 'notice-error',
                'text' => 'インポートファイルが見つかりません。',
            ],
            'import_upload_failed' => [
                'class' => 'notice-error',
                'text' => 'ファイルのアップロードに失敗しました。',
            ],
            'import_file_too_large' => [
                'class' => 'notice-error',
                'text' => 'ファイルサイズが大きすぎます（10MBまで）。',
            ],
            'import_invalid_file' => [
                'class' => 'notice-error',
                'text' => 'JSONファイルのみアップロードできます。',
            ],
            'import_invalid_json' => [
                'class' => 'notice-error',
                'text' => 'JSONの解析に失敗しました。',
            ],
            'import_replace_confirm' => [
                'class' => 'notice-error',
                'text' => '全置換を実行するには確認チェックが必要です。',
            ],
        ];

        return $messages[$message] ?? null;
    }
}
