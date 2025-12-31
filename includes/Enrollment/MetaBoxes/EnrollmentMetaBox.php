<?php
/**
 * Enrollment Meta Box
 *
 * Adds meta boxes to the enrollment edit screen for viewing
 * and editing enrollment details.
 *
 * @package FieldsBright\Enrollment\MetaBoxes
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\MetaBoxes;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\PostType\WorkshopCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EnrollmentMetaBox
 *
 * Handles meta boxes on the enrollment edit screen.
 *
 * @since 1.0.0
 */
class EnrollmentMetaBox
{
    /**
     * Nonce action for meta box saves.
     *
     * @var string
     */
    private const NONCE_ACTION = 'fields_bright_enrollment_meta_box';

    /**
     * Nonce name for meta box saves.
     *
     * @var string
     */
    private const NONCE_NAME = 'fields_bright_enrollment_meta_box_nonce';

    /**
     * Constructor.
     *
     * Registers hooks for meta boxes.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . EnrollmentCPT::POST_TYPE, [$this, 'save_meta_boxes'], 10, 2);
    }

    /**
     * Register meta boxes for the enrollment post type.
     *
     * @return void
     */
    public function register_meta_boxes(): void
    {
        add_meta_box(
            'enrollment_details',
            __('Enrollment Details', 'fields-bright-enrollment'),
            [$this, 'render_details_meta_box'],
            EnrollmentCPT::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'enrollment_stripe',
            __('Stripe Information', 'fields-bright-enrollment'),
            [$this, 'render_stripe_meta_box'],
            EnrollmentCPT::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'enrollment_status',
            __('Status & Notes', 'fields-bright-enrollment'),
            [$this, 'render_status_meta_box'],
            EnrollmentCPT::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render the enrollment details meta box.
     *
     * @param \WP_Post $post The post object.
     *
     * @return void
     */
    public function render_details_meta_box(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $workshop_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
        $customer_name = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true);
        $customer_email = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true);
        $customer_phone = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_phone', true);
        $amount = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'amount', true);
        $currency = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'currency', true) ?: 'usd';
        $pricing_option = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true);
        $date = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'date', true);

        // Get workshops for dropdown (both CPT and legacy posts in workshops category)
        $workshop_cpt_posts = get_posts([
            'post_type'      => WorkshopCPT::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        
        $workshop_category_posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'category_name'  => 'workshops',
        ]);
        
        // Merge and sort by title
        $workshops = array_merge($workshop_cpt_posts, $workshop_category_posts);
        usort($workshops, function($a, $b) {
            return strcmp($a->post_title, $b->post_title);
        });
        ?>
        <table class="form-table enrollment-details-table">
            <tr>
                <th scope="row">
                    <label for="enrollment_workshop_id"><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <select name="enrollment_workshop_id" id="enrollment_workshop_id" class="regular-text">
                        <option value=""><?php esc_html_e('Select a workshop...', 'fields-bright-enrollment'); ?></option>
                        <?php foreach ($workshops as $workshop) : ?>
                            <option value="<?php echo esc_attr($workshop->ID); ?>" <?php selected($workshop_id, $workshop->ID); ?>>
                                <?php echo esc_html($workshop->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($workshop_id) : ?>
                        <p class="description">
                            <a href="<?php echo esc_url(get_edit_post_link($workshop_id)); ?>" target="_blank">
                                <?php esc_html_e('Edit Workshop', 'fields-bright-enrollment'); ?> →
                            </a>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_customer_name"><?php esc_html_e('Customer Name', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="text" name="enrollment_customer_name" id="enrollment_customer_name" 
                           class="regular-text" value="<?php echo esc_attr($customer_name); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_customer_email"><?php esc_html_e('Customer Email', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="email" name="enrollment_customer_email" id="enrollment_customer_email" 
                           class="regular-text" value="<?php echo esc_attr($customer_email); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_customer_phone"><?php esc_html_e('Customer Phone', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="tel" name="enrollment_customer_phone" id="enrollment_customer_phone" 
                           class="regular-text" value="<?php echo esc_attr($customer_phone); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_amount"><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="number" name="enrollment_amount" id="enrollment_amount" 
                           class="small-text" step="0.01" min="0" value="<?php echo esc_attr($amount); ?>">
                    <select name="enrollment_currency" id="enrollment_currency">
                        <option value="usd" <?php selected($currency, 'usd'); ?>>USD ($)</option>
                        <option value="eur" <?php selected($currency, 'eur'); ?>>EUR (€)</option>
                        <option value="gbp" <?php selected($currency, 'gbp'); ?>>GBP (£)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_pricing_option"><?php esc_html_e('Pricing Option', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="text" name="enrollment_pricing_option" id="enrollment_pricing_option" 
                           class="regular-text" value="<?php echo esc_attr($pricing_option); ?>">
                    <p class="description"><?php esc_html_e('The pricing option selected at checkout.', 'fields-bright-enrollment'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enrollment_date"><?php esc_html_e('Enrollment Date', 'fields-bright-enrollment'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" name="enrollment_date" id="enrollment_date" 
                           value="<?php echo esc_attr($date ? date('Y-m-d\TH:i', strtotime($date)) : ''); ?>">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the Stripe information meta box.
     *
     * @param \WP_Post $post The post object.
     *
     * @return void
     */
    public function render_stripe_meta_box(\WP_Post $post): void
    {
        $session_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_session_id', true);
        $payment_intent_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true);
        $customer_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_customer_id', true);
        ?>
        <table class="form-table enrollment-stripe-table">
            <tr>
                <th scope="row"><?php esc_html_e('Session ID', 'fields-bright-enrollment'); ?></th>
                <td>
                    <code><?php echo esc_html($session_id ?: '—'); ?></code>
                    <?php if ($session_id) : ?>
                        <input type="hidden" name="enrollment_stripe_session_id" value="<?php echo esc_attr($session_id); ?>">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Payment Intent ID', 'fields-bright-enrollment'); ?></th>
                <td>
                    <code><?php echo esc_html($payment_intent_id ?: '—'); ?></code>
                    <?php if ($payment_intent_id) : ?>
                        <input type="hidden" name="enrollment_stripe_payment_intent_id" value="<?php echo esc_attr($payment_intent_id); ?>">
                        <br>
                        <a href="https://dashboard.stripe.com/payments/<?php echo esc_attr($payment_intent_id); ?>" 
                           target="_blank" class="button button-small" style="margin-top: 5px;">
                            <?php esc_html_e('View in Stripe Dashboard', 'fields-bright-enrollment'); ?> →
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Customer ID', 'fields-bright-enrollment'); ?></th>
                <td>
                    <code><?php echo esc_html($customer_id ?: '—'); ?></code>
                    <?php if ($customer_id) : ?>
                        <input type="hidden" name="enrollment_stripe_customer_id" value="<?php echo esc_attr($customer_id); ?>">
                        <br>
                        <a href="https://dashboard.stripe.com/customers/<?php echo esc_attr($customer_id); ?>" 
                           target="_blank" class="button button-small" style="margin-top: 5px;">
                            <?php esc_html_e('View Customer in Stripe', 'fields-bright-enrollment'); ?> →
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <p class="description">
            <?php esc_html_e('Stripe information is set automatically during checkout and should not be modified manually.', 'fields-bright-enrollment'); ?>
        </p>
        <?php
    }

    /**
     * Render the status and notes meta box.
     *
     * @param \WP_Post $post The post object.
     *
     * @return void
     */
    public function render_status_meta_box(\WP_Post $post): void
    {
        $status = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'status', true) ?: 'pending';
        $notes = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'notes', true);
        ?>
        <p>
            <label for="enrollment_status"><strong><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></strong></label>
        </p>
        <p>
            <select name="enrollment_status" id="enrollment_status" class="widefat">
                <?php foreach (EnrollmentCPT::STATUSES as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="enrollment_notes"><strong><?php esc_html_e('Admin Notes', 'fields-bright-enrollment'); ?></strong></label>
        </p>
        <p>
            <textarea name="enrollment_notes" id="enrollment_notes" rows="5" class="widefat"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e('Notes are only visible to administrators.', 'fields-bright-enrollment'); ?>
        </p>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     *
     * @return void
     */
    public function save_meta_boxes(int $post_id, \WP_Post $post): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions.
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fields
        $fields = [
            'enrollment_workshop_id'              => ['meta_key' => 'workshop_id', 'sanitize' => 'absint'],
            'enrollment_customer_name'            => ['meta_key' => 'customer_name', 'sanitize' => 'sanitize_text_field'],
            'enrollment_customer_email'           => ['meta_key' => 'customer_email', 'sanitize' => 'sanitize_email'],
            'enrollment_customer_phone'           => ['meta_key' => 'customer_phone', 'sanitize' => 'sanitize_text_field'],
            'enrollment_amount'                   => ['meta_key' => 'amount', 'sanitize' => [$this, 'sanitize_amount']],
            'enrollment_currency'                 => ['meta_key' => 'currency', 'sanitize' => 'sanitize_text_field'],
            'enrollment_pricing_option'           => ['meta_key' => 'pricing_option_id', 'sanitize' => 'sanitize_text_field'],
            'enrollment_date'                     => ['meta_key' => 'date', 'sanitize' => [$this, 'sanitize_date']],
            'enrollment_status'                   => ['meta_key' => 'status', 'sanitize' => [$this, 'sanitize_status']],
            'enrollment_notes'                    => ['meta_key' => 'notes', 'sanitize' => 'sanitize_textarea_field'],
            'enrollment_stripe_session_id'        => ['meta_key' => 'stripe_session_id', 'sanitize' => 'sanitize_text_field'],
            'enrollment_stripe_payment_intent_id' => ['meta_key' => 'stripe_payment_intent_id', 'sanitize' => 'sanitize_text_field'],
            'enrollment_stripe_customer_id'       => ['meta_key' => 'stripe_customer_id', 'sanitize' => 'sanitize_text_field'],
        ];

        foreach ($fields as $field_name => $config) {
            if (isset($_POST[$field_name])) {
                // Use wp_unslash before sanitization for proper handling of magic quotes.
                $value = call_user_func($config['sanitize'], wp_unslash($_POST[$field_name]));
                update_post_meta($post_id, EnrollmentCPT::META_PREFIX . $config['meta_key'], $value);
            }
        }

        // Update post title based on workshop and customer
        $workshop_id = get_post_meta($post_id, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
        $customer_name = get_post_meta($post_id, EnrollmentCPT::META_PREFIX . 'customer_name', true);

        $workshop_title = $workshop_id ? get_the_title($workshop_id) : __('Unknown Workshop', 'fields-bright-enrollment');
        $customer_display = $customer_name ?: __('Unknown Customer', 'fields-bright-enrollment');

        $new_title = sprintf('%s - %s', $workshop_title, $customer_display);

        // Unhook to prevent infinite loop
        remove_action('save_post_' . EnrollmentCPT::POST_TYPE, [$this, 'save_meta_boxes'], 10);

        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $new_title,
        ]);

        // Re-hook
        add_action('save_post_' . EnrollmentCPT::POST_TYPE, [$this, 'save_meta_boxes'], 10, 2);
    }

    /**
     * Sanitize amount value.
     *
     * @param mixed $value Value to sanitize.
     *
     * @return float
     */
    private function sanitize_amount($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Sanitize date value.
     *
     * @param string $value Value to sanitize.
     *
     * @return string
     */
    private function sanitize_date(string $value): string
    {
        if (empty($value)) {
            return '';
        }
        return date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * Sanitize status value.
     *
     * @param string $value Value to sanitize.
     *
     * @return string
     */
    private function sanitize_status(string $value): string
    {
        return array_key_exists($value, EnrollmentCPT::STATUSES) ? $value : 'pending';
    }
}

