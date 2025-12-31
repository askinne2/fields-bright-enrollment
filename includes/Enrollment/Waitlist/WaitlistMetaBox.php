<?php
/**
 * Waitlist Meta Box
 *
 * Provides admin interface for managing waitlist entry details.
 *
 * @package FieldsBright\Enrollment\Waitlist
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Waitlist;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WaitlistMetaBox
 *
 * Handles the waitlist details meta box on waitlist entry edit screen.
 *
 * @since 1.2.0
 */
class WaitlistMetaBox
{
    /**
     * Meta box ID.
     *
     * @var string
     */
    private const META_BOX_ID = 'fields_bright_waitlist_details';

    /**
     * WaitlistCPT instance.
     *
     * @var WaitlistCPT
     */
    private WaitlistCPT $waitlist_cpt;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->waitlist_cpt = new WaitlistCPT();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_' . WaitlistCPT::POST_TYPE, [$this, 'save_meta_box'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add the waitlist details meta box.
     *
     * @return void
     */
    public function add_meta_box(): void
    {
        add_meta_box(
            self::META_BOX_ID,
            __('Waitlist Entry Details', 'fields-bright-enrollment'),
            [$this, 'render_meta_box'],
            WaitlistCPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Enqueue scripts for the waitlist meta box.
     *
     * @param string $hook_suffix Current admin page hook.
     *
     * @return void
     */
    public function enqueue_scripts(string $hook_suffix): void
    {
        global $post;

        if ($hook_suffix !== 'post.php' || ! $post || $post->post_type !== WaitlistCPT::POST_TYPE) {
            return;
        }

        wp_enqueue_style(
            'fields-bright-waitlist-meta',
            get_stylesheet_directory_uri() . '/assets/css/waitlist-meta.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'fields-bright-waitlist-meta',
            get_stylesheet_directory_uri() . '/assets/js/waitlist-meta.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    /**
     * Render the waitlist details meta box.
     *
     * @param \WP_Post $post Current post object.
     *
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void
    {
        // Add nonce for security.
        wp_nonce_field('fields_bright_waitlist_meta', 'fields_bright_waitlist_meta_nonce');

        // Get all metadata.
        $workshop_id = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'workshop_id', true);
        $customer_name = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'customer_name', true);
        $customer_email = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'customer_email', true);
        $customer_phone = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'customer_phone', true);
        $position = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'position', true);
        $status = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'status', true) ?: 'waiting';
        $date_added = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'date_added', true);
        $notified = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'notified', true);
        $notified_at = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'notified_at', true);
        $enrollment_id = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'enrollment_id', true);
        $notes = get_post_meta($post->ID, WaitlistCPT::META_PREFIX . 'notes', true);

        $workshop = $workshop_id ? get_post($workshop_id) : null;
        $workshop_title = $workshop ? $workshop->post_title : __('Unknown Workshop', 'fields-bright-enrollment');
        ?>
        <div class="fb-waitlist-meta">
            
            <!-- Status Section -->
            <div class="fb-meta-section fb-status-section">
                <h3><?php esc_html_e('Status & Position', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-meta-grid">
                    <div class="fb-meta-field">
                        <label for="waitlist_status">
                            <strong><?php esc_html_e('Status', 'fields-bright-enrollment'); ?></strong>
                        </label>
                        <select name="waitlist_status" id="waitlist_status" class="widefat">
                            <?php foreach (WaitlistCPT::STATUSES as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Change status: waiting → notified → converted/expired', 'fields-bright-enrollment'); ?>
                        </p>
                    </div>

                    <div class="fb-meta-field">
                        <label><strong><?php esc_html_e('Queue Position', 'fields-bright-enrollment'); ?></strong></label>
                        <div class="fb-position-display">
                            <span class="fb-position-badge">#<?php echo esc_html($position ?: '—'); ?></span>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Position is automatically managed by the system.', 'fields-bright-enrollment'); ?>
                        </p>
                    </div>

                    <div class="fb-meta-field">
                        <label><strong><?php esc_html_e('Date Added', 'fields-bright-enrollment'); ?></strong></label>
                        <p>
                            <?php 
                            echo $date_added 
                                ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_added)))
                                : '—';
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Workshop Section -->
            <div class="fb-meta-section fb-workshop-section">
                <h3><?php esc_html_e('Workshop Information', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-meta-field">
                    <label><strong><?php esc_html_e('Workshop', 'fields-bright-enrollment'); ?></strong></label>
                    <p>
                        <?php if ($workshop) : ?>
                            <a href="<?php echo esc_url(get_edit_post_link($workshop_id)); ?>" target="_blank">
                                <?php echo esc_html($workshop_title); ?>
                            </a>
                            <span class="dashicons dashicons-external" style="text-decoration: none;"></span>
                        <?php else : ?>
                            <?php esc_html_e('Unknown Workshop', 'fields-bright-enrollment'); ?>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($workshop_id) : ?>
                        <p class="description">
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=waitlist_entry&workshop=' . $workshop_id)); ?>">
                                <?php esc_html_e('View all waitlist entries for this workshop', 'fields-bright-enrollment'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Information Section -->
            <div class="fb-meta-section fb-customer-section">
                <h3><?php esc_html_e('Customer Information', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-meta-grid">
                    <div class="fb-meta-field">
                        <label for="waitlist_customer_name">
                            <strong><?php esc_html_e('Name', 'fields-bright-enrollment'); ?></strong>
                        </label>
                        <input type="text" 
                               name="waitlist_customer_name" 
                               id="waitlist_customer_name" 
                               value="<?php echo esc_attr($customer_name); ?>" 
                               class="widefat">
                    </div>

                    <div class="fb-meta-field">
                        <label for="waitlist_customer_email">
                            <strong><?php esc_html_e('Email', 'fields-bright-enrollment'); ?></strong>
                        </label>
                        <input type="email" 
                               name="waitlist_customer_email" 
                               id="waitlist_customer_email" 
                               value="<?php echo esc_attr($customer_email); ?>" 
                               class="widefat">
                    </div>

                    <div class="fb-meta-field">
                        <label for="waitlist_customer_phone">
                            <strong><?php esc_html_e('Phone', 'fields-bright-enrollment'); ?></strong>
                        </label>
                        <input type="text" 
                               name="waitlist_customer_phone" 
                               id="waitlist_customer_phone" 
                               value="<?php echo esc_attr($customer_phone); ?>" 
                               class="widefat">
                    </div>
                </div>
            </div>

            <!-- Notification History Section -->
            <div class="fb-meta-section fb-notification-section">
                <h3><?php esc_html_e('Notification History', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-notification-status">
                    <?php if ($notified && $notified_at) : ?>
                        <div class="fb-notified-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php esc_html_e('Notified', 'fields-bright-enrollment'); ?></strong>
                        </div>
                        <p>
                            <?php 
                            printf(
                                /* translators: %s: Notification date/time */
                                esc_html__('Notified on: %s', 'fields-bright-enrollment'),
                                '<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($notified_at))) . '</strong>'
                            );
                            ?>
                        </p>
                        
                        <!-- Reset Notification Button -->
                        <div class="fb-action-buttons">
                            <label>
                                <input type="checkbox" name="waitlist_reset_notification" value="1">
                                <?php esc_html_e('Reset notification status (allows resending)', 'fields-bright-enrollment'); ?>
                            </label>
                        </div>
                    <?php else : ?>
                        <div class="fb-not-notified-badge">
                            <span class="dashicons dashicons-clock"></span>
                            <strong><?php esc_html_e('Not Yet Notified', 'fields-bright-enrollment'); ?></strong>
                        </div>
                        <p class="description">
                            <?php esc_html_e('This person will be notified when a spot becomes available.', 'fields-bright-enrollment'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Conversion/Enrollment Section -->
            <?php if ($enrollment_id) : ?>
            <div class="fb-meta-section fb-conversion-section">
                <h3><?php esc_html_e('Conversion Details', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-conversion-info">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p>
                        <?php 
                        printf(
                            /* translators: %s: Enrollment edit link */
                            esc_html__('Converted to enrollment: %s', 'fields-bright-enrollment'),
                            '<a href="' . esc_url(get_edit_post_link($enrollment_id)) . '" target="_blank">' . esc_html(sprintf(__('Enrollment #%d', 'fields-bright-enrollment'), $enrollment_id)) . '</a>'
                        );
                        ?>
                        <span class="dashicons dashicons-external" style="text-decoration: none;"></span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Notes Section -->
            <div class="fb-meta-section fb-notes-section">
                <h3><?php esc_html_e('Admin Notes', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-meta-field">
                    <label for="waitlist_notes">
                        <strong><?php esc_html_e('Internal Notes', 'fields-bright-enrollment'); ?></strong>
                    </label>
                    <textarea name="waitlist_notes" 
                              id="waitlist_notes" 
                              rows="5" 
                              class="widefat"><?php echo esc_textarea($notes); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Add notes about this waitlist entry (not visible to customer).', 'fields-bright-enrollment'); ?>
                    </p>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="fb-meta-section fb-actions-section">
                <h3><?php esc_html_e('Quick Actions', 'fields-bright-enrollment'); ?></h3>
                
                <div class="fb-action-buttons">
                    <?php if ($status === 'waiting' || ($status === 'notified' && $notified)) : ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=notify_waitlist&entry_id=' . $post->ID . '&workshop_id=' . $workshop_id), 'notify_waitlist_' . $post->ID)); ?>" 
                           class="button button-secondary">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e('Send Notification Email', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($workshop_id) : ?>
                        <a href="<?php echo esc_url(get_permalink($workshop_id)); ?>" 
                           class="button button-secondary" 
                           target="_blank">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('View Workshop Page', 'fields-bright-enrollment'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <style>
            .fb-waitlist-meta {
                max-width: 1200px;
            }
            .fb-meta-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .fb-meta-section h3 {
                margin-top: 0;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                color: #271C1A;
            }
            .fb-meta-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
            }
            .fb-meta-field {
                margin-bottom: 15px;
            }
            .fb-meta-field:last-child {
                margin-bottom: 0;
            }
            .fb-meta-field label {
                display: block;
                margin-bottom: 5px;
            }
            .fb-meta-field input[type="text"],
            .fb-meta-field input[type="email"],
            .fb-meta-field select,
            .fb-meta-field textarea {
                margin-top: 5px;
            }
            .fb-position-display {
                margin: 10px 0;
            }
            .fb-position-badge {
                display: inline-block;
                background: #F9DB5E;
                color: #271C1A;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 18px;
                font-weight: 600;
            }
            .fb-notified-badge,
            .fb-not-notified-badge {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .fb-notified-badge {
                background: #d1f0d1;
                color: #1e7e34;
            }
            .fb-not-notified-badge {
                background: #fff3cd;
                color: #856404;
            }
            .fb-notified-badge .dashicons,
            .fb-not-notified-badge .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .fb-conversion-info {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 15px;
                background: #d1f0d1;
                border-radius: 4px;
            }
            .fb-conversion-info .dashicons {
                color: #1e7e34;
                font-size: 24px;
                width: 24px;
                height: 24px;
            }
            .fb-action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 10px;
            }
            .fb-action-buttons .button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            .fb-action-buttons .button .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
        </style>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     *
     * @return void
     */
    public function save_meta_box(int $post_id, \WP_Post $post): void
    {
        // Verify nonce (use wp_unslash for proper sanitization).
        $nonce = isset($_POST['fields_bright_waitlist_meta_nonce']) 
            ? sanitize_text_field(wp_unslash($_POST['fields_bright_waitlist_meta_nonce'])) 
            : '';
        if (! wp_verify_nonce($nonce, 'fields_bright_waitlist_meta')) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions.
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save status.
        if (isset($_POST['waitlist_status'])) {
            $status = sanitize_text_field(wp_unslash($_POST['waitlist_status']));
            if (array_key_exists($status, WaitlistCPT::STATUSES)) {
                update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'status', $status);
            }
        }

        // Save customer information.
        if (isset($_POST['waitlist_customer_name'])) {
            update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'customer_name', sanitize_text_field(wp_unslash($_POST['waitlist_customer_name'])));
        }
        if (isset($_POST['waitlist_customer_email'])) {
            update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'customer_email', sanitize_email(wp_unslash($_POST['waitlist_customer_email'])));
        }
        if (isset($_POST['waitlist_customer_phone'])) {
            update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'customer_phone', sanitize_text_field(wp_unslash($_POST['waitlist_customer_phone'])));
        }

        // Save notes.
        if (isset($_POST['waitlist_notes'])) {
            update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'notes', sanitize_textarea_field(wp_unslash($_POST['waitlist_notes'])));
        }

        // Reset notification status if requested.
        if (isset($_POST['waitlist_reset_notification']) && $_POST['waitlist_reset_notification'] === '1') {
            delete_post_meta($post_id, WaitlistCPT::META_PREFIX . 'notified');
            delete_post_meta($post_id, WaitlistCPT::META_PREFIX . 'notified_at');
            
            // Add note about reset.
            $notes = get_post_meta($post_id, WaitlistCPT::META_PREFIX . 'notes', true);
            $reset_note = sprintf(
                "\n[%s] Notification status reset by admin",
                current_time('Y-m-d H:i:s')
            );
            update_post_meta($post_id, WaitlistCPT::META_PREFIX . 'notes', $notes . $reset_note);
        }
    }
}

