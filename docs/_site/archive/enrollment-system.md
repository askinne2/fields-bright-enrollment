# Fields Bright Enrollment System Documentation

## Overview

The Fields Bright Enrollment System is a comprehensive WordPress solution for managing online enrollments for workshops and coaching classes. It provides a complete enrollment experience with Stripe payment integration, advanced logging, email template management, and user account features.

**Version:** 1.2.0

**Core Features:**
- **Payment Processing:** Stripe Checkout integration with webhook support
- **Shopping Cart:** Multi-item cart system with session persistence
- **User Management:** Account linking, profile editing, role-based permissions
- **Waitlist System:** Automated waitlist management with notifications
- **Email System:** Template management with Branda Email Template module integration
- **Logging:** Centralized logging system with admin UI for debugging
- **Capacity Management:** Real-time capacity tracking and countdown
- **Admin Tools:** Refund processing, export functionality, comprehensive dashboard
- **Security:** Role-based access control, admin bar hiding for customers

**What's New in 1.2.0:**
- ✨ Centralized Logger with verbose debugging and admin UI
- ✨ Email Template Manager with Branda integration support
- ✨ New email templates (welcome, reminder, follow-up, cancellation, waitlist)
- ✨ Profile Manager with frontend editing and password change
- ✨ Enhanced account dashboard
- ✨ Improved logging throughout all system processes
- ✨ Custom Branda email template with brand styling

**Note:** This system replaces JetEngine for workshop meta fields. After migrating, you can delete the "Event Info" meta box from JetEngine > Meta Boxes.

## System Architecture

```
wp-content/themes/fields-bright-child-theme/
├── functions.php (bootstraps enrollment system)
├── includes/
│   ├── Autoloader.php (PSR-4 autoloader)
│   └── Enrollment/
│       ├── EnrollmentSystem.php (main class)
│       ├── PostType/
│       │   └── EnrollmentCPT.php (enrollment CPT)
│       ├── MetaBoxes/
│       │   ├── EnrollmentMetaBox.php
│       │   └── WorkshopMetaBox.php
│       ├── Stripe/
│       │   ├── StripeHandler.php
│       │   └── WebhookHandler.php
│       ├── Handlers/
│       │   └── EnrollmentHandler.php
│       ├── Admin/
│       │   ├── AdminMenu.php (top-level menu)
│       │   ├── Dashboard.php (admin dashboard)
│       │   ├── AdminSettings.php
│       │   ├── WorkshopEnrollmentMetaBox.php
│       │   ├── RefundHandler.php
│       │   ├── RefundMetaBox.php
│       │   ├── ExportHandler.php
│       │   └── LogViewer.php (system logs viewer)
│       ├── Cart/
│       │   ├── CartManager.php
│       │   └── CartStorage.php
│       ├── Accounts/
│       │   ├── UserAccountHandler.php
│       │   ├── AccountDashboard.php
│       │   └── ProfileManager.php (profile editing)
│       ├── Waitlist/
│       │   ├── WaitlistCPT.php
│       │   ├── WaitlistHandler.php
│       │   └── WaitlistForm.php
│       ├── Email/
│       │   ├── EmailHandler.php
│       │   └── TemplateManager.php (email template management)
│       ├── Utils/
│       │   ├── Logger.php (centralized logging)
│       │   └── LogLevel.php (log level constants)
│       ├── Shortcodes/
│       │   ├── CapacityShortcode.php
│       │   └── CartShortcodes.php (cart icon, summary, add-to-cart)
│       └── REST/
│           ├── EnrollmentEndpoints.php
│           └── CartEndpoints.php
├── templates/
│   ├── enrollment-success.php
│   ├── enrollment-cancel.php
│   ├── account-dashboard.php
│   └── emails/
│       ├── enrollment-confirmation.php
│       ├── admin-notification.php
│       ├── refund-confirmation.php
│       ├── welcome.php (new user welcome)
│       ├── reminder.php (workshop reminder)
│       ├── follow-up.php (post-workshop follow-up)
│       ├── cancellation.php (enrollment cancellation)
│       └── waitlist-notification.php (waitlist spot available)
├── assets/
│   ├── js/
│   │   ├── enrollment-pricing.js
│   │   ├── enrollment-admin.js
│   │   ├── enrollment-cart.js (cart operations)
│   │   ├── waitlist-form.js
│   │   ├── refund-admin.js
│   │   ├── log-viewer.js (log viewer UI)
│   │   └── profile-manager.js (profile editing)
│   └── css/
│       ├── enrollment-admin.css
│       ├── enrollment-cart.css (cart styling)
│       ├── log-viewer.css (log viewer styling)
│       └── profile-manager.css (profile form styling)
└── docs/
    ├── enrollment-system.md (this file)
    ├── stripe-cli-setup.md
    ├── branda-email-template.html (Branda email template)
    └── branda-email-template-instructions.md (Branda setup guide)
```

