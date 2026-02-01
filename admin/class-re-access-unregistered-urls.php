<?php
/**
 * Unregistered referrer URLs list
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Unregistered_URLs {

    /**
     * Render unregistered URLs page.
     */
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 're-access'));
        }

        $period = isset($_GET['period']) ? (int) $_GET['period'] : 7;
        if (!in_array($period, [7, 30], true)) {
            $period = 7;
        }

        $rows = self::get_unregistered_rows($period);
        $sites_url = admin_url('admin.php?page=re-access-sites&status=approved');
        ?>
        <div class="wrap re-access-unregistered-urls">
            <h1><?php echo esc_html__('未登録URL', 're-access'); ?></h1>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <form method="get">
                    <input type="hidden" name="page" value="re-access-unregistered-urls">
                    <label for="re-access-period"><?php echo esc_html__('期間', 're-access'); ?></label>
                    <select id="re-access-period" name="period">
                        <option value="7" <?php selected($period, 7); ?>><?php echo esc_html__('直近7日', 're-access'); ?></option>
                        <option value="30" <?php selected($period, 30); ?>><?php echo esc_html__('直近30日', 're-access'); ?></option>
                    </select>
                    <input type="submit" class="button" value="<?php echo esc_attr__('表示', 're-access'); ?>">
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h2><?php echo esc_html__('未登録リファラ一覧', 're-access'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('ホスト', 're-access'); ?></th>
                            <th><?php echo esc_html__('合計', 're-access'); ?></th>
                            <th><?php echo esc_html__('最終検知', 're-access'); ?></th>
                            <th><?php echo esc_html__('操作', 're-access'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)) : ?>
                            <?php foreach ($rows as $row) : ?>
                                <?php
                                $alias_url = add_query_arg(
                                    ['alias' => $row->ref_host],
                                    $sites_url
                                );
                                ?>
                                <tr>
                                    <td><?php echo esc_html($row->ref_host); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $row->total_count)); ?></td>
                                    <td><?php echo esc_html($row->last_seen); ?></td>
                                    <td>
                                        <a class="button button-small" href="<?php echo esc_url($alias_url); ?>">
                                            <?php echo esc_html__('サイト編集へ', 're-access'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4"><?php echo esc_html__('未登録リファラはありません。', 're-access'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Fetch aggregated unregistered referrer rows.
     *
     * @param int $period
     * @return array
     */
    private static function get_unregistered_rows($period) {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_unregistered_in';
        $interval = max(1, (int) $period) - 1;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ref_host, SUM(count) as total_count, MAX(last_seen) as last_seen
             FROM $table
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY ref_host
             ORDER BY total_count DESC, last_seen DESC",
            $interval
        ));

        return $rows ?: [];
    }
}
