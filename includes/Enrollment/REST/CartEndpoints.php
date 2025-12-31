<?php
/**
 * Cart REST API Endpoints
 *
 * Provides REST API endpoints for cart operations including
 * add, remove, update, clear, and checkout.
 *
 * @package FieldsBright\Enrollment\REST
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\REST;

use FieldsBright\Enrollment\Cart\CartManager;
use FieldsBright\Enrollment\Accounts\UserAccountHandler;
use FieldsBright\Enrollment\Stripe\StripeHandler;
use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class CartEndpoints
 *
 * Handles REST API endpoints for cart operations.
 *
 * @since 1.1.0
 */
class CartEndpoints
{
    /**
     * REST namespace.
     *
     * @var string
     */
    public const NAMESPACE = 'fields-bright/v1';

    /**
     * Cart manager instance.
     *
     * @var CartManager
     */
    private CartManager $cart_manager;

    /**
     * User account handler.
     *
     * @var UserAccountHandler
     */
    private UserAccountHandler $account_handler;

    /**
     * Stripe handler instance.
     *
     * @var StripeHandler
     */
    private StripeHandler $stripe_handler;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param CartManager|null        $cart_manager    Optional cart manager instance.
     * @param UserAccountHandler|null $account_handler Optional account handler instance.
     * @param StripeHandler|null      $stripe_handler  Optional stripe handler instance.
     */
    public function __construct(
        ?CartManager $cart_manager = null,
        ?UserAccountHandler $account_handler = null,
        ?StripeHandler $stripe_handler = null
    ) {
        $this->cart_manager = $cart_manager ?? new CartManager();
        $this->account_handler = $account_handler ?? new UserAccountHandler();
        $this->stripe_handler = $stripe_handler ?? new StripeHandler();
        $this->logger = Logger::instance();
        
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        // Get cart.
        register_rest_route(self::NAMESPACE, '/cart', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_cart'],
            'permission_callback' => '__return_true',
        ]);

