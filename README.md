# WP Cloudflare Turnstile

A lightweight WordPress plugin that adds [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) CAPTCHA protection to your site. Single file, no dependencies, no bloat.

## What it protects

- Login form
- Registration form
- Lost password form
- Comment forms (logged-in and guest)
- Gravity Forms

## Installation

1. Download `turnstile-protection.php`
2. Upload to `wp-content/plugins/`
3. Activate in WordPress admin
4. Go to **Turnstile** in the admin sidebar
5. Enter your Cloudflare Turnstile Site Key and Secret Key

Get your keys from the [Cloudflare dashboard](https://dash.cloudflare.com/?to=/:account/turnstile) under Turnstile.

## How it works

- Loads the Turnstile script on frontend and login pages
- Renders the widget automatically on protected forms
- Verifies the token server-side via Cloudflare's `siteverify` endpoint
- **Fails closed** — if verification fails or Cloudflare is unreachable, the form submission is blocked
- Admins and moderators bypass comment verification

## Configuration

The settings page (under the shield icon in the admin menu) has two fields:

| Field | Description |
|-------|-------------|
| Site Key | Your Turnstile widget site key (public) |
| Secret Key | Your Turnstile secret key (server-side verification) |

If either key is missing, the plugin shows an admin notice and stays inactive.

## Optional: Disable XML-RPC

The original deployment included XML-RPC disabling as a hardening measure. If you want this, add to your theme's `functions.php` or a mu-plugin:

```php
add_filter("xmlrpc_enabled", "__return_false");
add_filter("xmlrpc_methods", function($methods) { return []; });
```

## Requirements

- WordPress 5.6+
- PHP 7.4+
- A Cloudflare account with Turnstile enabled

## License

MIT
