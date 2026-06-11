<?php
/**
 * Plugin Name: WP Cloudflare Turnstile
 * Plugin URI: https://github.com/limehawk/wp-cloudflare-turnstile
 * Description: Cloudflare Turnstile CAPTCHA protection for WordPress login, registration, lost password, comments, and Gravity Forms.
 * Version: 1.5.0
 * Author: Limehawk
 * Author URI: https://limehawk.io
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Tested up to: 6.7
 */

defined("ABSPATH") || exit;

class Turnstile_Protection {

    private static $site_key;
    private static $secret_key;

    public static function init() {
        self::$site_key = get_option("turnstile_site_key", "");
        self::$secret_key = get_option("turnstile_secret_key", "");

        add_action("admin_menu", [__CLASS__, "add_settings_page"]);
        add_action("admin_init", [__CLASS__, "register_settings"]);

        if (empty(self::$site_key) || empty(self::$secret_key)) {
            add_action("admin_notices", [__CLASS__, "missing_keys_notice"]);
            return;
        }

        // Frontend + login script
        add_action("wp_enqueue_scripts", [__CLASS__, "enqueue_script"]);
        add_action("login_enqueue_scripts", [__CLASS__, "enqueue_script"]);

        // Login
        add_action("login_form", [__CLASS__, "render_login_widget"]);
        add_filter("authenticate", [__CLASS__, "verify_login"], 30, 3);

        // Registration
        add_action("register_form", [__CLASS__, "render_login_widget"]);
        add_filter("registration_errors", [__CLASS__, "verify_registration"], 10, 3);

        // Lost password
        add_action("lostpassword_form", [__CLASS__, "render_login_widget"]);
        add_action("lostpassword_post", [__CLASS__, "verify_lostpassword"]);

        // Comments
        add_action("comment_form_after_fields", [__CLASS__, "render_widget"]);
        add_action("comment_form_logged_in_after", [__CLASS__, "render_widget"]);
        add_filter("preprocess_comment", [__CLASS__, "verify_comment"]);

        // Gravity Forms
        add_filter("gform_submit_button", [__CLASS__, "gform_add_widget"], 10, 2);
        add_filter("gform_validation", [__CLASS__, "gform_validate"]);
    }

    public static function add_settings_page() {
        add_menu_page(
            "Turnstile Settings",
            "Turnstile",
            "manage_options",
            "turnstile-settings",
            [__CLASS__, "render_settings_page"],
            "dashicons-shield",
            100
        );
    }

    public static function register_settings() {
        register_setting("turnstile_settings", "turnstile_site_key", "sanitize_text_field");
        register_setting("turnstile_settings", "turnstile_secret_key", "sanitize_text_field");
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Cloudflare Turnstile Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields("turnstile_settings"); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="turnstile_site_key">Site Key</label></th>
                        <td><input type="text" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo esc_attr(get_option("turnstile_site_key")); ?>" class="regular-text" style="width: 400px;"></td>
                    </tr>
                    <tr>
                        <th><label for="turnstile_secret_key">Secret Key</label></th>
                        <td><input type="text" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo esc_attr(get_option("turnstile_secret_key")); ?>" class="regular-text" style="width: 400px;"></td>
                    </tr>
                </table>
                <?php submit_button("Save Settings"); ?>
            </form>
        </div>
        <?php
    }

    public static function missing_keys_notice() {
        echo '<div class="notice notice-warning"><p><strong>WP Cloudflare Turnstile:</strong> Please <a href="' . admin_url("admin.php?page=turnstile-settings") . '">configure your Cloudflare Turnstile keys</a> to enable protection.</p></div>';
    }

    public static function enqueue_script() {
        wp_enqueue_script("cloudflare-turnstile", "https://challenges.cloudflare.com/turnstile/v0/api.js", [], null, true);
    }

    public static function render_login_widget() {
        ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(self::$site_key); ?>" data-theme="light" data-size="flexible"></div>
        <style>.login .cf-turnstile { margin: 16px 0 16px -15px; }</style>
        <?php
    }

    public static function render_widget() {
        ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(self::$site_key); ?>" data-theme="light" data-size="flexible" style="margin: 16px 0;"></div>
        <?php
    }

    public static function verify_token() {
        $token = isset($_POST["cf-turnstile-response"]) ? sanitize_text_field($_POST["cf-turnstile-response"]) : "";
        if (empty($token)) return false;

        $response = wp_remote_post("https://challenges.cloudflare.com/turnstile/v0/siteverify", [
            "body" => [
                "secret" => self::$secret_key,
                "response" => $token,
                "remoteip" => $_SERVER["REMOTE_ADDR"] ?? "",
            ],
        ]);

        if (is_wp_error($response)) return false;

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($result["success"]);
    }

    public static function verify_login($user, $username, $password) {
        if (empty($username) || empty($password)) return $user;
        if (!self::verify_token()) {
            return new WP_Error("turnstile_failed", "<strong>Error:</strong> Please complete the security verification.");
        }
        return $user;
    }

    public static function verify_registration($errors, $sanitized_user_login, $user_email) {
        if (!self::verify_token()) {
            $errors->add("turnstile_failed", "<strong>Error:</strong> Please complete the security verification.");
        }
        return $errors;
    }

    public static function verify_lostpassword($errors) {
        if (!self::verify_token()) {
            $errors->add("turnstile_failed", "<strong>Error:</strong> Please complete the security verification.");
        }
    }

    public static function verify_comment($commentdata) {
        if (is_admin() || current_user_can("moderate_comments")) return $commentdata;
        if (!self::verify_token()) {
            wp_die("Please complete the security verification and try again.", "Verification Failed", ["back_link" => true]);
        }
        return $commentdata;
    }

    public static function gform_add_widget($button, $form) {
        return '<div class="cf-turnstile" data-sitekey="' . esc_attr(self::$site_key) . '" data-theme="light" data-size="flexible" style="margin: 16px 0;"></div>' . $button;
    }

    public static function gform_validate($validation_result) {
        if (!self::verify_token()) {
            $validation_result["is_valid"] = false;
            foreach ($validation_result["form"]["fields"] as &$field) {
                $field->failed_validation = true;
                $field->validation_message = "Please complete the security verification.";
                break;
            }
        }
        return $validation_result;
    }
}

add_action("init", ["Turnstile_Protection", "init"]);
