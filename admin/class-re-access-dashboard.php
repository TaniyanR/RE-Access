<?php
/**
 * Dashboard admin page
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Dashboard {
    
    /**
     * Render dashboard page
     */
    public static function render() {
        // Enqueue Chart.js properly
        wp_enqueue_script(
            're-access-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '4.4.0',
            true
        );
        
        // Get period from request
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '7';
        $period = in_array($period, ['1', '7', '30']) ? $period : '7';
        
        // Get access data
        $data = self::get_access_data($period);
        $kpis = self::get_kpis($period);
        
        ?>
        <div class="wrap re-access-dashboard">
            <h1><?php echo esc_html__('RE:Access Dashboard', 're-access'); ?></h1>
            
            <!-- Period Selection -->
            <div class="period-selector" style="margin: 20px 0;">
                <a href="?page=re-access&period=1" class="button <?php echo $period == '1' ? 'button-primary' : ''; ?>">
                    <?php esc_html_e('1 Day', 're-access'); ?>
                </a>
                <a href="?page=re-access&period=7" class="button <?php echo $period == '7' ? 'button-primary' : ''; ?>">
                    <?php esc_html_e('1 Week', 're-access'); ?>
                </a>
                <a href="?page=re-access&period=30" class="button <?php echo $period == '30' ? 'button-primary' : ''; ?>">
                    <?php esc_html_e('1 Month', 're-access'); ?>
                </a>
            </div>
            
            <!-- KPI Display -->
            <div class="kpi-container" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="kpi-box" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px; color: #666;"><?php esc_html_e('Total IN', 're-access'); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html(number_format($kpis['total_in'])); ?></p>
                </div>
                <div class="kpi-box" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px; color: #666;"><?php esc_html_e('Unique Users', 're-access'); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html(number_format($kpis['total_uu'])); ?></p>
                </div>
                <div class="kpi-box" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px; color: #666;"><?php esc_html_e('Page Views', 're-access'); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html(number_format($kpis['total_pv'])); ?></p>
                </div>
                <div class="kpi-box" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px; color: #666;"><?php esc_html_e('Total OUT', 're-access'); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0;"><?php echo esc_html(number_format($kpis['total_out'])); ?></p>
                </div>
            </div>
            
            <!-- Access Trend Graph -->
            <div class="chart-container" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Access Trend', 're-access'); ?></h2>
                <canvas id="accessChart" width="400" height="100"></canvas>
            </div>
            
            <!-- Daily Details Table -->
            <div class="table-container" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                <h2><?php esc_html_e('Daily Details', 're-access'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 're-access'); ?></th>
                            <th><?php esc_html_e('IN', 're-access'); ?></th>
                            <th><?php esc_html_e('OUT', 're-access'); ?></th>
                            <th><?php esc_html_e('PV', 're-access'); ?></th>
                            <th><?php esc_html_e('UU', 're-access'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data)): ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?php echo esc_html($row->date); ?></td>
                                    <td><?php echo esc_html(number_format($row->in_count)); ?></td>
                                    <td><?php echo esc_html(number_format($row->out_count)); ?></td>
                                    <td><?php echo esc_html(number_format($row->pv_count)); ?></td>
                                    <td><?php echo esc_html(number_format($row->uu_count)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No data available', 're-access'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        (function() {
            var ctx = document.getElementById('accessChart');
            if (!ctx) return;
            
            var data = <?php echo json_encode($data); ?>;
            var labels = data.map(function(item) { return item.date; });
            var inData = data.map(function(item) { return item.in_count; });
            var outData = data.map(function(item) { return item.out_count; });
            var pvData = data.map(function(item) { return item.pv_count; });
            var uuData = data.map(function(item) { return item.uu_count; });
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'IN',
                            data: inData,
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        },
                        {
                            label: 'OUT',
                            data: outData,
                            borderColor: 'rgb(255, 99, 132)',
                            tension: 0.1
                        },
                        {
                            label: 'PV',
                            data: pvData,
                            borderColor: 'rgb(54, 162, 235)',
                            tension: 0.1
                        },
                        {
                            label: 'UU',
                            data: uuData,
                            borderColor: 'rgb(255, 206, 86)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Get access data for period
     */
    private static function get_access_data($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_daily';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT date, in_count, out_count, pv_count, uu_count 
             FROM $table 
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             ORDER BY date ASC",
            $days
        ));
        
        return $results ?: [];
    }
    
    /**
     * Get KPI totals for period
     */
    private static function get_kpis($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'reaccess_daily';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(in_count) as total_in,
                SUM(out_count) as total_out,
                SUM(pv_count) as total_pv,
                SUM(uu_count) as total_uu
             FROM $table 
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $days
        ));
        
        return [
            'total_in' => $result ? (int)$result->total_in : 0,
            'total_out' => $result ? (int)$result->total_out : 0,
            'total_pv' => $result ? (int)$result->total_pv : 0,
            'total_uu' => $result ? (int)$result->total_uu : 0,
        ];
    }
}
