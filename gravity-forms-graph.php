<?php
/**
 * Plugin Name: Gravity Forms Graph
 * Plugin URI: https://github.com/InfactAi/gravity-form-graph
 * Description: Visualize Gravity Forms submission statistics with interactive charts and graphs. View daily, weekly, and monthly submission trends.
 * Version: 1.1.0
 * Author: Infact.ai
 * Author URI: https://infact.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gravity-forms-graph
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: gravityforms
 *
 * @package GravityFormsGraph
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('GFG_VERSION', '1.1.0');
define('GFG_PLUGIN_FILE', __FILE__);
define('GFG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GFG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GFG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Gravity_Forms_Graph {

    /**
     * Single instance of the class
     *
     * @var Gravity_Forms_Graph
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Gravity_Forms_Graph
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once GFG_PLUGIN_DIR . 'includes/class-admin.php';
        require_once GFG_PLUGIN_DIR . 'includes/class-assets.php';
        require_once GFG_PLUGIN_DIR . 'includes/class-ajax.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check if Gravity Forms is active
        add_action('admin_init', array($this, 'check_dependencies'));

        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    /**
     * Check if Gravity Forms is active
     */
    public function check_dependencies() {
        if (!class_exists('GFForms')) {
            add_action('admin_notices', array($this, 'gravity_forms_missing_notice'));

            // Deactivate plugin if Gravity Forms is not active
            deactivate_plugins(GFG_PLUGIN_BASENAME);

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    /**
     * Display admin notice if Gravity Forms is missing
     */
    public function gravity_forms_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: Gravity Forms plugin link */
                        __('Gravity Forms Graph requires %s to be installed and activated.', 'gravity-forms-graph'),
                        '<strong>Gravity Forms</strong>'
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'gravity-forms-graph',
            false,
            dirname(GFG_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        if (!class_exists('GFForms')) {
            return;
        }

        // Initialize admin pages
        GFG_Admin::instance();

        // Initialize assets handler
        GFG_Assets::instance();

        // Initialize AJAX handlers
        GFG_Ajax::instance();
    }
}

/**
 * Initialize the plugin
 */
function gfg_init() {
    return Gravity_Forms_Graph::instance();
}

// Start the plugin
gfg_init();

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'gfg_activate');
function gfg_activate() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(GFG_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Gravity Forms Graph requires PHP 7.4 or higher.', 'gravity-forms-graph'),
            esc_html__('Plugin Activation Error', 'gravity-forms-graph'),
            array('back_link' => true)
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.8', '<')) {
        deactivate_plugins(GFG_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Gravity Forms Graph requires WordPress 5.8 or higher.', 'gravity-forms-graph'),
            esc_html__('Plugin Activation Error', 'gravity-forms-graph'),
            array('back_link' => true)
        );
    }

    // Check if Gravity Forms is active
    if (!class_exists('GFForms')) {
        deactivate_plugins(GFG_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Gravity Forms Graph requires Gravity Forms to be installed and activated.', 'gravity-forms-graph'),
            esc_html__('Plugin Activation Error', 'gravity-forms-graph'),
            array('back_link' => true)
        );
    }
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'gfg_deactivate');
function gfg_deactivate() {
    // Clean up any transients or temporary data
    delete_transient('gfg_cache_');
}
