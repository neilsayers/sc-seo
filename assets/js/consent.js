/*
 * Cookie banner behaviour. Hooks for themes:
 *
 *   - [data-scseo-consent-state] on #scseo-consent: "unset" | "granted" | "denied"
 *   - html classes: scseo-consent-unset | scseo-consent-granted | scseo-consent-denied
 *   - html class scseo-consent-open while the banner is showing
 *   - .is-open on the banner while it is showing
 *   - a bubbling "scseo:consent" event on document, detail: { choice: "granted" | "denied" }
 *   - window.scseoLoadGA() — loads Google Analytics (what Accept calls)
 */
(function () {
    var KEY = (window.scseoConsent && window.scseoConsent.key) || 'scseo_consent';
    var MAX_AGE = 31536e6; // 12 months
    var banner = document.getElementById('scseo-consent');
    var root = document.documentElement;

    if (!banner) {
        return;
    }

    function read() {
        try {
            var c = JSON.parse(localStorage.getItem(KEY) || 'null');

            if (c && (c.v === 'granted' || c.v === 'denied') && Date.now() - c.t < MAX_AGE) {
                return c.v;
            }
        } catch (e) {}

        return null;
    }

    function write(value) {
        try {
            localStorage.setItem(KEY, JSON.stringify({ v: value, t: Date.now() }));
        } catch (e) {}
    }

    function setState(state) {
        banner.setAttribute('data-scseo-consent-state', state);
        ['unset', 'granted', 'denied'].forEach(function (name) {
            root.classList.toggle('scseo-consent-' + name, name === state);
        });
    }

    function setOpen(open) {
        banner.hidden = !open;
        banner.classList.toggle('is-open', open);
        root.classList.toggle('scseo-consent-open', open);
    }

    // Rejecting after having accepted: drop the _ga / _ga_XXXX cookies
    // GA already set, on this host and every parent domain.
    function clearGaCookies() {
        var host = location.hostname.split('.');
        var domains = [''];

        for (var i = 0; i < host.length - 1; i++) {
            domains.push('; domain=.' + host.slice(i).join('.'));
        }

        document.cookie.split(';').forEach(function (pair) {
            var name = pair.split('=')[0].trim();

            if (name === '_ga' || name.indexOf('_ga_') === 0 || name === '_gid') {
                domains.forEach(function (d) {
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d;
                });
            }
        });
    }

    function show() {
        setOpen(true);

        var first = banner.querySelector('[data-scseo-consent]');

        if (first) {
            first.focus();
        }
    }

    function choose(value) {
        write(value);
        setState(value);
        setOpen(false);

        if (value === 'granted') {
            if (typeof window.scseoLoadGA === 'function') {
                window.scseoLoadGA();
            }
        } else {
            clearGaCookies();
        }

        document.dispatchEvent(new CustomEvent('scseo:consent', { bubbles: true, detail: { choice: value } }));
    }

    banner.addEventListener('click', function (event) {
        var button = event.target.closest('[data-scseo-consent]');

        if (button) {
            choose(button.getAttribute('data-scseo-consent'));
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-scseo-consent-open]')) {
            event.preventDefault();
            show();
        }
    });

    var stored = read();

    setState(stored || 'unset');

    if (stored === null) {
        setOpen(true);
    }
})();
