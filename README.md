# Fields Bright Enrollment System

A comprehensive WordPress child theme that provides a complete workshop enrollment system with Stripe payment integration.

## Features

- **Workshop Management** - Create and manage workshops with dates, times, locations, and pricing
- **Stripe Integration** - Secure payment processing through Stripe Checkout
- **Shopping Cart** - Multi-item cart with session persistence
- **Waitlist System** - Automatic waitlist management with magic link claims
- **Email Notifications** - Automated confirmation, reminder, and refund emails
- **Admin Dashboard** - Complete enrollment management from WordPress admin
- **Refund Processing** - Full and partial refund support through Stripe
- **User Accounts** - Customer enrollment history and profile management

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Stripe account
- SSL certificate (required for payments)

## Installation

1. Upload the theme to `wp-content/themes/fields-bright-child-theme`
2. Activate the theme in WordPress
3. Configure Stripe settings under **Settings → Fields Bright Enrollment**
4. Set up Stripe webhook endpoint

## Documentation

Comprehensive documentation is available in the `docs/` folder:

### User Guides
- [Getting Started](docs/getting-started.md)
- [Managing Workshops](docs/managing-workshops.md)
- [Managing Enrollments](docs/managing-enrollments.md)
- [Processing Refunds](docs/processing-refunds.md)
- [Email Templates](docs/email-templates.md)
- [Troubleshooting](docs/troubleshooting.md)

### Technical Documentation
- [API Reference](docs/api-reference.md)
- [Security Guide](docs/security.md)
- [Deployment Guide](docs/deployment.md)

## GitHub Pages Documentation

The documentation is built with Jekyll and can be deployed to GitHub Pages:

1. Push this repository to GitHub
2. Go to repository Settings → Pages
3. Set source to `docs/` folder on `main` branch
4. Documentation will be available at `https://username.github.io/repo-name/`

## Security

This theme implements:

- Input validation and sanitization
- Nonce verification for CSRF protection
- Capability checks for authorization
- Output escaping for XSS prevention
- Prepared statements for SQL queries
- Stripe webhook signature verification
- PCI-compliant payment handling

## Development

### File Structure

```
fields-bright-child-theme/
├── assets/
│   ├── css/         # Stylesheets
│   └── js/          # JavaScript files
├── docs/            # Jekyll documentation
├── includes/
│   └── Enrollment/  # Main enrollment system classes
├── templates/
│   └── emails/      # Email templates
├── functions.php    # Theme functions
└── style.css        # Theme styles
```

### Running Documentation Locally

```bash
cd docs/
bundle install
bundle exec jekyll serve
```

Visit `http://localhost:4000` to preview documentation.

## Version History

### v1.2.0 (Current)
- Waitlist system with magic link claims
- Multi-item shopping cart
- Security audit and hardening
- Performance optimizations
- Comprehensive documentation

### v1.1.0
- Initial enrollment system
- Stripe integration
- Basic admin interface

## License

This theme is proprietary software developed for Fields Bright.

## Support

For technical support, contact the development team.

