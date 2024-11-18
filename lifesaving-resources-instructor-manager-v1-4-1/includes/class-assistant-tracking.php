<?php
if (!defined('ABSPATH')) exit;

class LSIM_Assistant_Tracking {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_instructor', [$this, 'save_assistant_data']);
        add_filter('manage_instructor_posts_columns', [$this, 'add_assistant_column']);
        add_action('manage_instructor_posts_custom_column', [$this, 'display_assistant_column'], 10, 2);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'assistant_history',
            'Assistant Teaching History',
            [$this, 'render_assistant_history'],
            'instructor',
            'normal',
            'high'
        );
    }

    public function render_assistant_history($post) {
        global $wpdb;
        wp_nonce_field('save_assistant_history', 'assistant_history_nonce');

        // Get assistant history
        $assistant_history = $wpdb->get_results($wpdb->prepare(
            "SELECT ah.*, 
                    p.post_title as lead_instructor_name,
                    pm1.meta_value as lead_instructor_id,
                    pm2.meta_value as lead_instructor_email,
                    pm3.meta_value as lead_instructor_phone
             FROM {$wpdb->prefix}lsim_assistant_history ah
             JOIN {$wpdb->posts} p ON ah.lead_instructor_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_instructor_id'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_email'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_phone'
             WHERE ah.instructor_id = %d
             ORDER BY ah.course_date DESC",
            $post->ID
        ));
        ?>
        <div class="assistant-history-wrapper">
            <?php if (empty($assistant_history)): ?>
                <p class="no-history">No assistant teaching history recorded yet.</p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Lead Instructor</th>
                            <th>Contact Info</th>
                            <th>Course Type</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assistant_history as $history): ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, Y', strtotime($history->course_date))); ?></td>
                                <td>
                                    <?php 
                                    echo esc_html($history->lead_instructor_name);
                                    if ($history->lead_instructor_id) {
                                        echo ' (ID: ' . esc_html($history->lead_instructor_id) . ')';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($history->lead_instructor_email): ?>
                                        Email: <?php echo esc_html($history->lead_instructor_email); ?><br>
                                    <?php endif; ?>
                                    <?php if ($history->lead_instructor_phone): ?>
                                        Phone: <?php echo esc_html($history->lead_instructor_phone); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($history->course_type)); ?></td>
                                <td><?php echo esc_html($history->location); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="assistant-stats">
                    <h4>Summary Statistics</h4>
                    <?php
                    $total_courses = count($assistant_history);
                    $ice_courses = count(array_filter($assistant_history, function($h) {
                        return $h->course_type === 'ice';
                    }));
                    $water_courses = count(array_filter($assistant_history, function($h) {
                        return $h->course_type === 'water';
                    }));
                    ?>
                    <p>
                        Total Courses Assisted: <?php echo esc_html($total_courses); ?><br>
                        Ice Rescue Courses: <?php echo esc_html($ice_courses); ?><br>
                        Water Rescue Courses: <?php echo esc_html($water_courses); ?>
                    </p>
                </div>
            <?php endif; ?>

            <style>
                .assistant-history-wrapper {
                    margin: 15px 0;
                }
                .assistant-stats {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .assistant-stats h4 {
                    margin: 0 0 10px 0;
                }
                .no-history {
                    padding: 20px;
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    text-align: center;
                    color: #666;
                }
            </style>
        </div>
        <?php
    }

    public function add_assistant_column($columns) {
        $columns['assistant_count'] = 'Courses Assisted';
        return $columns;
    }

    public function display_assistant_column($column, $post_id) {
        if ($column === 'assistant_count') {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}lsim_assistant_history 
                WHERE instructor_id = %d",
                $post_id
            ));
            echo esc_html($count ?: '0');
        }
    }

    public function save_assistant_data($post_id) {
        if (!isset($_POST['assistant_history_nonce']) || 
            !wp_verify_nonce($_POST['assistant_history_nonce'], 'save_assistant_history')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Any additional save operations would go here
    }

    public static function record_assistant($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'lsim_assistant_history',
            [
                'instructor_id' => $data['instructor_id'],
                'lead_instructor_id' => $data['lead_instructor_id'],
                'course_date' => $data['course_date'],
                'course_type' => $data['course_type'],
                'location' => $data['location'],
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
    }
}