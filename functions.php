<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 * @since   1.0.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts(): void
{
    wp_enqueue_style(
        'hello-elementor-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        [
            'hello-elementor-theme-style',
        ],
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20);

/**
 * Disable page title for Elementor pages
 *
 * @param bool $return Current return value.
 *
 * @return bool
 */
function ele_disable_page_title(bool $return): bool
{
    return false;
}
add_filter('hello_elementor_page_title', 'ele_disable_page_title');

/**
 * Set font display to swap for custom fonts
 */
add_filter('elementor_pro/custom_fonts/font_display', function ($current_value, $font_family, $data) {
    return 'swap';
}, 10, 3);

/**
 * =============================================================================
 * Fields Bright Enrollment System
 * =============================================================================
 *
 * Bootstrap the enrollment system for workshop/coaching class registration
 * with Stripe Checkout Sessions integration.
 */

/**
 * Load the PSR-4 autoloader for the enrollment system.
 */
require_once get_stylesheet_directory() . '/includes/Autoloader.php';

// Register the autoloader.
\FieldsBright\Autoloader::instance()->register();

/**
 * Initialize the enrollment system.
 *
 * Boots up the enrollment system after WordPress has loaded.
 *
 * @return void
 */
function fields_bright_init_enrollment_system(): void
{
    \FieldsBright\Enrollment\EnrollmentSystem::instance()->init();
}
add_action('init', 'fields_bright_init_enrollment_system', 5);

/**
 * Disable Branda integration for enrollment emails.
 *
 * Our email templates are self-contained with proper HTML structure,
 * headers, and footers. Branda's template wrapper causes content extraction
 * issues with nested divs. Disabling Branda ensures emails display correctly.
 *
 * @return bool False to disable Branda templates.
 */
add_filter('fields_bright_use_branda_templates', '__return_false');

/**
 * Register activation hook for the enrollment system.
 *
 * Since this is a theme, we use after_switch_theme instead of register_activation_hook.
 *
 * @return void
 */
function fields_bright_enrollment_activate(): void
{
    \FieldsBright\Enrollment\EnrollmentSystem::activate();
}
add_action('after_switch_theme', 'fields_bright_enrollment_activate');

/**
 * Register deactivation hook for the enrollment system.
 *
 * @return void
 */
function fields_bright_enrollment_deactivate(): void
{
    \FieldsBright\Enrollment\EnrollmentSystem::deactivate();
}
add_action('switch_theme', 'fields_bright_enrollment_deactivate');

/**
 * =============================================================================
 * Menu Filters
 * =============================================================================
 *
 * Custom menu visibility logic based on user login status, roles, and page context.
 * Supports: only-logged-in, only-logged-out, only-admin, only-role-{role}, hide-on-checkout
 */
require_once get_stylesheet_directory() . '/includes/menu-filters.php';

/**
 * Expose private workshop meta fields to Elementor Dynamic Field widget.
 *
 * Elementor filters out meta fields that start with underscore (_) by default.
 * This filter adds our workshop meta fields to Elementor's meta field dropdown.
 *
 * @param array $keys Array of meta keys.
 * @return array Modified array with workshop meta fields included.
 */
function fields_bright_expose_meta_to_elementor(array $keys): array
{
    // List of workshop meta fields to expose.
    $workshop_fields = [
        '_event_start_datetime',
        '_event_end_datetime',
        '_event_recurring_date_info',
        '_event_price',
        '_event_location',
        '_event_registration_link',
        '_event_checkout_enabled',
        '_event_checkout_price',
        '_event_pricing_options',
        '_event_capacity',
        '_event_waitlist_enabled',
    ];

    // Merge with existing keys, removing duplicates.
    return array_unique(array_merge($keys, $workshop_fields));
}

// Hook into Elementor Pro's custom field discovery.
add_filter('elementor_pro/dynamic_tags/post_custom_field/custom_keys', 'fields_bright_expose_meta_to_elementor', 10, 1);

// Also hook into Elementor's general meta field discovery (if available).
add_filter('elementor/dynamic_tags/post_custom_field/custom_keys', 'fields_bright_expose_meta_to_elementor', 10, 1);