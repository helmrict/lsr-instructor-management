jQuery(document).ready(function($) {
    const certificationPeriod = window.lsimSettings?.certificationPeriod || 3;

    // Certification Date Handling
    function calculateExpiration(date) {
        if (!date) return null;
        const expirationDate = new Date(date);
        expirationDate.setFullYear(expirationDate.getFullYear() + certificationPeriod);
        return expirationDate.toISOString().split('T')[0];
    }

    function updateCertificationStatus(section) {
        const type = section.data('type');
        const originalDate = section.find('input[name="' + type + '_original_date"]').val();
        const recertDates = section.find('input[name="' + type + '_recert_dates[]"]')
            .map(function() { return $(this).val(); })
            .get()
            .filter(Boolean);

        let allDates = [originalDate, ...recertDates].filter(Boolean);
        
        if (allDates.length > 0) {
            // Sort dates and get most recent
            allDates.sort();
            const mostRecent = allDates[allDates.length - 1];
            const expiration = calculateExpiration(mostRecent);
            
            // Check if certification is active
            const isActive = new Date(expiration) > new Date();
            
            // Update section styling
            section.removeClass('active inactive')
                .addClass(isActive ? 'active' : 'inactive');

            // Update expiration info
            const expirationInfo = section.find('.expiration-info');
            if (expirationInfo.length) {
                expirationInfo.html('Expires: ' + new Date(expiration).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }));
            } else {
                section.find('.certification-dates').append(
                    '<div class="expiration-info">Expires: ' + 
                    new Date(expiration).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    }) + '</div>'
                );
            }
        }
    }

    // Initialize certification sections
    $('.certification-section').each(function() {
        updateCertificationStatus($(this));
    });

    // Add recertification date
    $('.add-recert-date').on('click', function() {
        const type = $(this).data('type');
        const template = `
            <div class="recert-date">
                <label>Recertification Date:</label>
                <input type="date" name="${type}_recert_dates[]" class="recert-date-input">
                <button type="button" class="button remove-recert-date">Remove</button>
            </div>
        `;
        $(`#${type}-recert-dates`).append(template);
    });

    // Remove recertification date
    $(document).on('click', '.remove-recert-date', function() {
        $(this).closest('.recert-date').remove();
        updateCertificationStatus($(this).closest('.certification-section'));
    });

    // Update status when dates change
    $(document).on('change', 'input[type="date"]', function() {
        updateCertificationStatus($(this).closest('.certification-section'));
    });

    // Course History Filtering
    const courseHistoryTable = $('.course-history-wrapper table');
    if (courseHistoryTable.length) {
        $('#course-type-filter, #date-from, #date-to').on('change', function() {
            const typeFilter = $('#course-type-filter').val();
            const dateFrom = $('#date-from').val();
            const dateTo = $('#date-to').val();

            courseHistoryTable.find('tr').each(function() {
                const $row = $(this);
                if ($row.is('th')) return; // Skip header row

                const rowType = $row.find('td:nth-child(2)').text().toLowerCase();
                const rowDate = new Date($row.find('td:first').text());
                
                const typeMatch = !typeFilter || rowType.includes(typeFilter);
                const dateMatch = (!dateFrom || rowDate >= new Date(dateFrom)) && 
                                (!dateTo || rowDate <= new Date(dateTo));

                $row.toggle(typeMatch && dateMatch);
            });
        });
    }

    // Unrecognized Submissions Handling
    $('.dismiss-submission').on('click', function() {
        const button = $(this);
        const row = button.closest('tr');
        
        if (confirm('Are you sure you want to dismiss this submission?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dismiss_unrecognized_submission',
                    entry_id: button.data('entry'),
                    nonce: button.data('nonce')
                },
                beforeSend: function() {
                    button.prop('disabled', true).text('Dismissing...');
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut();
                    } else {
                        alert('Error dismissing submission');
                        button.prop('disabled', false).text('Dismiss');
                    }
                },
                error: function() {
                    alert('Error dismissing submission');
                    button.prop('disabled', false).text('Dismiss');
                }
            });
        }
    });

    // Required Fields Validation
    $('form#post').on('submit', function(e) {
        const requiredFields = ['first_name', 'last_name', 'email'];
        let isValid = true;
        let firstError = null;

        requiredFields.forEach(field => {
            const input = $(`#${field}`);
            if (!input.val().trim()) {
                isValid = false;
                input.addClass('error');
                if (!firstError) firstError = input;
                
                if (!input.next('.error-message').length) {
                    input.after(`<span class="error-message">This field is required</span>`);
                }
            } else {
                input.removeClass('error');
                input.next('.error-message').remove();
            }
        });

        if (!isValid) {
            e.preventDefault();
            firstError.focus();
            return false;
        }

        // Validate email format
        const email = $('#email');
        if (email.val() && !isValidEmail(email.val())) {
            e.preventDefault();
            email.addClass('error');
            if (!email.next('.error-message').length) {
                email.after(`<span class="error-message">Please enter a valid email address</span>`);
            }
            email.focus();
            return false;
        }
    });

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Export functionality
    $('#export-data').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'export_instructor_data',
                nonce: button.data('nonce')
            },
            beforeSend: function() {
                button.prop('disabled', true).text('Exporting...');
            },
            success: function(response) {
                if (response.success) {
                    // Create and trigger download
                    const blob = new Blob([response.data], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'instructor-data.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert('Export failed: ' + response.data);
                }
            },
            error: function() {
                alert('Export failed. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text('Export Data');
            }
        });
    });
});