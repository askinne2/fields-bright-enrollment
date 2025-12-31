---
layout: default
title: Managing Workshops
description: Learn how to create, edit, duplicate, and organize your workshops in the Fields Bright Enrollment System.
---

# Managing Workshops

This guide covers everything you need to know about creating and managing workshops. You'll learn how to set up various types of workshops, manage capacity, and handle special situations.

## Accessing Your Workshops

To see all your workshops:

1. Log into WordPress
2. Click **Workshops** in the left sidebar
3. You'll see a list of all workshops, organized by date

### Understanding the Workshop List

The workshop list shows you at a glance:

| Column | What It Means |
|--------|---------------|
| **Title** | The name of your workshop |
| **Date** | When the workshop takes place |
| **Capacity** | How many spots are filled (e.g., "12/15" means 12 enrolled out of 15 spots) |
| **Status** | Published, Draft, or Past |

<div class="tip">
Click on any column header to sort your workshops by that column. Click again to reverse the order.
</div>

---

## Creating a New Workshop

<ol class="steps">
<li>
<strong>Go to Workshops → Add New</strong>
</li>

<li>
<strong>Enter Basic Information</strong>
<ul>
<li><strong>Title</strong> - A clear, descriptive name</li>
<li><strong>Description</strong> - What participants will experience and learn</li>
<li><strong>Featured Image</strong> - An attractive photo to represent the workshop</li>
</ul>
</li>

<li>
<strong>Set Date and Time</strong><br>
Scroll to the <strong>Workshop Details</strong> section:
<ul>
<li><strong>Start Date/Time</strong> - When it begins</li>
<li><strong>End Date/Time</strong> - When it ends</li>
<li><strong>Recurring Info</strong> (optional) - For multi-session workshops</li>
</ul>
</li>

<li>
<strong>Set Location</strong>
<ul>
<li>Enter the full address or location name</li>
<li>Be specific so participants can find you!</li>
</ul>
</li>

<li>
<strong>Configure Pricing</strong><br>
See the <a href="#setting-up-pricing">Setting Up Pricing</a> section below.
</li>

<li>
<strong>Set Capacity</strong>
<ul>
<li><strong>Maximum Capacity</strong> - Total spots available</li>
<li><strong>Enable Waitlist</strong> - Let people join a waitlist when full</li>
</ul>
</li>

<li>
<strong>Publish or Save Draft</strong>
<ul>
<li><strong>Publish</strong> - Makes the workshop visible and available for enrollment</li>
<li><strong>Save Draft</strong> - Saves your work without making it public</li>
</ul>
</li>
</ol>

---

## Setting Up Pricing

The pricing section lets you create flexible payment options for your workshops.

### Basic Pricing

For a simple, single-price workshop:

1. Find the **Pricing Options** section
2. Enter your price (e.g., `75.00` for $75)
3. That's it! Customers will see this price when enrolling

### Multiple Pricing Options

You can offer different pricing tiers. Common examples:

- **Adult vs. Child** pricing
- **Member vs. Non-Member** rates  
- **Single Session vs. Full Series** options

To add multiple options:

1. Click **Add Pricing Option**
2. Enter:
   - **Option Name** (e.g., "Adult Registration")
   - **Price** (e.g., 75.00)
3. Repeat for each option you want to offer

<div class="note">
Customers will choose from these options during checkout. Make the names clear so they know which to pick.
</div>

### Setting a Default Price

If you have multiple pricing options:

1. Click the star icon next to the option you want as the default
2. This option will be pre-selected for customers

---

## Managing Workshop Capacity

### Setting Maximum Capacity

The **Maximum Capacity** determines how many people can enroll:

- Set a number that works for your space and teaching style
- When this number is reached, enrollment automatically closes
- The website shows "Sold Out" or enables the waitlist

### Enabling the Waitlist

When a workshop fills up, you can let interested people join a waitlist:

1. Check the **Enable Waitlist** option
2. When the workshop is full, visitors can add themselves to the waitlist
3. If someone cancels, waitlist members are notified automatically

<div class="tip">
Waitlists are a great way to gauge interest. If your waitlist is often full, consider adding more sessions!
</div>

### Checking Remaining Spots

On any workshop, you can see:

- **Enrolled:** Number of confirmed participants
- **Capacity:** Maximum spots available
- **Remaining:** How many spots are left
- **Waitlist:** Number of people waiting (if enabled)

---

## Editing a Workshop

To make changes to an existing workshop:

1. Go to **Workshops** and find your workshop
2. Click on the title or hover and click **Edit**
3. Make your changes
4. Click **Update** to save

### What You Can Change

You can update almost anything:

- ✅ Title and description
- ✅ Date and time
- ✅ Location
- ✅ Pricing (be careful - see warning below)
- ✅ Capacity
- ✅ Featured image

<div class="warning">
<strong>Changing Prices After People Enroll:</strong> If you change the price, people who already enrolled keep their original price. Only new enrollments will see the new price. Consider creating a new workshop instead if the change is significant.
</div>

---

## Workshop Status

Workshops can have different statuses:

| Status | What It Means | Visible to Public? |
|--------|---------------|-------------------|
| **Published** | Active and accepting enrollments | Yes |
| **Draft** | Work in progress | No |
| **Pending** | Awaiting review | No |
| **Private** | Only visible to logged-in users | Limited |

### Changing Status

- To **unpublish**: Click "Switch to Draft" in the Publish box
- To **publish**: Click the "Publish" button
- To **delete**: Move to Trash (can be recovered for 30 days)

---

## Best Practices

### Writing Great Workshop Descriptions

Your description should answer:

- **What** will participants learn or create?
- **Who** is this workshop for? (skill level, age, etc.)
- **What** should they bring or wear?
- **What's** included? (materials, refreshments, etc.)

### Setting Good Capacity Limits

Consider:

- Physical space limitations
- Your ability to give individual attention
- Equipment or material availability
- Safety requirements

### Planning Ahead

- Create workshops at least 2-3 weeks before the date
- This gives you time to promote and fill spots
- Set calendar reminders to create recurring workshops

---

## Common Questions

**Q: Can I change the date of a workshop that has enrollments?**

A: Yes, but enrolled customers won't be automatically notified. You should email them about the change. Consider if the new date works for everyone or if you need to offer refunds.

**Q: What happens when a workshop sells out?**

A: The "Enroll" button changes to "Sold Out" (or "Join Waitlist" if enabled). No more enrollments are accepted unless you increase capacity or someone cancels.

**Q: Can I hide a workshop without deleting it?**

A: Yes! Change its status to "Draft" and it will no longer appear on your website but all information is preserved.

**Q: How far in advance can I create workshops?**

A: As far as you like! Many people create their workshops months ahead and publish them as needed.

---

## Next Steps

- [Managing Enrollments]({{ '/guides/managing-enrollments' | relative_url }}) - Handle participant information
- [Processing Refunds]({{ '/guides/processing-refunds' | relative_url }}) - Manage cancellations
- [Email Templates]({{ '/guides/email-templates' | relative_url }}) - Customize your communications

