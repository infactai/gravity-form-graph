<?php
/**
 * Admin Page Handler
 *
 * Handles admin menu registration and page rendering
 *
 * @package GravityFormsGraph
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class GFG_Admin {

    /**
     * Single instance
     *
     * @var GFG_Admin
     */
    private static $instance = null;

    /**
     * Page hook suffix
     *
     * @var string
     */
    private $page_hook = '';

    /**
     * Get singleton instance
     *
     * @return GFG_Admin
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
        // Use priority 99 to add menu item near the bottom
        add_action('admin_menu', array($this, 'register_admin_menu'), 99);
    }

    /**
     * Register admin menu page
     */
    public function register_admin_menu() {
        $this->page_hook = add_submenu_page(
            'gf_edit_forms',
            __('Graphs', 'gravity-forms-graph'),
            __('Graphs', 'gravity-forms-graph'),
            'gravityforms_view_entries',
            'gf-submission-reports',
            array($this, 'render_page')
        );
    }

    /**
     * Get the page hook
     *
     * @return string
     */
    public function get_page_hook() {
        return $this->page_hook;
    }

    /**
     * Render the admin page
     */
    public function render_page() {
        // Check user capabilities
        if (!current_user_can('gravityforms_view_entries')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'gravity-forms-graph'));
        }

        // Check if Gravity Forms is active
        if (!class_exists('GFAPI')) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Graphs', 'gravity-forms-graph') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Gravity Forms plugin is required for this feature.', 'gravity-forms-graph') . '</p></div>';
            echo '</div>';
            return;
        }

        // Get all active forms
        $forms = GFAPI::get_forms();

        ?>
        <div class="wrap gfg-reports-wrap">
            <h1><?php esc_html_e('Graphs', 'gravity-forms-graph'); ?></h1>

            <div class="gfg-reports-controls">
                <div class="gfg-reports-control-row">
                    <div class="gfg-reports-control">
                        <label for="gfg-form-select"><?php esc_html_e('Select Form(s):', 'gravity-forms-graph'); ?></label>
                        <select id="gfg-form-select" class="gfg-form-select" multiple size="5">
                            <?php foreach ($forms as $form) : ?>
                                <option value="<?php echo esc_attr($form['id']); ?>">
                                    <?php echo esc_html($form['id'] . ' - ' . $form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple forms', 'gravity-forms-graph'); ?></small>
                    </div>

                    <div class="gfg-reports-control">
                        <label for="gfg-date-range"><?php esc_html_e('Date Range:', 'gravity-forms-graph'); ?></label>
                        <select id="gfg-date-range" class="gfg-date-range">
                            <option value="7"><?php esc_html_e('Last 7 days', 'gravity-forms-graph'); ?></option>
                            <option value="30" selected><?php esc_html_e('Last 30 days', 'gravity-forms-graph'); ?></option>
                            <option value="90"><?php esc_html_e('Last 90 days', 'gravity-forms-graph'); ?></option>
                            <option value="365"><?php esc_html_e('Last year', 'gravity-forms-graph'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom range', 'gravity-forms-graph'); ?></option>
                        </select>
                    </div>

                    <div class="gfg-reports-control gfg-custom-dates" style="display: none;">
                        <label for="gfg-start-date"><?php esc_html_e('Start Date:', 'gravity-forms-graph'); ?></label>
                        <input type="date" id="gfg-start-date" class="gfg-start-date">
                    </div>

                    <div class="gfg-reports-control gfg-custom-dates" style="display: none;">
                        <label for="gfg-end-date"><?php esc_html_e('End Date:', 'gravity-forms-graph'); ?></label>
                        <input type="date" id="gfg-end-date" class="gfg-end-date">
                    </div>

                    <div class="gfg-reports-control">
                        <label for="gfg-grouping"><?php esc_html_e('Group By:', 'gravity-forms-graph'); ?></label>
                        <select id="gfg-grouping" class="gfg-grouping">
                            <option value="hourly"><?php esc_html_e('Hourly', 'gravity-forms-graph'); ?></option>
                            <option value="daily" selected><?php esc_html_e('Daily', 'gravity-forms-graph'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly', 'gravity-forms-graph'); ?></option>
                            <option value="monthly"><?php esc_html_e('Monthly', 'gravity-forms-graph'); ?></option>
                        </select>
                    </div>

                    <div class="gfg-reports-control">
                        <button id="gfg-generate-report" class="button button-primary">
                            <?php esc_html_e('Generate Report', 'gravity-forms-graph'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="gfg-reports-chart-container">
                <div class="gfg-reports-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <p><?php esc_html_e('Loading report data...', 'gravity-forms-graph'); ?></p>
                </div>
                <div class="gfg-reports-error" style="display: none;"></div>
                <div class="gfg-reports-stats" style="display: none;">
                    <div class="stat-box">
                        <span class="stat-label"><?php esc_html_e('Total Submissions', 'gravity-forms-graph'); ?></span>
                        <span class="stat-value" id="total-submissions">0</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label"><?php esc_html_e('Average per Period', 'gravity-forms-graph'); ?></span>
                        <span class="stat-value" id="avg-submissions">0</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label"><?php esc_html_e('Peak Period', 'gravity-forms-graph'); ?></span>
                        <span class="stat-value" id="peak-period">-</span>
                    </div>
                </div>
                <canvas id="gfg-reports-chart"></canvas>

                <div class="gfg-conversion-charts" style="display: none; margin-top: 40px;">
                    <h2><?php esc_html_e('Conversion Rates (Views to Submissions)', 'gravity-forms-graph'); ?></h2>
                    <div id="gfg-conversion-charts-container"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
