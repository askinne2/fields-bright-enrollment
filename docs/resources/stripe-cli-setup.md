# Stripe CLI Local Testing Guide

This guide covers setting up the Stripe CLI for local development and testing of the Fields Bright Enrollment System webhooks.

## Prerequisites

- A Stripe account (test mode)
- Access to terminal/command line
- LocalWP or similar local development environment

## Installing Stripe CLI

### macOS (Homebrew)

```bash
brew install stripe/stripe-cli/stripe
```

### macOS (Direct Download)

```bash
# Download the latest release
curl -L https://github.com/stripe/stripe-cli/releases/latest/download/stripe_darwin_arm64.tar.gz -o stripe.tar.gz

# Extract and install
tar -xvf stripe.tar.gz
sudo mv stripe /usr/local/bin/
```

### Windows (Scoop)

```powershell
scoop bucket add stripe https://github.com/stripe/scoop-stripe-cli.git
scoop install stripe
```

### Linux

```bash
# Debian/Ubuntu
curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
sudo apt update
sudo apt install stripe
```

## Initial Setup

### 1. Login to Stripe CLI

```bash
stripe login
```

This opens a browser window to authenticate. Follow the prompts to connect your Stripe account.

### 2. Verify Installation

```bash
stripe --version
stripe config --list
```

## Local Webhook Testing

### Start Webhook Forwarding

For LocalWP sites (fields-bright.local):

```bash
stripe listen --forward-to https://fields-bright.local/wp-json/fields-bright/v1/stripe/webhook --skip-verify
```

**Flags explained:**
- `--forward-to`: Your local webhook endpoint URL
- `--skip-verify`: Skip SSL certificate verification (required for local self-signed certs)

### Getting the Webhook Signing Secret

When you start `stripe listen`, you'll see output like:

```
Ready! Your webhook signing secret is whsec_abc123...
```

**Important:** Copy this secret and add it to WordPress:
1. Go to **Enrollment > Settings** in WP Admin
2. Paste the secret in the **Webhook Secret** field
3. Save settings

> **Note:** The CLI webhook secret is different from your Stripe Dashboard webhook secret. Use the CLI secret for local development.

### Keep the Terminal Open

The `stripe listen` command runs continuously. Keep this terminal open while testing webhooks locally.

## Testing Webhook Events

### Trigger Test Events

In a separate terminal, trigger specific events:

```bash
# Successful checkout completion
stripe trigger checkout.session.completed

# Refund event
stripe trigger charge.refunded

# Payment failure
stripe trigger payment_intent.payment_failed
```

### Testing Real Checkout Flow

1. Start webhook forwarding (as above)
2. Visit a workshop page with enrollment enabled
3. Click "Enroll Now"
4. Use a Stripe test card to complete payment
5. Watch the terminal for webhook events

### Test Card Numbers

Use these test cards in Stripe Checkout:

| Card Number | Description |
|-------------|-------------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 3220` | 3D Secure authentication required |
| `4000 0000 0000 9995` | Declined (insufficient funds) |
| `4000 0000 0000 0002` | Declined (generic) |

For all test cards:
- Any future expiration date (e.g., 12/34)
- Any 3-digit CVC
- Any billing postal code

## Monitoring & Debugging

### View Recent Events

```bash
stripe events list --limit 10
```

### View Event Details

```bash
stripe events retrieve evt_xxxxxxxxxxxxx
```

### View Logs

```bash
stripe logs tail
```

### Check Webhook Endpoint Status

In Stripe Dashboard: **Developers > Webhooks** shows webhook delivery status and any failed attempts.

## WordPress Debug Logs

Enable WordPress debug logging to see enrollment system logs:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs appear in `wp-content/debug.log` with prefixes:
- `[Fields Bright Enrollment]` - General enrollment logs
- `[Fields Bright Webhook]` - Webhook-specific logs

## Common Issues & Solutions

### "Webhook signature verification failed"

**Cause:** Webhook secret mismatch between CLI and WordPress settings.

**Solution:**
1. Copy the webhook signing secret from `stripe listen` output
2. Update the secret in **Enrollment > Settings**
3. Make sure you're using the CLI secret, not the Dashboard webhook secret

### "Connection refused" or "Could not resolve host"

**Cause:** Local site not accessible.

**Solution:**
1. Ensure LocalWP is running
2. Verify the site is accessible in browser
3. Check the URL in `--forward-to` matches your local site exactly

### SSL Certificate Errors

**Cause:** Self-signed certificate not trusted.

**Solution:** Use `--skip-verify` flag (already included in our command).

### Webhook Events Not Reaching WordPress

**Debugging steps:**
1. Check `stripe listen` terminal for incoming events
2. Check `wp-content/debug.log` for webhook processing logs
3. Verify REST API endpoint works: visit `/wp-json/fields-bright/v1/health`
4. Check for PHP errors in debug log

### Enrollments Stuck in "Pending"

**Cause:** Webhook not being received or processed.

**Solution:**
1. Confirm `stripe listen` is running
2. Check webhook secret is correct
3. Trigger a test event: `stripe trigger checkout.session.completed`
4. Check debug logs for errors

## Production Webhook Setup

When deploying to production:

1. Go to **Stripe Dashboard > Developers > Webhooks**
2. Click **Add endpoint**
3. Enter your production webhook URL:
   ```
   https://your-domain.com/wp-json/fields-bright/v1/stripe/webhook
   ```
4. Select events:
   - `checkout.session.completed`
   - `charge.refunded`
5. Click **Add endpoint**
6. Copy the **Signing secret** (starts with `whsec_`)
7. Add the secret to **Enrollment > Settings** in production WordPress

## Useful Commands Reference

```bash
# Login
stripe login

# Start webhook forwarding (local)
stripe listen --forward-to https://fields-bright.local/wp-json/fields-bright/v1/stripe/webhook --skip-verify

# Trigger test events
stripe trigger checkout.session.completed
stripe trigger charge.refunded

# View recent events
stripe events list --limit 10

# View logs in real-time
stripe logs tail

# Check CLI version
stripe --version

# Get help
stripe help
stripe listen --help
```

## Resources

- [Stripe CLI Documentation](https://stripe.com/docs/stripe-cli)
- [Stripe Test Cards](https://stripe.com/docs/testing#cards)
- [Webhook Best Practices](https://stripe.com/docs/webhooks/best-practices)
- [Stripe API Reference](https://stripe.com/docs/api)