        // Add to cart.
        register_rest_route(self::NAMESPACE, '/cart/add', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_to_cart'],
            'permission_callback' => '__return_true',
            'args'                => [
                'workshop_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'pricing_option' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ],
            ],
        ]);

        // Remove from cart.
        register_rest_route(self::NAMESPACE, '/cart/remove/(?P<workshop_id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'remove_from_cart'],
            'permission_callback' => '__return_true',
            'args'                => [
                'workshop_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Update cart item.
        register_rest_route(self::NAMESPACE, '/cart/update', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_cart_item'],
            'permission_callback' => '__return_true',
            'args'                => [
                'workshop_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'pricing_option' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Clear cart.
        register_rest_route(self::NAMESPACE, '/cart/clear', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'clear_cart'],
            'permission_callback' => '__return_true',
        ]);

        // Validate cart.
        register_rest_route(self::NAMESPACE, '/cart/validate', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'validate_cart'],
            'permission_callback' => '__return_true',
        ]);

        // Checkout cart (create Stripe session).
        register_rest_route(self::NAMESPACE, '/cart/checkout', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkout_cart'],
            'permission_callback' => '__return_true',
        ]);

        // Get user enrollments (authenticated).
        register_rest_route(self::NAMESPACE, '/account/enrollments', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_user_enrollments'],
            'permission_callback' => [$this, 'check_user_logged_in'],
            'args'                => [
                'status' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_enrollment_status'],
                    'default'           => '',
                ],
            ],
        ]);

        // Check if user has enrolled in workshop.
        register_rest_route(self::NAMESPACE, '/account/has-enrolled/(?P<workshop_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'has_enrolled_in_workshop'],
            'permission_callback' => [$this, 'check_user_logged_in'],
            'args'                => [
                'workshop_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get cart contents.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function get_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => $this->cart_manager->get_cart_for_api(),
        ]);
    }

    /**
     * Add item to cart.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function add_to_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        $workshop_id = $request->get_param('workshop_id');
        $pricing_option = $request->get_param('pricing_option');

        $result = $this->cart_manager->add_to_cart($workshop_id, $pricing_option);

        $response_data = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $this->cart_manager->get_cart_for_api(),
        ];

        return rest_ensure_response($response_data);
    }

    /**
     * Remove item from cart.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function remove_from_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        $workshop_id = $request->get_param('workshop_id');

        $result = $this->cart_manager->remove_from_cart($workshop_id);

        $response_data = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $this->cart_manager->get_cart_for_api(),
        ];

        return rest_ensure_response($response_data);
    }

    /**
     * Update cart item.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function update_cart_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $workshop_id = $request->get_param('workshop_id');
        $pricing_option = $request->get_param('pricing_option');

        $result = $this->cart_manager->update_cart_item($workshop_id, $pricing_option);

        $response_data = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $this->cart_manager->get_cart_for_api(),
        ];

        return rest_ensure_response($response_data);
    }

    /**
     * Clear cart.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function clear_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->cart_manager->clear_cart();

        $response_data = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $this->cart_manager->get_cart_for_api(),
        ];

        return rest_ensure_response($response_data);
    }

    /**
     * Validate cart.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function validate_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->cart_manager->validate_cart();

        return rest_ensure_response([
            'success' => true,
            'valid'   => $result['valid'],
            'errors'  => $result['errors'],
            'data'    => $this->cart_manager->get_cart_for_api(),
        ]);
    }

    /**
     * Get user enrollments.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function get_user_enrollments(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id();
        $status = $request->get_param('status');

        $enrollments = $this->account_handler->get_user_enrollments($user_id, $status);

        return rest_ensure_response([
            'success' => true,
            'data'    => [
                'enrollments' => $enrollments,
                'count'       => count($enrollments),
            ],
        ]);
    }

    /**
     * Check if user has enrolled in a specific workshop.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function has_enrolled_in_workshop(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id();
        $workshop_id = $request->get_param('workshop_id');

        $has_enrolled = $this->account_handler->has_enrolled_in_workshop($user_id, $workshop_id);

        return rest_ensure_response([
            'success'      => true,
            'has_enrolled' => $has_enrolled,
        ]);
    }

    /**
     * Checkout cart - create Stripe Checkout Session for all items.
     *
     * @param \WP_REST_Request $request Request object.
     *
     * @return \WP_REST_Response
     */
    public function checkout_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        // Start process tracking.
        $this->logger->start_process('cart_checkout', [
            'user_id' => get_current_user_id(),
            'ip'      => isset($_SERVER['REMOTE_ADDR']) ? wp_privacy_anonymize_ip(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))) : '',
        ]);

        $this->logger->log_step('cart_checkout', 'Validating cart');
        
        // Validate cart first.
        $validation = $this->cart_manager->validate_cart();
        
        if (! $validation['valid']) {
            $this->logger->warning('Cart validation failed', [
                'errors' => $validation['errors'],
            ]);
            $this->logger->end_process('cart_checkout', ['result' => 'validation_failed']);
            
            return rest_ensure_response([
                'success' => false,
                'message' => __('Some items in your cart are no longer available.', 'fields-bright-enrollment'),
                'errors'  => $validation['errors'],
            ]);
        }

        $cart = $validation['cart'];
        $this->logger->log_step('cart_checkout', 'Cart validated successfully', [
            'item_count' => count($cart),
        ]);
        
        if (empty($cart)) {
            $this->logger->warning('Empty cart at checkout');
            $this->logger->end_process('cart_checkout', ['result' => 'empty_cart']);
            
            return rest_ensure_response([
                'success' => false,
                'message' => __('Your cart is empty.', 'fields-bright-enrollment'),
            ]);
        }

        $this->logger->log_step('cart_checkout', 'Checking Stripe configuration');

        // Check if Stripe is configured.
        if (! $this->stripe_handler->is_configured()) {
            $this->logger->error('Stripe not configured');
            $this->logger->end_process('cart_checkout', ['result' => 'stripe_not_configured']);
            
            return rest_ensure_response([
                'success' => false,
                'message' => __('Payment system is not configured.', 'fields-bright-enrollment'),
            ]);
        }

        // Build line items for Stripe.
        $line_items = [];
        $workshop_ids = [];
        $metadata_workshops = [];
        
        foreach ($cart as $item) {
            $workshop_id = $item['workshop_id'];
            $workshop = get_post($workshop_id);
            
            if (! $workshop) {
                continue;
            }

            // Build description with pricing option.
            $description = $workshop->post_title;
            if (! empty($item['pricing_option'])) {
                $options = WorkshopMetaBox::get_pricing_options($workshop_id);
                foreach ($options as $option) {
                    if ($option['id'] === $item['pricing_option']) {
                        $description .= ' - ' . $option['label'];
                        break;
                    }
                }
            }

            $line_items[] = [
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => (int) ((float) $item['price'] * 100), // Convert to cents.
                    'product_data' => [
                        'name'        => $workshop->post_title,
                        'description' => $description,
                    ],
                ],
                'quantity' => 1,
            ];

            $workshop_ids[] = $workshop_id;
            $metadata_workshops[] = [
                'id'             => $workshop_id,
                'pricing_option' => $item['pricing_option'] ?? '',
                'price'          => $item['price'],
            ];
        }

        if (empty($line_items)) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('No valid items in cart.', 'fields-bright-enrollment'),
            ]);
        }

        // Build success and cancel URLs.
        $success_page_id = EnrollmentSystem::get_option('enrollment_success_page', 0);
        $cancel_page_id = EnrollmentSystem::get_option('enrollment_cancel_page', 0);

        $success_url = $success_page_id
            ? get_permalink($success_page_id)
            : home_url('/enrollment-success/');
        
        $cancel_url = $cancel_page_id
            ? get_permalink($cancel_page_id)
            : home_url('/cart/');

        // Add session ID placeholder to success URL.
        $success_url = add_query_arg([
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'cart'       => '1', // Indicate this was a cart checkout.
        ], $success_url);

        // Build metadata (limited to 500 chars per value).
        $metadata = [
            'workshop_ids' => implode(',', $workshop_ids),
            'cart_data'    => wp_json_encode($metadata_workshops),
            'site_url'     => home_url(),
            'is_cart'      => 'true',
        ];

        // Check for waitlist claim - if user is claiming a reserved spot.
        if (class_exists('FieldsBright\Enrollment\Waitlist\WaitlistClaimHandler')) {
            $claim_handler = new \FieldsBright\Enrollment\Waitlist\WaitlistClaimHandler();
            $claim_data = $claim_handler->get_claim_data();
            
            if ($claim_data && in_array($claim_data['workshop_id'], $workshop_ids, true)) {
                $metadata['waitlist_entry_id'] = (string) $claim_data['entry_id'];
                $this->logger->info('Cart checkout includes waitlist claim', [
                    'entry_id' => $claim_data['entry_id'],
                    'workshop_id' => $claim_data['workshop_id'],
                ]);
            }
        }

        // Create Stripe Checkout Session.
        $params = [
            'line_items'              => $line_items,
            'mode'                    => 'payment',
            'success_url'             => $success_url,
            'cancel_url'              => $cancel_url,
            'metadata'                => $metadata,
            'phone_number_collection' => ['enabled' => true],
            'billing_address_collection' => 'auto',
            'customer_creation'       => 'always',
        ];

        // If user is logged in, pre-fill email.
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $params['customer_email'] = $user->user_email;
        }

        // Create the session.
        $result = $this->stripe_handler->create_checkout_session($params);

        if (! $result['success']) {
            $this->logger->error('Failed to create checkout session', [
                'error' => $result['error'] ?? 'Unknown error',
                'cart_count' => count($cart),
            ]);
            return rest_ensure_response([
                'success' => false,
                'message' => $result['error'] ?? __('Failed to create checkout session.', 'fields-bright-enrollment'),
            ]);
        }

        // Log the checkout (enrollments will be created by webhook on successful payment).
        $this->logger->info('Cart checkout initiated', [
            'session_id' => $result['session_id'],
            'items' => count($cart),
        ]);

        return rest_ensure_response([
            'success'      => true,
            'checkout_url' => $result['url'],
            'session_id'   => $result['session_id'],
        ]);
    }

    /**
     * Check if user is logged in (permission callback).
     *
     * @return bool
     */
    public function check_user_logged_in(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Validate enrollment status parameter.
     *
     * @param string          $value   The value to validate.
     * @param \WP_REST_Request $request The request object.
     * @param string          $param   The parameter name.
     *
     * @return bool|\WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_enrollment_status($value, $request, $param)
    {
        $allowed_statuses = ['', 'completed', 'pending', 'refunded', 'failed'];
        
        if (! in_array($value, $allowed_statuses, true)) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf(
                    /* translators: %s: parameter name */
                    __('Invalid %s parameter. Allowed values: completed, pending, refunded, failed', 'fields-bright-enrollment'),
                    $param
                ),
                ['status' => 400]
            );
        }
        
        return true;
    }
}

