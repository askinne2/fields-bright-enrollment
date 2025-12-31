---
layout: default
title: Security Guide
description: Security best practices and implementation details for the Fields Bright Enrollment System.
---

# Security Guide

This document outlines the security measures implemented in the Fields Bright Enrollment System and provides guidelines for maintaining a secure installation.

<div class="note">
This documentation is for developers and system administrators. Non-technical users should ensure they work with qualified professionals when handling security matters.
</div>

---

## Security Overview

The enrollment system implements multiple layers of security:

1. **Input Validation & Sanitization** - All user input is validated and sanitized
2. **Authentication & Authorization** - Proper access controls and capability checks
3. **CSRF Protection** - Nonces on all forms and AJAX requests
4. **SQL Injection Prevention** - Prepared statements for all database queries
5. **XSS Prevention** - Output escaping on all displayed data
6. **Secure Payment Handling** - PCI-compliant through Stripe

---

## Input Validation & Sanitization

### All User Input is Sanitized

Every input from users (GET, POST, cookies) is sanitized before use:

```php
// Always use wp_unslash before sanitization
$workshop_id = absint(wp_unslash($_POST['workshop_id']));
$email = sanitize_email(wp_unslash($_POST['email']));
$name = sanitize_text_field(wp_unslash($_POST['name']));
$description = sanitize_textarea_field(wp_unslash($_POST['description']));
$url = esc_url_raw(wp_unslash($_POST['url']));
```

### Sanitization Functions Used

| Data Type | Function | Use Case |
|-----------|----------|----------|
| Integer | `absint()` | IDs, counts, prices (cents) |
| Email | `sanitize_email()` | Email addresses |
| Text (single line) | `sanitize_text_field()` | Names, titles |
| Text (multi-line) | `sanitize_textarea_field()` | Descriptions |
| URL | `esc_url_raw()` | URLs for database storage |
| Filename | `sanitize_file_name()` | File operations |
| HTML | `wp_kses_post()` | Rich text content |

### REST API Validation

REST endpoints define validation schemas:

```php
'args' => [
    'workshop_id' => [
        'required' => true,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'validate_callback' => function($value) {
            return $value > 0;
        }
    ],
    'email' => [
        'required' => true,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_email',
        'validate_callback' => 'is_email'
    ]
]
```

---

## Authentication & Authorization

### Nonce Verification

All forms and AJAX requests use nonces:

```php
// Verify nonce before processing
if (!wp_verify_nonce(
    sanitize_text_field(wp_unslash($_POST['nonce'])),
    'fields_bright_action'
)) {
    wp_die('Security check failed');
}

// For AJAX
check_ajax_referer('fields_bright_action', 'nonce');
```

### Capability Checks

Admin functions verify user capabilities:

```php
// Check user can manage enrollments
if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized access');
}

// Check user can process refunds
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}
```

### REST API Authentication

- **Public endpoints** (cart, checkout): Session-based with nonces
- **User endpoints**: `is_user_logged_in()` check
- **Admin endpoints**: `current_user_can('manage_options')` check
- **Webhook endpoint**: Stripe signature verification

---

## CSRF Protection

### Form Nonces

All forms include nonce fields:

```php
// In form output
wp_nonce_field('save_enrollment', 'enrollment_nonce');

// On submission
if (!wp_verify_nonce(
    sanitize_text_field(wp_unslash($_POST['enrollment_nonce'])),
    'save_enrollment'
)) {
    wp_die('Invalid request');
}
```

### AJAX Nonces

AJAX requests include nonces in data:

```javascript
jQuery.ajax({
    url: ajaxurl,
    data: {
        action: 'process_enrollment',
        nonce: fieldsbrightData.nonce,
        workshop_id: 123
    }
});
```

---

## SQL Injection Prevention

### Prepared Statements

All database queries use prepared statements:

```php
// Direct queries use $wpdb->prepare()
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}postmeta 
     WHERE post_id = %d AND meta_key = %s",
    $post_id,
    $meta_key
));
```

### WordPress Query Functions

The system primarily uses WordPress query functions:

```php
// get_posts() with proper arguments
$enrollments = get_posts([
    'post_type' => 'enrollment',
    'meta_query' => [
        [
            'key' => 'workshop_id',
            'value' => $workshop_id,
            'compare' => '=',
            'type' => 'NUMERIC'
        ]
    ],
    'posts_per_page' => 100 // Always limit results
]);
```

