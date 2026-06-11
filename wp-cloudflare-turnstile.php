<?php
/**
 * Plugin Name: WP Cloudflare Turnstile
 * Plugin URI: https://github.com/limehawk/wp-cloudflare-turnstile
 * Description: Cloudflare Turnstile protection for WordPress core forms, WooCommerce, Elementor Pro Forms, and Gravity Forms.
 * Version: 2.0.0
 * Author: Limehawk
 * Author URI: https://limehawk.io
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

defined("ABSPATH") || exit;

define("WPCFT_VERSION", "2.0.0");
define("WPCFT_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("WPCFT_PLUGIN_URL", plugin_dir_url(__FILE__));

class Turnstile_Protection {

    private static $site_key;
    private static $secret_key;
    private static $settings;
    private static $verify_cache = null;

    const DEFAULT_SETTINGS = [
        "theme"            => "auto",
        "protect_login"    => 1,
        "protect_register" => 1,
        "protect_lostpass" => 1,
        "protect_comments" => 1,
        "woocommerce"      => 1,
        "elementor"        => 1,
        "gravityforms"     => 1,
    ];

    public static function init() {
        self::$site_key   = get_option("turnstile_site_key", "");
        self::$secret_key = get_option("turnstile_secret_key", "");
        self::$settings   = wp_parse_args(get_option("turnstile_settings", []), self::DEFAULT_SETTINGS);

        add_action("admin_menu", [__CLASS__, "add_settings_page"]);
        add_action("admin_init", [__CLASS__, "register_settings"]);

        if (empty(self::$site_key) || empty(self::$secret_key)) {
            add_action("admin_notices", [__CLASS__, "missing_keys_notice"]);
            return;
        }

        add_action("wp_enqueue_scripts", [__CLASS__, "enqueue_script"]);
        add_action("login_enqueue_scripts", [__CLASS__, "enqueue_script"]);

        if (self::enabled("protect_login")) {
            add_action("login_form", [__CLASS__, "render_login_widget"]);
            add_filter("authenticate", [__CLASS__, "verify_login"], 30, 3);
        }

        if (self::enabled("protect_register")) {
            add_action("register_form", [__CLASS__, "render_login_widget"]);
            add_filter("registration_errors", [__CLASS__, "verify_registration"], 10, 3);
        }

        if (self::enabled("protect_lostpass")) {
            add_action("lostpassword_form", [__CLASS__, "render_login_widget"]);
            add_action("lostpassword_post", [__CLASS__, "verify_lostpassword"]);
        }

        if (self::enabled("protect_comments")) {
            add_action("comment_form_after_fields", [__CLASS__, "render_widget"]);
            add_action("comment_form_logged_in_after", [__CLASS__, "render_widget"]);
            add_filter("preprocess_comment", [__CLASS__, "verify_comment"]);
        }

        // Integrations — each file guards on its plugin being active.
        if (self::enabled("woocommerce")) {
            require_once WPCFT_PLUGIN_DIR . "includes/integrations/woocommerce.php";
        }
        if (self::enabled("elementor")) {
            require_once WPCFT_PLUGIN_DIR . "includes/integrations/elementor.php";
        }
        if (self::enabled("gravityforms")) {
            require_once WPCFT_PLUGIN_DIR . "includes/integrations/gravity-forms.php";
        }
    }

    public static function enabled($key) {
        return !empty(self::$settings[$key]);
    }

    public static function site_key() {
        return self::$site_key;
    }

    public static function theme() {
        return self::$settings["theme"];
    }

    /* ---------- Settings ---------- */

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
        register_setting("turnstile_settings", "turnstile_site_key", [
            "type"              => "string",
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("turnstile_settings", "turnstile_secret_key", [
            "type"              => "string",
            "sanitize_callback" => "sanitize_text_field",
        ]);
        register_setting("turnstile_settings", "turnstile_settings", [
            "type"              => "array",
            "sanitize_callback" => [__CLASS__, "sanitize_settings"],
        ]);
    }

    public static function sanitize_settings($input) {
        $clean = [];
        $input = is_array($input) ? $input : [];
        $clean["theme"] = in_array($input["theme"] ?? "auto", ["auto", "light", "dark"], true) ? $input["theme"] : "auto";
        foreach (array_keys(self::DEFAULT_SETTINGS) as $key) {
            if ($key === "theme") continue;
            $clean[$key] = empty($input[$key]) ? 0 : 1;
        }
        return $clean;
    }

    public static function render_settings_page() {
        $s = self::$settings;
        $toggles = [
            "protect_login"    => "Login form",
            "protect_register" => "Registration form",
            "protect_lostpass" => "Lost password form",
            "protect_comments" => "Comment forms",
            "woocommerce"      => "WooCommerce (login, registration, lost password)",
            "elementor"        => "Elementor Pro Forms",
            "gravityforms"     => "Gravity Forms (consider the official add-on instead)",
        ];
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
                        <td><input type="password" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo esc_attr(get_option("turnstile_secret_key")); ?>" class="regular-text" style="width: 400px;" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="wpcft_theme">Widget Theme</label></th>
                        <td>
                            <select id="wpcft_theme" name="turnstile_settings[theme]">
                                <?php foreach (["auto", "light", "dark"] as $theme): ?>
                                    <option value="<?php echo esc_attr($theme); ?>" <?php selected($s["theme"], $theme); ?>><?php echo esc_html(ucfirst($theme)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Protected Forms</th>
                        <td>
                            <?php foreach ($toggles as $key => $label): ?>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="turnstile_settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($s[$key])); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">WooCommerce, Elementor, and Gravity Forms toggles only take effect when the plugin is active.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button("Save Settings"); ?>
            </form>
        </div>
        <?php
    }

    public static function missing_keys_notice() {
        echo '<div class="notice notice-warning"><p><strong>WP Cloudflare Turnstile:</strong> Please <a href="' . esc_url(admin_url("admin.php?page=turnstile-settings")) . '">configure your Cloudflare Turnstile keys</a> to enable protection.</p></div>';
    }

    /* ---------- Widget rendering ---------- */

    public static function enqueue_script() {
        wp_enqueue_script("cloudflare-turnstile", "https://challenges.cloudflare.com/turnstile/v0/api.js", [], null, true);
    }

    public static function render_login_widget() {
        self::widget_markup();
        echo "<style>.login .cf-turnstile { margin: 16px 0 16px -15px; }</style>";
    }

    public static function render_widget() {
        self::widget_markup("margin: 16px 0;");
    }

    public static function widget_markup($style = "") {
        printf(
            '<div class="cf-turnstile" data-sitekey="%s" data-theme="%s" data-size="flexible"%s></div>',
            esc_attr(self::$site_key),
            esc_attr(self::theme()),
            $style ? ' style="' . esc_attr($style) . '"' : ""
        );
    }

    /* ---------- Verification ---------- */

    /**
     * Verify the Turnstile token from the current request.
     * Result is cached per-request — tokens are single-use, and some flows
     * (e.g. WooCommerce) fire multiple validation hooks on one submission.
     */
    public static function verify_token() {
        if (self::$verify_cache !== null) {
            return self::$verify_cache;
        }

        $token = isset($_POST["cf-turnstile-response"]) ? sanitize_text_field(wp_unslash($_POST["cf-turnstile-response"])) : "";
        if (empty($token)) {
            return self::$verify_cache = false;
        }

        $response = wp_remote_post("https://challenges.cloudflare.com/turnstile/v0/siteverify", [
            "body" => [
                "secret"   => self::$secret_key,
                "response" => $token,
                "remoteip" => $_SERVER["REMOTE_ADDR"] ?? "",
            ],
        ]);

        if (is_wp_error($response)) {
            return self::$verify_cache = false; // Fail closed.
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return self::$verify_cache = !empty($result["success"]);
    }

    public static function error_message() {
        return apply_filters("wpcft_error_message", "Please complete the security verification.");
    }

    /**
     * Whether the current request is a programmatic context where browser
     * challenges can't be solved (CLI, cron, XML-RPC, REST).
     */
    public static function is_programmatic_request() {
        return (defined("WP_CLI") && WP_CLI)
            || (defined("DOING_CRON") && DOING_CRON)
            || (defined("XMLRPC_REQUEST") && XMLRPC_REQUEST)
            || (defined("REST_REQUEST") && REST_REQUEST);
    }

    /* ---------- Core form checks ---------- */

    public static function verify_login($user, $username, $password) {
        if (empty($username) || empty($password)) return $user;
        if (self::is_programmatic_request()) return $user;
        if (!apply_filters("wpcft_verify_login", true, $user)) return $user;
        if (!self::verify_token()) {
            return new WP_Error("turnstile_failed", "<strong>Error:</strong> " . self::error_message());
        }
        return $user;
    }

    public static function verify_registration($errors, $sanitized_user_login, $user_email) {
        if (self::is_programmatic_request()) return $errors;
        if (!self::verify_token()) {
            $errors->add("turnstile_failed", "<strong>Error:</strong> " . self::error_message());
        }
        return $errors;
    }

    public static function verify_lostpassword($errors) {
        if (self::is_programmatic_request()) return;
        if (!self::verify_token()) {
            $errors->add("turnstile_failed", "<strong>Error:</strong> " . self::error_message());
        }
    }

    public static function verify_comment($commentdata) {
        if (is_admin() || self::is_programmatic_request()) return $commentdata;
        if (current_user_can("moderate_comments")) return $commentdata;
        if (!self::verify_token()) {
            wp_die(esc_html(self::error_message()), "Verification Failed", ["back_link" => true]);
        }
        return $commentdata;
    }
}

add_action("init", ["Turnstile_Protection", "init"]);
