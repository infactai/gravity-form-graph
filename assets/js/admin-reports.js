/**
 * Gravity Forms Graph - Admin JavaScript
 * Handles chart rendering and data fetching
 *
 * @package GravityFormsGraph
 */

(function($) {
    'use strict';

    let reportChart = null;

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
        const formId = $('#gfg-form-select').val();
        const grouping = $('#gfg-grouping').val();
        const dateRange = getDateRange();

        // Validate inputs
        if (!formId) {
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
                form_id: formId,
                grouping: grouping,
                start_date: dateRange.startDate,
                end_date: dateRange.endDate
            },
            success: function(response) {
                if (response.success) {
                    renderChart(response.data);
                    updateStats(response.data.stats);
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
     * Render the chart using Chart.js
     */
    function renderChart(data) {
        const ctx = document.getElementById('gfg-reports-chart').getContext('2d');

        // Destroy existing chart if it exists
        if (reportChart) {
            reportChart.destroy();
        }

        // Create new chart
        reportChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: gfgReportsData.strings.submissions,
                    data: data.data,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgb(75, 192, 192)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
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
                                return gfgReportsData.strings.submissions + ': ' + context.parsed.y;
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
     * Update statistics display
     */
    function updateStats(stats) {
        $('#total-submissions').text(stats.total);
        $('#avg-submissions').text(stats.average);
        $('#peak-period').text(stats.peak_period || '-');
        $('.gfg-reports-stats').show();
    }

    /**
     * Show loading state
     */
    function showLoading() {
        $('.gfg-reports-loading').show();
        $('.gfg-reports-error').hide();
        $('.gfg-reports-stats').hide();
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
        $('#gfg-reports-chart').hide();
    }

})(jQuery);
