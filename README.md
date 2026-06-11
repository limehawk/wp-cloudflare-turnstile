# WP Cloudflare Turnstile

Lightweight [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) protection for the WordPress forms that *don't* have native Turnstile support. No bloat, no upsells, MIT licensed.

## What it protects

- **WordPress core**: login, registration, lost password, comments
- **WooCommerce**: login, registration, lost password (My Account forms)
- **Elementor Pro Forms**: all forms, including ones inside popups
- **Gravity Forms**: all forms (see note below)

Each surface has its own on/off toggle in settings.

## What it deliberately doesn't do

Most major form plugins now ship native Turnstile support — use theirs, not another plugin layer:

| Plugin | Native support |
|--------|----------------|
| Contact Form 7 | Built-in since 6.1 — Contact → Integration |
| WPForms | Built-in — Settings → CAPTCHA |
| Gravity Forms | Official add-on, free with every license |
| Fluent Forms / Formidable / Ninja Forms | Check your version — most have it |

This plugin's Gravity Forms integration exists for sites that haven't installed the official add-on. If you use the official add-on, turn the toggle off here so you don't get two widgets.

## Installation

1. Download the latest release
2. Upload the `wp-cloudflare-turnstile` folder to `wp-content/plugins/`
3. Activate, then go to **Turnstile** in the admin sidebar
4. Enter your Site Key and Secret Key from the [Cloudflare dashboard](https://dash.cloudflare.com/?to=/:account/turnstile)

## How it works

- Renders the widget on protected forms and verifies the token server-side against Cloudflare's `siteverify` endpoint
- **Fails closed** — if verification fails or Cloudflare is unreachable, the submission is blocked
- Verification results are cached per-request (Turnstile tokens are single-use; some flows fire multiple validation hooks)
- Programmatic requests (WP-CLI, cron, XML-RPC, REST API) are exempt — browser challenges can't be solved there
- Logged-in users who can moderate comments skip comment verification
- Elementor widgets are injected client-side and reset automatically after a failed submission

## Caveats

- **Custom login forms**: when "Login form" protection is on, *every* credentialed login through `wp_signon()` requires a token. WooCommerce's form is handled, but a theme's custom AJAX login modal won't render the widget and will fail. Either add the widget to your custom form (`<div class="cf-turnstile" data-sitekey="...">`) or use the `wpcft_verify_login` filter to exempt it:

  ```php
  add_filter("wpcft_verify_login", function ($verify, $user) {
      return empty($_POST["my_custom_login_marker"]) ? $verify : false;
  }, 10, 2);
  ```

- **Comments via REST API** are exempt from verification (REST comment creation requires authentication by default).
- **Trackbacks are effectively disabled** when comment protection is on — `wp-trackback.php` submissions carry no token and fail closed. Pingbacks (XML-RPC) are exempt. Given trackback spam volume, we consider this a feature.
- **Multisite signup (`wp-signup.php`) is not covered** — it uses a different form pipeline than single-site registration.
- **WooCommerce checkout** is not covered — guest checkout protection has too many theme/plugin interactions to do reliably in a lightweight plugin. Cloudflare's own WAF or rate limiting is a better fit there.

## Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpcft_verify_login` | filter | Return `false` to skip login verification for a request |
| `wpcft_verify_lostpassword` | filter | Return `false` to skip lost-password verification for a request |
| `wpcft_error_message` | filter | Customize the user-facing error message |

## Hardening extras

The original deployment of this plugin also disabled XML-RPC. That's out of scope here, but if you want it:

```php
add_filter("xmlrpc_enabled", "__return_false");
add_filter("xmlrpc_methods", "__return_empty_array");
```

## Requirements

- WordPress 5.6+, PHP 7.4+
- A Cloudflare account (Turnstile is free)

## License

MIT
