<?php
/**
 * Workshop Meta Box
 *
 * Provides a unified meta box for workshop posts combining event info
 * (dates, location, display price) with enrollment settings (checkout
 * pricing, capacity, Stripe integration).
 *
 * Replaces JetEngine "Event Info" meta box with native WordPress implementation.
 *
 * @package FieldsBright\Enrollment\MetaBoxes
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\MetaBoxes;

use FieldsBright\Enrollment\PostType\WorkshopCPT;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WorkshopMetaBox
 *
 * Handles the unified workshop settings meta box.
 *
 * @since 1.0.0
 */
class WorkshopMetaBox
{
    /**
     * Meta key prefix for all workshop fields.
     *
     * @var string
     */
    public const META_PREFIX = '_event_';

    /**
     * Nonce action for meta box saves.
     *
     * @var string
     */
    private const NONCE_ACTION = 'fields_bright_workshop_meta_box';

    /**
     * Nonce name for meta box saves.
     *
     * @var string
     */
    private const NONCE_NAME = 'fields_bright_workshop_meta_box_nonce';

    /**
     * Workshops category slug.
     *
     * @var string
     */
    private const WORKSHOP_CATEGORY = 'workshops';

    /**
     * Logger instance.
     *
     * @var Logger|null
     */
    private ?Logger $logger = null;

    /**
     * Constructor.
     *
     * Registers hooks for meta boxes.
     */
    public function __construct()
    {
        $this->logger = Logger::instance();
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        // Hook into both post types - posts (for backward compatibility) and workshop CPT
        add_action('save_post_post', [$this, 'save_meta_boxes'], 10, 2);
        add_action('save_post_workshop', [$this, 'save_meta_boxes'], 10, 2);
        add_action('init', [$this, 'register_meta_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Hook into REST API to handle Block Editor saves
        add_action('rest_after_insert_workshop', [$this, 'save_meta_via_rest'], 10, 3);
        add_action('rest_after_insert_post', [$this, 'save_meta_via_rest'], 10, 3);
    }

    /**
     * Get logger instance (lazy loading fallback).
     *
     * @return Logger
     */
    private function get_logger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = Logger::instance();
        }
        return $this->logger;
    }

    /**
     * Enqueue admin scripts for the meta box.
     *
     * @param string $hook The current admin page.
     *
     * @return void
     */
    public function enqueue_admin_scripts(string $hook): void
    {
        // Only load on post edit screens
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        // Enqueue jQuery UI Sortable for drag-and-drop reordering
        wp_enqueue_script('jquery-ui-sortable');

        // Sync meta box fields with Block Editor data store
        $script = "
        (function($) {
            // Wait for the Block Editor to be ready
            if (typeof wp === 'undefined' || typeof wp.data === 'undefined') {
                console.log('[Workshop Meta Box] Block Editor not detected, skipping sync');
                return;
            }

            $(document).ready(function() {
                console.log('[Workshop Meta Box] Syncing with Block Editor');

                // Helper function to update post meta in editor store
                function updateMeta(key, value) {
                    if (typeof wp.data.dispatch('core/editor').editPost === 'function') {
                        wp.data.dispatch('core/editor').editPost({
                            meta: { [key]: value }
                        });
                    }
                }

                // Sync all workshop meta fields
                $('#workshop_settings input, #workshop_settings textarea, #workshop_settings select').on('change', function() {
                    var fieldName = $(this).attr('name');
                    var fieldValue = $(this).val();
                    var metaKey = fieldName.startsWith('event_') ? '_' + fieldName : fieldName;

                    console.log('[Workshop Meta Box] Field changed:', metaKey, fieldValue);
                    updateMeta(metaKey, fieldValue);
                });

                // Sync checkbox fields
                $('#workshop_settings input[type=\"checkbox\"]').on('change', function() {
                    var fieldName = $(this).attr('name');
                    var fieldValue = $(this).is(':checked') ? '1' : '';
                    var metaKey = fieldName.startsWith('event_') ? '_' + fieldName : fieldName;

                    console.log('[Workshop Meta Box] Checkbox changed:', metaKey, fieldValue);
                    updateMeta(metaKey, fieldValue);
                });
            });
        })(jQuery);
        ";

        wp_add_inline_script('jquery-ui-sortable', $script);
    }

