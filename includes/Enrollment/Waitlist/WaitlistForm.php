<?php
/**
 * Waitlist Form
 *
 * Provides the shortcode and rendering for the waitlist signup form.
 *
 * @package FieldsBright\Enrollment\Waitlist
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Waitlist;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WaitlistForm
 *
 * Handles the waitlist form shortcode and display.
 *
 * @since 1.1.0
 */
class WaitlistForm
{
    /**
     * Waitlist handler instance.
     *
     * @var WaitlistHandler
     */
    private WaitlistHandler $handler;

    /**
     * Constructor.
     *
     * @param WaitlistHandler|null $handler Optional waitlist handler instance.
     */
    public function __construct(?WaitlistHandler $handler = null)
    {
        $this->handler = $handler ?? new WaitlistHandler();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_shortcode('waitlist_form', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue scripts and styles.
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        // Only enqueue on pages with the shortcode or single posts.
        if (! is_singular()) {
            return;
        }

        wp_enqueue_script(
            'fields-bright-waitlist',
            get_stylesheet_directory_uri() . '/assets/js/waitlist-form.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('fields-bright-waitlist', 'fieldsBrightWaitlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fields_bright_waitlist'),
            'strings' => [
                'submitting' => __('Joining waitlist...', 'fields-bright-enrollment'),
                'success'    => __('You\'ve been added to the waitlist!', 'fields-bright-enrollment'),
                'error'      => __('Something went wrong. Please try again.', 'fields-bright-enrollment'),
            ],
        ]);
    }

