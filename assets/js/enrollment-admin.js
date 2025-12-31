/**
 * Enrollment Admin JavaScript
 *
 * Handles admin functionality for the enrollment system.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Module
     */
    var FieldsBrightAdmin = {
        /**
         * Initialize the module
         */
        init: function() {
            this.initWorkshopCheckoutSettings();
            this.initPricingOptions();
        },

        /**
         * Initialize workshop checkout settings
         */
        initWorkshopCheckoutSettings: function() {
            var $enabledCheckbox = $('#workshop_checkout_enabled');
            var $conditionalFields = $('#checkout-conditional-fields');

            if (!$enabledCheckbox.length) {
                return;
            }

            // Toggle conditional fields
            $enabledCheckbox.on('change', function() {
                if ($(this).is(':checked')) {
                    $conditionalFields.slideDown();
                } else {
                    $conditionalFields.slideUp();
                }
            });
        },

        /**
         * Initialize pricing options functionality
         */
        initPricingOptions: function() {
            var self = this;
            var $container = $('#pricing-options-list');
            var $template = $('#pricing-option-template');
            var optionIndex = $container.find('.pricing-option-row').length;

            if (!$container.length) {
                return;
            }

            // Add pricing option
            $('#add-pricing-option').on('click', function() {
                var template = $template.html();
                template = template.replace(/__INDEX__/g, optionIndex);
                $container.append(template);
                optionIndex++;
            });

            // Remove pricing option
            $(document).on('click', '.remove-option-btn', function() {
                $(this).closest('.pricing-option-row').remove();
            });

            // Auto-generate ID from label
            $(document).on('blur', '.pricing-option-label', function() {
                var $row = $(this).closest('.pricing-option-row');
                var $idField = $row.find('.pricing-option-id');
                
                if ($idField.val() === '') {
                    var label = $(this).val();
                    var id = self.generateId(label);
                    $idField.val(id);
                }
            });

            // Validate pricing options JSON
            $('#publish').on('click', function(e) {
                if (!self.validatePricingOptions()) {
                    e.preventDefault();
                    alert('Please ensure all pricing options have a label and price.');
                }
            });
        },

        /**
         * Generate an ID from a label
         *
         * @param {string} label The label to convert
         * @return {string} The generated ID
         */
        generateId: function(label) {
            return label
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
        },

        /**
         * Validate pricing options before save
         *
         * @return {boolean} Whether validation passed
         */
        validatePricingOptions: function() {
            var valid = true;

            $('.pricing-option-row').each(function() {
                var $row = $(this);
                var label = $row.find('.pricing-option-label').val();
                var price = $row.find('.price-input').val();

                // Skip empty rows
                if (!label && !price) {
                    return true; // continue
                }

                // Check that both label and price are filled
                if (!label || !price) {
                    valid = false;
                    $row.css('background', '#fff3cd');
                    return false; // break
                }
            });

            return valid;
        },

        /**
         * Copy text to clipboard
         *
         * @param {string} text Text to copy
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert('Copied to clipboard!');
            }
        }
    };

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        FieldsBrightAdmin.init();
    });

    /**
     * Expose to global scope
     */
    window.FieldsBrightAdmin = FieldsBrightAdmin;

})(jQuery);

