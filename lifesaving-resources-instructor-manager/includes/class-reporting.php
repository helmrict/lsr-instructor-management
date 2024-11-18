<?php
if (!defined('ABSPATH')) exit;

class LSIM_Reporting {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_reports_page']);
        add_action('wp_ajax_export_instructor_report', [$this, 'export_report']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_reports_page() {
        add_submenu_page(
            'lifesaving-resources',
            'Reports',
            'Reports',
            'manage_options',
            'instructor-reports',
            [$this, 'render_reports_page']
        );
    }

    public function render_reports_page() {
        // Get filter parameters
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-1 year'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
        $cert_type = isset($_GET['cert_type']) ? sanitize_text_field($_GET['cert_type']) : 'all';

        // Get statistics
        $stats = $this->get_statistics($start_date, $end_date, $cert_type);
        ?>
        <div class="wrap">
            <h1>Instructor Reports</h1>

            <!-- Filters -->
            <div class="report-filters">
                <form method="get">
                    <input type="hidden" name="page" value="instructor-reports">
                    <div class="filter-row">
                        <label>Date Range:</label>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                        <span>to</span>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        
                        <label>Certification Type:</label>
                        <select name="cert_type">
                            <option value="all" <?php selected($cert_type, 'all'); ?>>All Types</option>
                            <option value="ice" <?php selected($cert_type, 'ice'); ?>>Ice Rescue</option>
                            <option value="water" <?php selected($cert_type, 'water'); ?>>Water Rescue</option>
                        </select>

                        <button type="submit" class="button">Apply Filters</button>
                        <button type="button" class="button" id="export-report">Export Report</button>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics -->
            <div class="report-grid">
                <div class="report-card">
                    <h3>Course Summary</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Total Courses</span>
                            <span class="stat-value"><?php echo $stats['total_courses']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Students</span>
                            <span class="stat-value"><?php echo $stats['total_students']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Average Class Size</span>
                            <span class="stat-value"><?php echo round($stats['avg_class_size'], 1); ?></span>
                        </div>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Certification Levels</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Awareness</span>
                            <span class="stat-value"><?php echo $stats['certification_levels']['awareness']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Operations</span>
                            <span class="stat-value"><?php echo $stats['certification_levels']['operations']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Technician</span>
                            <span class="stat-value"><?php echo $stats['certification_levels']['technician']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="report-card">
                    <h3>Instructor Activity</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Active Instructors</span>
                            <span class="stat-value"><?php echo $stats['active_instructors']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Avg Courses/Instructor</span>
                            <span class="stat-value"><?php echo round($stats['avg_courses_per_instructor'], 1); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic Distribution -->
            <div class="report-section">
                <h3>Geographic Distribution</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Active Instructors</th>
                            <th>Courses</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['geographic_distribution'] as $state => $data): ?>
                            <tr>
                                <td><?php echo esc_html($state); ?></td>
                                <td><?php echo esc_html($data['instructors']); ?></td>
                                <td><?php echo esc_html($data['courses']); ?></td>
                                <td><?php echo esc_html($data['students']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Training Activity Timeline -->
            <div class="report-section">
                <h3>Training Activity</h3>
                <div id="activity-chart" style="height: 400px;"></div>
            </div>
        </div>
        <?php
    }

    private function get_statistics($start_date, $end_date, $cert_type) {
        global $wpdb;
        
        $where_clause = $wpdb->prepare(
            "WHERE course_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        );

        if ($cert_type !== 'all') {
            $where_clause .= $wpdb->prepare(
                " AND course_type = %s",
                $cert_type
            );
        }

        // Get course data
        $courses = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}lsim_course_history
            $where_clause
            ORDER BY course_date ASC
        ");

        $stats = [
            'total_courses' => count($courses),
            'total_students' => 0,
            'total_hours' => 0,
            'certification_levels' => [
                'awareness' => 0,
                'operations' => 0,
                'technician' => 0
            ],
            'geographic_distribution' => [],
            'monthly_activity' => []
        ];

        // Process each course
        foreach ($courses as $course) {
            // Add to totals
            $participants = json_decode($course->participants_data, true);
            $stats['total_students'] += array_sum($participants);
            $stats['total_hours'] += $course->hours;

            // Add to certification levels
            $stats['certification_levels']['awareness'] += $participants['awareness'] ?? 0;
            $stats['certification_levels']['operations'] += $participants['operations'] ?? 0;
            $stats['certification_levels']['technician'] += $participants['technician'] ?? 0;

            // Add to geographic distribution
            $instructor = get_post($course->instructor_id);
            $state = get_post_meta($course->instructor_id, '_state', true);
            if ($state) {
                if (!isset($stats['geographic_distribution'][$state])) {
                    $stats['geographic_distribution'][$state] = [
                        'instructors' => [],
                        'courses' => 0,
                        'students' => 0
                    ];
                }
                $stats['geographic_distribution'][$state]['instructors'][] = $course->instructor_id;
                $stats['geographic_distribution'][$state]['courses']++;
                $stats['geographic_distribution'][$state]['students'] += array_sum($participants);
            }

            // Add to monthly activity
            $month = date('Y-m', strtotime($course->course_date));
            if (!isset($stats['monthly_activity'][$month])) {
                $stats['monthly_activity'][$month] = [
                    'courses' => 0,
                    'students' => 0
                ];
            }
            $stats['monthly_activity'][$month]['courses']++;
            $stats['monthly_activity'][$month]['students'] += array_sum($participants);
        }

        // Calculate averages
        $stats['avg_class_size'] = $stats['total_courses'] > 0 ? 
            $stats['total_students'] / $stats['total_courses'] : 0;

        // Clean up geographic distribution
        foreach ($stats['geographic_distribution'] as $state => &$data) {
            $data['instructors'] = count(array_unique($data['instructors']));
        }
        unset($data);

        // Get instructor statistics
        $stats['active_instructors'] = $this->count_active_instructors($cert_type);
        $stats['avg_courses_per_instructor'] = $stats['active_instructors'] > 0 ? 
            $stats['total_courses'] / $stats['active_instructors'] : 0;

        return $stats;
    }

    private function count_active_instructors($type = 'all') {
        $args = [
            'post_type' => 'instructor',
            'posts_per_page' => -1,
            'meta_query' => []
        ];

        if ($type === 'ice' || $type === 'water') {
            $args['meta_query'][] = [
                'key' => "_{$type}_active",
                'value' => '1'
            ];
        }

        return count(get_posts($args));
    }

    public function export_report() {
        check_ajax_referer('export_report', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
        $end_date = $_POST['end_date'] ?? date('Y-m-d');
        $cert_type = $_POST['cert_type'] ?? 'all';

        $stats = $this->get_statistics($start_date, $end_date, $cert_type);
        
        // Create CSV content
        $csv_content = "Instructor Activity Report\n";
        $csv_content .= "Period: $start_date to $end_date\n\n";

        // Summary Statistics
        $csv_content .= "Summary Statistics\n";
        $csv_content .= "Total Courses," . $stats['total_courses'] . "\n";
        $csv_content .= "Total Students," . $stats['total_students'] . "\n";
        $csv_content .= "Average Class Size," . round($stats['avg_class_size'], 1) . "\n";
        $csv_content .= "Total Hours," . $stats['total_hours'] . "\n\n";

        // Certification Levels
        $csv_content .= "Certification Levels\n";
        foreach ($stats['certification_levels'] as $level => $count) {
            $csv_content .= ucfirst($level) . "," . $count . "\n";
        }
        $csv_content .= "\n";

        // Geographic Distribution
        $csv_content .= "Geographic Distribution\n";
        $csv_content .= "State,Active Instructors,Courses,Students\n";
        foreach ($stats['geographic_distribution'] as $state => $data) {
            $csv_content .= "$state,{$data['instructors']},{$data['courses']},{$data['students']}\n";
        }

        // Send the CSV file
        $filename = 'instructor-report-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv_content;
        exit;
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'lifesaving-resources_page_instructor-reports') {
            return;
        }

        wp_enqueue_style(
            'instructor-reports',
            LSIM_PLUGIN_URL . 'assets/css/report-styles.css',
            [],
            LSIM_VERSION
        );

        wp_enqueue_script(
            'instructor-reports',
            LSIM_PLUGIN_URL . 'assets/js/reporting-scripts.js',
            ['jquery'],
            LSIM_VERSION,
            true
        );

        // Add Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '3.7.0',
            true
        );
    }
}