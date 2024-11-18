<?php
if (!defined('ABSPATH')) exit;

class LSIM_Email_Templates {
    private static $default_styles = "
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f5f5f5; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #fff; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .alert { background: #fff3cd; color: #856404; padding: 15px; margin-bottom: 20px; }
        .button { display: inline-block; padding: 10px 20px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 3px; }
    ";

    public static function get_template($type, $data) {
        $template = self::get_template_content($type);
        return self::apply_template($template, $data);
    }

    private static function get_template_content($type) {
        $templates = [
            'certification_expiring_180' => [
                'subject' => '{certification_type} Certification - 6 Month Expiration Notice',
                'body' => self::get_180_day_template()
            ],
            'certification_expiring_90' => [
                'subject' => '{certification_type} Certification - 3 Month Expiration Notice',
                'body' => self::get_90_day_template()
            ],
            'certification_expiring_30' => [
                'subject' => 'URGENT: {certification_type} Certification Expiring Soon',
                'body' => self::get_30_day_template()
            ],
            'certification_expiring_7' => [
                'subject' => 'FINAL NOTICE: {certification_type} Certification Expiration',
                'body' => self::get_7_day_template()
            ],
            'certification_expired' => [
                'subject' => '{certification_type} Certification Has Expired',
                'body' => self::get_expired_template()
            ],
            'teaching_requirement' => [
                'subject' => '{certification_type} Teaching Requirement Notice',
                'body' => self::get_teaching_requirement_template()
            ],
            'admin_summary' => [
                'subject' => 'Daily Certification Status Summary',
                'body' => self::get_admin_summary_template()
            ],
            'unrecognized_submission' => [
                'subject' => 'Unrecognized Instructor Submission',
                'body' => self::get_unrecognized_submission_template()
            ]
        ];

        return $templates[$type] ?? $templates['certification_expiring_180'];
    }

    private static function get_180_day_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <h2>Certification Expiration Notice</h2>
                </div>
                
                <div class="content">
                    <p>Dear {instructor_name},</p>
                    
                    <p>This is a reminder that your {certification_type} certification will expire in approximately 6 months on <strong>{expiration_date}</strong>.</p>
                    
                    <p>To maintain your certification status, please ensure you have:</p>
                    <ul>
                        <li>Taught at least {required_courses} courses within the past three years</li>
                        <li>Maintained all required qualifications</li>
                        <li>Kept your contact information up to date</li>
                    </ul>
                    
                    <p>Current Status:</p>
                    <ul>
                        <li>Courses Taught: {courses_taught}/{required_courses}</li>
                        <li>Days Until Expiration: {days_remaining}</li>
                    </ul>

                    <p>Please contact us if you have any questions about maintaining your certification.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_90_day_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <h2>Important Certification Notice</h2>
                </div>
                
                <div class="content">
                    <div class="alert">
                        <strong>Important Notice:</strong> Your {certification_type} certification will expire in 3 months on <strong>{expiration_date}</strong>.
                    </div>
                    
                    <p>Dear {instructor_name},</p>
                    
                    <p>Please review your certification requirements:</p>
                    <ul>
                        <li>Required Courses: {required_courses}</li>
                        <li>Your Courses Taught: {courses_taught}</li>
                        <li>Days Until Expiration: {days_remaining}</li>
                    </ul>
                    
                    <p>Action Required: Please ensure all requirements are met before your certification expires.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_30_day_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header" style="background: #fff3cd;">
                    <h2 style="color: #856404;">Urgent Certification Notice</h2>
                </div>
                
                <div class="content">
                    <p>Dear {instructor_name},</p>
                    
                    <div class="alert">
                        <strong>URGENT:</strong> Your {certification_type} certification will expire in {days_remaining} days on <strong>{expiration_date}</strong>.
                    </div>
                    
                    <p>Current Status:</p>
                    <ul>
                        <li>Courses Taught: {courses_taught}/{required_courses}</li>
                        <li>Days Until Expiration: {days_remaining}</li>
                    </ul>
                    
