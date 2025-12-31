<?php
/**
 * Cart Shortcodes
 *
 * Provides shortcodes for cart display including cart icon and cart summary.
 *
 * @package FieldsBright\Enrollment\Shortcodes
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Shortcodes;

use FieldsBright\Enrollment\Cart\CartManager;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class CartShortcodes
 *
 * Handles cart-related shortcodes.
 *
 * @since 1.1.0
 */
class CartShortcodes
{
    /**
     * Cart manager instance.
     *
     * @var CartManager
     */
    private CartManager $cart_manager;

    /**
     * Constructor.
     *
     * @param CartManager|null $cart_manager Optional cart manager instance.
     */
    public function __construct(?CartManager $cart_manager = null)
    {
        $this->cart_manager = $cart_manager ?? new CartManager();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_shortcode('cart_icon', [$this, 'render_cart_icon']);
        add_shortcode('cart_summary', [$this, 'render_cart_summary']);
        add_shortcode('add_to_cart_button', [$this, 'render_add_to_cart_button']);
        
        // Enqueue scripts.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue cart scripts and styles.
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_script(
            'fields-bright-cart',
            get_stylesheet_directory_uri() . '/assets/js/enrollment-cart.js',
            ['jquery'],
            '1.1.0',
            true
        );

        wp_localize_script('fields-bright-cart', 'fbCart', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'restUrl'     => rest_url('fields-bright/v1/'),
            'nonce'       => wp_create_nonce('fields_bright_cart'),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'cartPageUrl' => $this->get_cart_page_url(),
            'strings'     => [
                'adding'       => __('Adding...', 'fields-bright-enrollment'),
                'added'        => __('Added to Cart!', 'fields-bright-enrollment'),
                'inCart'       => __('In Cart', 'fields-bright-enrollment'),
                'removing'     => __('Removing...', 'fields-bright-enrollment'),
                'removed'      => __('Removed', 'fields-bright-enrollment'),
                'error'        => __('Error occurred', 'fields-bright-enrollment'),
                'viewCart'     => __('View Cart', 'fields-bright-enrollment'),
                'processing'   => __('Processing...', 'fields-bright-enrollment'),
                'emptyCart'    => __('Your cart is empty.', 'fields-bright-enrollment'),
                'confirmClear' => __('Are you sure you want to clear your cart?', 'fields-bright-enrollment'),
            ],
        ]);

