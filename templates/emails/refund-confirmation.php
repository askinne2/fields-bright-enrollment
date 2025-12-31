<?php
/**
 * Email Template: Refund Confirmation
 *
 * This template is sent to customers when a refund is processed.
 * Variables available: All keys from EmailHandler::get_enrollment_data()
 *
 * @package FieldsBright\Enrollment\Email
 * @since   1.1.0
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Refund Confirmation', 'fields-bright-enrollment'); ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Poppins', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" width="600" align="center" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #271C1A; padding: 40px; text-align: center;">
                            <h1 style="color: #F9DB5E; margin: 0; font-family: 'tenez', Georgia, serif; font-size: 28px; font-weight: 400;">
                                <?php esc_html_e('Refund Processed', 'fields-bright-enrollment'); ?>
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
                                <?php esc_html_e('Your refund has been processed. Please allow 5-10 business days for the funds to appear in your account, depending on your bank.', 'fields-bright-enrollment'); ?>
                            </p>

                            <!-- Refund Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background-color: #FDFDFC; border: 1px solid #eee; border-radius: 8px; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h2 style="margin: 0 0 20px; color: #271C1A; font-family: 'tenez', Georgia, serif; font-size: 22px; font-weight: 400;">
                                            <?php esc_html_e('Refund Details', 'fields-bright-enrollment'); ?>
                                        </h2>
                                        
                                        <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; width: 40%;">
                                                    <strong style="color: #666;"><?php esc_html_e('Confirmation #', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #271C1A;">
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
                                            <tr>
                                                <td style="padding: 10px 0;">
                                                    <strong style="color: #666;"><?php esc_html_e('Amount Refunded', 'fields-bright-enrollment'); ?></strong>
                                                </td>
                                                <td style="padding: 10px 0; color: #271C1A; font-weight: 600; font-size: 18px;">
                                                    $<?php echo esc_html(number_format((float) $amount, 2)); ?> <?php echo esc_html(strtoupper($currency)); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333;">
                                <?php 
                                printf(
                                    /* translators: %s: Admin email */
                                    esc_html__('If you have any questions about this refund, please contact us at %s.', 'fields-bright-enrollment'),
                                    '<a href="mailto:' . esc_attr($admin_email) . '" style="color: #0073aa;">' . esc_html($admin_email) . '</a>'
                                );
                                ?>
                            </p>

                            <p style="margin: 30px 0 0; font-size: 16px; line-height: 1.6; color: #333;">
                                <?php esc_html_e('We hope to see you at a future workshop!', 'fields-bright-enrollment'); ?>
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top: 25px;">
                                <tr>
                                    <td style="background-color: #F9DB5E; border-radius: 4px;">
                                        <a href="<?php echo esc_url($site_url); ?>/workshops/" style="display: inline-block; padding: 14px 28px; color: #271C1A; text-decoration: none; font-weight: 600; font-size: 16px;">
                                            <?php esc_html_e('Browse Workshops', 'fields-bright-enrollment'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
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

