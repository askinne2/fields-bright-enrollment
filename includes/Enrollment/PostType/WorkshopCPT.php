<?php
/**
 * Workshop Custom Post Type Registration
 *
 * Registers the workshop CPT with full admin UI including
 * custom columns, meta boxes, and Elementor integration.
 *
 * @package FieldsBright\Enrollment\PostType
 * @since   1.2.1
 */

namespace FieldsBright\Enrollment\PostType;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WorkshopCPT
 *
 * Handles registration and management of the workshop post type.
 *
 * @since 1.2.1
 */
class WorkshopCPT
{
    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'workshop';

    /**
     * Meta key prefix (reuse existing _event_ prefix for compatibility).
     *
     * @var string
     */
    public const META_PREFIX = '_event_';

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
        // Only register hooks once (prevents duplicate columns when class is instantiated multiple times)
        if (! self::$hooks_registered) {
            $this->register_hooks();
            self::$hooks_registered = true;
        }
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
        add_action('pre_get_posts', [$this, 'include_in_archives']);
    }

    /**
     * Register the workshop post type.
     *
     * @return void
     */
    public function register_post_type(): void
    {
        $labels = [
            'name'                  => _x('Workshops', 'Post Type General Name', 'fields-bright-enrollment'),
            'singular_name'         => _x('Workshop', 'Post Type Singular Name', 'fields-bright-enrollment'),
            'menu_name'             => __('Workshops', 'fields-bright-enrollment'),
            'name_admin_bar'        => __('Workshop', 'fields-bright-enrollment'),
            'archives'              => __('Workshop Archives', 'fields-bright-enrollment'),
            'attributes'            => __('Workshop Attributes', 'fields-bright-enrollment'),
            'parent_item_colon'     => __('Parent Workshop:', 'fields-bright-enrollment'),
            'all_items'             => __('All Workshops', 'fields-bright-enrollment'),
            'add_new_item'          => __('Add New Workshop', 'fields-bright-enrollment'),
            'add_new'               => __('Add New', 'fields-bright-enrollment'),
            'new_item'              => __('New Workshop', 'fields-bright-enrollment'),
            'edit_item'             => __('Edit Workshop', 'fields-bright-enrollment'),
            'update_item'           => __('Update Workshop', 'fields-bright-enrollment'),
            'view_item'             => __('View Workshop', 'fields-bright-enrollment'),
            'view_items'            => __('View Workshops', 'fields-bright-enrollment'),
            'search_items'          => __('Search Workshops', 'fields-bright-enrollment'),
            'not_found'             => __('No workshops found', 'fields-bright-enrollment'),
            'not_found_in_trash'    => __('No workshops found in Trash', 'fields-bright-enrollment'),
            'featured_image'        => __('Featured Image', 'fields-bright-enrollment'),
            'set_featured_image'    => __('Set featured image', 'fields-bright-enrollment'),
            'remove_featured_image' => __('Remove featured image', 'fields-bright-enrollment'),
            'use_featured_image'    => __('Use as featured image', 'fields-bright-enrollment'),
            'insert_into_item'      => __('Insert into workshop', 'fields-bright-enrollment'),
            'uploaded_to_this_item' => __('Uploaded to this workshop', 'fields-bright-enrollment'),
            'items_list'            => __('Workshops list', 'fields-bright-enrollment'),
            'items_list_navigation' => __('Workshops list navigation', 'fields-bright-enrollment'),
            'filter_items_list'     => __('Filter workshops list', 'fields-bright-enrollment'),
        ];

        $args = [
            'label'               => __('Workshop', 'fields-bright-enrollment'),
            'description'         => __('Workshops and coaching classes', 'fields-bright-enrollment'),
            'labels'              => $labels,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => 'fields-bright-enrollment', // Show under Enrollment menu
            'menu_position'       => 2,
            'menu_icon'           => 'dashicons-calendar-alt',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => 'workshops',
            'rewrite'             => [
                'slug'       => 'workshop',
                'with_front' => false,
            ],
            'taxonomies'          => ['category', 'post_tag'], // Enable categories and tags
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register meta fields for the workshop post type.
     *
     * Uses the same _event_ prefix as the old category-based workshops for compatibility.
     *
     * @return void
     */
    public function register_meta_fields(): void
    {
        $meta_fields = [
            // === Event Info Fields ===
            'start_datetime' => [
                'type'              => 'string',
                'description'       => 'Event start date and time',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'end_datetime' => [
                'type'              => 'string',
                'description'       => 'Event end date and time',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'recurring_date_info' => [
                'type'              => 'string',
                'description'       => 'Schedule description (e.g., "Every Tuesday")',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'price' => [
                'type'              => 'string',
                'description'       => 'Display price text (e.g., "$50 per person")',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'location' => [
                'type'              => 'string',
                'description'       => 'Event location',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ],
            'registration_link' => [
                'type'              => 'string',
                'description'       => 'External registration link',
                'single'            => true,
                'sanitize_callback' => 'esc_url_raw',
                'show_in_rest'      => true,
            ],
            // === Enrollment Settings ===
            // Note: show_in_rest is FALSE for enrollment fields because they are managed
            // by the Workshop Settings meta box via traditional POST (meta-box-loader).
            // This prevents Block Editor from trying to save these via REST API, which
            // causes type validation issues.
            'checkout_enabled' => [
                'type'              => 'boolean',
                'description'       => 'Whether online enrollment is enabled',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => false,
                'default'           => false,
            ],
            'checkout_price' => [
                'type'              => 'number',
                'description'       => 'Base checkout price in dollars',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_price'],
                'show_in_rest'      => false,
                'default'           => 0,
            ],
            'pricing_options' => [
                'type'              => 'string',
                'description'       => 'JSON array of pricing options',
                'single'            => true,
                'sanitize_callback' => [$this, 'sanitize_pricing_options'],
                'show_in_rest'      => false,
            ],
            'capacity' => [
                'type'              => 'integer',
                'description'       => 'Maximum enrollments (0 = unlimited)',
                'single'            => true,
                'sanitize_callback' => 'absint',
                'show_in_rest'      => false,
                'default'           => 0,
            ],
            'waitlist_enabled' => [
                'type'              => 'boolean',
                'description'       => 'Whether waitlist is enabled when full',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => false,
                'default'           => false,
            ],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta(self::POST_TYPE, self::META_PREFIX . $key, $args);
        }
    }

    /**
     * Set custom columns for the workshop list table.
     *
     * @param array<string, string> $columns Existing columns.
     *
     * @return array<string, string>
     */
    public function set_custom_columns(array $columns): array
    {
        $new_columns = [
            'cb'              => $columns['cb'],
            'title'           => __('Workshop', 'fields-bright-enrollment'),
            'workshop_date'   => __('Date', 'fields-bright-enrollment'),          
            'capacity'        => __('Capacity', 'fields-bright-enrollment'),
            'enrollments'     => __('Enrollments', 'fields-bright-enrollment'),
            'checkout_status' => __('Checkout', 'fields-bright-enrollment'),
            'date'            => $columns['date'] ?? __('Published', 'fields-bright-enrollment'),
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
            case 'workshop_date':
                $start_datetime = get_post_meta($post_id, self::META_PREFIX . 'start_datetime', true);
                if ($start_datetime) {
                    $timestamp = strtotime($start_datetime);
                    echo esc_html(date_i18n('m/d/y h:i a', $timestamp));
                } else {
                    echo '—';
                }
                break;

            case 'location':
                $location = get_post_meta($post_id, self::META_PREFIX . 'location', true);
                echo esc_html($location ?: '—');
                break;

            case 'price':
                $price = get_post_meta($post_id, self::META_PREFIX . 'price', true);
                $checkout_price = get_post_meta($post_id, self::META_PREFIX . 'checkout_price', true);
                if ($checkout_price) {
                    echo '$' . esc_html(number_format((float) $checkout_price, 2));
                } elseif ($price) {
                    echo esc_html($price);
                } else {
                    echo '—';
                }
                break;

            case 'capacity':
                $capacity = get_post_meta($post_id, self::META_PREFIX . 'capacity', true);
                echo $capacity > 0 ? esc_html($capacity) : esc_html__('Unlimited', 'fields-bright-enrollment');
                break;

            case 'enrollments':
                $enrollment_count = $this->get_enrollment_count($post_id);
                $capacity = get_post_meta($post_id, self::META_PREFIX . 'capacity', true);
                if ($capacity > 0) {
                    printf(
                        '<span class="%s">%d / %d</span>',
                        $enrollment_count >= $capacity ? 'workshop-full' : '',
                        $enrollment_count,
                        $capacity
                    );
                } else {
                    echo esc_html($enrollment_count);
                }
                break;

            case 'checkout_status':
                $checkout_enabled = get_post_meta($post_id, self::META_PREFIX . 'checkout_enabled', true);
                if ($checkout_enabled) {
                    echo '<span class="workshop-status workshop-status--enabled">' . esc_html__('✅ Enabled', 'fields-bright-enrollment') . '</span>';
                } else {
                    echo '<span class="workshop-status workshop-status--disabled">' . esc_html__('🚫 Disabled', 'fields-bright-enrollment') . '</span>';
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
        $columns['workshop_date'] = 'workshop_date';
        $columns['capacity'] = 'workshop_capacity';
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
            case 'workshop_date':
                $query->set('meta_key', self::META_PREFIX . 'start_datetime');
                $query->set('orderby', 'meta_value');
                break;

            case 'workshop_capacity':
                $query->set('meta_key', self::META_PREFIX . 'capacity');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    /**
     * Get enrollment count for a workshop.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return int
     */
    private function get_enrollment_count(int $workshop_id): int
    {
        // Use fields => 'ids' for efficiency since we only need count
        $enrollments = get_posts([
            'post_type'      => 'enrollment',
            'posts_per_page' => 1000, // High limit but not unlimited
            'fields'         => 'ids', // More efficient - only fetch IDs
            'meta_query'     => [
                [
                    'key'   => '_enrollment_workshop_id',
                    'value' => $workshop_id,
                ],
                [
                    'key'   => '_enrollment_status',
                    'value' => 'completed',
                ],
            ],
            'fields'         => 'ids',
        ]);

        return count($enrollments);
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
     * @param mixed $value Value to sanitize.
     *
     * @return string
     */
    public function sanitize_pricing_options($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $value;
            }
        }
        
        if (is_array($value)) {
            return wp_json_encode($value);
        }

        return '';
    }

    /**
     * Check if a post is a workshop.
     *
     * @param int $post_id Post ID.
     *
     * @return bool
     */
    public static function is_workshop(int $post_id): bool
    {
        return get_post_type($post_id) === self::POST_TYPE;
    }

    /**
     * Get all workshops.
     *
     * @param array $args Optional query arguments.
     *
     * @return array<\WP_Post>
     */
    public static function get_workshops(array $args = []): array
    {
        $defaults = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 100, // Reasonable limit to prevent performance issues
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ];

        return get_posts(array_merge($defaults, $args));
    }

    /**
     * Include workshop CPT in category and tag archives.
     *
     * WordPress only queries post_type='post' by default for taxonomy archives.
     * This modifies the query to include workshop CPT posts that share the same
     * category or tag, allowing mixed post type archives.
     *
     * @param \WP_Query $query The WordPress query object.
     *
     * @return void
     */
    public function include_in_archives(\WP_Query $query): void
    {
        // Only modify front-end main queries
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        // Include workshop CPT in category and tag archives
        if ($query->is_category() || $query->is_tag()) {
            $post_type = $query->get('post_type');
            
            // If post_type is not set or is 'post', include both
            if (empty($post_type) || $post_type === 'post') {
                $query->set('post_type', ['post', self::POST_TYPE]);
            }
        }
    }
}

