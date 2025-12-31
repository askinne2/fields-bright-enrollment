---
layout: default
title: Getting Started
description: Learn how to set up and start using the Fields Bright Enrollment System for your workshops.
---

This guide will walk you through everything you need to know to start using your workshop enrollment system. By the end, you'll have created your first workshop and be ready to accept enrollments!

## What You'll Learn

- How to access the enrollment system in WordPress
- Setting up your Stripe payment connection
- Creating your first workshop
- Adding the enrollment form to your website

---

## Step 1: Accessing the Enrollment System

The enrollment system is built into your WordPress website. Here's how to find it:

<ol class="steps">
<li>
<strong>Log into WordPress</strong><br>
Go to your website's admin area (Login URL <a href="https://fields-bright.com/21ads-login" target="blank">fields-bright.com/21ads-login</a>) and sign in with your administrator account.
</li>

<li>
<strong>Find the Enrollment Menu</strong><br>
In the left sidebar, look for <strong>Enrollments</strong>. These are where you'll manage everything.
</li>

<li>
<strong>Explore the Dashboard</strong><br>
Click on <strong>Workshops</strong> to see any existing workshops, or create new ones.
</li>
</ol>

<div class="tip">
If you don't see the Workshops or Enrollments menus, contact your website administrator to check your user permissions.
</div>

---

## Step 2: Understanding Stripe (Your Payment Processor)

Before customers can pay for workshops, you need to have Stripe connected. Stripe is the secure payment service that handles all credit card transactions.

### What is Stripe?

Stripe is like a digital cash register for your website. When someone enrolls in a workshop:

1. They enter their payment information on a secure Stripe page
2. Stripe processes the payment
3. The money goes to your bank account (usually within 2-3 business days)
4. The customer gets a receipt, and you get notified

### Checking Your Stripe Connection

Your Stripe account should already be connected by your website administrator. To verify:

1. Go to **Settings → Fields Bright Enrollment** in WordPress
2. Look for the **Stripe Connection** section
3. You should see a green checkmark or "Connected" status

<div class="warning">
If Stripe shows as "Not Connected" or you see error messages, do not try to accept payments. Contact your website administrator to fix the connection first.
</div>

---

## Step 3: Creating Your First Workshop

Now for the fun part! Let's create a workshop that people can enroll in.

<ol class="steps">
<li>
<strong>Go to Workshops → Add New</strong><br>
Click "Add New" to start creating your workshop.
</li>

<li>
<strong>Enter the Workshop Title</strong><br>
Give your workshop a clear, descriptive name. This is what customers will see.<br>
<em>Example: "Beginner Pottery Class - February 2024"</em>
</li>

<li>
<strong>Write a Description</strong><br>
Use the main editor to describe what the workshop includes, what participants will learn, what to bring, etc. Make it inviting!
</li>

<li>
<strong>Set the Date and Time</strong><br>
Scroll down to find the <strong>Workshop Details</strong> section. Fill in:
<ul>
<li><strong>Start Date/Time</strong> - When the workshop begins</li>
<li><strong>End Date/Time</strong> - When it ends</li>
</ul>
</li>

<li>
<strong>Set the Location</strong><br>
Enter the address or location name where the workshop will be held.
</li>

<li>
<strong>Configure Pricing</strong><br>
In the <strong>Pricing Options</strong> section:
<ul>
<li><strong>Price</strong> - The amount to charge (e.g., $75.00)</li>
<li><strong>Early Bird Price</strong> (optional) - A discounted price for early registrations</li>
</ul>
</li>

<li>
<strong>Set Capacity</strong><br>
Enter the <strong>Maximum Capacity</strong> - how many people can enroll before the workshop is full.
</li>

<li>
<strong>Publish!</strong><br>
When everything looks good, click the blue <strong>Publish</strong> button.
</li>
</ol>

<div class="note">
You can always come back and edit a workshop later. Unpublish it first if you need to make major changes.
</div>


---

## Step 4: Test Your First Enrollment

Before announcing your workshop, test the enrollment process yourself:

<ol class="steps">
<li>
<strong>View Your Workshop</strong><br>
Go to the workshop page on your website (not in the admin area).
</li>

<li>
<strong>Click Enroll</strong><br>
Go through the enrollment process as if you were a customer.
</li>

<li>
<strong>Use Stripe Test Mode</strong><br>
If Stripe is in test mode, use these test card numbers:
<ul>
<li><strong>Card that works:</strong> 4242 4242 4242 4242</li>
<li>Use any future date for expiry and any 3 digits for CVC</li>
</ul>
</li>

<li>
<strong>Complete the Enrollment</strong><br>
Fill in your information and complete the payment.
</li>

<li>
<strong>Check the Results</strong><br>
You should:
<ul>
<li>Receive a confirmation email</li>
<li>See the enrollment in <strong>Enrollments</strong> in WordPress</li>
<li>See the available spots decrease on the workshop</li>
</ul>
</li>
</ol>

<div class="important">
Remember to switch Stripe from "Test Mode" to "Live Mode" before accepting real payments! Your website administrator can help with this.
</div>

---

## You're Ready!

Congratulations! You now know how to:

- ✅ Access the enrollment system
- ✅ Create workshops
- ✅ Add them to your website
- ✅ Test the enrollment process

### What's Next?

- [Managing Workshops]({{ '/guides/managing-workshops' | relative_url }}) - Learn about editing, duplicating, and organizing workshops
- [Managing Enrollments]({{ '/guides/managing-enrollments' | relative_url }}) - See who's enrolled and manage participants
- [Email Templates]({{ '/guides/email-templates' | relative_url }}) - Customize your confirmation emails

