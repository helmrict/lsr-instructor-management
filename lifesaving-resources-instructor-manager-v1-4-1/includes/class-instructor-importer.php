<?php
if (!defined('ABSPATH')) exit;

class LSIM_Instructor_Importer {
    public function __construct() {
        add_action('admin_post_import_instructors', [$this, 'handle_import']);
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Instructors</h1>
            
            <div class="card">
                <h2>Instructions</h2>
                <p>Upload a CSV file containing instructor information. The CSV should have these columns:</p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li>First Name, Last Name, Department/Agency, Phone</li>
                    <li>Certification Type (Ice or Water)</li>
                    <li>Original Auth Date (YYYY-MM-DD), Training Location</li>
                    <li>ReAuth Dates 1-4 (YYYY-MM-DD)</li>
                    <li>Course Details (up to 4 sets):
                        <ul>
                            <li>Course Date</li>
                            <li>Assistant Instructor Name</li>
                            <li>Assistant Instructor Email</li>
                            <li>Student Counts: Awareness, Operations, Technician</li>
                            <li>Surf/Swiftwater Count (Water certification only)</li>
                        </ul>
                    </li>
                </ul>
                
                <p>
                    <a href="<?php echo plugin_dir_url(LSIM_PLUGIN_FILE) . 'includes/admin/templates/instructor-import-template.csv'; ?>" 
                       class="button">
                        Download Sample CSV
                    </a>
                </p>
            </div>

            <div class="card">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('import_instructors', 'instructor_import_nonce'); ?>
                    <input type="hidden" name="action" value="import_instructors">
                    
                    <p>
                        <label>
                            <input type="file" name="instructor_csv" accept=".csv" required>
                        </label>
                    </p>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="skip_first_row" value="1" checked>
                            Skip first row (headers)
                        </label>
                    </p>
                    
                    <p>
                        <button type="submit" class="button button-primary">Import Instructors</button>
                    </p>
                </form>
            </div>

            <?php 
            $import_log = get_option('lsim_last_import_log');
            if ($import_log): 
            ?>
                <div class="card">
                    <h2>Last Import Results</h2>
                    <p>
                        Imported: <?php echo esc_html($import_log['imported']); ?> instructors<br>
                        Skipped: <?php echo esc_html($import_log['skipped']); ?> instructors<br>
                        Errors: <?php echo esc_html($import_log['errors']); ?> instructors
                    </p>
                    <?php if (!empty($import_log['error_messages'])): ?>
                        <h3>Error Details:</h3>
                        <ul>
                            <?php foreach ($import_log['error_messages'] as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_import() {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        check_admin_referer('import_instructors', 'instructor_import_nonce');

        if (!isset($_FILES['instructor_csv'])) {
            wp_redirect(add_query_arg('import_error', 'no_file', wp_get_referer()));
            exit;
        }

        $file = $_FILES['instructor_csv'];
        if ($file['error'] || $file['size'] < 1) {
            wp_redirect(add_query_arg('import_error', 'invalid_file', wp_get_referer()));
            exit;
        }

        $skip_first = isset($_POST['skip_first_row']);
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_messages' => []
        ];

        $handle = fopen($file['tmp_name'], 'r');
        if ($skip_first) {
            fgetcsv($handle);
        }

        while (($data = fgetcsv($handle)) !== false) {
            try {
                // Basic validation
                if (count($data) < 39) { // Total number of fields needed
                    throw new Exception('Row does not contain all required fields');
                }

                // Extract basic instructor data
                $instructor_data = [
                    'first_name' => sanitize_text_field($data[0]),
                    'last_name' => sanitize_text_field($data[1]),
                    'department' => sanitize_text_field($data[2]),
                    'phone' => sanitize_text_field($data[3]),
                    'cert_type' => strtolower(sanitize_text_field($data[4])),
                    'original_auth_date' => sanitize_text_field($data[5]),
                    'training_location' => sanitize_text_field($data[6])
                ];

                // Validate certification type
                if (!in_array($instructor_data['cert_type'], ['ice', 'water'])) {
                    throw new Exception('Invalid certification type. Must be "ice" or "water"');
                }

                // Create or update instructor
                $existing = get_posts([
                    'post_type' => 'instructor',
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => '_first_name',
                            'value' => $instructor_data['first_name']
                        ],
                        [
                            'key' => '_last_name',
                            'value' => $instructor_data['last_name']
                        ]
                    ],
                    'posts_per_page' => 1
                ]);

                if (!empty($existing)) {
                    $post_id = $existing[0]->ID;
                    $results['skipped']++;
                } else {
                    $post_id = wp_insert_post([
                        'post_title' => $instructor_data['last_name'] . ', ' . $instructor_data['first_name'],
                        'post_type' => 'instructor',
                        'post_status' => 'publish'
                    ]);
                }

                if (is_wp_error($post_id)) {
                    throw new Exception('Failed to create/update instructor: ' . $post_id->get_error_message());
                }

                // Update basic metadata
                update_post_meta($post_id, '_first_name', $instructor_data['first_name']);
                update_post_meta($post_id, '_last_name', $instructor_data['last_name']);
                update_post_meta($post_id, '_department', $instructor_data['department']);
                update_post_meta($post_id, '_phone', $instructor_data['phone']);
                update_post_meta($post_id, "_{$instructor_data['cert_type']}_original_date", $instructor_data['original_auth_date']);
                update_post_meta($post_id, '_training_location', $instructor_data['training_location']);

                // Process reauthorization dates (indices 7-10)
                $reauth_dates = [];
                for ($i = 0; $i < 4; $i++) {
                    if (!empty($data[7 + $i])) {
                        $reauth_dates[] = sanitize_text_field($data[7 + $i]);
                    }
                }
                if (!empty($reauth_dates)) {
                    update_post_meta($post_id, "_{$instructor_data['cert_type']}_recert_dates", $reauth_dates);
                }

                // Process course history (indices 11-38, in groups of 7)
                for ($i = 0; $i < 4; $i++) {
                    $base_index = 11 + ($i * 7);
                    $course_date = sanitize_text_field($data[$base_index]);
                    
                    if (empty($course_date)) {
                        continue;
                    }

                    // Prepare course data
                    $course_data = [
                        'instructor_id' => $post_id,
                        'course_type' => $instructor_data['cert_type'],
                        'course_date' => $course_date,
                        'assistant_name' => sanitize_text_field($data[$base_index + 1]),
                        'assistant_email' => sanitize_email($data[$base_index + 2]),
                        'participants_data' => json_encode([
                            'awareness' => intval($data[$base_index + 3]),
                            'operations' => intval($data[$base_index + 4]),
                            'technician' => intval($data[$base_index + 5]),
                            'surf_swiftwater' => $instructor_data['cert_type'] === 'water' ? intval($data[$base_index + 6]) : 0
                        ])
                    ];

                    // Insert course history
                    $wpdb->insert(
                        $wpdb->prefix . 'lsim_course_history',
                        [
                            'instructor_id' => $course_data['instructor_id'],
                            'course_type' => $course_data['course_type'],
                            'course_date' => $course_data['course_date'],
                            'participants_data' => $course_data['participants_data'],
                            'created_at' => current_time('mysql')
                        ]
                    );

                    // If there's an assistant, record it
                    if (!empty($course_data['assistant_email'])) {
                        $assistant = get_posts([
                            'post_type' => 'instructor',
                            'meta_key' => '_email',
                            'meta_value' => $course_data['assistant_email'],
                            'posts_per_page' => 1
                        ]);

                        if (!empty($assistant)) {
                            $wpdb->insert(
                                $wpdb->prefix . 'lsim_assistant_history',
                                [
                                    'instructor_id' => $assistant[0]->ID,
                                    'lead_instructor_id' => $post_id,
                                    'course_date' => $course_data['course_date'],
                                    'course_type' => $course_data['course_type'],
                                    'created_at' => current_time('mysql')
                                ]
                            );
                        }
                    }
                }

                $results['imported']++;

            } catch (Exception $e) {
                $results['errors']++;
                $results['error_messages'][] = $e->getMessage();
            }
        }

        fclose($handle);
        update_option('lsim_last_import_log', $results);

        wp_redirect(add_query_arg('import_complete', '1', wp_get_referer()));
        exit;
    }
}