<?php
if (!defined('ABSPATH')) exit;

class LSIM_Report_Templates {
    public static function render_statistics_report($stats) {
        ?>
        <div class="statistics-report">
            <!-- Summary Cards -->
            <div class="report-grid">
                <div class="report-card">
                    <h3>Course Overview</h3>
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
                            <span class="stat-label">Training Hours</span>
                            <span class="stat-value"><?php echo $stats['total_hours']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Certification Distribution -->
                <div class="report-card">
                    <h3>Certification Distribution</h3>
                    <div class="stat-grid">
                        <?php foreach(['ice', 'water'] as $type): ?>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo ucfirst($type); ?> Rescue</span>
                                <span class="stat-value"><?php echo $stats['certifications'][$type]; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="certification-chart"></div>
                </div>

                <!-- Instructor Status -->
                <div class="report-card">
                    <h3>Instructor Status</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <span class="stat-label">Active</span>
                            <span class="stat-value"><?php echo $stats['active_instructors']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Expiring Soon</span>
                            <span class="stat-value status-warning"><?php echo $stats['expiring_soon']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Recently Expired</span>
                            <span class="stat-value status-expired"><?php echo $stats['recently_expired']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic Distribution -->
            <div class="report-section">
                <h3>Geographic Distribution</h3>
                <div class="geo-chart-container">
                    <div id="geo-distribution-chart"></div>
                </div>
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

            <!-- Training Activity -->
            <div class="report-section">
                <h3>Training Activity Timeline</h3>
                <div id="activity-timeline-chart"></div>
            </div>
        </div>
        <?php
    }
	
