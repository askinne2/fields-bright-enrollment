<?php
/**
 * Stripe Handler
 *
 * Handles all Stripe API interactions including Checkout Session creation,
 * API key management, and webhook signature verification.
 *
 * @package FieldsBright\Enrollment\Stripe
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\Stripe;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class StripeHandler
 *
 * Wrapper class for Stripe API interactions.
 *
 * @since 1.0.0
 */
class StripeHandler
{
    /**
     * Stripe API version.
     *
     * @var string
     */
    private const API_VERSION = '2023-10-16';

    /**
     * Stripe API base URL.
     *
     * @var string
     */
    private const API_BASE_URL = 'https://api.stripe.com/v1';

    /**
     * Current API secret key.
     *
     * @var string|null
     */
    private ?string $secret_key = null;

    /**
     * Current API publishable key.
     *
     * @var string|null
     */
    private ?string $publishable_key = null;

    /**
     * Whether we're in test mode.
     *
     * @var bool
     */
    private bool $test_mode = true;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * Loads API keys from WordPress options.
     */
    public function __construct()
    {
        $this->logger = Logger::instance();
        $this->load_api_keys();
    }

    /**
     * Load API keys from WordPress options.
     *
     * @return void
     */
    private function load_api_keys(): void
    {
        $this->test_mode = (bool) EnrollmentSystem::get_option('stripe_test_mode', true);

        if ($this->test_mode) {
            $this->secret_key = EnrollmentSystem::get_option('stripe_test_secret_key', '');
            $this->publishable_key = EnrollmentSystem::get_option('stripe_test_publishable_key', '');
        } else {
            $this->secret_key = EnrollmentSystem::get_option('stripe_live_secret_key', '');
            $this->publishable_key = EnrollmentSystem::get_option('stripe_live_publishable_key', '');
        }
    }

    /**
     * Check if Stripe is properly configured.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return ! empty($this->secret_key) && ! empty($this->publishable_key);
    }

    /**
     * Check if we're in test mode.
     *
     * @return bool
     */
    public function is_test_mode(): bool
    {
        return $this->test_mode;
    }

    /**
     * Get the publishable key.
     *
     * @return string
     */
    public function get_publishable_key(): string
    {
        return $this->publishable_key ?? '';
    }

    /**
     * Create a Stripe Checkout Session.
     *
     * @param array $params Session parameters.
     *
     * @return array{success: bool, session_id?: string, url?: string, error?: string}
     */
    public function create_checkout_session(array $params): array
    {
        if (! $this->is_configured()) {
            return [
                'success' => false,
                'error'   => __('Stripe is not configured. Please add API keys in settings.', 'fields-bright-enrollment'),
            ];
        }

        // Set defaults
        $defaults = [
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
        ];

        $params = wp_parse_args($params, $defaults);

        // Make API request
        $response = $this->api_request('checkout/sessions', $params, 'POST');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        return [
            'success'    => true,
            'session_id' => $response['id'],
            'url'        => $response['url'],
        ];
    }

