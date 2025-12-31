<?php
/**
 * Enrollment REST API Endpoints
 *
 * Registers REST API endpoints for the enrollment system including
 * the Stripe webhook endpoint.
 *
 * @package FieldsBright\Enrollment\REST
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\REST;

use FieldsBright\Enrollment\Stripe\WebhookHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EnrollmentEndpoints
 *
 * Handles REST API endpoint registration and routing.
 *
 * @since 1.0.0
 */
class EnrollmentEndpoints
{
    /**
     * REST API namespace.
     *
     * @var string
     */
    public const NAMESPACE = 'fields-bright/v1';

    /**
     * Webhook handler instance.
     *
     * @var WebhookHandler
     */
    private WebhookHandler $webhook_handler;

    /**
     * Constructor.
     *
     * @param WebhookHandler $webhook_handler Webhook handler instance.
     */
    public function __construct(WebhookHandler $webhook_handler)
    {
        $this->webhook_handler = $webhook_handler;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        // Stripe webhook endpoint
        register_rest_route(self::NAMESPACE, '/stripe/webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Webhooks are authenticated via signature
        ]);

        // Health check endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'health_check'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle incoming Stripe webhook.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        // Get raw payload
        $payload = $request->get_body();

        // Get Stripe signature header
        $signature = $request->get_header('stripe-signature');

        if (empty($signature)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing Stripe signature header',
            ], 400);
        }

        // Process the webhook
        $result = $this->webhook_handler->process_webhook($payload, $signature);

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status_code']);
    }

    /**
     * Health check endpoint.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function health_check(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'status'    => 'ok',
            'version'   => \FieldsBright\Enrollment\EnrollmentSystem::VERSION,
            'timestamp' => current_time('mysql'),
        ], 200);
    }
}

