<?php
/**
 * Export Handler
 *
 * Handles exporting enrollments with various filters and formats.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\Admin\AdminMenu;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class ExportHandler
 *
 * Manages enrollment exports with filtering options.
 *
 * @since 1.1.0
 */
class ExportHandler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Register export page under Enrollment menu.
        add_action('admin_menu', [$this, 'add_export_page'], 30);
        
        // Handle export download.
        add_action('admin_init', [$this, 'handle_export_download']);
    }

    /**
     * Add export submenu page.
     *
     * This method is kept for backward compatibility but will be skipped
     * if AdminMenu has already registered it.
     *
     * @return void
     */
    public function add_export_page(): void
    {
        // Skip if menu already exists (registered by AdminMenu).
        if ($this->menu_exists('fields-bright-export')) {
            return;
        }

        add_submenu_page(
            'fields-bright-enrollment',
            __('Export Enrollments', 'fields-bright-enrollment'),
            __('Exports', 'fields-bright-enrollment'),
            'manage_options',
            'fields-bright-export',
            [$this, 'render_export_page']
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
     * Handle export download request.
     *
     * @return void
     */
    public function handle_export_download(): void
    {
        if (! isset($_GET['fb_export']) || $_GET['fb_export'] !== '1') {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export data.', 'fields-bright-enrollment'));
        }

        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'fb_export_enrollments')) {
            wp_die(esc_html__('Security check failed.', 'fields-bright-enrollment'));
        }

        // Sanitize and validate format against allowed values.
        $format = isset($_GET['format']) ? sanitize_text_field(wp_unslash($_GET['format'])) : 'csv';
        $allowed_formats = ['csv'];
        if (! in_array($format, $allowed_formats, true)) {
            $format = 'csv';
        }

        // Sanitize and validate status against allowed values.
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $allowed_statuses = ['', 'completed', 'pending', 'refunded', 'failed'];
        if (! in_array($status, $allowed_statuses, true)) {
            $status = '';
        }

        $filters = [
            'date_from'   => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
            'date_to'     => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
            'status'      => $status,
            'workshop_id' => isset($_GET['workshop_id']) ? absint($_GET['workshop_id']) : 0,
        ];

        $enrollments = $this->get_filtered_enrollments($filters);

        switch ($format) {
            case 'csv':
            default:
                $this->export_csv($enrollments, $filters);
                break;
        }

        exit;
    }

    /**
     * Get enrollments with filters applied.
     *
     * @param array $filters Filter criteria.
     *
     * @return array Filtered enrollments.
     */
    private function get_filtered_enrollments(array $filters): array
    {
        $args = [
            'post_type'      => EnrollmentCPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $meta_query = [];

        // Status filter.
        if (! empty($filters['status'])) {
            $meta_query[] = [
                'key'   => EnrollmentCPT::META_PREFIX . 'status',
                'value' => $filters['status'],
            ];
        }

        // Workshop filter.
        if (! empty($filters['workshop_id'])) {
            $meta_query[] = [
                'key'   => EnrollmentCPT::META_PREFIX . 'workshop_id',
                'value' => $filters['workshop_id'],
            ];
        }

        // Date range filter.
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $date_query = ['relation' => 'AND'];
            
            if (! empty($filters['date_from'])) {
                $args['date_query'][] = [
                    'after'     => $filters['date_from'],
                    'inclusive' => true,
                ];
            }
            
            if (! empty($filters['date_to'])) {
                $args['date_query'][] = [
                    'before'    => $filters['date_to'],
                    'inclusive' => true,
                ];
            }
        }

        if (! empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $posts = get_posts($args);
        $results = [];

        foreach ($posts as $post) {
            $workshop_id = get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
            $workshop = get_post($workshop_id);

            $results[] = [
                'id'                  => $post->ID,
                'confirmation_number' => 'FB-' . str_pad($post->ID, 6, '0', STR_PAD_LEFT),
                'customer_name'       => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true),
                'customer_email'      => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true),
                'customer_phone'      => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'customer_phone', true),
                'workshop_id'         => $workshop_id,
                'workshop_title'      => $workshop ? $workshop->post_title : '',
                'amount'              => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'amount', true),
                'currency'            => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'currency', true) ?: 'USD',
                'status'              => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'status', true),
                'pricing_option'      => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true),
                'date'                => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'date', true),
                'stripe_session_id'   => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_session_id', true),
                'stripe_payment_id'   => get_post_meta($post->ID, EnrollmentCPT::META_PREFIX . 'stripe_payment_intent_id', true),
            ];
        }

        return $results;
    }

    /**
     * Export enrollments as CSV.
     *
     * @param array $enrollments Enrollments to export.
     * @param array $filters     Applied filters.
     *
     * @return void
     */
    private function export_csv(array $enrollments, array $filters): void
    {
        $filename = 'enrollments-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility.
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers.
        fputcsv($output, [
            __('Confirmation #', 'fields-bright-enrollment'),
            __('Customer Name', 'fields-bright-enrollment'),
            __('Email', 'fields-bright-enrollment'),
            __('Phone', 'fields-bright-enrollment'),
            __('Workshop', 'fields-bright-enrollment'),
            __('Amount', 'fields-bright-enrollment'),
            __('Currency', 'fields-bright-enrollment'),
            __('Pricing Option', 'fields-bright-enrollment'),
            __('Status', 'fields-bright-enrollment'),
            __('Date', 'fields-bright-enrollment'),
            __('Payment ID', 'fields-bright-enrollment'),
        ]);

        // Data rows.
        foreach ($enrollments as $enrollment) {
            fputcsv($output, [
                $enrollment['confirmation_number'],
                $enrollment['customer_name'],
                $enrollment['customer_email'],
                $enrollment['customer_phone'],
                $enrollment['workshop_title'],
                $enrollment['amount'],
                $enrollment['currency'],
                $enrollment['pricing_option'],
                ucfirst($enrollment['status']),
                $enrollment['date'],
                $enrollment['stripe_payment_id'],
            ]);
        }

        fclose($output);
    }

    /**
     * Get workshops for filter dropdown.
     *
     * @return array Workshops with enrollments.
     */
    private function get_workshops_with_enrollments(): array
    {
        global $wpdb;

        $workshop_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            EnrollmentCPT::META_PREFIX . 'workshop_id'
        ));

        $workshops = [];
        foreach ($workshop_ids as $id) {
            $workshop = get_post($id);
            if ($workshop) {
                $workshops[$id] = $workshop->post_title;
            }
        }

        return $workshops;
    }

    /**
     * Render the export page.
     *
     * @return void
     */
    public function render_export_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $workshops = $this->get_workshops_with_enrollments();
        $export_url = admin_url('admin.php');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Export Enrollments', 'fields-bright-enrollment'); ?></h1>

            <div class="fb-export-card">
                <form method="get" action="<?php echo esc_url($export_url); ?>">
                    <input type="hidden" name="fb_export" value="1">
                    <?php wp_nonce_field('fb_export_enrollments'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="date_from"><?php esc_html_e('Date From', 'fields-bright-enrollment'); ?></label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="date_from" 
                                       name="date_from" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="date_to"><?php esc_html_e('Date To', 'fields-bright-enrollment'); ?></label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="date_to" 
                                       name="date_to" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="status"><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></label>
                            </th>
                            <td>
                                <select id="status" name="status">
                                    <option value=""><?php esc_html_e('All Statuses', 'fields-bright-enrollment'); ?></option>
                                    <option value="completed"><?php esc_html_e('Completed', 'fields-bright-enrollment'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending', 'fields-bright-enrollment'); ?></option>
                                    <option value="refunded"><?php esc_html_e('Refunded', 'fields-bright-enrollment'); ?></option>
                                    <option value="failed"><?php esc_html_e('Failed', 'fields-bright-enrollment'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="workshop_id"><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></label>
                            </th>
                            <td>
                                <select id="workshop_id" name="workshop_id">
                                    <option value=""><?php esc_html_e('All Workshops', 'fields-bright-enrollment'); ?></option>
                                    <?php foreach ($workshops as $id => $title) : ?>
                                        <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="format"><?php esc_html_e('Format', 'fields-bright-enrollment'); ?></label>
                            </th>
                            <td>
                                <select id="format" name="format">
                                    <option value="csv"><?php esc_html_e('CSV (Excel compatible)', 'fields-bright-enrollment'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Download Export', 'fields-bright-enrollment'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div class="fb-export-info">
                <h3><?php esc_html_e('Export Information', 'fields-bright-enrollment'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Leave filters empty to export all enrollments.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('CSV files can be opened in Excel, Google Sheets, or any spreadsheet application.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Dates are in the format configured in your WordPress settings.', 'fields-bright-enrollment'); ?></li>
                </ul>
            </div>
        </div>

        <style>
            .fb-export-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                max-width: 600px;
                margin: 20px 0;
            }
            .fb-export-card .form-table th {
                width: 150px;
            }
            .fb-export-info {
                background: #f0f0f1;
                border-left: 4px solid #0073aa;
                padding: 15px 20px;
                max-width: 600px;
            }
            .fb-export-info h3 {
                margin-top: 0;
            }
            .fb-export-info ul {
                margin-bottom: 0;
            }
        </style>
        <?php
    }
}

