(function () {
    'use strict';

    /**
     * Generic media-library image picker — driven entirely by data
     * attributes so the same handful of lines serve the SEO metabox's
     * social-image field and the settings screen's default-image and
     * business-logo fields, rather than one wp.media() call per field.
     */
    function initImagePickers() {
        document.querySelectorAll('.scseo-image-select').forEach(function (button) {
            if (button.dataset.scseoBound) {
                return;
            }

            button.dataset.scseoBound = '1';

            button.addEventListener('click', function (event) {
                event.preventDefault();

                var input = document.getElementById(button.dataset.input);
                var preview = document.getElementById(button.dataset.preview);
                var removeButton = document.querySelector('.scseo-image-remove[data-input="' + button.dataset.input + '"]');

                var frame = wp.media({
                    title: 'Choose an image',
                    multiple: false,
                    library: { type: 'image' },
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();

                    input.value = attachment.id;
                    preview.innerHTML = '<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" alt="">';

                    if (removeButton) {
                        removeButton.style.display = '';
                    }
                });

                frame.open();
            });
        });

        document.querySelectorAll('.scseo-image-remove').forEach(function (button) {
            if (button.dataset.scseoBound) {
                return;
            }

            button.dataset.scseoBound = '1';

            button.addEventListener('click', function (event) {
                event.preventDefault();

                var input = document.getElementById(button.dataset.input);
                var preview = document.getElementById(button.dataset.preview);

                input.value = '';
                preview.innerHTML = '';
                button.style.display = 'none';
            });
        });
    }

    function initCharCounts() {
        document.querySelectorAll('.scseo-char-count').forEach(function (counter) {
            var target = document.getElementById(counter.dataset.target);

            if (! target) {
                return;
            }

            var recommended = parseInt(counter.dataset.recommended, 10) || 0;

            function update() {
                var length = target.value.length;

                counter.textContent = length + ' characters' + (recommended ? ' (recommended up to ' + recommended + ')' : '');
                counter.classList.toggle('scseo-char-count--over', recommended > 0 && length > recommended);
            }

            target.addEventListener('input', update);
            update();
        });
    }

    function initSerpPreview() {
        var preview = document.getElementById('scseo-serp-preview');

        if (! preview) {
            return;
        }

        var titleField = document.getElementById('scseo_title');
        var descriptionField = document.getElementById('scseo_description');
        var fallbackTitle = preview.dataset.fallbackTitle || '';

        function render() {
            var title = (titleField && titleField.value) || fallbackTitle;
            var description = (descriptionField && descriptionField.value) || '';

            preview.innerHTML =
                '<p class="scseo-serp-title">' + escapeHtml(title) + '</p>' +
                '<p class="scseo-serp-url">' + escapeHtml(window.location.origin) + '</p>' +
                '<p class="scseo-serp-description">' + escapeHtml(description) + '</p>';
        }

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = value;

            return div.innerHTML;
        }

        [titleField, descriptionField].forEach(function (field) {
            if (field) {
                field.addEventListener('input', render);
            }
        });

        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initImagePickers();
        initCharCounts();
        initSerpPreview();
    });
})();
