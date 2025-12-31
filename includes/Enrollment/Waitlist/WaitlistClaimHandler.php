<?php
/**
 * Waitlist Claim Handler
 *
 * Handles magic link token validation and spot reservation for waitlist customers.
 *
 * @package FieldsBright\Enrollment\Waitlist
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Waitlist;

use FieldsBright\Enrollment\PostType\WorkshopCPT;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WaitlistClaimHandler
 *
 * Processes waitlist claim tokens and reserves spots for customers.
 *
 * @since 1.2.0
 */
class WaitlistClaimHandler
{
    /**
     * WaitlistCPT instance.
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
     */
    public function __construct()
    {
        $this->waitlist_cpt = new WaitlistCPT();
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
        // Detect and validate waitlist token early.
        add_action('template_redirect', [$this, 'handle_waitlist_claim'], 5);
        
        // Add reserved spot banner to workshop pages.
        add_action('wp', [$this, 'show_reservation_banner'], 10);
        
        // Enqueue scripts for pre-filling form.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Handle waitlist claim token from URL.
     *
     * Validates token and stores claim data in transient.
     *
     * @return void
     */
    public function handle_waitlist_claim(): void
    {
        // Check if waitlist token is in URL.
        if (! isset($_GET['waitlist_token'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['waitlist_token']));
        $entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;

        // Validate token.
        $validated_entry_id = $this->waitlist_cpt->validate_claim_token($token);

        if (! $validated_entry_id || ($entry_id && $validated_entry_id !== $entry_id)) {
            // Token invalid or expired.
            $this->logger->warning('Waitlist claim token validation failed', [
                'entry_id' => $entry_id,
                'validated_entry_id' => $validated_entry_id,
            ]);
            wp_die(
                '<h1>' . esc_html__('Link Expired', 'fields-bright-enrollment') . '</h1>' .
                '<p>' . esc_html__('This waitlist link has expired or is invalid. The spot may have been claimed by someone else, or your reservation time has passed.', 'fields-bright-enrollment') . '</p>' .
                '<p><a href="' . esc_url(home_url('/workshops/')) . '">' . esc_html__('View Available Workshops', 'fields-bright-enrollment') . '</a></p>',
                esc_html__('Waitlist Link Expired', 'fields-bright-enrollment'),
                ['response' => 410] // 410 Gone
            );
        }

        // Get entry details.
        $workshop_id = get_post_meta($validated_entry_id, WaitlistCPT::META_PREFIX . 'workshop_id', true);
        $customer_email = get_post_meta($validated_entry_id, WaitlistCPT::META_PREFIX . 'customer_email', true);
        $customer_name = get_post_meta($validated_entry_id, WaitlistCPT::META_PREFIX . 'customer_name', true);
        $customer_phone = get_post_meta($validated_entry_id, WaitlistCPT::META_PREFIX . 'customer_phone', true);
        $token_expires = get_post_meta($validated_entry_id, WaitlistCPT::META_PREFIX . 'claim_token_expires', true);

        // Store claim data in transient (1 hour to complete checkout).
        $claim_data = [
            'entry_id'       => $validated_entry_id,
            'workshop_id'    => $workshop_id,
            'customer_email' => $customer_email,
            'customer_name'  => $customer_name,
            'customer_phone' => $customer_phone,
            'token'          => $token,
            'expires'        => time() + 3600, // 1 hour from now
            'token_expires'  => $token_expires,
        ];

        // Use session-based key (if user is logged in) or cookie-based fallback.
        $user_id = get_current_user_id();
        if ($user_id) {
            set_transient('waitlist_claim_' . $user_id, $claim_data, 3600);
        } else {
            // For non-logged-in users, store in cookie-based transient.
            $session_id = $this->get_or_create_session_id();
            set_transient('waitlist_claim_session_' . $session_id, $claim_data, 3600);
        }

        // Clean URL (remove token parameters for cleaner display).
        $clean_url = remove_query_arg(['waitlist_token', 'entry_id']);
        wp_safe_redirect($clean_url);
        exit;
    }

    /**
     * Get claim data for current user/session.
     *
     * @return array|false Claim data or false if not found.
     */
    public function get_claim_data()
    {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return get_transient('waitlist_claim_' . $user_id);
        }
        
        // Check session-based transient for non-logged-in users.
        if (isset($_COOKIE['fields_bright_session'])) {
            $session_id = sanitize_text_field(wp_unslash($_COOKIE['fields_bright_session']));
            return get_transient('waitlist_claim_session_' . $session_id);
        }
        
        return false;
    }

    /**
     * Clear claim data for current user/session.
     *
     * @return void
     */
    public function clear_claim_data(): void
    {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            delete_transient('waitlist_claim_' . $user_id);
        }
        
        if (isset($_COOKIE['fields_bright_session'])) {
            $session_id = sanitize_text_field(wp_unslash($_COOKIE['fields_bright_session']));
            delete_transient('waitlist_claim_session_' . $session_id);
        }
    }

