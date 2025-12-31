---
layout: default
title: API Reference
description: REST API documentation for the Fields Bright Enrollment System.
---

# API Reference

This document covers the REST API endpoints available in the Fields Bright Enrollment System. These endpoints are used internally by the system and can be used for integrations.

<div class="warning">
This documentation is for developers. If you're a non-technical user, you don't need to use these APIs - the WordPress admin interface handles everything.
</div>

---

## Authentication

All API endpoints require authentication unless otherwise noted.

### WordPress Authentication

Most endpoints use WordPress cookie-based authentication (for logged-in admin users) or nonces for public-facing operations.

### Webhook Authentication

The Stripe webhook endpoint uses Stripe's signature verification instead of WordPress authentication.

---

## Base URL

All endpoints are prefixed with:

```
/wp-json/fields-bright/v1/
```

Full URL example:
```
https://yoursite.com/wp-json/fields-bright/v1/cart
```

---

## Cart Endpoints

### Get Cart

Retrieve the current user's cart contents.

**Endpoint:** `GET /cart`

**Authentication:** None (uses session/cookie)

**Response:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "workshop_id": 123,
        "workshop_title": "Pottery Workshop",
        "pricing_option": "adult",
        "price": 75.00,
        "date": "2024-02-15",
        "time": "10:00 AM"
      }
    ],
    "total": 75.00,
    "item_count": 1
  }
}
```

---

### Add to Cart

Add a workshop to the cart.

**Endpoint:** `POST /cart/add`

**Authentication:** None (uses session/cookie)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `workshop_id` | integer | Yes | Workshop post ID |
| `pricing_option` | string | No | Selected pricing option key |

**Request Body:**

```json
{
  "workshop_id": 123,
  "pricing_option": "adult"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Added to cart",
  "data": {
    "cart_count": 1,
    "total": 75.00
  }
}
```

**Error Response:**

```json
{
  "success": false,
  "message": "Workshop is full",
  "code": "workshop_full"
}
```

---

### Remove from Cart

Remove an item from the cart.

**Endpoint:** `DELETE /cart/remove`

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `workshop_id` | integer | Yes | Workshop to remove |

**Response:**

```json
{
  "success": true,
  "message": "Removed from cart",
  "data": {
    "cart_count": 0,
    "total": 0
  }
}
```

---

### Clear Cart

Remove all items from the cart.

**Endpoint:** `DELETE /cart/clear`

**Response:**

```json
{
  "success": true,
  "message": "Cart cleared"
}
```

---

## Enrollment Endpoints

### Create Checkout Session

Initiate a Stripe Checkout session for enrollment.

**Endpoint:** `POST /enrollment/checkout`

**Authentication:** None (public)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `workshop_id` | integer | Yes | Workshop to enroll in |
| `pricing_option` | string | No | Selected pricing option |
| `customer_email` | string | No | Pre-fill customer email |

**Response:**

```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/...",
    "session_id": "cs_test_..."
  }
}
```

---

### Get User Enrollments

Get enrollments for the current logged-in user.

**Endpoint:** `GET /enrollments/user`

**Authentication:** Required (logged-in user)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status (completed, pending, cancelled) |

**Response:**

```json
{
  "success": true,
  "data": {
    "enrollments": [
      {
        "id": 456,
        "workshop_id": 123,
        "workshop_title": "Pottery Workshop",
        "status": "completed",
        "date_enrolled": "2024-01-15T14:30:00Z",
        "amount_paid": 75.00
      }
    ]
  }
}
```

---

## Workshop Endpoints

### Get Workshop

Get details for a specific workshop.

**Endpoint:** `GET /workshops/{id}`

**Authentication:** None

**Response:**

```json
{
  "id": 123,
  "title": "Pottery Workshop",
  "description": "Learn the basics of pottery...",
  "date": "2024-02-15",
  "start_time": "10:00",
  "end_time": "12:00",
  "location": "123 Art Street",
  "capacity": 15,
  "enrolled": 8,
  "remaining": 7,
  "is_full": false,
  "pricing_options": [
    {
      "key": "adult",
      "label": "Adult",
      "price": 75.00
    },
    {
      "key": "child",
      "label": "Child (under 12)",
      "price": 50.00
    }
  ]
}
```

---

### List Workshops

Get a list of available workshops.

**Endpoint:** `GET /workshops`

**Authentication:** None

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `upcoming` | boolean | No | Only return future workshops |
| `has_spots` | boolean | No | Only return workshops with availability |
| `per_page` | integer | No | Results per page (default: 10, max: 100) |
| `page` | integer | No | Page number |

**Response:**

```json
{
  "success": true,
  "data": {
    "workshops": [...],
    "total": 25,
    "pages": 3
  }
}
```

---

## Waitlist Endpoints

### Join Waitlist

Add someone to a workshop's waitlist.

**Endpoint:** `POST /waitlist/join`

**Authentication:** None (nonce protected)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `workshop_id` | integer | Yes | Workshop ID |
| `email` | string | Yes | Customer email |
| `name` | string | Yes | Customer name |
| `phone` | string | No | Customer phone |
| `nonce` | string | Yes | Security nonce |

**Response:**

```json
{
  "success": true,
  "message": "You've been added to the waitlist",
  "data": {
    "position": 3
  }
}
```

---

### Claim Waitlist Spot

Claim a spot when notified from waitlist.

**Endpoint:** `GET /waitlist/claim`

**Authentication:** Token-based

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Magic link token |
| `entry_id` | integer | Yes | Waitlist entry ID |

**Response:** Redirects to checkout or shows error page.

---

## Webhook Endpoint

### Stripe Webhook

Receives events from Stripe.

**Endpoint:** `POST /webhook/stripe`

**Authentication:** Stripe signature verification

**Headers Required:**

```
Stripe-Signature: t=...,v1=...
```

**Handled Events:**

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Creates enrollment, sends confirmation |
| `payment_intent.succeeded` | Marks payment as complete |
| `payment_intent.payment_failed` | Logs failure |
| `charge.refunded` | Updates enrollment status |

**Response:**

```json
{
  "received": true
}
```

---

## Admin Endpoints

These endpoints require administrator capabilities.

### Process Refund

Process a refund for an enrollment.

**Endpoint:** `POST /admin/refund`

**Authentication:** Administrator + nonce

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `enrollment_id` | integer | Yes | Enrollment to refund |
| `amount` | float | No | Partial amount (full if omitted) |
| `reason` | string | No | Refund reason |
| `nonce` | string | Yes | Admin nonce |

**Response:**

```json
{
  "success": true,
  "message": "Refund processed",
  "data": {
    "refund_id": "re_...",
    "amount": 75.00
  }
}
```

---

### Export Enrollments

Export enrollment data.

**Endpoint:** `GET /admin/export`

**Authentication:** Administrator

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `format` | string | No | csv or pdf (default: csv) |
| `workshop_id` | integer | No | Filter by workshop |
| `date_from` | string | No | Start date (Y-m-d) |
| `date_to` | string | No | End date (Y-m-d) |
| `status` | string | No | Filter by status |

**Response:** File download (CSV or PDF)

---

## Error Codes

Standard error responses follow this format:

```json
{
  "success": false,
  "code": "error_code",
  "message": "Human readable message"
}
```

### Common Error Codes

| Code | HTTP Status | Meaning |
|------|-------------|---------|
| `invalid_workshop` | 404 | Workshop not found |
| `workshop_full` | 400 | No spots available |
| `already_enrolled` | 400 | User already enrolled |
| `invalid_nonce` | 403 | Security validation failed |
| `unauthorized` | 401 | Authentication required |
| `forbidden` | 403 | Insufficient permissions |
| `stripe_error` | 500 | Stripe API error |
| `server_error` | 500 | Unexpected server error |

---

## Rate Limiting

Currently, no strict rate limiting is enforced, but:

- Stripe webhook processing has idempotency checks
- Cart operations use session-based throttling
- Abuse may result in IP-level blocks

---

## Testing

### Test Mode

When Stripe is in test mode, use test card numbers:

- **Success:** `4242 4242 4242 4242`
- **Decline:** `4000 0000 0000 0002`
- **Requires Auth:** `4000 0025 0000 3155`

### Local Testing

For webhook testing locally, use Stripe CLI:

```bash
stripe listen --forward-to localhost/wp-json/fields-bright/v1/webhook/stripe
```

---

## Changelog

### v1.2.0
- Added waitlist endpoints
- Improved error responses
- Added export functionality

### v1.1.0
- Added cart multi-item support
- Improved authentication

### v1.0.0
- Initial release

