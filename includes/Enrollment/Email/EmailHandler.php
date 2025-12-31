<?php
/**
 * Email Handler
 *
 * Manages all email notifications for the enrollment system including
 * customer confirmations, admin notifications, and refund notices.
 *
 * @package FieldsBright\Enrollment\Email
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Email;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Email\TemplateManager;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailHandler
 *
 * Handles sending enrollment-related emails.
 *
 * @since 1.1.0
 */
class EmailHandler
{
    /**
     * Email from name.
     *
     * @var string
     */
    private string $from_name;

    /**
     * Email from address.
     *
     * @var string
     */
    private string $from_email;

    /**
     * Admin email address.
     *
     * @var string
     */
    private string $admin_email;

    /**
     * Template manager instance.
     *
     * @var TemplateManager
     */
    private TemplateManager $template_manager;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->from_name = get_bloginfo('name');
        $this->from_email = get_option('admin_email');
        $this->admin_email = get_option('admin_email');
        $this->template_manager = new TemplateManager();
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
        // Hook into enrollment completion.
        add_action('fields_bright_enrollment_completed', [$this, 'send_enrollment_emails'], 10, 2);
        
        // Hook into refund events.
        add_action('fields_bright_enrollment_refunded', [$this, 'send_refund_emails'], 10, 2);
    }

    /**
     * Send enrollment confirmation emails.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $stripe_session Stripe session data.
     *
     * @return void
     */
    public function send_enrollment_emails(int $enrollment_id, array $stripe_session = []): void
    {
        // Send customer confirmation.
        $this->send_enrollment_confirmation($enrollment_id);
        
        // Send admin notification.
        $this->send_admin_notification($enrollment_id);
    }

    /**
     * Send refund confirmation emails.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $stripe_charge Stripe charge data.
     *
     * @return void
     */
    public function send_refund_emails(int $enrollment_id, array $stripe_charge = []): void
    {
        $this->send_refund_confirmation($enrollment_id);
    }

    /**
     * Send enrollment confirmation to customer.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool Whether the email was sent successfully.
     */
    public function send_enrollment_confirmation(int $enrollment_id): bool
    {
        $this->logger->start_process('send_enrollment_confirmation', [
            'enrollment_id' => $enrollment_id,
        ]);

        $data = $this->get_enrollment_data($enrollment_id);
        
        if (empty($data['customer_email'])) {
            $this->logger->error('Cannot send confirmation: no customer email', ['enrollment_id' => $enrollment_id]);
            $this->logger->end_process('send_enrollment_confirmation', ['result' => 'no_email']);
            return false;
        }

        $this->logger->log_step('send_enrollment_confirmation', 'Building email subject and message', [
            'customer_email' => $data['customer_email'],
            'workshop_title' => $data['workshop_title'],
        ]);

        $subject = sprintf(
            /* translators: %s: Workshop title */
            __('Enrollment Confirmed: %s', 'fields-bright-enrollment'),
            $data['workshop_title']
        );

        $message = $this->render_template('enrollment-confirmation', $data);
        
        $result = $this->send_email(
            $data['customer_email'],
            $subject,
            $message
        );

        $this->logger->end_process('send_enrollment_confirmation', [
            'result' => $result ? 'success' : 'failed',
        ]);

        return $result;
    }

    /**
     * Send enrollment notification to admin.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool Whether the email was sent successfully.
     */
    public function send_admin_notification(int $enrollment_id): bool
    {
        $data = $this->get_enrollment_data($enrollment_id);

        $subject = sprintf(
            /* translators: %1$s: Customer name, %2$s: Workshop title */
            __('New Enrollment: %1$s - %2$s', 'fields-bright-enrollment'),
            $data['customer_name'] ?: __('Unknown', 'fields-bright-enrollment'),
            $data['workshop_title']
        );

        $message = $this->render_template('admin-notification', $data);
        
        return $this->send_email(
            $this->admin_email,
            $subject,
            $message
        );
    }

    /**
     * Send refund confirmation to customer.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool Whether the email was sent successfully.
     */
    public function send_refund_confirmation(int $enrollment_id): bool
    {
        $data = $this->get_enrollment_data($enrollment_id);
        
        if (empty($data['customer_email'])) {
            $this->log_error('Cannot send refund confirmation: no customer email', ['enrollment_id' => $enrollment_id]);
            return false;
        }

        $subject = sprintf(
            /* translators: %s: Workshop title */
            __('Refund Processed: %s', 'fields-bright-enrollment'),
            $data['workshop_title']
        );

        $message = $this->render_template('refund-confirmation', $data);
        
        return $this->send_email(
            $data['customer_email'],
            $subject,
            $message
        );
    }

    /**
     * Get enrollment data for email templates.
     *
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return array Enrollment data.
     */
    private function get_enrollment_data(int $enrollment_id): array
    {
        $enrollment = get_post($enrollment_id);
        
        if (! $enrollment) {
            return [];
        }

        $workshop_id = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
        $workshop = get_post($workshop_id);

        // Get workshop event details.
        $event_start = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
        $event_end = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'end_datetime', true);
        $event_location = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'location', true);
        $recurring_info = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);

        return [
            'enrollment_id'       => $enrollment_id,
            'confirmation_number' => 'FB-' . str_pad($enrollment_id, 6, '0', STR_PAD_LEFT),
            'customer_name'       => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_name', true),
            'customer_email'      => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_email', true),
            'customer_phone'      => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_phone', true),
            'amount'              => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'amount', true),
            'currency'            => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'currency', true) ?: 'USD',
            'status'              => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'status', true),
            'date'                => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'date', true),
            'pricing_option'      => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true),
            'stripe_payment_id'   => get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true),
            'workshop_id'         => $workshop_id,
            'workshop_title'      => $workshop ? $workshop->post_title : __('Unknown Workshop', 'fields-bright-enrollment'),
            'workshop_url'        => $workshop ? get_permalink($workshop_id) : '',
            'event_start'         => $event_start,
            'event_end'           => $event_end,
            'event_location'      => $event_location,
            'recurring_info'      => $recurring_info,
            'site_name'           => get_bloginfo('name'),
            'site_url'            => home_url(),
            'admin_email'         => $this->admin_email,
            'edit_url'            => admin_url('post.php?post=' . $enrollment_id . '&action=edit'),
        ];
    }

    /**
     * Render an email template.
     *
     * @param string $template Template name (without extension).
     * @param array  $data     Data to pass to template.
     *
     * @return string Rendered template content.
     */
    private function render_template(string $template, array $data): string
    {
        $this->logger->debug("Rendering template: {$template}", [
            'template' => $template,
            'has_branda' => class_exists('Branda_Email_Template'),
        ]);

        // Use TemplateManager for rendering (handles Branda integration).
        return $this->template_manager->render($template, $data);
    }

    /**
     * Get default template content.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     *
     * @return string Default template content.
     */
    private function get_default_template(string $template, array $data): string
    {
        switch ($template) {
            case 'enrollment-confirmation':
                return $this->get_default_confirmation_template($data);
            case 'admin-notification':
                return $this->get_default_admin_template($data);
            case 'refund-confirmation':
                return $this->get_default_refund_template($data);
            default:
                return '';
        }
    }

    /**
     * Get default enrollment confirmation template.
     *
     * @param array $data Template data.
     *
     * @return string Template content.
     */
    private function get_default_confirmation_template(array $data): string
    {
        $schedule = $data['recurring_info'] ?: '';
        if (! $schedule && $data['event_start']) {
            $schedule = date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($data['event_start']));
        }

        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #271C1A; padding: 30px; text-align: center;">';
        $html .= '<h1 style="color: #F9DB5E; margin: 0;">You\'re Enrolled!</h1>';
        $html .= '</div>';
        
        $html .= '<div style="padding: 30px; background: #f9f9f9;">';
        $html .= '<p>Dear ' . esc_html($data['customer_name'] ?: 'Valued Customer') . ',</p>';
        $html .= '<p>Thank you for enrolling! Your spot has been confirmed.</p>';
        
        $html .= '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        $html .= '<h2 style="margin-top: 0; color: #271C1A;">Enrollment Details</h2>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Confirmation #</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['confirmation_number']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Workshop</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['workshop_title']) . '</td></tr>';
        if ($schedule) {
            $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Schedule</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($schedule) . '</td></tr>';
        }
        if ($data['event_location']) {
            $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Location</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['event_location']) . '</td></tr>';
        }
        $html .= '<tr><td style="padding: 8px 0;"><strong>Amount Paid</strong></td><td style="padding: 8px 0;">$' . esc_html(number_format((float) $data['amount'], 2)) . ' ' . strtoupper($data['currency']) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        $html .= '<h3 style="color: #271C1A;">What\'s Next?</h3>';
        $html .= '<ul>';
        $html .= '<li>Save this email for your records</li>';
        $html .= '<li>You\'ll receive workshop materials and instructions before we begin</li>';
        $html .= '<li>Mark your calendar and we\'ll see you there!</li>';
        $html .= '</ul>';
        
        if ($data['workshop_url']) {
            $html .= '<p><a href="' . esc_url($data['workshop_url']) . '" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">View Workshop Details</a></p>';
        }
        
        $html .= '<p style="margin-top: 30px; color: #666; font-size: 14px;">Questions? Reply to this email or contact us at ' . esc_html($data['admin_email']) . '</p>';
        $html .= '</div>';
        
        $html .= '<div style="background: #271C1A; padding: 20px; text-align: center;">';
        $html .= '<p style="color: #fff; margin: 0; font-size: 14px;">' . esc_html($data['site_name']) . '</p>';
        $html .= '<p style="color: #999; margin: 5px 0 0; font-size: 12px;">' . esc_html($data['site_url']) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get default admin notification template.
     *
     * @param array $data Template data.
     *
     * @return string Template content.
     */
    private function get_default_admin_template(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #0073aa; padding: 20px; text-align: center;">';
        $html .= '<h1 style="color: #fff; margin: 0;">New Enrollment</h1>';
        $html .= '</div>';
        
        $html .= '<div style="padding: 30px; background: #f9f9f9;">';
        $html .= '<p>A new enrollment has been received:</p>';
        
        $html .= '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Confirmation #</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['confirmation_number']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Customer</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['customer_name'] ?: 'Not provided') . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Email</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><a href="mailto:' . esc_attr($data['customer_email']) . '">' . esc_html($data['customer_email']) . '</a></td></tr>';
        if ($data['customer_phone']) {
            $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Phone</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['customer_phone']) . '</td></tr>';
        }
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Workshop</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['workshop_title']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Amount</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">$' . esc_html(number_format((float) $data['amount'], 2)) . ' ' . strtoupper($data['currency']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0;"><strong>Date</strong></td><td style="padding: 8px 0;">' . esc_html(date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($data['date']))) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        $html .= '<p><a href="' . esc_url($data['edit_url']) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">View Enrollment</a></p>';
        
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get default refund confirmation template.
     *
     * @param array $data Template data.
     *
     * @return string Template content.
     */
    private function get_default_refund_template(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #271C1A; padding: 30px; text-align: center;">';
        $html .= '<h1 style="color: #F9DB5E; margin: 0;">Refund Processed</h1>';
        $html .= '</div>';
        
        $html .= '<div style="padding: 30px; background: #f9f9f9;">';
        $html .= '<p>Dear ' . esc_html($data['customer_name'] ?: 'Valued Customer') . ',</p>';
        $html .= '<p>Your refund has been processed. Please allow 5-10 business days for the funds to appear in your account.</p>';
        
        $html .= '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        $html .= '<h2 style="margin-top: 0; color: #271C1A;">Refund Details</h2>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Confirmation #</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['confirmation_number']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Workshop</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . esc_html($data['workshop_title']) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0;"><strong>Amount Refunded</strong></td><td style="padding: 8px 0;">$' . esc_html(number_format((float) $data['amount'], 2)) . ' ' . strtoupper($data['currency']) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        $html .= '<p>If you have any questions about this refund, please contact us at ' . esc_html($data['admin_email']) . '.</p>';
        
        $html .= '<p style="margin-top: 30px;">We hope to see you at a future workshop!</p>';
        $html .= '</div>';
        
        $html .= '<div style="background: #271C1A; padding: 20px; text-align: center;">';
        $html .= '<p style="color: #fff; margin: 0; font-size: 14px;">' . esc_html($data['site_name']) . '</p>';
        $html .= '<p style="color: #999; margin: 5px 0 0; font-size: 12px;">' . esc_html($data['site_url']) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Send an email.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $message Email message (HTML).
     * @param array  $headers Optional additional headers.
     *
     * @return bool Whether the email was sent successfully.
     */
    private function send_email(string $to, string $subject, string $message, array $headers = []): bool
    {
        // Set default headers.
        $default_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
        ];

        $headers = array_merge($default_headers, $headers);

        // Filter for customization.
        $to = apply_filters('fields_bright_email_to', $to);
        $subject = apply_filters('fields_bright_email_subject', $subject);
        $message = apply_filters('fields_bright_email_message', $message);
        $headers = apply_filters('fields_bright_email_headers', $headers);

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            $this->log_info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
            ]);
        } else {
            $this->log_error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
            ]);
        }

        return $sent;
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
        $this->logger->info($message, $context);
    }

    /**
     * Log error message.
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
}