---

## XSS Prevention

### Output Escaping

All output is escaped appropriately:

```php
// HTML content
echo esc_html($customer_name);

// HTML attributes
echo '<input value="' . esc_attr($email) . '">';

// URLs
echo '<a href="' . esc_url($link) . '">Link</a>';

// JavaScript
echo '<script>var id = ' . esc_js($id) . ';</script>';

// JSON for JS
wp_localize_script('script', 'data', [
    'value' => $value // Automatically escaped
]);
```

### Escaping Functions

| Context | Function | Use Case |
|---------|----------|----------|
| HTML text | `esc_html()` | Text displayed in HTML |
| HTML attribute | `esc_attr()` | Input values, data attributes |
| URL | `esc_url()` | Links, redirects |
| JavaScript | `esc_js()` | Inline scripts |
| Textarea | `esc_textarea()` | Textarea content |
| SQL | `esc_sql()` | (Use prepare() instead) |

---

## Payment Security

### PCI Compliance

Card data never touches your server:

- Customers enter payment info directly on Stripe's hosted page
- Only Stripe tokens/session IDs are handled by the system
- No card numbers, CVCs, or sensitive payment data is stored

### API Key Security

Stripe keys are stored securely:

```php
// Keys stored in WordPress options (database)
$secret_key = get_option('fields_bright_stripe_secret_key');

// Never hardcoded, never logged
// Environment-specific (test vs live)
```

### Webhook Security

Stripe webhooks are verified:

```php
// Verify Stripe signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $webhook_secret
    );
} catch (\Exception $e) {
    http_response_code(400);
    exit;
}
```

### Idempotency

Webhook processing includes idempotency checks:

```php
// Check if event was already processed
if (get_transient('stripe_event_' . $event->id)) {
    return; // Already processed
}

// Process event...

// Mark as processed
set_transient('stripe_event_' . $event->id, true, DAY_IN_SECONDS);
```

---

## Sensitive Data Handling

### Personal Information (PII)

Customer data is handled carefully:

- Stored in WordPress database with standard protection
- Accessible only to authorized administrators
- Can be exported/deleted for GDPR compliance

### IP Address Anonymization

IP addresses are anonymized before logging:

```php
$anonymized_ip = wp_privacy_anonymize_ip(
    sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
);
```

### Password Handling

- User passwords use WordPress authentication
- Never stored in plain text
- Never logged or exposed

### Session Security

Cart sessions use secure tokens:

```php
// Secure cookie settings
setcookie(
    'cart_session',
    $token,
    [
        'expires' => time() + HOUR_IN_SECONDS,
        'path' => COOKIEPATH,
        'domain' => COOKIE_DOMAIN,
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);
```

---

## Direct File Access Prevention

All PHP files prevent direct access:

```php
<?php
// At the top of every file
if (!defined('ABSPATH')) {
    exit;
}
```

---

## Error Handling

### No Sensitive Info in Errors

Error messages shown to users are generic:

```php
// Bad - exposes system details
wp_die($e->getMessage());

// Good - generic message, detailed logging
Logger::error('Enrollment failed', ['exception' => $e->getMessage()]);
wp_die('An error occurred. Please try again.');
```

### Logging Best Practices

- Log errors with context (but no passwords/keys)
- Anonymize IP addresses
- Include timestamps and user info
- Rotate logs periodically

---

## Security Checklist

### For Deployment

- [ ] Stripe keys are for production (not test)
- [ ] HTTPS is enabled and enforced
- [ ] WordPress debug mode is OFF
- [ ] File permissions are correct (644 for files, 755 for directories)
- [ ] Admin accounts use strong passwords
- [ ] WordPress and plugins are up to date

### For Development

- [ ] All inputs are sanitized
- [ ] All outputs are escaped
- [ ] Nonces are used on all forms
- [ ] Capability checks are in place
- [ ] Prepared statements for queries
- [ ] No sensitive data in logs

---

## Reporting Security Issues

If you discover a security vulnerability:

1. **Do not** create a public GitHub issue
2. Contact the development team privately
3. Include details about the vulnerability
4. Allow reasonable time for a fix before disclosure

---

## Additional Resources

- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Stripe Security](https://stripe.com/docs/security)

