/**
 * WordPress Settings Library - Media Field
 *
 * A hidden attachment id driven by the wp.media modal. The preview built here
 * mirrors WP_Setting::media_preview(), so a choice made in the browser renders
 * the way a saved one does after reload.
 */
/* global wp */
(function () {
    'use strict';

    function previewFor(attachment, size) {
        if (attachment.type === 'image') {
            var img = document.createElement('img');
            var sized = attachment.sizes && attachment.sizes[size];
            img.className = 'wps-media-image';
            img.src = (sized || attachment).url;
            img.alt = attachment.alt || attachment.title || '';
            return img;
        }

        var name = document.createElement('span');
        name.className = 'wps-media-filename';
        name.textContent = attachment.filename || attachment.title || '';
        return name;
    }

    function initField(field) {
        var input = field.querySelector('.wps-media-value');
        var preview = field.querySelector('.wps-media-preview');
        var choose = field.querySelector('.wps-media-choose');
        var remove = field.querySelector('.wps-media-remove');
        var mimeTypes = JSON.parse(field.dataset.mimeTypes || '[]');
        var frame = null;

        function set(attachment) {
            input.value = attachment ? String(attachment.id) : '';
            if (attachment) {
                preview.replaceChildren(previewFor(attachment, field.dataset.size || 'medium'));
            } else {
                preview.replaceChildren();
            }
            remove.hidden = !attachment;
            // Conditional visibility listens for change on the named input.
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Built on first use, not on load: the modal is heavy, and most visits
        // to a settings page never open it.
        function getFrame() {
            if (frame) {
                return frame;
            }

            frame = wp.media({
                title: field.dataset.frameTitle,
                multiple: false,
                library: mimeTypes.length ? { type: mimeTypes } : {},
                button: { text: 'Select' },
            });

            // Opens on the current choice, so replacing starts from what is there.
            frame.on('open', function () {
                var selection = frame.state().get('selection');
                var current = input.value ? wp.media.attachment(input.value) : null;
                if (current) {
                    current.fetch();
                }
                selection.reset(current ? [current] : []);
            });

            frame.on('select', function () {
                var chosen = frame.state().get('selection').first();
                if (chosen) {
                    set(chosen.toJSON());
                }
            });

            return frame;
        }

        choose.addEventListener('click', function () {
            getFrame().open();
        });

        remove.addEventListener('click', function () {
            set(null);
            // The button just hid itself, so focus would otherwise drop to the body.
            choose.focus();
        });
    }

    document.querySelectorAll('.wps-media').forEach(initField);
})();
