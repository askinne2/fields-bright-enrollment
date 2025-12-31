<?php
/**
 * Enrollment Custom Post Type Registration
 *
 * Registers the enrollment CPT with full admin UI including
 * custom columns, meta boxes, and custom statuses.
 *
 * @package FieldsBright\Enrollment\PostType
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\PostType;

use FieldsBright\Enrollment\EnrollmentSystem;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EnrollmentCPT
 *
 * Handles registration and management of the enrollment post type.
 *
 * @since 1.0.0
 */
class EnrollmentCPT
{
    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'enrollment';

    /**
     * Meta key prefix.
     *
     * @var string
     */
    public const META_PREFIX = '_enrollment_';

    /**
     * Enrollment statuses.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'refunded'  => 'Refunded',
        'failed'    => 'Failed',
    ];

    /**
     * Meta fields configuration.
     *
     * @var array<string, array>
     */
    private array $meta_fields = [];

    /**
     * Whether hooks have been registered.
     *
     * @var bool
     */
    private static bool $hooks_registered = false;

    /**
     * Constructor.
     *
     * Registers hooks for CPT registration.
     */
    public function __construct()
    {
        $this->setup_meta_fields();
        
        // Only register hooks once (prevents duplicate columns when class is instantiated multiple times)
        if (! self::$hooks_registered) {
            $this->register_hooks();
            self::$hooks_registered = true;
        }
    }