        // Enqueue cart styles.
        wp_enqueue_style(
            'fields-bright-cart',
            get_stylesheet_directory_uri() . '/assets/css/enrollment-cart.css',
            [],
            '1.1.0'
        );
    }

    /**
     * Get cart page URL.
     *
     * @return string
     */
    private function get_cart_page_url(): string
    {
        // Check if a cart page is set in options.
        $cart_page_id = get_option('fields_bright_cart_page', 0);
        
        if ($cart_page_id) {
            return get_permalink($cart_page_id);
        }

        // Default to /cart/ slug.
        return home_url('/cart/');
    }

    /**
     * Render cart icon shortcode.
     *
     * Usage: [cart_icon show_count="true" link="true"]
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_cart_icon(array $atts = []): string
    {
        $atts = shortcode_atts([
            'show_count' => 'true',
            'link'       => 'true',
            'class'      => '',
            'icon'       => 'bag', // bag, cart, basket
        ], $atts);

        $show_count = filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN);
        $link = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN);
        $count = $this->cart_manager->get_count();
        $cart_url = $this->get_cart_page_url();

        // Select icon SVG.
        $icon_svg = $this->get_icon_svg($atts['icon']);

        $classes = ['fb-cart-icon'];
        if ($atts['class']) {
            $classes[] = $atts['class'];
        }
        if ($count > 0) {
            $classes[] = 'has-items';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-cart-icon>
            <?php if ($link) : ?>
            <a href="<?php echo esc_url($cart_url); ?>" class="fb-cart-icon__link">
            <?php endif; ?>
            
                <span class="fb-cart-icon__icon">
                    <?php echo $icon_svg; ?>
                </span>
                
                <?php if ($show_count) : ?>
                <span class="fb-cart-icon__count" data-cart-count><?php echo esc_html($count); ?></span>
                <?php endif; ?>
            
            <?php if ($link) : ?>
            </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get icon SVG.
     *
     * @param string $icon Icon name.
     *
     * @return string SVG HTML.
     */
    private function get_icon_svg(string $icon): string
    {
        switch ($icon) {
            case 'cart':
                return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
            case 'basket':
                return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h9.234L10.07 1.757a.5.5 0 1 1 .858-.514L14.117 6H19a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h4.883L6.443 1.757a.5.5 0 0 1 .686-.172z"/></svg>';
            case 'bag':
            default:
                return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
        }
    }

    /**
     * Render cart summary shortcode.
     *
     * Usage: [cart_summary show_checkout="true" show_continue="true"]
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_cart_summary(array $atts = []): string
    {
        $atts = shortcode_atts([
            'show_checkout'       => 'true',
            'show_continue'       => 'true',
            'continue_url'        => home_url('/workshops/'),
            'continue_text'       => __('Continue Browsing', 'fields-bright-enrollment'),
            'checkout_text'       => __('Proceed to Checkout', 'fields-bright-enrollment'),
            'empty_message'       => __('Your cart is empty.', 'fields-bright-enrollment'),
            'empty_cta_text'      => __('Browse Workshops', 'fields-bright-enrollment'),
        ], $atts);

        $show_checkout = filter_var($atts['show_checkout'], FILTER_VALIDATE_BOOLEAN);
        $show_continue = filter_var($atts['show_continue'], FILTER_VALIDATE_BOOLEAN);
        
        $cart = $this->cart_manager->get_cart();
        $total = $this->cart_manager->get_cart_total();

        ob_start();
        ?>
        <div class="fb-cart-summary" data-cart-summary>
            <?php if (empty($cart)) : ?>
                <div class="fb-cart-empty">
                    <div class="fb-cart-empty__icon">
                        <?php echo $this->get_icon_svg('bag'); ?>
                    </div>
                    <p class="fb-cart-empty__message"><?php echo esc_html($atts['empty_message']); ?></p>
                    <a href="<?php echo esc_url($atts['continue_url']); ?>" class="fb-btn fb-btn--primary">
                        <?php echo esc_html($atts['empty_cta_text']); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="fb-cart-items" data-cart-items>
                    <?php foreach ($cart as $index => $item) : ?>
                        <?php echo $this->render_cart_item($item, $index); ?>
                    <?php endforeach; ?>
                </div>

                <div class="fb-cart-totals">
                    <div class="fb-cart-totals__row fb-cart-totals__row--total">
                        <span class="fb-cart-totals__label"><?php esc_html_e('Total', 'fields-bright-enrollment'); ?></span>
                        <span class="fb-cart-totals__value" data-cart-total>$<?php echo esc_html(number_format($total, 2)); ?></span>
                    </div>
                </div>

                <div class="fb-cart-actions">
                    <?php if ($show_continue) : ?>
                    <a href="<?php echo esc_url($atts['continue_url']); ?>" class="fb-btn fb-btn--secondary">
                        <?php echo esc_html($atts['continue_text']); ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($show_checkout) : ?>
                    <button type="button" class="fb-btn fb-btn--primary fb-cart-checkout-btn" data-cart-checkout>
                        <?php echo esc_html($atts['checkout_text']); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <div class="fb-cart-secondary-actions">
                    <button type="button" class="fb-cart-clear-btn" data-cart-clear>
                        <?php esc_html_e('Clear Cart', 'fields-bright-enrollment'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single cart item.
     *
     * @param array $item  Cart item data.
     * @param int   $index Item index.
     *
     * @return string HTML output.
     */
    private function render_cart_item(array $item, int $index): string
    {
        $workshop_id = $item['workshop_id'];
        $workshop = get_post($workshop_id);
        $workshop_url = $workshop ? get_permalink($workshop_id) : '';
        $thumbnail = get_the_post_thumbnail_url($workshop_id, 'thumbnail');
        
        // Get workshop details.
        $event_start = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
        $recurring_info = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);
        $location = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'location', true);

        // Get pricing option label.
        $pricing_label = '';
        if (! empty($item['pricing_option'])) {
            $options = WorkshopMetaBox::get_pricing_options($workshop_id);
            foreach ($options as $option) {
                if ($option['id'] === $item['pricing_option']) {
                    $pricing_label = $option['label'];
                    break;
                }
            }
        }

        ob_start();
        ?>
        <div class="fb-cart-item" data-cart-item data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
            <?php if ($thumbnail) : ?>
            <div class="fb-cart-item__image">
                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($item['workshop_title']); ?>">
            </div>
            <?php endif; ?>
            
            <div class="fb-cart-item__details">
                <h4 class="fb-cart-item__title">
                    <?php if ($workshop_url) : ?>
                    <a href="<?php echo esc_url($workshop_url); ?>"><?php echo esc_html($item['workshop_title']); ?></a>
                    <?php else : ?>
                    <?php echo esc_html($item['workshop_title']); ?>
                    <?php endif; ?>
                </h4>
                
                <div class="fb-cart-item__meta">
                    <?php if ($pricing_label) : ?>
                    <span class="fb-cart-item__option"><?php echo esc_html($pricing_label); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($recurring_info) : ?>
                    <span class="fb-cart-item__schedule"><?php echo esc_html($recurring_info); ?></span>
                    <?php elseif ($event_start) : ?>
                    <span class="fb-cart-item__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($event_start))); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($location) : ?>
                    <span class="fb-cart-item__location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="fb-cart-item__price">
                $<?php echo esc_html(number_format((float) $item['price'], 2)); ?>
            </div>
            
            <button type="button" 
                    class="fb-cart-item__remove" 
                    data-remove-item 
                    data-workshop-id="<?php echo esc_attr($workshop_id); ?>"
                    aria-label="<?php esc_attr_e('Remove item', 'fields-bright-enrollment'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add to cart button shortcode.
     *
     * Usage: [add_to_cart_button workshop_id="123" text="Add to Cart"]
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_add_to_cart_button(array $atts = []): string
    {
        $atts = shortcode_atts([
            'workshop_id'      => get_the_ID(),
            'text'             => __('Add to Cart', 'fields-bright-enrollment'),
            'in_cart_text'     => __('In Cart', 'fields-bright-enrollment'),
            'class'            => '',
            'show_price'       => 'true',
            'show_capacity'    => 'true',
            'show_view_cart'   => 'true',
        ], $atts);

        $workshop_id = absint($atts['workshop_id']);
        $show_price = filter_var($atts['show_price'], FILTER_VALIDATE_BOOLEAN);
        $show_capacity = filter_var($atts['show_capacity'], FILTER_VALIDATE_BOOLEAN);
        $show_view_cart = filter_var($atts['show_view_cart'], FILTER_VALIDATE_BOOLEAN);

        // Check if checkout is enabled.
        if (! WorkshopMetaBox::is_checkout_enabled($workshop_id)) {
            return '';
        }

        // Check enrollment eligibility.
        $can_enroll = WorkshopMetaBox::can_enroll($workshop_id);
        $remaining_spots = WorkshopMetaBox::get_remaining_spots($workshop_id);
        $capacity = WorkshopMetaBox::get_capacity($workshop_id);

        // Check for waitlist claim - if user has a reserved spot.
        $has_waitlist_claim = false;
        if (class_exists('FieldsBright\Enrollment\Waitlist\WaitlistClaimHandler')) {
            $claim_handler = new \FieldsBright\Enrollment\Waitlist\WaitlistClaimHandler();
            $claim_data = $claim_handler->get_claim_data();
            
            if ($claim_data && (int) $claim_data['workshop_id'] === (int) $workshop_id) {
                $has_waitlist_claim = true;
                // Override enrollment eligibility - they have a reserved spot!
                $can_enroll['allowed'] = true;
                $can_enroll['waitlist'] = false;
            }
        }

        // Check if already in cart.
        $cart = $this->cart_manager->get_cart();
        $in_cart = false;
        foreach ($cart as $item) {
            if ($item['workshop_id'] === $workshop_id) {
                $in_cart = true;
                break;
            }
        }

        // Get pricing options.
        $pricing_options = WorkshopMetaBox::get_pricing_options($workshop_id);
        $base_price = WorkshopMetaBox::get_effective_price($workshop_id, '');

        // Determine button state.
        $is_sold_out = ! $can_enroll['allowed'] && ! $can_enroll['waitlist'];
        $is_waitlist = $can_enroll['waitlist'];

        $button_classes = ['fb-add-to-cart-btn'];
        if ($atts['class']) {
            $button_classes[] = $atts['class'];
        }
        if ($in_cart) {
            $button_classes[] = 'in-cart';
        }
        if ($is_sold_out) {
            $button_classes[] = 'sold-out';
        }

        ob_start();
        ?>
        <div class="fb-add-to-cart-wrapper" data-add-to-cart-wrapper data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
            <?php if ($show_capacity && $capacity > 0) : ?>
                <div class="fb-add-to-cart__capacity">
                    <?php if ($has_waitlist_claim) : ?>
                        <span class="fb-capacity-badge reserved" style="background: #d4edda; color: #155724; border-color: #28a745;">
                            <?php esc_html_e('✓ Reserved for You', 'fields-bright-enrollment'); ?>
                        </span>
                    <?php elseif ($remaining_spots !== null && $remaining_spots > 0) : ?>
                        <span class="fb-capacity-badge <?php echo $remaining_spots <= 5 ? 'urgent' : ''; ?>">
                            <?php
                            printf(
                                /* translators: %d: number of spots */
                                esc_html(_n('%d spot left', '%d spots left', $remaining_spots, 'fields-bright-enrollment')),
                                $remaining_spots
                            );
                            ?>
                        </span>
                    <?php elseif ($is_sold_out) : ?>
                        <span class="fb-capacity-badge sold-out"><?php esc_html_e('Sold Out', 'fields-bright-enrollment'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($pricing_options) && ! $is_sold_out && ! $in_cart) : ?>
                <div class="fb-add-to-cart__options">
                    <select class="fb-pricing-select" data-pricing-select data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
                        <?php foreach ($pricing_options as $option) : ?>
                            <option value="<?php echo esc_attr($option['id']); ?>" 
                                    data-price="<?php echo esc_attr($option['price']); ?>"
                                    <?php echo ! empty($option['default']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($option['label']); ?> - $<?php echo esc_html(number_format($option['price'], 2)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($show_price && $base_price > 0 && ! $is_sold_out && ! $in_cart) : ?>
                <div class="fb-add-to-cart__price">
                    $<?php echo esc_html(number_format($base_price, 2)); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_sold_out) : ?>
                <button type="button" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" disabled>
                    <?php esc_html_e('Sold Out', 'fields-bright-enrollment'); ?>
                </button>
            <?php elseif ($is_waitlist) : ?>
                <?php // Show waitlist form via shortcode. ?>
                <?php echo do_shortcode('[waitlist_form workshop_id="' . $workshop_id . '"]'); ?>
            <?php elseif ($in_cart) : ?>
                <button type="button" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php echo esc_html($atts['in_cart_text']); ?>
                </button>
                <?php if ($show_view_cart) : ?>
                <a href="<?php echo esc_url($this->get_cart_page_url()); ?>" class="fb-view-cart-link">
                    <?php esc_html_e('View Cart', 'fields-bright-enrollment'); ?>
                </a>
                <?php endif; ?>
            <?php else : ?>
                <button type="button" 
                        class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" 
                        data-add-to-cart
                        data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