                    <p><strong>Immediate action is required</strong> to maintain your certification status.</p>
                    
                    <p>Please contact us immediately if you need assistance with your certification renewal.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_7_day_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header" style="background: #dc3232; color: white;">
                    <h2>Final Certification Notice</h2>
                </div>
                
                <div class="content">
                    <p>Dear {instructor_name},</p>
                    
                    <div class="alert" style="background: #dc3232; color: white;">
                        <strong>FINAL NOTICE:</strong> Your {certification_type} certification will expire in 7 days on <strong>{expiration_date}</strong>.
                    </div>
                    
                    <p>If you have not already done so, please contact us immediately regarding your certification status.</p>
                    
                    <p>Current Status:</p>
                    <ul>
                        <li>Courses Taught: {courses_taught}/{required_courses}</li>
                        <li>Days Until Expiration: {days_remaining}</li>
                    </ul>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_expired_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header" style="background: #dc3232; color: white;">
                    <h2>Certification Expired</h2>
                </div>
                
                <div class="content">
                    <p>Dear {instructor_name},</p>
                    
                    <div class="alert" style="background: #dc3232; color: white;">
                        Your {certification_type} certification has expired as of <strong>{expiration_date}</strong>.
                    </div>
                    
                    <p><strong>Important Notice:</strong></p>
                    <ul>
                        <li>You are no longer authorized to teach {certification_type} courses</li>
                        <li>Final Course Count: {courses_taught}/{required_courses}</li>
                    </ul>
                    
                    <p>Please contact us immediately to discuss your certification status and renewal options.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_teaching_requirement_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header" style="background: #fff3cd;">
                    <h2>Teaching Requirement Notice</h2>
                </div>
                
                <div class="content">
                    <p>Dear {instructor_name},</p>
                    
                    <p>This is a reminder regarding your {certification_type} teaching requirements.</p>
                    
                    <p>Current Status:</p>
                    <ul>
                        <li>Courses Taught: {courses_taught}/{required_courses}</li>
                        <li>Certification Expires: {expiration_date}</li>
                        <li>Days Remaining: {days_remaining}</li>
                    </ul>
                    
                    <div class="alert">
                        To maintain your certification, you must teach at least {required_courses} courses before your certification expires.
                    </div>
                    
                    <p>Please contact us if you need assistance finding teaching opportunities.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_admin_summary_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <h2>Daily Certification Summary</h2>
                    <p>{current_date}</p>
                </div>
                
                <div class="content">
                    <h3>Notifications Sent Today:</h3>
                    {daily_notifications}
                    
                    <h3>Certification Status Overview:</h3>
                    {certification_summary}
                    
                    <h3>Teaching Requirements Status:</h3>
                    {teaching_summary}
                </div>
                
                <div class="footer">
                    <p>This is an automated summary from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function get_unrecognized_submission_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>' . self::$default_styles . '</style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <h2>Unrecognized Instructor Submission</h2>
                </div>
                
                <div class="content">
                    <div class="alert">
                        A course completion form was submitted by an unrecognized instructor email: {instructor_email}
                    </div>
                    
                    <p><strong>Course Details:</strong></p>
                    <ul>
                        <li>Course Type: {course_type}</li>
                        <li>Date: {course_date}</li>
                        <li>Location: {course_location}</li>
                    </ul>
                    
                    <p>Please review this submission and either:</p>
                    <ol>
                        <li>Create a new instructor record with this email</li>
                        <li>Update the existing instructor\'s email</li>
                    </ol>
                    
                    <p><a href="{form_link}" class="button">View Form Entry</a></p>
                </div>
                
                <div class="footer">
                    <p>This is an automated notification from Lifesaving Resources.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private static function apply_template($template, $data) {
        // Replace all placeholders with actual values
        foreach ($data as $key => $value) {
            $template['subject'] = str_replace('{' . $key . '}', $value, $template['subject']);
            $template['body'] = str_replace('{' . $key . '}', $value, $template['body']);
        }

        return $template;
    }
}