/**
 * Counts a click as a conversion for any running test that uses a click goal.
 *
 * The page this runs on can be cached, so nothing here depends on a nonce or on
 * anything rendered per visitor beyond the test's own cookie.
 */
(function () {
    'use strict';

    var config = window.convertproClickGoals;

    if (!config || !config.tests || !config.tests.length) {
        return;
    }

    function cookie(name) {
        var parts = document.cookie ? document.cookie.split('; ') : [];

        for (var i = 0; i < parts.length; i++) {
            var pair = parts[i].split('=');
            if (decodeURIComponent(pair[0]) === name) {
                return decodeURIComponent(pair.slice(1).join('='));
            }
        }

        return '';
    }

    /**
     * Does this element, or something it sits inside, match what the test is
     * watching for? Selectors are matched as CSS; anything that is not valid CSS
     * is treated as part of a link address instead.
     */
    function matches(element, patterns) {
        for (var i = 0; i < patterns.length; i++) {
            var pattern = patterns[i];

            if (!pattern) {
                continue;
            }

            try {
                if (element.closest(pattern)) {
                    return true;
                }
            } catch (e) {
                // Not a selector, fall through to the link check.
            }

            var link = element.closest('a[href]');

            if (link && link.getAttribute('href').indexOf(pattern) !== -1) {
                return true;
            }
        }

        return false;
    }

    function report(test) {
        var body = new FormData();
        body.append('test', test.id);
        body.append('variation', test.variation);

        // keepalive so the request survives the page starting to unload after a
        // click that navigates away.
        if (window.fetch) {
            fetch(config.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' });
        } else if (navigator.sendBeacon) {
            navigator.sendBeacon(config.endpoint, body);
        }
    }

    var done = {};

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        if (!cookie('convert_pro_uid')) {
            return;
        }

        for (var i = 0; i < config.tests.length; i++) {
            var test = config.tests[i];

            if (done[test.id]) {
                continue;
            }

            if (matches(target, test.patterns)) {
                done[test.id] = true;
                report(test);
            }
        }
    }, true);
})();
