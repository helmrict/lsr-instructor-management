jQuery(document).ready(function($) {
    // Initialize Charts
    function initializeCharts(stats) {
        // Activity Timeline Chart
        const timelineCtx = document.getElementById('activity-chart');
        if (timelineCtx) {
            new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(stats.monthly_activity).map(date => {
                        return new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short'
                        });
                    }),
                    datasets: [
                        {
                            label: 'Courses',
                            data: Object.values(stats.monthly_activity).map(data => data.courses),
                            borderColor: '#2271b1',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Students',
                            data: Object.values(stats.monthly_activity).map(data => data.students),
                            borderColor: '#46b450',
                            tension: 0.3,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Geographic Distribution Chart
        const geoCtx = document.getElementById('geographic-chart');
        if (geoCtx) {
            new Chart(geoCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(stats.geographic_distribution),
                    datasets: [
                        {
                            label: 'Active Instructors',
                            data: Object.values(stats.geographic_distribution).map(data => data.instructors),
                            backgroundColor: '#2271b1'
                        },
                        {
                            label: 'Courses',
                            data: Object.values(stats.geographic_distribution).map(data => data.courses),
                            backgroundColor: '#46b450'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Certification Levels Pie Chart
        const certCtx = document.getElementById('certification-chart');
        if (certCtx) {
            new Chart(certCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Awareness', 'Operations', 'Technician'],
                    datasets: [{
                        data: [
                            stats.certification_levels.awareness,
                            stats.certification_levels.operations,
                            stats.certification_levels.technician
                        ],
                        backgroundColor: ['#2271b1', '#46b450', '#ffb900']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // Export Report
    $('#export-report').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const startDate = $('input[name="start_date"]').val();
        const endDate = $('input[name="end_date"]').val();
        const certType = $('select[name="cert_type"]').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'export_instructor_report',
                nonce: lsimReporting.nonce,
                start_date: startDate,
                end_date: endDate,
                cert_type: certType
            },
            beforeSend: function() {
                button.prop('disabled', true).text('Exporting...');
            },
            success: function(response) {
                // Create and trigger download
                const blob = new Blob([response], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'instructor-report.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            },
            error: function() {
                alert('Error exporting report. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text('Export Report');
            }
        });
    });

    // Date Range Quick Selects
    $('.date-range-select').on('change', function() {
        const range = $(this).val();
        const endDate = new Date();
        let startDate = new Date();

        switch(range) {
            case 'month':
                startDate.setMonth(startDate.getMonth() - 1);
                break;
            case 'quarter':
                startDate.setMonth(startDate.getMonth() - 3);
                break;
            case 'year':
                startDate.setFullYear(startDate.getFullYear() - 1);
                break;
            case 'custom':
                return; // Don't update dates for custom selection
        }

        $('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
        $('input[name="end_date"]').val(endDate.toISOString().split('T')[0]);
    });

    // Print Report
    $('#print-report').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // Filter Form Validation
    $('.report-filters form').on('submit', function(e) {
        const startDate = new Date($('input[name="start_date"]').val());
        const endDate = new Date($('input[name="end_date"]').val());

        if (endDate < startDate) {
            e.preventDefault();
            alert('End date must be after start date');
            return false;
        }
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Initialize charts if stats are available
    if (typeof lsimReportingStats !== 'undefined') {
        initializeCharts(lsimReportingStats);
    }

    // Responsive chart resizing
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.chart-container canvas').each(function() {
                if (this.chart) {
                    this.chart.resize();
                }
            });
        }, 250);
    });

    // Export specific date range
    $('.export-date-range').on('click', function(e) {
        e.preventDefault();
        const range = $(this).data('range');
        const endDate = new Date();
        let startDate = new Date();

        switch(range) {
            case 'month':
                startDate.setMonth(startDate.getMonth() - 1);
                break;
            case 'quarter':
                startDate.setMonth(startDate.getMonth() - 3);
                break;
            case 'year':
                startDate.setFullYear(startDate.getFullYear() - 1);
                break;
        }

        $('#export-report').trigger('click', [{
            start_date: startDate.toISOString().split('T')[0],
            end_date: endDate.toISOString().split('T')[0]
        }]);
    });
});