    /**
     * Create a Checkout Session for a workshop enrollment.
     *
     * @param int         $workshop_id     Workshop post ID.
     * @param float       $price           Price in dollars.
     * @param string      $pricing_option  Selected pricing option ID.
     * @param string      $success_url     URL to redirect after successful payment.
     * @param string      $cancel_url      URL to redirect after cancelled payment.
     * @param array       $customer_data   Optional customer data.
     *
     * @return array{success: bool, session_id?: string, url?: string, error?: string}
     */
    public function create_workshop_checkout_session(
        int $workshop_id,
        float $price,
        string $pricing_option = '',
        string $success_url = '',
        string $cancel_url = '',
        array $customer_data = []
    ): array {
        $workshop = get_post($workshop_id);

        if (! $workshop) {
            return [
                'success' => false,
                'error'   => __('Workshop not found.', 'fields-bright-enrollment'),
            ];
        }

        // Build line item description
        $description = $workshop->post_title;
        if ($pricing_option) {
            $description .= ' - ' . $pricing_option;
        }

        // Build metadata
        $metadata = [
            'workshop_id'    => $workshop_id,
            'workshop_title' => $workshop->post_title,
            'pricing_option' => $pricing_option,
            'site_url'       => home_url(),
        ];

        // Build session parameters
        $params = [
            'line_items'             => [
                [
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => (int) ($price * 100), // Convert to cents
                        'product_data' => [
                            'name'        => $workshop->post_title,
                            'description' => $description,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode'                   => 'payment',
            'success_url'            => $success_url,
            'cancel_url'             => $cancel_url,
            'metadata'               => $metadata,
            'customer_email'         => $customer_data['email'] ?? null,
            'phone_number_collection' => ['enabled' => true],
            'billing_address_collection' => 'auto',
        ];

        // Add customer creation if we want to save the customer
        $params['customer_creation'] = 'always';

        // Remove null values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        return $this->create_checkout_session($params);
    }

    /**
     * Retrieve a Checkout Session.
     *
     * @param string $session_id Session ID.
     *
     * @return array|\WP_Error Session data or error.
     */
    public function retrieve_checkout_session(string $session_id)
    {
        return $this->api_request('checkout/sessions/' . $session_id);
    }

    /**
     * Retrieve a Checkout Session with expanded data.
     *
     * @param string $session_id Session ID.
     *
     * @return array|\WP_Error Session data or error.
     */
    public function retrieve_checkout_session_expanded(string $session_id)
    {
        return $this->api_request('checkout/sessions/' . $session_id, [
            'expand' => ['customer', 'payment_intent'],
        ]);
    }

    /**
     * Create a refund for a payment intent.
     *
     * @param string $payment_intent_id Payment Intent ID.
     * @param float  $amount            Refund amount (null for full refund).
     * @param string $reason            Optional refund reason.
     *
     * @return array{success: bool, refund_id?: string, message?: string}
     */
    public function create_refund(string $payment_intent_id, ?float $amount = null, string $reason = ''): array
    {
        if (! $this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Stripe is not configured.', 'fields-bright-enrollment'),
            ];
        }

        $params = [
            'payment_intent' => $payment_intent_id,
        ];

        // Add amount if specified (convert to cents).
        if ($amount !== null) {
            $params['amount'] = (int) ($amount * 100);
        }

        // Add reason if provided.
        if (! empty($reason)) {
            $params['reason'] = 'requested_by_customer';
            $params['metadata'] = [
                'reason' => $reason,
            ];
        }

        $result = $this->api_request('refunds', $params, 'POST');

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
            ];
        }

        return [
            'success'   => true,
            'refund_id' => $result['id'] ?? '',
        ];
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload   Raw request body.
     * @param string $signature Stripe signature header.
     *
     * @return array{success: bool, event?: array, error?: string}
     */
    public function verify_webhook_signature(string $payload, string $signature): array
    {
        $webhook_secret = EnrollmentSystem::get_option('stripe_webhook_secret', '');

        if (empty($webhook_secret)) {
            return [
                'success' => false,
                'error'   => __('Webhook secret not configured.', 'fields-bright-enrollment'),
            ];
        }

        // Parse the signature header
        $sig_parts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $sig_parts[$pair[0]] = $pair[1];
            }
        }

        if (empty($sig_parts['t']) || empty($sig_parts['v1'])) {
            return [
                'success' => false,
                'error'   => __('Invalid signature format.', 'fields-bright-enrollment'),
            ];
        }

        // Check timestamp (allow 5 minute tolerance)
        $timestamp = (int) $sig_parts['t'];
        $tolerance = 300; // 5 minutes

