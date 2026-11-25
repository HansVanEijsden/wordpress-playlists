/**
 * WordPress Playlists — public presentation.
 *
 * Progressive enhancement only: the page reads fine without this file. It
 * marks the currently playing row on today's playlist, copies playlist links
 * and hands archive episodes to the shared Broadcast Archive player.
 */
(function () {
	'use strict';

	var config = window.WordPressPlaylistsDisplay || {};

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	/**
	 * Current date and minutes in the station timezone.
	 */
	function stationNow() {
		if (config.nowOverride && config.nowDate) {
			var override = /^(\d{1,2}):(\d{2})$/.exec(config.nowOverride);

			if (override) {
				return {
					date: config.nowDate,
					minutes: parseInt(override[1], 10) * 60 + parseInt(override[2], 10)
				};
			}
		}

		var timeZone = config.timeZone || 'UTC';
		var fallback = new Date();

		try {
			var formatter = new Intl.DateTimeFormat('en-CA', {
				timeZone: timeZone,
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
				hourCycle: 'h23'
			});
			var parts = formatter.formatToParts(fallback);
			var values = {};

			parts.forEach(function (part) {
				if (part.type !== 'literal') {
					values[part.type] = part.value;
				}
			});

			return {
				date: values.year + '-' + values.month + '-' + values.day,
				minutes: (parseInt(values.hour, 10) % 24) * 60 + parseInt(values.minute, 10)
			};
		} catch (error) {
			return {
				date: fallback.getFullYear() + '-' + pad(fallback.getMonth() + 1) + '-' + pad(fallback.getDate()),
				minutes: fallback.getHours() * 60 + fallback.getMinutes()
			};
		}
	}

	/**
	 * Mark the row that is on air now.
	 */
	function markNow() {
		var sheet = document.querySelector('[data-wppl-sheet]');
		if (!sheet) {
			return;
		}

		var date = sheet.getAttribute('data-wppl-date');
		if (!date) {
			return;
		}

		var now = stationNow();
		if (now.date !== date) {
			return;
		}

		var rows = Array.prototype.slice.call(sheet.querySelectorAll('[data-wppl-time]'));
		var starts = rows.map(function (row) {
			var match = /^(\d{1,2}):(\d{2})$/.exec(row.getAttribute('data-wppl-time') || '');
			return match ? parseInt(match[1], 10) * 60 + parseInt(match[2], 10) : null;
		});

		var index = -1;

		for (var i = 0; i < starts.length; i++) {
			if (starts[i] !== null && starts[i] <= now.minutes) {
				index = i;
			}
		}

		if (index === -1) {
			return;
		}

		var row = rows[index];
		var start = starts[index];
		var next = null;

		for (var j = index + 1; j < starts.length; j++) {
			if (starts[j] !== null && starts[j] > start) {
				next = starts[j];
				break;
			}
		}

		var durationSeconds = parseInt(row.getAttribute('data-wppl-duration') || '0', 10);
		var end = next;

		if (!end || durationSeconds > 0) {
			var durationMinutes = durationSeconds > 0 ? Math.max(1, Math.ceil(durationSeconds / 60)) : 0;

			if (durationMinutes > 0) {
				end = start + durationMinutes;
			}
		}

		if (!end || now.minutes >= end) {
			return;
		}

		row.classList.add('is-now');

		var timeCell = row.querySelector('.wppl-track-time');
		if (timeCell && !timeCell.querySelector('.wppl-now')) {
			var label = document.createElement('span');
			label.className = 'wppl-now';
			label.textContent = config.nowLabel || 'Now';
			timeCell.appendChild(label);
		}
	}

	/**
	 * Copy a playlist link.
	 */
	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', 'readonly');
			area.style.position = 'fixed';
			area.style.top = '-1000px';
			document.body.appendChild(area);
			area.select();

			try {
				var ok = document.execCommand('copy');
				document.body.removeChild(area);
				ok ? resolve() : reject();
			} catch (error) {
				document.body.removeChild(area);
				reject(error);
			}
		});
	}

	function bindCopyButtons() {
		document.querySelectorAll('[data-wppl-copy]').forEach(function (button) {
			button.hidden = false;
			button.addEventListener('click', function () {
				var url = button.getAttribute('data-wppl-copy') || '';
				if (!url) {
					return;
				}

				var label = button.querySelector('[data-wppl-copy-label]');
				var status = button.querySelector('[data-wppl-copy-status]');
				var original = label ? label.textContent : '';

				function tell(message) {
					if (status) {
						status.textContent = message;
					}

					if (label) {
						label.textContent = message;
						window.setTimeout(function () {
							label.textContent = original;
						}, 2000);
					}
				}

				copyText(url).then(function () {
					tell(config.copiedLabel || 'Copied');
				}).catch(function () {
					tell(config.copyError || 'Could not copy');
				});
			});
		});
	}

	/**
	 * Hand episodes to the shared Broadcast Archive player. Without the player
	 * (or valid JSON) the link keeps working as a link to the archive page.
	 */
	function bindEpisodes() {
		document.querySelectorAll('[data-wppl-episode]').forEach(function (link) {
			link.addEventListener('click', function (event) {
				if (!window.BroadcastArchivePlayer || typeof window.BroadcastArchivePlayer.play !== 'function') {
					return;
				}

				var payload = null;

				try {
					payload = JSON.parse(link.getAttribute('data-wppl-episode'));
				} catch (error) {
					return;
				}

				if (!payload || !payload.audio) {
					return;
				}

				if (window.BroadcastArchivePlayer.play(payload)) {
					event.preventDefault();
				}
			});
		});
	}

	function init() {
		markNow();
		bindCopyButtons();
		bindEpisodes();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
