---
layout: default
title: Processing Refunds
description: Learn how to handle cancellations and process refunds in the Fields Bright Enrollment System.
---

# Processing Refunds

Sometimes participants need to cancel their enrollment. This guide shows you how to handle cancellations professionally and process refunds through the system.

## Understanding Refunds

When you issue a refund:

1. **Money goes back** to the customer's original payment method
2. **Enrollment status** changes to "Refunded" or "Cancelled"
3. **The spot** becomes available again for someone else
4. **Customer receives** an email confirmation

<div class="note">
Refunds typically take 5-10 business days to appear on the customer's statement, depending on their bank.
</div>

---

## Before Processing a Refund

### Consider Your Refund Policy

Before processing any refund, consider:

- How far in advance is the cancellation?
- What's your stated refund policy?
- Are there extenuating circumstances?
- Would a credit/transfer to another workshop work better?

<div class="tip">
Having a clear refund policy on your website prevents misunderstandings. Link to it from your workshop pages!
</div>

### Types of Refunds

You can issue:

| Type | When to Use |
|------|-------------|
| **Full Refund** | Customer cancels well in advance |
| **Partial Refund** | Late cancellation or after deducting a fee |
| **No Refund** | Per your policy (but still cancel their enrollment) |

---

## How to Process a Full Refund

<ol class="steps">
<li>
<strong>Find the Enrollment</strong><br>
Go to <strong>Enrollments</strong> and find the person who wants a refund. Click on their name.
</li>

<li>
<strong>Review the Details</strong><br>
Make sure you have the right enrollment:
<ul>
<li>Correct person?</li>
<li>Correct workshop?</li>
<li>Status shows "Completed" (meaning they paid)?</li>
</ul>
</li>

<li>
<strong>Click the Refund Button</strong><br>
In the enrollment details, find and click the <strong>Process Refund</strong> button.
</li>

<li>
<strong>Confirm the Amount</strong><br>
The full payment amount will be shown. Confirm this is correct.
</li>

<li>
<strong>Add a Reason (Optional)</strong><br>
Add a note about why the refund was issued (for your records).
</li>

<li>
<strong>Click "Process Refund"</strong><br>
The refund will be submitted to Stripe.
</li>

<li>
<strong>Verify Success</strong><br>
You should see a success message. The enrollment status will update to "Refunded."
</li>
</ol>

<div class="important">
Once a refund is processed, it cannot be reversed. Double-check before clicking!
</div>

---

## How to Process a Partial Refund

For partial refunds (like keeping a cancellation fee):

<ol class="steps">
<li>
<strong>Find the Enrollment</strong><br>
Navigate to the enrollment you need to partially refund.
</li>

<li>
<strong>Click Process Refund</strong><br>
Open the refund dialog.
</li>

<li>
<strong>Change the Amount</strong><br>
Edit the refund amount to the partial amount. For example:
<ul>
<li>Original payment: $75.00</li>
<li>Keeping $20 cancellation fee</li>
<li>Refund amount: $55.00</li>
</ul>
</li>

<li>
<strong>Add a Note</strong><br>
Document the partial refund reason (e.g., "Refund minus $20 cancellation fee per policy").
</li>

<li>
<strong>Process the Refund</strong><br>
Click to confirm and process.
</li>
</ol>

<div class="note">
When you process a partial refund, the enrollment status will show as "Partially Refunded" with the amount noted.
</div>

---

## Cancelling Without a Refund

If you need to cancel someone's enrollment but they're not entitled to a refund:

<ol class="steps">
<li>
<strong>Find the Enrollment</strong>
</li>

<li>
<strong>Change the Status</strong><br>
Update the status from "Completed" to "Cancelled".
</li>

<li>
<strong>Add a Note</strong><br>
Document why no refund was issued.
</li>

<li>
<strong>Update the Record</strong><br>
Save your changes.
</li>
</ol>

<div class="warning">
Be sure to communicate clearly with the customer about why they're not receiving a refund. Reference your refund policy.
</div>

