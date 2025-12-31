<?php
/**
 * Log Viewer Admin Page
 *
 * Provides an admin interface for viewing and managing enrollment system logs.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\Utils\Logger;
use FieldsBright\Enrollment\Utils\LogLevel;
use FieldsBright\Enrollment\Admin\AdminMenu;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class LogViewer
 *
 * Admin page for viewing enrollment system logs.
 *
 * @since 1.2.0
 */
class LogViewer
{
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
        // Add submenu under Enrollment menu (priority 20 ensures it's after the main menu is created).
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);

        // Handle AJAX requests.
        add_action('wp_ajax_fields_bright_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_fields_bright_export_logs', [$this, 'ajax_export_logs']);
        add_action('wp_ajax_fields_bright_get_logs', [$this, 'ajax_get_logs']);

        // Enqueue admin assets.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Display admin notices.
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Add admin menu page.
     *
     * This method is kept for backward compatibility but will be skipped
     * if AdminMenu has already registered it.
     *
     * @return void
     */
    public function add_admin_menu(): void
    {
        // Skip if menu already exists (registered by AdminMenu).
        if ($this->menu_exists('enrollment-logs')) {
            return;
        }

        add_submenu_page(
            AdminMenu::MENU_SLUG, // Parent menu: top-level Enrollment menu
            __('System Logs', 'fields-bright-enrollment'),
            __('Logs', 'fields-bright-enrollment'),
            'manage_options',
            'enrollment-logs',
            [$this, 'render_page']
        );
    }

    /**
     * Check if a menu page already exists.
     *
     * @param string $menu_slug Menu slug to check.
     *
     * @return bool
     */
    private function menu_exists(string $menu_slug): bool
    {
        global $submenu;
        $parent_slug = AdminMenu::MENU_SLUG;
        
        if (! isset($submenu[$parent_slug])) {
            return false;
        }

        foreach ($submenu[$parent_slug] as $item) {
            if ($item[2] === $menu_slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     *
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'enrollment_page_enrollment-logs') {
            return;
        }

        wp_enqueue_style(
            'fields-bright-log-viewer',
            get_stylesheet_directory_uri() . '/assets/css/log-viewer.css',
            [],
            '1.2.0'
        );

        wp_enqueue_script(
            'fields-bright-log-viewer',
            get_stylesheet_directory_uri() . '/assets/js/log-viewer.js',
            ['jquery'],
            '1.2.0',
            true
        );

        wp_localize_script('fields-bright-log-viewer', 'fieldsBrightLogs', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fields_bright_logs'),
        ]);
    }

    /**
     * Render log viewer page.
     *
     * @return void
     */
    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submissions.
        if (isset($_POST['action']) && check_admin_referer('fields_bright_logs_action')) {
            $this->handle_actions();
        }

        // Get filter parameters (use wp_unslash for proper sanitization).
        $level_filter = isset($_GET['level']) && $_GET['level'] !== '' ? absint($_GET['level']) : null;
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $per_page = 50;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        // Build filters.
        $filters = [
            'limit' => $per_page,
        ];

        if ($level_filter !== null) {
            $filters['level'] = $level_filter;
        }

        if (! empty($search)) {
            $filters['search'] = $search;
        }

        // Get logs.
        $logs = $this->logger->get_logs($filters);
        $total_logs = count($this->logger->get_logs());

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Enrollment System Logs', 'fields-bright-enrollment'); ?></h1>
            
            <?php settings_errors('fields_bright_logs'); ?>

            <div class="fb-log-viewer">
                <!-- Filters -->
                <div class="fb-log-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="enrollment-logs">
                        
                        <label for="level-filter">
                            <?php esc_html_e('Log Level:', 'fields-bright-enrollment'); ?>
                        </label>
                        <select name="level" id="level-filter">
                            <option value=""><?php esc_html_e('All Levels', 'fields-bright-enrollment'); ?></option>
                            <?php foreach (LogLevel::get_all() as $level => $name) : ?>
                                <option value="<?php echo esc_attr($level); ?>" <?php selected($level_filter, $level); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="search-logs">
                            <?php esc_html_e('Search:', 'fields-bright-enrollment'); ?>
                        </label>
                        <input type="text" name="search" id="search-logs" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search logs...', 'fields-bright-enrollment'); ?>">

                        <button type="submit" class="button"><?php esc_html_e('Filter', 'fields-bright-enrollment'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=enrollment-logs')); ?>" class="button">
                            <?php esc_html_e('Clear Filters', 'fields-bright-enrollment'); ?>
                        </a>
                    </form>

                    <div class="fb-log-actions">
                        <form method="post" action="" style="display: inline-block;">
                            <?php wp_nonce_field('fields_bright_logs_action'); ?>
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'fields-bright-enrollment'); ?>');">
                                <?php esc_html_e('Clear All Logs', 'fields-bright-enrollment'); ?>
                            </button>
                        </form>

                        <button type="button" class="button button-secondary" id="export-logs">
                            <?php esc_html_e('Export Logs', 'fields-bright-enrollment'); ?>
                        </button>

                        <button type="button" class="button button-secondary" id="refresh-logs">
                            <?php esc_html_e('Refresh', 'fields-bright-enrollment'); ?>
                        </button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="fb-log-stats">
                    <span class="fb-log-stat">
                        <strong><?php esc_html_e('Total Logs:', 'fields-bright-enrollment'); ?></strong>
                        <?php echo esc_html(number_format($total_logs)); ?>
                    </span>
                    <span class="fb-log-stat">
                        <strong><?php esc_html_e('Showing:', 'fields-bright-enrollment'); ?></strong>
                        <?php echo esc_html(number_format(count($logs))); ?>
                    </span>
                </div>

                <!-- Log Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?php esc_html_e('Timestamp', 'fields-bright-enrollment'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Level', 'fields-bright-enrollment'); ?></th>
                            <th><?php esc_html_e('Message', 'fields-bright-enrollment'); ?></th>
                            <th style="width: 100px;"><?php esc_html_e('Context', 'fields-bright-enrollment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;">
                                    <?php esc_html_e('No logs found.', 'fields-bright-enrollment'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : ?>
                                <tr class="fb-log-row fb-log-level-<?php echo esc_attr(strtolower($log['level_name'])); ?>">
                                    <td class="fb-log-timestamp">
                                        <?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log['timestamp']))); ?>
                                    </td>
                                    <td class="fb-log-level">
                                        <span class="fb-log-badge fb-log-badge-<?php echo esc_attr(strtolower($log['level_name'])); ?>">
                                            <?php echo esc_html($log['level_name']); ?>
                                        </span>
                                    </td>
                                    <td class="fb-log-message">
                                        <?php echo esc_html($log['message']); ?>
                                    </td>
                                    <td class="fb-log-context">
                                        <?php if (! empty($log['context'])) : ?>
                                            <button type="button" class="button button-small fb-view-context" data-context="<?php echo esc_attr(wp_json_encode($log['context'])); ?>">
                                                <?php esc_html_e('View', 'fields-bright-enrollment'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Context Modal -->
        <div id="fb-context-modal" class="fb-modal" style="display: none;">
            <div class="fb-modal-content">
                <span class="fb-modal-close">&times;</span>
                <h2><?php esc_html_e('Log Context', 'fields-bright-enrollment'); ?></h2>
                <pre id="fb-context-data"></pre>
            </div>
        </div>

        <style>
            .fb-log-viewer {
                margin-top: 20px;
            }
            .fb-log-filters {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .fb-log-filters form {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .fb-log-filters label {
                font-weight: 600;
            }
            .fb-log-filters select,
            .fb-log-filters input[type="text"] {
                min-width: 150px;
            }
            .fb-log-actions {
                display: flex;
                gap: 10px;
            }
            .fb-log-stats {
                background: #f0f0f1;
                padding: 15px 20px;
                border: 1px solid #ccd0d4;
                margin-bottom: 20px;
                display: flex;
                gap: 30px;
            }
            .fb-log-stat {
                font-size: 14px;
            }
            .fb-log-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .fb-log-badge-debug {
                background: #e3e3e3;
                color: #666;
            }
            .fb-log-badge-info {
                background: #d4edda;
                color: #155724;
            }
            .fb-log-badge-warning {
                background: #fff3cd;
                color: #856404;
            }
            .fb-log-badge-error {
                background: #f8d7da;
                color: #721c24;
            }
            .fb-log-badge-critical {
                background: #721c24;
                color: #fff;
            }
            .fb-log-message {
                font-family: 'Courier New', monospace;
                font-size: 13px;
            }
            .fb-view-context {
                cursor: pointer;
            }
            .fb-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }
            .fb-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 800px;
                border-radius: 4px;
            }
            .fb-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .fb-modal-close:hover,
            .fb-modal-close:focus {
                color: black;
            }
            #fb-context-data {
                background: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
                font-size: 12px;
            }
        </style>
        <?php
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices(): void
    {
        // Only show on the logs page.
        if (! isset($_GET['page']) || $_GET['page'] !== 'enrollment-logs') {
            return;
        }

        // Settings errors are handled by settings_errors() in render_page().
    }

    /**
     * Handle admin actions.
     *
     * @return void
     */
    private function handle_actions(): void
    {
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        switch ($action) {
            case 'clear_logs':
                $this->logger->clear_logs();
                add_settings_error(
                    'fields_bright_logs',
                    'logs_cleared',
                    __('All logs have been cleared.', 'fields-bright-enrollment'),
                    'success'
                );
                break;
        }
    }

    /**
     * AJAX handler: Clear logs.
     *
     * @return void
     */
    public function ajax_clear_logs(): void
    {
        check_ajax_referer('fields_bright_logs', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'fields-bright-enrollment')]);
        }

        $this->logger->clear_logs();
        wp_send_json_success(['message' => __('Logs cleared successfully.', 'fields-bright-enrollment')]);
    }

    /**
     * AJAX handler: Export logs.
     *
     * @return void
     */
    public function ajax_export_logs(): void
    {
        check_ajax_referer('fields_bright_logs', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'fields-bright-enrollment')]);
        }

        $logs_json = $this->logger->export_logs();
        wp_send_json_success(['logs' => $logs_json]);
    }

    /**
     * AJAX handler: Get logs.
     *
     * @return void
     */
    public function ajax_get_logs(): void
    {
        check_ajax_referer('fields_bright_logs', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'fields-bright-enrollment')]);
        }

        $filters = [];
        
        if (isset($_POST['level']) && $_POST['level'] !== '') {
            $filters['level'] = absint($_POST['level']);
        }
        
        if (isset($_POST['search'])) {
            $filters['search'] = sanitize_text_field(wp_unslash($_POST['search']));
        }
        
        if (isset($_POST['limit'])) {
            $filters['limit'] = absint($_POST['limit']);
        }

        $logs = $this->logger->get_logs($filters);
        wp_send_json_success(['logs' => $logs]);
    }
}

