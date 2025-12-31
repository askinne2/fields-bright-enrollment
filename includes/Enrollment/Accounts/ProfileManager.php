<?php
/**
 * Profile Manager
 *
 * Handles user profile editing and management.
 *
 * @package FieldsBright\Enrollment\Accounts
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Accounts;

use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class ProfileManager
 *
 * Manages user profile editing functionality.
 *
 * @since 1.2.0
 */
class ProfileManager
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->logger = Logger::instance();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // AJAX handlers.
        add_action('wp_ajax_fields_bright_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_fields_bright_change_password', [$this, 'ajax_change_password']);
        
        // Shortcodes.
        add_shortcode('enrollment_profile_form', [$this, 'render_profile_form']);
    }

    /**
     * Render profile edit form shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render_profile_form(array $atts = []): string
    {
        if (! is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to edit your profile.', 'fields-bright-enrollment') . '</p>';
        }

        $user = wp_get_current_user();
        $user_meta = get_user_meta($user->ID);

        wp_enqueue_script(
            'fields-bright-profile',
            get_stylesheet_directory_uri() . '/assets/js/profile-manager.js',
            ['jquery'],
            '1.2.0',
            true
        );

        wp_localize_script('fields-bright-profile', 'fieldsBrightProfile', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fields_bright_profile'),
        ]);

        ob_start();
        ?>
        <div class="fb-profile-manager">
            <div class="fb-profile-messages" style="display: none;"></div>
            
            <!-- Profile Information Form -->
            <form id="fb-profile-form" class="fb-form">
                <h3><?php esc_html_e('Profile Information', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-form-row">
                    <div class="fb-form-col">
                        <label for="first_name"><?php esc_html_e('First Name', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
                    </div>
                    
                    <div class="fb-form-col">
                        <label for="last_name"><?php esc_html_e('Last Name', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
                    </div>
                </div>
                
                <div class="fb-form-group">
                    <label for="user_email"><?php esc_html_e('Email Address', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                    <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" required>
                    <small class="fb-form-help"><?php esc_html_e('This email will be used for enrollment confirmations.', 'fields-bright-enrollment'); ?></small>
                </div>
                
                <div class="fb-form-group">
                    <label for="phone"><?php esc_html_e('Phone Number', 'fields-bright-enrollment'); ?></label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>">
                </div>
                
                <div class="fb-form-group">
                    <label for="description"><?php esc_html_e('Bio', 'fields-bright-enrollment'); ?></label>
                    <textarea id="description" name="description" rows="4"><?php echo esc_textarea($user->description); ?></textarea>
                </div>
                
                <button type="submit" class="fb-button fb-button-primary">
                    <?php esc_html_e('Update Profile', 'fields-bright-enrollment'); ?>
                </button>
            </form>
            
            <!-- Change Password Form -->
            <form id="fb-password-form" class="fb-form fb-form-password">
                <h3><?php esc_html_e('Change Password', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-form-group">
                    <label for="current_password"><?php esc_html_e('Current Password', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                
                <div class="fb-form-group">
                    <label for="new_password"><?php esc_html_e('New Password', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                    <small class="fb-form-help"><?php esc_html_e('Password should be at least 8 characters long.', 'fields-bright-enrollment'); ?></small>
                </div>
                
                <div class="fb-form-group">
                    <label for="confirm_password"><?php esc_html_e('Confirm New Password', 'fields-bright-enrollment'); ?> <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="fb-button fb-button-secondary">
                    <?php esc_html_e('Change Password', 'fields-bright-enrollment'); ?>
                </button>
            </form>
            
            <!-- Account Actions -->
            <div class="fb-account-actions">
                <h3><?php esc_html_e('Account Actions', 'fields-bright-enrollment'); ?></h3>
                <p>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="fb-link">
                        <?php esc_html_e('Log Out', 'fields-bright-enrollment'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler: Update profile.
     *
     * @return void
     */
    public function ajax_update_profile(): void
    {
        check_ajax_referer('fields_bright_profile', 'nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'fields-bright-enrollment')]);
        }

        $user_id = get_current_user_id();

        $this->logger->start_process('update_profile', ['user_id' => $user_id]);

        // Sanitize and validate inputs (wp_unslash to handle magic quotes).
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        // Validate required fields.
        if (empty($first_name) || empty($last_name) || empty($user_email)) {
            $this->logger->warning('Profile update validation failed', [
                'user_id' => $user_id,
                'reason'  => 'missing_required_fields',
            ]);
            $this->logger->end_process('update_profile', ['result' => 'validation_failed']);
            
            wp_send_json_error([
                'message' => __('Please fill in all required fields.', 'fields-bright-enrollment'),
            ]);
        }

        // Validate email format.
        if (! is_email($user_email)) {
            $this->logger->warning('Invalid email format', ['email' => $user_email]);
            $this->logger->end_process('update_profile', ['result' => 'invalid_email']);
            
            wp_send_json_error([
                'message' => __('Please enter a valid email address.', 'fields-bright-enrollment'),
            ]);
        }

        // Check if email is already in use by another user.
        $existing_user = get_user_by('email', $user_email);
        if ($existing_user && $existing_user->ID !== $user_id) {
            $this->logger->warning('Email already in use', ['email' => $user_email]);
            $this->logger->end_process('update_profile', ['result' => 'email_exists']);
            
            wp_send_json_error([
                'message' => __('This email is already in use by another account.', 'fields-bright-enrollment'),
            ]);
        }

        $this->logger->log_step('update_profile', 'Updating user data');

        // Update user.
        $user_data = [
            'ID'          => $user_id,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'user_email'  => $user_email,
            'description' => $description,
        ];

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            $this->logger->error('Failed to update user', [
                'user_id' => $user_id,
                'error'   => $result->get_error_message(),
            ]);
            $this->logger->end_process('update_profile', ['result' => 'error']);
            
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        // Update phone number.
        if (! empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }

        $this->logger->info('Profile updated successfully', ['user_id' => $user_id]);
        $this->logger->end_process('update_profile', ['result' => 'success']);

        wp_send_json_success([
            'message' => __('Profile updated successfully!', 'fields-bright-enrollment'),
        ]);
    }

    /**
     * AJAX handler: Change password.
     *
     * @return void
     */
    public function ajax_change_password(): void
    {
        check_ajax_referer('fields_bright_profile', 'nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'fields-bright-enrollment')]);
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $this->logger->start_process('change_password', ['user_id' => $user_id]);

        // Get and sanitize inputs.
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Validate required fields.
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $this->logger->warning('Password change validation failed', ['reason' => 'missing_fields']);
            $this->logger->end_process('change_password', ['result' => 'validation_failed']);
            
            wp_send_json_error([
                'message' => __('Please fill in all fields.', 'fields-bright-enrollment'),
            ]);
        }

        // Verify current password.
        if (! wp_check_password($current_password, $user->user_pass, $user_id)) {
            $this->logger->warning('Current password incorrect', ['user_id' => $user_id]);
            $this->logger->end_process('change_password', ['result' => 'incorrect_password']);
            
            wp_send_json_error([
                'message' => __('Current password is incorrect.', 'fields-bright-enrollment'),
            ]);
        }

        // Validate new password length.
        if (strlen($new_password) < 8) {
            $this->logger->warning('New password too short', ['length' => strlen($new_password)]);
            $this->logger->end_process('change_password', ['result' => 'password_too_short']);
            
            wp_send_json_error([
                'message' => __('New password must be at least 8 characters long.', 'fields-bright-enrollment'),
            ]);
        }

        // Validate password confirmation.
        if ($new_password !== $confirm_password) {
            $this->logger->warning('Password confirmation mismatch');
            $this->logger->end_process('change_password', ['result' => 'passwords_dont_match']);
            
            wp_send_json_error([
                'message' => __('New password and confirmation do not match.', 'fields-bright-enrollment'),
            ]);
        }

        $this->logger->log_step('change_password', 'Updating password');

        // Update password.
        wp_set_password($new_password, $user_id);

        // Send notification email.
        $this->send_password_change_notification($user_id);

        $this->logger->info('Password changed successfully', ['user_id' => $user_id]);
        $this->logger->end_process('change_password', ['result' => 'success']);

        wp_send_json_success([
            'message' => __('Password changed successfully!', 'fields-bright-enrollment'),
        ]);
    }

    /**
     * Send password change notification email.
     *
     * @param int $user_id User ID.
     *
     * @return void
     */
    private function send_password_change_notification(int $user_id): void
    {
        $user = get_userdata($user_id);
        
        if (! $user) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Password Changed', 'fields-bright-enrollment'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('Hi %s,', 'fields-bright-enrollment') . "\n\n" .
            __('This is a confirmation that your password was changed.', 'fields-bright-enrollment') . "\n\n" .
            __('If you did not make this change, please contact us immediately.', 'fields-bright-enrollment') . "\n\n" .
            __('Best regards,', 'fields-bright-enrollment') . "\n" .
            get_bloginfo('name'),
            $user->display_name
        );

        wp_mail($user->user_email, $subject, $message);

        $this->logger->info('Password change notification sent', [
            'user_id' => $user_id,
            'email'   => $user->user_email,
        ]);
    }
}