    /**
     * Render the waitlist form shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_form(array $atts = []): string
    {
        $atts = shortcode_atts([
            'workshop_id' => get_the_ID(),
            'title'       => __('Join the Waitlist', 'fields-bright-enrollment'),
            'description' => __('Enter your information to be notified when a spot opens up.', 'fields-bright-enrollment'),
            'show_phone'  => 'false',
            'button_text' => __('Join Waitlist', 'fields-bright-enrollment'),
        ], $atts);

        $workshop_id = absint($atts['workshop_id']);
        $show_phone = filter_var($atts['show_phone'], FILTER_VALIDATE_BOOLEAN);

        // Check if workshop exists.
        $workshop = get_post($workshop_id);
        if (! $workshop) {
            return '';
        }

        // Check if waitlist is enabled.
        if (! WorkshopMetaBox::is_waitlist_enabled($workshop_id)) {
            return '';
        }

        // Check if workshop is actually full.
        if (! WorkshopMetaBox::is_full($workshop_id)) {
            return ''; // Workshop has spots, don't show waitlist form.
        }

        // Get current user email if logged in.
        $current_user = wp_get_current_user();
        $user_email = $current_user->ID ? $current_user->user_email : '';
        $user_name = $current_user->ID ? $current_user->display_name : '';

        // Check if user is already on waitlist.
        $existing_position = null;
        if ($user_email) {
            $existing_position = $this->handler->get_position($workshop_id, $user_email);
        }

        // Check if user is already enrolled (completed enrollment).
        $is_already_enrolled = false;
        if (is_user_logged_in()) {
            $account_handler = EnrollmentSystem::instance()->get_account_handler();
            if ($account_handler) {
                $is_already_enrolled = $account_handler->has_enrolled_in_workshop(get_current_user_id(), $workshop_id);
            }
        }

        ob_start();
        ?>
        <div class="fb-waitlist-form-wrapper" data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
            <?php if ($is_already_enrolled) : ?>
                <div class="fb-waitlist-status fb-waitlist-status--enrolled">
                    <div class="fb-waitlist-status__icon">✓</div>
                    <h3><?php esc_html_e('You\'re Already Registered!', 'fields-bright-enrollment'); ?></h3>
                    <p class="fb-waitlist-status__message">
                        <?php esc_html_e('You\'ve already enrolled in this workshop. No need to join the waitlist.', 'fields-bright-enrollment'); ?>
                    </p>
                    <p class="fb-waitlist-status__action">
                        <a href="<?php echo esc_url(home_url('/account/')); ?>" class="fb-btn fb-btn--primary">
                            <?php esc_html_e('View My Enrollments', 'fields-bright-enrollment'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif ($existing_position) : ?>
                <div class="fb-waitlist-status">
                    <div class="fb-waitlist-status__icon">✓</div>
                    <h3><?php esc_html_e('You\'re on the Waitlist!', 'fields-bright-enrollment'); ?></h3>
                    <p>
                        <?php 
                        printf(
                            /* translators: %d: Position number */
                            esc_html__('Your position: #%d', 'fields-bright-enrollment'),
                            $existing_position
                        );
                        ?>
                    </p>
                    <p class="fb-waitlist-status__message">
                        <?php esc_html_e('We\'ll notify you when a spot opens up.', 'fields-bright-enrollment'); ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="fb-waitlist-form">
                    <?php if ($atts['title']) : ?>
                    <h3 class="fb-waitlist-form__title"><?php echo esc_html($atts['title']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if ($atts['description']) : ?>
                    <p class="fb-waitlist-form__description"><?php echo esc_html($atts['description']); ?></p>
                    <?php endif; ?>

                    <form class="fb-waitlist-form__form" method="post">
                        <input type="hidden" name="workshop_id" value="<?php echo esc_attr($workshop_id); ?>">
                        
                        <div class="fb-form-field">
                            <label for="waitlist-name"><?php esc_html_e('Name', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                            <input type="text" id="waitlist-name" name="name" value="<?php echo esc_attr($user_name); ?>" required>
                        </div>

                        <div class="fb-form-field">
                            <label for="waitlist-email"><?php esc_html_e('Email', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                            <input type="email" id="waitlist-email" name="email" value="<?php echo esc_attr($user_email); ?>" required>
                        </div>

                        <?php if ($show_phone) : ?>
                        <div class="fb-form-field">
                            <label for="waitlist-phone"><?php esc_html_e('Phone', 'fields-bright-enrollment'); ?></label>
                            <input type="tel" id="waitlist-phone" name="phone" value="">
                        </div>
                        <?php endif; ?>

                        <div class="fb-form-submit">
                            <button type="submit" class="fb-btn fb-btn--primary">
                                <?php echo esc_html($atts['button_text']); ?>
                            </button>
                        </div>

                        <div class="fb-waitlist-form__message" style="display: none;"></div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .fb-waitlist-form-wrapper {
                max-width: 400px;
                margin: 0 auto;
            }
            .fb-waitlist-form {
                background: var(--fb-cream, #FDFDFC);
                padding: 30px;
                border-radius: 8px;
                border: 1px solid #eee;
            }
            .fb-waitlist-form__title {
                margin: 0 0 10px;
                font-family: var(--fb-font-heading, Georgia, serif);
                color: var(--fb-primary, #271C1A);
            }
            .fb-waitlist-form__description {
                margin: 0 0 25px;
                color: var(--fb-secondary, #666);
            }
            .fb-form-field {
                margin-bottom: 20px;
            }
            .fb-form-field label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: var(--fb-primary, #271C1A);
            }
            .fb-form-field label .required {
                color: #dc3545;
            }
            .fb-form-field input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }
            .fb-form-field input:focus {
                outline: none;
                border-color: var(--fb-accent, #F9DB5E);
                box-shadow: 0 0 0 2px rgba(249, 219, 94, 0.2);
            }
            .fb-form-submit {
                margin-top: 25px;
            }
            .fb-btn {
                display: inline-block;
                padding: 14px 28px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
                text-align: center;
            }
            .fb-btn--primary {
                background: var(--fb-accent, #F9DB5E);
                color: var(--fb-primary, #271C1A);
            }
            .fb-btn--primary:hover {
                background: var(--fb-accent-hover, #FAE075);
            }
            .fb-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            .fb-waitlist-form__message {
                margin-top: 20px;
                padding: 12px;
                border-radius: 4px;
                text-align: center;
            }
            .fb-waitlist-form__message.success {
                background: #d4edda;
                color: #155724;
            }
            .fb-waitlist-form__message.error {
                background: #f8d7da;
                color: #721c24;
            }
            .fb-waitlist-status {
                text-align: center;
                padding: 30px;
                background: #d4edda;
                border-radius: 8px;
            }
            .fb-waitlist-status__icon {
                font-size: 48px;
                color: #155724;
                margin-bottom: 10px;
            }
            .fb-waitlist-status h3 {
                margin: 0 0 10px;
                color: #155724;
            }
            .fb-waitlist-status p {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
                color: #155724;
            }
            .fb-waitlist-status__message {
                font-size: 14px !important;
                font-weight: normal !important;
                margin-top: 10px !important;
            }
            .fb-waitlist-status__action {
                margin-top: 20px !important;
            }
            .fb-waitlist-status--enrolled {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
            }
            .fb-waitlist-status--enrolled .fb-waitlist-status__icon {
                color: #0c5460;
            }
            .fb-waitlist-status--enrolled h3 {
                color: #0c5460;
            }
            .fb-waitlist-status--enrolled p {
                color: #0c5460;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}

