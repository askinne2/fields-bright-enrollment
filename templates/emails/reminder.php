<?php
/**
 * Workshop Reminder Email Template
 *
 * Sent 24-48 hours before a workshop begins.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 *
 * Available variables:
 * @var string $customer_name    Customer name
 * @var string $workshop_title   Workshop title
 * @var string $workshop_url     Workshop URL
 * @var string $event_start      Event start datetime
 * @var string $event_end        Event end datetime
 * @var string $event_location   Event location
 * @var string $recurring_info   Recurring date info
 * @var string $site_name        Site name
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

$schedule = $recurring_info ?: '';
if (! $schedule && $event_start) {
    $schedule = date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($event_start));
}
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: #271C1A; padding: 30px; text-align: center;">
        <h1 style="color: #F9DB5E; margin: 0;">Workshop Reminder</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear <?php echo esc_html($customer_name ?? 'Valued Customer'); ?>,</p>
        
        <p>This is a friendly reminder that your workshop is coming up soon!</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #F9DB5E;">
            <h2 style="margin-top: 0; color: #271C1A;"><?php echo esc_html($workshop_title ?? ''); ?></h2>
            
            <?php if ($schedule) : ?>
            <p style="margin: 10px 0;">
                <strong>📅 When:</strong> <?php echo esc_html($schedule); ?>
            </p>
            <?php endif; ?>
            
            <?php if (! empty($event_location)) : ?>
            <p style="margin: 10px 0;">
                <strong>📍 Where:</strong> <?php echo esc_html($event_location); ?>
            </p>
            <?php endif; ?>
        </div>
        
        <h3 style="color: #271C1A;">What to Bring</h3>
        <ul>
            <li>A notebook and pen for taking notes</li>
            <li>An open mind and willingness to participate</li>
            <li>Any specific materials mentioned in your enrollment confirmation</li>
        </ul>
        
        <h3 style="color: #271C1A;">Before You Arrive</h3>
        <p>Please review any pre-workshop materials or readings that were sent to you.</p>
        
        <?php if (! empty($workshop_url)) : ?>
        <p style="text-align: center;">
            <a href="<?php echo esc_url($workshop_url); ?>" style="display: inline-block; background: #F9DB5E; color: #271C1A; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                View Workshop Details
            </a>
        </p>
        <?php endif; ?>
        
        <p style="margin-top: 30px;">We're looking forward to seeing you there!</p>
        
        <p style="color: #666; font-size: 14px;">
            Questions? Reply to this email and we'll be happy to help.
        </p>
    </div>
    
    <!-- Footer -->
    <div style="background: #271C1A; padding: 20px; text-align: center;">
        <p style="color: #fff; margin: 0; font-size: 14px;"><?php echo esc_html($site_name); ?></p>
    </div>
</div>

