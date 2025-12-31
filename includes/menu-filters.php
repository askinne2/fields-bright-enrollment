<?php
/**
 * Menu Filters
 * 
 * Custom menu visibility logic based on user login status, roles, and page context
 * 
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Filter navigation menu items based on visibility classes
 * 
 * Supports:
 * - only-logged-in: Show only when user is logged in
 * - only-logged-out: Show only when user is logged out
 * - only-admin: Show only to administrators
 * - only-role-{role}: Show only to users with specific role (e.g., only-role-ui_member)
 * - hide-on-checkout: Hide on WooCommerce checkout page
 */
add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    $is_logged_in = is_user_logged_in();

    foreach ( $items as $key => $item ) {
        $classes = is_array( $item->classes ) ? $item->classes : [];

        // Logged in/out visibility
        if ( in_array( 'only-logged-in', $classes, true ) && ! $is_logged_in ) {
            unset( $items[ $key ] );
            continue;
        }
        if ( in_array( 'only-logged-out', $classes, true ) && $is_logged_in ) {
            unset( $items[ $key ] );
            continue;
        }

        // Capability / role examples
        if ( in_array( 'only-admin', $classes, true ) && ! current_user_can( 'manage_options' ) ) {
            unset( $items[ $key ] );
            continue;
        }
        
        // Role-based visibility (e.g., show to a specific role: add class "only-role-ui_member")
        foreach ( $classes as $c ) {
            if ( strpos( $c, 'only-role-' ) === 0 ) {
                $role = substr( $c, strlen( 'only-role-' ) );
                if ( ! $is_logged_in || ! in_array( $role, wp_get_current_user()->roles, true ) ) {
                    unset( $items[ $key ] );
                }
            }
        }

        // Page/context example (hide item on checkout)
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
        if (in_array('hide-on-checkout', $classes, true) && function_exists('is_checkout') && is_checkout()) {
            unset($items[$key]);
            continue;
        }
    }
    return array_values( $items );
}, 10, 2 );