	public static function render_instructor_report($instructors) {
        ?>
        <div class="instructor-report">
            <div class="report-filters">
                <select id="certification-filter">
                    <option value="">All Certifications</option>
                    <option value="ice">Ice Rescue</option>
                    <option value="water">Water Rescue</option>
                </select>

                <select id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="expiring">Expiring Soon</option>
                    <option value="expired">Expired</option>
                </select>

                <select id="state-filter">
                    <option value="">All States</option>
                    <?php
                    $states = self::get_instructor_states();
                    foreach ($states as $code => $name) {
                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>

                <button type="button" class="button" id="export-instructor-report">Export</button>
            </div>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>State</th>
                        <th>Ice Rescue Status</th>
                        <th>Water Rescue Status</th>
                        <th>Last Course</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructors as $instructor): 
                        $meta = get_post_meta($instructor->ID);
                        ?>
                        <tr class="instructor-row"
                            data-ice="<?php echo esc_attr($meta['_ice_status'][0] ?? ''); ?>"
                            data-water="<?php echo esc_attr($meta['_water_status'][0] ?? ''); ?>"
                            data-state="<?php echo esc_attr($meta['_state'][0] ?? ''); ?>">
                            
                            <td>
                                <a href="<?php echo get_edit_post_link($instructor->ID); ?>">
                                    <?php echo esc_html($instructor->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($meta['_department'][0] ?? ''); ?></td>
                            <td><?php echo esc_html($meta['_state'][0] ?? ''); ?></td>
                            <td><?php self::render_certification_status($instructor->ID, 'ice'); ?></td>
                            <td><?php self::render_certification_status($instructor->ID, 'water'); ?></td>
                            <td>
                                <?php 
                                $last_course = $meta['_last_course_date'][0] ?? '';
                                echo $last_course ? date('M j, Y', strtotime($last_course)) : '—';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_course_report($courses) {
        ?>
        <div class="course-report">
            <div class="report-filters">
                <input type="date" id="start-date" placeholder="Start Date">
                <input type="date" id="end-date" placeholder="End Date">
                
                <select id="course-type-filter">
                    <option value="">All Types</option>
                    <option value="ice">Ice Rescue</option>
                    <option value="water">Water Rescue</option>
                </select>

                <select id="certification-level-filter">
                    <option value="">All Levels</option>
                    <option value="awareness">Awareness</option>
                    <option value="operations">Operations</option>
                    <option value="technician">Technician</option>
                </select>

                <button type="button" class="button" id="export-course-report">Export</button>
            </div>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Instructor</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Students</th>
                        <th>Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): 
                        $instructor = get_post($course->instructor_id);
                        $participants = json_decode($course->participants_data, true);
                        ?>
                        <tr class="course-row"
                            data-type="<?php echo esc_attr($course->course_type); ?>"
                            data-date="<?php echo esc_attr($course->course_date); ?>">
                            
                            <td><?php echo date('M j, Y', strtotime($course->course_date)); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($course->instructor_id); ?>">
                                    <?php echo esc_html($instructor->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo ucfirst($course->course_type) . ' Rescue'; ?></td>
                            <td><?php echo esc_html($course->location); ?></td>
                            <td>
                                <?php
                                if ($participants) {
                                    echo 'Total: ' . array_sum($participants) . '<br>';
                                    foreach ($participants as $level => $count) {
                                        echo ucfirst($level) . ': ' . $count . '<br>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($course->hours); ?></td>
                            <td>
                                <a href="<?php 
                                    echo admin_url('admin.php?page=gf_entries&view=entry&id=' . 
                                    $course->form_entry_id); 
                                    ?>" 
                                    class="button" 
                                    target="_blank">
                                    View Form
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Initialize datepickers
                $('#start-date, #end-date').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: 0
                });

                // Filter functionality
                function filterRows() {
                    const startDate = $('#start-date').val();
                    const endDate = $('#end-date').val();
                    const type = $('#course-type-filter').val();
                    const level = $('#certification-level-filter').val();

                    $('.course-row').each(function() {
                        const $row = $(this);
                        const rowDate = new Date($row.data('date'));
                        const rowType = $row.data('type');

                        const dateMatch = (!startDate || rowDate >= new Date(startDate)) && 
                                        (!endDate || rowDate <= new Date(endDate));
                        const typeMatch = !type || rowType === type;

                        $row.toggle(dateMatch && typeMatch);
                    });
                }

                // Bind filter events
                $('.report-filters select, .report-filters input').on('change', filterRows);

                // Export functionality
                $('#export-course-report').on('click', function() {
                    const data = [];
                    const headers = ['Date', 'Instructor', 'Type', 'Location', 'Students', 'Hours'];
                    
                    data.push(headers);

                    $('.course-row:visible').each(function() {
                        const $row = $(this);
                        data.push([
                            $row.find('td:eq(0)').text(),
                            $row.find('td:eq(1)').text(),
                            $row.find('td:eq(2)').text(),
                            $row.find('td:eq(3)').text(),
                            $row.find('td:eq(4)').text().replace(/\n/g, ' '),
                            $row.find('td:eq(5)').text()
                        ]);
                    });

                    // Create and download CSV
                    let csvContent = "data:text/csv;charset=utf-8,";
                    data.forEach(row => {
                        csvContent += row.join(',') + '\r\n';
                    });

                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', 'course-report.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            });
        </script>
        <?php
    }

    private static function render_certification_status($instructor_id, $type) {
        $status = get_post_meta($instructor_id, "_{$type}_status", true);
        $expiration = get_post_meta($instructor_id, "_{$type}_expiration", true);
        
        $class = '';
        switch ($status) {
            case 'active':
                $class = 'status-active';
                break;
            case 'expiring':
                $class = 'status-warning';
                break;
            case 'expired':
                $class = 'status-expired';
                break;
        }

        echo '<span class="status-indicator ' . $class . '">';
        echo ucfirst($status);
        if ($expiration) {
            echo '<br><small>Exp: ' . date('M j, Y', strtotime($expiration)) . '</small>';
        }
        echo '</span>';
    }

    private static function get_instructor_states() {
        return [
            'AL'=>'Alabama', 'AK'=>'Alaska', 'AZ'=>'Arizona', 'AR'=>'Arkansas', 
            'CA'=>'California', 'CO'=>'Colorado', 'CT'=>'Connecticut', 'DE'=>'Delaware', 
            'DC'=>'District of Columbia', 'FL'=>'Florida', 'GA'=>'Georgia', 'HI'=>'Hawaii', 
            'ID'=>'Idaho', 'IL'=>'Illinois', 'IN'=>'Indiana', 'IA'=>'Iowa', 
            'KS'=>'Kansas', 'KY'=>'Kentucky', 'LA'=>'Louisiana', 'ME'=>'Maine', 
            'MD'=>'Maryland', 'MA'=>'Massachusetts', 'MI'=>'Michigan', 'MN'=>'Minnesota', 
            'MS'=>'Mississippi', 'MO'=>'Missouri', 'MT'=>'Montana', 'NE'=>'Nebraska', 
            'NV'=>'Nevada', 'NH'=>'New Hampshire', 'NJ'=>'New Jersey', 'NM'=>'New Mexico', 
            'NY'=>'New York', 'NC'=>'North Carolina', 'ND'=>'North Dakota', 'OH'=>'Ohio', 
            'OK'=>'Oklahoma', 'OR'=>'Oregon', 'PA'=>'Pennsylvania', 'RI'=>'Rhode Island', 
            'SC'=>'South Carolina', 'SD'=>'South Dakota', 'TN'=>'Tennessee', 'TX'=>'Texas', 
            'UT'=>'Utah', 'VT'=>'Vermont', 'VA'=>'Virginia', 'WA'=>'Washington', 
            'WV'=>'West Virginia', 'WI'=>'Wisconsin', 'WY'=>'Wyoming'
        ];
    }
}