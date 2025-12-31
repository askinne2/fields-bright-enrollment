<?php
/**
 * Enrollment Dashboard
 *
 * Provides an overview dashboard for the enrollment system showing
 * statistics, recent enrollments, and quick actions.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\PostType\EnrollmentCPT;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Dashboard
 *
 * Handles the enrollment dashboard page.
 *
 * @since 1.1.0
 */
class Dashboard
{
    /**
     * Get enrollment statistics.
     *
     * @return array{total: int, pending: int, completed: int, refunded: int, failed: int, revenue: float}
     */
    private function get_stats(): array
    {
        $stats = [
            'total'     => 0,
            'pending'   => 0,
            'completed' => 0,
            'refunded'  => 0,
            'failed'    => 0,
            'revenue'   => 0.0,
        ];

        // Get all enrollments.
        $enrollments = get_posts([
            'post_type'      => EnrollmentCPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $stats['total'] = count($enrollments);

        // Count by status and calculate revenue.
        foreach ($enrollments as $enrollment_id) {
            $status = get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'status', true);
            $amount = (float) get_post_meta($enrollment_id, EnrollmentCPT::META_PREFIX . 'amount', true);

            if (isset($stats[$status])) {
                $stats[$status]++;
            }

            if ($status === 'completed') {
                $stats['revenue'] += $amount;
            }
        }

        return $stats;
    }

    /**
     * Get recent enrollments.
     *
     * @param int $limit Number of enrollments to retrieve.
     *
     * @return array
     */
    private function get_recent_enrollments(int $limit = 10): array
    {
        $enrollments = get_posts([
            'post_type'      => EnrollmentCPT::POST_TYPE,
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $results = [];
        foreach ($enrollments as $enrollment) {
            $workshop_id = get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'workshop_id', true);
            $workshop = get_post($workshop_id);

            $results[] = [
                'id'             => $enrollment->ID,
                'title'          => $enrollment->post_title,
                'customer_name'  => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_name', true),
                'customer_email' => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'customer_email', true),
                'workshop_title' => $workshop ? $workshop->post_title : __('Unknown', 'fields-bright-enrollment'),
                'workshop_id'    => $workshop_id,
                'amount'         => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'amount', true),
                'status'         => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'status', true),
                'date'           => get_post_meta($enrollment->ID, EnrollmentCPT::META_PREFIX . 'date', true),
                'edit_url'       => get_edit_post_link($enrollment->ID, 'raw'),
            ];
        }

