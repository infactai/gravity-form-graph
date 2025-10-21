<?php
/**
 * Uninstall Gravity Forms Graph
 *
 * This file runs when the plugin is uninstalled (deleted).
 * It cleans up any data created by the plugin.
 *
 * @package GravityFormsGraph
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up any transients that may have been created
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_gfg_%'
    OR option_name LIKE '_transient_timeout_gfg_%'"
);

// Note: We don't delete any Gravity Forms data as it belongs to Gravity Forms
// This plugin only reads that data, it doesn't create or modify it