    /**
     * Get or create session ID for non-logged-in users.
     *
     * @return string Session ID.
     */
    private function get_or_create_session_id(): string
    {
        if (isset($_COOKIE['fields_bright_session'])) {
            return sanitize_text_field(wp_unslash($_COOKIE['fields_bright_session']));
        }

        // Generate new session ID.
        $session_id = bin2hex(random_bytes(16));
        
        // Set cookie (7 days).
        setcookie('fields_bright_session', $session_id, time() + (7 * 24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        return $session_id;
    }

    /**
     * Show reservation banner on workshop page.
     *
     * @return void
     */
    public function show_reservation_banner(): void
    {
        // Check if this is a workshop (either workshop CPT or post in 'workshops' category).
        $is_workshop = is_singular(WorkshopCPT::POST_TYPE) 
            || (is_singular('post') && has_category('workshops'));
        
        // Only on single workshop pages.
        if (! $is_workshop) {
            return;
        }

        $claim_data = $this->get_claim_data();
        
        if (! $claim_data) {
            return;
        }

        // Check if this is the correct workshop.
        if ($claim_data['workshop_id'] != get_the_ID()) {
            return;
        }

        // Add banner content via action hook.
        add_action('wp_head', [$this, 'add_banner_styles']);
        add_filter('the_content', [$this, 'prepend_reservation_banner'], 5);
    }

    /**
     * Add inline styles for reservation banner.
     *
     * @return void
     */
    public function add_banner_styles(): void
    {
        ?>
        <style>
            .waitlist-claim-banner {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border: 3px solid #28a745;
                border-radius: 12px;
                padding: 25px 30px;
                margin: 30px 0;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
                animation: slideDown 0.5s ease-out;
            }
            
            .waitlist-claim-banner h3 {
                color: #155724;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 24px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .waitlist-claim-banner h3::before {
                content: "🎉";
                font-size: 32px;
            }
            
            .waitlist-claim-banner p {
                color: #155724;
                margin: 10px 0;
                font-size: 16px;
                line-height: 1.6;
            }
            
            .waitlist-claim-banner strong {
                font-weight: 600;
            }
            
            .waitlist-claim-timer {
                background: #fff;
                padding: 12px 20px;
                border-radius: 8px;
                margin-top: 15px;
                font-weight: 600;
                color: #155724;
                border-left: 4px solid #28a745;
                display: inline-block;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
        <?php
    }

    /**
     * Prepend reservation banner to content.
     *
     * @param string $content Post content.
     *
     * @return string Modified content.
     */
    public function prepend_reservation_banner(string $content): string
    {
        $claim_data = $this->get_claim_data();
        
        if (! $claim_data || $claim_data['workshop_id'] != get_the_ID()) {
            return $content;
        }

        $time_remaining = $claim_data['expires'] - time();
        $minutes_remaining = max(0, floor($time_remaining / 60));
        
        $banner = sprintf(
            '<div class="waitlist-claim-banner">
                <h3>%s</h3>
                <p><strong>%s</strong> %s</p>
                <p>%s</p>
                <div class="waitlist-claim-timer">
                    ⏰ %s
                </div>
            </div>',
            esc_html__('Your Spot is Reserved!', 'fields-bright-enrollment'),
            esc_html__('Welcome back,', 'fields-bright-enrollment'),
            esc_html($claim_data['customer_name']),
            esc_html__('This spot is reserved exclusively for you. Complete your enrollment below to secure it.', 'fields-bright-enrollment'),
            sprintf(
                /* translators: %d: Minutes remaining */
                esc_html__('Your reservation expires in %d minutes', 'fields-bright-enrollment'),
                $minutes_remaining
            )
        );

        return $banner . $content;
    }

    /**
     * Enqueue scripts for form pre-filling.
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        // Check if this is a workshop (either workshop CPT or post in 'workshops' category).
        $is_workshop = is_singular(WorkshopCPT::POST_TYPE) 
            || (is_singular('post') && has_category('workshops'));
        
        if (! $is_workshop) {
            return;
        }

        $claim_data = $this->get_claim_data();
        
        if (! $claim_data || $claim_data['workshop_id'] != get_the_ID()) {
            return;
        }

        // Pass claim data to JavaScript for form pre-filling.
        wp_localize_script('jquery', 'fieldsBrightWaitlistClaim', [
            'entry_id'       => $claim_data['entry_id'],
            'customer_email' => $claim_data['customer_email'],
            'customer_name'  => $claim_data['customer_name'],
            'customer_phone' => $claim_data['customer_phone'],
        ]);
    }
}

