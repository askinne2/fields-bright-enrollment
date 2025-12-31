<?php
/**
 * Enrollment Cancellation Email Template
 *
 * Sent when an enrollment is cancelled.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 *
 * Available variables:
 * @var string $customer_name      Customer name
 * @var string $workshop_title     Workshop title
 * @var string $confirmation_number Confirmation number
 * @var string $site_name          Site name
 * @var string $browse_url         Browse workshops URL
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: #271C1A; padding: 30px; text-align: center;">
        <h1 style="color: #F9DB5E; margin: 0;">Enrollment Cancelled</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear <?php echo esc_html($customer_name ?? 'Valued Customer'); ?>,</p>
        
        <p>Your enrollment for <strong><?php echo esc_html($workshop_title ?? ''); ?></strong> has been cancelled as requested.</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <?php if (! empty($confirmation_number)) : ?>
            <p><strong>Confirmation #:</strong> <?php echo esc_html($confirmation_number); ?></p>
            <?php endif; ?>
            <p style="margin: 15px 0; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                If a refund is applicable, it will be processed within 5-10 business days.
            </p>
        </div>
        
        <h3 style="color: #271C1A;">We're Here for You</h3>
        <p>We understand that plans change. If you have any questions or concerns about this cancellation, please don't hesitate to reach out.</p>
        
        <h3 style="color: #271C1A;">Future Workshops</h3>
        <p>We hope this cancellation doesn't mean goodbye! We'd love to see you at a future workshop that better fits your schedule.</p>
        
        <?php if (! empty($browse_url)) : ?>
        <p style="text-align: center;">
            <a href="<?php echo esc_url($browse_url); ?>" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Browse Upcoming Workshops
            </a>
        </p>
        <?php endif; ?>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            If you cancelled by mistake or would like to re-enroll, please contact us as soon as possible.
        </p>
    </div>
    
    <!-- Footer -->
    <div style="background: #271C1A; padding: 20px; text-align: center;">
        <p style="color: #fff; margin: 0; font-size: 14px;"><?php echo esc_html($site_name); ?></p>
    </div>
</div>

