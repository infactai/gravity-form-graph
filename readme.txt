=== Gravity Forms Graph ===

Contributors: infactai
Tags: gravity forms, analytics, charts, reports, statistics
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visualize Gravity Forms submission statistics with interactive charts and graphs.

== Description ==

Gravity Forms Graph is a powerful analytics tool that helps you visualize your Gravity Forms submission data over time. Get insights into form performance with beautiful, interactive charts powered by Chart.js.

= Features =

* **Interactive Charts** - Beautiful line charts showing submission trends
* **Flexible Date Ranges** - View data for last 7, 30, 90 days, last year, or custom ranges
* **Multiple Grouping Options** - Group submissions by day, week, or month
* **Key Statistics** - See total submissions, averages, and peak periods at a glance
* **Form Selection** - Analyze any of your Gravity Forms
* **Responsive Design** - Works perfectly on desktop and mobile devices
* **Performance Optimized** - Chart.js loaded only on the reports page

= Usage =

1. Install and activate the plugin
2. Navigate to **Tools → Form Reports** in your WordPress admin
3. Select a Gravity Form from the dropdown
4. Choose your preferred date range and grouping
5. Click "Generate Report" to view your interactive chart

= Requirements =

* Gravity Forms plugin (active)
* WordPress 5.8 or higher
* PHP 7.4 or higher

== Installation ==

1. Upload the `gravity-forms-graph` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Gravity Forms is installed and activated
4. Navigate to Tools → Form Reports to start viewing your analytics

== Frequently Asked Questions ==

= Does this plugin work without Gravity Forms? =

No, Gravity Forms Graph requires Gravity Forms to be installed and activated.

= Where can I view the reports? =

Navigate to **Tools → Form Reports** in your WordPress admin dashboard.

= Can I export the report data? =

The current version displays data in interactive charts. Export functionality may be added in future versions.

= What permissions are required? =

Users need the `gravityforms_view_entries` capability to view reports.

== Screenshots ==

1. Main reports page with form selection and filters
2. Interactive chart showing submission trends
3. Statistics panel with key metrics

== Changelog ==

= 1.1.0 =
* Added multiple form selection with color-coded visualization
* Added conversion rate graphs (views to submissions)
* Added hourly grouping option
* Moved menu from Tools to Gravity Forms menu
* Added form ID prefix in form selection dropdown
* Enhanced error handling for forms with no view data
* Changed default grouping from monthly to daily

= 1.0.0 =
* Initial release
* Interactive line charts for form submissions
* Daily, weekly, and monthly grouping
* Flexible date range selection
* Statistics dashboard with totals and averages

== Upgrade Notice ==

= 1.1.0 =
Major update: Multiple form selection, conversion rate tracking, and hourly grouping now available.

= 1.0.0 =
Initial release of Gravity Forms Graph.
