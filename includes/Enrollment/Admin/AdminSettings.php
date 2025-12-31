<?php
/**
 * Admin Settings
 *
 * Provides the admin settings page for configuring Stripe API keys,
 * webhook settings, and other enrollment options.
 *
 * @package FieldsBright\Enrollment\Admin
 * @since   1.0.0
 */

namespace FieldsBright\Enrollment\Admin;

use FieldsBright\Enrollment\EnrollmentSystem;
use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminSettings
 *
 * Handles the admin settings page for the enrollment system.
 *
 * @since 1.0.0
 */
class AdminSettings
{
    /**
     * Settings page slug.
     *
     * @var string
     */
    private const PAGE_SLUG = 'fields-bright-enrollment';

    /**
     * Settings group name.
     *
     * @var string
     */
    private const SETTINGS_GROUP = 'fields_bright_enrollment_settings';

    /**
     * Email templates settings group name.
     *
     * @var string
     */
    private const EMAIL_SETTINGS_GROUP = 'fields_bright_email_templates';

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * Registers hooks for the settings page.
     * Note: Menu registration is handled by AdminMenu for proper ordering.
     */
    public function __construct()
    {
        $this->logger = Logger::instance();
        
        // Menu registration is handled by AdminMenu class.
        // Only register settings if not already registered.
        if (! has_action('admin_menu', [$this, 'add_settings_page'])) {
            add_action('admin_menu', [$this, 'add_settings_page'], 20);
        }
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_preview_email_template', [$this, 'ajax_preview_email_template']);
        add_action('wp_ajax_send_test_email', [$this, 'ajax_send_test_email']);
    }

    /**
     * Add the settings page to the admin menu.
     *
     * This method is kept for backward compatibility but will be skipped
     * if AdminMenu has already registered it.
     *
     * @return void
     */
    public function add_settings_page(): void
    {
        // Skip if menu already exists (registered by AdminMenu).
        if ($this->menu_exists(self::PAGE_SLUG . '-settings')) {
            return;
        }

        add_submenu_page(
            'fields-bright-enrollment', // Parent menu slug
            __('Enrollment Settings', 'fields-bright-enrollment'),
            __('Settings', 'fields-bright-enrollment'),
            'manage_options',
            self::PAGE_SLUG . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Check if a menu page already exists.
     *
     * @param string $menu_slug Menu slug to check.
     *
     * @return bool
     */
    private function menu_exists(string $menu_slug): bool
    {
        global $submenu;
        $parent_slug = AdminMenu::MENU_SLUG;
        
        if (! isset($submenu[$parent_slug])) {
            return false;
        }

        foreach ($submenu[$parent_slug] as $item) {
            if ($item[2] === $menu_slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings(): void
    {
        // Register settings
        $settings = [
            'stripe_test_mode',
            'stripe_test_secret_key',
            'stripe_test_publishable_key',
            'stripe_live_secret_key',
            'stripe_live_publishable_key',
            'stripe_webhook_secret',
            'enrollment_success_page',
            'enrollment_cancel_page',
        ];

        foreach ($settings as $setting) {
            register_setting(
                self::SETTINGS_GROUP,
                EnrollmentSystem::OPTION_PREFIX . $setting,
                [
                    'sanitize_callback' => [$this, 'sanitize_setting'],
                ]
            );
        }

        // Register email template settings in separate group
        $email_templates = ['enrollment_confirmation', 'admin_notification', 'refund_confirmation', 'waitlist_confirmation', 'spot_available'];
        foreach ($email_templates as $template) {
            register_setting(
                self::EMAIL_SETTINGS_GROUP,
                EnrollmentSystem::OPTION_PREFIX . 'email_' . $template . '_subject',
                [
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            );
            register_setting(
                self::EMAIL_SETTINGS_GROUP,
                EnrollmentSystem::OPTION_PREFIX . 'email_' . $template . '_body',
                [
                    'sanitize_callback' => 'wp_kses_post',
                ]
            );
        }

        // Stripe Settings Section
        add_settings_section(
            'stripe_settings',
            __('Stripe Configuration', 'fields-bright-enrollment'),
            [$this, 'render_stripe_section'],
            self::PAGE_SLUG
        );

        // Test Mode Toggle
        add_settings_field(
            'stripe_test_mode',
            __('Test Mode', 'fields-bright-enrollment'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'stripe_settings',
            [
                'id'          => 'stripe_test_mode',
                'description' => __('Enable test mode to use Stripe test API keys.', 'fields-bright-enrollment'),
            ]
        );

        // Test API Keys
        add_settings_field(
            'stripe_test_keys',
            __('Test API Keys', 'fields-bright-enrollment'),
            [$this, 'render_api_keys_field'],
            self::PAGE_SLUG,
            'stripe_settings',
            [
                'mode'        => 'test',
                'description' => __('Get your test API keys from your Stripe Dashboard.', 'fields-bright-enrollment'),
            ]
        );

        // Live API Keys
        add_settings_field(
            'stripe_live_keys',
            __('Live API Keys', 'fields-bright-enrollment'),
            [$this, 'render_api_keys_field'],
            self::PAGE_SLUG,
            'stripe_settings',
            [
                'mode'        => 'live',
                'description' => __('Get your live API keys from your Stripe Dashboard.', 'fields-bright-enrollment'),
            ]
        );

        // Webhook Secret
        add_settings_field(
            'stripe_webhook_secret',
            __('Webhook Secret', 'fields-bright-enrollment'),
            [$this, 'render_text_field'],
            self::PAGE_SLUG,
            'stripe_settings',
            [
                'id'          => 'stripe_webhook_secret',
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __('Enter your webhook signing secret. Webhook URL: %s', 'fields-bright-enrollment'),
                    '<code>' . rest_url('fields-bright/v1/stripe/webhook') . '</code>'
                ),
            ]
        );

        // Page Settings Section
        add_settings_section(
            'page_settings',
            __('Page Settings', 'fields-bright-enrollment'),
            [$this, 'render_page_section'],
            self::PAGE_SLUG
        );

        // Success Page
        add_settings_field(
            'enrollment_success_page',
            __('Success Page', 'fields-bright-enrollment'),
            [$this, 'render_page_dropdown'],
            self::PAGE_SLUG,
            'page_settings',
            [
                'id'          => 'enrollment_success_page',
                'description' => __('Page to redirect to after successful enrollment.', 'fields-bright-enrollment'),
            ]
        );

        // Cancel Page
        add_settings_field(
            'enrollment_cancel_page',
            __('Cancel Page', 'fields-bright-enrollment'),
            [$this, 'render_page_dropdown'],
            self::PAGE_SLUG,
            'page_settings',
            [
                'id'          => 'enrollment_cancel_page',
                'description' => __('Page to redirect to when payment is cancelled.', 'fields-bright-enrollment'),
            ]
        );
    }

    /**
     * Sanitize a setting value.
     *
     * @param mixed $value Setting value.
     *
     * @return mixed Sanitized value.
     */
    public function sanitize_setting($value)
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        return $value;
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                self::SETTINGS_GROUP . '_messages',
                'settings_saved',
                __('Settings saved.', 'fields-bright-enrollment'),
                'success'
            );
        }

        $test_mode = EnrollmentSystem::get_option('stripe_test_mode', true);
        ?>
        <div class="wrap fields-bright-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(self::SETTINGS_GROUP . '_messages'); ?>

            <?php $this->render_stripe_tab($test_mode); ?>

        </div>
        <?php
        $this->render_styles();
    }

    /**
     * Render the email templates page.
     *
     * @return void
     */
    public function render_email_templates_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                self::EMAIL_SETTINGS_GROUP . '_messages',
                'email_settings_saved',
                __('Email template saved.', 'fields-bright-enrollment'),
                'success'
            );
        }
        ?>
        <div class="wrap fields-bright-settings-wrap">
            <h1><?php esc_html_e('Email Templates', 'fields-bright-enrollment'); ?></h1>

            <?php settings_errors(self::EMAIL_SETTINGS_GROUP . '_messages'); ?>

            <?php $this->render_email_templates_tab(); ?>

        </div>
        <?php
        $this->render_styles();
        $this->render_email_scripts();
    }

