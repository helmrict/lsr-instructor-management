<?php
if (!defined('ABSPATH')) exit;

class LSIM_Instructor_ID {
    public function __construct() {
        add_action('save_post_instructor', [$this, 'ensure_instructor_id'], 10, 3);
        add_filter('manage_instructor_posts_columns', [$this, 'add_id_column']);
        add_action('manage_instructor_posts_custom_column', [$this, 'display_id_column'], 10, 2);
        add_filter('manage_edit-instructor_sortable_columns', [$this, 'make_id_column_sortable']);
        add_action('pre_get_posts', [$this, 'sort_by_instructor_id']);
        add_action('add_meta_boxes', [$this, 'add_instructor_id_meta_box']);
    }

    public function ensure_instructor_id($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'instructor') return;
        
        $instructor_id = get_post_meta($post_id, '_instructor_id', true);
        if (empty($instructor_id)) {
            update_post_meta($post_id, '_instructor_id', $post_id);
        }
    }

    public function add_id_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns['instructor_id'] = 'Instructor ID';
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    public function display_id_column($column, $post_id) {
        if ($column === 'instructor_id') {
            $instructor_id = get_post_meta($post_id, '_instructor_id', true);
            echo '<strong>' . esc_html($instructor_id ?: $post_id) . '</strong>';
        }
    }

    public function make_id_column_sortable($columns) {
        $columns['instructor_id'] = 'instructor_id';
        return $columns;
    }

    public function sort_by_instructor_id($query) {
        if (!is_admin()) return;
        
        $orderby = $query->get('orderby');
        if ($orderby === 'instructor_id') {
            $query->set('meta_key', '_instructor_id');
            $query->set('orderby', 'meta_value_num');
        }
    }

    public function add_instructor_id_meta_box() {
        add_meta_box(
            'instructor_id_box',
            'Instructor ID',
            [$this, 'render_instructor_id_box'],
            'instructor',
            'side',
            'high'
        );
    }

    public function render_instructor_id_box($post) {
        $instructor_id = get_post_meta($post->ID, '_instructor_id', true);
        if (!$instructor_id) {
            $instructor_id = $post->ID;
        }
        ?>
        <div class="instructor-id-display">
            <p>
                <strong>ID: <?php echo esc_html($instructor_id); ?></strong>
            </p>
            <p class="description">
                This unique identifier is automatically assigned and cannot be changed.
            </p>
        </div>
        <style>
            .instructor-id-display {
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            .instructor-id-display p {
                margin: 0 0 8px 0;
            }
            .instructor-id-display .description {
                color: #666;
                font-style: italic;
                font-size: 12px;
            }
        </style>
        <?php
    }
}