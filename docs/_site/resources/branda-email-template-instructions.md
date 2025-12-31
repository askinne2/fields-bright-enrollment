# Branda Email Template Installation Instructions

## Overview

This document provides instructions for installing the Fields Bright custom email template into the Branda (Ultimate Branding) Email Template module.

## Template File

The template HTML file is located at:
```
docs/branda-email-template.html
```

## Installation Steps

### Method 1: Via Branda Admin Interface (Recommended)

1. **Access Branda Email Templates**
   - Log into your WordPress admin dashboard
   - Navigate to: **Branding** → **Emails** → **Email Templates**

2. **Create New Custom Template**
   - Click on **"Add New Template"** or **"Custom Template"**
   - Name it: **"Fields Bright Enrollment"**

3. **Upload Template HTML**
   - Copy the contents of `branda-email-template.html`
   - Paste into the template HTML editor
   - Save the template

4. **Set as Default (Optional)**
   - Go to template settings
   - Set **"Fields Bright Enrollment"** as the default template for all emails
   - Or set it specifically for enrollment-related emails

### Method 2: Direct File Installation

1. **Locate Branda Templates Directory**
   ```
   wp-content/plugins/ultimate-branding/inc/modules/emails/templates/
   ```

2. **Create Custom Template Folder**
   ```bash
   mkdir wp-content/plugins/ultimate-branding/inc/modules/emails/templates/fields-bright
   ```

3. **Copy Template File**
   ```bash
   cp docs/branda-email-template.html wp-content/plugins/ultimate-branding/inc/modules/emails/templates/fields-bright/template.html
   ```

4. **Register in Branda**
   - The template should now appear in Branda's template selection
   - Activate it through the Branda admin interface

## Template Variables

The template uses the following Branda variables:

### Standard Branda Variables
- `{{site_name}}` - Your website name
- `{{site_url}}` - Your website URL  
- `{{admin_email}}` - Admin email address
- `{{current_year}}` - Current year
- `{{subject}}` - Email subject line
- `{{email_heading}}` - Main email heading
- `{{email_content}}` - The main email content (your custom templates)

### Custom Email Variables
Your enrollment email templates will populate the `{{email_content}}` variable with their specific content.

## Customization

### Colors

The template uses the following color scheme:

- **Primary Dark**: `#271C1A` (Headers/Footer background)
- **Accent Yellow**: `#F9DB5E` (Highlights/CTAs)
- **Text**: `#333333` (Main content)
- **Light Grey**: `#f9f9f9` (Content background)
- **Medium Grey**: `#666666` (Secondary text)

To customize colors:
1. Open `branda-email-template.html`
2. Find and replace color codes
3. Re-upload to Branda

### Logo

To add your logo:

1. Locate this section in the template:
```html
<!-- Logo (if you have one) -->
<div style="margin-bottom: 20px;">
    <span style="font-size: 32px; font-weight: bold; color: #F9DB5E; letter-spacing: 2px;">
        {{site_name}}
    </span>
</div>
```

2. Replace with:
```html
<div style="margin-bottom: 20px;">
    <img src="YOUR_LOGO_URL" alt="{{site_name}}" style="max-width: 200px; height: auto;">
</div>
```

### Social Media Links

To add social media icons:

1. Find the Social Links section:
```html
<!-- Social Links (Optional - customize as needed) -->
<div style="margin: 15px 0;">
```

2. Add your social links:
```html
<td style="padding: 0 8px;">
    <a href="https://facebook.com/yourpage" style="color: #F9DB5E; font-size: 20px;">
        📘
    </a>
</td>
<td style="padding: 0 8px;">
    <a href="https://instagram.com/yourpage" style="color: #F9DB5E; font-size: 20px;">
        📷
    </a>
</td>
```

Or use icon images:
```html
<td style="padding: 0 8px;">
    <a href="https://facebook.com/yourpage">
        <img src="path/to/facebook-icon.png" alt="Facebook" width="24" height="24" style="display: block;">
    </a>
</td>
```

## Testing

After installation:

1. **Send Test Email**
   - In Branda Email Templates settings
   - Click **"Send Test Email"**
   - Enter your email address
   - Verify template renders correctly

2. **Test with Enrollment**
   - Create a test workshop
   - Complete a test enrollment
   - Check the confirmation email formatting

3. **Mobile Testing**
   - Forward test email to mobile device
   - Verify responsive design works correctly
   - Check all buttons and links are clickable

## Integration with Enrollment System

The enrollment system will automatically use this Branda template if:

1. Branda Email Template module is active
2. Template is set as default or selected for enrollment emails
3. The filter `fields_bright_use_branda_templates` returns `true` (default)

To disable Branda integration for specific emails:

```php
add_filter('fields_bright_use_branda_templates', function($use_branda) {
    // Disable Branda for all enrollment emails
    return false;
    
    // Or conditionally:
    // return !is_admin(); // Only use Branda for frontend emails
});
```

## Troubleshooting

### Template Not Showing

1. Clear WordPress cache
2. Check Branda is activated
3. Verify template file is in correct location
4. Check file permissions (should be 644)

### Variables Not Rendering

1. Ensure you're using Branda's variable syntax: `{{variable_name}}`
2. Check Branda settings for available variables
3. Test with Branda's test email feature

### Styling Issues

1. Ensure all CSS is inline (no external stylesheets)
2. Test in multiple email clients (Gmail, Outlook, Apple Mail)
3. Use [Litmus](https://litmus.com) or [Email on Acid](https://www.emailonacid.com) for cross-client testing

## Support

For issues with:
- **Branda Template Module**: Contact WPMU DEV support
- **Enrollment System Integration**: Check enrollment system documentation
- **Custom Template Design**: Refer to HTML email best practices

## References

- [Branda Email Templates Documentation](https://wpmudev.com/docs/wpmu-dev-plugins/branda/#email-templates)
- [HTML Email Best Practices](https://www.campaignmonitor.com/css/)
- [Email Client CSS Support](https://www.caniemail.com/)

