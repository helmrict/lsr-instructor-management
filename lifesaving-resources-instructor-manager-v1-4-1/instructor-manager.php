<?php
/**
 * Plugin Name: Lifesaving Resources Instructor Manager
 * Description: Manages Ice and Water Rescue instructors, certifications, and course completions
 * Version: 1.4.1
 * Author: Custom Development
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LSIM_VERSION', '1.4.1');
define('LSIM_PLUGIN_FILE', __FILE__);
define('LSIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LSIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LSIM_MIN_PHP_VERSION', '7.4');
define('LSIM_MIN_WP_VERSION', '5.8');

class Lifesaving_Resources_Manager {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Component instances
     */
    private $instructor_fields;
    private $form_integration;
    private $instructor_importer;
    private $notifications;
    private $reporting;
    private $instructor_id;        // Add this
    private $assistant_tracking;   // Add this

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

 private function load_dependencies() {
    $files = [
        'includes/class-instructor-fields.php',
        'includes/class-form-integration.php',
        'includes/class-instructor-importer.php',
        'includes/class-notifications.php',
        'includes/class-reporting.php',
        'includes/class-instructor-id.php',
        'includes/class-assistant-tracking.php',
        'includes/admin/settings.php'
    ];

    foreach ($files as $file) {
        $filepath = LSIM_PLUGIN_DIR . $file;
        if (!file_exists($filepath)) {
            error_log("Missing required file: $filepath");
            continue;
        }
        require_once $filepath;
    }
}

private function __construct() {
    // Load dependencies first
    $this->load_dependencies();
    $this->setup_security();
    
    // Initialize components
    $this->instructor_fields = new LSIM_Instructor_Fields();
    $this->form_integration = new LSIM_Form_Integration();
    $this->instructor_importer = new LSIM_Instructor_Importer();
    $this->notifications = new LSIM_Notifications();
    $this->reporting = new LSIM_Reporting();
    $this->instructor_id = new LSIM_Instructor_ID();
    $this->assistant_tracking = new LSIM_Assistant_Tracking();  // Add this line
    
    // Add hooks
    add_action('init', [$this, 'register_post_type']);
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_init', [$this, 'check_dependencies']);
}
    private function setup_security() {
        // Add CSRF protection
        add_action('admin_init', [$this, 'verify_nonce']);
    }

    private function log_error($message, $data = []) {
        if (WP_DEBUG_LOG) {
            error_log(sprintf(
                '[Lifesaving Resources] %s | Data: %s',
                $message,
                print_r($data, true)
            ));
        }
    }

    public function verify_nonce() {
        if (isset($_POST['_wpnonce'])) {
            check_admin_referer('lsim_action_nonce');
        }
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
            'show_in_menu' => false,
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
            'show_ui' => false,
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

    // Dashboard
    add_submenu_page(
        'lifesaving-resources',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'lifesaving-resources',
        [$this, 'render_dashboard_page']
    );

    // Instructors
    add_submenu_page(
        'lifesaving-resources',
        'Instructors',
        'Instructors',
        'manage_options',
        'edit.php?post_type=instructor'
    );

    // Add Instructor
    add_submenu_page(
        'lifesaving-resources',
        'Add Instructor',
        'Add Instructor',
        'manage_options',
        'post-new.php?post_type=instructor'
    );

    // Reports
    add_submenu_page(
        'lifesaving-resources',
        'Reports',
        'Reports',
        'manage_options',
        'instructor-reports',
        [$this->reporting, 'render_reports_page']
    );

// Import Instructors
if (isset($this->instructor_importer) && method_exists($this->instructor_importer, 'render_import_page')) {
    add_submenu_page(
        'lifesaving-resources',
        'Import Instructors',
        'Import Instructors',
        'manage_options',
        'instructor-import',
        [$this->instructor_importer, 'render_import_page']
    );
}

    // Settings
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

    public function check_dependencies() {
        $notices = [];
        
        if (!class_exists('GFAPI')) {
            $notices[] = 'Gravity Forms is required for full functionality.';
        }
        
        if (version_compare(PHP_VERSION, LSIM_MIN_PHP_VERSION, '<')) {
            $notices[] = sprintf('PHP version %s or higher is required.', LSIM_MIN_PHP_VERSION);
        }
        
        if (!empty($notices)) {
            add_action('admin_notices', function() use ($notices) {
                foreach ($notices as $notice) {
                    printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($notice));
                }
            });
        }
    }

    private function get_active_instructors($type) {
        return get_posts([
            'post_type' => 'instructor',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => "_{$type}_active",
                    'value' => '1'
                ]
            ]
        ]);
    }

    private function get_recent_courses($limit = 5) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ch.*, p.post_title as instructor_name 
                FROM {$wpdb->prefix}lsim_course_history ch 
                JOIN {$wpdb->posts} p ON ch.instructor_id = p.ID 
                ORDER BY ch.course_date DESC LIMIT %d",
                $limit
            )
        );
    }

    public function render_dashboard_page() {
        // Create nonce for security
        $nonce = wp_create_nonce('lsim_dashboard_nonce');
        
        // Get dashboard data
        $ice_instructors = $this->get_active_instructors('ice');
        $water_instructors = $this->get_active_instructors('water');
        $recent_courses = $this->get_recent_courses(5);
        
        // Include dashboard template
        require_once LSIM_PLUGIN_DIR . 'includes/admin/templates/dashboard.php';
    }

    public function enqueue_admin_assets($hook) {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        $this->enqueue_common_assets();
        
        if ($this->is_reports_page($hook)) {
            $this->enqueue_report_assets();
        }
    }

    private function is_plugin_page($hook) {
        $screen = get_current_screen();
        return strpos($hook, 'lifesaving-resources') !== false || 
               ($screen && $screen->post_type === 'instructor');
    }

    private function enqueue_common_assets() {
        wp_enqueue_style(
            'lsim-admin-style',
            LSIM_PLUGIN_URL . 'assets/css/admin-styles.css',
            [],
            LSIM_VERSION
        );

        wp_enqueue_script(
            'lsim-admin-script',
            LSIM_PLUGIN_URL . 'assets/js/admin-scripts.js',
            ['jquery'],
            LSIM_VERSION,
            true
        );
    }

    private function enqueue_report_assets() {
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

    private function is_reports_page($hook) {
        return strpos($hook, 'instructor-reports') !== false;
    }
}

// Activation hook
// Replace the existing lsim_activate() function
function lsim_activate() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create course history table
    $course_table = $wpdb->prefix . 'lsim_course_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $course_table (
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

    // Create assistant history table
    $assistant_table = $wpdb->prefix . 'lsim_assistant_history';
    
    $sql = "CREATE TABLE IF NOT EXISTS $assistant_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        instructor_id bigint(20) NOT NULL,
        lead_instructor_id bigint(20) NOT NULL,
        course_date date NOT NULL,
        course_type varchar(50) NOT NULL,
        location text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY instructor_id (instructor_id),
        KEY lead_instructor_id (lead_instructor_id)
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