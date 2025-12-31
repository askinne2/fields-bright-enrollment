<?php
/**
 * Email Template: Admin Notification
 *
 * This template is sent to the admin when a new enrollment is received.
 * Variables available: All keys from EmailHandler::get_enrollment_data()
 *
 * @package FieldsBright\Enrollment\Email
 * @since   1.1.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Format date display.
$date_display = $date ? date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($date)) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('New Enrollment', 'fields-bright-enrollment'); ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" width="600" align="center" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0073aa; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">
                                <?php esc_html_e('New Enrollment Received', 'fields-bright-enrollment'); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333;">
                                <?php esc_html_e('A new enrollment has been received for one of your workshops.', 'fields-bright-enrollment'); ?>
                            </p>

                            <!-- Enrollment Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #f9f9f9; border: 1px solid #eee; border-radius: 8px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h2 style="margin: 0 0 15px; color: #23282d; font-size: 18px; font-weight: 600;">
                                            <?php esc_html_e('Enrollment Details', 'fields-bright-enrollment'); ?>
                                        </h2>
                                        
                                        <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; width: 35%;">
                                                    <strong style="color: #666;"><?php esc_html_e('Confirmation #', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #23282d; font-weight: 600;">
                                                    <?php echo esc_html($confirmation_number); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Customer', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #23282d;">
                                                    <?php echo esc_html($customer_name ?: __('Not provided', 'fields-bright-enrollment')); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Email', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <a href="mailto:<?php echo esc_attr($customer_email); ?>" style="color: #0073aa; text-decoration: none;">
                                                        <?php echo esc_html($customer_email); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php if ($customer_phone) : ?>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Phone', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #23282d;">
                                                    <?php echo esc_html($customer_phone); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #23282d;">
                                                    <?php echo esc_html($workshop_title); ?>
                                                </td>
                                            </tr>
                                            <?php if ($pricing_option) : ?>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Pricing Option', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #23282d;">
                                                    <?php echo esc_html($pricing_option); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                                    <strong style="color: #666;"><?php esc_html_e('Amount', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #ddd; color: #46b450; font-weight: 600; font-size: 16px;">
                                                    $<?php echo esc_html(number_format((float) $amount, 2)); ?> <?php echo esc_html(strtoupper($currency)); ?>
                                                </td>
                                            </tr>
                                            <?php if ($date_display) : ?>
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <strong style="color: #666;"><?php esc_html_e('Date', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #23282d;">
                                                    <?php echo esc_html($date_display); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background-color: #0073aa; border-radius: 4px;">
                                        <a href="<?php echo esc_url($edit_url); ?>" style="display: inline-block; padding: 12px 24px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px;">
                                            <?php esc_html_e('View Enrollment in Admin', 'fields-bright-enrollment'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #23282d; padding: 20px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 14px;">
                                <?php echo esc_html($site_name); ?> - <?php esc_html_e('Enrollment System', 'fields-bright-enrollment'); ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

