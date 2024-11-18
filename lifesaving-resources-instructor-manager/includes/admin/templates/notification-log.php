<?php
if (!defined('ABSPATH')) exit;

class LSIM_Notification_Log {
    public static function render($log = null) {
        if ($log === null) {
            $log = get_option('lsim_notification_log', []);
        }
        ?>
        <div class="notification-log-wrapper">
            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="notification-type-filter">
                        <option value="">All Types</option>
                        <option value="ice">Ice Rescue</option>
                        <option value="water">Water Rescue</option>
                    </select>

                    <select id="notification-status-filter">
                        <option value="">All Statuses</option>
                        <option value="expiring">Expiration Notice</option>
                        <option value="expired">Expired</option>
                        <option value="teaching">Teaching Requirement</option>
                    </select>

                    <input type="date" id="date-from" placeholder="From Date">
                    <input type="date" id="date-to" placeholder="To Date">

                    <button type="button" class="button" id="apply-filters">Apply Filters</button>
                    <button type="button" class="button" id="export-log">Export Log</button>
                </div>

                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo count($log); ?> items</span>
                </div>
            </div>

            <!-- Log Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-date">Date/Time</th>
                        <th class="column-instructor">Instructor</th>
                        <th class="column-type">Type</th>
                        <th class="column-notice">Notice</th>
                        <th class="column-status">Status</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($log)): ?>
                        <tr>
                            <td colspan="6">No notifications have been sent yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($log as $entry): 
                            $status_class = self::get_status_class($entry);
                            $instructor = get_post($entry['instructor_id']);
                            if (!$instructor) continue;
                            ?>
                            <tr class="notification-entry" 
                                data-type="<?php echo esc_attr($entry['type']); ?>"
                                data-notice="<?php echo esc_attr($entry['notice']); ?>"
                                data-date="<?php echo esc_attr($entry['timestamp']); ?>">
                                
                                <td class="column-date">
                                    <?php echo date('M j, Y g:i a', strtotime($entry['timestamp'])); ?>
                                </td>
                                
                                <td class="column-instructor">
                                    <a href="<?php echo get_edit_post_link($entry['instructor_id']); ?>">
                                        <?php echo esc_html($instructor->post_title); ?>
                                    </a>
                                </td>
                                
                                <td class="column-type">
                                    <?php echo esc_html(ucfirst($entry['type']) . ' Rescue'); ?>
                                </td>
                                
                                <td class="column-notice">
                                    <?php echo self::format_notice_type($entry['notice']); ?>
                                </td>
                                
                                <td class="column-status">
                                    <span class="status-indicator <?php echo $status_class; ?>">
                                        <?php
                                        if (isset($entry['days_remaining'])) {
                                            echo $entry['days_remaining'] > 0 
                                                ? esc_html(floor($entry['days_remaining']) . ' days remaining')
                                                : 'Expired';
                                        }
                                        ?>
                                    </span>
                                </td>
                                
                                <td class="column-actions">
                                    <button type="button" 
                                            class="button view-notification-details"
                                            data-entry='<?php echo esc_attr(json_encode($entry)); ?>'>
                                        View Details
                                    </button>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <button type="button" 
                                                class="button resend-notification"
                                                data-instructor="<?php echo esc_attr($entry['instructor_id']); ?>"
                                                data-type="<?php echo esc_attr($entry['type']); ?>"
                                                data-notice="<?php echo esc_attr($entry['notice']); ?>">
                                            Resend
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="column-date">Date/Time</th>
                        <th class="column-instructor">Instructor</th>
                        <th class="column-type">Type</th>
                        <th class="column-notice">Notice</th>
                        <th class="column-status">Status</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </tfoot>
            </table>

            <!-- Notification Details Modal -->
            <div id="notification-details-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Notification Details</h2>
                    <div class="notification-details"></div>
                </div>
            </div>
        </div>

