<?php
/**
 * AJAX Handler
 *
 * Handles AJAX requests for fetching report data
 *
 * @package GravityFormsGraph
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX class
 */
class GFG_Ajax {

    /**
     * Single instance
     *
     * @var GFG_Ajax
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return GFG_Ajax
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_ajax_gfg_get_report_data', array($this, 'get_report_data'));
    }

    /**
     * AJAX handler to fetch form submission data
     */
    public function get_report_data() {
        // Verify nonce
        check_ajax_referer('gfg_reports_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('gravityforms_view_entries')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'gravity-forms-graph')
            ));
        }

        // Check if Gravity Forms is active
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array(
                'message' => __('Gravity Forms is not active', 'gravity-forms-graph')
            ));
        }

        // Get and sanitize parameters
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $grouping = isset($_POST['grouping']) ? sanitize_text_field(wp_unslash($_POST['grouping'])) : 'daily';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        // Validate form ID
        if (empty($form_id)) {
            wp_send_json_error(array(
                'message' => __('Please select a form', 'gravity-forms-graph')
            ));
        }

        // Validate dates
        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(array(
                'message' => __('Invalid date range', 'gravity-forms-graph')
            ));
        }

        // Validate grouping
        if (!in_array($grouping, array('daily', 'weekly', 'monthly'), true)) {
            $grouping = 'daily';
        }

        // Get the data
        $data = $this->query_submissions($form_id, $grouping, $start_date, $end_date);

        if (is_wp_error($data)) {
            wp_send_json_error(array(
                'message' => $data->get_error_message()
            ));
        }

        wp_send_json_success($data);
    }

    /**
     * Query form submissions from database
     *
     * @param int    $form_id Form ID
     * @param string $grouping Grouping type (daily, weekly, monthly)
     * @param string $start_date Start date (YYYY-MM-DD)
     * @param string $end_date End date (YYYY-MM-DD)
     * @return array|WP_Error
     */
    private function query_submissions($form_id, $grouping, $start_date, $end_date) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gf_entry';

        // Determine date format based on grouping
        switch ($grouping) {
            case 'daily':
                $date_format = '%Y-%m-%d';
                break;
            case 'weekly':
                // Group by year and week number
                $date_format = '%Y-W%v';
                break;
            case 'monthly':
                $date_format = '%Y-%m';
                break;
            default:
                $date_format = '%Y-%m-%d';
        }

        // Query to get submission counts grouped by date
        $query = $wpdb->prepare(
            "SELECT
                DATE_FORMAT(date_created, %s) as period,
                COUNT(*) as count,
                MIN(date_created) as period_start
            FROM {$table_name}
            WHERE form_id = %d
            AND status = 'active'
            AND date_created >= %s
            AND date_created <= %s
            GROUP BY period
            ORDER BY period_start ASC",
            $date_format,
            $form_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        $results = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            return new WP_Error('database_error', $wpdb->last_error);
        }

        // Process results for Chart.js
        $labels = array();
        $counts = array();
        $total = 0;
        $peak_count = 0;
        $peak_period = '';

        // Fill in any gaps in the data with zeros
        $filled_data = $this->fill_date_gaps($results, $grouping, $start_date, $end_date);

        foreach ($filled_data as $row) {
            $labels[] = $row['label'];
            $counts[] = $row['count'];
            $total += $row['count'];

            if ($row['count'] > $peak_count) {
                $peak_count = $row['count'];
                $peak_period = $row['label'];
            }
        }

        $avg = count($counts) > 0 ? round($total / count($counts), 1) : 0;

        return array(
            'labels' => $labels,
            'data' => $counts,
            'stats' => array(
                'total' => $total,
                'average' => $avg,
                'peak_period' => $peak_period,
                'peak_count' => $peak_count,
            ),
        );
    }

    /**
     * Fill in gaps in the data with zero counts
     *
     * @param array  $results Database results
     * @param string $grouping Grouping type
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array
     */
    private function fill_date_gaps($results, $grouping, $start_date, $end_date) {
        // Convert results to associative array
        $data_by_period = array();
        foreach ($results as $row) {
            $data_by_period[$row->period] = $row->count;
        }

        // Generate all periods in range
        $filled_data = array();
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        while ($current <= $end) {
            $period_key = '';
            $label = '';

            switch ($grouping) {
                case 'daily':
                    $period_key = $current->format('Y-m-d');
                    $label = $current->format('M j, Y');
                    $current->modify('+1 day');
                    break;
                case 'weekly':
                    $period_key = $current->format('Y-\WW');
                    $label = sprintf(
                        /* translators: %s: Week start date */
                        __('Week of %s', 'gravity-forms-graph'),
                        $current->format('M j, Y')
                    );
                    $current->modify('+1 week');
                    break;
                case 'monthly':
                    $period_key = $current->format('Y-m');
                    $label = $current->format('M Y');
                    $current->modify('+1 month');
                    break;
            }

            $filled_data[] = array(
                'period' => $period_key,
                'label' => $label,
                'count' => isset($data_by_period[$period_key]) ? absint($data_by_period[$period_key]) : 0,
            );
        }

        return $filled_data;
    }
}
