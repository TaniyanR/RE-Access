<?php
/**
 * Return prioritization logic (IN-OUT).
 *
 * @package ReAccess
 */

if (!defined('WPINC')) {
    die;
}

class RE_Access_Return {
    private const CACHE_TTL = 20 * MINUTE_IN_SECONDS;
    private const SHUFFLE_POOL_SIZE = 10;

    /**
     * Cached results for the current request.
     *
     * @var array<string,array>
     */
    private static $cached = [];

    /**
     * Get prioritized sites based on return needs (IN - OUT).
     *
     * @param string $type 'link' or 'rss'.
     * @param int $limit
     * @return array
     */
    public static function get_prioritized_sites($type, $limit) {
        $limit = absint($limit);
        if ($limit === 0) {
            return [];
        }

        $type = $type === 'rss' ? 'rss' : 'link';
        $sites = self::get_sites_with_priorities();

        if ($type === 'rss') {
            $sites = array_values(array_filter($sites, static function ($site) {
                return !empty($site->rss_url);
            }));
        }

        if (empty($sites)) {
            return [];
        }

        usort($sites, static function ($a, $b) {
            $priority_a = (int) ($a->return_need ?? 0);
            $priority_b = (int) ($b->return_need ?? 0);
            if ($priority_a === $priority_b) {
                return $b->id <=> $a->id;
            }
            return $priority_b <=> $priority_a;
        });

        $pool_size = min(self::SHUFFLE_POOL_SIZE, count($sites));
        $pool = array_slice($sites, 0, $pool_size);
        if (count($pool) > 1) {
            shuffle($pool);
        }

        $selected = array_slice($pool, 0, $limit);
        if (count($selected) < $limit && $pool_size < count($sites)) {
            $selected = array_merge(
                $selected,
                array_slice($sites, $pool_size, $limit - count($selected))
            );
        }

        return $selected;
    }

    /**
     * Build site list with return priorities (IN-OUT).
     *
     * @return array
     */
    private static function get_sites_with_priorities() {
        if (isset(self::$cached['sites'])) {
            return self::$cached['sites'];
        }

        $period = 7;
        if (class_exists('RE_Access_Ranking') && method_exists('RE_Access_Ranking', 'get_aggregation_period')) {
            $period = (int) RE_Access_Ranking::get_aggregation_period();
        }
        $period = max(1, $period);
        $cache_key = 're_access_return_sites_' . $period;

        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            self::$cached['sites'] = $cached;
            return $cached;
        }

        global $wpdb;
        $sites_table = $wpdb->prefix . 'reaccess_sites';
        $tracking_table = $wpdb->prefix . 'reaccess_site_daily';
        $interval = $period - 1;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.site_name, s.site_url, s.rss_url,
                COALESCE(SUM(t.`in`), 0) as total_in,
                COALESCE(SUM(t.`out`), 0) as total_out
             FROM $sites_table s
             LEFT JOIN $tracking_table t
               ON s.id = t.site_id
              AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             WHERE s.status = 'approved'
             GROUP BY s.id",
            $interval
        ));

        $sites = [];
        foreach ($rows as $row) {
            $row->return_need = max(0, (int) $row->total_in - (int) $row->total_out);
            $sites[] = $row;
        }

        set_transient($cache_key, $sites, self::CACHE_TTL);
        self::$cached['sites'] = $sites;

        return $sites;
    }
}
