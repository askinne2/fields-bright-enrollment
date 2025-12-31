# Waitlist Magic Link Implementation

## Overview
The waitlist system now includes secure magic links with tokens to authenticate and reserve spots for waitlist customers.

## What Was Implemented

### 1. Token Generation (WaitlistCPT.php)
- ✅ `generate_claim_token($entry_id)` - Generates secure 64-character token
- ✅ `validate_claim_token($token)` - Validates token and checks expiration
- ✅ `get_claim_url($entry_id, $token, $workshop_id)` - Builds magic link URL
- ✅ Tokens expire after 48 hours
- ✅ Expired tokens automatically mark entry as 'expired'

### 2. Notification Updates (WaitlistHandler.php)
- ✅ Generates token before sending notification
- ✅ Passes token to email methods
- ✅ Logs token generation (first 8 chars only for security)

### 3. Email Template (waitlist-notification.php)
- ✅ Uses `$claim_url` variable instead of generic `$enroll_url`
- ✅ Shows expiration warning (48 hours)
- ✅ Styled button with emoji for visibility

## Still Needed - Frontend Integration

### What Happens When User Clicks Magic Link

The URL will look like:
```
https://site.com/gottman-parent-of-teenagers-series/?waitlist_token=abc123...&entry_id=456
```

### Required Functionality:

#### 1. Token Detection & Validation
Add to `EnrollmentHandler.php` or create new `WaitlistClaimHandler.php`:

```php
public function __construct() {
    add_action('template_redirect', [$this, 'handle_waitlist_claim'], 5);
}

public function handle_waitlist_claim() {
    if (!isset($_GET['waitlist_token'])) {
        return;
    }
    
    $token = sanitize_text_field($_GET['waitlist_token']);
    $entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;
    
    $waitlist_cpt = new WaitlistCPT();
    $validated_entry_id = $waitlist_cpt->validate_claim_token($token);
    
    if (!$validated_entry_id || $validated_entry_id !== $entry_id) {
        // Token invalid or expired
        wp_die(__('This link has expired or is invalid. Please contact us if you need assistance.', 'fields-bright-enrollment'));
    }
    
    // Store validated entry info in session/transient
    set_transient('waitlist_claim_' . get_current_user_id(), [
        'entry_id' => $validated_entry_id,
        'workshop_id' => get_post_meta($validated_entry_id, '_waitlist_workshop_id', true),
        'customer_email' => get_post_meta($validated_entry_id, '_waitlist_customer_email', true),
        'customer_name' => get_post_meta($validated_entry_id, '_waitlist_customer_name', true),
        'expires' => time() + 3600, // 1 hour to complete checkout
    ], 3600);
}
```

#### 2. Display Reserved Spot Banner
Add to workshop page template or via shortcode filter:

```php
function show_waitlist_claim_banner() {
    $claim_data = get_transient('waitlist_claim_' . get_current_user_id());
    
    if (!$claim_data) {
        return;
    }
    
    ?>
    <div class="waitlist-claim-banner" style="background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h3 style="color: #155724; margin-top: 0;">🎉 Your Spot is Reserved!</h3>
        <p style="color: #155724;">
            Welcome back, <?php echo esc_html($claim_data['customer_name']); ?>! 
            This spot is reserved for you. Complete your enrollment below to secure it.
        </p>
        <p style="color: #666; font-size: 14px;">
            Your reservation expires in <?php echo human_time_diff($claim_data['expires']); ?>.
        </p>
    </div>
    <?php
}
```

#### 3. Pre-fill Enrollment Form
Modify `enrollment_button` shortcode to detect waitlist claim:

```php
public function render_enrollment_button($atts) {
    $claim_data = get_transient('waitlist_claim_' . get_current_user_id());
    
    $extra_data = '';
    if ($claim_data && $claim_data['workshop_id'] == $workshop_id) {
        $extra_data = sprintf(
            'data-waitlist-entry="%d" data-prefill-email="%s" data-prefill-name="%s"',
            $claim_data['entry_id'],
            esc_attr($claim_data['customer_email']),
            esc_attr($claim_data['customer_name'])
        );
    }
    
    // Add to button HTML and use in JS to pre-fill form
}
```

#### 4. Convert on Successful Checkout
In `WebhookHandler.php` after enrollment created:

```php
private function handle_checkout_completed($event) {
    // ... existing code ...
    
    // Check if this was a waitlist claim
    $metadata = $session['metadata'] ?? [];
    $waitlist_entry_id = $metadata['waitlist_entry_id'] ?? 0;
    
    if ($waitlist_entry_id) {
        $waitlist_cpt = new WaitlistCPT();
        $waitlist_cpt->convert_to_enrollment($waitlist_entry_id, $enrollment_id);
        
        // Clear the claim transient
        delete_transient('waitlist_claim_' . $user_id);
    }
}
```

#### 5. Pass Waitlist Data to Stripe
In `CartEndpoints.php` when creating Stripe session:

```php
public function checkout_cart() {
    $claim_data = get_transient('waitlist_claim_' . get_current_user_id());
    
    $metadata = [
        'is_cart' => 'true',
        'cart_data' => json_encode($cart_data),
    ];
    
    if ($claim_data) {
        $metadata['waitlist_entry_id'] = $claim_data['entry_id'];
    }
    
    // Pass to create_checkout_session
}
```

## Testing Checklist

- [ ] Refund an enrollment to trigger waitlist notification
- [ ] Check email includes magic link with token
- [ ] Click magic link - should see "reserved" banner
- [ ] Complete checkout - should convert waitlist entry
- [ ] Try using expired token (manually set expiration in past)
- [ ] Try using token after already converted
- [ ] Verify transient clears after checkout

## Security Considerations

✅ Tokens are 64 characters (secure random)
✅ Tokens expire after 48 hours
✅ One-time use (status changes prevent reuse)
✅ Validated before allowing enrollment
✅ Stored in transient (automatically expires)

## Next Steps

1. Create `WaitlistClaimHandler.php` class
2. Register in `EnrollmentSystem.php`
3. Add banner display function
4. Update enrollment form to detect and use claim data
5. Update Stripe metadata to include waitlist_entry_id
6. Update webhook to convert on success
7. Test full flow end-to-end