---

## What the Customer Sees

When you process a refund, the customer:

1. **Receives an email** confirming the cancellation
2. **Sees the refund** on their credit card statement (5-10 days)
3. **Statement shows** the refund from your business name

### Email Confirmation

The refund confirmation email includes:

- Workshop name
- Original amount paid
- Refund amount
- Approximate time to receive funds

---

## Handling Special Situations

### Customer Disputes the Charge with Their Bank

If a customer disputes (files a "chargeback") instead of asking you for a refund:

1. You'll receive a notification from Stripe
2. You have a limited time to respond
3. Provide evidence (enrollment confirmation, your refund policy, any communications)
4. Stripe makes the final decision

<div class="tip">
To prevent disputes, have a clear refund policy and respond quickly to cancellation requests. Most customers prefer asking you over filing a dispute.
</div>

### Refund Request After Workshop Already Happened

Generally:

- No-shows are not entitled to refunds
- If they contacted you before the workshop about not attending, consider your policy
- Document your decision and reasoning

### Refunding a Different Amount Than Paid

If you need to refund more than was paid (rare situations):

- You cannot refund more than the original payment through this system
- You would need to issue the difference through another method
- Document everything carefully

---

## Refund Timeline

Here's what happens after you process a refund:

| Day | What Happens |
|-----|--------------|
| Day 1 | Refund submitted to Stripe |
| Day 1-2 | Stripe processes the refund |
| Day 2-3 | Customer's bank receives the refund |
| Day 5-10 | Refund appears on customer's statement |

<div class="note">
Some banks are faster than others. If a customer hasn't received their refund after 10 business days, ask them to check with their bank.
</div>

---

## Keeping Records

### Why Documentation Matters

Good records protect you:

- Customer claims they didn't receive a refund
- Tax time / accounting needs
- Disputes with banks
- Learning patterns (frequent cancellations?)

### What to Document

For every refund, keep track of:

- ✅ Who requested the refund and when
- ✅ Why the refund was issued
- ✅ Full or partial amount
- ✅ Date processed
- ✅ Any communications with the customer

The enrollment system keeps this automatically, but adding notes helps with context.

---

## Viewing Refund History

### All Refunds

To see all refunds you've processed:

1. Go to **Enrollments**
2. Filter by status: **Refunded** or **Partially Refunded**
3. You'll see all refunded enrollments

### Refunds for a Specific Period

To see refunds for tax or accounting purposes:

1. Filter by date range
2. Filter by status: Refunded
3. Export the results

---

## Common Questions

**Q: Can I undo a refund?**

A: No, once processed, refunds cannot be reversed. If you need to re-charge the customer, they would need to enroll again.

**Q: The refund failed. What do I do?**

A: Check the error message. Common issues:
- Payment was made too long ago (some cards expire refund windows)
- Original payment was disputed/already refunded
- Contact Stripe support if the error persists

**Q: How long do I have to issue a refund?**

A: Generally, refunds can be issued within 90-180 days of the original payment. After that, the customer's bank may reject it.

**Q: Do I get my Stripe fees back when I refund?**

A: Stripe's policy varies by region. In the US, you typically do NOT get the original processing fee back, only the payment amount.

---

## Creating a Refund Policy

If you don't have a refund policy yet, consider:

**Example Policy:**

> **Cancellation Policy**
> - Full refund if cancelled 14+ days before workshop
> - 50% refund if cancelled 7-13 days before  
> - No refund if cancelled less than 7 days before
> - No-shows will not receive a refund
> - We reserve the right to cancel and fully refund any workshop

Post this clearly on your workshop pages and registration forms!

---

## Next Steps

- [Managing Enrollments]({{ '/guides/managing-enrollments' | relative_url }}) - Return to enrollment management
- [Email Templates]({{ '/guides/email-templates' | relative_url }}) - Customize your refund emails
- [Troubleshooting]({{ '/guides/troubleshooting' | relative_url }}) - Solve common problems

