/* global document, window, wp, FormData */
/**
 * پنل «گالری تصاویر شهرستان» در صفحهٔ ویرایش (v2.22.115 → v2.22.116).
 *
 * افزودن، تعویض، برداشتن، انتقال به زباله‌دان، مرتب‌سازی بالا/پایین، ذخیرهٔ مستقل
 * (سازگار با ویرایشگر کلاسیک و بلوکی) و بهینه‌سازی WebP زیر ۱۰۰ کیلوبایت.
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

	function collectBoxFields(box) {
		var body = new FormData();
		box.querySelectorAll('input[name^="sa_photo_"], select[name^="sa_photo_"], textarea[name^="sa_photo_"]').forEach(function (field) {
			if (field.disabled || !field.name) {
				return;
			}
			if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
				return;
			}
			body.append(field.name, field.value);
		});
		return body;
	}

	function say(box, text) {
		var status = box.querySelector('.sa-photo-admin__status');
		if (status) {
			status.textContent = text;
		}
	}

	function renumberRows(box) {
		box.querySelectorAll('tbody tr[data-sa-photo-row]').forEach(function (row, index) {
			var input = row.querySelector('.sa-photo-admin__order-input');
			if (input) {
				input.value = String(index + 1);
			}
		});
	}

	function mediaFrame(title, multiple, onPick) {
		if (!window.wp || !wp.media) {
			window.alert('کتابخانهٔ رسانه در این صفحه بار نشده است.');
			return;
		}
		var frame = wp.media({
			title: title,
			button: { text: 'انتخاب و بهینه‌سازی WebP' },
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
				var creditToggle = box.querySelector('.sa-photo-admin__default-credit');
				var useDefaultCredit = !creditToggle || creditToggle.checked ? '1' : '';
				mediaFrame('افزودن تصویر به گالری شهرستان', true, function (ids) {
					say(box, 'در حال افزودن و تبدیل به WebP زیر ۱۰۰ کیلوبایت…');
					post('sa_photo_attach', { ids: ids, default_credit: useDefaultCredit }).then(function (res) {
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
				var body = collectBoxFields(box);
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
						say(box, 'ذخیره شد (' + (res.data && res.data.saved ? res.data.saved : 0) + ' تصویر).');
					} else {
						say(box, (res && res.data && res.data.message) || 'ذخیره نشد.');
					}
				}).catch(function () {
					say(box, 'ذخیره نشد.');
				});
			});
		}

		var optButton = box.querySelector('.sa-photo-admin__optimize');
		if (optButton) {
			optButton.addEventListener('click', function () {
				say(box, 'در حال تبدیل تصاویر به WebP زیر ۱۰۰ کیلوبایت…');
				post('sa_photo_optimize', {}).then(function (res) {
					if (res && res.success) {
						window.location.reload();
					} else {
						say(box, (res && res.data && res.data.message) || 'بهینه‌سازی انجام نشد.');
					}
				}).catch(function () {
					say(box, 'بهینه‌سازی انجام نشد.');
				});
			});
		}

		box.querySelectorAll('.sa-photo-admin__move').forEach(function (button) {
			button.addEventListener('click', function () {
				var row = button.closest('tr');
				var dir = parseInt(button.getAttribute('data-dir') || '0', 10);
				if (!row || !row.parentNode) {
					return;
				}
				if (dir < 0 && row.previousElementSibling) {
					row.parentNode.insertBefore(row, row.previousElementSibling);
					renumberRows(box);
					say(box, 'ترتیب تغییر کرد — دکمهٔ «ذخیرهٔ تغییرات گالری» را بزنید.');
				} else if (dir > 0 && row.nextElementSibling) {
					row.parentNode.insertBefore(row.nextElementSibling, row);
					renumberRows(box);
					say(box, 'ترتیب تغییر کرد — دکمهٔ «ذخیرهٔ تغییرات گالری» را بزنید.');
				}
			});
		});

		box.querySelectorAll('.sa-photo-admin__swap').forEach(function (button) {
			button.addEventListener('click', function () {
				var attId = button.getAttribute('data-id');
				mediaFrame('انتخاب تصویر جایگزین', false, function (ids) {
					say(box, 'در حال تعویض و تبدیل به WebP زیر ۱۰۰ کیلوبایت…');
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
				if (!window.confirm('این تصویر از گالری این شهرستان برداشته شود؟ (فایل در کتابخانهٔ رسانه باقی می‌ماند)')) {
					return;
				}
				say(box, 'در حال برداشتن…');
				post('sa_photo_detach', { att_id: attId }).then(function (res) {
					if (res && res.success) {
						var row = button.closest('tr');
						if (row) {
							row.remove();
							renumberRows(box);
						}
						say(box, 'از گالری برداشته شد.');
					} else {
						say(box, (res && res.data && res.data.message) || 'برداشتن انجام نشد.');
					}
				}).catch(function () {
					say(box, 'برداشتن انجام نشد.');
				});
			});
		});

		box.querySelectorAll('.sa-photo-admin__trash').forEach(function (button) {
			button.addEventListener('click', function () {
				var attId = button.getAttribute('data-id');
				if (!window.confirm('این تصویر به زباله‌دان رسانه منتقل شود؟ (تا ۳۰ روز قابل بازگردانی است)')) {
					return;
				}
				say(box, 'در حال انتقال به زباله‌دان…');
				post('sa_photo_trash', { att_id: attId }).then(function (res) {
					if (res && res.success) {
						var row = button.closest('tr');
						if (row) {
							row.remove();
							renumberRows(box);
						}
						say(box, 'به زباله‌دان منتقل شد.');
					} else {
						say(box, (res && res.data && res.data.message) || 'حذف انجام نشد.');
					}
				}).catch(function () {
					say(box, 'حذف انجام نشد.');
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
