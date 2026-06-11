/**
 * Injects a Cloudflare Turnstile widget into Elementor Pro forms and resets
 * it after failed submissions (tokens are single-use).
 */
(function () {
    "use strict";

    var settings = window.wpcftElementor || {};
    var widgets = []; // { form, widgetId }

    function initForms(root) {
        if (!window.turnstile || !settings.sitekey) return;

        var forms = (root || document).querySelectorAll(".elementor-form:not(.wpcft-processed)");
        forms.forEach(function (form) {
            form.classList.add("wpcft-processed");
            if (form.querySelector(".cf-turnstile")) return;

            var submit = form.querySelector('button[type="submit"]');
            if (!submit) return;

            var container = document.createElement("div");
            container.className = "cf-turnstile";
            container.style.cssText = "margin: 10px 0 15px 0;";
            // Insert before the button's field-group wrapper when present,
            // else directly before the button — always inside the <form> so
            // the token posts with the submission.
            var anchor = (submit.closest && submit.closest(".elementor-field-group")) || submit;
            if (anchor === form || !form.contains(anchor)) anchor = submit;
            anchor.parentNode.insertBefore(container, anchor);

            var widgetId = window.turnstile.render(container, {
                sitekey: settings.sitekey,
                theme: settings.theme || "auto",
                size: "flexible",
            });
            widgets.push({ form: form, widgetId: widgetId });
        });
    }

    function resetWidget(form) {
        widgets.forEach(function (w) {
            if (w.form === form && window.turnstile) {
                window.turnstile.reset(w.widgetId);
            }
        });
    }

    function ready(fn) {
        if (document.readyState !== "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
    }

    ready(function () {
        // Turnstile api.js loads async; poll briefly until it's available.
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            if (window.turnstile || tries > 50) {
                clearInterval(timer);
                initForms();
            }
        }, 100);

        // Elementor popups render their content on open.
        if (window.jQuery) {
            window.jQuery(document).on("elementor/popup/show", function () {
                initForms();
            });
        }

        // Tokens are single-use — reset after every submission outcome.
        // Elementor triggers jQuery "submit_error" / "submit_success" events
        // on the form element.
        if (window.jQuery) {
            window.jQuery(document).on("submit_error submit_success", ".elementor-form", function () {
                resetWidget(this);
            });
        }
    });
})();
