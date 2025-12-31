<?php
/**
 * Workshop Follow-up Email Template
 *
 * Sent after a workshop is completed.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 *
 * Available variables:
 * @var string $customer_name   Customer name
 * @var string $workshop_title  Workshop title
 * @var string $site_name       Site name
 * @var string $feedback_url    Feedback/survey URL
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: #271C1A; padding: 30px; text-align: center;">
        <h1 style="color: #F9DB5E; margin: 0;">Thank You for Attending!</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear <?php echo esc_html($customer_name ?? 'Valued Customer'); ?>,</p>
        
        <p>Thank you for participating in <strong><?php echo esc_html($workshop_title ?? ''); ?></strong>. We hope you found it valuable and enriching.</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2 style="margin-top: 0; color: #271C1A;">We Value Your Feedback</h2>
            <p>Your input helps us improve our workshops and better serve our community. We'd love to hear about your experience!</p>
            
            <?php if (! empty($feedback_url)) : ?>
            <p style="text-align: center; margin-top: 20px;">
                <a href="<?php echo esc_url($feedback_url); ?>" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                    Share Your Feedback
                </a>
            </p>
            <?php endif; ?>
        </div>
        
        <h3 style="color: #271C1A;">What's Next?</h3>
        <ul>
            <li><strong>Practice:</strong> Apply what you've learned in your daily life</li>
            <li><strong>Connect:</strong> Stay in touch with fellow participants</li>
            <li><strong>Continue Learning:</strong> Explore our upcoming workshops</li>
        </ul>
        
        <div style="background: #fff7e6; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #F9DB5E;">
            <p style="margin: 0;">
                <strong>Special Offer:</strong> As a thank you, enjoy 10% off your next workshop enrollment! Use code <strong>THANKYOU10</strong> at checkout.
            </p>
        </div>
        
        <p style="margin-top: 30px;">We hope to see you at a future workshop!</p>
    </div>
    
    <!-- Footer -->
    <div style="background: #271C1A; padding: 20px; text-align: center;">
        <p style="color: #fff; margin: 0; font-size: 14px;"><?php echo esc_html($site_name); ?></p>
    </div>
</div>

