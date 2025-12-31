<?php
/**
 * Cart Manager
 *
 * Core cart operations for the enrollment system.
 * Handles adding, removing, validating cart items and checkout.
 *
 * @package FieldsBright\Enrollment\Cart
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Cart;

use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class CartManager
 *
 * Manages cart operations including add, remove, validate, and checkout.
 *
 * @since 1.1.0
 */
class CartManager
{
    /**
     * Cart storage instance.
     *
     * @var CartStorage
     */
    private CartStorage $storage;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param CartStorage|null $storage Optional cart storage instance.
     */
    public function __construct(?CartStorage $storage = null)
    {
        $this->storage = $storage ?? new CartStorage();
        $this->logger = Logger::instance();
    }

    /**
     * Get the cart storage instance.
     *
     * @return CartStorage
     */
    public function get_storage(): CartStorage
    {
        return $this->storage;
    }

    /**
     * Get all cart items.
     *
     * @return array Cart items.
     */
    public function get_cart(): array
    {
        $user_id = get_current_user_id();
        
        // If user is logged in, use their cart.
        if ($user_id) {
            return $this->storage->load_from_user($user_id);
        }
        
        return $this->storage->get_cart_data();
    }

    /**
     * Get cart item count.
     *
     * @return int
     */
    public function get_count(): int
    {
        return count($this->get_cart());
    }

    /**
     * Check if cart is empty.
     *
     * @return bool
     */
    public function is_empty(): bool
    {
        return $this->get_count() === 0;
    }

    /**
     * Add item to cart.
     *
     * @param int    $workshop_id    Workshop post ID.
     * @param string $pricing_option Pricing option ID.
     *
     * @return array{success: bool, message: string, cart: array}
     */
    public function add_to_cart(int $workshop_id, string $pricing_option = ''): array
    {
        $this->logger->debug('Adding to cart', [
            'workshop_id' => $workshop_id,
            'pricing_option' => $pricing_option,
        ]);

        // Validate workshop.
        $validation = $this->validate_workshop($workshop_id);
        if (! $validation['valid']) {
            $this->logger->warning('Cart add failed: validation', [
                'workshop_id' => $workshop_id,
                'error' => $validation['error'],
            ]);
            return [
                'success' => false,
                'message' => $validation['error'],
                'cart'    => $this->get_cart(),
            ];
        }

        $cart = $this->get_cart();

        // Check if already in cart.
        foreach ($cart as $item) {
            if ($item['workshop_id'] === $workshop_id) {
                $this->logger->debug('Item already in cart', ['workshop_id' => $workshop_id]);
                return [
                    'success' => false,
                    'message' => __('This workshop is already in your cart.', 'fields-bright-enrollment'),
                    'cart'    => $cart,
                ];
            }
        }

        // Get price.
        $price = WorkshopMetaBox::get_effective_price($workshop_id, $pricing_option);
        $workshop = get_post($workshop_id);

        // Add to cart.
        $cart[] = [
            'workshop_id'    => $workshop_id,
            'workshop_title' => $workshop ? $workshop->post_title : '',
            'pricing_option' => $pricing_option,
            'price'          => $price,
            'added_at'       => current_time('mysql'),
        ];

        $this->save_cart($cart);

        $this->logger->info('Item added to cart', [
            'workshop_id' => $workshop_id,
            'price' => $price,
            'cart_count' => count($cart),
        ]);

        return [
            'success' => true,
            'message' => __('Workshop added to cart.', 'fields-bright-enrollment'),
            'cart'    => $cart,
        ];
    }

    /**
     * Remove item from cart.
     *
     * @param int $workshop_id Workshop post ID to remove.
     *
     * @return array{success: bool, message: string, cart: array}
     */
    public function remove_from_cart(int $workshop_id): array
    {
        $this->logger->debug('Removing from cart', ['workshop_id' => $workshop_id]);

        $cart = $this->get_cart();
        $found = false;

        foreach ($cart as $key => $item) {
            if ($item['workshop_id'] === $workshop_id) {
                unset($cart[$key]);
                $found = true;
                break;
            }
        }

        if (! $found) {
            $this->logger->warning('Item not found in cart', ['workshop_id' => $workshop_id]);
            return [
                'success' => false,
                'message' => __('Item not found in cart.', 'fields-bright-enrollment'),
                'cart'    => $cart,
            ];
        }

        // Re-index array.
        $cart = array_values($cart);
        $this->save_cart($cart);

        $this->logger->info('Item removed from cart', [
            'workshop_id' => $workshop_id,
            'cart_count' => count($cart),
        ]);

        return [
            'success' => true,
            'message' => __('Item removed from cart.', 'fields-bright-enrollment'),
            'cart'    => $cart,
        ];
    }