    /**
     * Register meta fields for the REST API.
     *
     * @return void
     */
    public function register_meta_fields(): void
    {
        // Permission callback: allow if user can edit the post
        $auth_callback = function() {
            return current_user_can('edit_posts');
        };

        $meta_fields = [
            // === Event Info Fields (migrated from JetEngine) ===
            'start_datetime' => [
                'type'              => 'string',
                'description'       => 'Event start date and time',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'end_datetime' => [
                'type'              => 'string',
                'description'       => 'Event end date and time',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'recurring_date_info' => [
                'type'              => 'string',
                'description'       => 'Recurring schedule description',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'price' => [
                'type'              => 'string',
                'description'       => 'Display price text (e.g., "$50 per person")',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'location' => [
                'type'              => 'string',
                'description'       => 'Event location',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'registration_link' => [
                'type'              => 'string',
                'description'       => 'External registration link (optional)',
                'single'            => true,
                'sanitize_callback' => 'esc_url_raw',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],

            // === Enrollment Settings Fields ===
            'checkout_enabled' => [
                'type'              => 'boolean',
                'description'       => 'Whether online enrollment is enabled',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
                'default'           => false,
            ],
            'checkout_price' => [
                'type'              => 'number',
                'description'       => 'Base checkout price for Stripe',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_price'],
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'pricing_options' => [
                'type'              => 'string',
                'description'       => 'JSON array of pricing options',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_pricing_options'],
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
            ],
            'capacity' => [
                'type'              => 'integer',
                'description'       => 'Maximum enrollments (0 = unlimited)',
                'single'            => true,
                'sanitize_callback' => 'absint',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
                'default'           => 0,
            ],
            'waitlist_enabled' => [
                'type'              => 'boolean',
                'description'       => 'Whether waitlist is enabled when full',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => true,
                'auth_callback'     => $auth_callback,
                'default'           => false,
            ],
        ];

        // Register meta for both post (backward compatibility) and workshop CPT
        foreach ($meta_fields as $key => $args) {
            register_post_meta('post', self::META_PREFIX . $key, $args);
            register_post_meta(WorkshopCPT::POST_TYPE, self::META_PREFIX . $key, $args);
        }
    }

    /**
     * Register meta boxes for workshop posts.
     *
     * @return void
     */
    public function register_meta_boxes(): void
    {
        // Register for regular posts (backward compatibility)
        add_meta_box(
            'workshop_settings',
            __('Workshop Settings', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            'post',
            'normal',
            'high'
        );

        // Register for workshop CPT
        add_meta_box(
            'workshop_settings',
            __('Workshop Settings', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            WorkshopCPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Check if current post is a workshop (either workshop CPT or post in workshops category).
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    private function is_workshop(int $post_id): bool
    {
        $post_type = get_post_type($post_id);
        
        // Workshop CPT
        if ($post_type === WorkshopCPT::POST_TYPE) {
            return true;
        }
        
        // Backward compatibility: posts in workshops category
        if ($post_type === 'post' && has_category(self::WORKSHOP_CATEGORY, $post_id)) {
            return true;
        }
        
        return false;
    }

    /**
     * Render the unified workshop settings meta box.
     *
     * @param \WP_Post $post The post object.
     *
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void
    {
        // For workshop CPT, always show the meta box
        if ($post->post_type === WorkshopCPT::POST_TYPE) {
            // Always show for workshop CPT
        }
        // For regular posts, only show for workshops category
        elseif (! $this->is_workshop($post->ID)) {
            echo '<p class="description">';
            esc_html_e('Workshop settings are only available for posts in the "Workshops" category.', 'fields-bright-enrollment');
            echo '</p>';
            return;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        // Get all meta values
        $start_datetime = get_post_meta($post->ID, self::META_PREFIX . 'start_datetime', true);
        $end_datetime = get_post_meta($post->ID, self::META_PREFIX . 'end_datetime', true);
        $recurring_info = get_post_meta($post->ID, self::META_PREFIX . 'recurring_date_info', true);
        $display_price = get_post_meta($post->ID, self::META_PREFIX . 'price', true);
        $location = get_post_meta($post->ID, self::META_PREFIX . 'location', true);
        $registration_link = get_post_meta($post->ID, self::META_PREFIX . 'registration_link', true);

        $checkout_enabled = get_post_meta($post->ID, self::META_PREFIX . 'checkout_enabled', true);
        $checkout_price = get_post_meta($post->ID, self::META_PREFIX . 'checkout_price', true);
        $pricing_options = get_post_meta($post->ID, self::META_PREFIX . 'pricing_options', true);
        $capacity = get_post_meta($post->ID, self::META_PREFIX . 'capacity', true);
        $waitlist_enabled = get_post_meta($post->ID, self::META_PREFIX . 'waitlist_enabled', true);

        // Get enrollment stats
        $enrollment_count = self::get_enrollment_count($post->ID);
        $spots_remaining = $capacity > 0 ? max(0, $capacity - $enrollment_count) : null;
        ?>
        <div class="workshop-settings-metabox">
            <style>
                .workshop-settings-metabox {
                    padding: 10px 0;
                }
                .workshop-settings-metabox .section-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 0 0 15px 0;
                    padding: 10px 0;
                    border-bottom: 2px solid #0073aa;
                    cursor: pointer;
                }
                .workshop-settings-metabox .section-header h3 {
                    margin: 0;
                    font-size: 14px;
                    color: #0073aa;
                }
                .workshop-settings-metabox .section-header .dashicons {
                    color: #0073aa;
                }
                .workshop-settings-metabox .section-content {
                    padding: 0 0 20px 0;
                }
                .workshop-settings-metabox .form-row {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 15px;
                }
                .workshop-settings-metabox .form-row.single {
                    display: block;
                }
                .workshop-settings-metabox .form-field {
                    flex: 1;
                }
                .workshop-settings-metabox .form-field label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                    color: #1d2327;
                }
                .workshop-settings-metabox .form-field input[type="text"],
                .workshop-settings-metabox .form-field input[type="url"],
                .workshop-settings-metabox .form-field input[type="number"],
                .workshop-settings-metabox .form-field input[type="datetime-local"],
                .workshop-settings-metabox .form-field textarea {
                    width: 100%;
                }
                .workshop-settings-metabox .form-field .description {
                    color: #646970;
                    font-style: italic;
                    font-size: 12px;
                    margin-top: 4px;
                }
                .workshop-settings-metabox .checkbox-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 15px;
                }
                .workshop-settings-metabox .checkbox-row label {
                    font-weight: 600;
                    margin: 0;
                }
                .workshop-settings-metabox .enrollment-toggle {
                    background: #f0f6fc;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .workshop-settings-metabox .conditional-fields {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #dcdcde;
                }
                .workshop-settings-metabox .stats-row {
                    display: flex;
                    gap: 15px;
                    margin: 15px 0;
                }
                .workshop-settings-metabox .stat-box {
                    text-align: center;
                    padding: 12px 20px;
                    background: #f6f7f7;
                    border-radius: 4px;
                    min-width: 80px;
                }
                .workshop-settings-metabox .stat-box .number {
                    font-size: 24px;
                    font-weight: 600;
                    display: block;
                    color: #1d2327;
                }
                .workshop-settings-metabox .stat-box .label {
                    font-size: 11px;
                    color: #646970;
                    text-transform: uppercase;
                }
                .workshop-settings-metabox .stat-box.warning .number {
                    color: #d63638;
                }
                .workshop-settings-metabox .pricing-options-container {
                    background: #f6f7f7;
                    border: 1px solid #dcdcde;
                    padding: 15px;
                    border-radius: 4px;
                    margin-top: 10px;
                }
                .workshop-settings-metabox .pricing-option-row {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 10px;
                    align-items: center;
                }
                .workshop-settings-metabox .pricing-option-row input {
                    flex: 1;
                }
                .workshop-settings-metabox .pricing-option-row .price-input {
                    max-width: 100px;
                }
                .workshop-settings-metabox .drag-handle {
                    cursor: grab;
                    color: #999;
                    margin-right: 8px;
                    font-size: 16px;
                }
                .workshop-settings-metabox .drag-handle:hover {
                    color: #666;
                }
                .workshop-settings-metabox .pricing-option-row.ui-sortable-helper {
                    background: #fff;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    border-radius: 4px;
                    padding: 8px;
                }
                .workshop-settings-metabox .pricing-option-row.ui-sortable-placeholder {
                    visibility: visible !important;
                    background: #f0f6fc;
                    border: 2px dashed #0073aa;
                    border-radius: 4px;
                    height: 40px;
                }
                .workshop-settings-metabox .remove-option-btn {
                    color: #d63638;
                    cursor: pointer;
                }
                .workshop-settings-metabox .remove-option-btn:hover {
                    color: #a00;
                }
                .workshop-settings-metabox .inline-fields {
                    display: flex;
                    gap: 20px;
                    align-items: flex-start;
                }
                .workshop-settings-metabox .inline-fields .form-field {
                    margin-bottom: 0;
                }
            </style>

            <!-- ============================================
                 SECTION 1: Event Information
                 ============================================ -->
            <div class="section-header" data-section="event-info">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3><?php esc_html_e('Event Information', 'fields-bright-enrollment'); ?></h3>
            </div>
            <div class="section-content" id="section-event-info">
                <div class="form-row">
                    <div class="form-field">
                        <label for="event_start_datetime"><?php esc_html_e('Start Date & Time', 'fields-bright-enrollment'); ?></label>
                        <input type="datetime-local" 
                               name="event_start_datetime" 
                               id="event_start_datetime" 
                               value="<?php echo esc_attr($start_datetime ? date('Y-m-d\TH:i', strtotime($start_datetime)) : ''); ?>">
                        <p class="description"><?php esc_html_e('When does the workshop begin?', 'fields-bright-enrollment'); ?></p>
                    </div>
                    <div class="form-field">
                        <label for="event_end_datetime"><?php esc_html_e('End Date & Time', 'fields-bright-enrollment'); ?></label>
                        <input type="datetime-local" 
                               name="event_end_datetime" 
                               id="event_end_datetime" 
                               value="<?php echo esc_attr($end_datetime ? date('Y-m-d\TH:i', strtotime($end_datetime)) : ''); ?>">
                        <p class="description"><?php esc_html_e('When does the workshop end? (Optional)', 'fields-bright-enrollment'); ?></p>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="form-field">
                        <label for="event_recurring_date_info"><?php esc_html_e('Schedule / Recurring Info', 'fields-bright-enrollment'); ?></label>
                        <input type="text" 
                               name="event_recurring_date_info" 
                               id="event_recurring_date_info" 
                               value="<?php echo esc_attr($recurring_info); ?>"
                               placeholder="<?php esc_attr_e('e.g., Every Tuesday 6-8pm, Book on Demand, etc.', 'fields-bright-enrollment'); ?>">
                        <p class="description"><?php esc_html_e('Describe the schedule for recurring events or flexible booking.', 'fields-bright-enrollment'); ?></p>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="form-field">
                        <label for="event_location"><?php esc_html_e('Location', 'fields-bright-enrollment'); ?></label>
                        <input type="text" 
                               name="event_location" 
                               id="event_location" 
                               value="<?php echo esc_attr($location); ?>"
                               placeholder="<?php esc_attr_e('e.g., Online via Zoom, 123 Main St, etc.', 'fields-bright-enrollment'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="event_price"><?php esc_html_e('Display Price', 'fields-bright-enrollment'); ?></label>
                        <input type="text" 
                               name="event_price" 
                               id="event_price" 
                               value="<?php echo esc_attr($display_price); ?>"
                               placeholder="<?php esc_attr_e('e.g., $50 per person, $180 for bundle', 'fields-bright-enrollment'); ?>">
                        <p class="description"><?php esc_html_e('Displayed on your website. Can include details like "per person" or "for couples".', 'fields-bright-enrollment'); ?></p>
                    </div>
                    <div class="form-field">
                        <label for="event_registration_link"><?php esc_html_e('External Registration Link', 'fields-bright-enrollment'); ?></label>
                        <input type="url" 
                               name="event_registration_link" 
                               id="event_registration_link" 
                               value="<?php echo esc_attr($registration_link); ?>"
                               placeholder="<?php esc_attr_e('https:// or #contact', 'fields-bright-enrollment'); ?>">
                        <p class="description"><?php esc_html_e('Optional. Use if registration is handled externally.', 'fields-bright-enrollment'); ?></p>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 SECTION 2: Online Enrollment
                 ============================================ -->
            <div class="section-header" data-section="enrollment">
                <span class="dashicons dashicons-cart"></span>
                <h3><?php esc_html_e('Online Enrollment (Stripe)', 'fields-bright-enrollment'); ?></h3>
            </div>
            <div class="section-content" id="section-enrollment">
                <div class="enrollment-toggle">
                    <div class="checkbox-row">
                        <input type="checkbox" 
                               name="event_checkout_enabled" 
                               id="event_checkout_enabled" 
                               value="1" 
                               <?php checked($checkout_enabled, true); ?>>
                        <label for="event_checkout_enabled">
                            <?php esc_html_e('Enable Online Enrollment & Payment', 'fields-bright-enrollment'); ?>
                        </label>
                    </div>
                    <p class="description" style="margin: 0;">
                        <?php esc_html_e('Allow visitors to pay and enroll directly via Stripe Checkout.', 'fields-bright-enrollment'); ?>
                    </p>
                </div>

                <div class="conditional-fields" id="enrollment-conditional-fields" <?php echo ! $checkout_enabled ? 'style="display:none;"' : ''; ?>>
                    
                    <!-- Capacity Settings -->
                    <h4 style="margin: 0 0 15px; font-size: 13px; text-transform: uppercase; color: #646970;">
                        <?php esc_html_e('Capacity & Availability', 'fields-bright-enrollment'); ?>
                    </h4>

                    <?php if ($capacity > 0 || $enrollment_count > 0) : ?>
                    <div class="stats-row">
                        <div class="stat-box">
                            <span class="number"><?php echo esc_html($enrollment_count); ?></span>
                            <span class="label"><?php esc_html_e('Enrolled', 'fields-bright-enrollment'); ?></span>
                        </div>
                        <?php if ($capacity > 0) : ?>
                        <div class="stat-box <?php echo $spots_remaining <= 0 ? 'warning' : ''; ?>">
                            <span class="number"><?php echo esc_html($spots_remaining); ?></span>
                            <span class="label"><?php esc_html_e('Spots Left', 'fields-bright-enrollment'); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="number"><?php echo esc_html($capacity); ?></span>
                            <span class="label"><?php esc_html_e('Max', 'fields-bright-enrollment'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="inline-fields" style="margin-bottom: 20px;">
                        <div class="form-field">
                            <label for="event_capacity"><?php esc_html_e('Maximum Capacity', 'fields-bright-enrollment'); ?></label>
                            <input type="number" 
                                   name="event_capacity" 
                                   id="event_capacity" 
                                   style="width: 100px;" 
                                   min="0" 
                                   value="<?php echo esc_attr($capacity ?: ''); ?>"
                                   placeholder="0">
                            <p class="description"><?php esc_html_e('Leave empty or 0 for unlimited.', 'fields-bright-enrollment'); ?></p>
                        </div>
                        <div class="form-field">
                            <label><?php esc_html_e('When Full', 'fields-bright-enrollment'); ?></label>
                            <div class="checkbox-row" style="margin-top: 5px;">
                                <input type="checkbox" 
                                       name="event_waitlist_enabled" 
                                       id="event_waitlist_enabled" 
                                       value="1" 
                                       <?php checked($waitlist_enabled, true); ?>>
                                <label for="event_waitlist_enabled" style="font-weight: normal;">
                                    <?php esc_html_e('Enable waitlist', 'fields-bright-enrollment'); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Settings -->
                    <h4 style="margin: 25px 0 15px; font-size: 13px; text-transform: uppercase; color: #646970;">
                        <?php esc_html_e('Checkout Pricing', 'fields-bright-enrollment'); ?>
                    </h4>

                    <div class="form-row single">
                        <div class="form-field">
                            <label for="event_checkout_price"><?php esc_html_e('Base Price ($)', 'fields-bright-enrollment'); ?></label>
                            <input type="number" 
                                   name="event_checkout_price" 
                                   id="event_checkout_price" 
                                   style="width: 150px;" 
                                   step="0.01" 
                                   min="0" 
                                   value="<?php echo esc_attr($checkout_price); ?>"
                                   placeholder="0.00">
                            <p class="description"><?php esc_html_e('Amount charged via Stripe. Use variable pricing below for multiple options.', 'fields-bright-enrollment'); ?></p>
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-field">
                            <label><?php esc_html_e('Variable Pricing Options', 'fields-bright-enrollment'); ?></label>
                            <div class="pricing-options-container">
                                <p class="description" style="margin: 0 0 15px;">
                                    <?php esc_html_e('Add pricing tiers (e.g., "Individual $50" vs "Couple $80"). Leave empty to use base price.', 'fields-bright-enrollment'); ?>
                                </p>
                                <div id="pricing-options-list">
                                    <?php
                                    $options = [];
                                    if ($pricing_options) {
                                        $options = json_decode($pricing_options, true) ?: [];
                                    }
                                    
                                    if (empty($options)) {
                                        $this->render_pricing_option_row(['id' => '', 'label' => '', 'price' => '', 'default' => false], 0);
                                    } else {
                                        foreach ($options as $index => $option) {
                                            $this->render_pricing_option_row($option, $index);
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="button" id="add-pricing-option" style="margin-top: 10px;">
                                    <?php esc_html_e('+ Add Option', 'fields-bright-enrollment'); ?>
                                </button>
                                <button type="button" class="button" id="debug-pricing-options" style="margin-top: 10px; margin-left: 10px; background: #f0f0f1; border-color: #2271b1; color: #2271b1;">
                                    <?php esc_html_e('🔍 Debug: Show Form Data', 'fields-bright-enrollment'); ?>
                                </button>
                                <div id="pricing-debug-output" style="display: none; margin-top: 15px; padding: 10px; background: #fff8e5; border: 1px solid #ffcc00; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/template" id="pricing-option-template">
            <?php $this->render_pricing_option_row(['id' => '', 'label' => '', 'price' => '', 'default' => false], '__INDEX__'); ?>
        </script>

        <script>
        jQuery(document).ready(function($) {
            // Prevent double initialization
            if (window.workshopMetaBoxInitialized) {
                console.log('[Workshop Meta] Already initialized, skipping');
                return;
            }
            window.workshopMetaBoxInitialized = true;
            console.log('[Workshop Meta] Initializing meta box JavaScript');

            // Toggle enrollment fields
            $('#event_checkout_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#enrollment-conditional-fields').slideDown();
                } else {
                    $('#enrollment-conditional-fields').slideUp();
                }
            });

            // Pricing options management
            var lastAddTime = 0;
            var addDebounceMs = 300; // Prevent double-clicks within 300ms
            
            var getNextIndex = function() {
                var maxIndex = -1;
                $('#pricing-options-list .pricing-option-row').each(function() {
                    var idx = parseInt($(this).data('row-index'), 10);
                    if (!isNaN(idx) && idx > maxIndex) {
                        maxIndex = idx;
                    }
                });
                return maxIndex + 1;
            };
            
            console.log('[Workshop Meta] Initial max index:', getNextIndex() - 1);
            
            // Log existing rows on load
            $('#pricing-options-list .pricing-option-row').each(function(i) {
                var $row = $(this);
                var idVal = $row.find('.pricing-option-id').val();
                var labelVal = $row.find('.pricing-option-label').val();
                var priceVal = $row.find('.price-input').val();
                var idName = $row.find('.pricing-option-id').attr('name');
                console.log('[Workshop Meta] Existing row', i, '- name:', idName, 'values:', idVal, labelVal, priceVal);
            });
            
            // Remove ALL existing click handlers from this button
            var $addBtn = $('#add-pricing-option');
            $addBtn.off(); // Remove all event handlers
            $addBtn.prop('onclick', null); // Remove inline onclick if any
            
            // Attach single handler with debounce protection
            $addBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Debounce check
                var now = Date.now();
                if (now - lastAddTime < addDebounceMs) {
                    console.log('[Workshop Meta] Debounced - ignoring duplicate click');
                    return false;
                }
                lastAddTime = now;
                
                var newIndex = getNextIndex();
                console.log('[Workshop Meta] Adding row with index:', newIndex);
                
                var template = $('#pricing-option-template').html();
                template = template.replace(/__INDEX__/g, newIndex);
                $('#pricing-options-list').append(template);
                
                console.log('[Workshop Meta] Row added successfully');
                return false;
            });

            $(document).off('click', '.remove-option-btn').on('click', '.remove-option-btn', function() {
                $(this).closest('.pricing-option-row').remove();
                reindexPricingOptions();
            });

            // Function to reindex all pricing option fields after sorting/removing
            function reindexPricingOptions() {
                $('#pricing-options-list .pricing-option-row').each(function(index) {
                    var $row = $(this);
                    $row.attr('data-row-index', index);
                    
                    // Update input names
                    $row.find('.pricing-option-id').attr('name', 'event_pricing_options[' + index + '][id]');
                    $row.find('.pricing-option-label').attr('name', 'event_pricing_options[' + index + '][label]');
                    $row.find('.price-input').attr('name', 'event_pricing_options[' + index + '][price]');
                    
                    // Update default radio value
                    var $radio = $row.find('input[type="radio"]');
                    $radio.val(index);
                });
                console.log('[Workshop Meta] Reindexed', $('#pricing-options-list .pricing-option-row').length, 'rows');
            }

            // Initialize jQuery UI Sortable for drag-and-drop reordering
            $('#pricing-options-list').sortable({
                handle: '.drag-handle',
                placeholder: 'ui-sortable-placeholder',
                axis: 'y',
                tolerance: 'pointer',
                update: function(event, ui) {
                    reindexPricingOptions();
                    console.log('[Workshop Meta] Rows reordered via drag-and-drop');
                }
            });

            // Auto-generate ID from label
            $(document).off('blur', '.pricing-option-label').on('blur', '.pricing-option-label', function() {
                var $row = $(this).closest('.pricing-option-row');
                var $idField = $row.find('.pricing-option-id');
                if ($idField.val() === '') {
                    var label = $(this).val();
                    var id = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                    $idField.val(id);
                }
            });

            // Debug button - show what will be submitted
            $('#debug-pricing-options').off('click').on('click', function(e) {
                e.preventDefault();
                var output = "=== PRICING OPTIONS DEBUG ===\n\n";
                output += "Total rows found: " + $('#pricing-options-list .pricing-option-row').length + "\n\n";
                
                $('#pricing-options-list .pricing-option-row').each(function(i) {
                    var $row = $(this);
                    var rowIndex = $row.data('row-index');
                    var $idInput = $row.find('.pricing-option-id');
                    var $labelInput = $row.find('.pricing-option-label');
                    var $priceInput = $row.find('.price-input');
                    
                    output += "Row " + i + " (data-index: " + rowIndex + "):\n";
                    output += "  ID input name: " + $idInput.attr('name') + "\n";
                    output += "  ID value: '" + $idInput.val() + "'\n";
                    output += "  Label input name: " + $labelInput.attr('name') + "\n";
                    output += "  Label value: '" + $labelInput.val() + "'\n";
                    output += "  Price input name: " + $priceInput.attr('name') + "\n";
                    output += "  Price value: '" + $priceInput.val() + "'\n\n";
                });
                
                output += "=== WHAT WILL BE POSTED ===\n";
                var formData = {};
                $('#pricing-options-list input').each(function() {
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    if (name) {
                        formData[name] = val;
                    }
                });
                output += JSON.stringify(formData, null, 2);
                
                $('#pricing-debug-output').text(output).slideDown();
                console.log('[Workshop Meta Debug]', output);
            });

            // Debug: Log form data before submission
            $(document).on('submit', 'form#post', function() {
                console.log('[Workshop Meta] Form submitting, capturing pricing data...');
                $('#pricing-options-list .pricing-option-row').each(function(i) {
                    var $row = $(this);
                    var idName = $row.find('.pricing-option-id').attr('name');
                    var idVal = $row.find('.pricing-option-id').val();
                    var labelVal = $row.find('.pricing-option-label').val();
                    var priceVal = $row.find('.price-input').val();
                    console.log('[Workshop Meta] Submit row', i, '- name:', idName, 'values:', {id: idVal, label: labelVal, price: priceVal});
                });
            });

            // Sync pricing options to Block Editor before save
            function syncPricingOptionsToEditor() {
                var pricingOptions = [];
                var defaultIndex = $('input[name="event_pricing_option_default"]:checked').val();
                
                $('#pricing-options-list .pricing-option-row').each(function(i) {
                    var $row = $(this);
                    var idVal = $row.find('.pricing-option-id').val();
                    var labelVal = $row.find('.pricing-option-label').val();
                    var priceVal = $row.find('.price-input').val();
                    
                    // Skip empty rows
                    if (!labelVal && !priceVal) {
                        return;
                    }
                    
                    // Generate ID from label if empty
                    if (!idVal && labelVal) {
                        idVal = labelVal.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                    }
                    
                    pricingOptions.push({
                        id: idVal,
                        label: labelVal,
                        price: parseFloat(priceVal) || 0,
                        default: (String(i) === String(defaultIndex))
                    });
                });
                
                var jsonOptions = JSON.stringify(pricingOptions);
                console.log('[Workshop Meta] Syncing pricing options to editor:', jsonOptions);
                
                if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                    wp.data.dispatch('core/editor').editPost({
                        meta: { '_event_pricing_options': jsonOptions }
                    });
                }
                
                return jsonOptions;
            }
            
            // Sync all meta box fields to Block Editor
            function syncAllMetaToEditor() {
                if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch('core/editor')) {
                    return;
                }
                
                var meta = {};
                
                // Sync date/time fields
                var startDateTime = $('#event_start_datetime').val();
                if (startDateTime) {
                    meta['_event_start_datetime'] = startDateTime.replace('T', ' ') + ':00';
                }
                
                var endDateTime = $('#event_end_datetime').val();
                if (endDateTime) {
                    meta['_event_end_datetime'] = endDateTime.replace('T', ' ') + ':00';
                }
                
                // Sync text fields
                meta['_event_recurring_date_info'] = $('#event_recurring_date_info').val() || '';
                meta['_event_location'] = $('#event_location').val() || '';
                meta['_event_price'] = $('#event_price').val() || '';
                meta['_event_registration_link'] = $('#event_registration_link').val() || '';
                
                // Sync enrollment settings
                meta['_event_checkout_enabled'] = $('#event_checkout_enabled').is(':checked');
                meta['_event_checkout_price'] = parseFloat($('#event_checkout_price').val()) || 0;
                meta['_event_capacity'] = parseInt($('#event_capacity').val(), 10) || 0;
                meta['_event_waitlist_enabled'] = $('#event_waitlist_enabled').is(':checked');
                
                // Sync pricing options
                syncPricingOptionsToEditor();
                
                console.log('[Workshop Meta] Syncing all meta to editor:', meta);
                wp.data.dispatch('core/editor').editPost({ meta: meta });
            }
            
            // Watch for changes in pricing options and sync
            $(document).on('change blur', '#pricing-options-list input', function() {
                syncPricingOptionsToEditor();
            });
            
            // Also intercept Gutenberg save - ensure data is synced BEFORE save starts
            if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                var wasSaving = false;
                var wasAutoSaving = false;
                
                // Initial sync when page loads
                setTimeout(function() {
                    console.log('[Workshop Meta] Initial sync to Block Editor');
                    syncAllMetaToEditor();
                }, 500);
                
                wp.data.subscribe(function() {
                    var isSaving = wp.data.select('core/editor').isSavingPost();
                    var isAutoSaving = wp.data.select('core/editor').isAutosavingPost();
                    
                    // Sync before save starts (not during autosave)
                    if (isSaving && !wasSaving && !isAutoSaving) {
                        console.log('[Workshop Meta] Gutenberg save detected, syncing meta...');
                        syncAllMetaToEditor();
                    }
                    
                    wasSaving = isSaving;
                    wasAutoSaving = isAutoSaving;
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render a pricing option row.
     *
     * @param array      $option Option data.
     * @param int|string $index  Row index.
     *
     * @return void
     */
    private function render_pricing_option_row(array $option, $index): void
    {
        $id = $option['id'] ?? '';
        $label = $option['label'] ?? '';
        $price = $option['price'] ?? '';
        $is_default = $option['default'] ?? false;
        ?>
        <div class="pricing-option-row" data-row-index="<?php echo esc_attr($index); ?>">
            <span class="drag-handle dashicons dashicons-menu" title="<?php esc_attr_e('Drag to reorder', 'fields-bright-enrollment'); ?>"></span>
            <input type="text" 
                   name="event_pricing_options[<?php echo esc_attr($index); ?>][id]" 
                   class="pricing-option-id"
                   placeholder="<?php esc_attr_e('ID', 'fields-bright-enrollment'); ?>" 
                   value="<?php echo esc_attr($id); ?>"
                   autocomplete="off"
                   style="max-width: 80px;">
            <input type="text" 
                   name="event_pricing_options[<?php echo esc_attr($index); ?>][label]" 
                   class="pricing-option-label"
                   placeholder="<?php esc_attr_e('Label (e.g., Per Couple)', 'fields-bright-enrollment'); ?>" 
                   value="<?php echo esc_attr($label); ?>"
                   autocomplete="off">
            <input type="number" 
                   name="event_pricing_options[<?php echo esc_attr($index); ?>][price]" 
                   class="price-input"
                   step="0.01" 
                   min="0" 
                   placeholder="<?php esc_attr_e('$', 'fields-bright-enrollment'); ?>" 
                   value="<?php echo esc_attr($price); ?>"
                   autocomplete="off">
            <label style="white-space: nowrap; font-weight: normal;">
                <input type="radio" 
                       name="event_pricing_option_default" 
                       value="<?php echo esc_attr($index); ?>"
                       <?php checked($is_default, true); ?>>
                <?php esc_html_e('Default', 'fields-bright-enrollment'); ?>
            </label>
            <span class="remove-option-btn dashicons dashicons-trash" title="<?php esc_attr_e('Remove', 'fields-bright-enrollment'); ?>"></span>
        </div>
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
        // Debug: Log that save was triggered
        $this->get_logger()->debug('save_meta_boxes called', ['post_id' => $post_id]);

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $this->get_logger()->debug('Skipping - this is an autosave', ['post_id' => $post_id]);
            return;
        }

        // Check permissions
        if (! current_user_can('edit_post', $post_id)) {
            $this->get_logger()->warning('Skipping - user cannot edit post', ['post_id' => $post_id]);
            return;
        }

        // Only process for workshops
        if (! $this->is_workshop($post_id)) {
            $this->get_logger()->debug('Skipping - post is not in workshops category', ['post_id' => $post_id]);
            return;
        }

        // Skip if this is a REST API request (Block Editor saves)
        // REST API has its own authentication and doesn't send meta box data
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $this->get_logger()->debug('Skipping - REST API request (Block Editor)', ['post_id' => $post_id]);
            return;
        }

        // Verify nonce (only for Classic Editor form submissions). Use wp_unslash for proper sanitization.
        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $this->get_logger()->debug('Nonce verification failed or not present', ['post_id' => $post_id]);
            return;
        }

        $this->get_logger()->debug('Processing save for workshop post', ['post_id' => $post_id]);

        // === Save Event Info Fields ===
        
        // Start datetime
        if (isset($_POST['event_start_datetime'])) {
            $value = sanitize_text_field(wp_unslash($_POST['event_start_datetime']));
            $value = $value ? date('Y-m-d H:i:s', strtotime($value)) : '';
            update_post_meta($post_id, self::META_PREFIX . 'start_datetime', $value);
        }

        // End datetime
        if (isset($_POST['event_end_datetime'])) {
            $value = sanitize_text_field(wp_unslash($_POST['event_end_datetime']));
            $value = $value ? date('Y-m-d H:i:s', strtotime($value)) : '';
            update_post_meta($post_id, self::META_PREFIX . 'end_datetime', $value);
        }

        // Recurring info
        if (isset($_POST['event_recurring_date_info'])) {
            update_post_meta($post_id, self::META_PREFIX . 'recurring_date_info', sanitize_text_field(wp_unslash($_POST['event_recurring_date_info'])));
        }

        // Location
        if (isset($_POST['event_location'])) {
            update_post_meta($post_id, self::META_PREFIX . 'location', sanitize_text_field(wp_unslash($_POST['event_location'])));
        }

        // Display price
        if (isset($_POST['event_price'])) {
            update_post_meta($post_id, self::META_PREFIX . 'price', sanitize_text_field(wp_unslash($_POST['event_price'])));
        }

        // Registration link
        if (isset($_POST['event_registration_link'])) {
            $link = sanitize_text_field(wp_unslash($_POST['event_registration_link']));
            // Allow # anchors or full URLs
            if (strpos($link, '#') === 0) {
                $link = sanitize_text_field($link);
            } else {
                $link = esc_url_raw($link);
            }
            update_post_meta($post_id, self::META_PREFIX . 'registration_link', $link);
        }

        // === Save Enrollment Settings ===
        
        // Checkout enabled
        $checkout_enabled = isset($_POST['event_checkout_enabled']) ? true : false;
        update_post_meta($post_id, self::META_PREFIX . 'checkout_enabled', $checkout_enabled);

        // Checkout price
        if (isset($_POST['event_checkout_price'])) {
            $price = $this->sanitize_price(wp_unslash($_POST['event_checkout_price']));
            update_post_meta($post_id, self::META_PREFIX . 'checkout_price', $price);
        }

        // Capacity
        $capacity = isset($_POST['event_capacity']) ? absint($_POST['event_capacity']) : 0;
        update_post_meta($post_id, self::META_PREFIX . 'capacity', $capacity);

        // Waitlist enabled
        $waitlist_enabled = isset($_POST['event_waitlist_enabled']) ? true : false;
        update_post_meta($post_id, self::META_PREFIX . 'waitlist_enabled', $waitlist_enabled);

        // Pricing options (wp_unslash handled in process_pricing_options)
        // Only process if the form field was actually submitted.
        // In meta-box-loader requests (Block Editor), the form fields may not be present,
        // so we should NOT delete existing data that was saved via REST API.
        if (isset($_POST['event_pricing_options']) && is_array($_POST['event_pricing_options'])) {
            $default_index = isset($_POST['event_pricing_option_default']) 
                ? sanitize_text_field(wp_unslash($_POST['event_pricing_option_default'])) 
                : null;
            
            $pricing_options = $this->process_pricing_options(wp_unslash($_POST['event_pricing_options']), $default_index);
            
            // Only update if we have actual data OR if this is a full form submission
            // (not a meta-box-loader partial request)
            $is_meta_box_loader = isset($_GET['meta-box-loader']) || isset($_POST['meta-box-loader']);
            
            if (!empty($pricing_options) && $pricing_options !== '[]') {
                update_post_meta($post_id, self::META_PREFIX . 'pricing_options', $pricing_options);
                $this->get_logger()->debug('Pricing options saved from POST', ['post_id' => $post_id, 'options' => $pricing_options]);
            } elseif (!$is_meta_box_loader) {
                // Only clear pricing options if this is a full form submission with intentionally empty options
                $this->get_logger()->debug('Clearing pricing options - full form submission with empty options', ['post_id' => $post_id]);
                delete_post_meta($post_id, self::META_PREFIX . 'pricing_options');
            } else {
                $this->get_logger()->debug('Skipping pricing options update - meta-box-loader with empty data', ['post_id' => $post_id]);
            }
        } else {
            // No pricing options in POST - this is likely a meta-box-loader request or partial save
            // Do NOT delete existing data as it may have been saved via REST API
            $this->get_logger()->debug('No pricing options array in POST data - preserving existing', ['post_id' => $post_id]);
        }
    }

    /**
     * Save meta fields via REST API (Block Editor saves).
     *
     * The Block Editor saves posts via REST API, and meta fields registered
     * with 'show_in_rest' are automatically handled. However, we need to ensure
     * our custom meta box data is preserved when switching between editors.
     *
     * @param \WP_Post         $post     The inserted or updated post object.
     * @param \WP_REST_Request $request  The REST request object.
     * @param bool             $creating Whether this is a new post being created.
     *
     * @return void
     */
    public function save_meta_via_rest(\WP_Post $post, \WP_REST_Request $request, bool $creating): void
    {
        $post_id = $post->ID;
        
        // Only process for workshops
        if (! $this->is_workshop($post_id)) {
            return;
        }

        $this->get_logger()->debug('REST API save detected for workshop', [
            'post_id'  => $post_id,
            'creating' => $creating,
        ]);

        // Get meta from the REST request
        $meta = $request->get_param('meta');
        
        if (! is_array($meta)) {
            $this->get_logger()->debug('No meta in REST request', ['post_id' => $post_id]);
            return;
        }

        // Meta fields are automatically saved by WordPress REST API
        // because they're registered with 'show_in_rest' => true
        // This handler is here for logging and future custom processing if needed
        $this->get_logger()->debug('Meta fields saved via REST API', [
            'post_id'     => $post_id,
            'meta_fields' => array_keys($meta),
        ]);
    }

    /**
     * Process pricing options from form submission.
     *
     * @param array       $options       Raw options array.
     * @param string|null $default_index Index of default option.
     *
     * @return string JSON encoded options.
     */
    private function process_pricing_options(array $options, ?string $default_index): string
    {
        $processed = [];

        foreach ($options as $index => $option) {
            // Debug: Log each option being processed
            $this->get_logger()->debug('Processing pricing option', [
                'index' => $index,
                'id' => $option['id'] ?? '(empty)',
                'label' => $option['label'] ?? '(empty)',
                'price' => $option['price'] ?? '(empty)',
            ]);

            // Skip empty rows - must have at least a label OR a price
            if (empty($option['label']) && empty($option['price'])) {
                $this->get_logger()->debug('Skipping empty option', ['index' => $index]);
                continue;
            }

            $id = ! empty($option['id']) 
                ? sanitize_key($option['id']) 
                : sanitize_key($option['label']);

            $processed[] = [
                'id'      => $id,
                'label'   => sanitize_text_field($option['label']),
                'price'   => $this->sanitize_price($option['price']),
                'default' => (string) $index === $default_index,
            ];
            
            $this->get_logger()->debug('Added pricing option', ['option' => end($processed)]);
        }

        $this->get_logger()->debug('Total processed pricing options', ['count' => count($processed)]);

        return wp_json_encode($processed);
    }

    /**
     * Sanitize price value.
     *
     * @param mixed $value Value to sanitize.
     *
     * @return float
     */
    public function sanitize_price($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Sanitize pricing options JSON.
     *
     * @param string $value JSON string to sanitize.
     *
     * @return string
     */
    public function sanitize_pricing_options(string $value): string
    {
        $decoded = json_decode($value, true);
        
        if (! is_array($decoded)) {
            return '';
        }

        $sanitized = [];
        foreach ($decoded as $option) {
            if (! isset($option['label']) || ! isset($option['price'])) {
                continue;
            }
            $sanitized[] = [
                'id'      => sanitize_key($option['id'] ?? $option['label']),
                'label'   => sanitize_text_field($option['label']),
                'price'   => $this->sanitize_price($option['price']),
                'default' => (bool) ($option['default'] ?? false),
            ];
        }

        return wp_json_encode($sanitized);
    }

    // =========================================================================
    // Static Helper Methods
    // =========================================================================

    /**
     * Get pricing options for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return array<array{id: string, label: string, price: float, default: bool}>
     */
    public static function get_pricing_options(int $post_id): array
    {
        $options = get_post_meta($post_id, self::META_PREFIX . 'pricing_options', true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Logger::instance()->debug('get_pricing_options', ['post_id' => $post_id, 'raw_value' => $options]);
        }
        
        if (empty($options)) {
            return [];
        }

        $decoded = json_decode($options, true);
        
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log(sprintf('[Workshop Meta] Decoded pricing options: %s', print_r($decoded, true)));
        // }
        
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the effective price for a workshop.
     *
     * @param int         $post_id        Post ID.
     * @param string|null $pricing_option Selected pricing option ID.
     *
     * @return float|null Price or null if not set.
     */
    public static function get_effective_price(int $post_id, ?string $pricing_option = null): ?float
    {
        $options = self::get_pricing_options($post_id);

        if (! empty($options)) {
            if ($pricing_option) {
                foreach ($options as $option) {
                    if ($option['id'] === $pricing_option) {
                        return (float) $option['price'];
                    }
                }
            }

            foreach ($options as $option) {
                if (! empty($option['default'])) {
                    return (float) $option['price'];
                }
            }

            if (! empty($options[0]['price'])) {
                return (float) $options[0]['price'];
            }
        }

        $base_price = get_post_meta($post_id, self::META_PREFIX . 'checkout_price', true);
        
        return $base_price ? (float) $base_price : null;
    }

    /**
     * Check if checkout is enabled for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    public static function is_checkout_enabled(int $post_id): bool
    {
        return (bool) get_post_meta($post_id, self::META_PREFIX . 'checkout_enabled', true);
    }

    /**
     * Get the capacity for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return int Capacity (0 = unlimited).
     */
    public static function get_capacity(int $post_id): int
    {
        return (int) get_post_meta($post_id, self::META_PREFIX . 'capacity', true);
    }

    /**
     * Check if waitlist is enabled for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    public static function is_waitlist_enabled(int $post_id): bool
    {
        return (bool) get_post_meta($post_id, self::META_PREFIX . 'waitlist_enabled', true);
    }

    /**
     * Get the enrollment count for a workshop.
     *
     * @param int    $post_id Post ID.
     * @param string $status  Optional status filter (default: completed).
     *
     * @return int
     */
    public static function get_enrollment_count(int $post_id, string $status = 'completed'): int
    {
        // Try to get from cache first (5 minute TTL).
        $cache_key = 'fb_enrollment_count_' . $post_id . '_' . $status;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int) $cached;
        }

        $enrollments = get_posts([
            'post_type'      => 'enrollment',
            'posts_per_page' => 1000, // High limit but not unlimited for safety
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_enrollment_workshop_id',
                    'value' => $post_id,
                ],
                [
                    'key'   => '_enrollment_status',
                    'value' => $status,
                ],
            ],
        ]);

        $count = count($enrollments);
        
        // Cache for 5 minutes.
        set_transient($cache_key, $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Clear enrollment count cache for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return void
     */
    public static function clear_enrollment_cache(int $post_id): void
    {
        $statuses = ['completed', 'pending', 'refunded', 'failed'];
        foreach ($statuses as $status) {
            delete_transient('fb_enrollment_count_' . $post_id . '_' . $status);
        }
    }

    /**
     * Get remaining spots for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return int|null Remaining spots, or null if unlimited.
     */
    public static function get_remaining_spots(int $post_id): ?int
    {
        $capacity = self::get_capacity($post_id);

        if ($capacity <= 0) {
            return null;
        }

        $enrolled = self::get_enrollment_count($post_id, 'completed');

        return max(0, $capacity - $enrolled);
    }

    /**
     * Check if a workshop has spots available.
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    public static function has_spots_available(int $post_id): bool
    {
        $remaining = self::get_remaining_spots($post_id);

        if ($remaining === null) {
            return true;
        }

        return $remaining > 0;
    }

    /**
     * Check if a workshop is full.
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    public static function is_full(int $post_id): bool
    {
        return ! self::has_spots_available($post_id);
    }

    /**
     * Check if enrollment should be allowed for a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return array{allowed: bool, reason: string, waitlist: bool}
     */
    public static function can_enroll(int $post_id): array
    {
        if (! self::is_checkout_enabled($post_id)) {
            return [
                'allowed'  => false,
                'reason'   => __('Online enrollment is not available for this workshop.', 'fields-bright-enrollment'),
                'waitlist' => false,
            ];
        }

        if (self::is_full($post_id)) {
            if (self::is_waitlist_enabled($post_id)) {
                return [
                    'allowed'  => true,
                    'reason'   => __('This workshop is full. You will be added to the waitlist.', 'fields-bright-enrollment'),
                    'waitlist' => true,
                ];
            }

            return [
                'allowed'  => false,
                'reason'   => __('This workshop is full.', 'fields-bright-enrollment'),
                'waitlist' => false,
            ];
        }

        return [
            'allowed'  => true,
            'reason'   => '',
            'waitlist' => false,
        ];
    }

    /**
     * Get event start datetime.
     *
     * @param int $post_id Post ID.
     *
     * @return string|null
     */
    public static function get_start_datetime(int $post_id): ?string
    {
        $value = get_post_meta($post_id, self::META_PREFIX . 'start_datetime', true);
        return $value ?: null;
    }

    /**
     * Get display price text.
     *
     * @param int $post_id Post ID.
     *
     * @return string
     */
    public static function get_display_price(int $post_id): string
    {
        return get_post_meta($post_id, self::META_PREFIX . 'price', true) ?: '';
    }

    /**
     * Get location.
     *
     * @param int $post_id Post ID.
     *
     * @return string
     */
    public static function get_location(int $post_id): string
    {
        return get_post_meta($post_id, self::META_PREFIX . 'location', true) ?: '';
    }
}
