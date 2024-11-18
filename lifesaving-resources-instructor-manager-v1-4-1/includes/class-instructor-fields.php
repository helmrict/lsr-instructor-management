<?php
if (!defined('ABSPATH')) exit;

class LSIM_Instructor_Fields {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_instructor', [$this, 'save_instructor']);
        add_filter('manage_instructor_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_instructor_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        add_filter('manage_edit-instructor_sortable_columns', [$this, 'make_custom_columns_sortable']);
        add_action('admin_head', [$this, 'hide_title_field']);
    }
	
	public function ensure_instructor_id($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    $instructor_id = get_post_meta($post_id, '_instructor_id', true);
    if (empty($instructor_id)) {
        update_post_meta($post_id, '_instructor_id', $post_id);
    }
}

public function add_id_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        if ($key === 'title') {
            $new_columns['instructor_id'] = 'ID';
            $new_columns[$key] = $value;
        } else {
            $new_columns[$key] = $value;
        }
    }
    return $new_columns;
}

public function display_id_column($column, $post_id) {
    if ($column === 'instructor_id') {
        $instructor_id = get_post_meta($post_id, '_instructor_id', true);
        echo esc_html($instructor_id ?: $post_id);
    }
}

    public function hide_title_field() {
        global $post_type;
        if ($post_type === 'instructor') {
            echo '<style>#titlediv { display: none; }</style>';
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'instructor_details',
            'Instructor Information',
            [$this, 'render_instructor_details'],
            'instructor',
            'normal',
            'high'
        );

        add_meta_box(
            'instructor_certifications',
            'Certification Information',
            [$this, 'render_certification_details'],
            'instructor',
            'normal',
            'high'
        );
    }

    public function render_instructor_details($post) {
        wp_nonce_field('save_instructor_details', 'instructor_details_nonce');
        $meta = get_post_meta($post->ID);
        ?>
        <style>
            .instructor-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .form-row { margin-bottom: 15px; }
            .form-row label { display: block; font-weight: bold; margin-bottom: 5px; }
            .form-row input[type="text"],
            .form-row input[type="email"],
            .form-row input[type="tel"] { width: 100%; }
            .required::after { content: " *"; color: #dc3232; }
        </style>

        <div class="instructor-details-grid">
            <div>
                <div class="form-row">
                    <label class="required" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?php echo esc_attr($meta['_first_name'][0] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label class="required" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo esc_attr($meta['_last_name'][0] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label class="required" for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo esc_attr($meta['_email'][0] ?? ''); ?>" required>
                </div>
            </div>

            <div>
                <div class="form-row">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo esc_attr($meta['_phone'][0] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <label for="department">Department/Agency</label>
                    <input type="text" id="department" name="department" 
                           value="<?php echo esc_attr($meta['_department'][0] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">Select State</option>
                        <?php
                        $states = array(
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
                        );
                        $current_state = $meta['_state'][0] ?? '';
                        foreach ($states as $code => $name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($code),
                                selected($current_state, $code, false),
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_certification_details($post) {
        $meta = get_post_meta($post->ID);
        $certification_period = get_option('lsim_settings')['certification_period'] ?? 3;
        ?>
        <style>
            .certification-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
            .certification-section.active { background-color: #e7f6e7; }
            .certification-section.inactive { background-color: #fff; }
            .certification-dates { margin-top: 10px; }
            .auth-date { margin-bottom: 10px; }
            .add-recert-date { margin-top: 10px; }
            .recert-date { margin-left: 20px; margin-bottom: 5px; }
            .expiration-info { margin-top: 10px; color: #666; }
        </style>

        <!-- Ice Rescue Certification -->
        <div class="certification-section <?php echo $this->is_certification_active($post->ID, 'ice') ? 'active' : 'inactive'; ?>">
            <h3>Ice Rescue Certification</h3>
            
            <div class="certification-dates">
                <div class="auth-date">
                    <label>Original Authorization Date:</label>
                    <input type="date" name="ice_original_date" 
                           value="<?php echo esc_attr($meta['_ice_original_date'][0] ?? ''); ?>"
                           class="certification-date">
                </div>

                <div id="ice-recert-dates">
                    <?php
                    $ice_recerts = get_post_meta($post->ID, '_ice_recert_dates', true) ?: [];
                    foreach ($ice_recerts as $date) {
                        ?>
                        <div class="recert-date">
                            <label>Recertification Date:</label>
                            <input type="date" name="ice_recert_dates[]" value="<?php echo esc_attr($date); ?>">
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <button type="button" class="button add-recert-date" data-type="ice">Add Recertification Date</button>

                <?php if ($expiration = $this->get_certification_expiration($post->ID, 'ice')): ?>
                    <div class="expiration-info">
                        Expires: <?php echo date('F j, Y', strtotime($expiration)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Water Rescue Certification -->
        <div class="certification-section <?php echo $this->is_certification_active($post->ID, 'water') ? 'active' : 'inactive'; ?>">
            <h3>Water Rescue Certification</h3>
            
            <div class="certification-dates">
                <div class="auth-date">
                    <label>Original Authorization Date:</label>
                    <input type="date" name="water_original_date" 
                           value="<?php echo esc_attr($meta['_water_original_date'][0] ?? ''); ?>"
                           class="certification-date">
                </div>

                <div id="water-recert-dates">
                    <?php
                    $water_recerts = get_post_meta($post->ID, '_water_recert_dates', true) ?: [];
                    foreach ($water_recerts as $date) {
                        ?>
                        <div class="recert-date">
                            <label>Recertification Date:</label>
                            <input type="date" name="water_recert_dates[]" value="<?php echo esc_attr($date); ?>">
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <button type="button" class="button add-recert-date" data-type="water">Add Recertification Date</button>

                <?php if ($expiration = $this->get_certification_expiration($post->ID, 'water')): ?>
                    <div class="expiration-info">
                        Expires: <?php echo date('F j, Y', strtotime($expiration)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.add-recert-date').on('click', function() {
                const type = $(this).data('type');
                const template = `
                    <div class="recert-date">
                        <label>Recertification Date:</label>
                        <input type="date" name="${type}_recert_dates[]">
                    </div>
                `;
                $(`#${type}-recert-dates`).append(template);
            });

// Auto-calculate expiration when dates change
$('input[type="date"]').on('change', function() {
    const section = $(this).closest('.certification-section');
    const type = section.find('.add-recert-date').data('type');
    const dates = section.find('input[type="date"]').map(function() {
        return $(this).val();
    }).get().filter(Boolean);

    if (dates.length > 0) {
        // Get the most recent date
        const mostRecent = new Date(Math.max.apply(null, dates.map(date => new Date(date))));
        // Add certification period years (changing this part)
        const certYear = mostRecent.getFullYear();
        const expirationDate = new Date(certYear + 3, 11, 31); // Month is 0-based, so 11 is December
        
        // Update expiration info
        section.find('.expiration-info').html(
            'Expires: ' + expirationDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
        );
    }
});
        </script>
        <?php
    }

    public function save_instructor($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Save basic details
        if (isset($_POST['instructor_details_nonce']) && 
            wp_verify_nonce($_POST['instructor_details_nonce'], 'save_instructor_details')) {
            
            $fields = ['first_name', 'last_name', 'email', 'phone', 'department', 'state'];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
                }
            }

            // Update post title
            if (!empty($_POST['first_name']) && !empty($_POST['last_name'])) {
                $title = $_POST['last_name'] . ', ' . $_POST['first_name'];
                remove_action('save_post_instructor', [$this, 'save_instructor']);
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $title
                ]);
                add_action('save_post_instructor', [$this, 'save_instructor']);
            }
        }

        // Save certification dates
        $types = ['ice', 'water'];
        foreach ($types as $type) {
            // Save original date
            if (isset($_POST[$type . '_original_date'])) {
                update_post_meta($post_id, '_' . $type . '_original_date', 
                    sanitize_text_field($_POST[$type . '_original_date']));
            }

            // Save recertification dates
            if (isset($_POST[$type . '_recert_dates'])) {
                $dates = array_map('sanitize_text_field', $_POST[$type . '_recert_dates']);
                $dates = array_filter($dates); // Remove empty values
                sort($dates); // Sort chronologically
                update_post_meta($post_id, '_' . $type . '_recert_dates', $dates);
            }
        }

        // Update taxonomy terms
        $terms = [];
        foreach ($types as $type) {
            if ($this->is_certification_active($post_id, $type)) {
                $term = get_term_by('name', ucfirst($type) . ' Rescue', 'certification_type');
                if ($term) {
                    $terms[] = $term->term_id;
                }
            }
        }
        wp_set_object_terms($post_id, $terms, 'certification_type');
    }

    private function is_certification_active($post_id, $type) {
        $expiration = $this->get_certification_expiration($post_id, $type);
        return $expiration && strtotime($expiration) >= current_time('timestamp');
    }

private function get_certification_expiration($post_id, $type) {
    // Get all certification dates
    $dates = [];
    
    // Add original date
    $original_date = get_post_meta($post_id, '_' . $type . '_original_date', true);
    if ($original_date) {
        $dates[] = $original_date;
    }
    // Add recertification dates
    $recert_dates = get_post_meta($post_id, '_' . $type . '_recert_dates', true) ?: [];
    $dates = array_merge($dates, $recert_dates);
    
    if (empty($dates)) {
        return null;
    }
    
    // Get most recent date
    sort($dates);
    $most_recent = end($dates);
    
    // Calculate expiration (changing this part)
    $cert_year = date('Y', strtotime($most_recent));
    return date('Y-12-31', strtotime($cert_year . ' +3 years'));
}

    public function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = 'Name';
                $new_columns['department'] = 'Department/Agency';
                $new_columns['state'] = 'State';
                $new_columns['ice_rescue'] = 'Ice Rescue Status';
                $new_columns['water_rescue'] = 'Water Rescue Status';
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    public function populate_custom_columns($column, $post_id) {
        switch ($column) {
            case 'department':
                echo esc_html(get_post_meta($post_id, '_department', true));
                break;

            case 'state':
                echo esc_html(get_post_meta($post_id, '_state', true));
                break;

            case 'ice_rescue':
                $this->display_certification_status($post_id, 'ice');
                break;

            case 'water_rescue':
                $this->display_certification_status($post_id, 'water');
                break;
        }
    }

    private function display_certification_status($post_id, $type) {
        if ($expiration = $this->get_certification_expiration($post_id, $type)) {
            $is_active = strtotime($expiration) >= current_time('timestamp');
            $status_class = $is_active ? 'status-active' : 'status-expired';
            $status_text = $is_active ? 'Active' : 'Expired';
            
            echo sprintf(
                '<span class="%s">%s<br><small>Expires: %s</small></span>',
                esc_attr($status_class),
                esc_html($status_text),
                date('M j, Y', strtotime($expiration))
            );
        } else {
            echo '<span class="status-none">Not Certified</span>';
        }
    }

    public function make_custom_columns_sortable($columns) {
        $columns['department'] = 'department';
        $columns['state'] = 'state';
        return $columns;
    }
}