    /**
     * Render the Stripe settings tab.
     *
     * @param bool $test_mode Whether test mode is enabled.
     *
     * @return void
     */
    private function render_stripe_tab(bool $test_mode): void
    {
        ?>
        <!-- Two columns: status and setup guide -->
        <div class="enrollment-top-columns">
            
            <div class="enrollment-settings-help column-left">
                <h3><?php esc_html_e('Setup Guide', 'fields-bright-enrollment'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Get your API keys from the Stripe Dashboard.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Enter your test keys and enable test mode to test payments.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Create a webhook endpoint in Stripe pointing to the webhook URL above.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Copy the webhook signing secret and paste it in the Webhook Secret field.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Create success and cancel pages and select them in Page Settings.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('Enable online enrollment on individual workshop posts.', 'fields-bright-enrollment'); ?></li>
                    <li><?php esc_html_e('When ready for production, add your live keys and disable test mode.', 'fields-bright-enrollment'); ?></li>
                </ol>

                <h4><?php esc_html_e('Required Webhook Events', 'fields-bright-enrollment'); ?></h4>
                <ul>
                    <li><code>checkout.session.completed</code></li>
                    <li><code>charge.refunded</code></li>
                </ul>
            </div>
            <div class="enrollment-settings-status column-right">
                <h3><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Mode', 'fields-bright-enrollment'); ?></th>
                        <td>
                            <?php if ($test_mode) : ?>
                                <span class="status-badge status-test"><?php esc_html_e('Test Mode', 'fields-bright-enrollment'); ?></span>
                            <?php else : ?>
                                <span class="status-badge status-live"><?php esc_html_e('Live Mode', 'fields-bright-enrollment'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Stripe API', 'fields-bright-enrollment'); ?></th>
                        <td>
                            <?php
                            $stripe = EnrollmentSystem::instance()->get_stripe_handler();
                            if ($stripe && $stripe->is_configured()) :
                            ?>
                                <span class="status-badge status-ok"><?php esc_html_e('Configured', 'fields-bright-enrollment'); ?></span>
                            <?php else : ?>
                                <span class="status-badge status-error"><?php esc_html_e('Not Configured', 'fields-bright-enrollment'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Webhook URL', 'fields-bright-enrollment'); ?></th>
                        <td>
                            <code><?php echo esc_html(rest_url('fields-bright/v1/stripe/webhook')); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_attr(rest_url('fields-bright/v1/stripe/webhook')); ?>')">
                                <?php esc_html_e('Copy', 'fields-bright-enrollment'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Settings/configuration section as one column beneath -->
        <div class="enrollment-settings-bottom">
            <form action="options.php" method="post">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Email Templates tab.
     *
     * @return void
     */
    private function render_email_templates_tab(): void
    {
        $templates = $this->get_email_templates();
        $selected_template = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'enrollment_confirmation';
        
        if (! isset($templates[$selected_template])) {
            $selected_template = 'enrollment_confirmation';
        }

        $subject_key = 'email_' . $selected_template . '_subject';
        $body_key = 'email_' . $selected_template . '_body';
        
        // Get saved custom content from database
        $subject = get_option(EnrollmentSystem::OPTION_PREFIX . $subject_key);
        $body = get_option(EnrollmentSystem::OPTION_PREFIX . $body_key);
        
        $this->logger->debug("Loading email template editor", [
            'template' => $selected_template,
            'subject_key' => EnrollmentSystem::OPTION_PREFIX . $subject_key,
            'subject_value' => $subject === false ? '[FALSE]' : ($subject === '' ? '[EMPTY STRING]' : substr($subject, 0, 50)),
            'body_key' => EnrollmentSystem::OPTION_PREFIX . $body_key,
            'body_length' => $body === false ? '[FALSE]' : strlen($body),
        ]);
        
        // Pre-fill with defaults if option doesn't exist (false) OR is empty string
        // This handles both: never saved, and saved-but-empty cases
        if ($subject === false || $subject === '') {
            $this->logger->debug("Loading default subject for template: {$selected_template}");
            $subject = $templates[$selected_template]['default_subject'];
        }
        
        if ($body === false || $body === '') {
            $this->logger->debug("Loading default body template for: {$selected_template}");
            $body = $this->get_default_template_body($selected_template);
            $this->logger->debug("Default body loaded", ['length' => strlen($body)]);
        }
        
        $placeholders = $this->get_available_placeholders($selected_template);
        ?>
        <div class="email-templates-container">
            <form action="options.php" method="post" id="email-template-form">
                <?php settings_fields(self::EMAIL_SETTINGS_GROUP); ?>
                
                <!-- Template Selector -->
                <div class="template-selector-section">
                    <h3><?php esc_html_e('Select Email Template', 'fields-bright-enrollment'); ?></h3>
                    <select id="template-selector" class="regular-text">
                        <?php foreach ($templates as $key => $template) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_template, $key); ?>>
                                <?php echo esc_html($template['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html($templates[$selected_template]['description']); ?></p>
                </div>

                <!-- Subject Line -->
                <div class="email-field-section">
                    <h3><label for="email-subject"><?php esc_html_e('Subject Line', 'fields-bright-enrollment'); ?></label></h3>
                    <input type="text" 
                           id="email-subject" 
                           name="<?php echo esc_attr(EnrollmentSystem::OPTION_PREFIX . $subject_key); ?>" 
                           class="large-text" 
                           value="<?php echo esc_attr($subject); ?>" 
                           placeholder="<?php echo esc_attr($templates[$selected_template]['default_subject']); ?>">
                    <input type="hidden" name="email_template_type" value="<?php echo esc_attr($selected_template); ?>">
                </div>

                <!-- Body Content -->
                <div class="email-field-section">
                    <h3><label for="email-body"><?php esc_html_e('Email Body', 'fields-bright-enrollment'); ?></label></h3>
                    <?php
                    wp_editor($body, 'email_body', [
                        'textarea_name' => EnrollmentSystem::OPTION_PREFIX . $body_key,
                        'textarea_rows' => 15,
                        'teeny'         => false,
                        'media_buttons' => true,
                        'tinymce'       => [
                            'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,blockquote,alignleft,aligncenter,alignright,undo,redo',
                        ],
                    ]);
                    ?>
                    <p class="description"><?php esc_html_e('Use placeholders like {customer_name} which will be replaced with actual data.', 'fields-bright-enrollment'); ?></p>
                </div>

                <!-- Available Placeholders -->
                <div class="placeholders-section">
                    <h3><?php esc_html_e('Available Placeholders', 'fields-bright-enrollment'); ?></h3>
                    <div class="placeholders-grid">
                        <?php foreach ($placeholders as $placeholder => $description) : ?>
                            <div class="placeholder-item">
                                <code class="placeholder-code" data-placeholder="{<?php echo esc_attr($placeholder); ?>}">
                                    {<?php echo esc_html($placeholder); ?>}
                                </code>
                                <span class="placeholder-desc"><?php echo esc_html($description); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php esc_html_e('Click any placeholder to copy it to your clipboard.', 'fields-bright-enrollment'); ?></p>
                </div>

                <!-- Action Buttons -->
                <div class="email-actions-section">
                    <?php submit_button(__('Save Email Template', 'fields-bright-enrollment'), 'primary', 'submit', false); ?>
                    <button type="button" id="preview-email-btn" class="button button-secondary">
                        <?php esc_html_e('Preview Email', 'fields-bright-enrollment'); ?>
                    </button>
                    <button type="button" id="send-test-email-btn" class="button button-secondary">
                        <?php esc_html_e('Send Test Email', 'fields-bright-enrollment'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    <span id="email-feedback" class="email-feedback"></span>
                </div>
            </form>
        </div>

        <!-- Preview Modal -->
        <div id="email-preview-modal" class="email-preview-modal" style="display: none;">
            <div class="email-preview-content">
                <div class="email-preview-header">
                    <h2><?php esc_html_e('Email Preview', 'fields-bright-enrollment'); ?></h2>
                    <button type="button" class="email-preview-close">&times;</button>
                </div>
                <div class="email-preview-meta">
                    <div><strong><?php esc_html_e('Subject:', 'fields-bright-enrollment'); ?></strong> <span id="preview-subject"></span></div>
                </div>
                <div class="email-preview-body" id="preview-body"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Get email templates configuration.
     *
     * @return array Email templates.
     */
    private function get_email_templates(): array
    {
        return [
            'enrollment_confirmation' => [
                'label'           => __('Enrollment Confirmation', 'fields-bright-enrollment'),
                'description'     => __('Sent to customers after successful payment.', 'fields-bright-enrollment'),
                'default_subject' => __('You\'re Enrolled! {workshop_title}', 'fields-bright-enrollment'),
            ],
            'admin_notification' => [
                'label'           => __('Admin Notification', 'fields-bright-enrollment'),
                'description'     => __('Sent to admin when a new enrollment is received.', 'fields-bright-enrollment'),
                'default_subject' => __('New Enrollment: {customer_name} - {workshop_title}', 'fields-bright-enrollment'),
            ],
            'refund_confirmation' => [
                'label'           => __('Refund Confirmation', 'fields-bright-enrollment'),
                'description'     => __('Sent to customers when a refund is processed.', 'fields-bright-enrollment'),
                'default_subject' => __('Refund Processed: {workshop_title}', 'fields-bright-enrollment'),
            ],
            'waitlist_confirmation' => [
                'label'           => __('Waitlist Confirmation', 'fields-bright-enrollment'),
                'description'     => __('Sent when someone joins the waitlist.', 'fields-bright-enrollment'),
                'default_subject' => __('You\'re on the Waitlist: {workshop_title}', 'fields-bright-enrollment'),
            ],
            'spot_available' => [
                'label'           => __('Spot Available', 'fields-bright-enrollment'),
                'description'     => __('Sent when a waitlist spot opens up.', 'fields-bright-enrollment'),
                'default_subject' => __('A Spot Opened Up! {workshop_title}', 'fields-bright-enrollment'),
            ],
        ];
    }

    /**
     * Get default template body content from PHP template files.
     *
     * @param string $template Template key.
     *
     * @return string Default body content with placeholders.
     */
    private function get_default_template_body(string $template): string
    {
        // Map template keys to PHP template file names
        $template_map = [
            'enrollment_confirmation' => 'enrollment-confirmation',
            'admin_notification'      => 'admin-notification',
            'refund_confirmation'     => 'refund-confirmation',
            'waitlist_confirmation'   => 'waitlist-notification',
            'spot_available'          => 'waitlist-notification', // Use same template
        ];

        $template_file = isset($template_map[$template]) ? $template_map[$template] : $template;
        $template_path = get_stylesheet_directory() . '/templates/emails/' . $template_file . '.php';

        $this->logger->debug("Loading default template from PHP file", [
            'template' => $template,
            'file' => $template_file,
            'path' => $template_path,
        ]);

        // Check if template file exists
        if (! file_exists($template_path)) {
            $this->logger->warning("Template file not found, using fallback", ['path' => $template_path]);
            return $this->get_fallback_template_body($template);
        }

        // Get sample data for rendering
        $sample_data = $this->get_sample_email_data($template);

        // Extract variables for template
        extract($sample_data);

        // Start output buffering
        ob_start();
        
        // Suppress warnings during template rendering
        $old_error_level = error_reporting(E_ERROR | E_PARSE);
        
        try {
            // Include the template file
            include $template_path;
        } catch (\Exception $e) {
            $this->logger->error("Error rendering template", ['exception' => $e->getMessage()]);
        }
        
        // Restore error reporting
        error_reporting($old_error_level);
        
        // Get the rendered content
        $rendered_content = ob_get_clean();

        $this->logger->debug("Template rendered", ['content_length' => strlen($rendered_content)]);

        // Convert PHP variables to placeholders in the HTML
        $content_with_placeholders = $this->convert_to_placeholders($rendered_content);

        $this->logger->debug("Converted to placeholders", ['final_length' => strlen($content_with_placeholders)]);

        return $content_with_placeholders;
    }

    /**
     * Convert rendered HTML with sample data to use placeholders.
     *
     * @param string $html Rendered HTML content.
     *
     * @return string HTML with placeholders.
     */
    private function convert_to_placeholders(string $html): string
    {
        // Sample values that should be replaced with placeholders
        $replacements = [
            'Jane Smith'                           => '{customer_name}',
            'Jane'                                 => '{customer_first_name}',
            'jane@example.com'                     => '{customer_email}',
            '(555) 123-4567'                       => '{customer_phone}',
            'Introduction to Permaculture'         => '{workshop_title}',
            'FB-000123'                            => '{confirmation_number}',
            '$125.00'                              => '{amount_paid}',
            'Fields Bright Farm, 123 Farm Road'    => '{workshop_location}',
            '10:00 AM'                             => '{workshop_time}',
            home_url('/claim-spot?token=sample123') => '{claim_link}',
            '48 hours'                             => '{expiry_time}',
        ];

        // Replace sample values with placeholders
        foreach ($replacements as $sample => $placeholder) {
            $html = str_replace($sample, $placeholder, $html);
        }

        // Replace dynamic date with placeholder
        $sample_date = date_i18n(get_option('date_format'), strtotime('+1 month'));
        $html = str_replace($sample_date, '{workshop_date}', $html);

        // Replace site name and URL
        $html = str_replace(get_bloginfo('name'), '{site_name}', $html);
        $html = str_replace(home_url(), '{site_url}', $html);
        $html = str_replace(get_option('admin_email'), '{admin_email}', $html);

        return $html;
    }

    /**
     * Get fallback template body if PHP template doesn't exist.
     *
     * @param string $template Template key.
     *
     * @return string Fallback template body.
     */
    private function get_fallback_template_body(string $template): string
    {
        $defaults = [
            'waitlist_confirmation' => '<p>Dear {customer_name},</p>

<p>You\'ve been added to the waitlist for <strong>{workshop_title}</strong>.</p>

<p>We\'ll notify you immediately if a spot becomes available. You\'ll have 48 hours to claim your spot once notified.</p>

<p>Thank you for your interest!</p>',

            'spot_available' => '<p>Dear {customer_name},</p>

<p><strong>Great news!</strong> A spot has opened up for <strong>{workshop_title}</strong>!</p>

<p><strong>⏰ Act quickly!</strong> This offer is valid for {expiry_time}.</p>

<p><a href="{claim_link}">Claim Your Spot Now</a></p>',
        ];

        return isset($defaults[$template]) ? $defaults[$template] : '';
    }

    /**
     * Get available placeholders for a template.
     *
     * @param string $template Template key.
     *
     * @return array Placeholders and their descriptions.
     */
    private function get_available_placeholders(string $template): array
    {
        $common_placeholders = [
            'customer_name'       => __('Customer\'s full name', 'fields-bright-enrollment'),
            'customer_first_name' => __('Customer\'s first name', 'fields-bright-enrollment'),
            'customer_email'      => __('Customer\'s email address', 'fields-bright-enrollment'),
            'workshop_title'      => __('Workshop name', 'fields-bright-enrollment'),
            'workshop_date'       => __('Workshop date', 'fields-bright-enrollment'),
            'workshop_time'       => __('Workshop start time', 'fields-bright-enrollment'),
            'workshop_location'   => __('Workshop location', 'fields-bright-enrollment'),
            'site_name'           => __('Website name', 'fields-bright-enrollment'),
            'admin_email'         => __('Admin email address', 'fields-bright-enrollment'),
        ];

        $payment_placeholders = [
            'amount_paid'         => __('Total amount charged', 'fields-bright-enrollment'),
            'confirmation_number' => __('Enrollment confirmation number', 'fields-bright-enrollment'),
        ];

        switch ($template) {
            case 'enrollment_confirmation':
            case 'refund_confirmation':
                return array_merge($common_placeholders, $payment_placeholders);
            
            case 'admin_notification':
                return array_merge($common_placeholders, $payment_placeholders, [
                    'customer_phone' => __('Customer\'s phone number', 'fields-bright-enrollment'),
                ]);
            
            case 'spot_available':
                return array_merge($common_placeholders, [
                    'claim_link'  => __('Link to claim the spot', 'fields-bright-enrollment'),
                    'expiry_time' => __('How long the offer is valid', 'fields-bright-enrollment'),
                ]);
            
            default:
                return $common_placeholders;
        }
    }

    /**
     * Render custom styles.
     *
     * @return void
     */
    private function render_styles(): void
    {
        ?>
        <style>
            /* Two column layout at the top for status and setup */
            .enrollment-top-columns {
                display: flex;
                flex-direction: row;
                gap: 40px;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0;
            }
            .enrollment-top-columns .column-left {
                flex: 1;
            }
            .enrollment-top-columns .column-right {
                flex: 1;
            }
            .enrollment-settings-status,
            .enrollment-settings-help {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px 20px;
                margin: 20px 0;
                box-sizing: border-box;
            }
            .enrollment-settings-status h3,
            .enrollment-settings-help h3 {
                margin-top: 0;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .status-test { background: #fcf9e8; color: #856404; }
            .status-live { background: #e7f6ff; color: #0073aa; }
            .status-ok { background: #d4edda; color: #155724; }
            .status-error { background: #f8d7da; color: #721c24; }
            .enrollment-settings-help ol,
            .enrollment-settings-help ul {
                margin-left: 20px;
            }
            .enrollment-settings-help li {
                margin-bottom: 8px;
            }
            .api-keys-wrapper {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .api-key-row {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .api-key-row label {
                min-width: 120px;
            }
            /* One column for settings form at the bottom */
            .enrollment-settings-bottom {
                margin-top: 15px;
                margin-bottom: 30px;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px 25px 15px 25px;
                box-sizing: border-box;
                max-width: 950px;
            }
            
            /* Email Templates Tab Styles */
            .email-templates-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px 25px;
                margin-top: 20px;
                max-width: 1200px;
            }
            .template-selector-section,
            .email-field-section,
            .placeholders-section,
            .email-actions-section {
                margin-bottom: 25px;
            }
            .email-field-section h3,
            .template-selector-section h3,
            .placeholders-section h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
            }
            #template-selector {
                max-width: 400px;
            }
            .placeholders-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 12px;
                margin: 15px 0;
            }
            .placeholder-item {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .placeholder-code {
                background: #271C1A;
                color: #F9DB5E;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 13px;
                cursor: pointer;
                display: inline-block;
                width: fit-content;
            }
            .placeholder-code:hover {
                background: #3a2a27;
            }
            .placeholder-desc {
                font-size: 12px;
                color: #666;
            }
            .email-actions-section {
                border-top: 1px solid #ddd;
                padding-top: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .email-feedback {
                font-size: 13px;
            }
            .email-feedback.success {
                color: #46b450;
            }
            .email-feedback.error {
                color: #dc3232;
            }
            
            /* Preview Modal */
            .email-preview-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .email-preview-content {
                background: #fff;
                max-width: 800px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }
            .email-preview-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #ddd;
            }
            .email-preview-header h2 {
                margin: 0;
                font-size: 20px;
            }
            .email-preview-close {
                background: none;
                border: none;
                font-size: 32px;
                cursor: pointer;
                color: #666;
                line-height: 1;
                padding: 0;
                width: 32px;
                height: 32px;
            }
            .email-preview-close:hover {
                color: #000;
            }
            .email-preview-meta {
                padding: 15px 20px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
            }
            .email-preview-body {
                padding: 20px;
            }
            
            @media (max-width: 900px) {
                .enrollment-top-columns {
                    flex-direction: column;
                }
                .enrollment-top-columns .column-left,
                .enrollment-top-columns .column-right {
                    width: 100%;
                    margin-right: 0;
                }
                .placeholders-grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 700px) {
                .enrollment-settings-bottom,
                .email-templates-container {
                    padding: 10px 7px 7px 7px;
                }
                .email-actions-section {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        </style>
        <?php
    }

    /**
     * Render JavaScript for email templates tab.
     *
     * @return void
     */
    private function render_email_scripts(): void
    {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Template selector change
            $('#template-selector').on('change', function() {
                const template = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('tab', 'email');
                url.searchParams.set('template', template);
                window.location.href = url.toString();
            });

            // Copy placeholder to clipboard
            $('.placeholder-code').on('click', function() {
                const placeholder = $(this).data('placeholder');
                navigator.clipboard.writeText(placeholder).then(function() {
                    const $feedback = $('<span class="copied-feedback">✓ Copied!</span>');
                    $feedback.css({
                        position: 'absolute',
                        background: '#46b450',
                        color: '#fff',
                        padding: '4px 8px',
                        borderRadius: '3px',
                        fontSize: '12px',
                        marginLeft: '10px'
                    });
                    $feedback.insertAfter(this);
                    setTimeout(function() {
                        $feedback.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 1500);
                }.bind(this));
            });

            // Preview email
            $('#preview-email-btn').on('click', function(e) {
                e.preventDefault();
                
                const subject = $('#email-subject').val();
                const body = tinymce.get('email_body') ? tinymce.get('email_body').getContent() : $('#email_body').val();
                const template = $('#template-selector').val();
                
                $('.spinner').addClass('is-active');
                $('#email-feedback').removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'preview_email_template',
                        nonce: '<?php echo wp_create_nonce('preview_email_template'); ?>',
                        template: template,
                        subject: subject,
                        body: body
                    },
                    success: function(response) {
                        $('.spinner').removeClass('is-active');
                        if (response.success) {
                            $('#preview-subject').text(response.data.subject);
                            $('#preview-body').html(response.data.body);
                            $('#email-preview-modal').fadeIn(200);
                        } else {
                            $('#email-feedback').addClass('error').text(response.data.message || 'Failed to generate preview');
                        }
                    },
                    error: function() {
                        $('.spinner').removeClass('is-active');
                        $('#email-feedback').addClass('error').text('AJAX error occurred');
                    }
                });
            });

            // Send test email
            $('#send-test-email-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php esc_html_e('Send a test email to your admin email address?', 'fields-bright-enrollment'); ?>')) {
                    return;
                }
                
                const subject = $('#email-subject').val();
                const body = tinymce.get('email_body') ? tinymce.get('email_body').getContent() : $('#email_body').val();
                const template = $('#template-selector').val();
                
                $('.spinner').addClass('is-active');
                $('#email-feedback').removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_test_email',
                        nonce: '<?php echo wp_create_nonce('send_test_email'); ?>',
                        template: template,
                        subject: subject,
                        body: body
                    },
                    success: function(response) {
                        $('.spinner').removeClass('is-active');
                        if (response.success) {
                            $('#email-feedback').addClass('success').text('✓ Test email sent successfully!');
                        } else {
                            $('#email-feedback').addClass('error').text(response.data.message || 'Failed to send test email');
                        }
                    },
                    error: function() {
                        $('.spinner').removeClass('is-active');
                        $('#email-feedback').addClass('error').text('AJAX error occurred');
                    }
                });
            });

            // Close preview modal
            $('.email-preview-close, .email-preview-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#email-preview-modal').fadeOut(200);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for email preview.
     *
     * @return void
     */
    public function ajax_preview_email_template(): void
    {
        check_ajax_referer('preview_email_template', 'nonce');
        
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'fields-bright-enrollment')]);
        }

        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';

        // Get sample data
        $sample_data = $this->get_sample_email_data($template);

        // Replace placeholders
        $processed_subject = $this->replace_placeholders($subject, $sample_data);
        $processed_body = $this->replace_placeholders($body, $sample_data);

        // If empty, use defaults
        if (empty($processed_subject)) {
            $templates = $this->get_email_templates();
            $processed_subject = isset($templates[$template]) ? $templates[$template]['default_subject'] : 'Email Preview';
            $processed_subject = $this->replace_placeholders($processed_subject, $sample_data);
        }

        if (empty($processed_body)) {
            $processed_body = '<p>' . __('(No content yet - the default template will be used)', 'fields-bright-enrollment') . '</p>';
        }

        wp_send_json_success([
            'subject' => $processed_subject,
            'body'    => $processed_body,
        ]);
    }

    /**
     * AJAX handler for sending test email.
     *
     * @return void
     */
    public function ajax_send_test_email(): void
    {
        check_ajax_referer('send_test_email', 'nonce');
        
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'fields-bright-enrollment')]);
        }

        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';

