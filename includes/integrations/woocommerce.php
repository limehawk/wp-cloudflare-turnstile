<?php
/**
 * WooCommerce integration — login, registration, and lost password forms.
 *
 * The core `authenticate` filter already verifies WooCommerce logins (it goes
 * through wp_signon), so this file only needs to render the widget on WC's
 * own forms and hook WC-specific registration validation.
 */

defined("ABSPATH") || exit;

if (!class_exists("WooCommerce")) {
    return;
}

add_action("woocommerce_login_form", ["Turnstile_Protection", "render_widget"]);
add_action("woocommerce_register_form", ["Turnstile_Protection", "render_widget"]);
add_action("woocommerce_lostpassword_form", ["Turnstile_Protection", "render_widget"]);

// WC registration bypasses `registration_errors` — it has its own filter.
add_filter("woocommerce_registration_errors", function ($errors) {
    if (Turnstile_Protection::is_programmatic_request()) return $errors;
    if (!Turnstile_Protection::verify_token()) {
        $errors->add("turnstile_failed", Turnstile_Protection::error_message());
    }
    return $errors;
}, 10, 1);

// WC login and lost password go through wp_signon / lostpassword_post, which
// the core integration already hooks when those toggles are enabled. If the
// core toggles are off, verify only submissions coming from WC's own forms
// (identified by their nonce fields) so wp-login.php stays unprotected as
// the user configured.
if (!Turnstile_Protection::enabled("protect_login")) {
    add_filter("authenticate", function ($user, $username, $password) {
        if (!isset($_POST["woocommerce-login-nonce"])) return $user;
        return Turnstile_Protection::verify_login($user, $username, $password);
    }, 30, 3);
}
if (!Turnstile_Protection::enabled("protect_lostpass")) {
    add_action("lostpassword_post", function ($errors) {
        if (!isset($_POST["woocommerce-lost-password-nonce"])) return;
        Turnstile_Protection::verify_lostpassword($errors);
    });
}
