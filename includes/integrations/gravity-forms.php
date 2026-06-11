<?php
/**
 * Gravity Forms integration.
 *
 * Note: Gravity Forms ships an official Cloudflare Turnstile add-on with
 * every license. Prefer that if you have it — disable this toggle to avoid
 * rendering two widgets.
 */

defined("ABSPATH") || exit;

if (!class_exists("GFForms")) {
    return;
}

add_action("wp_enqueue_scripts", function () {
    wp_enqueue_script(
        "wpcft-gravity-forms",
        WPCFT_PLUGIN_URL . "assets/gravity-forms.js",
        ["cloudflare-turnstile", "jquery"],
        WPCFT_VERSION,
        true
    );
    wp_localize_script("wpcft-gravity-forms", "wpcftGF", [
        "sitekey" => Turnstile_Protection::site_key(),
        "theme"   => Turnstile_Protection::theme(),
    ]);
}, 99);

add_filter("gform_submit_button", function ($button, $form) {
    ob_start();
    Turnstile_Protection::render_widget();
    return ob_get_clean() . $button;
}, 10, 2);

add_filter("gform_validation", function ($validation_result) {
    $form = $validation_result["form"];

    // Multi-page forms: page navigation posts a non-zero target page and no
    // token (the widget only renders at the final submit button). Only the
    // real submission (target page 0) carries a token to verify.
    $target_page = isset($_POST["gform_target_page_number_" . $form["id"]])
        ? (int) $_POST["gform_target_page_number_" . $form["id"]]
        : 0;
    if ($target_page !== 0 && empty($_POST["cf-turnstile-response"])) {
        return $validation_result;
    }

    if (Turnstile_Protection::verify_token()) {
        return $validation_result;
    }
    $validation_result["is_valid"] = false;
    foreach ($validation_result["form"]["fields"] as &$field) {
        $field->failed_validation  = true;
        $field->validation_message = Turnstile_Protection::error_message();
        break;
    }
    return $validation_result;
});
