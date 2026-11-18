/* global document, window, wp, FormData */
/**
 * پنل «تصاویر شهرستان» در صفحهٔ ویرایش (v2.22.114).
 *
 * دستور مالک: «در بخش ویرایش مقاله در پیشنویس، جایی برای آپلود تصویر بگذار که اگر
 * خواستم تصویر جدید بگذارم، حذف کنم، و در هر صورت تصویر را بتوانم تغییر بدهم.»
 * افزودن و تعویض از کتابخانهٔ رسانهٔ خود وردپرس می‌آید (آپلود تازه هم در همان
 * پنجره ممکن است)، پس قالبِ پرونده و امنیت بارگذاری همان مسیر همیشگی است.
 */
(function () {
	'use strict';

	var cfg = window.saCountyPhotos || {};

	function post(action, extra) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		body.append('post_id', cfg.postId || 0);
		Object.keys(extra || {}).forEach(function (key) {
			var value = extra[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) {
					body.append(key + '[]', item);
				});
			} else {
				body.append(key, value);
			}
		});
		return window.fetch(cfg.ajax || '', {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.json();
		});
	}

	function statusBox(box) {
		return box.querySelector('.sa-photo-admin__status');
	}

	function say(box, text) {
		var status = statusBox(box);
		if (status) {
			status.textContent = text;
		}
	}

	/**
	 * پنجرهٔ کتابخانهٔ رسانه.
	 *
	 * @param {string}   title    عنوان پنجره.
	 * @param {boolean}  multiple انتخاب چندتایی.
	 * @param {Function} onPick   با فهرست شناسه‌ها صدا زده می‌شود.
	 */
	function mediaFrame(title, multiple, onPick) {
		if (!window.wp || !wp.media) {
			window.alert('کتابخانهٔ رسانه در این صفحه بار نشده است.');
			return;
		}
		var frame = wp.media({
			title: title,
			button: { text: 'انتخاب' },
			library: { type: 'image' },
			multiple: !!multiple
		});
		frame.on('select', function () {
			var selection = frame.state().get('selection');
			var ids = [];
			selection.each(function (attachment) {
				ids.push(attachment.id);
			});
			if (ids.length) {
				onPick(ids);
			}
		});
		frame.open();
	}

	function init(box) {
		if (box.getAttribute('data-sa-photos-ready') === '1') {
			return;
		}
		box.setAttribute('data-sa-photos-ready', '1');

		var addButton = box.querySelector('.sa-photo-admin__add');
		if (addButton) {
			addButton.addEventListener('click', function () {
				mediaFrame('افزودن تصویر به این شهرستان', true, function (ids) {
					say(box, 'در حال افزودن…');
					post('sa_photo_attach', { ids: ids }).then(function (res) {
						if (res && res.success) {
							window.location.reload();
						} else {
							say(box, (res && res.data && res.data.message) || 'افزودن انجام نشد.');
						}
					}).catch(function () {
						say(box, 'افزودن انجام نشد.');
					});
				});
			});
		}

		var saveButton = box.querySelector('.sa-photo-admin__save');
		if (saveButton) {
			saveButton.addEventListener('click', function () {
				say(box, 'در حال ذخیره…');
				var body = new FormData(box);
				body.append('action', 'sa_photo_save');
				body.append('nonce', cfg.nonce || '');
				body.append('post_id', cfg.postId || 0);
				window.fetch(cfg.ajax || '', {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				}).then(function (response) {
					return response.json();
				}).then(function (res) {
					if (res && res.success) {
						say(box, 'ذخیره شد — ' + (res.data && res.data.saved ? res.data.saved : 0) + ' ردیف.');
					} else {
						say(box, (res && res.data && res.data.message) || 'ذخیره نشد.');
					}
				}).catch(function () {
					say(box, 'ذخیره نشد.');
				});
			});
		}

		box.querySelectorAll('.sa-photo-admin__swap').forEach(function (button) {
			button.addEventListener('click', function () {
				var attId = button.getAttribute('data-id');
				mediaFrame('انتخاب فایل جایگزین', false, function (ids) {
					say(box, 'در حال تعویض فایل…');
					post('sa_photo_swap', { att_id: attId, src_id: ids[0] }).then(function (res) {
						if (res && res.success) {
							window.location.reload();
						} else {
							say(box, (res && res.data && res.data.message) || 'تعویض انجام نشد.');
						}
					}).catch(function () {
						say(box, 'تعویض انجام نشد.');
					});
				});
			});
		});

		box.querySelectorAll('.sa-photo-admin__detach').forEach(function (button) {
			button.addEventListener('click', function () {
				var attId = button.getAttribute('data-id');
				if (!window.confirm('این تصویر از شهرستان برداشته شود؟ فایل در کتابخانهٔ رسانه می‌ماند.')) {
					return;
				}
				say(box, 'در حال برداشتن…');
				post('sa_photo_detach', { att_id: attId }).then(function (res) {
					if (res && res.success) {
						var row = button.closest('tr');
						if (row) {
							row.remove();
						}
						say(box, 'برداشته شد.');
					} else {
						say(box, (res && res.data && res.data.message) || 'برداشتن انجام نشد.');
					}
				}).catch(function () {
					say(box, 'برداشتن انجام نشد.');
				});
			});
		});
	}

	function initAll() {
		var box = document.getElementById('sa-county-photos');
		if (box) {
			init(box);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
}());