    /**
     * Setup meta fields configuration.
     *
     * @return void
     */
    private function setup_meta_fields(): void
    {
        $this->meta_fields = [
            'workshop_id' => [
                'type'              => 'integer',
                'description'       => 'Workshop post ID',
                'single'            => true,
                'sanitize_callback' => 'absint',
                'show_in_rest'      => true,
            ],
            'stripe_session_id' => [
                'type'              => 'string',
                'description'       => 'Stripe Checkout Session ID',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => false,
            ],
            'stripe_payment_intent_id' => [
                'type'              => 'string',
                'description'       => 'Stripe Payment Intent ID',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => false,
            ],
            'stripe_customer_id' => [
                'type'              => 'string',
                'description'       => 'Stripe Customer ID',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => false,
            ],
            'customer_email' => [
                'type'              => 'string',
                'description'       => 'Customer email address',
                'single'            => true,
                'sanitize_callback' => 'sanitize_email',
                'show_in_rest'      => true,
            ],
            'customer_name' => [
                'type'              => 'string',
                'description'       => 'Customer full name',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'customer_phone' => [
                'type'              => 'string',
                'description'       => 'Customer phone number',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'amount' => [
                'type'              => 'number',
                'description'       => 'Amount charged in dollars',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_amount'],
                'show_in_rest'      => true,
            ],
            'currency' => [
                'type'              => 'string',
                'description'       => 'Currency code',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'usd',
                'show_in_rest'      => true,
            ],
            'pricing_option_id' => [
                'type'              => 'string',
                'description'       => 'Selected pricing option ID',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'status' => [
                'type'              => 'string',
                'description'       => 'Enrollment status',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_status'],
                'default'           => 'pending',
                'show_in_rest'      => true,
            ],
            'date' => [
                'type'              => 'string',
                'description'       => 'Enrollment date',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'notes' => [
                'type'              => 'string',
                'description'       => 'Admin notes',
                'single'            => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'show_in_rest'      => false,
            ],
        ];
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta_fields']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_custom_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'set_sortable_columns']);
        add_action('pre_get_posts', [$this, 'handle_sortable_columns']);
        add_filter('bulk_actions-edit-' . self::POST_TYPE, [$this, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-edit-' . self::POST_TYPE, [$this, 'handle_bulk_actions'], 10, 3);
        add_action('restrict_manage_posts', [$this, 'add_filter_dropdowns']);
        add_action('pre_get_posts', [$this, 'filter_by_dropdown']);
    }

    /**
     * Register the enrollment post type.
     *
     * @return void
     */
    public function register_post_type(): void
    {
        $labels = [
            'name'                  => _x('Enrollments', 'Post Type General Name', 'fields-bright-enrollment'),
            'singular_name'         => _x('Enrollment', 'Post Type Singular Name', 'fields-bright-enrollment'),
            'menu_name'             => __('Enrollments', 'fields-bright-enrollment'),
            'name_admin_bar'        => __('Enrollment', 'fields-bright-enrollment'),
            'archives'              => __('Enrollment Archives', 'fields-bright-enrollment'),
            'attributes'            => __('Enrollment Attributes', 'fields-bright-enrollment'),
            'parent_item_colon'     => __('Parent Enrollment:', 'fields-bright-enrollment'),
            'all_items'             => __('All Enrollments', 'fields-bright-enrollment'),
            'add_new_item'          => __('Add New Enrollment', 'fields-bright-enrollment'),
            'add_new'               => __('Add New', 'fields-bright-enrollment'),
            'new_item'              => __('New Enrollment', 'fields-bright-enrollment'),
            'edit_item'             => __('Edit Enrollment', 'fields-bright-enrollment'),
            'update_item'           => __('Update Enrollment', 'fields-bright-enrollment'),
            'view_item'             => __('View Enrollment', 'fields-bright-enrollment'),
            'view_items'            => __('View Enrollments', 'fields-bright-enrollment'),
            'search_items'          => __('Search Enrollments', 'fields-bright-enrollment'),
            'not_found'             => __('No enrollments found', 'fields-bright-enrollment'),
            'not_found_in_trash'    => __('No enrollments found in Trash', 'fields-bright-enrollment'),
            'featured_image'        => __('Featured Image', 'fields-bright-enrollment'),
            'set_featured_image'    => __('Set featured image', 'fields-bright-enrollment'),
            'remove_featured_image' => __('Remove featured image', 'fields-bright-enrollment'),
            'use_featured_image'    => __('Use as featured image', 'fields-bright-enrollment'),
            'insert_into_item'      => __('Insert into enrollment', 'fields-bright-enrollment'),
            'uploaded_to_this_item' => __('Uploaded to this enrollment', 'fields-bright-enrollment'),
            'items_list'            => __('Enrollments list', 'fields-bright-enrollment'),
            'items_list_navigation' => __('Enrollments list navigation', 'fields-bright-enrollment'),
            'filter_items_list'     => __('Filter enrollments list', 'fields-bright-enrollment'),
        ];

        $args = [
            'label'               => __('Enrollment', 'fields-bright-enrollment'),
            'description'         => __('Workshop and class enrollments', 'fields-bright-enrollment'),
            'labels'              => $labels,
            'supports'            => ['title'],
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'fields-bright-enrollment', // Show under Enrollment menu
            'menu_position'       => 3,
            'menu_icon'           => 'dashicons-groups',
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => 'enrollments',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register meta fields for the enrollment post type.
     *
     * @return void
     */
    public function register_meta_fields(): void
    {
        foreach ($this->meta_fields as $key => $args) {
            register_post_meta(
                self::POST_TYPE,
                self::META_PREFIX . $key,
                $args
            );
        }
    }

    /**
     * Set custom columns for the enrollment list table.
     *
     * @param array<string, string> $columns Existing columns.
     *
     * @return array<string, string>
     */
    public function set_custom_columns(array $columns): array
    {
        $new_columns = [
            'cb'               => $columns['cb'],
            'title'            => __('Enrollment', 'fields-bright-enrollment'),
            'workshop'         => __('Workshop', 'fields-bright-enrollment'),
            'customer_name'    => __('Customer', 'fields-bright-enrollment'),
            'customer_email'   => __('Email', 'fields-bright-enrollment'),
            'amount'           => __('Amount', 'fields-bright-enrollment'),
            'enrollment_status' => __('Status', 'fields-bright-enrollment'),
            'enrollment_date'  => __('Date', 'fields-bright-enrollment'),
        ];

        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     *
     * @return void
     */
    public function render_custom_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'workshop':
                $workshop_id = get_post_meta($post_id, self::META_PREFIX . 'workshop_id', true);
                if ($workshop_id) {
                    $workshop = get_post($workshop_id);
                    if ($workshop) {
                        printf(
                            '<a href="%s">%s</a>',
                            esc_url(get_edit_post_link($workshop_id)),
                            esc_html($workshop->post_title)
                        );
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'customer_name':
                $name = get_post_meta($post_id, self::META_PREFIX . 'customer_name', true);
                echo esc_html($name ?: '—');
                break;

            case 'customer_email':
                $email = get_post_meta($post_id, self::META_PREFIX . 'customer_email', true);
                if ($email) {
                    printf(
                        '<a href="mailto:%s">%s</a>',
                        esc_attr($email),
                        esc_html($email)
                    );
                } else {
                    echo '—';
                }
                break;

            case 'amount':
                $amount = get_post_meta($post_id, self::META_PREFIX . 'amount', true);
                $currency = get_post_meta($post_id, self::META_PREFIX . 'currency', true) ?: 'usd';
                if ($amount) {
                    echo esc_html($this->format_amount($amount, $currency));
                } else {
                    echo '—';
                }
                break;

            case 'enrollment_status':
                $status = get_post_meta($post_id, self::META_PREFIX . 'status', true) ?: 'pending';
                $status_class = 'enrollment-status-' . esc_attr($status);
                $status_label = self::STATUSES[$status] ?? ucfirst($status);
                printf(
                    '<span class="enrollment-status %s">%s</span>',
                    esc_attr($status_class),
                    esc_html($status_label)
                );
                break;

            case 'enrollment_date':
                $date = get_post_meta($post_id, self::META_PREFIX . 'date', true);
                if ($date) {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date)));
                } else {
                    $post = get_post($post_id);
                    echo esc_html(get_the_date('', $post));
                }
                break;
        }
    }

    /**
     * Set sortable columns.
     *
     * @param array<string, string> $columns Existing sortable columns.
     *
     * @return array<string, string>
     */
    public function set_sortable_columns(array $columns): array
    {
        $columns['amount'] = 'enrollment_amount';
        $columns['enrollment_status'] = 'enrollment_status';
        $columns['enrollment_date'] = 'enrollment_date';
        $columns['workshop'] = 'enrollment_workshop';
        return $columns;
    }

    /**
     * Handle sortable columns in queries.
     *
     * @param \WP_Query $query The WordPress query object.
     *
     * @return void
     */
    public function handle_sortable_columns(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');

        switch ($orderby) {
            case 'enrollment_amount':
                $query->set('meta_key', self::META_PREFIX . 'amount');
                $query->set('orderby', 'meta_value_num');
                break;

            case 'enrollment_status':
                $query->set('meta_key', self::META_PREFIX . 'status');
                $query->set('orderby', 'meta_value');
                break;

            case 'enrollment_date':
                $query->set('meta_key', self::META_PREFIX . 'date');
                $query->set('orderby', 'meta_value');
                break;

            case 'enrollment_workshop':
                $query->set('meta_key', self::META_PREFIX . 'workshop_id');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    /**
     * Register bulk actions.
     *
     * @param array<string, string> $bulk_actions Existing bulk actions.
     *
     * @return array<string, string>
     */
    public function register_bulk_actions(array $bulk_actions): array
    {
        $bulk_actions['export_csv'] = __('Export to CSV', 'fields-bright-enrollment');
        $bulk_actions['mark_completed'] = __('Mark as Completed', 'fields-bright-enrollment');
        $bulk_actions['mark_refunded'] = __('Mark as Refunded', 'fields-bright-enrollment');
        return $bulk_actions;
    }

    /**
     * Handle bulk actions.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $doaction    Action name.
     * @param array  $post_ids    Array of post IDs.
     *
     * @return string
     */
    public function handle_bulk_actions(string $redirect_to, string $doaction, array $post_ids): string
    {
        if ($doaction === 'export_csv') {
            $this->export_csv($post_ids);
            exit;
        }

        if ($doaction === 'mark_completed') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, self::META_PREFIX . 'status', 'completed');
            }
            $redirect_to = add_query_arg('bulk_marked_completed', count($post_ids), $redirect_to);
        }

        if ($doaction === 'mark_refunded') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, self::META_PREFIX . 'status', 'refunded');
            }
            $redirect_to = add_query_arg('bulk_marked_refunded', count($post_ids), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * Add filter dropdowns to the admin list.
     *
     * @param string $post_type Current post type.
     *
     * @return void
     */
    public function add_filter_dropdowns(string $post_type): void
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        // Status filter
        $current_status = isset($_GET['enrollment_status_filter']) ? sanitize_text_field($_GET['enrollment_status_filter']) : '';
        ?>
        <select name="enrollment_status_filter">
            <option value=""><?php esc_html_e('All Statuses', 'fields-bright-enrollment'); ?></option>
            <?php foreach (self::STATUSES as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php

        // Workshop filter
        $current_workshop = isset($_GET['enrollment_workshop_filter']) ? absint($_GET['enrollment_workshop_filter']) : 0;
        $workshops = $this->get_workshops_with_enrollments();
        ?>
        <select name="enrollment_workshop_filter">
            <option value=""><?php esc_html_e('All Workshops', 'fields-bright-enrollment'); ?></option>
            <?php foreach ($workshops as $workshop) : ?>
                <option value="<?php echo esc_attr($workshop->ID); ?>" <?php selected($current_workshop, $workshop->ID); ?>>
                    <?php echo esc_html($workshop->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Filter enrollments by dropdown selections.
     *
     * @param \WP_Query $query The WordPress query object.
     *
     * @return void
     */
    public function filter_by_dropdown(\WP_Query $query): void
    {
        global $pagenow;

        if (! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $meta_query = [];

        if (! empty($_GET['enrollment_status_filter'])) {
            $meta_query[] = [
                'key'   => self::META_PREFIX . 'status',
                'value' => sanitize_text_field($_GET['enrollment_status_filter']),
            ];
        }

        if (! empty($_GET['enrollment_workshop_filter'])) {
            $meta_query[] = [
                'key'   => self::META_PREFIX . 'workshop_id',
                'value' => absint($_GET['enrollment_workshop_filter']),
            ];
        }

        if (! empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Export enrollments to CSV.
     *
     * @param array<int> $post_ids Array of post IDs to export.
     *
     * @return void
     */
    private function export_csv(array $post_ids): void
    {
        $filename = 'enrollments-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'ID',
            'Workshop',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Amount',
            'Currency',
            'Status',
            'Date',
            'Stripe Session ID',
            'Stripe Payment Intent ID',
        ]);

        // Data
        foreach ($post_ids as $post_id) {
            $workshop_id = get_post_meta($post_id, self::META_PREFIX . 'workshop_id', true);
            $workshop_title = $workshop_id ? get_the_title($workshop_id) : '';

            fputcsv($output, [
                $post_id,
                $workshop_title,
                get_post_meta($post_id, self::META_PREFIX . 'customer_name', true),
                get_post_meta($post_id, self::META_PREFIX . 'customer_email', true),
                get_post_meta($post_id, self::META_PREFIX . 'customer_phone', true),
                get_post_meta($post_id, self::META_PREFIX . 'amount', true),
                get_post_meta($post_id, self::META_PREFIX . 'currency', true) ?: 'usd',
                get_post_meta($post_id, self::META_PREFIX . 'status', true),
                get_post_meta($post_id, self::META_PREFIX . 'date', true),
                get_post_meta($post_id, self::META_PREFIX . 'stripe_session_id', true),
                get_post_meta($post_id, self::META_PREFIX . 'stripe_payment_intent_id', true),
            ]);
        }

        fclose($output);
    }

    /**
     * Get workshops that have enrollments.
     *
     * @return array<\WP_Post>
     */
    private function get_workshops_with_enrollments(): array
    {
        global $wpdb;

        $workshop_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            self::META_PREFIX . 'workshop_id'
        ));

        if (empty($workshop_ids)) {
            return [];
        }

        // Query both workshop CPT and legacy posts (backward compatibility)
        // Using post__in limits results naturally, but add reasonable max limit
        return get_posts([
            'post_type'      => ['workshop', 'post'],
            'post__in'       => array_map('absint', $workshop_ids),
            'posts_per_page' => 200, // Reasonable limit for admin dropdown
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }

    /**
     * Sanitize amount value.
     *
     * @param mixed $value Value to sanitize.
     *
     * @return float
     */
    public function sanitize_amount($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Sanitize status value.
     *
     * @param string $value Value to sanitize.
     *
     * @return string
     */
    public function sanitize_status(string $value): string
    {
        return array_key_exists($value, self::STATUSES) ? $value : 'pending';
    }

    /**
     * Format amount with currency.
     *
     * @param float  $amount   Amount to format.
     * @param string $currency Currency code.
     *
     * @return string
     */
    private function format_amount(float $amount, string $currency = 'usd'): string
    {
        $symbols = [
            'usd' => '$',
            'eur' => '€',
            'gbp' => '£',
        ];

        $symbol = $symbols[strtolower($currency)] ?? strtoupper($currency) . ' ';

        return $symbol . number_format($amount, 2);
    }

    /**
     * Create a new enrollment.
     *
     * @param array<string, mixed> $data Enrollment data.
     *
     * @return int|\WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_enrollment(array $data)
    {
        $workshop_title = '';
        if (! empty($data['workshop_id'])) {
            $workshop = get_post($data['workshop_id']);
            $workshop_title = $workshop ? $workshop->post_title : '';
        }

        $customer_name = $data['customer_name'] ?? '';
        $title = sprintf(
            '%s - %s',
            $workshop_title ?: __('Unknown Workshop', 'fields-bright-enrollment'),
            $customer_name ?: __('Unknown Customer', 'fields-bright-enrollment')
        );

        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta fields
        $meta_keys = [
            'workshop_id',
            'stripe_session_id',
            'stripe_payment_intent_id',
            'stripe_customer_id',
            'customer_email',
            'customer_name',
            'customer_phone',
            'amount',
            'currency',
            'pricing_option_id',
            'status',
            'date',
            'notes',
        ];

        foreach ($meta_keys as $key) {
            if (isset($data[$key])) {
                update_post_meta($post_id, self::META_PREFIX . $key, $data[$key]);
            }
        }

        // Set default date if not provided
        if (empty($data['date'])) {
            update_post_meta($post_id, self::META_PREFIX . 'date', current_time('mysql'));
        }

        // Set default status if not provided
        if (empty($data['status'])) {
            update_post_meta($post_id, self::META_PREFIX . 'status', 'pending');
        }

        // Set default currency if not provided
        if (empty($data['currency'])) {
            update_post_meta($post_id, self::META_PREFIX . 'currency', 'usd');
        }

        /**
         * Fires after an enrollment is created.
         *
         * @param int   $post_id The enrollment post ID.
         * @param array $data    The enrollment data.
         */
        do_action('fields_bright_enrollment_created', $post_id, $data);

        return $post_id;
    }

    /**
     * Get enrollment by Stripe session ID.
     *
     * @param string $session_id Stripe session ID.
     *
     * @return \WP_Post|null
     */
    public function get_by_session_id(string $session_id): ?\WP_Post
    {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => self::META_PREFIX . 'stripe_session_id',
                    'value' => $session_id,
                ],
            ],
        ]);

        return ! empty($posts) ? $posts[0] : null;
    }

    /**
     * Get all enrollments by session ID (for cart checkouts).
     *
     * @param string $session_id Stripe session ID.
     *
     * @return array<\WP_Post> Array of enrollment posts.
     */
    public function get_all_by_session_id(string $session_id): array
    {
        // Cart checkout sessions typically have max 5-10 items
        return get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 50, // More than enough for any cart checkout
            'meta_query'     => [
                [
                    'key'   => self::META_PREFIX . 'stripe_session_id',
                    'value' => $session_id,
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);
    }

    /**
     * Update enrollment status.
     *
     * @param int    $post_id Post ID.
     * @param string $status  New status.
     *
     * @return bool
     */
    public function update_status(int $post_id, string $status): bool
    {
        $status = $this->sanitize_status($status);
        $result = update_post_meta($post_id, self::META_PREFIX . 'status', $status);

        /**
         * Fires after an enrollment status is updated.
         *
         * @param int    $post_id The enrollment post ID.
         * @param string $status  The new status.
         */
        do_action('fields_bright_enrollment_status_updated', $post_id, $status);

        return (bool) $result;
    }

    /**
     * Get enrollments for a workshop.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $status      Optional status filter.
     *
     * @return array<\WP_Post>
     */
    public function get_enrollments_for_workshop(int $workshop_id, string $status = ''): array
    {
        $meta_query = [
            [
                'key'   => self::META_PREFIX . 'workshop_id',
                'value' => $workshop_id,
            ],
        ];

        if ($status && array_key_exists($status, self::STATUSES)) {
            $meta_query[] = [
                'key'   => self::META_PREFIX . 'status',
                'value' => $status,
            ];
        }

        return get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 500, // Reasonable limit for workshop enrollments
            'meta_query'     => $meta_query,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Get enrollment count for a workshop.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $status      Optional status filter.
     *
     * @return int
     */
    public function get_enrollment_count(int $workshop_id, string $status = ''): int
    {
        return count($this->get_enrollments_for_workshop($workshop_id, $status));
    }
}

