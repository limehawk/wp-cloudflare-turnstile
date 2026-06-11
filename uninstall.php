<?php
defined("WP_UNINSTALL_PLUGIN") || exit;

delete_option("turnstile_site_key");
delete_option("turnstile_secret_key");
delete_option("turnstile_settings");
