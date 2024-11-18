<?php
if (!defined('ABSPATH')) exit;

class LSIM_Notifications {
    // Notification schedules (days before expiration)
    const NOTIFICATION_SCHEDULE = [
        'first_notice' => 180,     // 6 months
        'second_notice' => 90,     // 3 months
        'urgent_notice' => 30,     // 1 month
        'final_notice' => 7        // 1 week
    ];

    public function __construct() {
        // Schedule daily checks
        if (!wp_next_scheduled('lsim_daily_notification_check')) {
            wp_schedule_event(strtotime('tomorrow 9:00:00'), 'daily', 'lsim_daily_notification_check');
        }

        add_action('lsim_daily_notification_check', [$this, 'check_certifications']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_init', [$this, 'register_notification_settings']);
    }

    public function register_notification_settings() {
        register_setting('lsim_settings', 'lsim_notification_settings', [
            'type' => 'array',
            'default' => [
                'admin_email' => get_option('admin_email'),
                'notify_admin' => true,
                'notify_instructors' => true
            ]
        ]);
    }

    public function check_certifications() {
        $instructors = get_posts([
            'post_type' => 'instructor',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($instructors as $instructor) {
            $this->check_instructor_certifications($instructor);
        }

        // Send daily summary to admin
        $this->send_admin_summary();
    }

    private function check_instructor_certifications($instructor) {
        foreach (['ice', 'water'] as $type) {
            $expiration = $this->get_certification_expiration($instructor->ID, $type);
            if (!$expiration) continue;

            $days_until_expiration = (strtotime($expiration) - time()) / DAY_IN_SECONDS;
            $notifications_sent = get_post_meta($instructor->ID, "_{$type}_notifications_sent", true) ?: [];

            foreach (self::NOTIFICATION_SCHEDULE as $notice => $days) {
                if ($days_until_expiration <= $days && !isset($notifications_sent[$notice])) {
                    $this->send_expiration_notification($instructor, $type, $notice, $days_until_expiration);
                    $notifications_sent[$notice] = current_time('mysql');
                    update_post_meta($instructor->ID, "_{$type}_notifications_sent", $notifications_sent);
                }
            }
        }
    }

    private function get_certification_expiration($instructor_id, $type) {

        // Get all certification dates
        $dates = [];
        
        // Add original date
        $original_date = get_post_meta($instructor_id, "_{$type}_original_date", true);
        if ($original_date) $dates[] = $original_date;
        
        // Add recertification dates
        $recert_dates = get_post_meta($instructor_id, "_{$type}_recert_dates", true) ?: [];
        $dates = array_merge($dates, $recert_dates);
        
        if (empty($dates)) return null;
        
        // Get most recent date
        sort($dates);
        $most_recent = end($dates);
        
		// Calculate expiration (December 31st, 3 years after the certification year)
		$cert_year = date('Y', strtotime($most_recent));
		return date('Y-12-31', strtotime($cert_year . ' +3 years'));
    }

    private function send_expiration_notification($instructor, $type, $notice, $days_remaining) {
        $email = get_post_meta($instructor->ID, '_email', true);
        if (!$email) return;

        $settings = get_option('lsim_notification_settings');
        
        // Prepare email content
        $subject = $this->get_notification_subject($type, $notice);
        $message = $this->get_notification_message($instructor, $type, $notice, $days_remaining);

        // Send to instructor
        if ($settings['notify_instructors']) {
            wp_mail($email, $subject, $message);
        }

        // Send copy to admin
        if ($settings['notify_admin']) {
            $admin_email = $settings['admin_email'] ?? get_option('admin_email');
            wp_mail($admin_email, "Admin Copy: " . $subject, $message);
        }

        // Log the notification
        $this->log_notification([
            'instructor_id' => $instructor->ID,
            'type' => $type,
            'notice' => $notice,
            'days_remaining' => $days_remaining,
            'email_sent' => current_time('mysql')
        ]);
    }

    private function get_notification_subject($type, $notice) {
        $subjects = [
            'first_notice' => ucfirst($type) . ' Rescue Certification - 6 Month Notice',
            'second_notice' => ucfirst($type) . ' Rescue Certification - 3 Month Notice',
            'urgent_notice' => 'URGENT: ' . ucfirst($type) . ' Rescue Certification Expiring Soon',
            'final_notice' => 'FINAL NOTICE: ' . ucfirst($type) . ' Rescue Certification Expiration'
        ];

        return $subjects[$notice] ?? 'Certification Notice';
    }

    private function get_notification_message($instructor, $type, $notice, $days_remaining) {
        $template = $this->get_notification_template($notice);
        
        $replacements = [
            '{instructor_name}' => $instructor->post_title,
            '{certification_type}' => ucfirst($type) . ' Rescue',
            '{days_remaining}' => floor($days_remaining),
            '{expiration_date}' => date('F j, Y', strtotime("+{$days_remaining} days")),
            '{login_url}' => wp_login_url(),
            '{site_name}' => get_bloginfo('name')
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    private function get_notification_template($notice) {
        $templates = [
            'first_notice' => "
                Dear {instructor_name},

                This is a reminder that your {certification_type} certification will expire in approximately 6 months, on {expiration_date}.

                To maintain your certification status, please ensure you have completed the required number of courses and maintain all necessary qualifications.

                If you have any questions about maintaining your certification, please contact us.

                Best regards,
                {site_name}
            ",
            
            'second_notice' => "
                Dear {instructor_name},

                Your {certification_type} certification will expire in 3 months, on {expiration_date}.

                Please ensure all certification requirements are met before the expiration date to maintain your active status.

                Best regards,
                {site_name}
            ",
            
            'urgent_notice' => "
                Dear {instructor_name},

                IMPORTANT: Your {certification_type} certification will expire in {days_remaining} days, on {expiration_date}.

                Immediate action is required to maintain your certification status.

                Best regards,
                {site_name}
            ",
            
            'final_notice' => "
                Dear {instructor_name},

                URGENT: Your {certification_type} certification will expire in {days_remaining} days, on {expiration_date}.

                Please contact us immediately regarding your certification status.

                Best regards,
                {site_name}
            "
        ];

        return $templates[$notice] ?? $templates['first_notice'];
    }

    private function log_notification($data) {
        $log = get_option('lsim_notification_log', []);
        array_unshift($log, $data);
        $log = array_slice($log, 0, 1000); // Keep last 1000 notifications
        update_option('lsim_notification_log', $log);
    }

    public function display_admin_notices() {
        global $pagenow, $typenow;
        
        if ($typenow !== 'instructor') return;

        // Show expiring certifications notice
        $expiring_soon = $this->get_expiring_certifications();
        if (!empty($expiring_soon)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php echo count($expiring_soon); ?> instructor certifications are expiring within 30 days. 
                    <a href="<?php echo admin_url('edit.php?post_type=instructor&page=expiring-certifications'); ?>">
                        View details
                    </a>
                </p>
            </div>
            <?php
        }

        // Show unrecognized submissions notice
        $unrecognized = get_option('lsim_unrecognized_submissions', []);
        if (!empty($unrecognized)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php echo count($unrecognized); ?> course completion submissions from unrecognized instructors. 
                    <a href="<?php echo admin_url('edit.php?post_type=instructor&page=unrecognized-submissions'); ?>">
                        Review submissions
                    </a>
                </p>
            </div>
            <?php
        }
    }

    private function get_expiring_certifications() {
        global $wpdb;
        $thirty_days_from_now = date('Y-m-d', strtotime('+30 days'));
        
        $expiring = [];
        $instructors = get_posts([
            'post_type' => 'instructor',
            'posts_per_page' => -1
        ]);

        foreach ($instructors as $instructor) {
            foreach (['ice', 'water'] as $type) {
                $expiration = $this->get_certification_expiration($instructor->ID, $type);
                if ($expiration && $expiration <= $thirty_days_from_now) {
                    $expiring[] = [
                        'instructor_id' => $instructor->ID,
                        'type' => $type,
                        'expiration' => $expiration
                    ];
                }
            }
        }

        return $expiring;
    }

    private function send_admin_summary() {
        $settings = get_option('lsim_notification_settings');
        if (!$settings['notify_admin']) return;

        $admin_email = $settings['admin_email'] ?? get_option('admin_email');
        $expiring = $this->get_expiring_certifications();
        $unrecognized = get_option('lsim_unrecognized_submissions', []);

        if (empty($expiring) && empty($unrecognized)) return;

        $message = "Daily Instructor Management Summary\n\n";

        if (!empty($expiring)) {
            $message .= "Expiring Certifications:\n";
            foreach ($expiring as $cert) {
                $instructor = get_post($cert['instructor_id']);
                $message .= sprintf(
                    "- %s: %s Rescue expires %s\n",
                    $instructor->post_title,
                    ucfirst($cert['type']),
                    date('M j, Y', strtotime($cert['expiration']))
                );
            }
            $message .= "\n";
        }

        if (!empty($unrecognized)) {
            $message .= "Unrecognized Submissions:\n";
            foreach ($unrecognized as $submission) {
                $message .= sprintf(
                    "- %s (%s Rescue) submitted on %s\n",
                    $submission['email'],
                    ucfirst($submission['type']),
                    date('M j, Y', strtotime($submission['date']))
                );
            }
        }

        wp_mail(
            $admin_email,
            'Daily Instructor Management Summary - ' . date('M j, Y'),
            $message
        );
    }
}