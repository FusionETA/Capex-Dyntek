// Capex companion app — vanilla JS, no build step. Runs inside the Bitrix24 iframe.
// Screens re-check rights server-side; this file is presentation only.

(function () {
    'use strict';

    // Bitrix24 JS SDK is injected by the placement host when available.
    if (typeof window.BX24 !== 'undefined') {
        window.BX24.init(function () {
            window.BX24.fitWindow();
        });
    }
})();
