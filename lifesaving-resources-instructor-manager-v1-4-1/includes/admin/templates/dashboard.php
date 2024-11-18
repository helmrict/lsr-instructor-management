<?php
/**
 * Dashboard template for Lifesaving Resources Instructor Manager
 * 
 * @package Lifesaving_Resources
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Verify we have our required variables
if (!isset($ice_instructors) || !isset($water_instructors) || !isset($recent_courses) || !isset($nonce)) {
    return;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="dashboard-widgets-wrap">
        <!-- Instructor Statistics -->
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('Instructor Overview', 'lsim'); ?></span></h2>
            <div class="inside">
                <ul>
                    <li><?php printf(
                        esc_html__('Active Ice Rescue Instructors: %d', 'lsim'),
                        count($ice_instructors)
                    ); ?></li>
                    <li><?php printf(
                        esc_html__('Active Water Rescue Instructors: %d', 'lsim'),
                        count($water_instructors)
                    ); ?></li>
                </ul>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('Recent Activity', 'lsim'); ?></span></h2>
            <div class="inside">
                <?php if ($recent_courses): ?>
                    <ul>
                        <?php foreach ($recent_courses as $course): ?>
                            <li>
                                <?php 
                                printf(
                                    esc_html__('%s completed %s course on %s', 'lsim'),
                                    esc_html($course->instructor_name),
                                    esc_html(ucfirst($course->course_type)),
                                    esc_html(date_i18n(get_option('date_format'), strtotime($course->course_date)))
                                );
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php esc_html_e('No recent course activity.', 'lsim'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('Quick Actions', 'lsim'); ?></span></h2>
            <div class="inside">
                <p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=instructor')); ?>" 
                       class="button button-primary">
                        <?php esc_html_e('Add New Instructor', 'lsim'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=instructor')); ?>" 
                       class="button">
                        <?php esc_html_e('View All Instructors', 'lsim'); ?>
                    </a>
                </p>

                <?php if (current_user_can('manage_options')): ?>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=instructor-settings')); ?>" 
                           class="button">
                            <?php esc_html_e('Manage Settings', 'lsim'); ?>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=certification_type&post_type=instructor')); ?>" 
                           class="button">
                            <?php esc_html_e('Manage Certification Types', 'lsim'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        // Allow other plugins/themes to add their own dashboard widgets
        do_action('lsim_after_dashboard_widgets'); 
        ?>
    </div>
</div>

<style>
    .dashboard-widgets-wrap {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .dashboard-widgets-wrap .postbox {
        margin: 0;
    }
    
    .dashboard-widgets-wrap .inside {
        margin-bottom: 0;
    }
    
    .dashboard-widgets-wrap .button {
        margin: 0 10px 10px 0;
    }
</style>