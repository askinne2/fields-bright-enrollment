<?php
/**
 * Email Template: Enrollment Confirmation
 *
 * This template is used when a customer successfully enrolls in a workshop.
 * Variables available: All keys from EmailHandler::get_enrollment_data()
 *
 * @package FieldsBright\Enrollment\Email
 * @since   1.1.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Format schedule display.
$schedule_display = $recurring_info ?: '';
if (! $schedule_display && $event_start) {
    $schedule_display = date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($event_start));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Enrollment Confirmation', 'fields-bright-enrollment'); ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Poppins', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" width="600" align="center" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #271C1A; padding: 40px; text-align: center;">
                            <h1 style="color: #F9DB5E; margin: 0; font-family: 'tenez', Georgia, serif; font-size: 32px; font-weight: 400;">
                                <?php esc_html_e("You're Enrolled!", 'fields-bright-enrollment'); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333;">
                                <?php 
                                printf(
                                    /* translators: %s: Customer name */
                                    esc_html__('Dear %s,', 'fields-bright-enrollment'),
                                    esc_html($customer_name ?: __('Valued Customer', 'fields-bright-enrollment'))
                                );
                                ?>
                            </p>
                            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #333;">
                                <?php esc_html_e('Thank you for enrolling! Your spot has been confirmed and we\'re excited to have you join us.', 'fields-bright-enrollment'); ?>
                            </p>

                            <!-- Enrollment Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #FDFDFC; border: 1px solid #eee; border-radius: 8px; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h2 style="margin: 0 0 20px; color: #271C1A; font-family: 'tenez', Georgia, serif; font-size: 22px; font-weight: 400;">
                                            <?php esc_html_e('Enrollment Details', 'fields-bright-enrollment'); ?>
                                        </h2>
                                        
                                        <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; width: 40%;">
                                                    <strong style="color: #666;"><?php esc_html_e('Confirmation #', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #271C1A; font-weight: 600;">
                                                    <?php echo esc_html($confirmation_number); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                                    <strong style="color: #666;"><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #271C1A;">
                                                    <?php echo esc_html($workshop_title); ?>
                                                </td>
                                            </tr>
                                            <?php if ($schedule_display) : ?>
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                                    <strong style="color: #666;"><?php esc_html_e('Schedule', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #271C1A;">
                                                    <?php echo esc_html($schedule_display); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($event_location) : ?>
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                                    <strong style="color: #666;"><?php esc_html_e('Location', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #271C1A;">
                                                    <?php echo esc_html($event_location); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding: 10px 0;">
                                                    <strong style="color: #666;"><?php esc_html_e('Amount Paid', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; color: #271C1A; font-weight: 600; font-size: 18px;">
                                                    $<?php echo esc_html(number_format((float) $amount, 2)); ?> <?php echo esc_html(strtoupper($currency)); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- What's Next -->
                            <h3 style="margin: 0 0 15px; color: #271C1A; font-family: 'tenez', Georgia, serif; font-size: 20px; font-weight: 400;">
                                <?php esc_html_e("What's Next?", 'fields-bright-enrollment'); ?>
                            </h3>
                            <ul style="margin: 0 0 30px; padding-left: 20px; color: #333; line-height: 1.8;">
                                <li><?php esc_html_e('Save this email for your records', 'fields-bright-enrollment'); ?></li>
                                <li><?php esc_html_e("You'll receive workshop materials and instructions before we begin", 'fields-bright-enrollment'); ?></li>
                                <li><?php esc_html_e("Mark your calendar and we'll see you there!", 'fields-bright-enrollment'); ?></li>
                            </ul>

                            <!-- CTA Button -->
                            <?php if ($workshop_url) : ?>
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background-color: #F9DB5E; border-radius: 4px;">
                                        <a href="<?php echo esc_url($workshop_url); ?>" style="display: inline-block; padding: 14px 28px; color: #271C1A; text-decoration: none; font-weight: 600; font-size: 16px;">
                                            <?php esc_html_e('View Workshop Details', 'fields-bright-enrollment'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <p style="margin: 30px 0 0; font-size: 14px; color: #666; line-height: 1.6;">
                                <?php 
                                printf(
                                    /* translators: %s: Admin email */
                                    esc_html__('Questions? Reply to this email or contact us at %s', 'fields-bright-enrollment'),
                                    esc_html($admin_email)
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #271C1A; padding: 25px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 14px;">
                                <?php echo esc_html($site_name); ?>
                            </p>
                            <p style="margin: 5px 0 0; color: #999; font-size: 12px;">
                                <?php echo esc_html($site_url); ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

