---
layout: default
title: Deployment Guide
description: Production deployment and configuration guide for the Fields Bright Enrollment System.
---

# Deployment Guide

This guide covers deploying the Fields Bright Enrollment System to a production environment.

<div class="warning">
This documentation is for developers and system administrators. Improper deployment can cause payment failures or data loss.
</div>

---

## Pre-Deployment Checklist

Before deploying to production:

### Environment
- [ ] PHP 8.0+ installed
- [ ] WordPress 6.0+ running
- [ ] SSL certificate installed and HTTPS enforced
- [ ] MySQL 5.7+ or MariaDB 10.3+

### Configuration
- [ ] Production Stripe API keys ready
- [ ] Stripe webhook endpoint configured
- [ ] Email delivery tested
- [ ] Backup system in place

### Code
- [ ] All debug settings disabled
- [ ] Error logging configured
- [ ] Assets minified (if applicable)
- [ ] Version number updated

---

## Deployment Steps

### Step 1: Backup Current Site

Always backup before deploying:

```bash
# Database backup
wp db export backup-$(date +%Y%m%d).sql

# Files backup
zip -r backup-files-$(date +%Y%m%d).zip wp-content/themes/fields-bright-child-theme
```

### Step 2: Upload Theme Files

Transfer theme files to your server:

```bash
# Via rsync (recommended)
rsync -avz --exclude='.git' --exclude='node_modules' \
  ./fields-bright-child-theme/ \
  user@server:/path/to/wp-content/themes/fields-bright-child-theme/

# Or via SFTP/FTP
# Upload the fields-bright-child-theme folder to wp-content/themes/
```

### Step 3: Activate Theme

If not already active:

```bash
wp theme activate fields-bright-child-theme
```

### Step 4: Configure Stripe

In WordPress Admin:

1. Go to **Settings → Fields Bright Enrollment**
2. Enter **Live Stripe API keys**:
   - Publishable Key: `pk_live_...`
   - Secret Key: `sk_live_...`
   - Webhook Secret: `whsec_...`
3. Save settings

<div class="important">
Double-check you're using LIVE keys, not test keys. Test keys start with `pk_test_` and `sk_test_`.
</div>

### Step 5: Configure Stripe Webhook

In your Stripe Dashboard:

1. Go to **Developers → Webhooks**
2. Click **Add endpoint**
3. Enter your endpoint URL:
   ```
   https://yoursite.com/wp-json/fields-bright/v1/webhook/stripe
   ```
4. Select events to listen for:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. Copy the **Webhook signing secret** to your WordPress settings

### Step 6: Verify Installation

Test the deployment:

```bash
# Check theme is active
wp theme status fields-bright-child-theme

# Verify custom post types registered
wp post-type list --fields=name | grep -E "workshop|enrollment"

# Test REST API
curl -I https://yoursite.com/wp-json/fields-bright/v1/
```

---

## Configuration Options

### WordPress Options

Key options stored in `wp_options`:

| Option Key | Description |
|------------|-------------|
| `fields_bright_stripe_publishable_key` | Stripe public key |
| `fields_bright_stripe_secret_key` | Stripe secret key |
| `fields_bright_stripe_webhook_secret` | Webhook signing secret |
| `fields_bright_admin_email` | Notification email |
| `fields_bright_test_mode` | Enable/disable test mode |

### Environment-Based Configuration

For different environments, consider using `wp-config.php`:

```php
// In wp-config.php
define('FIELDS_BRIGHT_STRIPE_KEY', 'sk_live_...');
define('FIELDS_BRIGHT_DEBUG', false);
```

---

## Performance Optimization

### Caching

The system uses transients for caching. Ensure your cache backend is configured:

```php
// Object caching (recommended)
define('WP_CACHE', true);

// Transient expiration times
// Workshop capacity: 5 minutes
// Pricing options: 1 hour
// Enrollment counts: 5 minutes
```

### Database Optimization

Run periodic optimization:

```bash
# Optimize database tables
wp db optimize

# Check for missing indexes
wp db query "SHOW INDEX FROM wp_posts"
```

### Asset Optimization

Assets are versioned based on file modification time:

