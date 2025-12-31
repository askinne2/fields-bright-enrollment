<?php
/**
 * Email Template Manager
 *
 * Manages email templates including rendering, variables, and Branda integration.
 *
 * @package FieldsBright\Enrollment\Email
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Email;

use FieldsBright\Enrollment\Utils\Logger;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class TemplateManager
 *
 * Handles email template management and rendering.
 *
 * @since 1.2.0
 */
class TemplateManager
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Template directory.
     *
     * @var string
     */
    private string $template_dir;

    /**
     * Available template types.
     *
     * @var array
     */
    private array $template_types = [
        'enrollment-confirmation' => 'Enrollment Confirmation',
        'admin-notification'      => 'Admin Notification',
        'refund-confirmation'     => 'Refund Confirmation',
        'welcome'                 => 'Welcome Email',
        'reminder'                => 'Workshop Reminder',
        'follow-up'               => 'Workshop Follow-up',
        'cancellation'            => 'Enrollment Cancellation',
        'waitlist-notification'   => 'Waitlist Notification',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->logger = Logger::instance();
        $this->template_dir = get_stylesheet_directory() . '/templates/emails/';
    }

    /**
     * Render a template.
     *
     * @param string $template Template name.
     * @param array  $data     Template data/variables.
     *
     * @return string Rendered template content.
     */
    public function render(string $template, array $data): string
    {
        $this->logger->debug("Rendering email template: {$template}", [
            'template' => $template,
            'data_keys' => array_keys($data),
        ]);

        // Check if Branda is active and should handle templating.
        if ($this->should_use_branda()) {
            $this->logger->debug('Using Branda email template wrapper');
            return $this->render_with_branda($template, $data);
        }

        // Use custom template.
        return $this->render_custom_template($template, $data);
    }

    /**
     * Render custom template.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     *
     * @return string Rendered content.
     */
    private function render_custom_template(string $template, array $data): string
    {
        $template_path = $this->template_dir . $template . '.php';

        // Check for PHP template.
        if (file_exists($template_path)) {
            $this->logger->debug("Found PHP template: {$template_path}");
            ob_start();
            extract($data, EXTR_SKIP);
            include $template_path;
            return ob_get_clean();
        }

        // Check for text template.
        $txt_path = $this->template_dir . $template . '.txt';
        if (file_exists($txt_path)) {
            $this->logger->debug("Found text template: {$txt_path}");
            ob_start();
            extract($data, EXTR_SKIP);
            include $txt_path;
            return nl2br(ob_get_clean());
        }

        // Use default template.
        $this->logger->warning("No custom template found for: {$template}, using default");
        return $this->get_default_template($template, $data);
    }

    /**
     * Render with Branda integration.
     *
     * When Branda is active, we need to extract only the body content
     * from our templates since Branda provides the outer HTML structure.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     *
     * @return string Rendered content (body only).
     */
    private function render_with_branda(string $template, array $data): string
    {
        // Render the full template first.
        $full_content = $this->render_custom_template($template, $data);

        // Extract only the body content (strip DOCTYPE, html, head, body tags).
        $body_content = $this->extract_body_content($full_content);

        return $body_content;
    }

    /**
     * Extract body content from full HTML document.
     *
     * Removes DOCTYPE, html, head, and body tags, keeping only the inner content.
     * This is used when Branda is active, as Branda provides the outer structure.
     * We extract the content from inside the main 600px table cells or div-based templates,
     * which will be wrapped in Branda's {MESSAGE} div.
     *
     * @param string $html Full HTML document or div-based template.
     *
     * @return string Body content only (content from inside table cells or divs).
     */
    private function extract_body_content(string $html): string
    {
        // Check if this is a div-based template (no DOCTYPE/html/body tags).
        $is_div_template = ! preg_match('/<!DOCTYPE|<html|<body/is', $html);
        
        if ($is_div_template) {
            // Div-based template: extract the main content div.
            // Look for div with padding: 30px and background: #f9f9f9 (in any order).
            
            // Find all divs with these styles.
            preg_match_all('/<div[^>]*style="([^"]*)"[^>]*>(.*?)<\/div>/is', $html, $all_divs, PREG_SET_ORDER);
            
            foreach ($all_divs as $div_match) {
                $style = $div_match[1];
                $content = $div_match[2];
                
                // Check if this div has both padding: 30px and background: #f9f9f9.
                if (preg_match('/padding:\s*30px/i', $style) && preg_match('/background:\s*#f9f9f9/i', $style)) {
                    // This is the main content div - return its inner content.
                    return trim($content);
                }
            }
            
            // Fallback: return the entire div structure (will be wrapped in Branda's div).
            return trim($html);
        }

        // Table-based template: extract from HTML document structure.
        // Remove DOCTYPE declaration.
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);

        // Remove HTML opening/closing tags.
        $html = preg_replace('/<\/?html[^>]*>/i', '', $html);

        // Remove HEAD section and its contents.
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);

        // Extract content from BODY tag (keep inner content, remove body tags).
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        // Find the 600px centered table (the main email content table).
        $table_pattern = '/<table[^>]*role=["\']presentation["\'][^>]*width=["\']600["\'][^>]*align=["\']center["\'][^>]*style="[^"]*"[^>]*>(.*?)<\/table>/is';
        
        if (! preg_match($table_pattern, $html, $table_matches)) {
            // Try without align attribute.
            $table_pattern = '/<table[^>]*width=["\']600["\'][^>]*role=["\']presentation["\'][^>]*>(.*?)<\/table>/is';
            if (! preg_match($table_pattern, $html, $table_matches)) {
                // Try any 600px table.
                $table_pattern = '/<table[^>]*width=["\']600["\'][^>]*>(.*?)<\/table>/is';
                if (! preg_match($table_pattern, $html, $table_matches)) {
                    // Fallback: return body content as-is.
                    return trim($html);
                }
            }
        }

        $table_content = $table_matches[1];

        // Extract the main body content from the table.
        // Branda's {MESSAGE} variable is inside a <div>, so we need content that works in a div context.
        // We'll extract the content from the main content cell (the <td> with padding: 40px or 30px).
        // This content includes paragraphs, nested tables (like the details card), etc.
        
        // Pattern 1: Find the main body content cell (has padding: 40px or 30px, no background color).
        // This is the primary content area where the actual email message lives.
        $body_pattern = '/<tr[^>]*>\s*<td[^>]*style="[^"]*padding:\s*(30|40)px[^"]*"[^>]*>(.*?)<\/td>\s*<\/tr>/is';
        
        if (preg_match_all($body_pattern, $table_content, $body_matches, PREG_SET_ORDER)) {
            foreach ($body_matches as $body_match) {
                $cell_html = $body_match[0]; // Full <tr><td>...</td></tr>
                $cell_content = trim($body_match[2]); // Content inside <td>
                
                // Skip cells with background color (those are headers/footers).
                if (preg_match('/style="[^"]*background[^"]*#271C1A/is', $cell_html) ||
                    preg_match('/style="[^"]*background[^"]*#0073aa/is', $cell_html)) {
                    continue;
                }
                
                // This is the main content cell - return its inner HTML content.
                // The content includes paragraphs, nested tables (details card), etc.
                // This will be wrapped in Branda's {MESSAGE} div.
                if (! empty($cell_content) && strlen($cell_content) > 50) {
                    return $cell_content;
                }
            }
        }

        // Pattern 2: Try a more flexible match for the body content.
        // Look for a <td> with padding that contains substantial content (not just a header).
        if (preg_match_all('/<tr[^>]*>.*?<td[^>]*style="[^"]*padding[^"]*"[^>]*>(.*?)<\/td>.*?<\/tr>/is', $table_content, $all_cells)) {
            foreach ($all_cells[1] as $index => $cell_content) {
                $cell_content = trim($cell_content);
                
                // Skip cells with background color (those are headers/footers).
                if (preg_match('/style="[^"]*background[^"]*#271C1A/is', $all_cells[0][$index]) ||
                    preg_match('/style="[^"]*background[^"]*#0073aa/is', $all_cells[0][$index])) {
                    continue;
                }
                
                // If this cell has substantial content (more than just a title), use it.
                if (! empty($cell_content) && strlen($cell_content) > 100) {
                    return $cell_content;
                }
            }
        }

        // Last resort: return the table content as-is (will be wrapped in Branda's div).
        // This preserves the structure but may cause nesting issues.
        return $table_content;
    }

    /**
     * Check if Branda is active (public helper for templates).
     *
     * Templates can use this to conditionally output content.
     *
     * @return bool Whether Branda is active.
     */
    public static function is_branda_active(): bool
    {
        // Check if Branda email template module is active.
        if (! class_exists('Branda_Email_Template')) {
            return false;
        }

        // Check if enrollment system should use Branda (can be filtered).
        return apply_filters('fields_bright_use_branda_templates', true);
    }

    /**
     * Add Branda template variables.
     *
     * Branda uses variables like {BLOG_NAME}, {BLOG_URL}, {MESSAGE}, etc.
     * The {MESSAGE} variable will contain our rendered template content.
     *
     * @param string $content Current content.
     * @param array  $data    Template data.
     *
     * @return string Content with Branda-compatible format.
     */
    private function add_branda_variables(string $content, array $data): string
    {
        // Branda will wrap our content in {MESSAGE}
        // Just ensure content is properly formatted for email
        return $content;
    }

    /**
     * Check if Branda should be used.
     *
     * @return bool Whether to use Branda.
     */
    private function should_use_branda(): bool
    {
        // Check if Branda email template module is active.
        if (! class_exists('Branda_Email_Template')) {
            return false;
        }

        // Check if enrollment system should use Branda (can be filtered).
        return apply_filters('fields_bright_use_branda_templates', true);
    }

    /**
     * Get default template content.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     *
     * @return string Default template content.
     */
    private function get_default_template(string $template, array $data): string
    {
        $defaults = [
            'enrollment-confirmation' => $this->get_default_confirmation($data),
            'admin-notification'      => $this->get_default_admin_notification($data),
            'refund-confirmation'     => $this->get_default_refund($data),
            'welcome'                 => $this->get_default_welcome($data),
            'reminder'                => $this->get_default_reminder($data),
            'follow-up'               => $this->get_default_follow_up($data),
            'cancellation'            => $this->get_default_cancellation($data),
            'waitlist-notification'   => $this->get_default_waitlist($data),
        ];

        return $defaults[$template] ?? $this->get_generic_template($data);
    }

    /**
     * Get default confirmation template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_confirmation(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Enrollment Confirmed!</h2>';
        $html .= '<p>Dear ' . esc_html($data['customer_name'] ?? 'Valued Customer') . ',</p>';
        $html .= '<p>Thank you for enrolling in <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong>.</p>';
        $html .= '<p><strong>Confirmation #:</strong> ' . esc_html($data['confirmation_number'] ?? '') . '</p>';
        $html .= '<p><strong>Amount Paid:</strong> $' . esc_html(number_format((float) ($data['amount'] ?? 0), 2)) . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default admin notification template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_admin_notification(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>New Enrollment</h2>';
        $html .= '<p>A new enrollment has been received:</p>';
        $html .= '<p><strong>Customer:</strong> ' . esc_html($data['customer_name'] ?? '') . '</p>';
        $html .= '<p><strong>Email:</strong> ' . esc_html($data['customer_email'] ?? '') . '</p>';
        $html .= '<p><strong>Workshop:</strong> ' . esc_html($data['workshop_title'] ?? '') . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default refund template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_refund(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Refund Processed</h2>';
        $html .= '<p>Your refund has been processed for <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong>.</p>';
        $html .= '<p>Amount refunded: $' . esc_html(number_format((float) ($data['amount'] ?? 0), 2)) . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default welcome template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_welcome(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Welcome!</h2>';
        $html .= '<p>Dear ' . esc_html($data['customer_name'] ?? 'Valued Customer') . ',</p>';
        $html .= '<p>Welcome to ' . esc_html($data['site_name'] ?? get_bloginfo('name')) . '!</p>';
        $html .= '<p>Your account has been created successfully.</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default reminder template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_reminder(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Workshop Reminder</h2>';
        $html .= '<p>This is a reminder that <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong> is coming up soon!</p>';
        if (! empty($data['event_start'])) {
            $html .= '<p><strong>Date:</strong> ' . esc_html(date_i18n(get_option('date_format'), strtotime($data['event_start']))) . '</p>';
        }
        $html .= '<p>We look forward to seeing you there!</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default follow-up template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_follow_up(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Thank You for Attending!</h2>';
        $html .= '<p>We hope you enjoyed <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong>.</p>';
        $html .= '<p>We\'d love to hear your feedback!</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default cancellation template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_cancellation(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Enrollment Cancelled</h2>';
        $html .= '<p>Your enrollment for <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong> has been cancelled.</p>';
        $html .= '<p>We hope to see you at a future workshop!</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get default waitlist notification template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_default_waitlist(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<h2>A Spot is Available!</h2>';
        $html .= '<p>Good news! A spot has opened up for <strong>' . esc_html($data['workshop_title'] ?? '') . '</strong>.</p>';
        $html .= '<p>Click below to claim your spot:</p>';
        if (! empty($data['enroll_url'])) {
            $html .= '<p><a href="' . esc_url($data['enroll_url']) . '">Enroll Now</a></p>';
        }
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get generic template.
     *
     * @param array $data Template data.
     *
     * @return string Template HTML.
     */
    private function get_generic_template(array $data): string
    {
        $html = '<div style="font-family: Arial, sans-serif;">';
        $html .= '<p>' . esc_html($data['message'] ?? 'Thank you for your enrollment.') . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get available template types.
     *
     * @return array Template types.
     */
    public function get_template_types(): array
    {
        return $this->template_types;
    }

    /**
     * Check if template exists.
     *
     * @param string $template Template name.
     *
     * @return bool Whether template exists.
     */
    public function template_exists(string $template): bool
    {
        $php_path = $this->template_dir . $template . '.php';
        $txt_path = $this->template_dir . $template . '.txt';
        
        return file_exists($php_path) || file_exists($txt_path);
    }
}

