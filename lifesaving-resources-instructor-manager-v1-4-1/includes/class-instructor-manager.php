<?php
if (!defined('ABSPATH')) exit;

class LifesavingResourcesInstructorManager {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'create_instructor_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_instructor', [$this, 'save_instructor_metadata']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('manage_instructor_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_instructor_posts_custom_column', [$this, 'manage_custom_columns'], 10, 2);
        add_filter('manage_edit-instructor_sortable_columns', [$this, 'make_custom_columns_sortable']);
    }

    public function create_instructor_post_type() {
        $labels = array(
            'name'               => 'Instructors',
            'singular_name'      => 'Instructor',
            'menu_name'          => 'Instructors',
            'add_new'           => 'Add New',
            'add_new_item'      => 'Add New Instructor',
            'edit_item'         => 'Edit Instructor',
            'new_item'          => 'New Instructor',
            'view_item'         => 'View Instructor',
            'search_items'      => 'Search Instructors',
            'not_found'         => 'No instructors found',
            'not_found_in_trash'=> 'No instructors found in Trash'
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-groups',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => array('title'),
            'has_archive'         => false,
            'show_in_nav_menus'   => false,
            'exclude_from_search' => true
        );

        register_post_type('instructor', $args);

        // Register taxonomies
        register_taxonomy('certification_type', 'instructor', array(
            'hierarchical'      => true,
            'labels'           => array(
                'name'              => 'Certification Types',
                'singular_name'     => 'Certification Type',
                'search_items'      => 'Search Certification Types',
                'all_items'         => 'All Certification Types',
                'edit_item'         => 'Edit Certification Type',
                'update_item'       => 'Update Certification Type',
                'add_new_item'      => 'Add New Certification Type',
                'new_item_name'     => 'New Certification Type Name',
                'menu_name'         => 'Certification Types'
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'instructor_details',
            'Instructor Information',
            [$this, 'render_details_meta_box'],
            'instructor',
            'normal',
            'high'
        );

        add_meta_box(
            'certification_details',
            'Certification Details',
            [$this, 'render_certification_meta_box'],
            'instructor',
            'normal',
            'high'
        );
    }

    public function render_details_meta_box($post) {
        wp_nonce_field('save_instructor_details', 'instructor_details_nonce');
        $meta = get_post_meta($post->ID);
        ?>
        <div class="instructor-details-grid">
            <div class="name-section">
                <p>
                    <label for="first_name"><strong>First Name:</strong> <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?php echo esc_attr($meta['_first_name'][0] ?? ''); ?>" required>
                </p>
                <p>
                    <label for="last_name"><strong>Last Name:</strong> <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo esc_attr($meta['_last_name'][0] ?? ''); ?>" required>
                </p>
                <p>
                    <label for="middle_name"><strong>Middle Name/Initial:</strong></label>
                    <input type="text" id="middle_name" name="middle_name" 
                           value="<?php echo esc_attr($meta['_middle_name'][0] ?? ''); ?>">
                </p>
            </div>

            <div class="contact-section">
                <p>
                    <label for="email"><strong>Email:</strong> <span class="required">*</span></label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo esc_attr($meta['_email'][0] ?? ''); ?>" required>
                </p>
                <p>
                    <label for="phone"><strong>Phone:</strong></label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo esc_attr($meta['_phone'][0] ?? ''); ?>">
                </p>
            </div>

            <div class="organization-section">
                <p>
                    <label for="department"><strong>Department/Agency:</strong></label>
                    <input type="text" id="department" name="department" 
                           value="<?php echo esc_attr($meta['_department'][0] ?? ''); ?>">
                </p>
                <p>
                    <label for="address"><strong>Address:</strong></label>
                    <textarea id="address" name="address" rows="3"><?php 
                        echo esc_textarea($meta['_address'][0] ?? ''); 
                    ?></textarea>
                </p>
            </div>
        </div>
        <?php
    }

    public function render_certification_meta_box($post) {
        wp_nonce_field('save_certification_details', 'certification_details_nonce');
        $ice_status = $this->get_certification_status($post->ID, 'ice');
        $water_status = $this->get_certification_status($post->ID, 'water');
        ?>
        <div class="certification-sections">
            <div class="certification-section <?php echo $ice_status['valid'] ? 'status-valid' : 'status-invalid'; ?>">
                <h3>Ice Rescue Certification
                    <?php if ($ice_status['expiration']): ?>
                        <span class="expiration-date">
                            (Expires: <?php echo date('M j, Y', strtotime($ice_status['expiration'])); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                <?php $this->render_certification_fields($post->ID, 'ice'); ?>
            </div>

            <div class="certification-section <?php echo $water_status['valid'] ? 'status-valid' : 'status-invalid'; ?>">
                <h3>Water Rescue Certification
                    <?php if ($water_status['expiration']): ?>
                        <span class="expiration-date">
                            (Expires: <?php echo date('M j, Y', strtotime($water_status['expiration'])); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                <?php $this->render_certification_fields($post->ID, 'water'); ?>
            </div>
        </div>
        <?php
    }

    private function render_certification_fields($post_id, $type) {
        $meta = get_post_meta($post_id);
        $prefix = "_{$type}_";
        ?>
        <div class="certification-fields">
            <p>
                <label><strong>Original Authorization Date:</strong></label>
                <input type="date" name="<?php echo $type; ?>_original_date" 
                       value="<?php echo esc_attr($meta[$prefix . 'original_date'][0] ?? ''); ?>" 
                       class="certification-date">
            </p>
            <div class="reauthorization-history" id="<?php echo $type; ?>-history">
                <?php
                $history = get_post_meta($post_id, $prefix . 'reauthorization_history', true) ?: array();
                foreach ($history as $index => $entry):
                ?>
                    <div class="history-entry">
                        <input type="date" name="<?php echo $type; ?>_reauth_date[]" 
                               value="<?php echo esc_attr($entry['date']); ?>">
                        <input type="date" name="<?php echo $type; ?>_expiration_date[]" 
                               value="<?php echo esc_attr($entry['expiration']); ?>">
                        <input type="text" name="<?php echo $type; ?>_notes[]" 
                               value="<?php echo esc_attr($entry['notes']); ?>" placeholder="Notes">
                        <button type="button" class="remove-history-entry">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="add-history-entry button" data-type="<?php echo $type; ?>">
                Add Reauthorization
            </button>
        </div>
        <?php
    }

    public function save_instructor_metadata($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save instructor details
        if (isset($_POST['instructor_details_nonce']) && 
            wp_verify_nonce($_POST['instructor_details_nonce'], 'save_instructor_details')) {
            
            $this->save_basic_details($post_id);
        }

        // Save certification details
        if (isset($_POST['certification_details_nonce']) && 
            wp_verify_nonce($_POST['certification_details_nonce'], 'save_certification_details')) {
            
            $this->save_certification_details($post_id, 'ice');
            $this->save_certification_details($post_id, 'water');
        }

        // Update the post title
        $this->update_instructor_title($post_id);
    }

    private function save_basic_details($post_id) {
        $fields = array(
            'first_name', 'last_name', 'middle_name', 'email', 
            'phone', 'department', 'address'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    private function save_certification_details($post_id, $type) {
        // Save original date
        $original_date = $_POST["{$type}_original_date"] ?? '';
        if ($original_date) {
            update_post_meta($post_id, "_{$type}_original_date", sanitize_text_field($original_date));
        }

        // Save reauthorization history
        $history = array();
        $reauth_dates = $_POST["{$type}_reauth_date"] ?? array();
        $exp_dates = $_POST["{$type}_expiration_date"] ?? array();
        $notes = $_POST["{$type}_notes"] ?? array();

        foreach ($reauth_dates as $index => $date) {
            if (!empty($date)) {
                $history[] = array(
                    'date' => sanitize_text_field($date),
                    'expiration' => sanitize_text_field($exp_dates[$index] ?? ''),
                    'notes' => sanitize_text_field($notes[$index] ?? '')
                );
            }
        }

        update_post_meta($post_id, "_{$type}_reauthorization_history", $history);

        // Update certification type taxonomy
        $term = get_term_by('name', ucfirst($type) . ' Rescue', 'certification_type');
        if ($term && (!empty($original_date) || !empty($history))) {
            wp_set_object_terms($post_id, $term->term_id, 'certification_type', true);
        }
    }

    private function update_instructor_title($post_id) {
        $first_name = get_post_meta($post_id, '_first_name', true);
        $last_name = get_post_meta($post_id, '_last_name', true);
        $middle_name = get_post_meta($post_id, '_middle_name', true);

        $title = $last_name . ', ' . $first_name;
        if (!empty($middle_name)) {
            $title .= ' ' . substr($middle_name, 0, 1) . '.';
        }

        remove_action('save_post_instructor', [$this, 'save_instructor_metadata']);
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title
        ));
        add_action('save_post_instructor', [$this, 'save_instructor_metadata']);
    }

    public function enqueue_admin_assets($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post_type;
        if ('instructor' !== $post_type) {
            return;
        }

        wp_enqueue_style(
            'instructor-manager-admin',
            LSIM_PLUGIN_URL . 'assets/css/admin-styles.css',
            array(),
            LSIM_VERSION
        );

        wp_enqueue_script(
            'instructor-manager-admin',
            LSIM_PLUGIN_URL . 'assets/js/admin-scripts.js',
            array('jquery'),
            LSIM_VERSION,
            true
        );
    }

    // Add custom columns to the instructor list
    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['department'] = 'Department/Agency';
                $new_columns['ice_status'] = 'Ice Rescue Status';
                $new_columns['water_status'] = 'Water Rescue Status';
                $new_columns['last_course'] = 'Last Course';
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    // Manage custom column content
    public function manage_custom_columns($column, $post_id) {
        switch ($column) {
            case 'department':
                echo esc_html(get_post_meta($post_id, '_department', true));
                break;
            case 'ice_status':
                $this->display_certification_status($post_id, 'ice');
                break;
            case 'water_status':
                $this->display_certification_status($post_id, 'water');
                break;
            case 'last_course':
			$courses = get_post_meta($post_id, '_taught_courses', true) ?: array();
                if (!empty($courses)) {
                    usort($courses, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });
                    echo date('M j, Y', strtotime($courses[0]['date']));
                } else {
                    echo '—';
                }
                break;
        }
    }

    private function display_certification_status($post_id, $type) {
        $status = $this->get_certification_status($post_id, $type);
        $class = $status['valid'] ? 'status-valid' : 'status-invalid';
        $label = $status['valid'] ? 'Active' : 'Expired';
        
        echo '<span class="certification-status ' . esc_attr($class) . '">';
        echo esc_html($label);
        if ($status['expiration']) {
            echo '<br><small>Exp: ' . date('M j, Y', strtotime($status['expiration'])) . '</small>';
        }
        echo '</span>';
    }

    private function get_certification_status($post_id, $type) {
        $current_date = current_time('Y-m-d');
        $original_date = get_post_meta($post_id, "_{$type}_original_date", true);
        $history = get_post_meta($post_id, "_{$type}_reauthorization_history", true) ?: array();
        
        // Calculate original expiration
        $original_expiration = $original_date ? 
            date('Y-m-d', strtotime($original_date . ' +3 years')) : null;
        
        // Get latest reauthorization if exists
        $latest_reauth = end($history);
        $latest_expiration = $latest_reauth ? 
            $latest_reauth['expiration'] : $original_expiration;
        
        return array(
            'valid' => $latest_expiration && $latest_expiration >= $current_date,
            'expiration' => $latest_expiration
        );
    }

    public function make_custom_columns_sortable($columns) {
        $columns['department'] = 'department';
        $columns['last_course'] = 'last_course';
        return $columns;
    }

    public function pre_get_posts_sort($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        switch ($orderby) {
            case 'department':
                $query->set('meta_key', '_department');
                $query->set('orderby', 'meta_value');
                break;
            case 'last_course':
                $query->set('meta_key', '_last_course_date');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    public function validate_required_fields($post_id) {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        // Verify this is an instructor post
        if (get_post_type($post_id) !== 'instructor') return;
        
        // Check required fields
        $required_fields = array(
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email'
        );
        
        $missing_fields = array();
        
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $label;
            }
        }
        
        if (!empty($missing_fields)) {
            wp_die(
                'Error: The following required fields are missing: ' . 
                implode(', ', $missing_fields) . 
                '<br><br><a href="javascript:history.back()">Go Back</a>'
            );
        }
        
        // Validate email format
        if (!empty($_POST['email']) && !is_email($_POST['email'])) {
            wp_die(
                'Error: Please enter a valid email address.' .
                '<br><br><a href="javascript:history.back()">Go Back</a>'
            );
        }
    }

    public function check_duplicate_email($post_id) {
        if (empty($_POST['email'])) return;
        
        $email = sanitize_email($_POST['email']);
        
        $existing = get_posts(array(
            'post_type' => 'instructor',
            'post_status' => 'any',
            'meta_key' => '_email',
            'meta_value' => $email,
            'post__not_in' => array($post_id),
            'posts_per_page' => 1
        ));
        
        if (!empty($existing)) {
            wp_die(
                'Error: An instructor with this email address already exists.' .
                '<br><br><a href="javascript:history.back()">Go Back</a>'
            );
        }
    }

    private function format_phone_number($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6)
            );
        }
        return $phone;
    }

    public function register_meta_fields() {
        register_meta('post', '_first_name', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_meta('post', '_last_name', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_meta('post', '_email', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
}

// Initialize the class
return LifesavingResourcesInstructorManager::get_instance();