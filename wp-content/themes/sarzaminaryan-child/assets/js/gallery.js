/* global document, window */
(function () {
	'use strict';

	var lightbox;
	var track;
	var titleEl;
	var placeEl;
	var descEl;
	var countEl;
	var downloadEl;
	var gestureEl;
	var activeAlbum = null;
	var activeIndex = 0;
	var startX = 0;
	var currentX = 0;
	var dragging = false;

	function faNumber(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹'[parseInt(digit, 10)];
		});
	}

	function ensureLightbox() {
		if (lightbox) {
			return;
		}

		lightbox = document.createElement('div');
		lightbox.className = 'sa-gallery-lightbox';
		lightbox.setAttribute('hidden', 'hidden');
		lightbox.setAttribute('role', 'dialog');
		lightbox.setAttribute('aria-modal', 'true');
		lightbox.setAttribute('aria-label', 'نمایش آلبوم تصاویر');
		lightbox.innerHTML = '' +
			'<div class="sa-gallery-lightbox__panel">' +
				'<div class="sa-gallery-lightbox__top">' +
					'<div class="sa-gallery-lightbox__bar"><span class="sa-gallery-lightbox__pulse" aria-hidden="true"></span><span class="sa-gallery-lightbox__title"></span></div>' +
					'<button type="button" class="sa-gallery-lightbox__close" aria-label="بستن">×</button>' +
				'</div>' +
				'<div class="sa-gallery-lightbox__stage">' +
					'<button type="button" class="sa-gallery-lightbox__nav sa-gallery-lightbox__nav--prev" aria-label="تصویر قبلی">‹</button>' +
					'<div class="sa-gallery-lightbox__track"></div>' +
					'<button type="button" class="sa-gallery-lightbox__nav sa-gallery-lightbox__nav--next" aria-label="تصویر بعدی">›</button>' +
					'<span class="sa-gallery-lightbox__gesture">لمس یا کشیدن آرام برای عکس بعدی</span>' +
				'</div>' +
				'<div class="sa-gallery-lightbox__meta">' +
					'<div class="sa-gallery-lightbox__caption"><strong class="sa-gallery-lightbox__place"></strong><p class="sa-gallery-lightbox__desc"></p></div>' +
					'<div class="sa-gallery-lightbox__actions"><span class="sa-gallery-lightbox__count"></span><a class="sa-gallery-lightbox__download" href="#" download>دانلود تصویر نشان‌دار</a></div>' +
				'</div>' +
			'</div>';
		document.body.appendChild(lightbox);

		track = lightbox.querySelector('.sa-gallery-lightbox__track');
		titleEl = lightbox.querySelector('.sa-gallery-lightbox__title');
		placeEl = lightbox.querySelector('.sa-gallery-lightbox__place');
		descEl = lightbox.querySelector('.sa-gallery-lightbox__desc');
		countEl = lightbox.querySelector('.sa-gallery-lightbox__count');
		downloadEl = lightbox.querySelector('.sa-gallery-lightbox__download');
		gestureEl = lightbox.querySelector('.sa-gallery-lightbox__gesture');

		lightbox.querySelector('.sa-gallery-lightbox__close').addEventListener('click', closeLightbox);
		lightbox.querySelector('.sa-gallery-lightbox__nav--prev').addEventListener('click', function () { go(-1); });
		lightbox.querySelector('.sa-gallery-lightbox__nav--next').addEventListener('click', function () { go(1); });
		lightbox.addEventListener('click', function (event) {
			if (event.target === lightbox) {
				closeLightbox();
			}
		});
		var stage = lightbox.querySelector('.sa-gallery-lightbox__stage');
		stage.addEventListener('pointerdown', onPointerDown);
		stage.addEventListener('pointermove', onPointerMove);
		stage.addEventListener('pointerup', onPointerUp);
		stage.addEventListener('pointercancel', onPointerUp);
		stage.addEventListener('click', function (event) {
			if (dragging || event.target.closest('button')) {
				return;
			}
			go(1);
		});
		document.addEventListener('keydown', function (event) {
			if (!lightbox || !lightbox.classList.contains('is-open')) {
				return;
			}
			if (event.key === 'Escape') {
				closeLightbox();
			} else if (event.key === 'ArrowLeft') {
				go(1);
			} else if (event.key === 'ArrowRight') {
				go(-1);
			}
		});
	}

	function readAlbum(scriptId) {
		var script = document.getElementById(scriptId);
		if (!script) {
			return null;
		}
		try {
			return JSON.parse(script.textContent || '{}');
		} catch (error) {
			return null;
		}
	}

	function openAlbum(album) {
		ensureLightbox();
		activeAlbum = album || {};
		activeIndex = 0;
		track.innerHTML = '';
		titleEl.textContent = activeAlbum.title || 'آلبوم تصاویر';

		var images = Array.isArray(activeAlbum.images) ? activeAlbum.images : [];
		if (!images.length) {
			track.innerHTML = '<div class="sa-gallery-lightbox__slide"><div class="sa-gallery-lightbox__empty">هنوز تصویری برای این آلبوم تأیید نشده است.</div></div>';
		} else {
			images.forEach(function (image, index) {
				var slide = document.createElement('div');
				slide.className = 'sa-gallery-lightbox__slide';
				var img = document.createElement('img');
				img.alt = image.alt || image.place || activeAlbum.title || '';
				img.decoding = 'async';
				img.loading = index === 0 ? 'eager' : 'lazy';
				img.dataset.src = image.src || image.full || image.thumb || '';
				slide.appendChild(img);
				track.appendChild(slide);
			});
		}

		lightbox.removeAttribute('hidden');
		window.requestAnimationFrame(function () {
			lightbox.classList.add('is-open');
		});
		document.documentElement.style.overflow = 'hidden';
		updateSlide(0, true);
	}

	function closeLightbox() {
		if (!lightbox) {
			return;
		}
		lightbox.classList.remove('is-open');
		document.documentElement.style.overflow = '';
		window.setTimeout(function () {
			if (!lightbox.classList.contains('is-open')) {
				lightbox.setAttribute('hidden', 'hidden');
				track.innerHTML = '';
				activeAlbum = null;
			}
		}, 260);
	}

	function imageAt(index) {
		if (!activeAlbum || !Array.isArray(activeAlbum.images)) {
			return null;
		}
		return activeAlbum.images[index] || null;
	}

	function loadNearby() {
		var slides = track.querySelectorAll('.sa-gallery-lightbox__slide img');
		[activeIndex - 1, activeIndex, activeIndex + 1].forEach(function (i) {
			if (i >= 0 && i < slides.length && !slides[i].src && slides[i].dataset.src) {
				slides[i].src = slides[i].dataset.src;
			}
		});
	}

	function updateSlide(index, instant) {
		var images = activeAlbum && Array.isArray(activeAlbum.images) ? activeAlbum.images : [];
		if (images.length) {
			activeIndex = Math.max(0, Math.min(index, images.length - 1));
		} else {
			activeIndex = 0;
		}
		if (instant) {
			track.style.transition = 'none';
		}
		track.style.transform = 'translateX(-' + (activeIndex * 100) + '%)';
		if (instant) {
			window.requestAnimationFrame(function () {
				track.style.transition = '';
			});
		}
		loadNearby();

		var image = imageAt(activeIndex);
		if (image) {
			placeEl.textContent = image.place || activeAlbum.cityTitle || 'تصویر گالری';
			var meta = [];
			if (activeAlbum.cityTitle) {
				meta.push('شهرستان ' + activeAlbum.cityTitle);
			}
			if (activeAlbum.provinceTitle) {
				meta.push('استان ' + activeAlbum.provinceTitle);
			}
			if (image.contributor) {
				meta.push('ارسالی: ' + image.contributor);
			}
			var caption = image.caption || 'توضیحی برای این تصویر ثبت نشده است.';
			descEl.textContent = (meta.length ? meta.join(' · ') + ' — ' : '') + caption;
			downloadEl.href = image.download || image.full || image.src || '#';
			downloadEl.removeAttribute('hidden');
			countEl.textContent = faNumber(activeIndex + 1) + ' / ' + faNumber(images.length);
		} else {
			placeEl.textContent = activeAlbum ? activeAlbum.cityTitle || 'آلبوم آماده' : 'آلبوم آماده';
			descEl.textContent = 'بعد از تأیید تصاویر، توضیح و نام مکان در این بخش نمایش داده می‌شود.';
			downloadEl.setAttribute('hidden', 'hidden');
			countEl.textContent = '۰ / ۰';
		}
		if (gestureEl) {
			gestureEl.style.opacity = images.length > 1 ? '1' : '0';
		}
	}

	function go(delta) {
		var total = activeAlbum && Array.isArray(activeAlbum.images) ? activeAlbum.images.length : 0;
		if (total < 2) {
			return;
		}
		var next = activeIndex + delta;
		if (next >= total) {
			next = 0;
		} else if (next < 0) {
			next = total - 1;
		}
		updateSlide(next, false);
	}

	function onPointerDown(event) {
		if (!activeAlbum || !Array.isArray(activeAlbum.images) || activeAlbum.images.length < 2) {
			return;
		}
		dragging = true;
		startX = event.clientX;
		currentX = startX;
		track.style.transition = 'none';
		if (event.currentTarget.setPointerCapture) {
			event.currentTarget.setPointerCapture(event.pointerId);
		}
	}

	function onPointerMove(event) {
		if (!dragging) {
			return;
		}
		currentX = event.clientX;
		var diff = currentX - startX;
		track.style.transform = 'translateX(calc(-' + (activeIndex * 100) + '% + ' + diff + 'px))';
	}

	function onPointerUp() {
		if (!dragging) {
			return;
		}
		var diff = currentX - startX;
		dragging = false;
		track.style.transition = '';
		if (Math.abs(diff) > 48) {
			go(diff < 0 ? 1 : -1);
		} else {
			updateSlide(activeIndex, false);
		}
	}

	function bindCards() {
		document.querySelectorAll('[data-sa-gallery-album]').forEach(function (card) {
			var open = function () {
				var album = readAlbum(card.getAttribute('data-sa-gallery-album'));
				if (album) {
					openAlbum(album);
				}
			};
			card.addEventListener('click', function (event) {
				if (event.target.closest('[data-sa-gallery-stop]')) {
					return;
				}
				open();
			});
			card.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					open();
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindCards);
	} else {
		bindCards();
	}
}());
