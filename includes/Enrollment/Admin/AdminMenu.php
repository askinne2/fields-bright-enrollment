<?php
/**
 * Admin Menu Manager
 *
 * Handles top-level admin menu registration for the enrollment system.
 * Consolidates all enrollment-related admin pages under one menu.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\PostType\WorkshopCPT;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminMenu
 *
 * Manages the top-level Enrollment admin menu and all submenus.
 * Centralizes menu registration to control ordering.
 *
 * @since 1.1.0
 */
class AdminMenu
{
    /**
     * Menu slug.
     *
     * @var string
     */
    public const MENU_SLUG = 'fields-bright-enrollment';

    /**
     * Menu position.
     *
     * @var int
     */
    public const MENU_POSITION = 25;

    /**
     * Dashboard instance.
     *
     * @var Dashboard
     */
    private Dashboard $dashboard;

    /**
     * Admin settings instance.
     *
     * @var AdminSettings|null
     */
    private ?AdminSettings $admin_settings = null;

    /**
     * Log viewer instance.
     *
     * @var LogViewer|null
     */
    private ?LogViewer $log_viewer = null;

    /**
     * Export handler instance.
     *
     * @var ExportHandler|null
     */
    private ?ExportHandler $export_handler = null;

    /**
     * Constructor.
     *
     * @param Dashboard $dashboard Dashboard instance for rendering.
     */
    public function __construct(Dashboard $dashboard)
    {
        $this->dashboard = $dashboard;
        $this->register_hooks();
    }

    /**
     * Set admin settings instance.
     *
     * @param AdminSettings $admin_settings Admin settings instance.
     *
     * @return void
     */
    public function set_admin_settings(AdminSettings $admin_settings): void
    {
        $this->admin_settings = $admin_settings;
    }

    /**
     * Set log viewer instance.
     *
     * @param LogViewer $log_viewer Log viewer instance.
     *
     * @return void
     */
    public function set_log_viewer(LogViewer $log_viewer): void
    {
        $this->log_viewer = $log_viewer;
    }

    /**
     * Set export handler instance.
     *
     * @param ExportHandler $export_handler Export handler instance.
     *
     * @return void
     */
    public function set_export_handler(ExportHandler $export_handler): void
    {
        $this->export_handler = $export_handler;
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Register menu early to ensure submenus can attach to it.
        add_action('admin_menu', [$this, 'register_admin_menu'], 5);
        
        // Reorder submenus after all are registered (CPTs register at priority 10).
        add_action('admin_menu', [$this, 'reorder_submenus'], 100);
    }

    /**
     * Register the top-level admin menu and all submenus in desired order.
     *
     * @return void
     */
    public function register_admin_menu(): void
    {
        // Register top-level menu.
        add_menu_page(
            __('Enrollment', 'fields-bright-enrollment'),
            __('Enrollment', 'fields-bright-enrollment'),
            'manage_options',
            self::MENU_SLUG,
            [$this->dashboard, 'render'],
            'dashicons-groups',
            self::MENU_POSITION
        );

        // 1. Dashboard (first submenu - replaces duplicate menu item).
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'fields-bright-enrollment'),
            __('Dashboard', 'fields-bright-enrollment'),
            'manage_options',
            self::MENU_SLUG,
            [$this->dashboard, 'render']
        );

        // 2. Workshops (CPT - registered automatically, will be reordered)
        // 3. Enrollments (CPT - registered automatically, will be reordered)
        
        // 4. Waitlist (if WaitlistCPT exists, it will be registered automatically)
        
        // 5. Settings
        if ($this->admin_settings) {
            add_submenu_page(
                self::MENU_SLUG,
                __('Enrollment Settings', 'fields-bright-enrollment'),
                __('Settings', 'fields-bright-enrollment'),
                'manage_options',
                self::MENU_SLUG . '-settings',
                [$this->admin_settings, 'render_settings_page']
            );
        }

        // 6. Logs
        if ($this->log_viewer) {
            add_submenu_page(
                self::MENU_SLUG,
                __('System Logs', 'fields-bright-enrollment'),
                __('Logs', 'fields-bright-enrollment'),
                'manage_options',
                'enrollment-logs',
                [$this->log_viewer, 'render_page']
            );
        }

        // 7. Exports
        if ($this->export_handler) {
            add_submenu_page(
                self::MENU_SLUG,
                __('Export Enrollments', 'fields-bright-enrollment'),
                __('Exports', 'fields-bright-enrollment'),
                'manage_options',
                'fields-bright-export',
                [$this->export_handler, 'render_export_page']
            );
        }
    }

    /**
     * Reorder submenus to desired order.
     *
     * Manipulates the global $submenu array to reorder items.
     *
     * @return void
     */
    public function reorder_submenus(): void
    {
        global $submenu;

        if (! isset($submenu[self::MENU_SLUG])) {
            return;
        }

        $menu_items = $submenu[self::MENU_SLUG];
        $ordered_items = [];

        // Define desired order (by menu slug or title).
        $order = [
            self::MENU_SLUG,                                    // Dashboard
            'edit.php?post_type=' . WorkshopCPT::POST_TYPE,     // Workshops
            'edit.php?post_type=' . EnrollmentCPT::POST_TYPE,    // Enrollments
            'edit.php?post_type=waitlist',                      // Waitlist (if exists)
            self::MENU_SLUG . '-settings',                      // Settings
            'enrollment-logs',                                  // Logs
            'fields-bright-export',                            // Exports
        ];

        // First, add items in desired order.
        foreach ($order as $slug) {
            foreach ($menu_items as $key => $item) {
                if (strpos($item[2], $slug) !== false) {
                    $ordered_items[] = $item;
                    unset($menu_items[$key]);
                    break;
                }
            }
        }

        // Add any remaining items (fallback for items not in order list).
        foreach ($menu_items as $item) {
            $ordered_items[] = $item;
        }

        $submenu[self::MENU_SLUG] = $ordered_items;
    }

    /**
     * Get the menu slug.
     *
     * @return string
     */
    public static function get_menu_slug(): string
    {
        return self::MENU_SLUG;
    }
}