        <style>
            .notification-log-wrapper .status-indicator {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .status-active { background: #e7f6e7; color: #46b450; }
            .status-warning { background: #fff8e5; color: #ffb900; }
            .status-expired { background: #ffebee; color: #dc3232; }
            
            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                position: relative;
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 600px;
                border-radius: 4px;
            }
            .close {
                position: absolute;
                right: 10px;
                top: 10px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .notification-details {
                margin-top: 20px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Filter functionality
            $('#apply-filters').on('click', function() {
                const type = $('#notification-type-filter').val();
                const status = $('#notification-status-filter').val();
                const dateFrom = $('#date-from').val();
                const dateTo = $('#date-to').val();

                $('.notification-entry').each(function() {
                    const $row = $(this);
                    const rowType = $row.data('type');
                    const rowNotice = $row.data('notice');
                    const rowDate = new Date($row.data('date'));

                    const typeMatch = !type || rowType === type;
                    const statusMatch = !status || rowNotice.includes(status);
                    const dateMatch = (!dateFrom || rowDate >= new Date(dateFrom)) && 
                                    (!dateTo || rowDate <= new Date(dateTo));

                    $row.toggle(typeMatch && statusMatch && dateMatch);
                });
            });

            // View Details Modal
            $('.view-notification-details').on('click', function() {
                const data = $(this).data('entry');
                const modal = $('#notification-details-modal');
                const details = modal.find('.notification-details');

                details.html(`
                    <p><strong>Sent:</strong> ${new Date(data.timestamp).toLocaleString()}</p>
                    <p><strong>Instructor:</strong> ${data.instructor_name}</p>
                    <p><strong>Type:</strong> ${data.type} Rescue</p>
                    <p><strong>Notice:</strong> ${formatNoticeType(data.notice)}</p>
                    <p><strong>Days Remaining:</strong> ${Math.floor(data.days_remaining)}</p>
                    ${data.courses_taught ? `<p><strong>Courses Taught:</strong> ${data.courses_taught}</p>` : ''}
                `);

                modal.show();
            });

            // Close Modal
            $('.close').on('click', function() {
                $('#notification-details-modal').hide();
            });

            // Export Log
            $('#export-log').on('click', function() {
                const data = [];
                const headers = ['Date/Time', 'Instructor', 'Type', 'Notice', 'Status'];

                // Add headers
                data.push(headers);

                // Add visible rows
                $('.notification-entry:visible').each(function() {
                    const $row = $(this);
                    data.push([
                        $row.find('.column-date').text().trim(),
                        $row.find('.column-instructor').text().trim(),
                        $row.find('.column-type').text().trim(),
                        $row.find('.column-notice').text().trim(),
                        $row.find('.column-status').text().trim()
                    ]);
                });

                // Create CSV
                let csvContent = "data:text/csv;charset=utf-8,";
                data.forEach(row => {
                    csvContent += row.join(',') + '\r\n';
                });

                // Download
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', 'notification-log.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });

            // Resend Notification
            $('.resend-notification').on('click', function() {
                const button = $(this);
                const data = {
                    action: 'resend_notification',
                    instructor_id: button.data('instructor'),
                    type: button.data('type'),
                    notice: button.data('notice'),
                    nonce: '<?php echo wp_create_nonce('resend_notification'); ?>'
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        button.prop('disabled', true).text('Sending...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Notification resent successfully');
                        } else {
                            alert('Error resending notification');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Resend');
                    }
                });
            });
        });

        function formatNoticeType(notice) {
            return notice.split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }
        </script>
        <?php
    }

    private static function get_status_class($entry) {
        if ($entry['days_remaining'] <= 0) {
            return 'status-expired';
        } elseif ($entry['days_remaining'] <= 30) {
            return 'status-warning';
        }
        return 'status-active';
    }

    private static function format_notice_type($notice) {
        return ucwords(str_replace('_', ' ', $notice));
    }
}