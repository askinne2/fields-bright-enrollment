<?php
/**
 * Waitlist Notification Email Template
 *
 * Sent when a spot becomes available for someone on the waitlist.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 *
 * Available variables:
 * @var string $customer_name   Customer name
 * @var string $workshop_title  Workshop title
 * @var string $event_start     Event start datetime
 * @var string $claim_url       Magic link to claim spot (with token)
 * @var string $site_name       Site name
 * @var int    $hours_to_claim  Hours they have to claim the spot (default 48)
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

$hours_to_claim = $hours_to_claim ?? 48;
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: #271C1A; padding: 30px; text-align: center;">
        <h1 style="color: #F9DB5E; margin: 0;">🎉 Great News!</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear <?php echo esc_html($customer_name ?? 'Valued Customer'); ?>,</p>
        
        <p style="font-size: 16px; font-weight: bold; color: #155724;">
            A spot has opened up for <strong><?php echo esc_html($workshop_title ?? ''); ?></strong>!
        </p>
        
        <div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
            <h2 style="margin-top: 0; color: #155724;">Your Spot is Reserved</h2>
            <p style="margin: 0;">
                You're receiving this because you joined the waitlist. You have <strong><?php echo esc_html($hours_to_claim); ?> hours</strong> to claim your spot.
            </p>
        </div>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #271C1A;"><?php echo esc_html($workshop_title ?? ''); ?></h3>
            
            <?php if (! empty($event_start)) : ?>
            <p style="margin: 10px 0;">
                <strong>📅 Date:</strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($event_start))); ?>
            </p>
            <?php endif; ?>
            
            <p style="margin: 20px 0; padding: 15px; background: #fff7e6; border-radius: 4px; border-left: 3px solid #F9DB5E;">
                <strong>⏰ Act Fast!</strong> If you don't claim your spot within <?php echo esc_html($hours_to_claim); ?> hours, it will be offered to the next person on the waitlist.
            </p>
        </div>
        
        <?php if (! empty($claim_url)) : ?>
        <p style="text-align: center; margin: 30px 0;">
            <a href="<?php echo esc_url($claim_url); ?>" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 15px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                🎯 Claim Your Spot Now →
            </a>
        </p>
        <p style="text-align: center; color: #666; font-size: 13px; font-style: italic;">
            This link is reserved exclusively for you and expires in <?php echo esc_html($hours_to_claim); ?> hours.
        </p>
        <?php endif; ?>
        
        <p style="color: #666; font-size: 14px;">
            If you're no longer interested, no action is needed. Your spot will automatically be offered to someone else.
        </p>
    </div>
    
    <!-- Footer -->
    <div style="background: #271C1A; padding: 20px; text-align: center;">
        <p style="color: #fff; margin: 0; font-size: 14px;"><?php echo esc_html($site_name); ?></p>
    </div>
</div>