## Admin Menu Structure

All enrollment features are consolidated under a single **Enrollment** menu in the WordPress admin:

- **Enrollment** (Top-level)
  - Dashboard - Overview with statistics and quick actions
  - All Enrollments - Enrollment CPT list
  - Waitlist - Waitlist entries management
  - Settings - Stripe configuration
  - Export - Export enrollments with filters
  - Logs - System logs viewer with filtering (NEW in 1.2.0)

## Setup Guide

### 1. Stripe Configuration

1. Log in to your [Stripe Dashboard](https://dashboard.stripe.com)
2. Get your API keys:
   - Go to Developers > API keys
   - Copy your **Publishable key** and **Secret key**
   - For testing, use the test mode keys

3. Configure webhook:
   - Go to Developers > Webhooks
   - Click "Add endpoint"
   - Enter your webhook URL: `https://yoursite.com/wp-json/fields-bright/v1/stripe/webhook`
   - Select events:
     - `checkout.session.completed`
     - `charge.refunded`
   - Copy the **Signing secret**

### 2. WordPress Configuration

1. Go to **Enrollment > Settings** in WordPress admin
2. Enter your Stripe keys:
   - Test Publishable Key
   - Test Secret Key
   - Live Publishable Key (when ready for production)
   - Live Secret Key (when ready for production)
   - Webhook Secret
3. Select success and cancel pages
4. Enable/disable Test Mode

### 3. Creating Success/Cancel Pages

1. Create a new page titled "Enrollment Success"
2. Create a new page titled "Enrollment Cancelled"
3. Select these pages in Enrollment > Settings

### 4. Enabling Enrollment on Workshops

1. Edit a workshop post (must be in "Workshops" category)
2. Scroll to "Online Enrollment Settings" meta box
3. Check "Enable Online Enrollment"
4. Set the base price or add pricing options
5. Set capacity (optional, 0 = unlimited)
6. Enable waitlist (optional)
7. Save the post

## Features

### Cart System

The cart system allows customers to add multiple workshops before checkout:

- Session-based persistence using cookies
- User account linking on login
- Cart merging when guest logs in
- REST API for React/JS integration

**Cart REST Endpoints:**
- `GET /wp-json/fields-bright/v1/cart` - Get current cart
- `POST /wp-json/fields-bright/v1/cart/add` - Add item
- `DELETE /wp-json/fields-bright/v1/cart/remove/{workshop_id}` - Remove item
- `POST /wp-json/fields-bright/v1/cart/clear` - Clear cart

### User Accounts

Customers are automatically linked to WordPress accounts:

- Auto-creates account on first enrollment (role: "customer")
- Sends welcome email with password reset link
- Enrollment history accessible via shortcode
- Guest checkout supported (account created after payment)

**Account Shortcodes:**
```php
// Full account dashboard
[enrollment_account]

// Enrollment history table
[enrollment_history status="completed" limit="10"]
```

### Waitlist

When a workshop reaches capacity:

1. Waitlist form appears instead of enrollment button
2. Customers can join waitlist with name/email
3. When spot opens (refund/cancellation), next person is notified
4. Manual conversion available in admin

**Waitlist Shortcode:**
```php
[waitlist_form workshop_id="123" title="Join the Waitlist" show_phone="false"]
```

### Capacity Display

Show remaining spots with the capacity shortcode:

```php
[enrollment_capacity workshop_id="123"]
// or alias:
[enrollment_spots workshop_id="123" urgency_threshold="5"]
```

Parameters:
- `workshop_id` - Workshop post ID (default: current post)
- `show_icon` - Show icon (default: true)
- `show_total` - Show total capacity (default: false)
- `urgency_threshold` - Show urgency styling when spots <= this (default: 5)

### Email Notifications

Automatic emails are sent for:
- **Enrollment confirmation** - To customer after successful payment
- **Admin notification** - To site admin on new enrollment
- **Refund confirmation** - To customer when refund processed

Custom templates can be created in `templates/emails/`.

### Refund Processing

Admins can process refunds directly from WordPress:

1. Edit an enrollment
2. Use the "Refund" meta box in the sidebar
3. Enter amount (full or partial)
4. Click "Process Refund"

The system:
- Creates Stripe refund via API
- Updates enrollment status
- Sends confirmation email
- Triggers waitlist notification if applicable

### Export

Enhanced export with filtering:

1. Go to **Enrollment > Export**
2. Set filters:
   - Date range
   - Status (pending/completed/refunded)
   - Workshop
3. Download CSV

## Usage

### Enrollment Flow

1. Visitor views workshop with enrollment enabled
2. Clicks enrollment button (or adds to cart)
3. Redirected to Stripe Checkout
4. Completes payment
5. Stripe sends webhook to WordPress
6. WordPress creates/updates enrollment record
7. User account created/linked
8. Confirmation emails sent
9. Customer redirected to success page

### Meta Fields

#### Workshop Post Meta

All workshop meta fields use the `_event_` prefix for consistency.

**Event Information (formerly JetEngine):**

| Meta Key | Type | Description |
|----------|------|-------------|
| `_event_start_datetime` | datetime | Event start date and time |
| `_event_end_datetime` | datetime | Event end date and time |
| `_event_recurring_date_info` | string | Schedule description (e.g., "Every Tuesday") |
| `_event_price` | string | Display price text (e.g., "$50 per person") |
| `_event_location` | string | Event location |
| `_event_registration_link` | url | External registration link (optional) |

**Enrollment Settings:**

| Meta Key | Type | Description |
|----------|------|-------------|
| `_event_checkout_enabled` | boolean | Whether online enrollment is enabled |
| `_event_checkout_price` | number | Base checkout price in dollars |
| `_event_pricing_options` | JSON | Array of pricing options |
| `_event_capacity` | integer | Maximum enrollments (0 = unlimited) |
| `_event_waitlist_enabled` | boolean | Enable waitlist when full |

#### Pricing Options JSON Structure

```json
[
  {
    "id": "single",
    "label": "Single Session",
    "price": 50.00,
    "default": true
  },
  {
    "id": "bundle",
    "label": "Bundle (4 Sessions)",
    "price": 180.00,
    "default": false
  }
]
```

#### Enrollment Post Meta

| Meta Key | Type | Description |
|----------|------|-------------|
| `_enrollment_workshop_id` | integer | Workshop post ID |
| `_enrollment_stripe_session_id` | string | Stripe Checkout Session ID |
| `_enrollment_stripe_payment_intent_id` | string | Stripe Payment Intent ID |
| `_enrollment_stripe_customer_id` | string | Stripe Customer ID |
| `_enrollment_customer_email` | string | Customer email |
| `_enrollment_customer_name` | string | Customer name |
| `_enrollment_customer_phone` | string | Customer phone |
| `_enrollment_amount` | number | Amount paid |
| `_enrollment_currency` | string | Currency code (usd) |
| `_enrollment_pricing_option_id` | string | Selected pricing option |
| `_enrollment_status` | string | pending/completed/refunded/failed |
| `_enrollment_date` | string | Enrollment date |
| `_enrollment_user_id` | integer | Linked WordPress user ID |
| `_enrollment_refund_id` | string | Stripe refund ID |
| `_enrollment_refund_amount` | number | Refund amount |
| `_enrollment_refund_date` | string | Refund date |
| `_enrollment_notes` | string | Admin notes |

### Shortcodes

#### Enrollment & Cart Shortcodes

**Enrollment Button (Add to Cart)**
```php
[enrollment_button workshop_id="123" text="Add to Cart" class="my-button"]
```
Parameters:
- `workshop_id`: Workshop post ID (default: current post)
- `text`: Button text (default: "Add to Cart")
- `class`: CSS class for button

**Cart Icon** (for header/navigation)
```php
[cart_icon]
```
Displays cart icon with live item count badge.

**Cart Summary** (for cart page)
```php
[cart_summary]
```
Full cart page with items, totals, and checkout button.

**Add to Cart Button** (alternative)
```php
[add_to_cart_button workshop_id="123" text="Add to Cart"]
```

#### Account & Profile Shortcodes

**Account Dashboard**
```php
[enrollment_account show_header="true" show_profile="true"]
```
Complete account overview with enrollment history.

**Enrollment History Table**
```php
[enrollment_history status="" limit="10"]
```
Standalone enrollment history table.

**Profile Edit Form** (NEW in 1.2.0)
```php
[enrollment_profile_form]
```
User profile editing form with password change capability.

#### Waitlist Shortcode

**Waitlist Form**
```php
[waitlist_form workshop_id="123" title="Join the Waitlist" button_text="Join" show_phone="false"]
```

#### Capacity Display

**Capacity Countdown**
```php
[enrollment_capacity workshop_id="123" show_icon="true" urgency_threshold="5"]
```
Shows remaining spots with urgency styling when below threshold.

### Template Functions

Get enrollment URL:
```php
$url = \FieldsBright\Enrollment\Handlers\EnrollmentHandler::get_enrollment_url($workshop_id, $pricing_option);
```

Check if checkout is enabled:
```php
$enabled = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::is_checkout_enabled($post_id);
```

Get pricing options:
```php
$options = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::get_pricing_options($post_id);
```

Get effective price:
```php
$price = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::get_effective_price($post_id, $pricing_option);
```

Check capacity:
```php
$has_spots = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::has_spots_available($post_id);
$remaining = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::get_remaining_spots($post_id);
$is_full = \FieldsBright\Enrollment\MetaBoxes\WorkshopMetaBox::is_full($post_id);
```

## REST API

### Enrollment Endpoints

#### Stripe Webhook
- **URL:** `/wp-json/fields-bright/v1/stripe/webhook`
- **Method:** POST
- **Auth:** Stripe signature verification

#### Health Check
- **URL:** `/wp-json/fields-bright/v1/health`
- **Method:** GET

### Cart Endpoints

#### Get Cart
- **URL:** `/wp-json/fields-bright/v1/cart`
- **Method:** GET

#### Add to Cart
- **URL:** `/wp-json/fields-bright/v1/cart/add`
- **Method:** POST
- **Body:** `{ "workshop_id": 123, "pricing_option": "single" }`

#### Remove from Cart
- **URL:** `/wp-json/fields-bright/v1/cart/remove/{workshop_id}`
- **Method:** DELETE

#### Clear Cart
- **URL:** `/wp-json/fields-bright/v1/cart/clear`
- **Method:** DELETE

### Account Endpoints (Authenticated)

#### Get User Enrollments
- **URL:** `/wp-json/fields-bright/v1/account/enrollments`
- **Method:** GET
- **Query:** `?status=completed`

#### Check Enrollment Status
- **URL:** `/wp-json/fields-bright/v1/account/has-enrolled/{workshop_id}`
- **Method:** GET

## Hooks & Filters

### Actions

```php
// Fired after enrollment system initializes
do_action('fields_bright_enrollment_init', $enrollment_system);

// Fired after enrollment is created
do_action('fields_bright_enrollment_created', $post_id, $data);

// Fired after enrollment status changes
do_action('fields_bright_enrollment_status_updated', $post_id, $status);

// Fired after enrollment is completed (payment successful)
do_action('fields_bright_enrollment_completed', $post_id, $stripe_session);

// Fired after enrollment is refunded
do_action('fields_bright_enrollment_refunded', $post_id, $stripe_charge);

// Activation/deactivation hooks
do_action('fields_bright_enrollment_activate');
do_action('fields_bright_enrollment_deactivate');
```

### Email Filters

```php
// Customize email recipient
add_filter('fields_bright_email_to', function($to) { return $to; });

// Customize email subject
add_filter('fields_bright_email_subject', function($subject) { return $subject; });

// Customize email message
add_filter('fields_bright_email_message', function($message) { return $message; });

// Customize email headers
add_filter('fields_bright_email_headers', function($headers) { return $headers; });

// Toggle Branda email template integration (default: true)
add_filter('fields_bright_use_branda_templates', function($use_branda) {
    return true; // Set to false to disable Branda integration
});
```

### Logging Filters (NEW in 1.2.0)

```php
// Set minimum log level (0=DEBUG, 1=INFO, 2=WARNING, 3=ERROR, 4=CRITICAL)
add_filter('fields_bright_log_level', function($level) {
    return 1; // Only log INFO and above
});

// Hook into log events
add_action('fields_bright_log', function($level, $message, $context) {
    // Custom logging handler
}, 10, 3);
```

## Enhanced Logging System (NEW in 1.2.0)

The enrollment system now includes a comprehensive centralized logging system with admin UI.

### Features

- **Log Levels:** DEBUG, INFO, WARNING, ERROR, CRITICAL
- **Context Tracking:** Each log entry includes timestamp, user ID, IP address, request URI
- **Process Tracking:** Track multi-step processes from start to completion with timing
- **Admin UI:** View, filter, search, and export logs from WordPress admin
- **Database Storage:** Logs stored in database for easy access (INFO+ levels)
- **File Logging:** All logs also written to `debug.log` when `WP_DEBUG_LOG` is enabled

### Accessing Logs

Navigate to: **Enrollment > Logs**

### Log Viewer Features

- **Filter by Level:** Show only specific log levels
- **Search:** Full-text search across messages and context
- **Export:** Download logs as JSON
- **Clear Logs:** Remove all or old logs
- **Auto-refresh:** Page refreshes every 30 seconds
- **Context Viewer:** Click "View" to see detailed context data

### Using Logger in Custom Code

```php
use FieldsBright\Enrollment\Utils\Logger;

$logger = Logger::instance();

// Simple logging
$logger->info('Payment processed');
$logger->warning('Low capacity', ['workshop_id' => 123, 'remaining' => 2]);
$logger->error('Payment failed', ['error' => $error_message]);

// Process tracking
$logger->start_process('checkout', ['cart_items' => 3]);
$logger->log_step('checkout', 'Validating cart');
$logger->log_step('checkout', 'Creating Stripe session');
$logger->end_process('checkout', ['session_id' => 'cs_123']);
```

## Email Template System (NEW in 1.2.0)

### Overview

The system now includes a sophisticated email template manager with support for Branda Email Template module integration.

### Available Templates

1. **enrollment-confirmation** - Sent after successful enrollment
2. **admin-notification** - Notifies admin of new enrollments
3. **refund-confirmation** - Sent when refund is processed
4. **welcome** - Welcome email for new user accounts
5. **reminder** - Workshop reminder (24-48 hours before)
6. **follow-up** - Post-workshop follow-up
7. **cancellation** - Enrollment cancellation confirmation
8. **waitlist-notification** - Waitlist spot available notification

### Branda Integration

The system seamlessly integrates with the Branda (Ultimate Branding) Email Template module:

1. **Install Branda Template:**
   - See `docs/branda-email-template.html` for the template
   - See `docs/branda-email-template-instructions.md` for setup guide

2. **Enable Branda Integration:**
   ```php
   // Already enabled by default if Branda is active
   add_filter('fields_bright_use_branda_templates', '__return_true');
   ```

3. **Disable Branda Integration:**
   ```php
   add_filter('fields_bright_use_branda_templates', '__return_false');
   ```

### Customizing Email Templates

Templates are located in: `templates/emails/`

To customize a template:
1. Copy template from theme to child theme (if not already there)
2. Edit the PHP file with your custom content
3. Use available variables (documented in each template)

Example variables:
- `$customer_name` - Customer's name
- `$workshop_title` - Workshop title
- `$confirmation_number` - Enrollment confirmation number
- `$amount` - Payment amount
- `$event_start` - Workshop start date/time
- `$site_name` - Website name
- `$site_url` - Website URL

### Template Manager Usage

```php
use FieldsBright\Enrollment\Email\TemplateManager;

$template_manager = new TemplateManager();

// Render a template
$html = $template_manager->render('reminder', [
    'customer_name'  => 'John Doe',
    'workshop_title' => 'Yoga Workshop',
    'event_start'    => '2024-02-15 10:00:00',
]);

// Check if template exists
if ($template_manager->template_exists('welcome')) {
    // Send welcome email
}
```

## Profile Management (NEW in 1.2.0)

### Features

- Frontend profile editing form
- Password change functionality
- Email validation and uniqueness checking
- AJAX form submission
- Success/error messaging
- Password strength indicator

### Using the Profile Form

Add to any page:
```php
[enrollment_profile_form]
```

### Profile Fields

- First Name (required)
- Last Name (required)
- Email Address (required, unique)
- Phone Number (optional)
- Bio (optional)

### Security

- Current password required for password changes
- Minimum 8 character password requirement
- Password confirmation matching
- Email uniqueness validation
- Nonce verification
- Capability checks
- Password change notification emails

### Customizing Profile Form

Use CSS to style the form:
```css
.fb-profile-manager { /* Container */ }
.fb-form { /* Form wrapper */ }
.fb-form-group { /* Form field group */ }
.fb-button-primary { /* Primary button */ }
.fb-button-secondary { /* Secondary button */ }
```

## Troubleshooting

### Common Issues

#### "Stripe is not configured"
- Check that API keys are entered in Enrollment > Settings
- Verify keys match your current mode (test/live)

#### Webhook not working
- Verify webhook URL is correct in Stripe Dashboard
- Check webhook signing secret is correct
- Ensure your site is accessible from the internet (not localhost)
- Check for SSL/HTTPS issues
- See `docs/stripe-cli-setup.md` for local testing

#### Enrollments stuck in "pending"
- Webhook may not be receiving events
- Check Stripe Dashboard > Developers > Webhooks for failed events
- Verify webhook signature secret

#### Emails not sending
- Check that WP Mail SMTP Pro is configured
- Test email sending in WordPress
- Check spam folders
- Check Enrollment > Logs for email-related errors
- Verify Branda Email Templates are configured (if using Branda)

#### Cart not persisting
- Check cookie settings
- Verify transient storage is working
- Clear browser cookies and try again

### Debug Mode

Enable WordPress debug mode to see enrollment system logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**NEW in 1.2.0:** Access logs through admin UI at **Enrollment > Logs**

Logs also appear in `wp-content/debug.log` with prefix `[Fields Bright]`:
- **DEBUG** - Detailed debugging information
- **INFO** - General informational messages
- **WARNING** - Warning messages for potential issues
- **ERROR** - Error messages for failures
- **CRITICAL** - Critical errors requiring immediate attention

**Log Management:**
- View logs with filtering: Enrollment > Logs
- Export logs as JSON
- Clear old logs (30+ days)
- Search across all log entries

## Security

- All inputs are sanitized and validated
- Nonces used for all form submissions
- Capability checks for admin functions
- Stripe webhook signature verification
- Rate limiting on enrollment endpoint
- No sensitive data exposed in frontend
- Password reset emails for auto-created accounts

## Updates & Maintenance

### Clearing Processed Webhooks

The system stores processed webhook event IDs to prevent duplicate processing. These are automatically trimmed to the last 1000 events. To manually clear:

```php
delete_option('fields_bright_processed_webhook_events');
```

### Flushing Rewrite Rules

After making changes that affect URLs:

1. Go to Settings > Permalinks
2. Click "Save Changes" without modifying anything

Or programmatically:

```php
flush_rewrite_rules();
```

### Cleanup Tasks

Cart transients are automatically cleaned up after 30 days. Waitlist entries can be manually archived through the admin interface.

## Local Development

For local Stripe webhook testing, see `docs/stripe-cli-setup.md`.

## Support

For issues or questions:
1. Check this documentation
2. Enable debug logging and check logs
3. Verify Stripe configuration in dashboard
4. Check `docs/stripe-cli-setup.md` for webhook testing
5. Contact site administrator
