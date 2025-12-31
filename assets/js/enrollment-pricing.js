/**
 * Enrollment Pricing Selection
 *
 * Handles frontend pricing option selection and enrollment button updates.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Enrollment Module
     */
    var FieldsBrightEnrollment = {
        /**
         * Configuration
         */
        config: {
            selectors: {
                wrapper: '.enrollment-button-wrapper',
                select: '.enrollment-pricing-select',
                button: '.enrollment-button',
                errorContainer: '.enrollment-error'
            },
            classes: {
                loading: 'enrollment-loading',
                disabled: 'enrollment-disabled'
            }
        },

        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
            this.initPricingSelects();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Handle pricing select changes
            $(document).on('change', this.config.selectors.select, function() {
                self.handlePricingChange($(this));
            });

            // Handle enrollment button clicks
            $(document).on('click', this.config.selectors.button, function(e) {
                self.handleEnrollmentClick(e, $(this));
            });
        },

        /**
         * Initialize pricing selects on page load
         */
        initPricingSelects: function() {
            var self = this;

            $(this.config.selectors.select).each(function() {
                self.updateButtonUrl($(this));
            });
        },

        /**
         * Handle pricing option change
         *
         * @param {jQuery} $select The select element
         */
        handlePricingChange: function($select) {
            this.updateButtonUrl($select);
            this.updatePriceDisplay($select);
        },

        /**
         * Update the enrollment button URL with selected pricing
         *
         * @param {jQuery} $select The select element
         */
        updateButtonUrl: function($select) {
            var $wrapper = $select.closest(this.config.selectors.wrapper);
            var $button = $wrapper.find(this.config.selectors.button);
            var workshopId = $select.data('workshop-id');
            var pricingOption = $select.val();
            var baseUrl = $button.data('base-url') || (window.fieldsBrightEnrollment ? window.fieldsBrightEnrollment.enrollUrl : '/enroll/');

            // Build new URL
            var newUrl = baseUrl + '?workshop=' + workshopId;
            
            if (pricingOption) {
                newUrl += '&pricing=' + encodeURIComponent(pricingOption);
            }

            $button.attr('href', newUrl);
        },

        /**
         * Update price display when option changes
         *
         * @param {jQuery} $select The select element
         */
        updatePriceDisplay: function($select) {
            var $wrapper = $select.closest(this.config.selectors.wrapper);
            var $selectedOption = $select.find(':selected');
            var price = $selectedOption.data('price');

            // Update any price display elements
            $wrapper.find('.enrollment-price-display').text('$' + parseFloat(price).toFixed(2));
        },

        /**
         * Handle enrollment button click
         *
         * @param {Event} e Click event
         * @param {jQuery} $button The button element
         */
        handleEnrollmentClick: function(e, $button) {
            // If there's a pricing select, make sure an option is selected
            var $wrapper = $button.closest(this.config.selectors.wrapper);
            var $select = $wrapper.find(this.config.selectors.select);

            if ($select.length && !$select.val()) {
                e.preventDefault();
                this.showError($wrapper, 'Please select a pricing option.');
                return;
            }

            // Add loading state
            $button.addClass(this.config.classes.loading);
            
            // The default behavior will follow the href
        },

        /**
         * Show error message
         *
         * @param {jQuery} $wrapper The wrapper element
         * @param {string} message Error message
         */
        showError: function($wrapper, message) {
            var $error = $wrapper.find(this.config.selectors.errorContainer);

            if (!$error.length) {
                $error = $('<div class="enrollment-error"></div>');
                $wrapper.prepend($error);
            }

            $error.text(message).show();

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $error.fadeOut();
            }, 5000);
        },

        /**
         * Handle AJAX enrollment (optional, for SPA-like experience)
         *
         * @param {int} workshopId Workshop ID
         * @param {string} pricingOption Selected pricing option
         */
        ajaxEnrollment: function(workshopId, pricingOption) {
            var self = this;

            if (!window.fieldsBrightEnrollment || !window.fieldsBrightEnrollment.ajaxUrl) {
                console.error('AJAX enrollment not configured');
                return;
            }

            $.ajax({
                url: window.fieldsBrightEnrollment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fields_bright_enroll',
                    workshop_id: workshopId,
                    pricing_option: pricingOption,
                    nonce: window.fieldsBrightEnrollment.nonce
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        self.showError(
                            $('[data-workshop-id="' + workshopId + '"]').closest(self.config.selectors.wrapper),
                            response.data.message || 'An error occurred.'
                        );
                    }
                },
                error: function() {
                    self.showError(
                        $('[data-workshop-id="' + workshopId + '"]').closest(self.config.selectors.wrapper),
                        'An error occurred. Please try again.'
                    );
                }
            });
        }
    };

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        FieldsBrightEnrollment.init();
    });

    /**
     * Expose to global scope for external access
     */
    window.FieldsBrightEnrollment = FieldsBrightEnrollment;

})(jQuery);

