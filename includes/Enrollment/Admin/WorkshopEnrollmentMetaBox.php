<?php
/**
 * Workshop Enrollment Meta Box
 *
 * Adds a meta box to workshop posts showing enrollments
 * for that specific workshop.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\PostType\EnrollmentCPT;
use FieldsBright\Enrollment\PostType\WorkshopCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WorkshopEnrollmentMetaBox
 *
 * Displays enrollment list on workshop edit screen.
 *
 * @since 1.0.0
 */
class WorkshopEnrollmentMetaBox
{
    /**
     * Constructor.
     *
     * Registers hooks for the meta box.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('admin_post_export_workshop_enrollments', [$this, 'handle_export']);
    }

    /**
     * Register the enrollments meta box.
     *
     * @return void
     */
    public function register_meta_box(): void
    {
        // Register for regular posts (backward compatibility)
        add_meta_box(
            'workshop_enrollments',
            __('Enrollments', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            'post',
            'normal',
            'default'
        );

        // Register for workshop CPT
        add_meta_box(
            'workshop_enrollments',
            __('Enrollments', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            WorkshopCPT::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * Render the enrollments meta box.
     *
     * @param \WP_Post $post The post object.
     *
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void
    {
        // For workshop CPT, always show the meta box
        $is_workshop = ($post->post_type === WorkshopCPT::POST_TYPE) 
            || ($post->post_type === 'post' && has_category('workshops', $post));

        // Only show for workshops (CPT or category)
        if (! $is_workshop) {
            echo '<p class="description">';
            esc_html_e('Enrollment tracking is available for workshop posts.', 'fields-bright-enrollment');
            echo '</p>';
            return;
        }

        $enrollment_cpt = new EnrollmentCPT();
        $enrollments = $enrollment_cpt->get_enrollments_for_workshop($post->ID);
        $completed_count = $enrollment_cpt->get_enrollment_count($post->ID, 'completed');
        $pending_count = $enrollment_cpt->get_enrollment_count($post->ID, 'pending');
        ?>
        <style>
            .workshop-enrollments-summary {
                display: flex;
                gap: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            .workshop-enrollments-summary .stat {
                text-align: center;
            }
            .workshop-enrollments-summary .stat-number {
                font-size: 24px;
                font-weight: 600;
                color: #333;
                display: block;
            }
            .workshop-enrollments-summary .stat-label {
                font-size: 12px;
                color: #666;
            }
            .workshop-enrollments-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            .workshop-enrollments-table th,
            .workshop-enrollments-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .workshop-enrollments-table th {
                background: #f8f9fa;
                font-weight: 600;
            }
            .enrollment-status-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
            }
            .enrollment-status-completed {
                background: #d4edda;
                color: #155724;
            }
            .enrollment-status-pending {
                background: #fff3cd;
                color: #856404;
            }
            .enrollment-status-refunded {
                background: #f8d7da;
                color: #721c24;
            }
            .workshop-enrollments-actions {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
        </style>

        <div class="workshop-enrollments-summary">
            <div class="stat">
                <span class="stat-number"><?php echo esc_html(count($enrollments)); ?></span>
                <span class="stat-label"><?php esc_html_e('Total', 'fields-bright-enrollment'); ?></span>
            </div>
            <div class="stat">
                <span class="stat-number" style="color: #28a745;"><?php echo esc_html($completed_count); ?></span>
                <span class="stat-label"><?php esc_html_e('Completed', 'fields-bright-enrollment'); ?></span>
            </div>
            <div class="stat">
                <span class="stat-number" style="color: #ffc107;"><?php echo esc_html($pending_count); ?></span>
                <span class="stat-label"><?php esc_html_e('Pending', 'fields-bright-enrollment'); ?></span>
            </div>
        </div>

        <?php if (empty($enrollments)) : ?>
            <p class="description"><?php esc_html_e('No enrollments yet for this workshop.', 'fields-bright-enrollment'); ?></p>
        <?php else : ?>
            <table class="workshop-enrollments-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'fields-bright-enrollment'); ?></th>
                        <th><?php esc_html_e('Email', 'fields-bright-enrollment'); ?></th>
                        <th><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></th>
                        <th><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></th>
                        <th><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enrollment) : 
                        $name = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true);
                        $email = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true);
                        $amount = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', true);
                        $status = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true) ?: 'pending';
                        $date = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'date', true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($name ?: '—'); ?></td>
                            <td>
                                <?php if ($email) : ?>
                                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo esc_html(number_format((float) $amount, 2)); ?></td>
                            <td>
                                <span class="enrollment-status-badge enrollment-status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($date ? date_i18n(get_option('date_format'), strtotime($date)) : '—'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($enrollment->ID)); ?>" class="button button-small">
                                    <?php esc_html_e('View', 'fields-bright-enrollment'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="workshop-enrollments-actions">
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=export_workshop_enrollments&workshop_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce('export_workshop_enrollments'))); ?>" class="button">
                    <?php esc_html_e('Export to CSV', 'fields-bright-enrollment'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=enrollment&enrollment_workshop_filter=' . $post->ID)); ?>" class="button">
                    <?php esc_html_e('View All Enrollments', 'fields-bright-enrollment'); ?>
                </a>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Handle CSV export for a workshop's enrollments.
     *
     * @return void
     */
    public function handle_export(): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'export_workshop_enrollments')) {
            wp_die(esc_html__('Invalid security token.', 'fields-bright-enrollment'));
        }

        // Check permissions
        if (! current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to export enrollments.', 'fields-bright-enrollment'));
        }

        $workshop_id = isset($_GET['workshop_id']) ? absint($_GET['workshop_id']) : 0;

        if (! $workshop_id) {
            wp_die(__('Workshop not specified.', 'fields-bright-enrollment'));
        }

        $workshop = get_post($workshop_id);
        if (! $workshop) {
            wp_die(__('Workshop not found.', 'fields-bright-enrollment'));
        }

        $enrollment_cpt = new EnrollmentCPT();
        $enrollments = $enrollment_cpt->get_enrollments_for_workshop($workshop_id);

        $filename = sanitize_title($workshop->post_title) . '-enrollments-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'ID',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Amount',
            'Status',
            'Date',
            'Pricing Option',
        ]);

        // Data
        foreach ($enrollments as $enrollment) {
            fputcsv($output, [
                $enrollment->ID,
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_phone', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'date', true),
                get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'pricing_option_id', true),
            ]);
        }

        fclose($output);
        exit;
    }
}

