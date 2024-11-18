<?php
if (!defined('ABSPATH')) exit;

class LSIM_Form_Integration {
    // Form IDs
    const ICE_FORM_ID = 3;
    const WATER_FORM_ID = 1;

    // Field IDs for Ice Form
    const ICE_FIELDS = [
        'name' => 4,
        'department' => 5,
        'address' => 6,
        'phone' => 7,
        'email' => 8,
        'course_date' => 12,
        'location' => 13,
        'assisting_instructors' => 28,
        'hours' => 40,
        'participants_ira' => 15,
        'participants_irt' => 16,
        'participants_iro' => 17
    ];

    // Field IDs for Water Form
    const WATER_FIELDS = [
        'name' => 4,
        'department' => 5,
        'address' => 6,
        'phone' => 7,
        'email' => 8,
        'course_date' => 12,
        'location' => 13,
        'assisting_instructors' => 28,
        'hours' => 39,
        'participants_wra' => 15,
        'participants_wrt' => 16,
        'participants_wro' => 17
    ];

    public function __construct() {
        // Form submission handlers
        add_action('gform_after_submission_' . self::ICE_FORM_ID, [$this, 'process_ice_course_submission'], 10, 2);
        add_action('gform_after_submission_' . self::WATER_FORM_ID, [$this, 'process_water_course_submission'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_course_history_meta_box']);
        add_action('admin_notices', [$this, 'show_unrecognized_instructor_notice']);
    }

    public function process_ice_course_submission($entry, $form) {
        $this->process_course_submission($entry, 'ice', self::ICE_FIELDS);
    }

    public function process_water_course_submission($entry, $form) {
        $this->process_course_submission($entry, 'water', self::WATER_FIELDS);
    }

    private function process_course_submission($entry, $type, $fields) {
        // Get instructor email from form
        $instructor_email = rgar($entry, $fields['email']);
        $instructor = $this->get_instructor_by_email($instructor_email);

        if (!$instructor) {
            // Store unrecognized submission
            $this->store_unrecognized_submission($entry, $type);
            // Send admin notification
            $this->send_unrecognized_instructor_notification($instructor_email, $entry, $type);
            return;
        }

        // Prepare participant data
        $participants = [];
        if ($type === 'ice') {
            $participants = [
                'awareness' => intval(rgar($entry, $fields['participants_ira'])),
                'technician' => intval(rgar($entry, $fields['participants_irt'])),
                'operations' => intval(rgar($entry, $fields['participants_iro']))
            ];
        } else {
            $participants = [
                'awareness' => intval(rgar($entry, $fields['participants_wra'])),
                'technician' => intval(rgar($entry, $fields['participants_wrt'])),
                'operations' => intval(rgar($entry, $fields['participants_wro']))
            ];
        }

        // Add course to history
        $this->add_course_to_instructor($instructor->ID, [
            'type' => $type,
            'form_id' => $entry['form_id'],
            'entry_id' => $entry['id'],
            'date' => rgar($entry, $fields['course_date']),
            'location' => rgar($entry, $fields['location']),
            'hours' => rgar($entry, $fields['hours']),
            'participants' => $participants,
            'assisting_instructors' => $this->process_assisting_instructors($entry, $fields['assisting_instructors'])
        ]);
    }

    private function store_unrecognized_submission($entry, $type) {
        $unrecognized = get_option('lsim_unrecognized_submissions', []);
        $unrecognized[] = [
            'entry_id' => $entry['id'],
            'form_id' => $entry['form_id'],
            'type' => $type,
            'email' => rgar($entry, ($type === 'ice' ? self::ICE_FIELDS['email'] : self::WATER_FIELDS['email'])),
            'date' => current_time('mysql')
        ];
        update_option('lsim_unrecognized_submissions', $unrecognized);
    }

    private function send_unrecognized_instructor_notification($email, $entry, $type) {
        $admin_email = get_option('admin_email');
        $subject = 'Unrecognized Instructor Submission - Action Required';
        $message = sprintf(
            "A course completion form was submitted by an unrecognized instructor email: %s\n\n" .
            "Course Type: %s\n" .
            "Date: %s\n" .
            "Location: %s\n\n" .
            "Please review this submission and either:\n" .
            "1. Create a new instructor record with this email\n" .
            "2. Update the existing instructor's email\n\n" .
            "View the form entry here: %s",
            $email,
            ucfirst($type) . ' Rescue',
            rgar($entry, ($type === 'ice' ? self::ICE_FIELDS['course_date'] : self::WATER_FIELDS['course_date'])),
            rgar($entry, ($type === 'ice' ? self::ICE_FIELDS['location'] : self::WATER_FIELDS['location'])),
            admin_url('admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry['id'])
        );

        wp_mail($admin_email, $subject, $message);
    }

    public function show_unrecognized_instructor_notice() {
        $unrecognized = get_option('lsim_unrecognized_submissions', []);
        if (!empty($unrecognized)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    There are <?php echo count($unrecognized); ?> course completion submissions from unrecognized instructor emails. 
                    <a href="<?php echo admin_url('admin.php?page=instructor-settings&tab=unrecognized'); ?>">Review submissions</a>
                </p>
            </div>
            <?php
        }
    }

    public function render_course_history($post) {
        global $wpdb;
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lsim_course_history 
            WHERE instructor_id = %d ORDER BY course_date DESC",
            $post->ID
        ));
        ?>
        <div class="course-history-wrapper">
            <!-- Course Summary -->
            <div class="course-summary">
                <p>
                    <strong>Ice Rescue Courses (Last 3 Years):</strong> 
                    <?php echo $this->count_recent_courses($post->ID, 'ice'); ?>
                </p>
                <p>
                    <strong>Water Rescue Courses (Last 3 Years):</strong> 
                    <?php echo $this->count_recent_courses($post->ID, 'water'); ?>
                </p>
            </div>

            <!-- Course History Table -->
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Hours</th>
                        <th>Participants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="6">No courses recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($course->course_date)); ?></td>
                                <td><?php echo ucfirst($course->course_type) . ' Rescue'; ?></td>
                                <td><?php echo esc_html($course->location); ?></td>
                                <td><?php echo esc_html($course->hours); ?></td>
                                <td>
                                    <?php 
                                    $participants = json_decode($course->participants_data, true);
                                    if ($participants) {
                                        echo 'Total: ' . array_sum($participants) . '<br>';
                                        foreach ($participants as $level => $count) {
                                            echo ucfirst($level) . ': ' . $count . '<br>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php 
                                        echo esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=' . 
                                        $course->form_entry_id . '&lid=' . $course->entry_id)); 
                                        ?>" 
                                        class="button button-small" 
                                        target="_blank">
                                        View Form
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function count_recent_courses($instructor_id, $type) {
        global $wpdb;
        $certification_period = get_option('lsim_settings')['certification_period'] ?? 3;
        $period_start = date('Y-m-d', strtotime("-{$certification_period} years"));
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lsim_course_history 
            WHERE instructor_id = %d 
            AND course_type = %s 
            AND course_date >= %s",
            $instructor_id,
            $type,
            $period_start
        ));
    }

    private function get_instructor_by_email($email) {
        $instructors = get_posts([
            'post_type' => 'instructor',
            'meta_key' => '_email',
            'meta_value' => $email,
            'posts_per_page' => 1
        ]);
        return !empty($instructors) ? $instructors[0] : null;
    }

    private function add_course_to_instructor($instructor_id, $course_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'lsim_course_history',
            [
                'instructor_id' => $instructor_id,
                'course_type' => $course_data['type'],
                'course_date' => $course_data['date'],
                'location' => $course_data['location'],
                'hours' => $course_data['hours'],
                'participants_data' => json_encode($course_data['participants']),
                'form_entry_id' => $course_data['entry_id'],
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        // Update the certification type taxonomy
        $term = get_term_by('name', ucfirst($course_data['type']) . ' Rescue', 'certification_type');
        if ($term) {
            wp_set_object_terms($instructor_id, $term->term_id, 'certification_type', true);
        }
    }

    private function process_assisting_instructors($entry, $field_id) {
        $assistants = [];
        if (class_exists('GFAPI')) {
            $field = GFAPI::get_field($entry['form_id'], $field_id);
            if ($field && $field->get_entry_inputs()) {
                foreach ($field->get_entry_inputs() as $input) {
                    $value = rgar($entry, (string)$input['id']);
                    if (!empty($value)) {
                        $assistants[] = $value;
                    }
                }
            }
        }
        return $assistants;
    }
}