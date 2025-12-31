/**
 * Profile Manager Frontend
 *
 * Handles AJAX form submissions for profile editing.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        
        /**
         * Show message.
         */
        function showMessage(message, type) {
            const $messagesContainer = $('.fb-profile-messages');
            const messageClass = type === 'success' ? 'fb-message-success' : 'fb-message-error';
            
            $messagesContainer
                .removeClass('fb-message-success fb-message-error')
                .addClass(messageClass)
                .html('<p>' + message + '</p>')
                .fadeIn();

            // Scroll to message
            $('html, body').animate({
                scrollTop: $messagesContainer.offset().top - 100
            }, 300);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function () {
                    $messagesContainer.fadeOut();
                }, 5000);
            }
        }

        /**
         * Handle profile form submission.
         */
        $('#fb-profile-form').on('submit', function (e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Disable submit button
            $submitBtn.prop('disabled', true).text('Updating...');

            $.ajax({
                url: fieldsBrightProfile.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fields_bright_update_profile',
                    nonce: fieldsBrightProfile.nonce,
                    first_name: $('#first_name').val(),
                    last_name: $('#last_name').val(),
                    user_email: $('#user_email').val(),
                    phone: $('#phone').val(),
                    description: $('#description').val()
                },
                success: function (response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        /**
         * Handle password change form submission.
         */
        $('#fb-password-form').on('submit', function (e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Client-side validation
            const newPassword = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();

            if (newPassword !== confirmPassword) {
                showMessage('New password and confirmation do not match.', 'error');
                return;
            }

            if (newPassword.length < 8) {
                showMessage('Password must be at least 8 characters long.', 'error');
                return;
            }

            // Disable submit button
            $submitBtn.prop('disabled', true).text('Changing Password...');

            $.ajax({
                url: fieldsBrightProfile.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fields_bright_change_password',
                    nonce: fieldsBrightProfile.nonce,
                    current_password: $('#current_password').val(),
                    new_password: newPassword,
                    confirm_password: confirmPassword
                },
                success: function (response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        $form[0].reset();
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        /**
         * Password strength indicator.
         */
        $('#new_password').on('input', function () {
            const password = $(this).val();
            const $indicator = $('#password-strength');

            // Remove existing indicator if present
            $indicator.remove();

            if (password.length === 0) {
                return;
            }

            let strength = 0;
            let strengthText = '';
            let strengthClass = '';

            // Check length
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;

            // Check complexity
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;

            // Determine strength level
            if (strength <= 2) {
                strengthText = 'Weak';
                strengthClass = 'fb-strength-weak';
            } else if (strength <= 3) {
                strengthText = 'Medium';
                strengthClass = 'fb-strength-medium';
            } else {
                strengthText = 'Strong';
                strengthClass = 'fb-strength-strong';
            }

            // Add indicator
            $(this).after(
                '<div id="password-strength" class="fb-password-strength ' + strengthClass + '">' +
                'Password Strength: <span>' + strengthText + '</span>' +
                '</div>'
            );
        });
    });
})(jQuery);