    /**
     * Update cart item.
     *
     * @param int    $workshop_id    Workshop post ID.
     * @param string $pricing_option New pricing option.
     *
     * @return array{success: bool, message: string, cart: array}
     */
    public function update_cart_item(int $workshop_id, string $pricing_option): array
    {
        $cart = $this->get_cart();
        $found = false;

        foreach ($cart as $key => $item) {
            if ($item['workshop_id'] === $workshop_id) {
                $price = WorkshopMetaBox::get_effective_price($workshop_id, $pricing_option);
                $cart[$key]['pricing_option'] = $pricing_option;
                $cart[$key]['price'] = $price;
                $found = true;
                break;
            }
        }

        if (! $found) {
            return [
                'success' => false,
                'message' => __('Item not found in cart.', 'fields-bright-enrollment'),
                'cart'    => $cart,
            ];
        }

        $this->save_cart($cart);

        return [
            'success' => true,
            'message' => __('Cart updated.', 'fields-bright-enrollment'),
            'cart'    => $cart,
        ];
    }

    /**
     * Clear the cart.
     *
     * @return array{success: bool, message: string, cart: array}
     */
    public function clear_cart(): array
    {
        $user_id = get_current_user_id();
        $this->logger->info('Clearing cart', ['user_id' => $user_id]);
        
        if ($user_id) {
            $this->storage->clear_user_cart($user_id);
        } else {
            $this->storage->clear_cart_data();
        }

        return [
            'success' => true,
            'message' => __('Cart cleared.', 'fields-bright-enrollment'),
            'cart'    => [],
        ];
    }

    /**
     * Get cart total.
     *
     * @return float
     */
    public function get_cart_total(): float
    {
        $cart = $this->get_cart();
        $total = 0.0;

        foreach ($cart as $item) {
            $total += (float) ($item['price'] ?? 0);
        }

        return $total;
    }

    /**
     * Validate all cart items.
     *
     * @return array{valid: bool, errors: array, cart: array}
     */
    public function validate_cart(): array
    {
        $cart = $this->get_cart();
        $errors = [];
        $valid_items = [];

        foreach ($cart as $item) {
            $validation = $this->validate_workshop($item['workshop_id']);
            
            if (! $validation['valid']) {
                $errors[] = [
                    'workshop_id' => $item['workshop_id'],
                    'workshop_title' => $item['workshop_title'] ?? '',
                    'error' => $validation['error'],
                ];
            } else {
                $valid_items[] = $item;
            }
        }

        // Update cart to only contain valid items.
        if (count($valid_items) !== count($cart)) {
            $this->save_cart($valid_items);
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'cart'   => $valid_items,
        ];
    }

    /**
     * Validate a workshop for cart addition.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return array{valid: bool, error?: string}
     */
    private function validate_workshop(int $workshop_id): array
    {
        $workshop = get_post($workshop_id);

        if (! $workshop || $workshop->post_status !== 'publish') {
            return [
                'valid' => false,
                'error' => __('Workshop not found or not available.', 'fields-bright-enrollment'),
            ];
        }

        if (! WorkshopMetaBox::is_checkout_enabled($workshop_id)) {
            return [
                'valid' => false,
                'error' => __('Online enrollment is not available for this workshop.', 'fields-bright-enrollment'),
            ];
        }

        $can_enroll = WorkshopMetaBox::can_enroll($workshop_id);
        if (! $can_enroll['allowed'] && ! $can_enroll['waitlist']) {
            return [
                'valid' => false,
                'error' => $can_enroll['reason'],
            ];
        }

        return ['valid' => true];
    }

    /**
     * Save cart data.
     *
     * @param array $cart Cart data.
     *
     * @return bool
     */
    private function save_cart(array $cart): bool
    {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return $this->storage->save_to_user($user_id, $cart);
        }
        
        return $this->storage->save_cart_data($cart);
    }

    /**
     * Get cart for REST API response.
     *
     * @return array Formatted cart data for API.
     */
    public function get_cart_for_api(): array
    {
        $cart = $this->get_cart();
        $items = [];

        foreach ($cart as $item) {
            $workshop_id = $item['workshop_id'];
            $workshop = get_post($workshop_id);
            
            $items[] = [
                'workshop_id'     => $workshop_id,
                'workshop_title'  => $workshop ? $workshop->post_title : $item['workshop_title'],
                'workshop_url'    => $workshop ? get_permalink($workshop_id) : '',
                'pricing_option'  => $item['pricing_option'],
                'price'           => (float) $item['price'],
                'price_formatted' => '$' . number_format((float) $item['price'], 2),
                'added_at'        => $item['added_at'],
            ];
        }

        return [
            'items'           => $items,
            'count'           => count($items),
            'total'           => $this->get_cart_total(),
            'total_formatted' => '$' . number_format($this->get_cart_total(), 2),
        ];
    }

    /**
     * Merge guest cart with user cart on login.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return void
     */
    public function merge_on_login(int $user_id): void
    {
        // Get guest cart.
        $guest_cart = $this->storage->get_cart_data();
        
        if (empty($guest_cart)) {
            return;
        }

        // Get user cart.
        $user_cart = $this->storage->load_from_user($user_id);

        // Merge carts (guest items take precedence for duplicates).
        $merged = $user_cart;
        $existing_ids = array_column($user_cart, 'workshop_id');

        foreach ($guest_cart as $item) {
            if (! in_array($item['workshop_id'], $existing_ids, true)) {
                $merged[] = $item;
            }
        }

        // Save merged cart to user.
        $this->storage->save_to_user($user_id, $merged);

        // Clear guest cart.
        $this->storage->clear_cart_data();
    }
}

