<?php
/**
 * Waitlist Custom Post Type
 *
 * Registers the waitlist_entry CPT for tracking customers
 * waiting for spots to open in full workshops.
 *
 * @package FieldsBright\Enrollment\Waitlist
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Waitlist;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WaitlistCPT
 *
 * Handles registration and management of the waitlist entry post type.
 *
 * @since 1.1.0
 */
class WaitlistCPT
{
    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'waitlist_entry';

    /**
     * Meta key prefix.
     *
     * @var string
     */
    public const META_PREFIX = '_waitlist_';

    /**
     * Waitlist statuses.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'waiting'   => 'Waiting',
        'notified'  => 'Notified',
        'converted' => 'Converted',
        'expired'   => 'Expired',
    ];

    /**
     * Whether hooks have been registered.
     *
     * @var bool
     */
    private static bool $hooks_registered = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
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
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_custom_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'set_sortable_columns']);
        
        // Add row actions.
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
        
        // Handle manual notification action.
        add_action('admin_action_notify_waitlist', [$this, 'handle_notify_action']);
        
        // Display admin notices.
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices(): void
    {
        if (! isset($_GET['post_type']) || $_GET['post_type'] !== self::POST_TYPE) {
            return;
        }

        if (isset($_GET['notified'])) {
            $notified = $_GET['notified'] === '1';
            $class = $notified ? 'notice-success' : 'notice-error';
            $message = $notified 
                ? __('Waitlist notification sent successfully!', 'fields-bright-enrollment')
                : __('Failed to send waitlist notification. Please try again.', 'fields-bright-enrollment');
            
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
        }
    }

    /**
     * Register the waitlist entry post type.
     *
     * @return void
     */
    public function register_post_type(): void
    {
        $labels = [
            'name'               => _x('Waitlist Entries', 'Post Type General Name', 'fields-bright-enrollment'),
            'singular_name'      => _x('Waitlist Entry', 'Post Type Singular Name', 'fields-bright-enrollment'),
            'menu_name'          => __('Waitlist', 'fields-bright-enrollment'),
            'all_items'          => __('All Waitlist Entries', 'fields-bright-enrollment'),
            'add_new_item'       => __('Add New Entry', 'fields-bright-enrollment'),
            'add_new'            => __('Add New', 'fields-bright-enrollment'),
            'edit_item'          => __('Edit Entry', 'fields-bright-enrollment'),
            'update_item'        => __('Update Entry', 'fields-bright-enrollment'),
            'view_item'          => __('View Entry', 'fields-bright-enrollment'),
            'search_items'       => __('Search Entries', 'fields-bright-enrollment'),
            'not_found'          => __('No entries found', 'fields-bright-enrollment'),
            'not_found_in_trash' => __('No entries found in Trash', 'fields-bright-enrollment'),
        ];

        $args = [
            'label'               => __('Waitlist Entry', 'fields-bright-enrollment'),
            'description'         => __('Workshop waitlist entries', 'fields-bright-enrollment'),
            'labels'              => $labels,
            'supports'            => ['title'],
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'fields-bright-enrollment',
            'menu_icon'           => 'dashicons-clock',
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'show_in_rest'        => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Set custom columns for the waitlist list table.
     *
     * @param array<string, string> $columns Existing columns.
     *
     * @return array<string, string>
     */
    public function set_custom_columns(array $columns): array
    {
        return [
            'cb'              => $columns['cb'],
            'title'           => __('Entry', 'fields-bright-enrollment'),
            'workshop'        => __('Workshop', 'fields-bright-enrollment'),
            'customer_name'   => __('Customer', 'fields-bright-enrollment'),
            'customer_email'  => __('Email', 'fields-bright-enrollment'),
            'position'        => __('Position', 'fields-bright-enrollment'),
            'waitlist_status' => __('Status', 'fields-bright-enrollment'),
            'date_added'      => __('Date Added', 'fields-bright-enrollment'),
        ];
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
                $workshop = get_post($workshop_id);
                if ($workshop) {
                    echo '<a href="' . esc_url(get_edit_post_link($workshop_id)) . '">' . esc_html($workshop->post_title) . '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'customer_name':
                echo esc_html(get_post_meta($post_id, self::META_PREFIX . 'customer_name', true) ?: '—');
                break;

            case 'customer_email':
                $email = get_post_meta($post_id, self::META_PREFIX . 'customer_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'position':
                $position = get_post_meta($post_id, self::META_PREFIX . 'position', true);
                echo $position ? esc_html('#' . $position) : '—';
                break;

            case 'waitlist_status':
                $status = get_post_meta($post_id, self::META_PREFIX . 'status', true) ?: 'waiting';
                $status_label = self::STATUSES[$status] ?? ucfirst($status);
                $status_class = 'waitlist-status--' . $status;
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
                break;

            case 'date_added':
                $date = get_post_meta($post_id, self::META_PREFIX . 'date_added', true);
                echo $date ? esc_html(date_i18n(get_option('date_format'), strtotime($date))) : '—';
                break;
        }
    }

    /**
     * Set sortable columns.
     *
     * @param array<string, string> $columns Existing columns.
     *
     * @return array<string, string>
     */
    public function set_sortable_columns(array $columns): array
    {
        $columns['position'] = 'position';
        $columns['date_added'] = 'date_added';
        return $columns;
    }

    /**
     * Add row actions to waitlist entries.
     *
     * @param array    $actions Current row actions.
     * @param \WP_Post $post    Current post object.
     *
     * @return array Modified row actions.
     */
    public function add_row_actions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $actions;
        }

        $status = get_post_meta($post->ID, self::META_PREFIX . 'status', true);
        $workshop_id = get_post_meta($post->ID, self::META_PREFIX . 'workshop_id', true);

        // Add "Notify" action for waiting entries
        if ($status === 'waiting') {
            $notify_url = wp_nonce_url(
                admin_url('admin.php?action=notify_waitlist&entry_id=' . $post->ID . '&workshop_id=' . $workshop_id),
                'notify_waitlist_' . $post->ID
            );
            
            $actions['notify'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($notify_url),
                esc_html__('Notify Now', 'fields-bright-enrollment')
            );
        }

        // Add "View Workshop" action
        if ($workshop_id) {
            $actions['view_workshop'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(get_edit_post_link($workshop_id)),
                esc_html__('View Workshop', 'fields-bright-enrollment')
            );
        }

        return $actions;
    }

    /**
     * Handle manual notification action.
     *
     * @return void
     */
    public function handle_notify_action(): void
    {
        // Verify nonce and capabilities.
        $entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;
        
        if (! $entry_id || ! check_admin_referer('notify_waitlist_' . $entry_id)) {
            wp_die(__('Invalid request.', 'fields-bright-enrollment'));
        }

        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'fields-bright-enrollment'));
        }

        $workshop_id = isset($_GET['workshop_id']) ? absint($_GET['workshop_id']) : 0;
        
        if (! $workshop_id) {
            wp_die(__('Invalid workshop ID.', 'fields-bright-enrollment'));
        }

        // Trigger notification through WaitlistHandler.
        $waitlist_handler = new \FieldsBright\Enrollment\Waitlist\WaitlistHandler($this);
        $notified = $waitlist_handler->notify_next_in_line($workshop_id);

        // Redirect back with message.
        $redirect_url = add_query_arg([
            'post_type' => self::POST_TYPE,
            'notified'  => $notified ? '1' : '0',
        ], admin_url('edit.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Create a waitlist entry.
     *
     * @param array $data Entry data.
     *
     * @return int|\WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_entry(array $data)
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
            $customer_name ?: $data['customer_email'] ?? __('Unknown', 'fields-bright-enrollment')
        );

        // Calculate position.
        $position = $this->get_next_position($data['workshop_id'] ?? 0);

        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta fields.
        $meta_fields = [
            'workshop_id'     => $data['workshop_id'] ?? 0,
            'customer_email'  => $data['customer_email'] ?? '',
            'customer_name'   => $data['customer_name'] ?? '',
            'customer_phone'  => $data['customer_phone'] ?? '',
            'position'        => $position,
            'status'          => 'waiting',
            'date_added'      => current_time('mysql'),
            'notified'        => false,
            'notified_at'     => '',
            'enrollment_id'   => 0,
        ];

        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }

        return $post_id;
    }

    /**
     * Get the next position for a workshop waitlist.
     *
     * @param int $workshop_id Workshop post ID.
     *
     * @return int Next position number.
     */
    public function get_next_position(int $workshop_id): int
    {
        $entries = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => self::META_PREFIX . 'workshop_id',
                    'value' => $workshop_id,
                ],
            ],
            'meta_key'       => self::META_PREFIX . 'position',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);

        if (empty($entries)) {
            return 1;
        }

        $highest = get_post_meta($entries[0]->ID, self::META_PREFIX . 'position', true);
        return ((int) $highest) + 1;
    }

    /**
     * Get waitlist entries for a workshop.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $status      Optional status filter.
     *
     * @return array Waitlist entries.
     */
    public function get_entries_for_workshop(int $workshop_id, string $status = ''): array
    {
        $meta_query = [
            [
                'key'   => self::META_PREFIX . 'workshop_id',
                'value' => $workshop_id,
            ],
        ];

        if ($status) {
            $meta_query[] = [
                'key'   => self::META_PREFIX . 'status',
                'value' => $status,
            ];
        }

        $entries = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
            'meta_key'       => self::META_PREFIX . 'position',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        ]);

        $results = [];
        foreach ($entries as $entry) {
            $results[] = [
                'id'             => $entry->ID,
                'workshop_id'    => get_post_meta($entry->ID, self::META_PREFIX . 'workshop_id', true),
                'customer_email' => get_post_meta($entry->ID, self::META_PREFIX . 'customer_email', true),
                'customer_name'  => get_post_meta($entry->ID, self::META_PREFIX . 'customer_name', true),
                'customer_phone' => get_post_meta($entry->ID, self::META_PREFIX . 'customer_phone', true),
                'position'       => get_post_meta($entry->ID, self::META_PREFIX . 'position', true),
                'status'         => get_post_meta($entry->ID, self::META_PREFIX . 'status', true),
                'date_added'     => get_post_meta($entry->ID, self::META_PREFIX . 'date_added', true),
                'notified'       => get_post_meta($entry->ID, self::META_PREFIX . 'notified', true),
                'notified_at'    => get_post_meta($entry->ID, self::META_PREFIX . 'notified_at', true),
            ];
        }

        return $results;
    }

    /**
     * Get waitlist entry by email and workshop.
     *
     * @param string $email       Customer email.
     * @param int    $workshop_id Workshop post ID.
     *
     * @return \WP_Post|null
     */
    public function get_entry_by_email(string $email, int $workshop_id): ?\WP_Post
    {
        $entries = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => self::META_PREFIX . 'workshop_id',
                    'value' => $workshop_id,
                ],
                [
                    'key'   => self::META_PREFIX . 'customer_email',
                    'value' => $email,
                ],
            ],
        ]);

        return $entries[0] ?? null;
    }

    /**
     * Get waitlist count for a workshop.
     *
     * @param int    $workshop_id Workshop post ID.
     * @param string $status      Optional status filter.
     *
     * @return int
     */
    public function get_waitlist_count(int $workshop_id, string $status = 'waiting'): int
    {
        return count($this->get_entries_for_workshop($workshop_id, $status));
    }

    /**
     * Update entry status.
     *
     * @param int    $entry_id Entry post ID.
     * @param string $status   New status.
     *
     * @return bool
     */
    public function update_status(int $entry_id, string $status): bool
    {
        return (bool) update_post_meta($entry_id, self::META_PREFIX . 'status', $status);
    }

    /**
     * Mark entry as notified.
     *
     * @param int $entry_id Entry post ID.
     *
     * @return bool
     */
    public function mark_as_notified(int $entry_id): bool
    {
        update_post_meta($entry_id, self::META_PREFIX . 'notified', true);
        update_post_meta($entry_id, self::META_PREFIX . 'notified_at', current_time('mysql'));
        return $this->update_status($entry_id, 'notified');
    }

    /**
     * Convert entry to enrollment.
     *
     * @param int $entry_id      Entry post ID.
     * @param int $enrollment_id Enrollment post ID.
     *
     * @return bool
     */
    public function convert_to_enrollment(int $entry_id, int $enrollment_id): bool
    {
        update_post_meta($entry_id, self::META_PREFIX . 'enrollment_id', $enrollment_id);
        return $this->update_status($entry_id, 'converted');
    }

    /**
     * Generate and store a secure claim token for waitlist entry.
     *
     * Token is valid for 48 hours and allows the customer to claim their spot.
     *
     * @param int $entry_id Waitlist entry ID.
     *
     * @return string The generated token.
     */
    public function generate_claim_token(int $entry_id): string
    {
        // Generate a secure random token.
        $token = bin2hex(random_bytes(32));
        
        // Set expiration to 48 hours from now.
        $expiration = strtotime('+48 hours');
        
        // Store token and expiration.
        update_post_meta($entry_id, self::META_PREFIX . 'claim_token', $token);
        update_post_meta($entry_id, self::META_PREFIX . 'claim_token_expires', $expiration);
        
        return $token;
    }

    /**
     * Validate a claim token and return the waitlist entry if valid.
     *
     * @param string $token Claim token from URL.
     *
     * @return int|null Waitlist entry ID if valid, null otherwise.
     */
    public function validate_claim_token(string $token): ?int
    {
        if (empty($token)) {
            return null;
        }

        // Find entry by token.
        $entries = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => self::META_PREFIX . 'claim_token',
                    'value' => sanitize_text_field($token),
                ],
            ],
        ]);

        if (empty($entries)) {
            return null;
        }

        $entry_id = $entries[0]->ID;

        // Check if token is expired.
        $expiration = get_post_meta($entry_id, self::META_PREFIX . 'claim_token_expires', true);
        
        if (! $expiration || time() > $expiration) {
            // Token expired - mark entry as expired.
            $this->update_status($entry_id, 'expired');
            return null;
        }

        // Check if entry is still in notified status.
        $status = get_post_meta($entry_id, self::META_PREFIX . 'status', true);
        
        if ($status !== 'notified') {
            return null;
        }

        return $entry_id;
    }

    /**
     * Get claim URL for a waitlist entry.
     *
     * @param int    $entry_id    Waitlist entry ID.
     * @param string $token       Claim token.
     * @param int    $workshop_id Workshop ID.
     *
     * @return string The claim URL.
     */
    public function get_claim_url(int $entry_id, string $token, int $workshop_id): string
    {
        $workshop = get_post($workshop_id);
        $workshop_url = $workshop ? get_permalink($workshop_id) : home_url('/workshops/');
        
        // Add token to URL.
        return add_query_arg([
            'waitlist_token' => $token,
            'entry_id'       => $entry_id,
        ], $workshop_url);
    }
}

