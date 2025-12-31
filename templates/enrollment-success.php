<?php
/**
 * Template Name: Enrollment Success
 * Template Post Type: page
 *
 * Beautiful, responsive template for displaying enrollment success after Stripe checkout.
 * Inherits styling from the Elementor site kit via the child theme's style.css.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\Cart\CartManager;

// Clear the cart after successful checkout.
$cart_manager = new CartManager();
$cart_manager->clear_cart();

/**
 * Get all enrollment details from session ID (supports cart checkouts).
 *
 * @return array{is_cart: bool, enrollments: array, customer_name: string, customer_email: string, total: float, stripe_payment_id: string, session_id: string, date: string}|null
 */
function fields_bright_get_enrollment_details(): ?array
{
    $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
    $is_cart_checkout = isset($_GET['cart']) && $_GET['cart'] === '1';

    if (empty($session_id)) {
        return null;
    }

    $enrollment_cpt = new EnrollmentCPT();

    // For cart checkouts, get all enrollments.
    if ($is_cart_checkout) {
        $enrollments_posts = $enrollment_cpt->get_all_by_session_id($session_id);

        if (empty($enrollments_posts)) {
            return null;
        }

        $enrollments = [];
        $total = 0.0;
        $customer_name = '';
        $customer_email = '';
        $stripe_payment_id = '';
        $date = '';

        foreach ($enrollments_posts as $enrollment_post) {
            $workshop_id = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
            $workshop = get_post($workshop_id);
            
            // Get customer info from first enrollment (should be same for all).
            if (empty($customer_name)) {
                $customer_name = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true);
            }
            if (empty($customer_email)) {
                $customer_email = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true);
            }
            if (empty($stripe_payment_id)) {
                $stripe_payment_id = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);
            }
            if (empty($date)) {
                $date = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'date', true);
            }

            // Get workshop details.
            $event_start = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
            $event_end = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'end_datetime', true);
            $event_location = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'location', true);
            $recurring_info = get_post_meta($workshop_id, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);
            $amount = (float) get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'amount', true);
            $pricing_option = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true);
            $status = get_post_meta($enrollment_post->ID, EnrollmentCPT::META_PREFIX . 'status', true);

            $total += $amount;

            // Get pricing option label.
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

            $enrollments[] = [
                'enrollment_id'   => $enrollment_post->ID,
                'workshop_id'     => $workshop_id,
                'workshop'        => $workshop,
                'amount'          => $amount,
                'status'          => $status,
                'event_start'     => $event_start,
                'event_end'       => $event_end,
                'event_location'  => $event_location,
                'recurring_info'  => $recurring_info,
                'pricing_option'  => $pricing_label,
            ];
        }

        return [
            'is_cart'          => true,
            'enrollments'      => $enrollments,
            'customer_name'    => $customer_name,
            'customer_email'   => $customer_email,
            'total'            => $total,
            'stripe_payment_id' => $stripe_payment_id,
            'session_id'       => $session_id,
            'date'             => $date,
            'pending'          => false, // Cart enrollments are created on webhook, so they should exist.
        ];
    }

    // Single enrollment (legacy flow).
    $workshop_id = isset($_GET['workshop']) ? absint($_GET['workshop']) : 0;
    $enrollment = $enrollment_cpt->get_by_session_id($session_id);

    if (! $enrollment) {
        // Enrollment not yet created (webhook pending), return partial info.
        $workshop = $workshop_id ? get_post($workshop_id) : null;
        
        $event_start = '';
        $event_location = '';
        $recurring_info = '';
        $checkout_price = '';
        
        if ($workshop) {
            $event_start = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
            $event_location = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'location', true);
            $recurring_info = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);
            $checkout_price = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'checkout_price', true);
        }
        
        return [
            'is_cart'          => false,
            'pending'          => true,
            'enrollment_id'    => null,
            'workshop_id'      => $workshop_id,
            'workshop'         => $workshop,
            'customer_name'    => '',
            'customer_email'   => '',
            'amount'           => $checkout_price,
            'status'           => 'pending',
            'date'             => current_time('mysql'),
            'session_id'       => $session_id,
            'event_start'      => $event_start,
            'event_location'   => $event_location,
            'recurring_info'   => $recurring_info,
            'pricing_option'   => '',
            'stripe_payment_id' => '',
        ];
    }

    $enrollment_workshop_id = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
    $workshop = get_post($enrollment_workshop_id);

    $event_start = get_post_meta($enrollment_workshop_id, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
    $event_end = get_post_meta($enrollment_workshop_id, WorkshopMetaBox::META_PREFIX . 'end_datetime', true);
    $event_location = get_post_meta($enrollment_workshop_id, WorkshopMetaBox::META_PREFIX . 'location', true);
    $recurring_info = get_post_meta($enrollment_workshop_id, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);
    $pricing_option = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true);
    $stripe_payment_id = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);

    // Get pricing option label.
    $pricing_label = '';
    if ($pricing_option) {
        $options = WorkshopMetaBox::get_pricing_options($enrollment_workshop_id);
        foreach ($options as $option) {
            if ($option['id'] === $pricing_option) {
                $pricing_label = $option['label'];
                break;
            }
        }
    }

    return [
        'is_cart'          => false,
        'pending'          => false,
        'enrollment_id'    => $enrollment->ID,
        'workshop_id'      => $enrollment_workshop_id,
        'workshop'         => $workshop,
        'customer_name'    => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true),
        'customer_email'   => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true),
        'amount'           => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', true),
        'status'           => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true),
        'date'             => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'date', true),
        'session_id'       => $session_id,
        'event_start'      => $event_start,
        'event_end'        => $event_end,
        'event_location'   => $event_location,
        'recurring_info'   => $recurring_info,
        'pricing_option'   => $pricing_label,
        'stripe_payment_id' => $stripe_payment_id,
    ];
}

