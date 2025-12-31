---
layout: default
title: Managing Enrollments
description: Learn how to view, manage, and export enrollment data in the Fields Bright Enrollment System.
---

# Managing Enrollments

Once people start enrolling in your workshops, you'll need to manage their information. This guide shows you how to view enrollments, update statuses, and keep track of who's attending.

## Viewing All Enrollments

To see everyone who has enrolled:

1. Log into WordPress
2. Click **Enrollments** in the left sidebar
3. You'll see a list of all enrollments across all workshops

### Understanding the Enrollment List

| Column | What It Means |
|--------|---------------|
| **Name** | The participant's name |
| **Workshop** | Which workshop they enrolled in |
| **Status** | Current enrollment status (see below) |
| **Date** | When they enrolled |
| **Amount** | How much they paid |

---

## Enrollment Statuses

Every enrollment has a status that tells you where it is in the process:

| Status | What It Means | What You Should Do |
|--------|---------------|-------------------|
| **Pending** | Started enrollment but hasn't paid | Wait for payment or follow up |
| **Completed** | Paid and confirmed | They're ready to attend! |
| **Cancelled** | Enrollment was cancelled | Check if refund was processed |
| **Refunded** | Full refund was issued | No further action needed |

<div class="tip">
You can filter the enrollment list by status using the dropdown at the top of the page.
</div>

---

## Viewing a Single Enrollment

To see all details for one enrollment:

1. Click on the participant's name in the list
2. You'll see their full enrollment record

### Enrollment Details Include:

**Participant Information:**
- Full name
- Email address
- Phone number (if collected)

**Workshop Details:**
- Which workshop they enrolled in
- The date and time
- Which pricing option they selected

**Payment Information:**
- Amount paid
- Payment date
- Stripe transaction ID
- Payment status

<div class="note">
The Stripe transaction ID is useful if you need to look up the payment in your Stripe dashboard.
</div>

---

## Finding Enrollments for a Specific Workshop

To see who's enrolled in a particular workshop:

### Method 1: Filter the List
1. Go to **Enrollments**
2. Use the **Workshop** filter dropdown
3. Select the workshop you want to see

### Method 2: From the Workshop
1. Go to **Workshops** and click on the workshop
2. Scroll down to the **Enrollments** section
3. You'll see everyone enrolled in that workshop

---

## Changing Enrollment Status

Sometimes you need to manually update an enrollment's status:

<ol class="steps">
<li>
<strong>Find the Enrollment</strong><br>
Go to <strong>Enrollments</strong> and click on the enrollment you need to update.
</li>

<li>
<strong>Find the Status Field</strong><br>
Look for the <strong>Enrollment Status</strong> dropdown in the enrollment details.
</li>

<li>
<strong>Select the New Status</strong><br>
Choose from: Pending, Completed, Cancelled, or Refunded.
</li>

<li>
<strong>Update the Record</strong><br>
Click <strong>Update</strong> to save your changes.
</li>
</ol>

<div class="warning">
<strong>Changing to "Cancelled" doesn't automatically refund the payment.</strong> If the customer paid, you'll need to process a refund separately. See <a href="{{ '/processing-refunds' | relative_url }}">Processing Refunds</a>.
</div>

---

## Exporting Enrollment Data

You can download enrollment information for:

- Creating attendance sheets
- Importing into other systems
- Keeping records
- Sharing with instructors

### How to Export

<ol class="steps">
<li>
<strong>Go to Enrollments</strong>
</li>

<li>
<strong>Set Your Filters</strong><br>
Filter by workshop, date range, or status to export only what you need.
</li>

<li>
<strong>Click Export</strong><br>
Click the <strong>Export</strong> button at the top of the page.
</li>

<li>
<strong>Choose Your Format</strong>
<ul>
<li><strong>CSV</strong> - Opens in Excel, Google Sheets, etc.</li>
<li><strong>PDF</strong> - Ready to print</li>
</ul>
</li>
</ol>

### What's Included in Exports

The export includes:

- Participant name and contact info
- Workshop name and date
- Enrollment status
- Amount paid
- Enrollment date

<div class="tip">
Export a list the day before your workshop to have a printed attendance sheet ready!
</div>

---

## Handling Common Situations

### "I need to add someone manually"

If someone paid in person or needs to be added manually:

1. Go to **Enrollments → Add New**
2. Select the workshop
3. Enter the participant's information
4. Set the status (usually "Completed" for in-person payments)
5. Note the payment method in the notes field
6. Click **Publish**

<div class="note">
Manual enrollments don't process through Stripe, so no payment will be collected online.
</div>

### "Someone says they enrolled but I don't see them"

Check these things:

1. **Check all statuses** - They might be "Pending" if payment didn't complete
2. **Search by email** - Use the search box with their email address
3. **Check the right workshop** - Make sure you're looking at the correct one
4. **Check their spam folder** - Their confirmation email might be there
5. **Ask for details** - Ask them when they enrolled and what email they used

### "The enrollment shows Pending but they say they paid"

This can happen if:

- Payment was processing when they closed the browser
- Their bank flagged the payment
- The webhook didn't arrive (technical issue)

**What to do:**

1. Check your Stripe dashboard for the payment
2. If payment is there, manually update the status to "Completed"
3. If no payment, ask them to try enrolling again

### "I need to move someone to a different workshop"

The easiest approach:

1. Cancel their current enrollment (refund if needed)
2. Have them enroll in the new workshop
3. Or manually create an enrollment in the new workshop

---

## Communicating with Participants

### Emailing Individual Participants

From an enrollment record:

1. Click on their email address
2. Your default email app will open
3. Write your message and send

### Emailing All Participants in a Workshop

Currently, you'll need to:

1. Export the workshop enrollments
2. Open the CSV in Excel/Sheets
3. Copy the email addresses
4. Use your email program to send to all

<div class="tip">
Consider using the BCC field when emailing multiple participants to protect everyone's privacy.
</div>

---

## Understanding Payment Information

Each completed enrollment shows:

**Amount Paid**
- The total amount charged to the customer

**Stripe Payment ID**
- A unique identifier for the transaction
- Use this if you need to find it in Stripe's dashboard

**Payment Date**
- When the payment was processed

### Finding a Payment in Stripe

1. Copy the Stripe Payment ID from the enrollment
2. Log into your [Stripe Dashboard](https://dashboard.stripe.com)
3. Go to Payments
4. Paste the ID in the search box

---

## Enrollment Reports

### Quick Stats

At the top of the Enrollments page, you'll see:

- **Total Enrollments** - All-time enrollment count
- **This Month** - Enrollments this month
- **Revenue** - Total revenue from enrollments

### Viewing Revenue by Workshop

On each workshop's edit page, you can see:

- Total revenue for that workshop
- Number of enrollments
- Capacity vs. filled spots

---

## Privacy and Data

### What Data is Collected

When someone enrolls, we collect:

- Name
- Email address
- Phone (if required)
- Payment information (stored securely by Stripe, not on your site)

### Handling Data Requests

If a customer asks what data you have:

1. Search for their enrollments
2. Export their enrollment records
3. Provide them the information

If they ask you to delete their data:

1. Export their records first (for your records)
2. Delete their enrollment records
3. Note: Some data may need to be kept for tax/legal purposes

---

## Next Steps

- [Processing Refunds]({{ '/processing-refunds' | relative_url }}) - Handle cancellations and refunds
- [Email Templates]({{ '/email-templates' | relative_url }}) - Customize confirmation emails
- [Troubleshooting]({{ '/troubleshooting' | relative_url }}) - Solve common problems

