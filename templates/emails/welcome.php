<?php
/**
 * Welcome Email Template
 *
 * Sent when a new user account is created.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 *
 * Available variables:
 * @var string $customer_name   Customer name
 * @var string $customer_email  Customer email
 * @var string $site_name      Site name
 * @var string $site_url       Site URL
 * @var string $login_url      Login URL
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: #271C1A; padding: 30px; text-align: center;">
        <h1 style="color: #F9DB5E; margin: 0;">Welcome to <?php echo esc_html($site_name); ?>!</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear <?php echo esc_html($customer_name ?? 'Valued Customer'); ?>,</p>
        
        <p>Thank you for creating an account with us! We're excited to have you as part of our community.</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2 style="margin-top: 0; color: #271C1A;">Your Account Details</h2>
            <p><strong>Email:</strong> <?php echo esc_html($customer_email ?? ''); ?></p>
            <p>You can now:</p>
            <ul>
                <li>View your enrollment history</li>
                <li>Access workshop materials</li>
                <li>Manage your account settings</li>
                <li>Enroll in upcoming workshops</li>
            </ul>
        </div>
        
        <?php if (! empty($login_url)) : ?>
        <p style="text-align: center;">
            <a href="<?php echo esc_url($login_url); ?>" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Access Your Account
            </a>
        </p>
        <?php endif; ?>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            If you have any questions, feel free to reply to this email or contact us.
        </p>
    </div>
    
    <!-- Footer -->
    <div style="background: #271C1A; padding: 20px; text-align: center;">
        <p style="color: #fff; margin: 0; font-size: 14px;"><?php echo esc_html($site_name); ?></p>
        <p style="color: #999; margin: 5px 0 0; font-size: 12px;"><?php echo esc_html($site_url); ?></p>
    </div>
</div>

