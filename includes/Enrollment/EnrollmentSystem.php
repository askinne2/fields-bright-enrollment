<?php
/**
 * Main Enrollment System Class
 *
 * This is the primary class for the Fields Bright Enrollment System.
 * It serves as a dependency injection container and bootstraps all
 * system components.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\PostType\WorkshopCPT;
use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;
use FieldsBright\Enrollment\MetaBoxes\EnrollmentMetaBox;
use FieldsBright\Enrollment\Stripe\StripeHandler;
use FieldsBright\Enrollment\Stripe\WebhookHandler;
use FieldsBright\Enrollment\Handlers\EnrollmentHandler;
use FieldsBright\Enrollment\Admin\AdminMenu;
use FieldsBright\Enrollment\Admin\Dashboard;
use FieldsBright\Enrollment\Admin\AdminSettings;
use FieldsBright\Enrollment\Admin\WorkshopEnrollmentMetaBox;
use FieldsBright\Enrollment\Email\EmailHandler;
use FieldsBright\Enrollment\Cart\CartManager;
use FieldsBright\Enrollment\Accounts\UserAccountHandler;
use FieldsBright\Enrollment\Accounts\AccountDashboard;
use FieldsBright\Enrollment\Accounts\ProfileManager;
use FieldsBright\Enrollment\Waitlist\WaitlistCPT;
use FieldsBright\Enrollment\Waitlist\WaitlistHandler;
use FieldsBright\Enrollment\Waitlist\WaitlistForm;
use FieldsBright\Enrollment\Waitlist\WaitlistMetaBox;
use FieldsBright\Enrollment\Waitlist\WaitlistClaimHandler;
use FieldsBright\Enrollment\Shortcodes\CapacityShortcode;
use FieldsBright\Enrollment\Shortcodes\CartShortcodes;
use FieldsBright\Enrollment\Admin\RefundHandler;
use FieldsBright\Enrollment\Admin\RefundMetaBox;
use FieldsBright\Enrollment\Admin\ExportHandler;
use FieldsBright\Enrollment\Admin\LogViewer;
use FieldsBright\Enrollment\REST\EnrollmentEndpoints;
use FieldsBright\Enrollment\REST\CartEndpoints;
use FieldsBright\Enrollment\Utils\Logger;
use FieldsBright\Enrollment\Migration\WorkshopRedirects;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EnrollmentSystem
 *
 * Main system class implementing singleton pattern with dependency injection.
 *
 * @since 1.0.0
 */
class EnrollmentSystem
{
    /**
     * Plugin version.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * Plugin text domain.
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'fields-bright-enrollment';

    /**
     * Option prefix for all plugin options.
     *
     * @var string
     */
    public const OPTION_PREFIX = 'fields_bright_';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Enrollment CPT handler.
     *
     * @var EnrollmentCPT|null
     */
    private ?EnrollmentCPT $enrollment_cpt = null;

    /**
     * Workshop CPT handler.
     *
     * @var WorkshopCPT|null
     */
    private ?WorkshopCPT $workshop_cpt = null;

    /**
     * Workshop meta box handler.
     *
     * @var WorkshopMetaBox|null
     */
    private ?WorkshopMetaBox $workshop_meta_box = null;

    /**
     * Enrollment meta box handler.
     *
     * @var EnrollmentMetaBox|null
     */
    private ?EnrollmentMetaBox $enrollment_meta_box = null;

    /**
     * Stripe handler.
     *
     * @var StripeHandler|null
     */
    private ?StripeHandler $stripe_handler = null;

    /**
     * Webhook handler.
     *
     * @var WebhookHandler|null
     */
    private ?WebhookHandler $webhook_handler = null;

    /**
     * Enrollment handler.
     *
     * @var EnrollmentHandler|null
     */
    private ?EnrollmentHandler $enrollment_handler = null;

    /**
     * Admin menu handler.
     *
     * @var AdminMenu|null
     */
    private ?AdminMenu $admin_menu = null;

