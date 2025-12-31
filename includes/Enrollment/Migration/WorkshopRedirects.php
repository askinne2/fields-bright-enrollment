<?php
/**
 * Workshop URL Redirects
 *
 * Handles redirects from old workshop URLs (posts with workshops category)
 * to new workshop CPT URLs after migration.
 *
 * @package FieldsBright\Enrollment\Migration
 * @since   1.2.1
 */

namespace FieldsBright\Enrollment\Migration;

use FieldsBright\Enrollment\PostType\WorkshopCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WorkshopRedirects
 *
 * Handles URL redirects for migrated workshop posts.
 *
 * @since 1.2.1
 */
class WorkshopRedirects
{
    /**
     * Option key for storing redirect mapping.
     *
     * @var string
     */
    private const REDIRECT_MAP_KEY = 'fields_bright_workshop_redirect_map';

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
        // Handle redirects on template_redirect (early in page load).
        add_action('template_redirect', [$this, 'handle_redirects'], 1);
    }

    /**
     * Handle redirects from old workshop URLs to new CPT URLs.
     *
     * @return void
     */
    public function handle_redirects(): void
    {
        // Skip if in admin.
        if (is_admin()) {
            return;
        }

        // Get redirect map.
        $redirect_map = get_option(self::REDIRECT_MAP_KEY, []);
        
        if (empty($redirect_map)) {
            return;
        }

        // Get current URL path.
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        
        // Remove trailing slash for comparison.
        $request_path = rtrim($request_path, '/');
        
        // Check if current path is in redirect map.
        if (isset($redirect_map[$request_path])) {
            $new_url = $redirect_map[$request_path];
            
            // Add query string if present.
            $query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field($_SERVER['QUERY_STRING']) : '';
            if ($query_string) {
                $new_url .= '?' . $query_string;
            }
            
            // Perform 301 redirect.
            wp_redirect($new_url, 301);
            exit;
        }
    }

    /**
     * Generate redirect map after migration.
     *
     * Call this after running the migration to create a map of old URLs to new URLs.
     *
     * @return array{old_url: string, new_url: string}[] Array of redirect mappings.
     */
    public function generate_redirect_map(): array
    {
        $redirect_map = [];

        // Get all workshop CPT posts.
        $workshops = get_posts([
            'post_type'      => WorkshopCPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
        ]);

        foreach ($workshops as $workshop) {
            // The old URL would have been the post's original permalink.
            // Since we're changing post_type but keeping the ID, we need to
            // reconstruct what the old URL would have been.
            $old_slug = $workshop->post_name;
            $old_path = '/' . $old_slug;
            
            // The new URL is the workshop CPT permalink.
            $new_url = get_permalink($workshop->ID);
            $new_path = parse_url($new_url, PHP_URL_PATH);
            
            // Only add redirect if URLs are different.
            if ($old_path !== rtrim($new_path, '/')) {
                $redirect_map[$old_path] = $new_url;
            }
        }

        // Store the redirect map.
        update_option(self::REDIRECT_MAP_KEY, $redirect_map);

        return $redirect_map;
    }

    /**
     * Clear the redirect map.
     *
     * Call this if you need to regenerate the map or after rollback.
     *
     * @return void
     */
    public function clear_redirect_map(): void
    {
        delete_option(self::REDIRECT_MAP_KEY);
    }

    /**
     * Get the current redirect map.
     *
     * @return array{old_url: string, new_url: string}[]
     */
    public function get_redirect_map(): array
    {
        return get_option(self::REDIRECT_MAP_KEY, []);
    }

    /**
     * Add a single redirect.
     *
     * @param string $old_path Old URL path (without domain).
     * @param string $new_url  New full URL.
     *
     * @return void
     */
    public function add_redirect(string $old_path, string $new_url): void
    {
        $redirect_map = $this->get_redirect_map();
        $redirect_map[$old_path] = $new_url;
        update_option(self::REDIRECT_MAP_KEY, $redirect_map);
    }

    /**
     * Remove a single redirect.
     *
     * @param string $old_path Old URL path to remove.
     *
     * @return void
     */
    public function remove_redirect(string $old_path): void
    {
        $redirect_map = $this->get_redirect_map();
        unset($redirect_map[$old_path]);
        update_option(self::REDIRECT_MAP_KEY, $redirect_map);
    }
}

