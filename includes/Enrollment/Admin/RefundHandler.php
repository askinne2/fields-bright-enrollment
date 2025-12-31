<?php
/**
 * Refund Handler
 *
 * Handles refund processing through Stripe and updates enrollment status.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\Stripe\StripeHandler;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class RefundHandler
 *
 * Manages refund operations via Stripe API.
 *
 * @since 1.1.0
 */
class RefundHandler
{
    /**
     * Stripe handler instance.
     *
     * @var StripeHandler
     */
    private StripeHandler $stripe_handler;

    /**
     * Constructor.
     *
     * @param StripeHandler|null $stripe_handler Optional Stripe handler instance.
     */
    public function __construct(?StripeHandler $stripe_handler = null)
    {
        $this->stripe_handler = $stripe_handler ?? EnrollmentSystem::instance()->get_stripe_handler();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // AJAX handler for admin refund.
        add_action('wp_ajax_fields_bright_process_refund', [$this, 'handle_refund_ajax']);
    }

    /**
     * Process a refund for an enrollment.
     *
     * @param int        $enrollment_id Enrollment post ID.
     * @param float|null $amount        Optional amount to refund (null for full refund).
     * @param string     $reason        Optional refund reason.
     *
     * @return array{success: bool, message: string, refund_id?: string}
     */
    public function process_refund(int $enrollment_id, ?float $amount = null, string $reason = ''): array
    {
        // Verify enrollment exists.
        $enrollment = get_post($enrollment_id);
        if (! $enrollment || $enrollment->post_type !== EnrollmentCPT::POST_TYPE) {
            return [
                'success' => false,
                'message' => __('Enrollment not found.', 'fields-bright-enrollment'),
            ];
        }

        // Get payment intent ID.
        $payment_intent_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);
        if (empty($payment_intent_id)) {
            return [
                'success' => false,
                'message' => __('No payment record found for this enrollment.', 'fields-bright-enrollment'),
            ];
        }

        // Check current status - prevent duplicate refunds.
        $current_status = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'status', true);
        if ($current_status === 'refunded') {
            $this->log_info('Refund attempt blocked - already refunded', [
                'enrollment_id' => $enrollment_id,
                'current_status' => $current_status,
            ]);
            return [
                'success' => false,
                'message' => __('This enrollment has already been refunded.', 'fields-bright-enrollment'),
            ];
        }

        // Additional check: verify no refund_id exists (belt and suspenders).
        $existing_refund_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_id', true);
        if (! empty($existing_refund_id)) {
            $this->log_info('Refund attempt blocked - refund_id already exists', [
                'enrollment_id' => $enrollment_id,
                'existing_refund_id' => $existing_refund_id,
            ]);
            return [
                'success' => false,
                'message' => __('This enrollment has already been refunded.', 'fields-bright-enrollment'),
            ];
        }

        // Get original amount if not specified.
        $original_amount = (float) get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'amount', true);
        if ($amount === null) {
            $amount = $original_amount;
        }

        // Validate amount.
        if ($amount <= 0 || $amount > $original_amount) {
            return [
                'success' => false,
                'message' => __('Invalid refund amount.', 'fields-bright-enrollment'),
            ];
        }

        // Process refund through Stripe.
        $result = $this->create_stripe_refund($payment_intent_id, $amount, $reason);

        if (! $result['success']) {
            return $result;
        }

        // Update enrollment status.
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'status', 'refunded');
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_id', $result['refund_id']);
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_amount', $amount);
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_date', current_time('mysql'));
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_reason', $reason);

        // Fire refund action.
        do_action('fields_bright_enrollment_refunded', $enrollment_id, [
            'refund_id' => $result['refund_id'],
            'amount'    => $amount,
            'reason'    => $reason,
        ]);

        $this->log_info('Refund processed', [
            'enrollment_id' => $enrollment_id,
            'refund_id'     => $result['refund_id'],
            'amount'        => $amount,
        ]);

        return [
            'success'   => true,
            'message'   => __('Refund processed successfully.', 'fields-bright-enrollment'),
            'refund_id' => $result['refund_id'],
        ];
    }

    /**
     * Create refund through Stripe API.
     *
     * @param string $payment_intent_id Stripe Payment Intent ID.
     * @param float  $amount            Amount to refund in dollars.
     * @param string $reason            Refund reason.
     *
     * @return array{success: bool, message?: string, refund_id?: string}
     */
    private function create_stripe_refund(string $payment_intent_id, float $amount, string $reason = ''): array
    {
        // Check if Stripe is configured.
        if (! $this->stripe_handler || ! $this->stripe_handler->is_configured()) {
            return [
                'success' => false,
                'message' => __('Stripe is not configured.', 'fields-bright-enrollment'),
            ];
        }

        // Use StripeHandler to create the refund (uses WordPress HTTP API, not Stripe PHP library).
        $this->log_info('Processing refund', [
            'payment_intent_id' => $payment_intent_id,
            'amount'            => $amount,
            'reason'            => $reason,
        ]);

        $result = $this->stripe_handler->create_refund($payment_intent_id, $amount, $reason);

        if ($result['success']) {
            $this->log_info('Refund processed successfully', [
                'payment_intent_id' => $payment_intent_id,
                'refund_id'         => $result['refund_id'] ?? '',
            ]);
        } else {
            $this->log_info('Refund failed', [
                'payment_intent_id' => $payment_intent_id,
                'error'             => $result['message'] ?? 'Unknown error',
            ]);
        }

        return $result;
    }

    /**
     * Handle AJAX refund request.
     *
     * @return void
     */
    public function handle_refund_ajax(): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'fields_bright_refund')) {
            wp_send_json_error(['message' => __('Security check failed.', 'fields-bright-enrollment')]);
        }

        // Check capabilities.
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to process refunds.', 'fields-bright-enrollment')]);
        }

        $enrollment_id = isset($_POST['enrollment_id']) ? absint($_POST['enrollment_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        if (! $enrollment_id) {
            wp_send_json_error(['message' => __('Invalid enrollment.', 'fields-bright-enrollment')]);
        }

        $result = $this->process_refund($enrollment_id, $amount, $reason);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Check if an enrollment can be refunded.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool
     */
    public function can_refund(int $enrollment_id): bool
    {
        $status = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'status', true);
        $payment_intent_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);

        // Can refund if completed and has payment record.
        return $status === 'completed' && ! empty($payment_intent_id);
    }

    /**
     * Get refund details for an enrollment.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return array|null Refund details or null.
     */
    public function get_refund_details(int $enrollment_id): ?array
    {
        $refund_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_id', true);
        
        if (empty($refund_id)) {
            return null;
        }

        return [
            'refund_id'     => $refund_id,
            'amount'        => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_amount', true),
            'date'          => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_date', true),
            'reason'        => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'refund_reason', true),
        ];
    }

    /**
     * Log info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    private function log_info(string $message, array $context = []): void
    {
            \FieldsBright\Enrollment\Utils\Logger::instance()->info($message, $context);
    }
}

