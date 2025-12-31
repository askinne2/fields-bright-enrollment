<?php
/**
 * Template Name: Enrollment Cancelled
 * Template Post Type: page
 *
 * Beautiful, responsive template for displaying when a user cancels checkout.
 * Inherits styling from the Elementor site kit via the child theme's style.css.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;

$workshop_id = isset($_GET['workshop']) ? absint($_GET['workshop']) : 0;
$workshop = $workshop_id ? get_post($workshop_id) : null;

// Get workshop details if available
$event_start = '';
$event_end = '';
$event_location = '';
$display_price = '';
$checkout_price = '';
$recurring_info = '';

if ($workshop) {
    $event_start = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'start_datetime', true);
    $event_end = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'end_datetime', true);
    $event_location = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'location', true);
    $display_price = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'price', true);
    $checkout_price = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'checkout_price', true);
    $recurring_info = get_post_meta($workshop->ID, WorkshopMetaBox::META_PREFIX . 'recurring_date_info', true);
}

// Get site info
$site_name = get_bloginfo('name');
$custom_logo_id = get_theme_mod('custom_logo');
$logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'medium') : '';

// Output the theme header (includes Elementor header)
get_header();
?>

<main class="enrollment-page enrollment-cancel-page">
    <div class="enrollment-card">
        
        <!-- Header -->
        <div class="enrollment-card__header">
            <div class="enrollment-card__icon enrollment-card__icon--cancel">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </div>
            <h1 class="enrollment-card__title"><?php esc_html_e('Enrollment Cancelled', 'fields-bright-enrollment'); ?></h1>
            <p class="enrollment-card__subtitle">
                <?php esc_html_e('No worries! Your payment was not processed and you haven\'t been charged.', 'fields-bright-enrollment'); ?>
            </p>
        </div>

        <!-- Body -->
        <div class="enrollment-card__body">
            
            <?php if ($workshop) : ?>
                <!-- Workshop Details Card -->
                <div class="enrollment-details">
                    <h2 class="enrollment-details__title"><?php esc_html_e('Workshop Details', 'fields-bright-enrollment'); ?></h2>
                    <dl>
                        <dt><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></dt>
                        <dd class="workshop-name"><?php echo esc_html($workshop->post_title); ?></dd>
                        
                        <?php if (! empty($recurring_info)) : ?>
                            <dt><?php esc_html_e('Schedule', 'fields-bright-enrollment'); ?></dt>
                            <dd><?php echo esc_html($recurring_info); ?></dd>
                        <?php elseif (! empty($event_start)) : ?>
                            <dt><?php esc_html_e('Starts', 'fields-bright-enrollment'); ?></dt>
                            <dd class="datetime"><?php echo esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($event_start))); ?></dd>
                            
                            <?php if (! empty($event_end)) : ?>
                                <dt><?php esc_html_e('Ends', 'fields-bright-enrollment'); ?></dt>
                                <dd class="datetime"><?php echo esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($event_end))); ?></dd>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (! empty($event_location)) : ?>
                            <dt><?php esc_html_e('Location', 'fields-bright-enrollment'); ?></dt>
                            <dd><?php echo esc_html($event_location); ?></dd>
                        <?php endif; ?>
                        
                        <?php if (! empty($checkout_price)) : ?>
                            <hr class="enrollment-details__divider">
                            <dt class="total-label"><?php esc_html_e('Price', 'fields-bright-enrollment'); ?></dt>
                            <dd class="total-amount">$<?php echo esc_html(number_format((float) $checkout_price, 2)); ?> USD</dd>
                        <?php elseif (! empty($display_price)) : ?>
                            <hr class="enrollment-details__divider">
                            <dt class="total-label"><?php esc_html_e('Price', 'fields-bright-enrollment'); ?></dt>
                            <dd class="total-amount"><?php echo esc_html($display_price); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Encouragement Message -->
                <div class="enrollment-next-steps">
                    <h3 class="enrollment-next-steps__title"><?php esc_html_e('Changed Your Mind?', 'fields-bright-enrollment'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Your spot is still available! You can complete enrollment anytime before the workshop begins.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('Have questions? We\'re here to help you decide if this workshop is right for you.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('Browse our other workshops to find the perfect fit for your journey.', 'fields-bright-enrollment'); ?></li>
                    </ul>
                </div>

            <?php else : ?>
                
                <!-- Generic Message (no workshop context) -->
                <div class="enrollment-next-steps">
                    <h3 class="enrollment-next-steps__title"><?php esc_html_e('What Would You Like to Do?', 'fields-bright-enrollment'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Browse our workshops to find something that interests you.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('Learn more about our coaching and therapy services.', 'fields-bright-enrollment'); ?></li>
                        <li><?php esc_html_e('Reach out if you have any questions—we\'d love to hear from you.', 'fields-bright-enrollment'); ?></li>
                    </ul>
                </div>

            <?php endif; ?>

        </div>

        <!-- Footer Actions -->
        <div class="enrollment-card__footer">
            <div class="enrollment-actions">
                <?php if ($workshop) : ?>
                    <a href="<?php echo esc_url(home_url('/enroll/?workshop=' . $workshop->ID)); ?>" class="enrollment-btn enrollment-btn--primary">
                        <?php esc_html_e('Try Again', 'fields-bright-enrollment'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_permalink($workshop->ID)); ?>" class="enrollment-btn enrollment-btn--secondary">
                        <?php esc_html_e('View Workshop Details', 'fields-bright-enrollment'); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/workshops/')); ?>" class="enrollment-btn enrollment-btn--primary">
                        <?php esc_html_e('Browse Workshops', 'fields-bright-enrollment'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="enrollment-btn enrollment-btn--secondary">
                        <?php esc_html_e('Return to Homepage', 'fields-bright-enrollment'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Footer -->
        <div class="enrollment-help">
            <p>
                <?php
                printf(
                    /* translators: %s: contact page link */
                    esc_html__('Need help deciding? %s and let\'s chat.', 'fields-bright-enrollment'),
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