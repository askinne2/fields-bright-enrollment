<?php
/**
 * Webhook Handler
 *
 * Handles incoming Stripe webhook events including checkout completion,
 * refunds, and payment failures.
 *
 * @package FieldsBright\Enrollment\Stripe
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\Stripe;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WebhookHandler
 *
 * Processes Stripe webhook events.
 *
 * @since 1.0.0
 */
class WebhookHandler
{
    /**
     * Stripe handler instance.
     *
     * @var StripeHandler
     */
    private StripeHandler $stripe_handler;

    /**
     * Enrollment CPT instance.
     *
     * @var EnrollmentCPT
     */
    private EnrollmentCPT $enrollment_cpt;

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
     */
    public function __construct(StripeHandler $stripe_handler)
    {
        $this->stripe_handler = $stripe_handler;
        $this->enrollment_cpt = new EnrollmentCPT();
        $this->logger = Logger::instance();
    }

    /**
     * Process incoming webhook.
     *
     * @param string $payload   Raw request body.
     * @param string $signature Stripe signature header.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    public function process_webhook(string $payload, string $signature): array
    {
        $this->logger->start_process('webhook_processing', [
            'payload_length' => strlen($payload),
            'has_signature' => !empty($signature),
        ]);

        // Verify signature
        $verification = $this->stripe_handler->verify_webhook_signature($payload, $signature);

        if (! $verification['success']) {
            $this->logger->error('Webhook signature verification failed', [
                'error' => $verification['error'],
            ]);
            $this->logger->end_process('webhook_processing', ['success' => false]);
            return [
                'success'     => false,
                'message'     => $verification['error'],
                'status_code' => 400,
            ];
        }

        $event = $verification['event'];
        $event_type = $event['type'] ?? '';
        $event_id = $event['id'] ?? '';

        $this->logger->log_step('webhook_processing', 'Signature verified', [
            'event_id' => $event_id,
            'event_type' => $event_type,
        ]);

        // Check for idempotency (prevent duplicate processing)
        if ($this->is_event_processed($event_id)) {
            $this->logger->info('Webhook event already processed', ['event_id' => $event_id]);
            $this->logger->end_process('webhook_processing', ['success' => true, 'duplicate' => true]);
            return [
                'success'     => true,
                'message'     => 'Event already processed',
                'status_code' => 200,
            ];
        }

        $this->logger->log_step('webhook_processing', 'Processing event', [
            'event_id' => $event_id,
            'event_type' => $event_type,
        ]);

        // Route to appropriate handler
        switch ($event_type) {
            case 'checkout.session.completed':
                $result = $this->handle_checkout_completed($event);
                break;
            case 'charge.refunded':
                $result = $this->handle_charge_refunded($event);
                break;
            case 'payment_intent.payment_failed':
                $result = $this->handle_payment_failed($event);
                break;
            default:
                $this->logger->debug('Unhandled event type', ['event_type' => $event_type]);
                $result = [
                    'success'     => true,
                    'message'     => 'Event type not handled: ' . $event_type,
                    'status_code' => 200,
                ];
        }

        // Mark event as processed
        if ($result['success']) {
            $this->mark_event_processed($event_id);
        }

        $this->logger->end_process('webhook_processing', [
            'success' => $result['success'],
            'event_type' => $event_type,
            'status_code' => $result['status_code'],
        ]);

        return $result;
    }

    /**
     * Handle checkout.session.completed event.
     *
     * Creates enrollment records on successful payment (not before).
     *
     * @param array $event Stripe event data.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    private function handle_checkout_completed(array $event): array
    {
        $session = $event['data']['object'] ?? [];
        $session_id = $session['id'] ?? '';
        $metadata = $session['metadata'] ?? [];

        if (empty($session_id)) {
            return [
                'success'     => false,
                'message'     => 'Session ID not found in event',
                'status_code' => 400,
            ];
        }

        // Extract customer details from session.
        $customer_email = $session['customer_details']['email'] ?? '';
        $customer_name = $session['customer_details']['name'] ?? '';
        $customer_phone = $session['customer_details']['phone'] ?? '';
        $payment_intent_id = $session['payment_intent'] ?? '';
        $customer_id = $session['customer'] ?? '';
        $amount_total = isset($session['amount_total']) ? $session['amount_total'] / 100 : 0;

        // Check if this is a cart checkout (multiple items) or legacy single-item checkout.
        $is_cart = ($metadata['is_cart'] ?? '') === 'true';
        
        if ($is_cart) {
            // Cart checkout - create enrollments from cart_data metadata.
            return $this->handle_cart_checkout_completed($session, $metadata);
        }

        // Legacy single-item checkout - check if enrollment already exists.
        $enrollment = $this->enrollment_cpt->get_by_session_id($session_id);

        if ($enrollment) {
            // Update existing enrollment (legacy flow).
            return $this->update_enrollment_from_session($enrollment, $session);
        }

        // Single-item checkout without existing enrollment - create one.
        $workshop_id = $metadata['workshop_id'] ?? 0;
        $pricing_option = $metadata['pricing_option'] ?? '';

        if (! $workshop_id) {
            $this->log_error('No workshop ID in metadata for session', ['session_id' => $session_id]);
            return [
                'success'     => false,
                'message'     => 'No workshop ID found in session metadata',
                'status_code' => 400,
            ];
        }

        // Create the enrollment.
        $enrollment_id = $this->enrollment_cpt->create_enrollment([
            'workshop_id'              => $workshop_id,
            'stripe_session_id'        => $session_id,
            'amount'                   => $amount_total,
            'pricing_option_id'        => $pricing_option,
            'status'                   => 'completed',
            'date'                     => current_time('mysql'),
            'customer_email'           => $customer_email,
            'customer_name'            => $customer_name,
            'customer_phone'           => $customer_phone,
            'stripe_payment_intent_id' => $payment_intent_id,
            'stripe_customer_id'       => $customer_id,
        ]);

        if (is_wp_error($enrollment_id)) {
            $this->log_error('Failed to create enrollment', [
                'session_id' => $session_id,
                'error'      => $enrollment_id->get_error_message(),
            ]);
            return [
                'success'     => false,
                'message'     => 'Failed to create enrollment: ' . $enrollment_id->get_error_message(),
                'status_code' => 500,
            ];
        }

        $this->log_info('Enrollment created and completed', [
            'enrollment_id'  => $enrollment_id,
            'session_id'     => $session_id,
            'customer_email' => $customer_email,
        ]);

        // Fire action for other plugins/themes (EmailHandler sends emails via this hook).
        do_action('fields_bright_enrollment_completed', $enrollment_id, $session);

        return [
            'success'     => true,
            'message'     => 'Enrollment created and completed successfully',
            'status_code' => 200,
        ];
    }

    /**
     * Handle cart checkout completion - creates multiple enrollments.
     *
     * @param array $session  Stripe session data.
     * @param array $metadata Session metadata.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    private function handle_cart_checkout_completed(array $session, array $metadata): array
    {
        $session_id = $session['id'] ?? '';
        $cart_data = json_decode($metadata['cart_data'] ?? '[]', true);

        if (empty($cart_data)) {
            $this->log_error('Empty cart data in metadata', ['session_id' => $session_id]);
            return [
                'success'     => false,
                'message'     => 'Empty cart data in session metadata',
                'status_code' => 400,
            ];
        }

        // Extract customer details.
        $customer_email = $session['customer_details']['email'] ?? '';
        $customer_name = $session['customer_details']['name'] ?? '';
        $customer_phone = $session['customer_details']['phone'] ?? '';
        $payment_intent_id = $session['payment_intent'] ?? '';
        $customer_id = $session['customer'] ?? '';

        $created_enrollments = [];

        // Create enrollment for each item in cart.
        foreach ($cart_data as $item) {
            $workshop_id = $item['id'] ?? 0;
            $pricing_option = $item['pricing_option'] ?? '';
            $price = $item['price'] ?? 0;

            if (! $workshop_id) {
                continue;
            }

            $enrollment_id = $this->enrollment_cpt->create_enrollment([
                'workshop_id'              => $workshop_id,
                'stripe_session_id'        => $session_id,
                'amount'                   => $price,
                'pricing_option_id'        => $pricing_option,
                'status'                   => 'completed',
                'date'                     => current_time('mysql'),
                'customer_email'           => $customer_email,
                'customer_name'            => $customer_name,
                'customer_phone'           => $customer_phone,
                'stripe_payment_intent_id' => $payment_intent_id,
                'stripe_customer_id'       => $customer_id,
            ]);

            if (! is_wp_error($enrollment_id)) {
                $created_enrollments[] = $enrollment_id;

                // Fire action (EmailHandler sends emails via this hook).
                do_action('fields_bright_enrollment_completed', $enrollment_id, $session);
            } else {
                $this->log_error('Failed to create cart enrollment', [
                    'workshop_id' => $workshop_id,
                    'error'       => $enrollment_id->get_error_message(),
                ]);
            }
        }

        $this->log_info('Cart checkout completed', [
            'session_id'   => $session_id,
            'enrollments'  => $created_enrollments,
            'customer'     => $customer_email,
        ]);

        // Check if this was a waitlist claim - convert entry to enrollment.
        if (! empty($metadata['waitlist_entry_id']) && ! empty($created_enrollments)) {
            $waitlist_entry_id = absint($metadata['waitlist_entry_id']);
            
            if ($waitlist_entry_id && class_exists('FieldsBright\Enrollment\Waitlist\WaitlistCPT')) {
                $waitlist_cpt = new \FieldsBright\Enrollment\Waitlist\WaitlistCPT();
                
                // Get the primary enrollment (first one created).
                $primary_enrollment_id = $created_enrollments[0];
                
                // Convert waitlist entry to enrollment.
                $converted = $waitlist_cpt->convert_to_enrollment($waitlist_entry_id, $primary_enrollment_id);
                
                if ($converted) {
                    $this->log_info('Waitlist entry converted to enrollment', [
                        'waitlist_entry_id' => $waitlist_entry_id,
                        'enrollment_id'     => $primary_enrollment_id,
                    ]);
                }
            }
        }

        // Clear the user's cart after successful checkout.
        $this->clear_cart_after_checkout();

        return [
            'success'     => true,
            'message'     => sprintf('Created %d enrollment(s) successfully', count($created_enrollments)),
            'status_code' => 200,
        ];
    }

    /**
     * Update existing enrollment from session data (legacy flow).
     *
     * @param \WP_Post $enrollment Existing enrollment post.
     * @param array    $session    Stripe session data.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    private function update_enrollment_from_session(\WP_Post $enrollment, array $session): array
    {
        $session_id = $session['id'] ?? '';
        
        // Extract customer details from session.
        $customer_email = $session['customer_details']['email'] ?? '';
        $customer_name = $session['customer_details']['name'] ?? '';
        $customer_phone = $session['customer_details']['phone'] ?? '';
        $payment_intent_id = $session['payment_intent'] ?? '';
        $customer_id = $session['customer'] ?? '';
        $amount_total = isset($session['amount_total']) ? $session['amount_total'] / 100 : 0;

        // Update enrollment record.
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', 'completed');
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_email', $customer_email);
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_name', $customer_name);
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_phone', $customer_phone);
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', $payment_intent_id);
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'stripe_customer_id', $customer_id);
        
        // Update amount if it differs.
        if ($amount_total > 0) {
            update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', $amount_total);
        }

        // Update post title with customer name.
        $workshop_id = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
        $workshop_title = $workshop_id ? get_the_title($workshop_id) : __('Unknown Workshop', 'fields-bright-enrollment');
        
        wp_update_post([
            'ID'         => $enrollment->ID,
            'post_title' => sprintf('%s - %s', $workshop_title, $customer_name ?: $customer_email),
        ]);

        $this->log_info('Enrollment completed (legacy update)', [
            'enrollment_id'  => $enrollment->ID,
            'session_id'     => $session_id,
            'customer_email' => $customer_email,
        ]);

        // Fire action (EmailHandler sends emails via this hook).
        do_action('fields_bright_enrollment_completed', $enrollment->ID, $session);

        return [
            'success'     => true,
            'message'     => 'Enrollment completed successfully',
            'status_code' => 200,
        ];
    }

    /**
     * Clear user's cart after successful checkout.
     *
     * @return void
     */
    private function clear_cart_after_checkout(): void
    {
        // This runs in webhook context, so we can't directly access user cart.
        // The cart is cleared client-side on redirect to success page.
        // Could implement server-side clearing via transient lookup if needed.
    }