```php
// Version string changes when file changes
wp_enqueue_style('enrollment', $url, [], filemtime($path));
```

For additional optimization:

1. Enable WordPress caching plugin
2. Configure CDN for static assets
3. Enable GZIP compression

---

## Email Configuration

### SMTP Setup

For reliable email delivery, configure SMTP:

```php
// Option 1: Use an SMTP plugin like WP Mail SMTP

// Option 2: In wp-config.php or custom plugin
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.example.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587;
    $phpmailer->Username = 'user@example.com';
    $phpmailer->Password = 'password';
    $phpmailer->SMTPSecure = 'tls';
});
```

### Testing Email Delivery

```bash
# Test WordPress email
wp eval 'wp_mail("test@example.com", "Test Subject", "Test body");'
```

---

## Security Configuration

### File Permissions

Set correct permissions:

```bash
# Directories
find /path/to/wp-content/themes/fields-bright-child-theme -type d -exec chmod 755 {} \;

# Files
find /path/to/wp-content/themes/fields-bright-child-theme -type f -exec chmod 644 {} \;
```

### Disable Debug Mode

In `wp-config.php`:

```php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
```

### Secure Headers

Add security headers via `.htaccess` or server config:

```apache
# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## Monitoring

### Log Files

Monitor these log locations:

- **WordPress debug log:** `wp-content/debug.log`
- **Enrollment system log:** `wp-content/uploads/fields-bright-logs/`
- **Server error log:** Check your hosting control panel

### Health Checks

Verify these periodically:

```bash
# Check Stripe connectivity
curl -X GET https://yoursite.com/wp-json/fields-bright/v1/health

# Check recent enrollments
wp post list --post_type=enrollment --posts_per_page=5

# Check for errors in log
tail -100 wp-content/debug.log
```

### Stripe Dashboard

Monitor in Stripe:

- Payment success rate
- Failed payment reasons
- Webhook delivery status
- Dispute rate

---

## Backup Strategy

### Database Backups

Automate daily backups:

```bash
# Cron job for daily backup
0 2 * * * cd /path/to/wordpress && wp db export /backups/db-$(date +\%Y\%m\%d).sql
```

### File Backups

Include in your backup:

- `wp-content/themes/fields-bright-child-theme/`
- `wp-content/uploads/` (enrollment attachments)
- Database export

### Backup Retention

Keep backups for:

- Daily backups: 7 days
- Weekly backups: 4 weeks
- Monthly backups: 12 months

---

## Updating

### Update Process

1. **Backup first** - Always backup before updating
2. **Test on staging** - Apply updates to staging environment first
3. **Deploy to production** - During low-traffic period
4. **Verify functionality** - Test enrollment process after update

### Version Compatibility

Check compatibility before updating:

- WordPress version requirements
- PHP version requirements
- Stripe API version changes

---

## Troubleshooting Deployment

### Common Issues

**Theme not activating:**
- Check PHP version compatibility
- Look for syntax errors in debug log
- Verify all required files are uploaded

**Stripe not connecting:**
- Verify API keys are correct
- Check webhook URL is accessible
- Test with Stripe CLI locally

**Emails not sending:**
- Check SMTP configuration
- Verify sender email is allowed
- Check spam folders

**Enrollments not processing:**
- Verify webhook is receiving events
- Check webhook signing secret
- Review Stripe dashboard for errors

### Getting Help

1. Check the debug log first
2. Review Stripe webhook logs
3. Test components individually
4. Contact development support with detailed logs

---

## Rollback Procedure

If deployment causes issues:

### Quick Rollback

```bash
# Restore database
wp db import backup-YYYYMMDD.sql

# Restore files
unzip backup-files-YYYYMMDD.zip -d wp-content/themes/

# Clear cache
wp cache flush
```

### Verify After Rollback

- Test enrollment process
- Check admin functionality
- Verify existing enrollments are intact

---

## Post-Deployment Checklist

After successful deployment:

- [ ] Test a complete enrollment flow
- [ ] Verify confirmation emails arrive
- [ ] Check admin can view/manage enrollments
- [ ] Process a test refund (if in test mode)
- [ ] Monitor for errors for 24 hours
- [ ] Inform stakeholders of successful deployment

