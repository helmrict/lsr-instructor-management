<?php
if (!defined('ABSPATH')) exit;

class LSIM_Admin_Settings {
    private $settings_page = 'instructor-settings';
    private $option_name = 'lsim_settings';

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        // Register the setting
        register_setting(
            $this->option_name, // Option group
            $this->option_name, // Option name
            [
                'type' => 'array',
                'default' => [
                    'certification_period_ice' => 3,
                    'certification_period_water' => 3
                ]
            ]
        );

        // Add settings section
        add_settings_section(
            'certification_settings',          // Section ID
            'Certification Settings',          // Section title
            [$this, 'render_section_intro'],   // Callback for section intro
            $this->settings_page              // Page to add section to
        );

        // Add Ice Rescue Period field
        add_settings_field(
            'certification_period_ice',        // Field ID
            'Ice Rescue Certification Period', // Field title
            [$this, 'render_number_field'],    // Callback to render field
            $this->settings_page,             // Page to add field to
            'certification_settings',          // Section to add field to
            [
                'label_for' => 'certification_period_ice',
                'name' => 'certification_period_ice',
                'min' => 1,
                'max' => 10,
                'description' => 'Number of years an Ice Rescue certification remains valid'
            ]
        );

        // Add Water Rescue Period field
        add_settings_field(
            'certification_period_water',
            'Water Rescue Certification Period',
            [$this, 'render_number_field'],
            $this->settings_page,
            'certification_settings',
            [
                'label_for' => 'certification_period_water',
                'name' => 'certification_period_water',
                'min' => 1,
                'max' => 10,
                'description' => 'Number of years a Water Rescue certification remains valid'
            ]
        );
    }

    public function render_section_intro() {
        echo '<p>Configure the certification periods for Ice and Water Rescue instructors.</p>';
    }

    public function render_number_field($args) {
        $options = get_option($this->option_name);
        $value = isset($options[$args['name']]) ? $options[$args['name']] : 3;
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo $this->option_name . '[' . $args['name'] . ']'; ?>"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               class="small-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_settings_page() {
        // Ensure user has permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(); ?>
            <form action="options.php" method="post">
                <?php
                // Output security fields
                settings_fields($this->option_name);
                // Output setting sections and their fields
                do_settings_sections($this->settings_page);
                // Output save settings button
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}