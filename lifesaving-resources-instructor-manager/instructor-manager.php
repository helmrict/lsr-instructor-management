<?php
/**
 * Plugin Name: Lifesaving Resources Instructor Manager
 * Description: Manages Ice and Water Rescue instructors, certifications, and course completions
 * Version: 1.2
 * Author: Custom Development
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LSIM_VERSION', '1.2');
define('LSIM_PLUGIN_FILE', __FILE__);
define('LSIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LSIM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once LSIM_PLUGIN_DIR . 'includes/class-instructor-fields.php';
require_once LSIM_PLUGIN_DIR . 'includes/class-form-integration.php';
require_once LSIM_PLUGIN_DIR . 'includes/class-instructor-importer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin/settings.php';

class Lifesaving_Resources_Manager {
    private static $instance = null;
    private $instructor_fields;
    private $form_integration;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize components
        $this->instructor_fields = new LSIM_Instructor_Fields();
        $this->form_integration = new LSIM_Form_Integration();
        $this->instructor_importer = new LSIM_Instructor_Importer(); // Add this line
        
        // Add hooks
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'check_dependencies']);
    }

    public function register_post_type() {
        // Register Instructor post type
        register_post_type('instructor', [
    'labels' => [
        'name' => 'Instructors',
        'singular_name' => 'Instructor',
        'add_new' => 'Add New Instructor',
        'add_new_item' => 'Add New Instructor',
        'edit_item' => 'Edit Instructor',
        'view_item' => 'View Instructor',
        'search_items' => 'Search Instructors',
        'not_found' => 'No instructors found',
        'not_found_in_trash' => 'No instructors found in trash'
    ],
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => false,  // Changed to false to prevent duplicate menu
    'menu_position' => null,
    'supports' => ['title'],
    'capability_type' => 'post',
    'hierarchical' => false,
    'has_archive' => false
]);

        // Register Certification Type taxonomy
        register_taxonomy('certification_type', 'instructor', [
            'labels' => [
                'name' => 'Certification Types',
                'singular_name' => 'Certification Type',
                'search_items' => 'Search Certification Types',
                'all_items' => 'All Certification Types',
                'edit_item' => 'Edit Certification Type',
                'update_item' => 'Update Certification Type',
                'add_new_item' => 'Add New Certification Type',
                'new_item_name' => 'New Certification Type Name',
                'menu_name' => 'Certification Types'
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'certification-type']
        ]);
    }

public function add_admin_menu() {
    // Main menu
    add_menu_page(
        'Lifesaving Resources',
        'Lifesaving Resources',
        'manage_options',
        'lifesaving-resources',
        [$this, 'render_dashboard_page'],
        'dashicons-shield',
        30
    );

    // Submenus in desired order
    add_submenu_page(
        'lifesaving-resources',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'lifesaving-resources',
        [$this, 'render_dashboard_page']
    );

    add_submenu_page(
        'lifesaving-resources',
        'Instructors',
        'Instructors',
        'manage_options',
        'edit.php?post_type=instructor'
    );

    add_submenu_page(
        'lifesaving-resources',
        'Add New Instructor',
        'Add New',
        'manage_options',
        'post-new.php?post_type=instructor'
    );

    // Let the importer class handle its own menu registration
    // DO NOT add Import Instructors menu here

    add_submenu_page(
        'lifesaving-resources',
        'Certification Types',
        'Certification Types',
        'manage_options',
        'edit-tags.php?taxonomy=certification_type&post_type=instructor'
    );

    // Create settings instance before using it
    $settings_page = new LSIM_Admin_Settings();
    
    add_submenu_page(
        'lifesaving-resources',
        'Settings',
        'Settings',
        'manage_options',
        'instructor-settings',
        [$settings_page, 'render_settings_page']
    );
}

    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>Lifesaving Resources Dashboard</h1>
            
            <div class="dashboard-widgets-wrap">
                <!-- Instructor Statistics -->
                <div class="postbox">
                    <h2 class="hndle"><span>Instructor Overview</span></h2>
                    <div class="inside">
                        <?php
                        $ice_instructors = get_posts([
                            'post_type' => 'instructor',
                            'numberposts' => -1,
                            'meta_query' => [
                                [
                                    'key' => '_ice_active',
                                    'value' => '1'
                                ]
                            ]
                        ]);

                        $water_instructors = get_posts([
                            'post_type' => 'instructor',
                            'numberposts' => -1,
                            'meta_query' => [
                                [
                                    'key' => '_water_active',
                                    'value' => '1'
                                ]
                            ]
                        ]);
                        ?>
                        <ul>
                            <li>Active Ice Rescue Instructors: <?php echo count($ice_instructors); ?></li>
                            <li>Active Water Rescue Instructors: <?php echo count($water_instructors); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="postbox">
                    <h2 class="hndle"><span>Recent Activity</span></h2>
                    <div class="inside">
                        <?php
                        global $wpdb;
                        $recent_courses = $wpdb->get_results(
                            "SELECT ch.*, p.post_title as instructor_name 
                            FROM {$wpdb->prefix}lsim_course_history ch 
                            JOIN {$wpdb->posts} p ON ch.instructor_id = p.ID 
                            ORDER BY ch.course_date DESC LIMIT 5"
                        );

                        if ($recent_courses): ?>
                            <ul>
                                <?php foreach ($recent_courses as $course): ?>
                                    <li>
                                        <?php 
                                        echo sprintf(
                                            '%s completed %s course on %s',
                                            esc_html($course->instructor_name),
                                            esc_html(ucfirst($course->course_type)),
                                            date('M j, Y', strtotime($course->course_date))
                                        );
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No recent course activity.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="postbox">
                    <h2 class="hndle"><span>Quick Actions</span></h2>
                    <div class="inside">
                        <a href="<?php echo admin_url('post-new.php?post_type=instructor'); ?>" class="button button-primary">Add New Instructor</a>
                        <a href="<?php echo admin_url('edit.php?post_type=instructor'); ?>" class="button">View All Instructors</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Instructor Manager Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lsim_settings');
                do_settings_sections('lsim_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
    // Get current screen to check post type
    $screen = get_current_screen();
    
    // Only load on our plugin pages
    if (strpos($hook, 'lifesaving-resources') === false && 
        (!$screen || $screen->post_type !== 'instructor')) {
        return;
    }

    // Main admin styles
    wp_enqueue_style(
        'lsim-admin-style',
        LSIM_PLUGIN_URL . 'assets/css/admin-styles.css',
        [],
        LSIM_VERSION
    );

    // Main admin scripts
    wp_enqueue_script(
        'lsim-admin-script',
        LSIM_PLUGIN_URL . 'assets/js/admin-scripts.js',
        ['jquery'],
        LSIM_VERSION,
        true
    );

    // Only load reporting assets on the reports page
    if (strpos($hook, 'instructor-reports') !== false) {
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
}

    public function check_dependencies() {
        if (!class_exists('GFAPI')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning">
                    <p>Lifesaving Resources Instructor Manager requires Gravity Forms to be installed and activated for full functionality.</p>
                </div>
                <?php
            });
        }
    }
}

// Activation hook
function lsim_activate() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create course history table
    $table_name = $wpdb->prefix . 'lsim_course_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        instructor_id bigint(20) NOT NULL,
        course_type varchar(50) NOT NULL,
        course_date date NOT NULL,
        location text NOT NULL,
        hours int(11) NOT NULL,
        participants_data text NOT NULL,
        form_entry_id bigint(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY instructor_id (instructor_id),
        KEY course_date (course_date)
    ) $charset_collate;";
    
    dbDelta($sql);

    // Add default certification types
    if (!term_exists('Ice Rescue', 'certification_type')) {
        wp_insert_term('Ice Rescue', 'certification_type');
    }
    if (!term_exists('Water Rescue', 'certification_type')) {
        wp_insert_term('Water Rescue', 'certification_type');
    }

    // Set default options
    add_option('lsim_settings', [
        'certification_period_ice' => 3,
        'certification_period_water' => 3,
        'require_phone' => true,
        'require_department' => true
    ]);
    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'lsim_activate');

// Deactivation hook
function lsim_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lsim_deactivate');

// Initialize the plugin
function initialize_lifesaving_resources_manager() {
    return Lifesaving_Resources_Manager::get_instance();
}
add_action('plugins_loaded', 'initialize_lifesaving_resources_manager');