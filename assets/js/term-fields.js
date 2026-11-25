/**
 * Artist term fields: media picker for the artist photo.
 */
(function () {
	'use strict';

	var config = window.WordPressPlaylistsTermFields || {};
	var frame = null;

	function containerFor(element) {
		return element.closest('.form-field, tr.form-field, tr');
	}

	function previewFor(container) {
		return container ? container.querySelector('[data-wppl-preview]') : null;
	}

	function inputFor(container) {
		return container ? container.querySelector('#wppl-artist-image') : null;
	}

	function removeButtonFor(container) {
		return container ? container.querySelector('[data-wppl-media-remove]') : null;
	}

	function setImage(container, attachment) {
		var input = inputFor(container);
		var preview = previewFor(container);
		var remove = removeButtonFor(container);

		if (!input) {
			return;
		}

		input.value = attachment ? String(attachment.id) : '0';

		if (preview) {
			preview.textContent = '';

			if (attachment) {
				var image = document.createElement('img');
				var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				image.src = url;
				image.alt = '';
				image.style.maxWidth = '120px';
				image.style.height = 'auto';
				image.style.borderRadius = '8px';
				preview.appendChild(image);
			}
		}

		if (remove) {
			remove.hidden = !attachment;
		}
	}

	document.addEventListener('click', function (event) {
		var select = event.target.closest('[data-wppl-media-select]');
		var remove = event.target.closest('[data-wppl-media-remove]');

		if (remove) {
			event.preventDefault();
			setImage(containerFor(remove), null);
			return;
		}

		if (!select) {
			return;
		}

		event.preventDefault();

		if (!window.wp || !window.wp.media) {
			return;
		}

		var container = containerFor(select);

		frame = window.wp.media({
			title: config.select || 'Select image',
			button: { text: config.use || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			setImage(container, attachment);
		});

		frame.open();
	});
})();
