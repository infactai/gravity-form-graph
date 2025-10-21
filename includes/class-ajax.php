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
        $form_ids = isset($_POST['form_id']) ? $_POST['form_id'] : array();

        // Handle both single and multiple form IDs
        if (!is_array($form_ids)) {
            $form_ids = array($form_ids);
        }

        // Sanitize form IDs
        $form_ids = array_map('absint', $form_ids);
        $form_ids = array_filter($form_ids); // Remove zeros

        $grouping = isset($_POST['grouping']) ? sanitize_text_field(wp_unslash($_POST['grouping'])) : 'daily';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        // Validate form IDs
        if (empty($form_ids)) {
            wp_send_json_error(array(
                'message' => __('Please select at least one form', 'gravity-forms-graph')
            ));
        }

        // Validate dates
        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(array(
                'message' => __('Invalid date range', 'gravity-forms-graph')
            ));
        }

        // Validate grouping
        if (!in_array($grouping, array('hourly', 'daily', 'weekly', 'monthly'), true)) {
            $grouping = 'daily';
        }

        // Get the data for all selected forms
        $all_labels = array();
        $datasets = array();
        $conversion_datasets = array();

        foreach ($form_ids as $form_id) {
            // Get submission data
            $submission_data = $this->query_submissions($form_id, $grouping, $start_date, $end_date);

            if (is_wp_error($submission_data)) {
                continue; // Skip this form if there's an error
            }

            // Get views data
            $views_data = $this->query_views($form_id, $grouping, $start_date, $end_date);

            // Check if views data is valid (handle errors gracefully)
            if (is_wp_error($views_data)) {
                // If no views data, create empty views data structure
                $views_data = array(
                    'labels' => $submission_data['labels'],
                    'data' => array_fill(0, count($submission_data['labels']), 0),
                );
            }

            // Calculate conversion rate
            $conversion_data = $this->calculate_conversion_rate($submission_data, $views_data);

            // Get form title
            $form = GFAPI::get_form($form_id);
            $form_title = $form ? $form['title'] : 'Form ' . $form_id;

            // Store labels (use the first form's labels)
            if (empty($all_labels)) {
                $all_labels = $submission_data['labels'];
            }

            // Add to datasets
            $datasets[] = array(
                'label' => $form_title,
                'data' => $submission_data['data'],
                'form_id' => $form_id,
                'stats' => isset($submission_data['stats']) ? $submission_data['stats'] : array(
                    'total' => 0,
                    'average' => 0,
                    'peak_period' => '',
                    'peak_count' => 0,
                ),
            );

            $conversion_datasets[] = array(
                'label' => $form_title,
                'data' => isset($conversion_data['data']) ? $conversion_data['data'] : array(),
                'form_id' => $form_id,
                'stats' => isset($conversion_data['stats']) ? $conversion_data['stats'] : array(
                    'total_views' => 0,
                    'total_submissions' => 0,
                    'conversion_rate' => 0,
                ),
            );
        }

        if (empty($datasets)) {
            wp_send_json_error(array(
                'message' => __('No data found for selected forms', 'gravity-forms-graph')
            ));
        }

        wp_send_json_success(array(
            'labels' => $all_labels,
            'datasets' => $datasets,
            'conversion_datasets' => $conversion_datasets,
        ));
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
            case 'hourly':
                $date_format = '%Y-%m-%d %H:00:00';
                break;
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
                case 'hourly':
                    $period_key = $current->format('Y-m-d H:00:00');
                    $label = $current->format('M j, Y g:00 A');
                    $current->modify('+1 hour');
                    break;
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

    /**
     * Query form views from database
     *
     * @param int    $form_id Form ID
     * @param string $grouping Grouping type (hourly, daily, weekly, monthly)
     * @param string $start_date Start date (YYYY-MM-DD)
     * @param string $end_date End date (YYYY-MM-DD)
     * @return array|WP_Error
     */
    private function query_views($form_id, $grouping, $start_date, $end_date) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gf_form_view';

        // Determine date format based on grouping
        switch ($grouping) {
            case 'hourly':
                $date_format = '%Y-%m-%d %H:00:00';
                break;
            case 'daily':
                $date_format = '%Y-%m-%d';
                break;
            case 'weekly':
                $date_format = '%Y-W%v';
                break;
            case 'monthly':
                $date_format = '%Y-%m';
                break;
            default:
                $date_format = '%Y-%m-%d';
        }

        // Query to get view counts grouped by date
        $query = $wpdb->prepare(
            "SELECT
                DATE_FORMAT(date_created, %s) as period,
                SUM(count) as count,
                MIN(date_created) as period_start
            FROM {$table_name}
            WHERE form_id = %d
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

        // Process results
        $labels = array();
        $counts = array();

        // Fill in any gaps in the data with zeros
        $filled_data = $this->fill_date_gaps($results, $grouping, $start_date, $end_date);

        foreach ($filled_data as $row) {
            $labels[] = $row['label'];
            $counts[] = $row['count'];
        }

        return array(
            'labels' => $labels,
            'data' => $counts,
        );
    }

    /**
     * Calculate conversion rate from submissions and views data
     *
     * @param array $submission_data Submission data with labels and counts
     * @param array $views_data Views data with labels and counts
     * @return array Conversion rate data
     */
    private function calculate_conversion_rate($submission_data, $views_data) {
        $conversion_rates = array();
        $total_views = 0;
        $total_submissions = 0;

        // Calculate conversion rate for each period
        for ($i = 0; $i < count($submission_data['data']); $i++) {
            $submissions = $submission_data['data'][$i];
            $views = isset($views_data['data'][$i]) ? $views_data['data'][$i] : 0;

            $total_views += $views;
            $total_submissions += $submissions;

            // Calculate conversion rate as percentage
            if ($views > 0) {
                $conversion_rates[] = round(($submissions / $views) * 100, 2);
            } else {
                $conversion_rates[] = 0;
            }
        }

        // Calculate overall conversion rate
        $overall_rate = $total_views > 0 ? round(($total_submissions / $total_views) * 100, 2) : 0;

        return array(
            'labels' => $submission_data['labels'],
            'data' => $conversion_rates,
            'stats' => array(
                'total_views' => $total_views,
                'total_submissions' => $total_submissions,
                'conversion_rate' => $overall_rate,
            ),
        );
    }
}