    /**
     * Dashboard handler.
     *
     * @var Dashboard|null
     */
    private ?Dashboard $dashboard = null;

    /**
     * Admin settings handler.
     *
     * @var AdminSettings|null
     */
    private ?AdminSettings $admin_settings = null;

    /**
     * Workshop enrollment meta box.
     *
     * @var WorkshopEnrollmentMetaBox|null
     */
    private ?WorkshopEnrollmentMetaBox $workshop_enrollment_meta_box = null;

    /**
     * REST API endpoints handler.
     *
     * @var EnrollmentEndpoints|null
     */
    private ?EnrollmentEndpoints $rest_endpoints = null;

    /**
     * Email handler.
     *
     * @var EmailHandler|null
     */
    private ?EmailHandler $email_handler = null;

    /**
     * Cart manager.
     *
     * @var CartManager|null
     */
    private ?CartManager $cart_manager = null;

    /**
     * User account handler.
     *
     * @var UserAccountHandler|null
     */
    private ?UserAccountHandler $user_account_handler = null;

    /**
     * Account dashboard handler.
     *
     * @var AccountDashboard|null
     */
    private ?AccountDashboard $account_dashboard = null;

    /**
     * Profile manager.
     *
     * @var ProfileManager|null
     */
    private ?ProfileManager $profile_manager = null;

    /**
     * Cart REST endpoints handler.
     *
     * @var CartEndpoints|null
     */
    private ?CartEndpoints $cart_endpoints = null;

    /**
     * Waitlist CPT handler.
     *
     * @var WaitlistCPT|null
     */
    private ?WaitlistCPT $waitlist_cpt = null;

    /**
     * Waitlist handler.
     *
     * @var WaitlistHandler|null
     */
    private ?WaitlistHandler $waitlist_handler = null;

    /**
     * Waitlist meta box.
     *
     * @var WaitlistMetaBox|null
     */
    private ?WaitlistMetaBox $waitlist_meta_box = null;

    /**
     * Waitlist claim handler.
     *
     * @var WaitlistClaimHandler|null
     */
    private ?WaitlistClaimHandler $waitlist_claim_handler = null;

    /**
     * Waitlist form handler.
     *
     * @var WaitlistForm|null
     */
    private ?WaitlistForm $waitlist_form = null;

    /**
     * Capacity shortcode handler.
     *
     * @var CapacityShortcode|null
     */
    private ?CapacityShortcode $capacity_shortcode = null;

    /**
     * Cart shortcodes handler.
     *
     * @var CartShortcodes|null
     */
    private ?CartShortcodes $cart_shortcodes = null;

    /**
     * Refund handler.
     *
     * @var RefundHandler|null
     */
    private ?RefundHandler $refund_handler = null;

    /**
     * Refund meta box handler.
     *
     * @var RefundMetaBox|null
     */
    private ?RefundMetaBox $refund_meta_box = null;

    /**
     * Export handler.
     *
     * @var ExportHandler|null
     */
    private ?ExportHandler $export_handler = null;

    /**
     * Log viewer.
     *
     * @var LogViewer|null
     */
    private ?LogViewer $log_viewer = null;

    /**
     * Logger instance.
     *
     * @var Logger|null
     */
    private ?Logger $logger = null;

    /**
     * Workshop redirects handler.
     *
     * @var WorkshopRedirects|null
     */
    private ?WorkshopRedirects $workshop_redirects = null;

    /**
     * Base path for the plugin.
     *
     * @var string
     */
    private string $base_path;

