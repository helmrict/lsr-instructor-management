<?php
if (!defined('ABSPATH')) exit;

class LSIM_Instructor_History {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_history_meta_boxes']);
    }

    public function add_history_meta_boxes() {
        add_meta_box(
            'instructor_course_history',
            'Course History',
            [$this, 'render_course_history'],
            'instructor',
            'normal',
            'high'
        );

        add_meta_box(
            'instructor_assistant_history',
            'Assistant History',
            [$this, 'render_assistant_history'],
            'instructor',
            'normal',
            'high'
        );
    }

    public function render_course_history($post) {
        global $wpdb;
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lsim_course_history 
            WHERE instructor_id = %d 
            ORDER BY course_date DESC",
            $post->ID
        ));
        ?>
        <div class="course-history-wrapper">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Assistant</th>
                        <th>Students</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="5">No courses recorded</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, Y', strtotime($course->course_date))); ?></td>
                                <td><?php echo esc_html(ucfirst($course->course_type)); ?></td>
                                <td>
                                    <?php 
                                    $assistant = $wpdb->get_row($wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}lsim_assistant_history 
                                        WHERE lead_instructor_id = %d AND course_date = %s",
                                        $post->ID,
                                        $course->course_date
                                    ));
                                    if ($assistant) {
                                        $assistant_name = get_post_meta($assistant->instructor_id, '_first_name', true) . ' ' . 
                                                        get_post_meta($assistant->instructor_id, '_last_name', true);
                                        echo esc_html($assistant_name);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $participants = json_decode($course->participants_data, true);
                                    if ($participants) {
                                        echo 'Awareness: ' . esc_html($participants['awareness']) . '<br>';
                                        echo 'Operations: ' . esc_html($participants['operations']) . '<br>';
                                        echo 'Technician: ' . esc_html($participants['technician']);
                                        if (isset($participants['surf_swiftwater'])) {
                                            echo '<br>Surf/Swiftwater: ' . esc_html($participants['surf_swiftwater']);
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($course->location); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_assistant_history($post) {
        global $wpdb;
        $assistant_history = $wpdb->get_results($wpdb->prepare(
            "SELECT ah.*, 
                    p.post_title as lead_instructor_name,
                    pm1.meta_value as lead_instructor_phone,
                    pm2.meta_value as lead_instructor_email
             FROM {$wpdb->prefix}lsim_assistant_history ah
             JOIN {$wpdb->posts} p ON ah.lead_instructor_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_phone'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_email'
             WHERE ah.instructor_id = %d
             ORDER BY ah.course_date DESC",
            $post->ID
        ));
        ?>
        <div class="assistant-history-wrapper">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Lead Instructor</th>
                        <th>Contact</th>
                        <th>Course Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assistant_history)): ?>
                        <tr>
                            <td colspan="4">No assistant history recorded</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assistant_history as $history): ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, Y', strtotime($history->course_date))); ?></td>
                                <td><?php echo esc_html($history->lead_instructor_name); ?></td>
                                <td>
                                    <?php if ($history->lead_instructor_phone): ?>
                                        Phone: <?php echo esc_html($history->lead_instructor_phone); ?><br>
                                    <?php endif; ?>
                                    <?php if ($history->lead_instructor_email): ?>
                                        Email: <?php echo esc_html($history->lead_instructor_email); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($history->course_type)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}