$enrollment_data = fields_bright_get_enrollment_details();
$is_cart = $enrollment_data && ($enrollment_data['is_cart'] ?? false);
$is_confirmed = $enrollment_data && ! ($enrollment_data['pending'] ?? false) && (($enrollment_data['is_cart'] ?? false) || ($enrollment_data['status'] ?? '') === 'completed');
$is_pending = $enrollment_data && ($enrollment_data['pending'] ?? false);

// Get site info for receipt header
$site_name = get_bloginfo('name');
$custom_logo_id = get_theme_mod('custom_logo');
$logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'medium') : '';

// Output the theme header (includes Elementor header)
get_header();
?>

<main class="enrollment-page enrollment-success-page">
    <div class="enrollment-card">
        <?php if ($enrollment_data) : ?>
            
            <!-- Header -->
            <div class="enrollment-card__header">
                <?php if ($is_confirmed) : ?>
                    <div class="enrollment-card__icon enrollment-card__icon--success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </div>
                    <h1 class="enrollment-card__title"><?php esc_html_e('You\'re Enrolled!', 'fields-bright-enrollment'); ?></h1>
                    <p class="enrollment-card__subtitle">
                        <?php esc_html_e('Thank you for registering. Your spot has been secured.', 'fields-bright-enrollment'); ?>
                    </p>
                <?php else : ?>
                    <div class="enrollment-card__icon enrollment-card__icon--warning">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <h1 class="enrollment-card__title"><?php esc_html_e('Almost There!', 'fields-bright-enrollment'); ?></h1>
                    <p class="enrollment-card__subtitle">
                        <?php esc_html_e('Your payment is being processed. This usually takes just a moment.', 'fields-bright-enrollment'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Status Banner (for pending) -->
            <?php if ($is_pending) : ?>
                <div class="enrollment-status enrollment-status--pending">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <p><?php esc_html_e('You\'ll receive a confirmation email once your enrollment is complete.', 'fields-bright-enrollment'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Body -->
            <div class="enrollment-card__body">
                
                <!-- Receipt Header -->
                <div class="enrollment-receipt-header">
                    <?php if ($logo_url) : ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="enrollment-receipt-header__logo">
                    <?php else : ?>
                        <h2 style="margin: 0 0 0.5rem; font-family: var(--fb-font-heading);"><?php echo esc_html($site_name); ?></h2>
                    <?php endif; ?>
                    <p class="enrollment-receipt-header__business">
                        <?php esc_html_e('Therapy & Consulting', 'fields-bright-enrollment'); ?><br>
                        <?php echo esc_html(home_url()); ?>
                    </p>
                </div>
                
                <!-- Enrollment Details / Receipt -->
                <div class="enrollment-details">
                    <h2 class="enrollment-details__title"><?php esc_html_e('Enrollment Receipt', 'fields-bright-enrollment'); ?></h2>
                    <dl>
                        <!-- Transaction Info -->
                        <?php if ($is_cart && ! empty($enrollment_data['enrollments'])) : ?>
                            <?php // First enrollment ID for confirmation number. ?>
                            <dt><?php esc_html_e('Confirmation #', 'fields-bright-enrollment'); ?></dt>
                            <dd><strong>FB-<?php echo esc_html(str_pad($enrollment_data['enrollments'][0]['enrollment_id'], 6, '0', STR_PAD_LEFT)); ?></strong> <?php if (count($enrollment_data['enrollments']) > 1) : ?><span style="font-size: 0.9em; color: #666;">(<?php printf(esc_html__('%d workshops', 'fields-bright-enrollment'), count($enrollment_data['enrollments'])); ?>)</span><?php endif; ?></dd>
                        <?php elseif (! empty($enrollment_data['enrollment_id'])) : ?>
                            <dt><?php esc_html_e('Confirmation #', 'fields-bright-enrollment'); ?></dt>
                            <dd><strong>FB-<?php echo esc_html(str_pad($enrollment_data['enrollment_id'], 6, '0', STR_PAD_LEFT)); ?></strong></dd>
                        <?php endif; ?>
                        
                        <?php if (! empty($enrollment_data['date'])) : ?>
                            <dt><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></dt>
                            <dd class="datetime"><?php echo esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($enrollment_data['date']))); ?></dd>
                        <?php endif; ?>

                        <hr class="enrollment-details__divider">

                        <!-- Customer Info -->
                        <?php if (! empty($enrollment_data['customer_name'])) : ?>
                            <dt><?php esc_html_e('Customer', 'fields-bright-enrollment'); ?></dt>
                            <dd><?php echo esc_html($enrollment_data['customer_name']); ?></dd>
                        <?php endif; ?>

                        <?php if (! empty($enrollment_data['customer_email'])) : ?>
                            <dt><?php esc_html_e('Email', 'fields-bright-enrollment'); ?></dt>
                            <dd><?php echo esc_html($enrollment_data['customer_email']); ?></dd>
                        <?php endif; ?>

                        <hr class="enrollment-details__divider">

                        <!-- Workshops (Cart or Single) -->
                        <?php if ($is_cart && ! empty($enrollment_data['enrollments'])) : ?>
                            <!-- Multiple Workshops -->
                            <dt><?php esc_html_e('Workshops Enrolled', 'fields-bright-enrollment'); ?></dt>
                            <dd>
                                <div class="enrollment-workshops-list">
                                    <?php foreach ($enrollment_data['enrollments'] as $idx => $enrollment) : ?>
                                        <div class="enrollment-workshop-item">
                                            <div class="enrollment-workshop-item__header">
                                                <strong><?php echo esc_html($enrollment['workshop']->post_title); ?></strong>
                                                <span class="enrollment-workshop-item__price">$<?php echo esc_html(number_format($enrollment['amount'], 2)); ?></span>
                                            </div>
                                            <div class="enrollment-workshop-item__details">
                                                <?php if (! empty($enrollment['pricing_option'])) : ?>
                                                    <span class="enrollment-workshop-item__option"><?php echo esc_html($enrollment['pricing_option']); ?></span>
                                                <?php endif; ?>
                                                <?php if (! empty($enrollment['recurring_info'])) : ?>
                                                    <span class="enrollment-workshop-item__schedule"><?php echo esc_html($enrollment['recurring_info']); ?></span>
                                                <?php elseif (! empty($enrollment['event_start'])) : ?>
                                                    <span class="enrollment-workshop-item__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment['event_start']))); ?></span>
                                                <?php endif; ?>
                                                <?php if (! empty($enrollment['event_location'])) : ?>
                                                    <span class="enrollment-workshop-item__location"><?php echo esc_html($enrollment['event_location']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($enrollment['workshop']) : ?>
                                                <a href="<?php echo esc_url(get_permalink($enrollment['workshop']->ID)); ?>" class="enrollment-workshop-item__link"><?php esc_html_e('View Details', 'fields-bright-enrollment'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </dd>
                        <?php elseif (! empty($enrollment_data['workshop'])) : ?>
                            <!-- Single Workshop -->
                            <dt><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></dt>
                            <dd class="workshop-name"><?php echo esc_html($enrollment_data['workshop']->post_title); ?></dd>

                            <?php if (! empty($enrollment_data['recurring_info'])) : ?>
                                <dt><?php esc_html_e('Schedule', 'fields-bright-enrollment'); ?></dt>
                                <dd><?php echo esc_html($enrollment_data['recurring_info']); ?></dd>
                            <?php elseif (! empty($enrollment_data['event_start'])) : ?>
                                <dt><?php esc_html_e('Starts', 'fields-bright-enrollment'); ?></dt>
                                <dd class="datetime"><?php echo esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($enrollment_data['event_start']))); ?></dd>
                                
                                <?php if (! empty($enrollment_data['event_end'])) : ?>
                                    <dt><?php esc_html_e('Ends', 'fields-bright-enrollment'); ?></dt>
                                    <dd class="datetime"><?php echo esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($enrollment_data['event_end']))); ?></dd>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (! empty($enrollment_data['event_location'])) : ?>
                                <dt><?php esc_html_e('Location', 'fields-bright-enrollment'); ?></dt>
                                <dd><?php echo esc_html($enrollment_data['event_location']); ?></dd>
                            <?php endif; ?>

                            <?php if (! empty($enrollment_data['pricing_option'])) : ?>
                                <dt><?php esc_html_e('Option', 'fields-bright-enrollment'); ?></dt>
                                <dd><?php echo esc_html($enrollment_data['pricing_option']); ?></dd>
                            <?php endif; ?>
                        <?php endif; ?>

                        <hr class="enrollment-details__divider">

                        <!-- Payment Info -->
                        <?php if ($is_cart && isset($enrollment_data['total'])) : ?>
                            <dt class="total-label"><?php esc_html_e('Total Paid', 'fields-bright-enrollment'); ?></dt>
                            <dd class="total-amount">$<?php echo esc_html(number_format($enrollment_data['total'], 2)); ?> USD</dd>
                        <?php elseif (! empty($enrollment_data['amount'])) : ?>
                            <dt class="total-label"><?php esc_html_e('Total Paid', 'fields-bright-enrollment'); ?></dt>
                            <dd class="total-amount">$<?php echo esc_html(number_format((float) $enrollment_data['amount'], 2)); ?> USD</dd>
                        <?php endif; ?>

                        <dt><?php esc_html_e('Payment Method', 'fields-bright-enrollment'); ?></dt>
                        <dd><?php esc_html_e('Credit/Debit Card', 'fields-bright-enrollment'); ?></dd>

                        <dt><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></dt>
                        <dd>
                            <?php if ($is_confirmed) : ?>
                                <span style="color: var(--fb-success); font-weight: 600;">✓ <?php esc_html_e('Paid', 'fields-bright-enrollment'); ?></span>
                            <?php else : ?>
                                <span style="color: var(--fb-warning); font-weight: 600;">⏳ <?php esc_html_e('Processing', 'fields-bright-enrollment'); ?></span>
                            <?php endif; ?>
                        </dd>

                        <?php if (! empty($enrollment_data['stripe_payment_id'])) : ?>
                            <dt><?php esc_html_e('Transaction ID', 'fields-bright-enrollment'); ?></dt>
                            <dd class="transaction-id"><?php echo esc_html($enrollment_data['stripe_payment_id']); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Next Steps -->
                <div class="enrollment-next-steps">
                    <h3 class="enrollment-next-steps__title"><?php esc_html_e('What Happens Next', 'fields-bright-enrollment'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Check your email for a confirmation with all the details.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('You\'ll receive workshop materials and instructions before we begin.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('Mark your calendar and we\'ll see you there!', 'fields-bright-enrollment'); ?></li>
                    </ul>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="enrollment-card__footer">
                <div class="enrollment-actions">
                    <?php if ($is_cart && ! empty($enrollment_data['enrollments'])) : ?>
                        <a href="<?php echo esc_url(home_url('/workshops/')); ?>" class="enrollment-btn enrollment-btn--primary">
                            <?php esc_html_e('Browse More Workshops', 'fields-bright-enrollment'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="enrollment-btn enrollment-btn--secondary">
                            <?php esc_html_e('Return to Homepage', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php elseif (! empty($enrollment_data['workshop'])) : ?>
                        <a href="<?php echo esc_url(get_permalink($enrollment_data['workshop']->ID)); ?>" class="enrollment-btn enrollment-btn--primary">
                            <?php esc_html_e('View Workshop Details', 'fields-bright-enrollment'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="enrollment-btn enrollment-btn--secondary">
                            <?php esc_html_e('Return to Homepage', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="enrollment-btn enrollment-btn--primary">
                            <?php esc_html_e('Return to Homepage', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else : ?>
            
            <!-- Error State: No enrollment found -->
            <div class="enrollment-card__header">
                <div class="enrollment-card__icon enrollment-card__icon--warning">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </div>
                <h1 class="enrollment-card__title"><?php esc_html_e('Enrollment Not Found', 'fields-bright-enrollment'); ?></h1>
                <p class="enrollment-card__subtitle">
                    <?php esc_html_e('We couldn\'t locate your enrollment details.', 'fields-bright-enrollment'); ?>
                </p>
            </div>

            <div class="enrollment-card__body">
                <div class="enrollment-error">
                    <h2 class="enrollment-error__title"><?php esc_html_e('This may happen if:', 'fields-bright-enrollment'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Your payment is still being processed (please wait a moment)', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('The confirmation link has expired or is invalid', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('There was an issue with your session', 'fields-bright-enrollment'); ?></li>
                    </ul>
                    <p class="enrollment-error__text">
                        <?php esc_html_e('Please check your email for a confirmation, or contact us for assistance.', 'fields-bright-enrollment'); ?>
                    </p>
                </div>
            </div>

            <div class="enrollment-card__footer">
                <div class="enrollment-actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="enrollment-btn enrollment-btn--primary">
                        <?php esc_html_e('Return to Homepage', 'fields-bright-enrollment'); ?>
                    </a>
                </div>
            </div>

        <?php endif; ?>

        <!-- Help Footer -->
        <div class="enrollment-help">
            <p>
                <?php
                printf(
                    /* translators: %s: contact page link */
                    esc_html__('Questions? %s and we\'ll be happy to help.', 'fields-bright-enrollment'),
                    '<a href="' . esc_url(home_url('/contact/')) . '">' . esc_html__('Contact us', 'fields-bright-enrollment') . '</a>'
                );
                ?>
            </p>
        </div>
    </div>
</main>

<?php
// Output the theme footer (includes Elementor footer)
get_footer();