        return $results;
    }

    /**
     * Check if Stripe is configured.
     *
     * @return bool
     */
    private function is_stripe_configured(): bool
    {
        $stripe = EnrollmentSystem::instance()->get_stripe_handler();
        return $stripe && $stripe->is_configured();
    }

    /**
     * Check if in test mode.
     *
     * @return bool
     */
    private function is_test_mode(): bool
    {
        return (bool) EnrollmentSystem::get_option('stripe_test_mode', true);
    }

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $stats = $this->get_stats();
        $recent_enrollments = $this->get_recent_enrollments(10);
        $is_stripe_configured = $this->is_stripe_configured();
        $is_test_mode = $this->is_test_mode();
        $webhook_url = rest_url('fields-bright/v1/stripe/webhook');
        ?>
        <div class="wrap enrollment-dashboard">
            <h1><?php esc_html_e('Enrollment Dashboard', 'fields-bright-enrollment'); ?></h1>

            <!-- Status Banner -->
            <div class="enrollment-dashboard__status-banner">
                <div class="status-item">
                    <span class="status-label"><?php esc_html_e('Mode:', 'fields-bright-enrollment'); ?></span>
                    <?php if ($is_test_mode) : ?>
                        <span class="status-badge status-badge--warning"><?php esc_html_e('Test Mode', 'fields-bright-enrollment'); ?></span>
                    <?php else : ?>
                        <span class="status-badge status-badge--success"><?php esc_html_e('Live Mode', 'fields-bright-enrollment'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="status-item">
                    <span class="status-label"><?php esc_html_e('Stripe:', 'fields-bright-enrollment'); ?></span>
                    <?php if ($is_stripe_configured) : ?>
                        <span class="status-badge status-badge--success"><?php esc_html_e('Configured', 'fields-bright-enrollment'); ?></span>
                    <?php else : ?>
                        <span class="status-badge status-badge--error"><?php esc_html_e('Not Configured', 'fields-bright-enrollment'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="enrollment-dashboard__stats">
                <div class="stat-card stat-card--primary">
                    <div class="stat-card__value"><?php echo esc_html(number_format($stats['total'])); ?></div>
                    <div class="stat-card__label"><?php esc_html_e('Total Enrollments', 'fields-bright-enrollment'); ?></div>
                </div>
                <div class="stat-card stat-card--success">
                    <div class="stat-card__value"><?php echo esc_html(number_format($stats['completed'])); ?></div>
                    <div class="stat-card__label"><?php esc_html_e('Completed', 'fields-bright-enrollment'); ?></div>
                </div>
                <div class="stat-card stat-card--warning">
                    <div class="stat-card__value"><?php echo esc_html(number_format($stats['pending'])); ?></div>
                    <div class="stat-card__label"><?php esc_html_e('Pending', 'fields-bright-enrollment'); ?></div>
                </div>
                <div class="stat-card stat-card--info">
                    <div class="stat-card__value">$<?php echo esc_html(number_format($stats['revenue'], 2)); ?></div>
                    <div class="stat-card__label"><?php esc_html_e('Total Revenue', 'fields-bright-enrollment'); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="enrollment-dashboard__actions">
                <h2><?php esc_html_e('Quick Actions', 'fields-bright-enrollment'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=enrollment')); ?>" class="button button-primary">
                        <?php esc_html_e('View All Enrollments', 'fields-bright-enrollment'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fields-bright-enrollment-settings')); ?>" class="button">
                        <?php esc_html_e('Settings', 'fields-bright-enrollment'); ?>
                    </a>
                    <?php if (! $is_stripe_configured) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fields-bright-enrollment-settings')); ?>" class="button button-secondary">
                            <?php esc_html_e('Configure Stripe', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="enrollment-dashboard__recent">
                <h2><?php esc_html_e('Recent Enrollments', 'fields-bright-enrollment'); ?></h2>
                <?php if (empty($recent_enrollments)) : ?>
                    <p class="no-enrollments"><?php esc_html_e('No enrollments yet.', 'fields-bright-enrollment'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Customer', 'fields-bright-enrollment'); ?></th>
                                <th><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></th>
                                <th><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></th>
                                <th><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></th>
                                <th><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></th>
                                <th><?php esc_html_e('Actions', 'fields-bright-enrollment'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_enrollments as $enrollment) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($enrollment['customer_name'] ?: __('Unknown', 'fields-bright-enrollment')); ?></strong>
                                        <?php if ($enrollment['customer_email']) : ?>
                                            <br><small><?php echo esc_html($enrollment['customer_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['workshop_id']) : ?>
                                            <a href="<?php echo esc_url(get_edit_post_link($enrollment['workshop_id'], 'raw')); ?>">
                                                <?php echo esc_html($enrollment['workshop_title']); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo esc_html($enrollment['workshop_title']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo esc_html(number_format((float) $enrollment['amount'], 2)); ?></td>
                                    <td>
                                        <?php
                                        $status = $enrollment['status'] ?: 'pending';
                                        $status_class = 'status-badge--' . $status;
                                        $status_labels = [
                                            'pending'   => __('Pending', 'fields-bright-enrollment'),
                                            'completed' => __('Completed', 'fields-bright-enrollment'),
                                            'refunded'  => __('Refunded', 'fields-bright-enrollment'),
                                            'failed'    => __('Failed', 'fields-bright-enrollment'),
                                        ];
                                        ?>
                                        <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_labels[$status] ?? ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($enrollment['date']) {
                                            echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment['date'])));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($enrollment['edit_url']); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'fields-bright-enrollment'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="view-all">
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=enrollment')); ?>">
                            <?php esc_html_e('View all enrollments →', 'fields-bright-enrollment'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Webhook Info -->
            <div class="enrollment-dashboard__info">
                <h2><?php esc_html_e('Webhook Information', 'fields-bright-enrollment'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Webhook URL', 'fields-bright-enrollment'); ?></th>
                        <td>
                            <code id="webhook-url"><?php echo esc_html($webhook_url); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('webhook-url').textContent); this.textContent='<?php esc_attr_e('Copied!', 'fields-bright-enrollment'); ?>'; setTimeout(() => this.textContent='<?php esc_attr_e('Copy', 'fields-bright-enrollment'); ?>', 2000);">
                                <?php esc_html_e('Copy', 'fields-bright-enrollment'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Required Events', 'fields-bright-enrollment'); ?></th>
                        <td>
                            <code>checkout.session.completed</code>, <code>charge.refunded</code>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <style>
            .enrollment-dashboard {
                max-width: 1200px;
            }
            .enrollment-dashboard__status-banner {
                display: flex;
                gap: 20px;
                background: #fff;
                padding: 15px 20px;
                border: 1px solid #ccd0d4;
                border-left: 4px solid #0073aa;
                margin: 20px 0;
            }
            .status-item {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .status-label {
                font-weight: 600;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .status-badge--success { background: #d4edda; color: #155724; }
            .status-badge--warning { background: #fff3cd; color: #856404; }
            .status-badge--error { background: #f8d7da; color: #721c24; }
            .status-badge--pending { background: #fff3cd; color: #856404; }
            .status-badge--completed { background: #d4edda; color: #155724; }
            .status-badge--refunded { background: #d1ecf1; color: #0c5460; }
            .status-badge--failed { background: #f8d7da; color: #721c24; }
            
            .enrollment-dashboard__stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            .stat-card--primary { border-left: 4px solid #0073aa; }
            .stat-card--success { border-left: 4px solid #46b450; }
            .stat-card--warning { border-left: 4px solid #ffb900; }
            .stat-card--info { border-left: 4px solid #00a0d2; }
            .stat-card__value {
                font-size: 32px;
                font-weight: 600;
                color: #23282d;
            }
            .stat-card__label {
                font-size: 14px;
                color: #666;
                margin-top: 5px;
            }
            
            .enrollment-dashboard__actions {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
            }
            .enrollment-dashboard__actions h2 {
                margin-top: 0;
            }
            .action-buttons {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .enrollment-dashboard__recent {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
            }
            .enrollment-dashboard__recent h2 {
                margin-top: 0;
            }
            .enrollment-dashboard__recent .no-enrollments {
                color: #666;
                font-style: italic;
            }
            .enrollment-dashboard__recent .view-all {
                margin-top: 15px;
                text-align: right;
            }
            
            .enrollment-dashboard__info {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
            }
            .enrollment-dashboard__info h2 {
                margin-top: 0;
            }
            .enrollment-dashboard__info code {
                background: #f0f0f1;
                padding: 4px 8px;
                border-radius: 3px;
            }
        </style>
        <?php
    }
}