    /**
     * Handle charge.refunded event.
     *
     * @param array $event Stripe event data.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    private function handle_charge_refunded(array $event): array
    {
        $charge = $event['data']['object'] ?? [];
        $payment_intent_id = $charge['payment_intent'] ?? '';

        if (empty($payment_intent_id)) {
            return [
                'success'     => true,
                'message'     => 'Payment intent not found, skipping',
                'status_code' => 200,
            ];
        }

        // Find enrollment by payment intent
        $enrollments = get_posts([
            'post_type'      => EnrollmentCPT::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id',
                    'value' => $payment_intent_id,
                ],
            ],
        ]);

        if (empty($enrollments)) {
            $this->log_info('No enrollment found for refund', ['payment_intent_id' => $payment_intent_id]);
            return [
                'success'     => true,
                'message'     => 'No enrollment found for payment intent',
                'status_code' => 200,
            ];
        }

        $enrollment = $enrollments[0];

        // Check if already refunded - prevent duplicate processing.
        // If status is already 'refunded', this was processed by the admin via RefundHandler.
        // Skip firing the action to prevent duplicate emails.
        $current_status = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true);
        $already_refunded = ($current_status === 'refunded');

        if ($already_refunded) {
            $this->log_info('Enrollment already refunded (skipping duplicate webhook processing)', [
                'enrollment_id'    => $enrollment->ID,
                'payment_intent_id' => $payment_intent_id,
            ]);

            // Just add a note about the webhook received, but don't fire the action.
            $notes = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'notes', true);
            $refund_note = sprintf(
                '[%s] Webhook received: charge.refunded (already processed)',
                current_time('Y-m-d H:i:s')
            );
            update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'notes', $notes . "\n" . $refund_note);

            return [
                'success'     => true,
                'message'     => 'Refund already processed (webhook skipped)',
                'status_code' => 200,
            ];
        }

        // Not yet refunded - update status
        $this->enrollment_cpt->update_status($enrollment->ID, 'refunded');

        // Add note about refund
        $notes = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'notes', true);
        $refund_note = sprintf(
            '[%s] Refund processed via webhook. Amount: $%s',
            current_time('Y-m-d H:i:s'),
            number_format($charge['amount_refunded'] / 100, 2)
        );
        update_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'notes', $notes . "\n" . $refund_note);

        $this->log_info('Enrollment refunded via webhook', [
            'enrollment_id'    => $enrollment->ID,
            'payment_intent_id' => $payment_intent_id,
        ]);

        // Fire action (only if this is the first time processing the refund)
        do_action('fields_bright_enrollment_refunded', $enrollment->ID, $charge);

        return [
            'success'     => true,
            'message'     => 'Refund processed successfully',
            'status_code' => 200,
        ];
    }

    /**
     * Handle payment_intent.payment_failed event.
     *
     * @param array $event Stripe event data.
     *
     * @return array{success: bool, message: string, status_code: int}
     */
    private function handle_payment_failed(array $event): array
    {
        $payment_intent = $event['data']['object'] ?? [];
        $payment_intent_id = $payment_intent['id'] ?? '';
        $error_message = $payment_intent['last_payment_error']['message'] ?? 'Unknown error';

        $this->log_error('Payment failed', [
            'payment_intent_id' => $payment_intent_id,
            'error'             => $error_message,
        ]);

        // We don't need to update any enrollment here since the checkout
        // session hasn't completed. Just log it.

        return [
            'success'     => true,
            'message'     => 'Payment failure logged',
            'status_code' => 200,
        ];
    }

