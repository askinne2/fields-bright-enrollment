---
layout: default
title: Email Templates
description: Learn how to customize the automatic emails sent by the Fields Bright Enrollment System.
---

# Email Templates

Your enrollment system automatically sends emails to customers at key moments. This guide shows you what emails are sent, when they go out, and how to customize them.

## Emails That Are Sent Automatically

The system sends these emails without you having to do anything:

| Email | When It's Sent | Who Receives It |
|-------|----------------|-----------------|
| **Enrollment Confirmation** | After successful payment | Customer |
| **Waitlist Confirmation** | When joining waitlist | Customer |
| **Spot Available** | When a waitlist spot opens | Waitlist customer |
| **Refund Confirmation** | After you process a refund | Customer |
| **Admin Notification** | After each enrollment | You (the admin) |

---

## Enrollment Confirmation Email

This is the most important email - it confirms the customer's registration.

### What's Included

- ✅ Workshop name
- ✅ Date and time
- ✅ Location/address
- ✅ Amount paid
- ✅ Receipt/invoice link
- ✅ Your contact information

### When It's Sent

Immediately after the payment is successfully processed.

<div class="tip">
This email serves as the customer's "ticket" - they may save or print it to bring to your workshop!
</div>

---

## Customizing Email Content

To customize your emails:

<ol class="steps">
<li>
<strong>Go to Settings</strong><br>
Navigate to <strong>Settings → Fields Bright Enrollment</strong>.
</li>

<li>
<strong>Find Email Settings</strong><br>
Look for the <strong>Email Templates</strong> tab.
</li>

<li>
<strong>Select an Email to Edit</strong><br>
Choose which email template you want to customize.
</li>

<li>
<strong>Make Your Changes</strong><br>
Edit the subject line and content.
</li>

<li>
<strong>Save Changes</strong><br>
Click <strong>Save</strong> when done.
</li>
</ol>

---

## Using Placeholders

Placeholders are special codes that automatically insert information. Use these in your email templates:

### Customer Placeholders

| Placeholder | What It Inserts |
|-------------|-----------------|
| `{customer_name}` | Customer's full name |
| `{customer_first_name}` | Customer's first name |
| `{customer_email}` | Customer's email address |

### Workshop Placeholders

| Placeholder | What It Inserts |
|-------------|-----------------|
| `{workshop_title}` | Name of the workshop |
| `{workshop_date}` | Workshop date |
| `{workshop_time}` | Start time |
| `{workshop_location}` | Location/address |

### Payment Placeholders

| Placeholder | What It Inserts |
|-------------|-----------------|
| `{amount_paid}` | Total amount charged |
| `{receipt_url}` | Link to Stripe receipt |

### Other Placeholders

| Placeholder | What It Inserts |
|-------------|-----------------|
| `{site_name}` | Your website name |
| `{admin_email}` | Your admin email address |

---

## Example Email Templates

### Enrollment Confirmation Template

**Subject:** You're Enrolled! {workshop_title} Confirmation

**Body:**
```
Hi {customer_first_name},

Great news! You're all set for {workshop_title}.

📅 WORKSHOP DETAILS
Date: {workshop_date}
Time: {workshop_time}
Location: {workshop_location}

💳 PAYMENT CONFIRMED
Amount: {amount_paid}
View Receipt: {receipt_url}

📝 WHAT TO BRING
[Add your specific instructions here]

❓ QUESTIONS?
Reply to this email or contact us at {admin_email}.

We can't wait to see you there!

{site_name}
```

### Waitlist Confirmation Template

**Subject:** You're on the Waitlist for {workshop_title}

**Body:**
```
Hi {customer_first_name},

You've been added to the waitlist for {workshop_title}.

📅 Workshop Date: {workshop_date}

WHAT HAPPENS NEXT
If a spot opens up, you'll receive an email right away 
with a link to complete your registration.

Spots are offered in the order people joined the waitlist, 
so you'll have 48 hours to claim your spot before it goes 
to the next person.

Questions? Reply to this email.

{site_name}
```

