<?php
/**
 * Waitlist Handler
 *
 * Manages waitlist operations including adding to waitlist,
 * notifications, and conversion to enrollments.
 *
 * @package FieldsBright\Enrollment\Waitlist
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Waitlist;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WaitlistHandler
 *
 * Handles waitlist operations and notifications.
 *
 * @since 1.1.0
 */
class WaitlistHandler
{
    /**
     * Waitlist CPT instance.
     *
     * @var WaitlistCPT
     */
    private WaitlistCPT $waitlist_cpt;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param WaitlistCPT|null $waitlist_cpt Optional waitlist CPT instance.
     */
    public function __construct(?WaitlistCPT $waitlist_cpt = null)
    {
        $this->waitlist_cpt = $waitlist_cpt ?? new WaitlistCPT();
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
        // Check for spot availability when enrollment is refunded.
        add_action('fields_bright_enrollment_refunded', [$this, 'check_waitlist_on_refund'], 10, 2);
        
        // AJAX handler for waitlist form.
        add_action('wp_ajax_fields_bright_join_waitlist', [$this, 'handle_join_waitlist_ajax']);
        add_action('wp_ajax_nopriv_fields_bright_join_waitlist', [$this, 'handle_join_waitlist_ajax']);
    }

    /**
     * Add customer to waitlist.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $email       Customer email.
     * @param string $name        Customer name.
     * @param string $phone       Customer phone (optional).
     *
     * @return array{success: bool, message: string, position?: int, entry_id?: int}
     */
    public function add_to_waitlist(int $workshop_id, string $email, string $name, string $phone = ''): array
    {
        $this->logger->start_process('add_to_waitlist', [
            'workshop_id' => $workshop_id,
            'email' => $email,
        ]);

        // Validate email.
        if (! is_email($email)) {
            $this->logger->warning('Invalid email for waitlist', ['email' => $email]);
            return [
                'success' => false,
                'message' => __('Please enter a valid email address.', 'fields-bright-enrollment'),
            ];
        }

        // Check if workshop exists.
        $workshop = get_post($workshop_id);
        if (! $workshop || $workshop->post_status !== 'publish') {
            $this->logger->warning('Workshop not found for waitlist', ['workshop_id' => $workshop_id]);
            return [
                'success' => false,
                'message' => __('Workshop not found.', 'fields-bright-enrollment'),
            ];
        }

        // Check if waitlist is enabled.
        if (! WorkshopMetaBox::is_waitlist_enabled($workshop_id)) {
            $this->logger->warning('Waitlist not enabled', ['workshop_id' => $workshop_id]);
            return [
                'success' => false,
                'message' => __('Waitlist is not available for this workshop.', 'fields-bright-enrollment'),
            ];
        }

        // Check if already on waitlist.
        $existing = $this->waitlist_cpt->get_entry_by_email($email, $workshop_id);
        if ($existing) {
            $position = get_post_meta($existing->ID, WaitlistCPT::META_PREFIX . 'position', true);
            $this->logger->info('Already on waitlist', [
                'workshop_id' => $workshop_id,
                'email' => $email,
                'position' => $position,
            ]);
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %d: Position number */
                    __('You\'re already on the waitlist at position #%d.', 'fields-bright-enrollment'),
                    $position
                ),
                'position' => (int) $position,
            ];
        }

        // Create waitlist entry.
        $entry_id = $this->waitlist_cpt->create_entry([
            'workshop_id'    => $workshop_id,
            'customer_email' => $email,
            'customer_name'  => $name,
            'customer_phone' => $phone,
        ]);

        if (is_wp_error($entry_id)) {
            $this->logger->error('Failed to create waitlist entry', [
                'workshop_id' => $workshop_id,
                'error' => $entry_id->get_error_message(),
            ]);
            return [
                'success' => false,
                'message' => __('Failed to join waitlist. Please try again.', 'fields-bright-enrollment'),
            ];
        }

        $position = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'position', true);

        $this->logger->log_step('add_to_waitlist', 'Entry created', [
            'entry_id' => $entry_id,
            'position' => $position,
        ]);

        // Send confirmation email.
        $this->send_waitlist_confirmation($entry_id);

        $this->logger->end_process('add_to_waitlist', [
            'entry_id' => $entry_id,
            'position' => $position,
        ]);

        return [
            'success'  => true,
            'message'  => sprintf(
                /* translators: %d: Position number */
                __('You\'ve been added to the waitlist at position #%d. We\'ll notify you when a spot opens up.', 'fields-bright-enrollment'),
                $position
            ),
            'position' => (int) $position,
            'entry_id' => $entry_id,
        ];
    }

    /**
     * Get waitlist position for an email.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $email       Customer email.
     *
     * @return int|null Position or null if not on waitlist.
     */
    public function get_position(int $workshop_id, string $email): ?int
    {
        $entry = $this->waitlist_cpt->get_entry_by_email($email, $workshop_id);
        
        if (! $entry) {
            return null;
        }

        return (int) get_post_meta($entry->ID, WaitlistCPT::META_PREFIX . 'position', true);
    }

    /**
     * Notify next person in line when spot opens.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return bool Whether notification was sent.
     */
    public function notify_next_in_line(int $workshop_id): bool
    {
        // Get waiting entries ordered by position.
        $entries = $this->waitlist_cpt->get_entries_for_workshop($workshop_id, 'waiting');
        
        if (empty($entries)) {
            return false;
        }

        // Get the first (next) entry.
        $next_entry = $entries[0];
        
        // Generate claim token before sending email.
        $token = $this->waitlist_cpt->generate_claim_token($next_entry['id']);
        
        // Send notification email (use templated version if available).
        $sent = method_exists($this, 'send_spot_available_notification_templated')
            ? $this->send_spot_available_notification_templated($next_entry['id'], $token)
            : $this->send_spot_available_notification($next_entry['id'], $token);
        
        if ($sent) {
            // Mark as notified.
            $this->waitlist_cpt->mark_as_notified($next_entry['id']);
            
            $this->log_info('Notified next in line', [
                'entry_id'    => $next_entry['id'],
                'workshop_id' => $workshop_id,
                'email'       => $next_entry['customer_email'],
                'token'       => substr($token, 0, 8) . '...', // Only log first 8 chars for security
            ]);
            
            return true;
        }

        return false;
    }

    /**
     * Check waitlist when enrollment is refunded.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $stripe_charge Stripe charge data.
     *
     * @return void
     */
    public function check_waitlist_on_refund(int $enrollment_id, array $stripe_charge = []): void
    {
        $workshop_id = get_post_meta($enrollment_id, '_enrollment_workshop_id', true);
        
        if (! $workshop_id) {
            return;
        }

        // Check if workshop now has availability.
        if (WorkshopMetaBox::has_spots_available((int) $workshop_id)) {
            // Check if waitlist is enabled.
            if (WorkshopMetaBox::is_waitlist_enabled((int) $workshop_id)) {
                $this->notify_next_in_line((int) $workshop_id);
            }
        }
    }

    /**
     * Handle AJAX request to join waitlist.
     *
     * @return void
     */
    public function handle_join_waitlist_ajax(): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'fields_bright_waitlist')) {
            wp_send_json_error(['message' => __('Security check failed.', 'fields-bright-enrollment')]);
        }

        $workshop_id = isset($_POST['workshop_id']) ? absint($_POST['workshop_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (! $workshop_id || ! $email || ! $name) {
            wp_send_json_error(['message' => __('Please fill in all required fields.', 'fields-bright-enrollment')]);
        }

        $result = $this->add_to_waitlist($workshop_id, $email, $name, $phone);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Send waitlist confirmation email.
     *
     * @param int $entry_id Waitlist entry ID.
     *
     * @return bool
     */
    private function send_waitlist_confirmation(int $entry_id): bool
    {
        $email = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_email', true);
        $name = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_name', true);
        $position = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'position', true);
        $workshop_id = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'workshop_id', true);
        
        $workshop = get_post($workshop_id);
        $workshop_title = $workshop ? $workshop->post_title : __('Unknown Workshop', 'fields-bright-enrollment');

        $subject = sprintf(
            /* translators: %s: Workshop title */
            __('Waitlist Confirmation: %s', 'fields-bright-enrollment'),
            $workshop_title
        );

        $message = sprintf(
            /* translators: 1: Customer name, 2: Workshop title, 3: Position number */
            __("Hello %1\$s,\n\nYou've been added to the waitlist for %2\$s.\n\nYour position: #%3\$d\n\nWe'll notify you as soon as a spot becomes available.\n\nThank you for your interest!", 'fields-bright-enrollment'),
            $name,
            $workshop_title,
            $position
        );

        return wp_mail($email, $subject, $message);
    }

    /**
     * Send spot available notification email.
     *
     * @param int    $entry_id Waitlist entry ID.
     * @param string $token    Claim token for magic link.
     *
     * @return bool
     */
    private function send_spot_available_notification(int $entry_id, string $token): bool
    {
        $email = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_email', true);
        $name = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_name', true);
        $workshop_id = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'workshop_id', true);
        
        $workshop = get_post($workshop_id);
        $workshop_title = $workshop ? $workshop->post_title : __('Unknown Workshop', 'fields-bright-enrollment');
        
        // Generate magic link with token.
        $claim_url = $this->waitlist_cpt->get_claim_url($entry_id, $token, $workshop_id);

        $subject = sprintf(
            /* translators: %s: Workshop title */
            __('A Spot Just Opened: %s', 'fields-bright-enrollment'),
            $workshop_title
        );

        $message = sprintf(
            /* translators: 1: Customer name, 2: Workshop title, 3: Claim URL */
            __("Hello %1\$s,\n\nGreat news! A spot just opened up for %2\$s.\n\nYou're next in line! Click the link below to secure your spot:\n\n%3\$s\n\nThis link is reserved for you and will expire in 48 hours. Please claim your spot as soon as possible.\n\nThank you!", 'fields-bright-enrollment'),
            $name,
            $workshop_title,
            $claim_url
        );

        return wp_mail($email, $subject, $message);
    }

    /**
     * Send spot available notification email using template.
     *
     * @param int    $entry_id Waitlist entry ID.
     * @param string $token    Claim token for magic link.
     *
     * @return bool
     */
    private function send_spot_available_notification_templated(int $entry_id, string $token): bool
    {
        $email = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_email', true);
        $name = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'customer_name', true);
        $workshop_id = get_post_meta($entry_id, WaitlistCPT::META_PREFIX . 'workshop_id', true);
        
        $workshop = get_post($workshop_id);
        $workshop_title = $workshop ? $workshop->post_title : __('Unknown Workshop', 'fields-bright-enrollment');
        
        // Generate magic link with token.
        $claim_url = $this->waitlist_cpt->get_claim_url($entry_id, $token, $workshop_id);

        // Use TemplateManager if available (v1.2.0+)
        if (class_exists('FieldsBright\Enrollment\Email\TemplateManager')) {
            $template_manager = new \FieldsBright\Enrollment\Email\TemplateManager();
            
            $event_start = get_post_meta($workshop_id, '_workshop_event_start', true);
            
            $message = $template_manager->render('waitlist-notification', [
                'customer_name'   => $name,
                'workshop_title'  => $workshop_title,
                'event_start'     => $event_start,
                'claim_url'       => $claim_url,
                'hours_to_claim'  => 48,
                'site_name'       => get_bloginfo('name'),
                'site_url'        => home_url(),
                'admin_email'     => get_option('admin_email'),
            ]);
            
            $subject = sprintf(
                /* translators: %s: Workshop title */
                __('A Spot Just Opened: %s', 'fields-bright-enrollment'),
                $workshop_title
            );
            
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            return wp_mail($email, $subject, $message, $headers);
        }
        
        // Fallback to simple text email
        return $this->send_spot_available_notification($entry_id, $token);
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
}

