/**
 * Repaints an email preview as its watched subject and body inputs change.
 *
 * The server rendered sentinels through the site's mail filter and sent back
 * the markup it found around them (see WP_Settings_Email::live_template()), so
 * typed text can be rebuilt into the site's template here, with no request.
 * The text rules mirror the wrapper the template was captured from: escape,
 * link bare URLs, split on blank lines, single newlines as line breaks.
 */
(function () {
	'use strict';

	var ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

	function escapeHtml(text) {
		return text.replace(/[&<>"']/g, function (c) {
			return ESCAPES[c];
		});
	}

	function fill(template, sample) {
		return template.replace(/\{([A-Za-z0-9_]+)\}/g, function (token, name) {
			return Object.prototype.hasOwnProperty.call(sample, name) ? String(sample[name]) : token;
		});
	}

	function linkUrls(text, parts) {
		return text.replace(/\bhttps?:\/\/[^\s<]+/g, function (url) {
			var trail = '';
			// Sentence punctuation after a bare URL belongs to the sentence.
			while (url !== '' && '.,!?)'.indexOf(url.slice(-1)) !== -1) {
				trail = url.slice(-1) + trail;
				url = url.slice(0, -1);
			}
			return parts.join(url) + trail;
		});
	}

	function htmlBody(text, t) {
		var escaped = linkUrls(escapeHtml(text.replace(/\r\n|\r/g, '\n')), t.link);
		var paragraphs = escaped.trim().split(/\n{2,}/).map(function (p) {
			return p.trim();
		}).filter(function (p) {
			return p !== '';
		}).map(function (p) {
			return t.open + p.split('\n').join(t.lineBreak) + t.close;
		});

		return t.prefix + paragraphs.join(t.separator) + t.suffix;
	}

	function bind(preview) {
		var dataEl = preview.querySelector('script.wps-email-preview-data');
		if (!dataEl) {
			return;
		}

		var data = JSON.parse(dataEl.textContent);
		var t = data.template;
		var subjectEl = preview.querySelector('[data-wps-email-subject]');
		var frame = preview.querySelector('iframe.wps-email-preview-frame');
		var pre = preview.querySelector('pre.wps-email-preview-text');

		function paint() {
			var subjectInput = data.watch.subject ? document.getElementById(data.watch.subject) : null;
			var bodyInput = data.watch.body ? document.getElementById(data.watch.body) : null;

			if (subjectInput && subjectEl) {
				subjectEl.textContent = t.subject[0] + fill(subjectInput.value, data.sample) + t.subject[1];
			}

			if (!bodyInput) {
				return;
			}

			var body = fill(bodyInput.value, data.sample);
			if (t.html && frame) {
				frame.srcdoc = htmlBody(body, t);
			} else if (!t.html && pre) {
				pre.textContent = t.body[0] + body + t.body[1];
			}
		}

		var ids = [data.watch.subject, data.watch.body].filter(Boolean);
		document.addEventListener('input', function (e) {
			if (e.target && ids.indexOf(e.target.id) !== -1) {
				paint();
			}
		});
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.wps-email-preview'), bind);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
