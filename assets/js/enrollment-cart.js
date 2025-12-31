/**
 * Enrollment Cart JavaScript
 *
 * Handles all cart interactions including add to cart, remove,
 * update, and checkout functionality.
 *
 * @package FieldsBright\Enrollment
 * @since   1.1.0
 */

(function($) {
    'use strict';

    /**
     * Cart module.
     */
    const Cart = {
        /**
         * Initialize cart functionality.
         */
        init: function() {
            this.bindEvents();
            this.updateCartCountOnLoad();
        },

        /**
         * Bind event listeners.
         */
        bindEvents: function() {
            // Add to cart buttons.
            $(document).on('click', '[data-add-to-cart]', this.handleAddToCart.bind(this));
            
            // Remove item buttons.
            $(document).on('click', '[data-remove-item]', this.handleRemoveItem.bind(this));
            
            // Clear cart button.
            $(document).on('click', '[data-cart-clear]', this.handleClearCart.bind(this));
            
            // Checkout button.
            $(document).on('click', '[data-cart-checkout]', this.handleCheckout.bind(this));
            
            // Pricing option change.
            $(document).on('change', '[data-pricing-select]', this.handlePricingChange.bind(this));
        },

        /**
         * Handle add to cart click.
         *
         * @param {Event} e Click event.
         */
        handleAddToCart: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $wrapper = $button.closest('[data-add-to-cart-wrapper]');
            const workshopId = $button.data('workshop-id');
            const $pricingSelect = $wrapper.find('[data-pricing-select]');
            const pricingOption = $pricingSelect.length ? $pricingSelect.val() : '';
            
            // Disable button and show loading.
            const originalText = $button.text();
            $button.prop('disabled', true).text(fbCart.strings.adding);
            
            this.addToCart(workshopId, pricingOption)
                .then(response => {
                    if (response.success) {
                        // Update button state.
                        $button.addClass('in-cart')
                               .html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ' + fbCart.strings.added);
                        
                        // Hide pricing select.
                        $wrapper.find('.fb-add-to-cart__options').slideUp();
                        $wrapper.find('.fb-add-to-cart__price').slideUp();
                        
                        // Show view cart link.
                        if (!$wrapper.find('.fb-view-cart-link').length) {
                            $button.after('<a href="' + fbCart.cartPageUrl + '" class="fb-view-cart-link">' + fbCart.strings.viewCart + '</a>');
                        }
                        
                        // Update cart count.
                        this.updateCartCount(response.data.count);
                        
                        // Show notification.
                        this.showNotification(fbCart.strings.added, 'success');
                        
                        // After delay, update button to "In Cart".
                        setTimeout(() => {
                            $button.html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ' + fbCart.strings.inCart);
                        }, 2000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        this.showNotification(response.message || fbCart.strings.error, 'error');
                    }
                })
                .catch(error => {
                    $button.prop('disabled', false).text(originalText);
                    this.showNotification(fbCart.strings.error, 'error');
                    console.error('Add to cart error:', error);
                });
        },

        /**
         * Handle remove item click.
         *
         * @param {Event} e Click event.
         */
        handleRemoveItem: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('[data-cart-item]');
            const workshopId = $button.data('workshop-id');
            
            // Add removing state.
            $item.addClass('removing');
            
            this.removeFromCart(workshopId)
                .then(response => {
                    if (response.success) {
                        // Animate removal.
                        $item.slideUp(300, function() {
                            $(this).remove();
                            
                            // Check if cart is empty.
                            if ($('[data-cart-item]').length === 0) {
                                Cart.refreshCartSummary();
                            }
                        });
                        
                        // Update totals.
                        this.updateCartTotal(response.data.total_formatted);
                        this.updateCartCount(response.data.count);
                        
                        // Update any add-to-cart buttons for this workshop.
                        this.resetAddToCartButton(workshopId);
                        
                        this.showNotification(fbCart.strings.removed, 'success');
                    } else {
                        $item.removeClass('removing');
                        this.showNotification(response.message || fbCart.strings.error, 'error');
                    }
                })
                .catch(error => {
                    $item.removeClass('removing');
                    this.showNotification(fbCart.strings.error, 'error');
                    console.error('Remove from cart error:', error);
                });
        },

        /**
         * Handle clear cart click.
         *
         * @param {Event} e Click event.
         */
        handleClearCart: function(e) {
            e.preventDefault();
            
            if (!confirm(fbCart.strings.confirmClear)) {
                return;
            }
            
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            $button.prop('disabled', true).text(fbCart.strings.processing);
            
            this.clearCart()
                .then(response => {
                    if (response.success) {
                        // Refresh the cart summary.
                        this.refreshCartSummary();
                        this.updateCartCount(0);
                        
                        // Reset all add-to-cart buttons.
                        $('[data-add-to-cart-wrapper]').each(function() {
                            const workshopId = $(this).data('workshop-id');
                            Cart.resetAddToCartButton(workshopId);
                        });
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        this.showNotification(response.message || fbCart.strings.error, 'error');
                    }
                })
                .catch(error => {
                    $button.prop('disabled', false).text(originalText);
                    this.showNotification(fbCart.strings.error, 'error');
                    console.error('Clear cart error:', error);
                });
        },

        /**
         * Handle checkout click.
         *
         * @param {Event} e Click event.
         */
        handleCheckout: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            $button.prop('disabled', true).text(fbCart.strings.processing);
            
            this.checkout()
                .then(response => {
                    // Handle both direct response and wrapped response formats.
                    const checkoutUrl = response.checkout_url || (response.data && response.data.checkout_url);
                    
                    if (response.success && checkoutUrl) {
                        // Redirect to Stripe Checkout.
                        window.location.href = checkoutUrl;
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        this.showNotification(response.message || fbCart.strings.error, 'error');
                    }
                })
                .catch(error => {
                    $button.prop('disabled', false).text(originalText);
                    this.showNotification(fbCart.strings.error, 'error');
                    console.error('Checkout error:', error);
                });
        },

        /**
         * Handle pricing option change.
         *
         * @param {Event} e Change event.
         */
        handlePricingChange: function(e) {
            const $select = $(e.currentTarget);
            const $wrapper = $select.closest('[data-add-to-cart-wrapper]');
            const $priceDisplay = $wrapper.find('.fb-add-to-cart__price');
            const selectedOption = $select.find('option:selected');
            const price = selectedOption.data('price');
            
            if ($priceDisplay.length && price) {
                $priceDisplay.text('$' + parseFloat(price).toFixed(2));
            }
        },

        /**
         * Add item to cart via REST API.
         *
         * @param {number} workshopId Workshop ID.
         * @param {string} pricingOption Pricing option ID.
         * @returns {Promise} API response.
         */
        addToCart: function(workshopId, pricingOption) {
            return $.ajax({
                url: fbCart.restUrl + 'cart/add',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fbCart.restNonce);
                },
                data: {
                    workshop_id: workshopId,
                    pricing_option: pricingOption || ''
                }
            });
        },

        /**
         * Remove item from cart via REST API.
         *
         * @param {number} workshopId Workshop ID.
         * @returns {Promise} API response.
         */
        removeFromCart: function(workshopId) {
            return $.ajax({
                url: fbCart.restUrl + 'cart/remove/' + workshopId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fbCart.restNonce);
                }
            });
        },

        /**
         * Clear cart via REST API.
         *
         * @returns {Promise} API response.
         */
        clearCart: function() {
            return $.ajax({
                url: fbCart.restUrl + 'cart/clear',
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fbCart.restNonce);
                }
            });
        },

        /**
         * Checkout cart via REST API.
         *
         * @returns {Promise} API response.
         */
        checkout: function() {
            return $.ajax({
                url: fbCart.restUrl + 'cart/checkout',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fbCart.restNonce);
                }
            });
        },

        /**
         * Update cart count display.
         *
         * @param {number} count New count.
         */
        updateCartCount: function(count) {
            $('[data-cart-count]').text(count);
            
            // Toggle has-items class on cart icon.
            if (count > 0) {
                $('[data-cart-icon]').addClass('has-items');
            } else {
                $('[data-cart-icon]').removeClass('has-items');
            }
        },

        /**
         * Update cart count on page load.
         */
        updateCartCountOnLoad: function() {
            $.ajax({
                url: fbCart.restUrl + 'cart',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fbCart.restNonce);
                }
            }).then(response => {
                if (response.success && response.data) {
                    this.updateCartCount(response.data.count);
                }
            });
        },

        /**
         * Update cart total display.
         *
         * @param {string} total Formatted total.
         */
        updateCartTotal: function(total) {
            $('[data-cart-total]').text(total);
        },

        /**
         * Refresh cart summary (reload via AJAX).
         */
        refreshCartSummary: function() {
            const $summary = $('[data-cart-summary]');
            if (!$summary.length) return;
            
            // Simple reload - could be enhanced with AJAX partial update.
            location.reload();
        },

        /**
         * Reset add to cart button for a workshop.
         *
         * @param {number} workshopId Workshop ID.
         */
        resetAddToCartButton: function(workshopId) {
            const $wrapper = $('[data-add-to-cart-wrapper][data-workshop-id="' + workshopId + '"]');
            if (!$wrapper.length) return;
            
            const $button = $wrapper.find('[data-add-to-cart], .in-cart');
            
            $button.removeClass('in-cart')
                   .prop('disabled', false)
                   .attr('data-add-to-cart', '')
                   .text($wrapper.data('original-text') || 'Add to Cart');
            
            // Show pricing options again.
            $wrapper.find('.fb-add-to-cart__options').slideDown();
            $wrapper.find('.fb-add-to-cart__price').slideDown();
            
            // Remove view cart link.
            $wrapper.find('.fb-view-cart-link').remove();
        },

        /**
         * Show notification toast.
         *
         * @param {string} message Message to display.
         * @param {string} type    Notification type (success, error).
         */
        showNotification: function(message, type) {
            // Remove existing notifications.
            $('.fb-cart-notification').remove();
            
            const $notification = $('<div class="fb-cart-notification fb-cart-notification--' + type + '">' +
                '<span class="fb-cart-notification__message">' + message + '</span>' +
                '<button type="button" class="fb-cart-notification__close">&times;</button>' +
                '</div>');
            
            $('body').append($notification);
            
            // Animate in.
            setTimeout(() => $notification.addClass('visible'), 10);
            
            // Close button.
            $notification.find('.fb-cart-notification__close').on('click', function() {
                $notification.removeClass('visible');
                setTimeout(() => $notification.remove(), 300);
            });
            
            // Auto-hide after 4 seconds.
            setTimeout(() => {
                $notification.removeClass('visible');
                setTimeout(() => $notification.remove(), 300);
            }, 4000);
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        Cart.init();
    });

})(jQuery);