### Spot Available Template

**Subject:** 🎉 A Spot Opened Up! {workshop_title}

**Body:**
```
Hi {customer_first_name},

Good news! A spot just opened up for {workshop_title}!

📅 Date: {workshop_date}
🕐 Time: {workshop_time}
📍 Location: {workshop_location}

⏰ CLAIM YOUR SPOT NOW
Click here to complete your registration: {claim_link}

IMPORTANT: This offer expires in 48 hours. If you don't 
claim your spot by then, it will be offered to the next 
person on the waitlist.

Questions? Reply to this email.

{site_name}
```

---

## Best Practices for Emails

### Keep It Clear and Scannable

- Use short paragraphs
- Use bullet points or lists
- Bold important information
- Include relevant emojis sparingly

### Include All Essential Information

Your confirmation email should answer:

- What did they sign up for?
- When and where is it?
- How much did they pay?
- How can they contact you?

### Make Important Info Easy to Find

People skim emails. Put the most important details:

- Near the top
- In bold or larger text
- In easy-to-scan format

### Test Your Emails

After making changes:

1. Send yourself a test email
2. Check on desktop AND mobile
3. Make sure placeholders are replaced correctly
4. Click all links to verify they work

<div class="tip">
Send a test enrollment to yourself periodically to make sure everything still looks right!
</div>

---

## Sender Information

### Email "From" Address

Emails are sent from your WordPress admin email. To change this:

1. Go to **Settings → General**
2. Update the **Admin Email Address**

<div class="warning">
Make sure you can receive email at this address - customers may reply to it!
</div>

### Email "From" Name

The sender name is your site name. To change:

1. Go to **Settings → General**
2. Update the **Site Title**

---

## Troubleshooting Email Problems

### Customers Say They Didn't Get the Email

Ask them to:

1. **Check spam/junk folder** - Most common issue!
2. **Check promotions tab** (Gmail) - Often lands there
3. **Search for the subject line** - It might be in an unexpected folder
4. **Verify their email** - Did they type it correctly?

### Emails Are Going to Spam

To improve deliverability:

- Use a professional "from" address (not @gmail.com)
- Avoid spam-trigger words in subject lines
- Don't use ALL CAPS
- Include a way to contact you
- Consider using an SMTP plugin for better delivery

<div class="note">
If email delivery is consistently problematic, ask your website administrator about setting up proper email authentication (SPF, DKIM records).
</div>

### Placeholders Aren't Being Replaced

If you see `{workshop_title}` instead of the actual title:

1. Check you used the exact placeholder format
2. Make sure there are no extra spaces
3. Save the template and try again
4. Contact support if it persists

---

## Admin Notification Emails

You receive an email each time someone enrolls. This includes:

- Customer name and email
- Workshop they enrolled in
- Amount paid
- Quick link to view the enrollment

### Managing Admin Notifications

To change who receives admin notifications:

1. Go to **Settings → Fields Bright Enrollment**
2. Find **Admin Notifications**
3. Enter the email address(es) to receive notifications
4. Separate multiple addresses with commas

---

## Common Questions

**Q: Can I add attachments to emails?**

A: Not directly through the template system. Consider including links to documents hosted on your website instead.

**Q: Can I send custom emails to specific enrollees?**

A: The template system is for automatic emails. For one-off messages, you can email customers directly from their enrollment record.

**Q: Can I add my logo to emails?**

A: Basic templates are text-based for best deliverability. Advanced customization may require developer assistance.

**Q: Can I disable certain emails?**

A: The confirmation email cannot be disabled (customers need it!). Other notifications may have enable/disable options in settings.

---

## Next Steps

- [Managing Enrollments]({{ '/guides/managing-enrollments' | relative_url }}) - See enrollment details
- [Processing Refunds]({{ '/guides/processing-refunds' | relative_url }}) - Refund confirmation emails
- [Troubleshooting]({{ '/guides/troubleshooting' | relative_url }}) - Solve common problems

