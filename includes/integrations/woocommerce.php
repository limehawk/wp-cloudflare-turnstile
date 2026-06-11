<?php
/**
 * WooCommerce integration — login, registration, and lost password forms.
 *
 * Always loaded when WooCommerce is active. When the WooCommerce toggle is
 * ON, widgets render on WC's forms and WC-specific validation hooks in.
 * When it's OFF, WC form submissions are exempted from the core login and
 * lost-password checks — those share wp_signon / lostpassword_post with
 * wp-login.php, and enforcing them on forms that render no widget would
 * lock customers out.
 */

defined("ABSPATH") || exit;

if (!class_exists("WooCommerce")) {
    return;
}

/**
 * True only when the request carries a VALID WC nonce — presence alone is
 * forgeable and must never relax verification. Nonce actions verified
 * against WC core (class-wc-form-handler.php / class-wc-checkout.php).
 * If WC ever renames one, this fails toward enforcing verification.
 */
function wpcft_wc_nonce_valid($field, $action) {
    if (empty($_POST[$field])) return false;
    return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field])), $action);
}

if (!Turnstile_Protection::enabled("woocommerce")) {
    add_filter("wpcft_verify_login", function ($verify) {
        return wpcft_wc_nonce_valid("woocommerce-login-nonce", "woocommerce-login") ? false : $verify;
    });
    add_filter("wpcft_verify_lostpassword", function ($verify) {
        return wpcft_wc_nonce_valid("woocommerce-lost-password-nonce", "lost_password") ? false : $verify;
    });
    return;
}

add_action("woocommerce_login_form", ["Turnstile_Protection", "render_widget"]);
add_action("woocommerce_register_form", ["Turnstile_Protection", "render_widget"]);
add_action("woocommerce_lostpassword_form", ["Turnstile_Protection", "render_widget"]);

// WC registration bypasses `registration_errors` — it has its own filter.
add_filter("woocommerce_registration_errors", function ($errors) {
    if (is_admin()) return $errors; // Admin-created users solve no challenge.
    if (Turnstile_Protection::is_programmatic_request()) return $errors;
    // Checkout "Create an account?" runs this same filter, but the checkout
    // page has no widget — don't block orders. Valid nonce only: a forged
    // bare field must not bypass registration verification.
    if (wpcft_wc_nonce_valid("woocommerce-process-checkout-nonce", "woocommerce-process_checkout")) return $errors;
    if (function_exists("is_checkout") && is_checkout()) return $errors;
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
