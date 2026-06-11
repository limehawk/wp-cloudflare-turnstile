/**
 * Re-renders Turnstile widgets after Gravity Forms AJAX re-renders.
 *
 * api.js only auto-renders .cf-turnstile elements present at script load.
 * GF AJAX forms replace their markup after a failed validation (and on
 * multi-page navigation), leaving an empty widget container — render it
 * explicitly when GF announces a re-render.
 */
(function () {
    "use strict";

    var settings = window.wpcftGF || {};

    function renderEmptyWidgets(root) {
        if (!window.turnstile || !settings.sitekey) return;
        (root || document).querySelectorAll(".cf-turnstile").forEach(function (el) {
            if (el.childElementCount === 0) {
                window.turnstile.render(el, {
                    sitekey: settings.sitekey,
                    theme: settings.theme || "auto",
                    size: "flexible",
                });
            }
        });
    }

    if (window.jQuery) {
        window.jQuery(document).on("gform_post_render", function (event, formId) {
            var wrapper = document.getElementById("gform_wrapper_" + formId);
            renderEmptyWidgets(wrapper || document);
        });
    }
})();
