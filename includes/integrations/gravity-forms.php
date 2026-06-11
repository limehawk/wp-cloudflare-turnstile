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

add_filter("gform_submit_button", function ($button, $form) {
    ob_start();
    Turnstile_Protection::render_widget();
    return ob_get_clean() . $button;
}, 10, 2);

add_filter("gform_validation", function ($validation_result) {
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
