/**
 * Gravity Forms Graph - Admin JavaScript
 * Handles chart rendering and data fetching
 *
 * @package GravityFormsGraph
 */

(function($) {
    'use strict';

    let reportChart = null;
    let conversionCharts = [];

    // Color palette for multiple forms
    const COLOR_PALETTE = [
        { border: 'rgb(75, 192, 192)', background: 'rgba(75, 192, 192, 0.2)' },
        { border: 'rgb(255, 99, 132)', background: 'rgba(255, 99, 132, 0.2)' },
        { border: 'rgb(54, 162, 235)', background: 'rgba(54, 162, 235, 0.2)' },
        { border: 'rgb(255, 206, 86)', background: 'rgba(255, 206, 86, 0.2)' },
        { border: 'rgb(153, 102, 255)', background: 'rgba(153, 102, 255, 0.2)' },
        { border: 'rgb(255, 159, 64)', background: 'rgba(255, 159, 64, 0.2)' },
        { border: 'rgb(99, 255, 132)', background: 'rgba(99, 255, 132, 0.2)' },
        { border: 'rgb(235, 54, 162)', background: 'rgba(235, 54, 162, 0.2)' },
        { border: 'rgb(86, 255, 206)', background: 'rgba(86, 255, 206, 0.2)' },
        { border: 'rgb(102, 153, 255)', background: 'rgba(102, 153, 255, 0.2)' },
    ];

    // Initialize when DOM is ready
    $(document).ready(function() {
        initReportPage();
    });

    /**
     * Initialize the report page
     */
    function initReportPage() {
        // Handle date range selector change
        $('#gfg-date-range').on('change', function() {
            if ($(this).val() === 'custom') {
                $('.gfg-custom-dates').show();
                setCustomDateDefaults();
            } else {
                $('.gfg-custom-dates').hide();
            }
        });

        // Handle generate report button click
        $('#gfg-generate-report').on('click', function(e) {
            e.preventDefault();
            generateReport();
        });

        // Allow Enter key to trigger report generation
        $('#gfg-form-select, #gfg-date-range, #gfg-grouping').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                generateReport();
            }
        });
    }

    /**
     * Set default values for custom date range
     */
    function setCustomDateDefaults() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        $('#gfg-end-date').val(formatDate(today));
        $('#gfg-start-date').val(formatDate(thirtyDaysAgo));
    }

    /**
     * Format date as YYYY-MM-DD
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Calculate date range based on selection
     */
    function getDateRange() {
        const rangeValue = $('#gfg-date-range').val();
        const today = new Date();
        let startDate, endDate;

        if (rangeValue === 'custom') {
            startDate = $('#gfg-start-date').val();
            endDate = $('#gfg-end-date').val();

            if (!startDate || !endDate) {
                return null;
            }
        } else {
            const days = parseInt(rangeValue);
            endDate = formatDate(today);

            const pastDate = new Date();
            pastDate.setDate(today.getDate() - days);
            startDate = formatDate(pastDate);
        }

        return { startDate, endDate };
    }

    /**
     * Generate the report
     */
    function generateReport() {
        const formIds = $('#gfg-form-select').val();
        const grouping = $('#gfg-grouping').val();
        const dateRange = getDateRange();

        // Validate inputs
        if (!formIds || formIds.length === 0) {
            showError(gfgReportsData.strings.selectForm);
            return;
        }

        if (!dateRange) {
            showError(gfgReportsData.strings.invalidDateRange);
            return;
        }

        // Show loading state
        showLoading();

        // Make AJAX request
        $.ajax({
            url: gfgReportsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gfg_get_report_data',
                nonce: gfgReportsData.nonce,
                form_id: formIds, // Send as array
                grouping: grouping,
                start_date: dateRange.startDate,
                end_date: dateRange.endDate
            },
            success: function(response) {
                if (response.success) {
                    renderChart(response.data);
                    renderConversionCharts(response.data);
                    updateStats(response.data.datasets);
                    hideLoading();
                } else {
                    showError(response.data.message || gfgReportsData.strings.fetchError);
                    hideLoading();
                }
            },
            error: function(xhr, status, error) {
                showError(gfgReportsData.strings.fetchError + ': ' + error);
                hideLoading();
            }
        });
    }

    /**
     * Render the submissions chart using Chart.js
     */
    function renderChart(data) {
        const ctx = document.getElementById('gfg-reports-chart').getContext('2d');

        // Destroy existing chart if it exists
        if (reportChart) {
            reportChart.destroy();
        }

        // Prepare datasets with colors
        const chartDatasets = data.datasets.map((dataset, index) => {
            const colorIndex = index % COLOR_PALETTE.length;
            const colors = COLOR_PALETTE[colorIndex];

            return {
                label: dataset.label,
                data: dataset.data,
                borderColor: colors.border,
                backgroundColor: colors.background,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: colors.border,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            };
        });

        // Create new chart
        reportChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: chartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: gfgReportsData.strings.chartTitle,
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: gfgReportsData.strings.submissionsLabel,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: gfgReportsData.strings.timePeriodLabel,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    /**
     * Render conversion rate charts
     */
    function renderConversionCharts(data) {
        // Destroy existing conversion charts
        conversionCharts.forEach(chart => chart.destroy());
        conversionCharts = [];

        // Clear container
        $('#gfg-conversion-charts-container').empty();

        // Create a chart for each form
        data.conversion_datasets.forEach((dataset, index) => {
            const colorIndex = index % COLOR_PALETTE.length;
            const colors = COLOR_PALETTE[colorIndex];

            // Add defensive checks for stats
            const stats = dataset.stats || {
                total_views: 0,
                total_submissions: 0,
                conversion_rate: 0
            };

            // Create canvas element
            const canvasId = 'gfg-conversion-chart-' + dataset.form_id;
            const $canvas = $('<canvas>', {
                id: canvasId,
                class: 'gfg-conversion-chart'
            });

            // Add title and canvas
            const $wrapper = $('<div>', { class: 'gfg-conversion-chart-wrapper' });
            $wrapper.append('<h3>' + dataset.label + ' - Conversion Rate</h3>');
            $wrapper.append($canvas);
            $wrapper.append('<p class="conversion-stats">Total Views: ' + stats.total_views + ' | Total Submissions: ' + stats.total_submissions + ' | Overall Rate: ' + stats.conversion_rate + '%</p>');
            $('#gfg-conversion-charts-container').append($wrapper);

            // Create chart
            const ctx = document.getElementById(canvasId).getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Conversion Rate (%)',
                        data: dataset.data,
                        borderColor: colors.border,
                        backgroundColor: colors.background,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: colors.border,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2.5,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return 'Conversion Rate: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Conversion Rate (%)',
                                font: {
                                    size: 11,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });

            conversionCharts.push(chart);
        });

        // Show conversion charts section
        $('.gfg-conversion-charts').show();
    }

    /**
     * Update statistics display
     */
    function updateStats(datasets) {
        // Calculate totals across all selected forms
        let totalSubmissions = 0;
        let totalPeriods = 0;
        let peakCount = 0;
        let peakPeriod = '';

        datasets.forEach(dataset => {
            // Add defensive checks for stats object
            if (dataset.stats && typeof dataset.stats === 'object') {
                totalSubmissions += dataset.stats.total || 0;

                const datasetPeakCount = dataset.stats.peak_count || 0;
                if (datasetPeakCount > peakCount) {
                    peakCount = datasetPeakCount;
                    peakPeriod = (dataset.stats.peak_period || '-') + ' (' + dataset.label + ')';
                }
            }

            if (dataset.data && Array.isArray(dataset.data)) {
                totalPeriods = Math.max(totalPeriods, dataset.data.length);
            }
        });

        const avgSubmissions = totalPeriods > 0 ? (totalSubmissions / totalPeriods).toFixed(1) : 0;

        $('#total-submissions').text(totalSubmissions);
        $('#avg-submissions').text(avgSubmissions);
        $('#peak-period').text(peakPeriod || '-');
        $('.gfg-reports-stats').show();
    }

    /**
     * Show loading state
     */
    function showLoading() {
        $('.gfg-reports-loading').show();
        $('.gfg-reports-error').hide();
        $('.gfg-reports-stats').hide();
        $('.gfg-conversion-charts').hide();
        $('#gfg-reports-chart').hide();
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('.gfg-reports-loading').hide();
        $('#gfg-reports-chart').show();
    }

    /**
     * Show error message
     */
    function showError(message) {
        $('.gfg-reports-error').html('<p>' + message + '</p>').show();
        $('.gfg-reports-stats').hide();
        $('.gfg-conversion-charts').hide();
        $('#gfg-reports-chart').hide();
    }

})(jQuery);
