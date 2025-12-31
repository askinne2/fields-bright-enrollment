/**
 * Refund Admin JavaScript
 *
 * Handles refund processing in the admin panel.
 *
 * @package FieldsBright\Enrollment
 * @since   1.1.0
 */

(function($) {
    'use strict';

    /**
     * Initialize refund functionality.
     */
    function init() {
        $('.fb-refund-button').on('click', handleRefundClick);
    }

    /**
     * Handle refund button click.
     *
     * @param {Event} e Click event.
     */
    function handleRefundClick(e) {
        e.preventDefault();

        const $button = $(this);
        
        // Prevent double-clicks - if already processing, ignore.
        if ($button.prop('disabled') || $button.data('processing')) {
            return;
        }

        const $metabox = $button.closest('.fb-refund-metabox');
        const $message = $metabox.find('.fb-refund-message');
        
        const enrollmentId = $button.data('enrollment-id');
        const amount = $metabox.find('#refund-amount').val();
        const reason = $metabox.find('#refund-reason').val();

        // Confirm refund.
        if (!confirm(fieldsBrightRefund.strings.confirmRefund)) {
            return;
        }

        // Mark as processing and disable button.
        $button.data('processing', true);
        const originalText = $button.text();
        $button.prop('disabled', true).text(fieldsBrightRefund.strings.processing);
        $message.hide();

        // Process refund via AJAX.
        $.ajax({
            url: fieldsBrightRefund.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fields_bright_process_refund',
                nonce: fieldsBrightRefund.nonce,
                enrollment_id: enrollmentId,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    showMessage($message, fieldsBrightRefund.strings.success, 'success');
                    
                    // Reload page after short delay to show updated status.
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage($message, response.data.message || fieldsBrightRefund.strings.error, 'error');
                    $button.data('processing', false).prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showMessage($message, fieldsBrightRefund.strings.error, 'error');
                $button.data('processing', false).prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Show message to user.
     *
     * @param {jQuery} $element Message element.
     * @param {string} message  Message text.
     * @param {string} type     Message type (success or error).
     */
    function showMessage($element, message, type) {
        $element
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .slideDown();
    }

    // Initialize on document ready.
    $(document).ready(init);

})(jQuery);

