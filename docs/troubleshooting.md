---
layout: default
title: Troubleshooting
description: Find solutions to common issues with the Fields Bright Enrollment System.
---

# Troubleshooting

Having an issue? This guide covers the most common problems and how to solve them.

## Quick Diagnosis

**Before diving into specific issues, try these steps:**

1. **Refresh the page** (Ctrl+F5 or Cmd+Shift+R)
2. **Clear your browser cache**
3. **Try a different browser**
4. **Log out and back in** to WordPress

If the problem persists, find your issue below.

---

## Enrollment Issues

### Customer says payment went through but they're not enrolled

**Why this happens:** The payment processed, but the confirmation didn't complete (often due to closing the browser too soon).

**How to fix:**

1. Check your Stripe dashboard for the payment
2. If the payment exists:
   - Find the enrollment in WordPress (it might be "Pending")
   - Update the status to "Completed"
   - Manually send them a confirmation email

<div class="tip">
To prevent this, remind customers to wait for the confirmation page to load completely.
</div>

---

### Customer sees "Payment Failed" error

**Common causes:**

| Cause | Solution |
|-------|----------|
| Insufficient funds | Customer needs to try a different card |
| Card declined by bank | Customer should contact their bank |
| Incorrect card details | Double-check numbers, expiry, CVC |
| 3D Secure failed | Customer needs to complete bank verification |

**What to tell the customer:**

"Your bank declined the payment. Please try a different payment method or contact your bank to authorize the transaction, then try again."

---

### Customer enrolled twice (duplicate enrollment)

**Why this happens:** Customer clicked "Pay" multiple times, or refreshed during processing.

**How to fix:**

1. Check if both enrollments have payments in Stripe
2. If only one payment exists:
   - Delete the enrollment without a payment
3. If two payments exist:
   - Refund one of the payments
   - Delete one enrollment

<div class="warning">
Always verify in Stripe before deleting. You don't want to leave a paid customer without an enrollment!
</div>

---

### "Workshop is full" but the count seems wrong

**Why this happens:** A "Pending" enrollment might be holding a spot.

**How to check:**

1. Go to the workshop's enrollments
2. Look for any "Pending" status enrollments
3. If they're old (more than an hour) and no payment in Stripe, delete them

**How to prevent:**

Pending enrollments that don't complete should automatically expire. If this isn't happening, contact your developer.

---

## Payment Issues

### I can't see the payment in Stripe

**Check these things:**

1. **Test vs. Live mode** - Are you looking at the right mode in Stripe?
2. **Correct account** - Do you have multiple Stripe accounts?
3. **Date filter** - Stripe defaults to recent payments; expand the date range
4. **Search properly** - Try searching by email address instead of name

---

### Refund won't process

**Common causes and solutions:**

| Error Message | Solution |
|---------------|----------|
| "Original payment not found" | Payment too old or already refunded |
| "Insufficient funds" | Your Stripe balance is too low |
| "Card expired" | Can't refund to expired cards; contact Stripe support |

---

### Customer charged but status shows "Pending"

**Why this happens:** Stripe webhook didn't reach your site.

**How to fix:**

1. Verify payment in Stripe dashboard
2. Manually update enrollment status to "Completed"
3. Send customer their confirmation

**To prevent recurring issues:**
Contact your developer to check webhook configuration.

---

## Workshop Display Issues

### Workshop isn't showing on the website

**Check these things:**

1. **Is it published?** Draft workshops aren't visible
2. **Has the date passed?** Past workshops may be hidden
3. **Is enrollment enabled?** Check the workshop settings
4. **Cache issue?** Clear your website cache

**How to test:**
Log out and view the page as a regular visitor.

---

### Wrong price is displaying

**Possible causes:**

1. **Browser cache** - Try a hard refresh
2. **Multiple pricing options** - Is the correct default selected?
3. **Site cache** - Clear any caching plugin's cache

---

### "Sold Out" showing but spots are available

**Check:**

1. Actual enrollment count vs. capacity
2. Are there pending enrollments holding spots?
3. Clear cache and refresh

---

## Email Issues

