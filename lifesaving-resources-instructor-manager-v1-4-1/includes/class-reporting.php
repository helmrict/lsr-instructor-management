<?php
if (!defined('ABSPATH')) exit;

class LSIM_Reporting {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'lifesaving-resources_page_instructor-reports') {
            return;
        }

        wp_enqueue_style(
            'lsim-report-style',
            LSIM_PLUGIN_URL . 'assets/css/report-styles.css',
            [],
            LSIM_VERSION
        );

        wp_enqueue_script(
            'lsim-report-script',
            LSIM_PLUGIN_URL . 'assets/js/reporting-scripts.js',
            ['jquery'],
            LSIM_VERSION,
            true
        );
    }

    private function get_statistics($start_date, $end_date, $cert_type) {
        global $wpdb;

        $where_clause = $wpdb->prepare(
            "WHERE ch.course_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        );

        if ($cert_type !== 'all') {
            $where_clause .= $wpdb->prepare(
                " AND ch.course_type = %s",
                $cert_type
            );
        }

        // Get course data including assistant info
        $courses = $wpdb->get_results("
            SELECT 
                ch.*,
                p.post_title as instructor_name,
                pm.meta_value as instructor_id,
                ah.instructor_id as assistant_id,
                CONCAT(pm_first.meta_value, ' ', pm_last.meta_value) as assistant_name
            FROM {$wpdb->prefix}lsim_course_history ch
            JOIN {$wpdb->posts} p ON ch.instructor_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_instructor_id'
            LEFT JOIN {$wpdb->prefix}lsim_assistant_history ah ON ch.course_date = ah.course_date AND ch.instructor_id = ah.lead_instructor_id
            LEFT JOIN {$wpdb->posts} p_assistant ON ah.instructor_id = p_assistant.ID
            LEFT JOIN {$wpdb->postmeta} pm_first ON p_assistant.ID = pm_first.post_id AND pm_first.meta_key = '_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_last ON p_assistant.ID = pm_last.post_id AND pm_last.meta_key = '_last_name'
            $where_clause
            ORDER BY ch.course_date DESC
        ");

        // Initialize statistics
        $stats = [
            'total_courses' => 0,
            'ice_courses' => 0,
            'water_courses' => 0,
            'students' => [
                'awareness' => 0,
                'operations' => 0,
                'technician' => 0,
                'surf_swiftwater' => 0
            ],
            'instructor_activity' => [],
            'assistant_activity' => [],
            'course_history' => $courses,
            'assistant_summary' => [
                'total_assistants' => 0,
                'courses_with_assistants' => 0,
                'top_assistants' => []
            ]
        ];

        // Process courses
        $unique_assistants = [];
        foreach ($courses as $course) {
            $stats['total_courses']++;
            $stats['ice_courses'] += ($course->course_type === 'ice') ? 1 : 0;
            $stats['water_courses'] += ($course->course_type === 'water') ? 1 : 0;

            if ($course->assistant_id) {
                $stats['courses_with_assistants']++;
                $unique_assistants[$course->assistant_id] = true;
            }

            $participants = json_decode($course->participants_data, true);
            $stats['students']['awareness'] += $participants['awareness'] ?? 0;
            $stats['students']['operations'] += $participants['operations'] ?? 0;
            $stats['students']['technician'] += $participants['technician'] ?? 0;
            $stats['students']['surf_swiftwater'] += $participants['surf_swiftwater'] ?? 0;

            $this->update_instructor_stats($stats['instructor_activity'], $course, $participants);
        }

        // Get assistant activity
        $assistant_activity = $wpdb->get_results("
            SELECT 
                ah.*,
                p1.post_title as assistant_name,
                p2.post_title as lead_instructor,
                pm1.meta_value as assistant_id,
                pm2.meta_value as lead_instructor_id
            FROM {$wpdb->prefix}lsim_assistant_history ah
            JOIN {$wpdb->posts} p1 ON ah.instructor_id = p1.ID
            JOIN {$wpdb->posts} p2 ON ah.lead_instructor_id = p2.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON p1.ID = pm1.post_id AND pm1.meta_key = '_instructor_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '_instructor_id'
            WHERE ah.course_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY ah.course_date DESC
        ");

        $stats['assistant_activity'] = $assistant_activity;
        $stats['assistant_summary']['total_assistants'] = count($unique_assistants);

        // Get top assistants
        $top_assistants = $wpdb->get_results("
            SELECT 
                ah.instructor_id,
                p.post_title as assistant_name,
                pm.meta_value as assistant_id,
                COUNT(*) as assist_count
            FROM {$wpdb->prefix}lsim_assistant_history ah
            JOIN {$wpdb->posts} p ON ah.instructor_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_instructor_id'
            WHERE ah.course_date BETWEEN '$start_date' AND '$end_date'
            GROUP BY ah.instructor_id
            ORDER BY assist_count DESC
            LIMIT 5
        ");

        $stats['assistant_summary']['top_assistants'] = $top_assistants;

        return $stats;
    }

    private function update_instructor_stats(&$instructor_activity, $course, $participants) {
        $instructor_id = $course->instructor_id;
        if (!isset($instructor_activity[$instructor_id])) {
            $instructor_activity[$instructor_id] = (object)[
                'instructor_id' => $course->instructor_id,
                'instructor_name' => $course->instructor_name,
                'cert_type' => $course->course_type,
                'courses_taught' => 0,
                'courses_assisted' => 0,
                'students_trained' => 0,
                'last_course' => $course->course_date
            ];
        }
        $instructor_activity[$instructor_id]->courses_taught++;
        $instructor_activity[$instructor_id]->students_trained += array_sum($participants);
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
                        <button type="button" class="button" id="export-report" 
                                data-nonce="<?php echo wp_create_nonce('export_report'); ?>">
                            Export Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Cards -->
            <div class="report-grid">
                <!-- Course Summary Card -->
                <div class="report-card">
                    <h3>Course Summary</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Total Courses</span>
                            <span class="stat-value"><?php echo $stats['total_courses']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Ice Rescue</span>
                            <span class="stat-value"><?php echo $stats['ice_courses']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Water Rescue</span>
                            <span class="stat-value"><?php echo $stats['water_courses']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Student Summary Card -->
                <div class="report-card">
                    <h3>Students Trained</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Awareness</span>
                            <span class="stat-value"><?php echo $stats['students']['awareness']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Operations</span>
                            <span class="stat-value"><?php echo $stats['students']['operations']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Technician</span>
                            <span class="stat-value"><?php echo $stats['students']['technician']; ?></span>
                        </div>
                        <?php if ($cert_type !== 'ice'): ?>
                        <div class="stat-item">
                            <span class="stat-label">Surf/Swiftwater</span>
                            <span class="stat-value"><?php echo $stats['students']['surf_swiftwater']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assistant Summary Card -->
                <div class="report-card">
                    <h3>Assistant Summary</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Total Assistants</span>
                            <span class="stat-value"><?php echo $stats['assistant_summary']['total_assistants']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Courses with Assistants</span>
                            <span class="stat-value"><?php echo $stats['assistant_summary']['courses_with_assistants']; ?></span>
                        </div>
                    </div>

                    <?php if (!empty($stats['assistant_summary']['top_assistants'])): ?>
                        <div class="top-assistants">
                            <h4>Top Assistants</h4>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Assistant</th>
                                        <th>ID</th>
                                        <th>Courses Assisted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['assistant_summary']['top_assistants'] as $assistant): ?>
                                        <tr>
                                            <td><?php echo esc_html($assistant->assistant_name); ?></td>
                                            <td><?php echo esc_html($assistant->assistant_id); ?></td>
                                            <td><?php echo esc_html($assistant->assist_count); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabbed Reports -->
            <div class="tabbed-reports">
                <ul class="tab-nav">
                    <li class="active" data-tab="instructor-activity">Instructor Activity</li>
                    <li data-tab="assistant-activity">Assistant Activity</li>
                    <li data-tab="course-history">Course History</li>
                </ul>

                <!-- Instructor Activity Tab -->
                <div class="tab-content active" id="instructor-activity">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Instructor ID</th>
                                <th>Name</th>
                                <th>Certification</th>
                                <th>Courses Taught</th>
                                <th>Students Trained</th>
                                <th>Last Course</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['instructor_activity'] as $activity): ?>
                                <tr>
                                    <td><?php echo esc_html($activity->instructor_id); ?></td>
                                    <td><?php echo esc_html($activity->instructor_name); ?></td>
                                    <td><?php echo esc_html(ucfirst($activity->cert_type)); ?></td>
                                    <td><?php echo esc_html($activity->courses_taught); ?></td>
                                    <td><?php echo esc_html($activity->students_trained); ?></td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($activity->last_course))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Assistant Activity Tab -->
                <div class="tab-content" id="assistant-activity">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Assistant</th>
                                <th>Lead Instructor</th>
                                <th>Course Type</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['assistant_activity'] as $activity): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($activity->course_date))); ?></td>
                                    <td><?php echo esc_html($activity->assistant_name); ?> (ID: <?php echo esc_html($activity->assistant_id); ?>)</td>
                                    <td><?php echo esc_html($activity->lead_instructor); ?> (ID: <?php echo esc_html($activity->lead_instructor_id); ?>)</td>
                                    <td><?php echo esc_html(ucfirst($activity->course_type)); ?></td>
                                    <td><?php echo esc_html($activity->location); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Course History Tab -->
                <div class="tab-content" id="course-history">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Instructor</th>
                                <th>Type</th>
                                <th>Students</th>
                                <th>Assistant</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['course_history'] as $course): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($course->course_date))); ?></td>
                                    <td><?php echo esc_html($course->instructor_name); ?></td>
                                    <td><?php echo esc_html(ucfirst($course->course_type)); ?></td>
                                    <td>
                                        <?php
                                        $students = json_decode($course->participants_data, true);
                                        echo "A: {$students['awareness']}, ";
                                        echo "O: {$students['operations']}, ";
                                        echo "T: {$students['technician']}";
                                        if (isset($students['surf_swiftwater'])) {
                                            echo ", S: {$students['surf_swiftwater']}";
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($course->assistant_name ?: 'None'); ?></td>
                                    <td><?php echo esc_html($course->location); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}