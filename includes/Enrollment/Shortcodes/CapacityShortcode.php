<?php
/**
 * Capacity Shortcode
 *
 * Provides a shortcode to display remaining spots for a workshop.
 *
 * @package FieldsBright\Enrollment\Shortcodes
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Shortcodes;

use FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class CapacityShortcode
 *
 * Handles the capacity countdown shortcode.
 *
 * @since 1.1.0
 */
class CapacityShortcode
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_shortcode('enrollment_capacity', [$this, 'render']);
        add_shortcode('enrollment_spots', [$this, 'render']); // Alias
    }

    /**
     * Render the capacity shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string HTML output.
     */
    public function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'workshop_id'     => get_the_ID(),
            'show_icon'       => 'true',
            'show_total'      => 'false',
            'sold_out_text'   => __('Sold Out', 'fields-bright-enrollment'),
            'unlimited_text'  => __('Unlimited spots', 'fields-bright-enrollment'),
            'spots_text'      => __('%d spots remaining', 'fields-bright-enrollment'),
            'spot_text'       => __('%d spot remaining', 'fields-bright-enrollment'),
            'urgency_threshold' => 5,
            'class'           => '',
        ], $atts);

        $workshop_id = absint($atts['workshop_id']);
        $show_icon = filter_var($atts['show_icon'], FILTER_VALIDATE_BOOLEAN);
        $show_total = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $urgency_threshold = absint($atts['urgency_threshold']);

        // Check if workshop exists.
        if (! get_post($workshop_id)) {
            return '';
        }

        // Check if checkout is enabled.
        if (! WorkshopMetaBox::is_checkout_enabled($workshop_id)) {
            return '';
        }

        $capacity = WorkshopMetaBox::get_capacity($workshop_id);
        $remaining = WorkshopMetaBox::get_remaining_spots($workshop_id);
        
        // Determine display state.
        $is_unlimited = ($remaining === null);
        $is_sold_out = (! $is_unlimited && $remaining <= 0);
        $is_urgent = (! $is_unlimited && ! $is_sold_out && $remaining <= $urgency_threshold);

        // Build CSS classes.
        $classes = ['fb-capacity'];
        if ($atts['class']) {
            $classes[] = $atts['class'];
        }
        if ($is_sold_out) {
            $classes[] = 'fb-capacity--sold-out';
        } elseif ($is_urgent) {
            $classes[] = 'fb-capacity--urgent';
        } elseif ($is_unlimited) {
            $classes[] = 'fb-capacity--unlimited';
        }

        // Determine text.
        if ($is_sold_out) {
            $text = $atts['sold_out_text'];
            $icon = '✕';
        } elseif ($is_unlimited) {
            $text = $atts['unlimited_text'];
            $icon = '∞';
        } else {
            // Use singular or plural text.
            $template = ($remaining === 1) ? $atts['spot_text'] : $atts['spots_text'];
            $text = sprintf($template, $remaining);
            $icon = '○';
            
            // Add total if requested.
            if ($show_total && $capacity > 0) {
                $text .= sprintf(' / %d total', $capacity);
            }
        }

        ob_start();
        ?>
        <span class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-workshop-id="<?php echo esc_attr($workshop_id); ?>">
            <?php if ($show_icon) : ?>
            <span class="fb-capacity__icon"><?php echo esc_html($icon); ?></span>
            <?php endif; ?>
            <span class="fb-capacity__text"><?php echo esc_html($text); ?></span>
        </span>

        <style>
            .fb-capacity {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
            }
            .fb-capacity__icon {
                font-size: 12px;
            }
            .fb-capacity--sold-out {
                background: #f8d7da;
                color: #721c24;
            }
            .fb-capacity--urgent {
                background: #fff3cd;
                color: #856404;
            }
            .fb-capacity--unlimited {
                background: #d4edda;
                color: #155724;
            }
            .fb-capacity:not(.fb-capacity--sold-out):not(.fb-capacity--urgent):not(.fb-capacity--unlimited) {
                background: #e2e3e5;
                color: #383d41;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}

