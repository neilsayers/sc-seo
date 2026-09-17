(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.scseo-delete-redirect').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (! window.confirm('Delete this redirect? This can\'t be undone.')) {
                    event.preventDefault();
                }
            });
        });
    });
})();