    /**
     * Base URL for the plugin.
     *
     * @var string
     */
    private string $base_url;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Private to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->base_path = dirname(dirname(__FILE__));
        $this->base_url = get_stylesheet_directory_uri() . '/includes';
    }

    /**
     * Initialize the enrollment system.
     *
     * This method bootstraps all components and registers WordPress hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Initialize components.
        $this->init_components();

        // Register hooks.
        $this->register_hooks();

        // Fire action for other plugins/themes to hook into.
        do_action('fields_bright_enrollment_init', $this);
    }

    /**
     * Initialize all system components.
     *
     * @return void
     */
    private function init_components(): void
    {
        // Initialize Logger first (needed by all components).
        $this->logger = Logger::instance();
        // $this->logger->info('Initializing enrollment system components');
        
        // Initialize Stripe handler first (needed by other components).
        $this->stripe_handler = new StripeHandler();
        // $this->logger->debug('Stripe handler initialized');

        // Initialize CPT registration.
        $this->enrollment_cpt = new EnrollmentCPT();
        $this->workshop_cpt = new WorkshopCPT();
        // $this->logger->debug('Custom post types registered');

        // Initialize meta boxes.
        $this->workshop_meta_box = new WorkshopMetaBox();
        $this->enrollment_meta_box = new EnrollmentMetaBox();
        // $this->logger->debug('Meta boxes initialized');

        // Initialize cart manager first (needed by handlers).
        $this->cart_manager = new CartManager();
        // $this->logger->debug('Cart manager initialized');
        
        // Initialize handlers.
        $this->webhook_handler = new WebhookHandler($this->stripe_handler);
        $this->enrollment_handler = new EnrollmentHandler($this->stripe_handler, $this->cart_manager);
        // $this->logger->debug('Handlers initialized');

        // Initialize admin components.
        if (is_admin()) {
            // Initialize dashboard first (needed by AdminMenu).
            $this->dashboard = new Dashboard();
            
            // Initialize admin menu (registers top-level menu).
            $this->admin_menu = new AdminMenu($this->dashboard);
            
            // Initialize settings (will be registered by AdminMenu).
            $this->admin_settings = new AdminSettings();
            $this->admin_menu->set_admin_settings($this->admin_settings);
            
            // Initialize workshop enrollment meta box.
            $this->workshop_enrollment_meta_box = new WorkshopEnrollmentMetaBox();
            
            // Initialize log viewer (will be registered by AdminMenu).
            $this->log_viewer = new LogViewer();
            $this->admin_menu->set_log_viewer($this->log_viewer);
        }

        // Initialize REST API endpoints.
        $this->rest_endpoints = new EnrollmentEndpoints($this->webhook_handler);

        // Initialize email handler.
        $this->email_handler = new EmailHandler();

        // Initialize account system.
        $this->user_account_handler = new UserAccountHandler();
        $this->account_dashboard = new AccountDashboard($this->user_account_handler);
        $this->profile_manager = new ProfileManager();
        $this->cart_endpoints = new CartEndpoints($this->cart_manager, $this->user_account_handler, $this->stripe_handler);

        // Initialize waitlist system.
        $this->waitlist_cpt = new WaitlistCPT();
        $this->waitlist_handler = new WaitlistHandler($this->waitlist_cpt);
        $this->waitlist_form = new WaitlistForm($this->waitlist_handler);
        $this->waitlist_meta_box = new WaitlistMetaBox();
        $this->waitlist_claim_handler = new WaitlistClaimHandler();

        // Initialize shortcodes.
        $this->capacity_shortcode = new CapacityShortcode();
        $this->cart_shortcodes = new CartShortcodes($this->cart_manager);
        // $this->logger->debug('Shortcodes registered');

        // Initialize workshop redirects (for migrated workshops).
        $this->workshop_redirects = new WorkshopRedirects();
        // $this->logger->debug('Workshop redirects initialized');
        
        // $this->logger->info('All enrollment system components initialized');

        // Initialize admin components.
        if (is_admin()) {
            $this->refund_handler = new RefundHandler($this->stripe_handler);
            $this->refund_meta_box = new RefundMetaBox($this->refund_handler);
            
            // Initialize export handler (will be registered by AdminMenu).
            $this->export_handler = new ExportHandler();
            if ($this->admin_menu) {
                $this->admin_menu->set_export_handler($this->export_handler);
            }
        }
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Enqueue scripts and styles.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Template redirects for enrollment pages.
        add_action('template_redirect', [$this, 'handle_enrollment_redirect']);

        // Template override for success/cancel pages.
        add_filter('template_include', [$this, 'load_enrollment_templates'], 99);

        // Register custom page templates in admin dropdown.
        add_filter('theme_page_templates', [$this, 'register_page_templates']);

        // Cache invalidation hooks.
        add_action('fields_bright_enrollment_created', [$this, 'clear_workshop_enrollment_cache'], 10, 2);
        add_action('fields_bright_enrollment_refunded', [$this, 'clear_workshop_enrollment_cache'], 10, 2);

        // Plugin activation/deactivation hooks are registered in functions.php.
    }

    /**
     * Clear enrollment cache when enrollment is created or updated.
     *
     * @param int   $enrollment_id Enrollment post ID.
     * @param array $data          Enrollment data or refund data.
     *
     * @return void
     */
    public function clear_workshop_enrollment_cache(int $enrollment_id, array $data = []): void
    {
        $workshop_id = $data['workshop_id'] ?? get_post_meta($enrollment_id, '_enrollment_workshop_id', true);
        
        if ($workshop_id) {
            WorkshopMetaBox::clear_enrollment_cache((int) $workshop_id);
        }
    }

    /**
     * Enqueue frontend assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void
    {
        // Enqueue on workshop pages (both CPT and legacy posts in workshops category).
        if (! is_singular('post') && ! is_singular(WorkshopCPT::POST_TYPE)) {
            return;
        }

        $script_path = $this->get_asset_path('js/enrollment-pricing.js');
        $script_version = file_exists($script_path) ? filemtime($script_path) : self::VERSION;

        wp_enqueue_script(
            'fields-bright-enrollment',
            $this->get_asset_url('js/enrollment-pricing.js'),
            ['jquery'],
            $script_version,
            true
        );

        wp_localize_script('fields-bright-enrollment', 'fieldsBrightEnrollment', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'enrollUrl' => home_url('/enroll/'),
            'nonce' => wp_create_nonce('fields_bright_enrollment'),
        ]);
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     *
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void
    {
        $screen = get_current_screen();

        // Enqueue on enrollment CPT screens, workshop CPT screens, and post edit screens.
        if ($screen && ($screen->post_type === 'enrollment' || $screen->post_type === 'post' || $screen->post_type === WorkshopCPT::POST_TYPE)) {
            $css_path = $this->get_asset_path('css/enrollment-admin.css');
            $css_version = file_exists($css_path) ? filemtime($css_path) : self::VERSION;
            
            wp_enqueue_style(
                'fields-bright-enrollment-admin',
                $this->get_asset_url('css/enrollment-admin.css'),
                [],
                $css_version
            );

            $js_path = $this->get_asset_path('js/enrollment-admin.js');
            $js_version = file_exists($js_path) ? filemtime($js_path) : self::VERSION;
            
            wp_enqueue_script(
                'fields-bright-enrollment-admin',
                $this->get_asset_url('js/enrollment-admin.js'),
                ['jquery'],
                $js_version,
                true
            );
        }

        // Enqueue on settings page and dashboard.
        if (
            $hook_suffix === 'enrollment_page_fields-bright-enrollment-settings' ||
            $hook_suffix === 'toplevel_page_fields-bright-enrollment'
        ) {
            wp_enqueue_style(
                'fields-bright-enrollment-admin',
                $this->get_asset_url('css/enrollment-admin.css'),
                [],
                self::VERSION
            );
        }
    }

    /**
     * Handle enrollment page redirects.
     *
     * @return void
     */
    public function handle_enrollment_redirect(): void
    {
        // Check if this is an enrollment request.
        if (isset($_GET['enroll']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/enroll/') !== false)) {
            $this->enrollment_handler->handle_enrollment_request();
        }
    }

    /**
     * Load custom templates for enrollment success/cancel pages.
     *
     * @param string $template The current template path.
     *
     * @return string The modified template path.
     */
    public function load_enrollment_templates(string $template): string
    {
        if (! is_page()) {
            return $template;
        }

        $page_id = get_queried_object_id();
        $page_template = get_post_meta($page_id, '_wp_page_template', true);

        // Check if page has our template selected in admin.
        if ($page_template === 'templates/enrollment-success.php') {
            $custom_template = $this->get_template_path('enrollment-success');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if ($page_template === 'templates/enrollment-cancel.php') {
            $custom_template = $this->get_template_path('enrollment-cancel');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        // Get the configured page IDs.
        $success_page_id = self::get_option('enrollment_success_page', 0);
        $cancel_page_id = self::get_option('enrollment_cancel_page', 0);

        // Check if we're on the success page or have the session_id parameter.
        if (
            (is_page($success_page_id) && $success_page_id > 0) ||
            isset($_GET['session_id'])
        ) {
            $custom_template = $this->get_template_path('enrollment-success');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        // Check if we're on the cancel page or coming from Stripe cancel.
        if (
            (is_page($cancel_page_id) && $cancel_page_id > 0) ||
            isset($_GET['enrollment_cancel'])
        ) {
            $custom_template = $this->get_template_path('enrollment-cancel');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        // Also check by page slug as fallback.
        if (is_page('thank-you') && isset($_GET['session_id'])) {
            $custom_template = $this->get_template_path('enrollment-success');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_page('enrollment-cancelled')) {
            $custom_template = $this->get_template_path('enrollment-cancel');
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Register custom page templates in the admin template dropdown.
     *
     * @param array $templates Array of page templates.
     *
     * @return array Modified array of page templates.
     */
    public function register_page_templates(array $templates): array
    {
        $templates['templates/enrollment-success.php'] = __('Enrollment Success', 'fields-bright-enrollment');
        $templates['templates/enrollment-cancel.php'] = __('Enrollment Cancelled', 'fields-bright-enrollment');

        return $templates;
    }

    /**
     * Get the base path for the plugin.
     *
     * @param string $path Optional path to append.
     *
     * @return string
     */
    public function get_base_path(string $path = ''): string
    {
        return $this->base_path . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the base URL for the plugin.
     *
     * @param string $path Optional path to append.
     *
     * @return string
     */
    public function get_base_url(string $path = ''): string
    {
        return $this->base_url . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get asset URL.
     *
     * @param string $path Asset path relative to assets directory.
     *
     * @return string
     */
    public function get_asset_url(string $path): string
    {
        return get_stylesheet_directory_uri() . '/assets/' . ltrim($path, '/');
    }

    /**
     * Get asset file path for cache busting.
     *
     * @param string $path Asset path relative to assets directory.
     *
     * @return string
     */
    public function get_asset_path(string $path): string
    {
        return get_stylesheet_directory() . '/assets/' . ltrim($path, '/');
    }

    /**
     * Get template path.
     *
     * @param string $template Template name without .php extension.
     *
     * @return string
     */
    public function get_template_path(string $template): string
    {
        return get_stylesheet_directory() . '/templates/' . $template . '.php';
    }

    /**
     * Get a plugin option.
     *
     * @param string $key     Option key without prefix.
     * @param mixed  $default Default value if option not found.
     *
     * @return mixed
     */
    public static function get_option(string $key, $default = null)
    {
        return get_option(self::OPTION_PREFIX . $key, $default);
    }

    /**
     * Update a plugin option.
     *
     * @param string $key   Option key without prefix.
     * @param mixed  $value Option value.
     *
     * @return bool
     */
    public static function update_option(string $key, $value): bool
    {
        return update_option(self::OPTION_PREFIX . $key, $value);
    }

    /**
     * Delete a plugin option.
     *
     * @param string $key Option key without prefix.
     *
     * @return bool
     */
    public static function delete_option(string $key): bool
    {
        return delete_option(self::OPTION_PREFIX . $key);
    }

    /**
     * Get the Stripe handler.
     *
     * @return StripeHandler|null
     */
    public function get_stripe_handler(): ?StripeHandler
    {
        return $this->stripe_handler;
    }

    /**
     * Get the enrollment CPT handler.
     *
     * @return EnrollmentCPT|null
     */
    public function get_enrollment_cpt(): ?EnrollmentCPT
    {
        return $this->enrollment_cpt;
    }

    /**
     * Get the cart manager.
     *
     * @return CartManager|null
     */
    public function get_cart_manager(): ?CartManager
    {
        return $this->cart_manager;
    }

    /**
     * Get the user account handler.
     *
     * @return UserAccountHandler|null
     */
    public function get_user_account_handler(): ?UserAccountHandler
    {
        return $this->user_account_handler;
    }

    /**
     * Activation hook callback.
     *
     * Called when the theme is activated.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Ensure CPT is registered.
        $instance = self::instance();
        $instance->init_components();

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Set default options.
        if (! get_option(self::OPTION_PREFIX . 'stripe_test_mode')) {
            update_option(self::OPTION_PREFIX . 'stripe_test_mode', true);
        }

        // Register customer role.
        UserAccountHandler::register_customer_role();

        // Create default success/cancel pages.
        self::create_default_pages();

        // Fire activation action.
        do_action('fields_bright_enrollment_activate');
    }

    /**
     * Create default success and cancel pages if they don't exist.
     *
     * @return void
     */
    public static function create_default_pages(): void
    {
        // Create success page.
        $success_page_id = self::get_option('enrollment_success_page', 0);
        if (! $success_page_id || ! get_post($success_page_id)) {
            // Check if "Thank You" page already exists by slug.
            $existing_page = get_page_by_path('thank-you');
            if ($existing_page) {
                $success_page_id = $existing_page->ID;
            } else {
                $success_page_id = wp_insert_post([
                    'post_title'   => __('Thank You', 'fields-bright-enrollment'),
                    'post_name'    => 'thank-you',
                    'post_content' => '<!-- This page uses a custom template. Content is generated dynamically. -->',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => 1,
                    'meta_input'   => [
                        '_wp_page_template' => 'enrollment-success',
                    ],
                ]);
            }
            if ($success_page_id && ! is_wp_error($success_page_id)) {
                self::update_option('enrollment_success_page', $success_page_id);
            }
        }

        // Create cancel page.
        $cancel_page_id = self::get_option('enrollment_cancel_page', 0);
        if (! $cancel_page_id || ! get_post($cancel_page_id)) {
            // Check if "Enrollment Cancelled" page already exists by slug.
            $existing_page = get_page_by_path('enrollment-cancelled');
            if ($existing_page) {
                $cancel_page_id = $existing_page->ID;
            } else {
                $cancel_page_id = wp_insert_post([
                    'post_title'   => __('Enrollment Cancelled', 'fields-bright-enrollment'),
                    'post_name'    => 'enrollment-cancelled',
                    'post_content' => '<!-- This page uses a custom template. Content is generated dynamically. -->',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => 1,
                    'meta_input'   => [
                        '_wp_page_template' => 'enrollment-cancel',
                    ],
                ]);
            }
            if ($cancel_page_id && ! is_wp_error($cancel_page_id)) {
                self::update_option('enrollment_cancel_page', $cancel_page_id);
            }
        }
    }

    /**
     * Deactivation hook callback.
     *
     * Called when the theme is deactivated.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Flush rewrite rules.
        flush_rewrite_rules();

        // Fire deactivation action.
        do_action('fields_bright_enrollment_deactivate');
    }
}