        // Get sample data
        $sample_data = $this->get_sample_email_data($template);

        // Replace placeholders
        $processed_subject = $this->replace_placeholders($subject, $sample_data);
        $processed_body = $this->replace_placeholders($body, $sample_data);

        // Use defaults if empty
        if (empty($processed_subject)) {
            $templates = $this->get_email_templates();
            $processed_subject = isset($templates[$template]) ? $templates[$template]['default_subject'] : 'Test Email';
            $processed_subject = $this->replace_placeholders($processed_subject, $sample_data);
        }

        if (empty($processed_body)) {
            $processed_body = '<p>' . __('This is a test email. No custom content has been configured yet.', 'fields-bright-enrollment') . '</p>';
        }

        // Send email
        $admin_email = get_option('admin_email');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $sent = wp_mail($admin_email, '[TEST] ' . $processed_subject, $processed_body, $headers);

        if ($sent) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %s: admin email address */
                    __('Test email sent to %s', 'fields-bright-enrollment'),
                    $admin_email
                ),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send test email. Please check your WordPress email configuration.', 'fields-bright-enrollment'),
            ]);
        }
    }

    /**
     * Get sample data for email preview.
     *
     * @param string $template Template key.
     *
     * @return array Sample data.
     */
    private function get_sample_email_data(string $template): array
    {
        $sample_date = strtotime('+1 month');
        
        return [
            // Customer info
            'customer_name'       => 'Jane Smith',
            'customer_first_name' => 'Jane',
            'customer_email'      => 'jane@example.com',
            'customer_phone'      => '(555) 123-4567',
            
            // Workshop info
            'workshop_title'      => 'Introduction to Permaculture',
            'workshop_id'         => 123,
            'workshop_url'        => home_url('/workshops/introduction-to-permaculture'),
            'workshop_date'       => date_i18n(get_option('date_format'), $sample_date),
            'workshop_time'       => '10:00 AM',
            'event_start'         => date('Y-m-d H:i:s', $sample_date),
            'event_end'           => date('Y-m-d H:i:s', $sample_date + 7200), // 2 hours later
            'event_location'      => 'Fields Bright Farm, 123 Farm Road',
            'recurring_info'      => '',
            
            // Payment info
            'amount'              => 125.00,
            'amount_paid'         => '$125.00',
            'currency'            => 'USD',
            'confirmation_number' => 'FB-000123',
            'enrollment_id'       => 123,
            'pricing_option'      => 'Standard Rate',
            'date'                => date('Y-m-d H:i:s'),
            
            // Site info
            'site_name'           => get_bloginfo('name'),
            'site_url'            => home_url(),
            'admin_email'         => get_option('admin_email'),
            'edit_url'            => admin_url('post.php?post=123&action=edit'),
            
            // Waitlist specific
            'claim_link'          => home_url('/claim-spot?token=sample123'),
            'expiry_time'         => '48 hours',
        ];
    }

    /**
     * Replace placeholders in content.
     *
     * @param string $content Content with placeholders.
     * @param array  $data    Data to replace placeholders with.
     *
     * @return string Content with placeholders replaced.
     */
    private function replace_placeholders(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    /**
     * Render the Stripe settings section description.
     *
     * @return void
     */
    public function render_stripe_section(): void
    {
        echo '<p>' . esc_html__('Configure your Stripe API keys for payment processing.', 'fields-bright-enrollment') . '</p>';
    }

    /**
     * Render the page settings section description.
     *
     * @return void
     */
    public function render_page_section(): void
    {
        echo '<p>' . esc_html__('Configure the pages used for enrollment flow.', 'fields-bright-enrollment') . '</p>';
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     *
     * @return void
     */
    public function render_checkbox_field(array $args): void
    {
        $id = $args['id'];
        $option_name = EnrollmentSystem::OPTION_PREFIX . $id;
        $value = get_option($option_name, false);
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo esc_attr($option_name); ?>" 
                   id="<?php echo esc_attr($id); ?>" 
                   value="1" 
                   <?php checked($value, true); ?>>
            <?php echo isset($args['description']) ? esc_html($args['description']) : ''; ?>
        </label>
        <?php
    }

    /**
     * Render API keys fields.
     *
     * @param array $args Field arguments.
     *
     * @return void
     */
    public function render_api_keys_field(array $args): void
    {
        $mode = $args['mode'];
        $secret_key_name = EnrollmentSystem::OPTION_PREFIX . 'stripe_' . $mode . '_secret_key';
        $publishable_key_name = EnrollmentSystem::OPTION_PREFIX . 'stripe_' . $mode . '_publishable_key';
        
        $secret_key = get_option($secret_key_name, '');
        $publishable_key = get_option($publishable_key_name, '');
        ?>
        <div class="api-keys-wrapper">
            <div class="api-key-row">
                <label for="<?php echo esc_attr($mode); ?>_publishable_key">
                    <?php esc_html_e('Publishable Key', 'fields-bright-enrollment'); ?>
                </label>
                <input type="text" 
                       name="<?php echo esc_attr($publishable_key_name); ?>" 
                       id="<?php echo esc_attr($mode); ?>_publishable_key" 
                       class="regular-text" 
                       value="<?php echo esc_attr($publishable_key); ?>"
                       placeholder="pk_<?php echo esc_attr($mode); ?>_...">
            </div>
            <div class="api-key-row">
                <label for="<?php echo esc_attr($mode); ?>_secret_key">
                    <?php esc_html_e('Secret Key', 'fields-bright-enrollment'); ?>
                </label>
                <input type="password" 
                       name="<?php echo esc_attr($secret_key_name); ?>" 
                       id="<?php echo esc_attr($mode); ?>_secret_key" 
                       class="regular-text" 
                       value="<?php echo esc_attr($secret_key); ?>"
                       placeholder="sk_<?php echo esc_attr($mode); ?>_...">
            </div>
        </div>
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Render a text field.
     *
     * @param array $args Field arguments.
     *
     * @return void
     */
    public function render_text_field(array $args): void
    {
        $id = $args['id'];
        $type = $args['type'] ?? 'text';
        $option_name = EnrollmentSystem::OPTION_PREFIX . $id;
        $value = get_option($option_name, '');
        ?>
        <input type="<?php echo esc_attr($type); ?>" 
               name="<?php echo esc_attr($option_name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               class="regular-text" 
               value="<?php echo esc_attr($value); ?>">
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Render a page dropdown field.
     *
     * @param array $args Field arguments.
     *
     * @return void
     */
    public function render_page_dropdown(array $args): void
    {
        $id = $args['id'];
        $option_name = EnrollmentSystem::OPTION_PREFIX . $id;
        $value = get_option($option_name, 0);

        $pages = get_pages([
            'post_status' => 'publish',
            'sort_order'  => 'asc',
            'sort_column' => 'post_title',
        ]);
        ?>
        <select name="<?php echo esc_attr($option_name); ?>" id="<?php echo esc_attr($id); ?>">
            <option value=""><?php esc_html_e('Select a page...', 'fields-bright-enrollment'); ?></option>
            <?php foreach ($pages as $page) : ?>
                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($value, $page->ID); ?>>
                    <?php echo esc_html($page->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }
}