### Customers aren't receiving confirmation emails

**Most common causes:**

1. **Spam folder** - Ask them to check spam/junk
2. **Email deliverability issues** - See below
3. **Wrong email address** - Typos happen

**Improving email deliverability:**

- Use a professional sending address
- Set up proper email authentication (ask your developer)
- Consider using an SMTP plugin
- Avoid spam-trigger words

<div class="note">
Gmail users: Check the "Promotions" and "Updates" tabs, not just the primary inbox.
</div>

---

### Emails going to spam

**Quick fixes:**

1. Ask recipients to mark your email as "Not Spam"
2. Have them add your email to their contacts
3. Avoid spam-trigger words in subject lines

**Long-term fixes (need developer help):**

- Set up SPF records
- Configure DKIM authentication
- Implement DMARC
- Use a dedicated email sending service

---

### Placeholders showing instead of actual data

**Example:** Email shows `{customer_name}` instead of "John Smith"

**Fixes:**

1. Check placeholder spelling exactly
2. Save and re-save the email template
3. Contact developer if it persists

---

## Admin Access Issues

### "You do not have permission" error

**Possible causes:**

1. **Wrong user role** - You need Administrator or Editor role
2. **Session expired** - Log out and back in
3. **Plugin conflict** - Ask developer to check

---

### Workshop or Enrollment menus missing

**Check:**

1. Are you logged in as an Administrator?
2. Is the enrollment system plugin activated?
3. Check with your developer

---

## Performance Issues

### Pages loading slowly

**Common causes:**

1. **Too many workshops loading** - Archive pages with many workshops
2. **Large images** - Optimize workshop images
3. **Server issues** - Contact your host

**Quick fixes:**

1. Reduce number of workshops per page
2. Compress images before uploading
3. Clear caching plugins

---

### Admin area is slow

**Try:**

1. Disable other plugins temporarily to test
2. Check for plugin updates
3. Contact your host about server performance

---

## Error Messages Explained

### "Invalid nonce"

**What it means:** Your session expired or there's a security mismatch.

**Fix:** Refresh the page and try again. If it persists, log out and back in.

---

### "Workshop not found"

**What it means:** The workshop ID doesn't exist or was deleted.

**Check:** Was the workshop accidentally deleted? Check trash.

---

### "Stripe connection error"

**What it means:** Can't connect to Stripe.

**Causes:**
- Stripe is down (rare)
- API keys are wrong
- Server can't reach Stripe

**Fix:** Contact your developer to verify Stripe configuration.

---

### "Database error"

**What it means:** Something went wrong saving or reading data.

**First steps:**
1. Refresh and try again
2. If persistent, note the exact error message
3. Contact your developer with the details

---

## Getting More Help

### Information to Gather

Before contacting support, collect:

1. **What you were trying to do**
2. **What happened instead**
3. **Any error messages** (exact wording or screenshot)
4. **When it started happening**
5. **Does it affect all workshops or just one?**
6. **Browser and device you're using**

### Where to Get Help

1. **This documentation** - Search for your specific issue
2. **Your website administrator** - For technical problems
3. **Developer support** - For bugs or feature issues

---

## Frequently Asked Questions

**Q: How do I test the system without real payments?**

A: Ask your developer to enable Stripe "Test Mode." Then use test card number: 4242 4242 4242 4242

**Q: Can I edit an enrollment after it's created?**

A: Yes, you can change most fields. Be careful changing status - it doesn't automatically process refunds.

**Q: What happens to enrollments if I delete a workshop?**

A: Enrollments stay in the system but become "orphaned." It's better to unpublish a workshop rather than delete it.

**Q: How do I export all my data?**

A: Go to Enrollments → Export. You can filter by date range, workshop, or status.

**Q: Can customers edit their own enrollment?**

A: Not directly. They need to contact you to make changes.

---

## Still Stuck?

If you've tried the solutions above and still have issues:

1. **Document the problem** - Screenshots, error messages, steps to reproduce
2. **Note what you've already tried**
3. **Contact your website administrator** with all this information

Most issues can be resolved quickly with the right information!

