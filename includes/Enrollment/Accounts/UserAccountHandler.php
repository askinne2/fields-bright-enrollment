<?php
/**
 * User Account Handler
 *
 * Manages user account creation, linking enrollments to users,
 * and user enrollment history.
 *
 * @package FieldsBright\Enrollment\Accounts
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Accounts;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\Cart\CartManager;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class UserAccountHandler
 *
 * Handles user account operations for the enrollment system.
 *
 * @since 1.1.0
 */
class UserAccountHandler
{
    /**
     * Customer role name.
     *
     * @var string
     */
    public const CUSTOMER_ROLE = 'customer';

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
        // Merge carts on login.
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);
        
        // Link enrollment to user on completion.
        add_action('fields_bright_enrollment_completed', [$this, 'link_enrollment_on_completion'], 10, 2);
        
        // Customer role restrictions.
        add_filter('show_admin_bar', [$this, 'hide_admin_bar_for_customers']);
        add_action('admin_init', [$this, 'redirect_customers_from_admin']);
        add_action('after_setup_theme', [$this, 'disable_admin_bar_for_customers']);
    }

    /**
     * Hide admin bar for customer role.
     *
     * @param bool $show Whether to show the admin bar.
     *
     * @return bool
     */
    public function hide_admin_bar_for_customers(bool $show): bool
    {
        if (! is_user_logged_in()) {
            return $show;
        }

        $user = wp_get_current_user();
        
        if (in_array(self::CUSTOMER_ROLE, (array) $user->roles, true)) {
            return false;
        }

        return $show;
    }

    /**
     * Disable admin bar completely for customers (belt and suspenders).
     *
     * @return void
     */
    public function disable_admin_bar_for_customers(): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        
        if (in_array(self::CUSTOMER_ROLE, (array) $user->roles, true)) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    /**
     * Redirect customers away from wp-admin.
     *
     * @return void
     */
    public function redirect_customers_from_admin(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        $user = wp_get_current_user();
        
        if (in_array(self::CUSTOMER_ROLE, (array) $user->roles, true)) {
            // Allow access to profile.php only.
            global $pagenow;
            $allowed_pages = ['profile.php'];
            
            if (! in_array($pagenow, $allowed_pages, true)) {
                wp_safe_redirect(home_url('/account/'));
                exit;
            }
        }
    }

    /**
     * Handle user login - merge carts.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       User object.
     *
     * @return void
     */
    public function handle_user_login(string $user_login, \WP_User $user): void
    {
        $cart_manager = new CartManager();
        $cart_manager->merge_on_login($user->ID);
    }

    /**
     * Link enrollment to user on completion.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $stripe_session Stripe session data.
     *
     * @return void
     */
    public function link_enrollment_on_completion(int $enrollment_id, array $stripe_session = []): void
    {
        $email = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_email', true);
        
        if (empty($email)) {
            return;
        }

        $this->link_enrollment_to_user($enrollment_id, $email);
    }

    /**
     * Link an enrollment to a user account.
     *
     * Creates user if doesn't exist.
     *
     * @param int    $enrollment_id Enrollment post ID.
     * @param string $email         Customer email.
     *
     * @return int|false User ID on success, false on failure.
     */
    public function link_enrollment_to_user(int $enrollment_id, string $email)
    {
        if (empty($email)) {
            return false;
        }

        // Ensure customer role exists.
        self::register_customer_role();

        // Find or create user.
        $user = get_user_by('email', $email);
        
        if (! $user) {
            $customer_name = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'customer_name', true);
            $user_id = $this->create_user_from_email($email, $customer_name);
            
            if (is_wp_error($user_id)) {
                $this->log_error('Failed to create user', [
                    'email' => $email,
                    'error' => $user_id->get_error_message(),
                ]);
                return false;
            }
        } else {
            $user_id = $user->ID;
            
            // Fix existing users with no role - assign customer role.
            $user_obj = new \WP_User($user_id);
            if (empty($user_obj->roles)) {
                $user_obj->set_role(self::CUSTOMER_ROLE);
                $this->log_info('Assigned customer role to existing user with no role', [
                    'user_id' => $user_id,
                    'email'   => $email,
                ]);
            }
        }

        // Link enrollment to user.
        update_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'user_id', $user_id);

        $this->log_info('Enrollment linked to user', [
            'enrollment_id' => $enrollment_id,
            'user_id'       => $user_id,
        ]);

        return $user_id;
    }

    /**
     * Create a WordPress user from email.
     *
     * @param string $email Customer email.
     * @param string $name  Customer name (optional).
     *
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    public function create_user_from_email(string $email, string $name = '')
    {
        // Validate email.
        if (! is_email($email)) {
            return new \WP_Error('invalid_email', __('Invalid email address.', 'fields-bright-enrollment'));
        }

        // Check if user exists.
        if (email_exists($email)) {
            $user = get_user_by('email', $email);
            return $user ? $user->ID : new \WP_Error('user_not_found', __('User not found.', 'fields-bright-enrollment'));
        }

        // Generate username from email.
        $username = $this->generate_username_from_email($email);

        // Generate secure password.
        $password = wp_generate_password(16, true, true);

        // Ensure customer role exists before creating user.
        self::register_customer_role();

        // Parse name.
        $name_parts = $this->parse_name($name);

        // Create user.
        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $name_parts['first_name'],
            'last_name'    => $name_parts['last_name'],
            'display_name' => $name ?: $username,
            'role'         => self::CUSTOMER_ROLE,
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Explicitly set role as fallback (in case wp_insert_user didn't assign it).
        $user = new \WP_User($user_id);
        if (empty($user->roles) || ! in_array(self::CUSTOMER_ROLE, (array) $user->roles, true)) {
            $user->set_role(self::CUSTOMER_ROLE);
            $this->log_info('Customer role explicitly assigned to user', [
                'user_id' => $user_id,
                'email'   => $email,
            ]);
        }

        // Send password reset email so user can set their password.
        $this->send_welcome_email($user_id);

        $this->log_info('User created from enrollment', [
            'user_id' => $user_id,
            'email'   => $email,
            'role'    => self::CUSTOMER_ROLE,
        ]);

        return $user_id;
    }

    /**
     * Generate a unique username from email.
     *
     * @param string $email Email address.
     *
     * @return string Unique username.
     */
    private function generate_username_from_email(string $email): string
    {
        $username = sanitize_user(explode('@', $email)[0], true);
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Parse a full name into first and last name.
     *
     * @param string $name Full name.
     *
     * @return array{first_name: string, last_name: string}
     */
    private function parse_name(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['first_name' => '', 'last_name' => ''];
        }

        $parts = explode(' ', $name, 2);
        
        return [
            'first_name' => $parts[0] ?? '',
            'last_name'  => $parts[1] ?? '',
        ];
    }

    /**
     * Send welcome email to new user.
     *
     * @param int $user_id User ID.
     *
     * @return bool Whether email was sent.
     */
    private function send_welcome_email(int $user_id): bool
    {
        $user = get_user_by('id', $user_id);
        
        if (! $user) {
            return false;
        }

        // Generate password reset key.
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            return false;
        }

        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

        $site_name = get_bloginfo('name');
        $subject = sprintf(
            /* translators: %s: Site name */
            __('Welcome to %s - Set Your Password', 'fields-bright-enrollment'),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: User display name, 2: Site name, 3: Password reset URL */
            __("Hello %1\$s,\n\nYour account has been created at %2\$s.\n\nTo set your password and access your account, please visit:\n%3\$s\n\nIf you did not request this account, please ignore this email.\n\nThank you!", 'fields-bright-enrollment'),
            $user->display_name,
            $site_name,
            $reset_url
        );

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Get all enrollments for a user.
     *
     * @param int    $user_id User ID.
     * @param string $status  Optional status filter.
     *
     * @return array Enrollments.
     */
    public function get_user_enrollments(int $user_id, string $status = ''): array
    {
        $user = get_user_by('id', $user_id);
        
        if (! $user) {
            return [];
        }

        $meta_query = [
            'relation' => 'OR',
            // Match by user ID.
            [
                'key'   => EnrollmentCPT::META_PREFIX . 'user_id',
                'value' => $user_id,
            ],
            // Match by email (for legacy enrollments).
            [
                'key'   => EnrollmentCPT::META_PREFIX . 'customer_email',
                'value' => $user->user_email,
            ],
        ];

        $args = [
            'post_type'      => EnrollmentCPT::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Add status filter if specified.
        if ($status) {
            $args['meta_query'] = [
                'relation' => 'AND',
                $meta_query,
                [
                    'key'   => EnrollmentCPT::META_PREFIX . 'status',
                    'value' => $status,
                ],
            ];
        }

        $enrollments = get_posts($args);
        $results = [];

        foreach ($enrollments as $enrollment) {
            $workshop_id = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
            $workshop = get_post($workshop_id);

            $results[] = [
                'id'                  => $enrollment->ID,
                'confirmation_number' => 'FB-' . str_pad($enrollment->ID, 6, '0', STR_PAD_LEFT),
                'workshop_id'         => $workshop_id,
                'workshop_title'      => $workshop ? $workshop->post_title : __('Unknown', 'fields-bright-enrollment'),
                'workshop_url'        => $workshop ? get_permalink($workshop_id) : '',
                'amount'              => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', true),
                'status'              => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true),
                'date'                => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'date', true),
                'pricing_option'      => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true),
            ];
        }

        return $results;
    }

    /**
     * Get enrollment count for a user.
     *
     * @param int    $user_id User ID.
     * @param string $status  Optional status filter.
     *
     * @return int Enrollment count.
     */
    public function get_user_enrollment_count(int $user_id, string $status = ''): int
    {
        return count($this->get_user_enrollments($user_id, $status));
    }

    /**
     * Check if user has enrolled in a specific workshop.
     *
     * @param int $user_id     User ID.
     * @param int $workshop_id Workshop post ID.
     *
     * @return bool
     */
    public function has_enrolled_in_workshop(int $user_id, int $workshop_id): bool
    {
        $enrollments = $this->get_user_enrollments($user_id, 'completed');
        
        foreach ($enrollments as $enrollment) {
            if ((int) $enrollment['workshop_id'] === $workshop_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register the customer role if it doesn't exist.
     *
     * @return void
     */
    /**
     * Fix existing users with no role by assigning customer role.
     *
     * Can be called manually or via WP-CLI to fix existing users.
     *
     * @return array{fixed: int, total: int} Number of users fixed and total checked.
     */
    public static function fix_users_without_role(): array
    {
        // Ensure role exists.
        self::register_customer_role();

        // Get all users.
        $users = get_users(['number' => -1]);
        $fixed = 0;

        foreach ($users as $user) {
            // Reload user object to get fresh roles.
            $user_obj = new \WP_User($user->ID);
            
            // Check if user actually has no roles.
            if (empty($user_obj->roles)) {
                $user_obj->set_role(self::CUSTOMER_ROLE);
                $fixed++;
                
                Logger::instance()->info('Fixed user without role - assigned customer role', [
                    'user_id' => $user_obj->ID,
                    'user_email' => $user_obj->user_email,
                ]);
            }
        }

        return [
            'fixed' => $fixed,
            'total' => count($users),
        ];
    }

    /**
     * Register the customer role with explicit capabilities.
     *
     * Customers can:
     * - Read (view the site)
     * - Edit their own profile (handled by WordPress default)
     *
     * Customers cannot:
     * - Access wp-admin (except profile)
     * - Create/edit/delete posts
     * - Upload files
     * - Manage options
     * - View other users
     *
     * @return void
     */
    public static function register_customer_role(): void
    {
        $capabilities = [
            // Basic reading.
            'read' => true,
            
            // Explicitly deny dangerous capabilities.
            'edit_posts'             => false,
            'delete_posts'           => false,
            'publish_posts'          => false,
            'upload_files'           => false,
            'edit_pages'             => false,
            'edit_others_posts'      => false,
            'create_posts'           => false,
            'manage_categories'      => false,
            'manage_links'           => false,
            'moderate_comments'      => false,
            'manage_options'         => false,
            'edit_users'             => false,
            'list_users'             => false,
            'remove_users'           => false,
            'promote_users'          => false,
            'edit_themes'            => false,
            'install_themes'         => false,
            'switch_themes'          => false,
            'edit_plugins'           => false,
            'install_plugins'        => false,
            'activate_plugins'       => false,
            'delete_plugins'         => false,
            'delete_themes'          => false,
            'export'                 => false,
            'import'                 => false,
        ];

        $existing_role = get_role(self::CUSTOMER_ROLE);

        if (! $existing_role) {
            add_role(
                self::CUSTOMER_ROLE,
                __('Customer', 'fields-bright-enrollment'),
                $capabilities
            );
        } else {
            // Update existing role capabilities.
            foreach ($capabilities as $cap => $grant) {
                if ($grant) {
                    $existing_role->add_cap($cap);
                } else {
                    $existing_role->remove_cap($cap);
                }
            }
        }
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

    /**
     * Log error message.
     *
     * @param string $message Error message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    private function log_error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}

