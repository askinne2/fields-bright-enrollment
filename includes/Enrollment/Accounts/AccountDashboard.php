<?php
/**
 * Account Dashboard
 *
 * Provides frontend account management for customers to view
 * their enrollment history and manage their account.
 *
 * @package FieldsBright\Enrollment\Accounts
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Accounts;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class AccountDashboard
 *
 * Handles the frontend account dashboard shortcode and display.
 *
 * @since 1.1.0
 */
class AccountDashboard
{
    /**
     * User account handler.
     *
     * @var UserAccountHandler
     */
    private UserAccountHandler $account_handler;

    /**
     * Constructor.
     *
     * @param UserAccountHandler|null $account_handler Optional account handler instance.
     */
    public function __construct(?UserAccountHandler $account_handler = null)
    {
        $this->account_handler = $account_handler ?? new UserAccountHandler();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_shortcode('enrollment_account', [$this, 'render_account_dashboard']);
        add_shortcode('enrollment_history', [$this, 'render_enrollment_history']);
    }

    /**
     * Render the account dashboard shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_account_dashboard(array $atts = []): string
    {
        $atts = shortcode_atts([
            'show_header'  => 'true',
            'show_profile' => 'true',
        ], $atts);

        // Check if user is logged in.
        if (! is_user_logged_in()) {
            return $this->render_login_form();
        }

        $user = wp_get_current_user();
        $enrollments = $this->account_handler->get_user_enrollments($user->ID);
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $show_profile = filter_var($atts['show_profile'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        ?>
        <div class="fb-account-dashboard">
            <?php if ($show_header) : ?>
            <div class="fb-account-dashboard__header">
                <h2><?php esc_html_e('My Account', 'fields-bright-enrollment'); ?></h2>
                <p class="fb-account-dashboard__welcome">
                    <?php 
                    printf(
                        /* translators: %s: User display name */
                        esc_html__('Welcome back, %s!', 'fields-bright-enrollment'),
                        esc_html($user->display_name)
                    );
                    ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($show_profile) : ?>
            <div class="fb-account-dashboard__profile">
                <h3><?php esc_html_e('Account Details', 'fields-bright-enrollment'); ?></h3>
                <dl class="fb-account-details">
                    <dt><?php esc_html_e('Name', 'fields-bright-enrollment'); ?></dt>
                    <dd><?php echo esc_html($user->display_name); ?></dd>
                    
                    <dt><?php esc_html_e('Email', 'fields-bright-enrollment'); ?></dt>
                    <dd><?php echo esc_html($user->user_email); ?></dd>
                </dl>
                <p class="fb-account-actions">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="fb-btn fb-btn--link">
                        <?php esc_html_e('Change Password', 'fields-bright-enrollment'); ?>
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="fb-btn fb-btn--link">
                        <?php esc_html_e('Log Out', 'fields-bright-enrollment'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>

            <div class="fb-account-dashboard__enrollments">
                <h3><?php esc_html_e('My Enrollments', 'fields-bright-enrollment'); ?></h3>
                
                <?php if (empty($enrollments)) : ?>
                <p class="fb-no-enrollments">
                    <?php esc_html_e('You haven\'t enrolled in any workshops yet.', 'fields-bright-enrollment'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(home_url('/workshops/')); ?>" class="fb-btn fb-btn--primary">
                        <?php esc_html_e('Browse Workshops', 'fields-bright-enrollment'); ?>
                    </a>
                </p>
                <?php else : ?>
                <div class="fb-enrollment-list">
                    <?php foreach ($enrollments as $enrollment) : ?>
                    <div class="fb-enrollment-card">
                        <div class="fb-enrollment-card__header">
                            <span class="fb-enrollment-card__confirmation">
                                <?php echo esc_html($enrollment['confirmation_number']); ?>
                            </span>
                            <span class="fb-enrollment-card__status fb-status--<?php echo esc_attr($enrollment['status'] ?: 'pending'); ?>">
                                <?php echo esc_html(ucfirst($enrollment['status'] ?: 'pending')); ?>
                            </span>
                        </div>
                        <div class="fb-enrollment-card__body">
                            <h4 class="fb-enrollment-card__title">
                                <?php if ($enrollment['workshop_url']) : ?>
                                <a href="<?php echo esc_url($enrollment['workshop_url']); ?>">
                                    <?php echo esc_html($enrollment['workshop_title']); ?>
                                </a>
                                <?php else : ?>
                                <?php echo esc_html($enrollment['workshop_title']); ?>
                                <?php endif; ?>
                            </h4>
                            <div class="fb-enrollment-card__meta">
                                <span class="fb-enrollment-card__date">
                                    <?php 
                                    if ($enrollment['date']) {
                                        echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment['date'])));
                                    }
                                    ?>
                                </span>
                                <span class="fb-enrollment-card__amount">
                                    $<?php echo esc_html(number_format((float) $enrollment['amount'], 2)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .fb-account-dashboard {
                max-width: 800px;
                margin: 0 auto;
            }
            .fb-account-dashboard__header {
                margin-bottom: 30px;
            }
            .fb-account-dashboard__header h2 {
                font-family: var(--fb-font-heading, Georgia, serif);
                color: var(--fb-primary, #271C1A);
                margin-bottom: 10px;
            }
            .fb-account-dashboard__welcome {
                color: var(--fb-secondary, #666);
            }
            .fb-account-dashboard__profile {
                background: var(--fb-cream, #FDFDFC);
                padding: 25px;
                border-radius: 8px;
                margin-bottom: 30px;
            }
            .fb-account-dashboard__profile h3 {
                margin-top: 0;
                font-family: var(--fb-font-heading, Georgia, serif);
            }
            .fb-account-details {
                display: grid;
                grid-template-columns: 100px 1fr;
                gap: 10px 20px;
                margin: 20px 0;
            }
            .fb-account-details dt {
                font-weight: 600;
                color: var(--fb-secondary, #666);
            }
            .fb-account-details dd {
                margin: 0;
            }
            .fb-account-actions {
                display: flex;
                gap: 15px;
                margin-top: 20px;
            }
            .fb-account-dashboard__enrollments h3 {
                font-family: var(--fb-font-heading, Georgia, serif);
                margin-bottom: 20px;
            }
            .fb-no-enrollments {
                color: var(--fb-secondary, #666);
                font-style: italic;
            }
            .fb-enrollment-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .fb-enrollment-card {
                background: #fff;
                border: 1px solid #eee;
                border-radius: 8px;
                overflow: hidden;
            }
            .fb-enrollment-card__header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
                background: var(--fb-cream, #f9f9f9);
                border-bottom: 1px solid #eee;
            }
            .fb-enrollment-card__confirmation {
                font-family: monospace;
                font-weight: 600;
            }
            .fb-enrollment-card__status {
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .fb-status--completed {
                background: #d4edda;
                color: #155724;
            }
            .fb-status--pending {
                background: #fff3cd;
                color: #856404;
            }
            .fb-status--refunded {
                background: #d1ecf1;
                color: #0c5460;
            }
            .fb-status--failed {
                background: #f8d7da;
                color: #721c24;
            }
            .fb-enrollment-card__body {
                padding: 20px;
            }
            .fb-enrollment-card__title {
                margin: 0 0 10px;
                font-size: 18px;
            }
            .fb-enrollment-card__title a {
                color: var(--fb-primary, #271C1A);
                text-decoration: none;
            }
            .fb-enrollment-card__title a:hover {
                color: var(--fb-accent, #F9DB5E);
            }
            .fb-enrollment-card__meta {
                display: flex;
                justify-content: space-between;
                color: var(--fb-secondary, #666);
                font-size: 14px;
            }
            .fb-enrollment-card__amount {
                font-weight: 600;
            }
            .fb-btn {
                display: inline-block;
                padding: 10px 20px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 600;
                cursor: pointer;
                border: none;
            }
            .fb-btn--primary {
                background: var(--fb-accent, #F9DB5E);
                color: var(--fb-primary, #271C1A);
            }
            .fb-btn--secondary {
                background: var(--fb-primary, #271C1A);
                color: #fff;
            }
            .fb-btn--link {
                background: transparent;
                color: var(--fb-secondary, #666);
            }
            .fb-btn:hover {
                opacity: 0.9;
            }
            .fb-login-form {
                max-width: 400px;
                margin: 0 auto;
                padding: 30px;
                background: var(--fb-cream, #FDFDFC);
                border-radius: 8px;
            }
            .fb-login-form h3 {
                margin-top: 0;
                font-family: var(--fb-font-heading, Georgia, serif);
                text-align: center;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render enrollment history shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_enrollment_history(array $atts = []): string
    {
        $atts = shortcode_atts([
            'status' => '',
            'limit'  => 10,
        ], $atts);

        if (! is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your enrollment history.', 'fields-bright-enrollment') . '</p>';
        }

        $user_id = get_current_user_id();
        $enrollments = $this->account_handler->get_user_enrollments($user_id, $atts['status']);
        
        // Apply limit.
        if ($atts['limit'] > 0) {
            $enrollments = array_slice($enrollments, 0, (int) $atts['limit']);
        }

        if (empty($enrollments)) {
            return '<p class="fb-no-enrollments">' . esc_html__('No enrollments found.', 'fields-bright-enrollment') . '</p>';
        }

        ob_start();
        ?>
        <table class="fb-enrollment-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Confirmation', 'fields-bright-enrollment'); ?></th>
                    <th><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></th>
                    <th><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></th>
                    <th><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></th>
                    <th><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $enrollment) : ?>
                <tr>
                    <td><?php echo esc_html($enrollment['confirmation_number']); ?></td>
                    <td>
                        <?php if ($enrollment['workshop_url']) : ?>
                        <a href="<?php echo esc_url($enrollment['workshop_url']); ?>">
                            <?php echo esc_html($enrollment['workshop_title']); ?>
                        </a>
                        <?php else : ?>
                        <?php echo esc_html($enrollment['workshop_title']); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($enrollment['date']) {
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment['date'])));
                        }
                        ?>
                    </td>
                    <td>$<?php echo esc_html(number_format((float) $enrollment['amount'], 2)); ?></td>
                    <td>
                        <span class="fb-status--<?php echo esc_attr($enrollment['status'] ?: 'pending'); ?>">
                            <?php echo esc_html(ucfirst($enrollment['status'] ?: 'pending')); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
            .fb-enrollment-table {
                width: 100%;
                border-collapse: collapse;
            }
            .fb-enrollment-table th,
            .fb-enrollment-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .fb-enrollment-table th {
                background: var(--fb-cream, #f9f9f9);
                font-weight: 600;
            }
            .fb-enrollment-table a {
                color: var(--fb-primary, #271C1A);
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login form for non-logged-in users.
     *
     * @return string HTML output.
     */
    private function render_login_form(): string
    {
        ob_start();
        ?>
        <div class="fb-login-form">
            <h3><?php esc_html_e('Sign In', 'fields-bright-enrollment'); ?></h3>
            <p><?php esc_html_e('Please log in to view your account and enrollment history.', 'fields-bright-enrollment'); ?></p>
            
            <?php
            wp_login_form([
                'redirect'       => get_permalink(),
                'remember'       => true,
                'value_remember' => true,
            ]);
            ?>
            
            <p class="fb-login-links">
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                    <?php esc_html_e('Forgot password?', 'fields-bright-enrollment'); ?>
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}

