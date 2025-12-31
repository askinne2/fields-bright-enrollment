<?php
/**
 * Enrollment Handler
 *
 * Handles the enrollment flow including validating workshops,
 * creating Stripe Checkout Sessions, and managing redirects.
 *
 * @package FieldsBright\Enrollment\Handlers
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\Handlers;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\Stripe\StripeHandler;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Cart\CartManager;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EnrollmentHandler
 *
 * Handles enrollment requests and redirects.
 *
 * @since 1.0.0
 */
class EnrollmentHandler
{
    /**
     * Stripe handler instance.
     *
     * @var StripeHandler
     */
    private StripeHandler $stripe_handler;

    /**
     * Cart manager instance.
     *
     * @var CartManager
     */
    private CartManager $cart_manager;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param StripeHandler $stripe_handler Stripe handler instance.
     * @param CartManager|null $cart_manager Optional cart manager instance.
     */
    public function __construct(StripeHandler $stripe_handler, ?CartManager $cart_manager = null)
    {
        $this->stripe_handler = $stripe_handler;
        $this->cart_manager = $cart_manager ?? new CartManager();
        $this->logger = Logger::instance();
        
        // Register shortcodes
        add_shortcode('enrollment_button', [$this, 'render_enrollment_button']);
        
        // Handle form submission via AJAX
        add_action('wp_ajax_fields_bright_enroll', [$this, 'handle_ajax_enrollment']);
        add_action('wp_ajax_nopriv_fields_bright_enroll', [$this, 'handle_ajax_enrollment']);
        
        // Handle add to cart via AJAX
        add_action('wp_ajax_fields_bright_add_to_cart', [$this, 'handle_ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_fields_bright_add_to_cart', [$this, 'handle_ajax_add_to_cart']);
    }

    /**
     * Check if current user is already enrolled in a workshop.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return array{enrolled: bool, enrollment_id: int|null, status: string|null}
     */
    private function check_user_enrollment_status(int $workshop_id): array
    {
        $user_id = get_current_user_id();
        
        if (! $user_id) {
            return ['enrolled' => false, 'enrollment_id' => null, 'status' => null];
        }

        $account_handler = EnrollmentSystem::instance()->get_user_account_handler();
        
        if (! $account_handler) {
            return ['enrolled' => false, 'enrollment_id' => null, 'status' => null];
        }

        $enrollments = $account_handler->get_user_enrollments($user_id, 'completed');
        
        foreach ($enrollments as $enrollment) {
            if ((int) $enrollment['workshop_id'] === $workshop_id) {
                return [
                    'enrolled'      => true,
                    'enrollment_id' => $enrollment['id'],
                    'status'        => $enrollment['status'],
                ];
            }
        }
        
        return ['enrolled' => false, 'enrollment_id' => null, 'status' => null];
    }

    /**
     * Handle enrollment request.
     *
     * This is called from the template_redirect hook when an enrollment
     * URL is detected.
     *
     * @return void
     */
    public function handle_enrollment_request(): void
    {
        $this->logger->start_process('enrollment_request', [
            'workshop_id' => isset($_GET['workshop']) ? absint($_GET['workshop']) : null,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '',
        ]);

        // Get workshop ID from query string or URL
        $workshop_id = $this->get_workshop_id_from_request();

        if (! $workshop_id) {
            $this->logger->warning('Enrollment request missing workshop ID');
            $this->redirect_with_error(__('Workshop not specified.', 'fields-bright-enrollment'));
            return;
        }

        $this->logger->log_step('enrollment_request', 'Workshop ID extracted', ['workshop_id' => $workshop_id]);

        // Validate workshop
        $validation = $this->validate_workshop($workshop_id);

        if (! $validation['valid']) {
            $this->logger->warning('Workshop validation failed', [
                'workshop_id' => $workshop_id,
                'error' => $validation['error'],
            ]);
            $this->redirect_with_error($validation['error']);
            return;
        }

        $this->logger->log_step('enrollment_request', 'Workshop validated', ['workshop_id' => $workshop_id]);

        // Get pricing option if specified (use wp_unslash for proper sanitization).
        $pricing_option = isset($_GET['pricing']) ? sanitize_text_field(wp_unslash($_GET['pricing'])) : '';

        // Get effective price
        $price = WorkshopMetaBox::get_effective_price($workshop_id, $pricing_option);

        if (! $price || $price <= 0) {
            $this->logger->error('Invalid price configuration', [
                'workshop_id' => $workshop_id,
                'pricing_option' => $pricing_option,
                'price' => $price,
            ]);
            $this->redirect_with_error(__('Invalid price configuration.', 'fields-bright-enrollment'));
            return;
        }

        $this->logger->log_step('enrollment_request', 'Price calculated', [
            'workshop_id' => $workshop_id,
            'pricing_option' => $pricing_option,
            'price' => $price,
        ]);

        // Get pricing option label for display
        $pricing_label = '';
        if ($pricing_option) {
            $options = WorkshopMetaBox::get_pricing_options($workshop_id);
            foreach ($options as $option) {
                if ($option['id'] === $pricing_option) {
                    $pricing_label = $option['label'];
                    break;
                }
            }
        }

        // Build success and cancel URLs
        $success_url = $this->get_success_url($workshop_id);
        $cancel_url = $this->get_cancel_url($workshop_id);

        $this->logger->log_step('enrollment_request', 'Creating Stripe checkout session', [
            'workshop_id' => $workshop_id,
            'price' => $price,
            'success_url' => $success_url,
        ]);

        // Create Stripe Checkout Session
        $result = $this->stripe_handler->create_workshop_checkout_session(
            $workshop_id,
            $price,
            $pricing_label ?: $pricing_option,
            $success_url,
            $cancel_url
        );

        if (! $result['success']) {
            $this->logger->error('Failed to create checkout session', [
                'workshop_id' => $workshop_id,
                'error' => $result['error'],
            ]);
            $this->redirect_with_error($result['error']);
            return;
        }

        $this->logger->log_step('enrollment_request', 'Stripe session created', [
            'session_id' => $result['session_id'],
        ]);

        // Create pending enrollment record
        $enrollment_cpt = new EnrollmentCPT();
        $enrollment_id = $enrollment_cpt->create_enrollment([
            'workshop_id'       => $workshop_id,
            'stripe_session_id' => $result['session_id'],
            'amount'            => $price,
            'pricing_option_id' => $pricing_option,
            'status'            => 'pending',
            'date'              => current_time('mysql'),
        ]);

        if (is_wp_error($enrollment_id)) {
            $this->logger->error('Failed to create enrollment record', [
                'workshop_id' => $workshop_id,
                'session_id' => $result['session_id'],
                'error' => $enrollment_id->get_error_message(),
            ]);
        } else {
            $this->logger->log_step('enrollment_request', 'Enrollment record created', [
                'enrollment_id' => $enrollment_id,
            ]);
        }

        $this->logger->end_process('enrollment_request', [
            'enrollment_id' => $enrollment_id,
            'session_id' => $result['session_id'],
            'redirect_url' => $result['url'],
        ]);

        // Redirect to Stripe Checkout
        wp_redirect($result['url']);
        exit;
    }

    /**
     * Handle AJAX enrollment request.
     *
     * @return void
     */
    public function handle_ajax_enrollment(): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'fields_bright_enrollment')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'fields-bright-enrollment')]);
        }

        $workshop_id = isset($_POST['workshop_id']) ? absint($_POST['workshop_id']) : 0;
        $pricing_option = isset($_POST['pricing_option']) ? sanitize_text_field(wp_unslash($_POST['pricing_option'])) : '';

        if (! $workshop_id) {
            wp_send_json_error(['message' => __('Workshop not specified.', 'fields-bright-enrollment')]);
        }

        // Validate workshop
        $validation = $this->validate_workshop($workshop_id);

        if (! $validation['valid']) {
            wp_send_json_error(['message' => $validation['error']]);
        }

        // Get effective price
        $price = WorkshopMetaBox::get_effective_price($workshop_id, $pricing_option);

        if (! $price || $price <= 0) {
            wp_send_json_error(['message' => __('Invalid price configuration.', 'fields-bright-enrollment')]);
        }

        // Get pricing option label
        $pricing_label = '';
        if ($pricing_option) {
            $options = WorkshopMetaBox::get_pricing_options($workshop_id);
            foreach ($options as $option) {
                if ($option['id'] === $pricing_option) {
                    $pricing_label = $option['label'];
                    break;
                }
            }
        }

        // Build success and cancel URLs
        $success_url = $this->get_success_url($workshop_id);
        $cancel_url = $this->get_cancel_url($workshop_id);

        // Create Stripe Checkout Session
        $result = $this->stripe_handler->create_workshop_checkout_session(
            $workshop_id,
            $price,
            $pricing_label ?: $pricing_option,
            $success_url,
            $cancel_url
        );

        if (! $result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Create pending enrollment record
        $enrollment_cpt = new EnrollmentCPT();
        $enrollment_id = $enrollment_cpt->create_enrollment([
            'workshop_id'       => $workshop_id,
            'stripe_session_id' => $result['session_id'],
            'amount'            => $price,
            'pricing_option_id' => $pricing_option,
            'status'            => 'pending',
            'date'              => current_time('mysql'),
        ]);

        wp_send_json_success([
            'checkout_url' => $result['url'],
            'session_id'   => $result['session_id'],
        ]);
    }

    /**
     * Render enrollment button shortcode.
     *
     * Now functions as an "Add to Cart" button that uses AJAX to add workshops
     * to the cart instead of redirecting directly to Stripe checkout.
     *
     * Usage: [enrollment_button workshop_id="123" text="Add to Cart" show_capacity="true"]
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string Button HTML.
     */
    public function render_enrollment_button(array $atts = []): string
    {
        $atts = shortcode_atts([
            'workshop_id'    => get_the_ID(),
            'text'           => __('Add to Cart', 'fields-bright-enrollment'),
            'in_cart_text'   => __('In Cart', 'fields-bright-enrollment'),
            'class'          => 'fb-add-to-cart-btn',
            'show_capacity'  => 'true',
            'show_price'     => 'true',
            'show_view_cart' => 'true',
            'sold_out_text'  => __('Sold Out', 'fields-bright-enrollment'),
            'waitlist_text'  => __('Join Waitlist', 'fields-bright-enrollment'),
        ], $atts);

        $workshop_id = absint($atts['workshop_id']);
        $show_capacity = filter_var($atts['show_capacity'], FILTER_VALIDATE_BOOLEAN);
        $show_price = filter_var($atts['show_price'], FILTER_VALIDATE_BOOLEAN);
        $show_view_cart = filter_var($atts['show_view_cart'], FILTER_VALIDATE_BOOLEAN);

        // Check if checkout is enabled.
        if (! WorkshopMetaBox::is_checkout_enabled($workshop_id)) {
            return '';
        }

        // Check enrollment eligibility.
        $can_enroll = WorkshopMetaBox::can_enroll($workshop_id);
        $remaining_spots = WorkshopMetaBox::get_remaining_spots($workshop_id);
        $capacity = WorkshopMetaBox::get_capacity($workshop_id);

        // Check if user is already enrolled in this workshop.
        $enrollment_status = $this->check_user_enrollment_status($workshop_id);
        $is_already_enrolled = $enrollment_status['enrolled'];

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
                $this->logger->info('Waitlist claim detected - allowing enrollment', ['workshop_id' => $workshop_id]);
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

        // Get cart page URL.
        $cart_page_id = get_option('fields_bright_cart_page', 0);
        $cart_url = $cart_page_id ? get_permalink($cart_page_id) : home_url('/cart/');

        $button_classes = ['fb-add-to-cart-btn'];
        if ($atts['class'] && $atts['class'] !== 'fb-add-to-cart-btn') {
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
        <div class="fb-add-to-cart-wrapper" data-add-to-cart-wrapper data-workshop-id="<?php echo esc_attr($workshop_id); ?>" data-original-text="<?php echo esc_attr($atts['text']); ?>">
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

            <?php if ($is_already_enrolled && is_user_logged_in()) : ?>
                <div class="fb-enrollment-notice fb-enrollment-notice--already-enrolled">
                    <div class="fb-enrollment-notice__icon">✓</div>
                    <div class="fb-enrollment-notice__content">
                        <strong><?php esc_html_e('You\'re Already Registered!', 'fields-bright-enrollment'); ?></strong>
                        <p>
                            <?php 
                            printf(
                                /* translators: %s: Link to account dashboard */
                                esc_html__('You\'ve already enrolled in this workshop. View your enrollment details in your %s.', 'fields-bright-enrollment'),
                                '<a href="' . esc_url(home_url('/account/')) . '">' . esc_html__('account dashboard', 'fields-bright-enrollment') . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                </div>
            <?php else : ?>
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
                    <?php echo esc_html($atts['sold_out_text']); ?>
                </button>
            <?php elseif ($is_waitlist) : ?>
                <?php // Show waitlist form via shortcode if available. ?>
                <?php if (shortcode_exists('waitlist_form')) : ?>
                    <?php echo do_shortcode('[waitlist_form workshop_id="' . $workshop_id . '"]'); ?>
                <?php else : ?>
                    <button type="button" class="<?php echo esc_attr(implode(' ', $button_classes)); ?> waitlist" disabled>
                        <?php echo esc_html($atts['waitlist_text']); ?>
                    </button>
                <?php endif; ?>
            <?php elseif ($in_cart) : ?>
                <button type="button" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php echo esc_html($atts['in_cart_text']); ?>
                </button>
                <?php if ($show_view_cart) : ?>
                <a href="<?php echo esc_url($cart_url); ?>" class="fb-view-cart-link">
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
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX add to cart request.
     *
     * @return void
     */
    public function handle_ajax_add_to_cart(): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'fields_bright_cart')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'fields-bright-enrollment')]);
        }

        $workshop_id = isset($_POST['workshop_id']) ? absint($_POST['workshop_id']) : 0;
        $pricing_option = isset($_POST['pricing_option']) ? sanitize_text_field(wp_unslash($_POST['pricing_option'])) : '';

        if (! $workshop_id) {
            wp_send_json_error(['message' => __('Workshop not specified.', 'fields-bright-enrollment')]);
        }

        $result = $this->cart_manager->add_to_cart($workshop_id, $pricing_option);

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'cart'    => $this->cart_manager->get_cart_for_api(),
                'count'   => $this->cart_manager->get_count(),
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Get workshop ID from request.
     *
     * @return int Workshop ID or 0 if not found.
     */
    private function get_workshop_id_from_request(): int
    {
        // Check query string
        if (isset($_GET['workshop'])) {
            return absint($_GET['workshop']);
        }

        // Check URL path (e.g., /enroll/123/)
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (preg_match('/\/enroll\/(\d+)\/?/', $path, $matches)) {
            return absint($matches[1]);
        }

        return 0;
    }

    /**
     * Validate a workshop for enrollment.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return array{valid: bool, error?: string, waitlist?: bool}
     */
    private function validate_workshop(int $workshop_id): array
    {
        // Check if post exists
        $workshop = get_post($workshop_id);

        if (! $workshop || $workshop->post_status !== 'publish') {
            return [
                'valid' => false,
                'error' => __('Workshop not found or not available.', 'fields-bright-enrollment'),
            ];
        }

        // Check enrollment eligibility (includes checkout enabled and capacity)
        $can_enroll = WorkshopMetaBox::can_enroll($workshop_id);

        if (! $can_enroll['allowed']) {
            return [
                'valid' => false,
                'error' => $can_enroll['reason'],
            ];
        }

        // Check if Stripe is configured
        if (! $this->stripe_handler->is_configured()) {
            return [
                'valid' => false,
                'error' => __('Payment system is not configured.', 'fields-bright-enrollment'),
            ];
        }

        return [
            'valid'    => true,
            'waitlist' => $can_enroll['waitlist'],
        ];
    }

    /**
     * Get success URL.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return string Success URL.
     */
    private function get_success_url(int $workshop_id): string
    {
        $success_page_id = EnrollmentSystem::get_option('enrollment_success_page', 0);

        if ($success_page_id) {
            $url = get_permalink($success_page_id);
        } else {
            $url = home_url('/enrollment-success/');
        }

        return add_query_arg([
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'workshop'   => $workshop_id,
        ], $url);
    }

    /**
     * Get cancel URL.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return string Cancel URL.
     */
    private function get_cancel_url(int $workshop_id): string
    {
        $cancel_page_id = EnrollmentSystem::get_option('enrollment_cancel_page', 0);

        if ($cancel_page_id) {
            $url = get_permalink($cancel_page_id);
        } else {
            // Default to the workshop page
            $url = get_permalink($workshop_id);
        }

        return add_query_arg('cancelled', '1', $url);
    }

    /**
     * Redirect with error message.
     *
     * @param string $message Error message.
     *
     * @return void
     */
    private function redirect_with_error(string $message): void
    {
        $url = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : home_url();
        
        // Validate the URL is from our site to prevent open redirect vulnerabilities.
        if (! wp_validate_redirect($url, home_url())) {
            $url = home_url();
        }
        
        $url = add_query_arg('enrollment_error', urlencode($message), $url);
        
        wp_redirect($url);
        exit;
    }

    /**
     * Log an error.
     *
     * @param string $message Error message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    private function log_error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Get enrollment URL for a workshop.
     *
     * @param int         $workshop_id    Workshop post ID.
     * @param string|null $pricing_option Optional pricing option ID.
     *
     * @return string Enrollment URL.
     */
    public static function get_enrollment_url(int $workshop_id, ?string $pricing_option = null): string
    {
        $url = home_url('/enroll/');
        
        $args = ['workshop' => $workshop_id];
        
        if ($pricing_option) {
            $args['pricing'] = $pricing_option;
        }
        
        return add_query_arg($args, $url);
    }
}

