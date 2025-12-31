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
     * Constructor.
     *
     * Registers hooks for the settings page.
     * Note: Menu registration is handled by AdminMenu for proper ordering.
     */
    public function __construct()
    {
        // Menu registration is handled by AdminMenu class.
        // Only register settings if not already registered.
        if (! has_action('admin_menu', [$this, 'add_settings_page'])) {
            add_action('admin_menu', [$this, 'add_settings_page'], 20);
        }
        add_action('admin_init', [$this, 'register_settings']);
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
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(self::SETTINGS_GROUP . '_messages'); ?>
            
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
        </div>

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
            @media (max-width: 900px) {
                .enrollment-top-columns {
                    flex-direction: column;
                }
                .enrollment-top-columns .column-left,
                .enrollment-top-columns .column-right {
                    width: 100%;
                    margin-right: 0;
                }
            }
            @media (max-width: 700px) {
                .enrollment-settings-bottom {
                    padding: 10px 7px 7px 7px;
                }
            }
        </style>
        <?php
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