        if (abs(time() - $timestamp) > $tolerance) {
            return [
                'success' => false,
                'error'   => __('Timestamp outside tolerance.', 'fields-bright-enrollment'),
            ];
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

        // Compare signatures
        if (! hash_equals($expected_signature, $sig_parts['v1'])) {
            return [
                'success' => false,
                'error'   => __('Signature verification failed.', 'fields-bright-enrollment'),
            ];
        }

        // Decode the event
        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error'   => __('Invalid JSON payload.', 'fields-bright-enrollment'),
            ];
        }

        return [
            'success' => true,
            'event'   => $event,
        ];
    }

    /**
     * Maximum number of retry attempts for failed API requests.
     *
     * @var int
     */
    private const MAX_RETRIES = 3;

    /**
     * Base delay for exponential backoff (in milliseconds).
     *
     * @var int
     */
    private const RETRY_BASE_DELAY_MS = 500;

    /**
     * Make an API request to Stripe with retry logic.
     *
     * @param string $endpoint API endpoint (without base URL).
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     *
     * @return array|\WP_Error Response data or error.
     */
    private function api_request(string $endpoint, array $params = [], string $method = 'GET')
    {
        $this->logger->debug('Stripe API request', [
            'endpoint' => $endpoint,
            'method' => $method,
            'has_params' => !empty($params),
        ]);

        $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

        $headers = [
            'Authorization'   => 'Bearer ' . $this->secret_key,
            'Stripe-Version'  => self::API_VERSION,
            'Content-Type'    => 'application/x-www-form-urlencoded',
        ];

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30, // 30 second timeout
        ];

        if ($method === 'GET' && ! empty($params)) {
            $url = add_query_arg($this->build_query_params($params), $url);
        } elseif (! empty($params)) {
            $args['body'] = $this->build_query_params($params);
        }

        // Retry logic with exponential backoff.
        $last_error = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $last_error = $response;
                $error_message = $response->get_error_message();
                
                // Only retry on connection/timeout errors, not on client errors.
                if ($this->is_retryable_error($error_message)) {
                    $this->logger->warning('Stripe API request failed, retrying', [
                        'endpoint' => $endpoint,
                        'attempt'  => $attempt,
                        'error'    => $error_message,
                    ]);
                    
                    if ($attempt < self::MAX_RETRIES) {
                        $this->exponential_backoff($attempt);
                        continue;
                    }
                }
                
                $this->logger->error('API request failed', [
                    'endpoint' => $endpoint,
                    'error'    => $error_message,
                    'attempts' => $attempt,
                ]);
                return $response;
            }

            // Check HTTP status for retryable conditions (429, 5xx).
            $status_code = wp_remote_retrieve_response_code($response);
            if ($this->is_retryable_status_code($status_code)) {
                $this->logger->warning('Stripe API returned retryable status', [
                    'endpoint'    => $endpoint,
                    'status_code' => $status_code,
                    'attempt'     => $attempt,
                ]);
                
                if ($attempt < self::MAX_RETRIES) {
                    $this->exponential_backoff($attempt);
                    continue;
                }
            }

            // Parse the response body.
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON response from Stripe', [
                    'endpoint' => $endpoint,
                    'json_error' => json_last_error_msg(),
                ]);
                return new \WP_Error('json_error', __('Invalid JSON response from Stripe.', 'fields-bright-enrollment'));
            }

            if (isset($data['error'])) {
                $error_message = $data['error']['message'] ?? __('Unknown Stripe error.', 'fields-bright-enrollment');
                $this->logger->error('Stripe API error', [
                    'endpoint' => $endpoint,
                    'error'    => $data['error'],
                ]);
                return new \WP_Error('stripe_error', $error_message);
            }

            $this->logger->debug('Stripe API request successful', [
                'endpoint' => $endpoint,
                'method' => $method,
                'attempts' => $attempt,
            ]);

            return $data;
        }

        // All retries exhausted.
        return $last_error ?? new \WP_Error('stripe_error', __('Stripe API request failed after multiple attempts.', 'fields-bright-enrollment'));
    }

    /**
     * Check if an error message indicates a retryable error.
     *
     * @param string $error_message Error message.
     *
     * @return bool Whether the error is retryable.
     */
    private function is_retryable_error(string $error_message): bool
    {
        $retryable_patterns = [
            'timed out',
            'timeout',
            'connection refused',
            'could not connect',
            'temporary failure',
        ];

        $error_lower = strtolower($error_message);
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if HTTP status code is retryable.
     *
     * @param int $status_code HTTP status code.
     *
     * @return bool Whether the status code is retryable.
     */
    private function is_retryable_status_code(int $status_code): bool
    {
        // 429 = Rate limited, 5xx = Server errors.
        return $status_code === 429 || ($status_code >= 500 && $status_code < 600);
    }

    /**
     * Perform exponential backoff delay.
     *
     * @param int $attempt Current attempt number.
     *
     * @return void
     */
    private function exponential_backoff(int $attempt): void
    {
        // Exponential backoff: 500ms, 1000ms, 2000ms, etc.
        $delay_ms = self::RETRY_BASE_DELAY_MS * pow(2, $attempt - 1);
        
        // Add jitter (random 0-250ms) to prevent thundering herd.
        $delay_ms += wp_rand(0, 250);
        
        // Convert to microseconds and sleep.
        usleep($delay_ms * 1000);
    }

    /**
     * Build query parameters for Stripe API (handles nested arrays).
     *
     * @param array  $params Input parameters.
     * @param string $prefix Key prefix for nested arrays.
     *
     * @return array Flattened parameters.
     */
    private function build_query_params(array $params, string $prefix = ''): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            $new_key = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                // Check if it's a numerically indexed array
                if (array_keys($value) === range(0, count($value) - 1)) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $result = array_merge($result, $this->build_query_params($item, "{$new_key}[{$index}]"));
                        } else {
                            $result["{$new_key}[{$index}]"] = $item;
                        }
                    }
                } else {
                    $result = array_merge($result, $this->build_query_params($value, $new_key));
                }
            } elseif (is_bool($value)) {
                $result[$new_key] = $value ? 'true' : 'false';
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
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
     * Log info.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function log_info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}

