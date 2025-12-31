/**
 * Waitlist Form JavaScript
 *
 * Handles AJAX submission of the waitlist form.
 *
 * @package FieldsBright\Enrollment
 * @since   1.1.0
 */

(function($) {
    'use strict';

    /**
     * Initialize waitlist forms.
     */
    function initWaitlistForms() {
        $('.fb-waitlist-form__form').on('submit', handleFormSubmit);
    }

    /**
     * Handle form submission.
     *
     * @param {Event} e Submit event.
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        const $form = $(this);
        const $wrapper = $form.closest('.fb-waitlist-form-wrapper');
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.fb-waitlist-form__message');

        // Get form data.
        const workshopId = $form.find('input[name="workshop_id"]').val();
        const name = $form.find('input[name="name"]').val();
        const email = $form.find('input[name="email"]').val();
        const phone = $form.find('input[name="phone"]').val() || '';

        // Validate required fields.
        if (!name || !email) {
            showMessage($message, fieldsBrightWaitlist.strings.error, 'error');
            return;
        }

        // Disable button and show loading.
        const originalText = $button.text();
        $button.prop('disabled', true).text(fieldsBrightWaitlist.strings.submitting);
        $message.hide();

        // Submit via AJAX.
        $.ajax({
            url: fieldsBrightWaitlist.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fields_bright_join_waitlist',
                nonce: fieldsBrightWaitlist.nonce,
                workshop_id: workshopId,
                name: name,
                email: email,
                phone: phone
            },
            success: function(response) {
                if (response.success) {
                    // Show success state.
                    $wrapper.html(getSuccessHTML(response.data.position));
                } else {
                    showMessage($message, response.data.message || fieldsBrightWaitlist.strings.error, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showMessage($message, fieldsBrightWaitlist.strings.error, 'error');
                $button.prop('disabled', false).text(originalText);
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

    /**
     * Get success state HTML.
     *
     * @param {number} position Waitlist position.
     * @returns {string} HTML string.
     */
    function getSuccessHTML(position) {
        return `
            <div class="fb-waitlist-status">
                <div class="fb-waitlist-status__icon">✓</div>
                <h3>You're on the Waitlist!</h3>
                <p>Your position: #${position}</p>
                <p class="fb-waitlist-status__message">We'll notify you when a spot opens up.</p>
            </div>
        `;
    }

    // Initialize on document ready.
    $(document).ready(initWaitlistForms);

})(jQuery);

