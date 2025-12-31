/**
 * Waitlist Meta Box JavaScript
 *
 * Handles interactivity for the waitlist entry edit screen.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 */

(function($) {
    'use strict';

    /**
     * Initialize waitlist meta box functionality.
     */
    function init() {
        handleStatusChange();
        handleResetNotificationToggle();
    }

    /**
     * Handle status dropdown changes.
     */
    function handleStatusChange() {
        const $statusSelect = $('#waitlist_status');
        
        if (!$statusSelect.length) {
            return;
        }

        // Store original value on load.
        const originalStatus = $statusSelect.val();
        
        // Warn if changing from converted/expired.
        $statusSelect.on('change', function() {
            const newStatus = $(this).val();
            
            if ((originalStatus === 'converted' || originalStatus === 'expired') && 
                newStatus !== originalStatus) {
                const confirmed = confirm(
                    'Changing status from "' + originalStatus + '" may affect reporting. Continue?'
                );
                
                if (!confirmed) {
                    $(this).val(originalStatus);
                }
            }
        });
    }

    /**
     * Handle reset notification checkbox toggle.
     */
    function handleResetNotificationToggle() {
        const $resetCheckbox = $('input[name="waitlist_reset_notification"]');
        
        if (!$resetCheckbox.length) {
            return;
        }

        $resetCheckbox.on('change', function() {
            if ($(this).is(':checked')) {
                const confirmed = confirm(
                    'This will reset the notification status, allowing you to send the notification email again. Continue?'
                );
                
                if (!confirmed) {
                    $(this).prop('checked', false);
                }
            }
        });
    }

    // Initialize on document ready.
    $(document).ready(init);

})(jQuery);

