<?php
/**
 * Elementor Pro Forms integration.
 *
 * Elementor forms are rendered by the page builder with no PHP hook to
 * inject markup inside the <form>, so the widget is inserted client-side
 * (assets/elementor-forms.js) and the token rides along in the form's AJAX
 * submission. Validation happens server-side here.
 */

defined("ABSPATH") || exit;

if (!defined("ELEMENTOR_PRO_VERSION")) {
    return;
}

add_action("wp_enqueue_scripts", function () {
    wp_enqueue_script(
        "wpcft-elementor-forms",
        WPCFT_PLUGIN_URL . "assets/elementor-forms.js",
        ["cloudflare-turnstile"],
        WPCFT_VERSION,
        true
    );
    wp_localize_script("wpcft-elementor-forms", "wpcftElementor", [
        "sitekey" => Turnstile_Protection::site_key(),
        "theme"   => Turnstile_Protection::theme(),
    ]);
}, 99);

add_action("elementor_pro/forms/validation", function ($record, $ajax_handler) {
    if (Turnstile_Protection::verify_token()) {
        return;
    }
    $ajax_handler->add_error_message(Turnstile_Protection::error_message());
    $ajax_handler->add_error("", "");
    $ajax_handler->is_success = false;
}, 10, 2);
