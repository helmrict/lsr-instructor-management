<?php
if (!defined('ABSPATH')) exit;

class LSIM_Instructor_Importer {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_import_page']);
        add_action('admin_post_import_instructors', [$this, 'handle_import']);
    }

    public function add_import_page() {
        add_submenu_page(
            'lifesaving-resources',
            'Import Instructors',
            'Import Instructors',
            'manage_options',
            'instructor-import',
            [$this, 'render_import_page']
        );
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Instructors</h1>
            
            <div class="card">
                <h2>Instructions</h2>
                <p>Upload a CSV file containing instructor information. The CSV should have the following columns:</p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li>First Name</li>
                    <li>Last Name</li>
                    <li>Email</li>
                    <li>Phone</li>
                    <li>Department/Agency</li>
                    <li>State</li>
                    <li>Ice Rescue Original Authorization Date (YYYY-MM-DD)</li>
                    <li>Ice Rescue Status (active/expired)</li>
                    <li>Water Rescue Original Authorization Date (YYYY-MM-DD)</li>
                    <li>Water Rescue Status (active/expired)</li>
                </ul>
                
                <p>
                    <a href="<?php echo plugin_dir_url(LSIM_PLUGIN_FILE) . 'templates/instructor-import-template.csv'; ?>" 
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
            // Show import log if exists
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
            fgetcsv($handle); // Skip header row
        }

        while (($data = fgetcsv($handle)) !== false) {
            try {
                if (count($data) < 10) {
                    throw new Exception('Row does not contain all required fields');
                }

                $instructor_data = [
                    'first_name' => sanitize_text_field($data[0]),
                    'last_name' => sanitize_text_field($data[1]),
                    'email' => sanitize_email($data[2]),
                    'phone' => sanitize_text_field($data[3]),
                    'department' => sanitize_text_field($data[4]),
                    'state' => sanitize_text_field($data[5]),
                    'ice_auth_date' => sanitize_text_field($data[6]),
                    'ice_status' => sanitize_text_field($data[7]),
                    'water_auth_date' => sanitize_text_field($data[8]),
                    'water_status' => sanitize_text_field($data[9])
                ];

                // Check if instructor already exists
                $existing = get_posts([
                    'post_type' => 'instructor',
                    'meta_query' => [
                        [
                            'key' => '_email',
                            'value' => $instructor_data['email']
                        ]
                    ],
                    'posts_per_page' => 1
                ]);

                if (!empty($existing)) {
                    $results['skipped']++;
                    continue;
                }

                // Create instructor
                $post_id = wp_insert_post([
                    'post_title' => $instructor_data['last_name'] . ', ' . $instructor_data['first_name'],
                    'post_type' => 'instructor',
                    'post_status' => 'publish'
                ]);

                if (is_wp_error($post_id)) {
                    throw new Exception('Failed to create instructor: ' . $post_id->get_error_message());
                }

                // Add metadata
                update_post_meta($post_id, '_first_name', $instructor_data['first_name']);
                update_post_meta($post_id, '_last_name', $instructor_data['last_name']);
                update_post_meta($post_id, '_email', $instructor_data['email']);
                update_post_meta($post_id, '_phone', $instructor_data['phone']);
                update_post_meta($post_id, '_department', $instructor_data['department']);
                update_post_meta($post_id, '_state', $instructor_data['state']);

                // Add certification data
                if (!empty($instructor_data['ice_auth_date'])) {
                    update_post_meta($post_id, '_ice_original_date', $instructor_data['ice_auth_date']);
                    update_post_meta($post_id, '_ice_status', $instructor_data['ice_status']);
                }

                if (!empty($instructor_data['water_auth_date'])) {
                    update_post_meta($post_id, '_water_original_date', $instructor_data['water_auth_date']);
                    update_post_meta($post_id, '_water_status', $instructor_data['water_status']);
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