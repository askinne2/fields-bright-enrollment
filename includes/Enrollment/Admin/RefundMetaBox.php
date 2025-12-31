<?php
/**
 * Refund Meta Box
 *
 * Provides admin interface for processing refunds on enrollment edit screen.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class RefundMetaBox
 *
 * Handles the refund meta box on enrollment edit screen.
 *
 * @since 1.1.0
 */
class RefundMetaBox
{
    /**
     * Meta box ID.
     *
     * @var string
     */
    private const META_BOX_ID = 'fields_bright_refund';

    /**
     * Refund handler instance.
     *
     * @var RefundHandler
     */
    private RefundHandler $refund_handler;

    /**
     * Constructor.
     *
     * @param RefundHandler|null $refund_handler Optional refund handler instance.
     */
    public function __construct(?RefundHandler $refund_handler = null)
    {
        $this->refund_handler = $refund_handler ?? new RefundHandler();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add the refund meta box.
     *
     * @return void
     */
    public function add_meta_box(): void
    {
        add_meta_box(
            self::META_BOX_ID,
            __('Refund', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            EnrollmentCPT::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Enqueue scripts for the refund meta box.
     *
     * @param string $hook_suffix Current admin page hook.
     *
     * @return void
     */
    public function enqueue_scripts(string $hook_suffix): void
    {
        global $post;

        if ($hook_suffix !== 'post.php' || ! $post || $post->post_type !== EnrollmentCPT::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'fields-bright-refund',
            get_stylesheet_directory_uri() . '/assets/js/refund-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('fields-bright-refund', 'fieldsBrightRefund', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fields_bright_refund'),
            'strings' => [
                'processing'    => __('Processing...', 'fields-bright-enrollment'),
                'confirmRefund' => __('Are you sure you want to process this refund? This action cannot be undone.', 'fields-bright-enrollment'),
                'success'       => __('Refund processed successfully!', 'fields-bright-enrollment'),
                'error'         => __('Error processing refund.', 'fields-bright-enrollment'),
            ],
        ]);
    }

    /**
     * Render the refund meta box.
     *
     * @param \WP_Post $post Current post object.
     *
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void
    {
        $status = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'status', true);
        $amount = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'amount', true);
        $payment_intent_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);
        $refund_details = $this->refund_handler->get_refund_details($post->ID);
        $can_refund = $this->refund_handler->can_refund($post->ID);

        ?>
        <div class="fb-refund-metabox">
            <?php if ($status === 'refunded' && $refund_details) : ?>
                <div class="fb-refund-status fb-refund-status--refunded">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php esc_html_e('Refunded', 'fields-bright-enrollment'); ?></strong>
                </div>
                
                <table class="fb-refund-details">
                    <tr>
                        <th><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></th>
                        <td>$<?php echo esc_html(number_format((float) $refund_details['amount'], 2)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($refund_details['date']))); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Refund ID', 'fields-bright-enrollment'); ?></th>
                        <td><code><?php echo esc_html($refund_details['refund_id']); ?></code></td>
                    </tr>
                    <?php if ($refund_details['reason']) : ?>
                    <tr>
                        <th><?php esc_html_e('Reason', 'fields-bright-enrollment'); ?></th>
                        <td><?php echo esc_html($refund_details['reason']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

            <?php elseif ($can_refund) : ?>
                <div class="fb-refund-form">
                    <p class="fb-refund-info">
                        <?php 
                        printf(
                            /* translators: %s: Amount */
                            esc_html__('Original payment: %s', 'fields-bright-enrollment'),
                            '<strong>$' . esc_html(number_format((float) $amount, 2)) . '</strong>'
                        );
                        ?>
                    </p>

                    <div class="fb-form-field">
                        <label for="refund-amount"><?php esc_html_e('Refund Amount ($)', 'fields-bright-enrollment'); ?></label>
                        <input type="number" 
                               id="refund-amount" 
                               name="refund_amount" 
                               value="<?php echo esc_attr($amount); ?>" 
                               min="0.01" 
                               max="<?php echo esc_attr($amount); ?>" 
                               step="0.01">
                    </div>

                    <div class="fb-form-field">
                        <label for="refund-reason"><?php esc_html_e('Reason (optional)', 'fields-bright-enrollment'); ?></label>
                        <input type="text" id="refund-reason" name="refund_reason" value="">
                    </div>

                    <button type="button" 
                            class="button button-secondary fb-refund-button" 
                            data-enrollment-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Process Refund', 'fields-bright-enrollment'); ?>
                    </button>

                    <div class="fb-refund-message" style="display: none;"></div>
                </div>

            <?php elseif ($status === 'pending') : ?>
                <p class="fb-refund-pending">
                    <?php esc_html_e('This enrollment is still pending and cannot be refunded yet.', 'fields-bright-enrollment'); ?>
                </p>

            <?php elseif (empty($payment_intent_id)) : ?>
                <p class="fb-refund-no-payment">
                    <?php esc_html_e('No payment record found for this enrollment.', 'fields-bright-enrollment'); ?>
                </p>

            <?php else : ?>
                <p class="fb-refund-unavailable">
                    <?php esc_html_e('Refund is not available for this enrollment.', 'fields-bright-enrollment'); ?>
                </p>
            <?php endif; ?>
        </div>

        <style>
            .fb-refund-metabox {
                padding: 10px 0;
            }
            .fb-refund-status {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .fb-refund-status--refunded {
                background: #d1ecf1;
                color: #0c5460;
            }
            .fb-refund-status .dashicons {
                font-size: 20px;
            }
            .fb-refund-details {
                width: 100%;
                border-collapse: collapse;
            }
            .fb-refund-details th,
            .fb-refund-details td {
                padding: 5px 0;
                text-align: left;
                vertical-align: top;
            }
            .fb-refund-details th {
                width: 35%;
                color: #666;
                font-weight: normal;
            }
            .fb-refund-details code {
                font-size: 11px;
                word-break: break-all;
            }
            .fb-refund-form .fb-form-field {
                margin-bottom: 12px;
            }
            .fb-refund-form label {
                display: block;
                margin-bottom: 4px;
                font-weight: 600;
            }
            .fb-refund-form input[type="number"],
            .fb-refund-form input[type="text"] {
                width: 100%;
            }
            .fb-refund-info {
                margin-bottom: 15px;
                padding: 10px;
                background: #f0f0f1;
                border-radius: 4px;
            }
            .fb-refund-button {
                width: 100%;
                margin-top: 5px;
            }
            .fb-refund-message {
                margin-top: 10px;
                padding: 10px;
                border-radius: 4px;
            }
            .fb-refund-message.success {
                background: #d4edda;
                color: #155724;
            }
            .fb-refund-message.error {
                background: #f8d7da;
                color: #721c24;
            }
            .fb-refund-pending,
            .fb-refund-no-payment,
            .fb-refund-unavailable {
                color: #666;
                font-style: italic;
            }
        </style>
        <?php
    }
}