    /**
     * Send confirmation email to customer.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool Whether the email was sent.
     */
    private function send_confirmation_email(int $enrollment_id): bool
    {
        $customer_email = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_email', true);
        $customer_name = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_name', true);
        $workshop_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
        $amount = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'amount', true);
        $date = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'date', true);

        if (empty($customer_email)) {
            $this->log_error('Cannot send confirmation email: no customer email', ['enrollment_id' => $enrollment_id]);
            return false;
        }

        $workshop = $workshop_id ? get_post($workshop_id) : null;
        $workshop_title = $workshop ? $workshop->post_title : __('Workshop', 'fields-bright-enrollment');
        
        // Get workshop details
        $workshop_date = $workshop_id ? get_post_meta($workshop_id, '_event_start_datetime', true) : '';
        $workshop_location = $workshop_id ? get_post_meta($workshop_id, '_event_location', true) : '';

        $site_name = get_bloginfo('name');
        $subject = sprintf(
            /* translators: %1$s: workshop title, %2$s: site name */
            __('Enrollment Confirmation: %1$s - %2$s', 'fields-bright-enrollment'),
            $workshop_title,
            $site_name
        );

        // Build email body
        $message = $this->build_confirmation_email($enrollment_id, [
            'customer_name'     => $customer_name,
            'customer_email'    => $customer_email,
            'workshop_title'    => $workshop_title,
            'workshop_date'     => $workshop_date,
            'workshop_location' => $workshop_location,
            'amount'            => $amount,
            'date'              => $date,
            'enrollment_id'     => $enrollment_id,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        ];

        $sent = wp_mail($customer_email, $subject, $message, $headers);

        if ($sent) {
            $this->log_info('Confirmation email sent', [
                'enrollment_id' => $enrollment_id,
                'email'         => $customer_email,
            ]);
        } else {
            $this->log_error('Failed to send confirmation email', [
                'enrollment_id' => $enrollment_id,
                'email'         => $customer_email,
            ]);
        }

        // Also send notification to admin
        $this->send_admin_notification($enrollment_id, [
            'customer_name'  => $customer_name,
            'customer_email' => $customer_email,
            'workshop_title' => $workshop_title,
            'amount'         => $amount,
        ]);

        return $sent;
    }

    /**
     * Build confirmation email HTML.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $data          Email data.
     *
     * @return string Email HTML.
     */
    private function build_confirmation_email(int $enrollment_id, array $data): string
    {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fa; padding: 30px; border-radius: 10px;">
                <h1 style="color: #28a745; margin-top: 0;"><?php esc_html_e('Enrollment Confirmed!', 'fields-bright-enrollment'); ?></h1>
                
                <p><?php echo esc_html(sprintf(__('Hello %s,', 'fields-bright-enrollment'), $data['customer_name'] ?: __('there', 'fields-bright-enrollment'))); ?></p>
                
                <p><?php esc_html_e('Thank you for enrolling! Your payment has been processed successfully.', 'fields-bright-enrollment'); ?></p>
                
                <div style="background: #fff; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h2 style="margin-top: 0; font-size: 18px; color: #333;"><?php esc_html_e('Enrollment Details', 'fields-bright-enrollment'); ?></h2>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong><?php esc_html_e('Workshop:', 'fields-bright-enrollment'); ?></strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($data['workshop_title']); ?></td>
                        </tr>
                        <?php if (! empty($data['workshop_date'])) : ?>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong><?php esc_html_e('Date:', 'fields-bright-enrollment'); ?></strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($data['workshop_date']))); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (! empty($data['workshop_location'])) : ?>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong><?php esc_html_e('Location:', 'fields-bright-enrollment'); ?></strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($data['workshop_location']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong><?php esc_html_e('Amount Paid:', 'fields-bright-enrollment'); ?></strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">$<?php echo esc_html(number_format((float) $data['amount'], 2)); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong><?php esc_html_e('Confirmation #:', 'fields-bright-enrollment'); ?></strong></td>
                            <td style="padding: 8px 0;"><?php echo esc_html($data['enrollment_id']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <h3 style="font-size: 16px;"><?php esc_html_e('What Happens Next?', 'fields-bright-enrollment'); ?></h3>
                <ul style="padding-left: 20px;">
                    <li><?php esc_html_e('Save this email for your records.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Check your inbox for any additional workshop information.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('If you have questions, please reply to this email.', 'fields-bright-enrollment'); ?></li>
                </ul>
                
                <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
                    <?php echo esc_html(sprintf(__('Best regards,<br>%s', 'fields-bright-enrollment'), $site_name)); ?>
                </p>
                
                <p style="color: #999; font-size: 12px;">
                    <a href="<?php echo esc_url($site_url); ?>" style="color: #0073aa;"><?php echo esc_html($site_url); ?></a>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send notification to admin.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $data          Notification data.
     *
     * @return bool Whether the email was sent.
     */
    private function send_admin_notification(int $enrollment_id, array $data): bool
    {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: workshop title */
            __('[%s] New Enrollment: %s', 'fields-bright-enrollment'),
            $site_name,
            $data['workshop_title']
        );

        $message = sprintf(
            __("A new enrollment has been received.\n\nWorkshop: %s\nCustomer: %s (%s)\nAmount: $%s\n\nView enrollment: %s", 'fields-bright-enrollment'),
            $data['workshop_title'],
            $data['customer_name'],
            $data['customer_email'],
            number_format((float) $data['amount'], 2),
            admin_url('post.php?post=' . $enrollment_id . '&action=edit')
        );

        return wp_mail($admin_email, $subject, $message);
    }

    /**
     * Check if an event has already been processed.
     *
     * @param string $event_id Stripe event ID.
     *
     * @return bool
     */
    private function is_event_processed(string $event_id): bool
    {
        $processed_events = get_option('fields_bright_processed_webhook_events', []);
        return in_array($event_id, $processed_events, true);
    }

    /**
     * Mark an event as processed.
     *
     * @param string $event_id Stripe event ID.
     *
     * @return void
     */
    private function mark_event_processed(string $event_id): void
    {
        $processed_events = get_option('fields_bright_processed_webhook_events', []);
        $processed_events[] = $event_id;

        // Keep only the last 1000 events
        if (count($processed_events) > 1000) {
            $processed_events = array_slice($processed_events, -1000);
        }

        update_option('fields_bright_processed_webhook_events', $processed_events);
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
    private function log_info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}

