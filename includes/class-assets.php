<?php
/**
 * Assets Handler
 *
 * Handles enqueuing scripts and styles
 *
 * @package GravityFormsGraph
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets class
 */
class GFG_Assets {

    /**
     * Single instance
     *
     * @var GFG_Assets
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return GFG_Assets
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our specific page
        if ($hook !== 'forms_page_gf-submission-reports') {
            return;
        }

        // Enqueue Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Enqueue Date Adapter for Chart.js (for better date handling)
        wp_enqueue_script(
            'chartjs-adapter-date-fns',
            'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
            array('chartjs'),
            '3.0.0',
            true
        );

        // Enqueue our custom admin JS
        wp_enqueue_script(
            'gfg-admin-reports',
            GFG_PLUGIN_URL . 'assets/js/admin-reports.js',
            array('jquery', 'chartjs'),
            GFG_VERSION,
            true
        );

        // Enqueue our custom admin CSS
        wp_enqueue_style(
            'gfg-admin-reports',
            GFG_PLUGIN_URL . 'assets/css/admin-reports.css',
            array(),
            GFG_VERSION
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('gfg-admin-reports', 'gfgReportsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gfg_reports_nonce'),
            'strings' => array(
                'selectForm' => __('Please select a form', 'gravity-forms-graph'),
                'invalidDateRange' => __('Please select a valid date range', 'gravity-forms-graph'),
                'fetchError' => __('Failed to fetch report data', 'gravity-forms-graph'),
                'submissions' => __('Submissions', 'gravity-forms-graph'),
                'chartTitle' => __('Form Submissions Over Time', 'gravity-forms-graph'),
                'submissionsLabel' => __('Number of Submissions', 'gravity-forms-graph'),
                'timePeriodLabel' => __('Time Period', 'gravity-forms-graph'),
            ),
        ));
    }
}
