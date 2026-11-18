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
			if (image.noDownload) {
				downloadEl.setAttribute('hidden', 'hidden');
			} else {
				downloadEl.removeAttribute('hidden');
			}
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

	/**
	 * درِ ورودی لایت‌باکس برای گالری عکس شهرستان (v2.22.116).
	 * کلیک روی هر قاب `[data-sa-zoom]` همین تابع را صدا می‌زند تا همان آلبوم با
	 * نمای همان قاب باز شود؛ برای عکس‌های شهرستان دکمهٔ دانلود پنهان می‌ماند.
	 *
	 * @param {Object} album آلبوم { title, images }.
	 * @param {number} index نمای آغازین.
	 */
	window.saCountyOpenGallery = function (album, index) {
		if (!album || !Array.isArray(album.images) || !album.images.length) {
			return;
		}
		openAlbum(album);
		if (typeof index === 'number' && index > 0) {
			updateSlide(index, true);
		}
	};
}());

/* اسلایدر گالری تصاویر شهرستان (v2.22.116) ---------------------------------
 * گالری «تصاویر شهرستان» از همین پوستهٔ `.sa-slider` استفاده می‌کند: دسکتاپ سه
 * قاب، تبلت دو قاب، موبایل یک قاب. کشیدن انگشت کار خودِ مرورگر است (scroll-snap
 * + touch-action در CSS)؛ این اسکریپت دکمه‌ها، نقطه‌ها، شمارنده، کلیدهای جهت‌دار،
 * همگام‌سازی نمای فعال و پنهان‌کردن کنترل‌ها وقتی همهٔ قاب‌ها جا می‌شوند را
 * زنده می‌کند. بازکردن لایت‌باکس با `window.saCountyOpenGallery` انجام می‌شود
 * تا متغیرهای اسکوپ لایت‌باکس دست‌نخورده بمانند.
 * --------------------------------------------------------------------------- */
(function () {
	'use strict';

	function faNumber(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹'[parseInt(digit, 10)];
		});
	}

	function initSlider(slider) {
		var track = slider.querySelector('.sa-slider__track');
		if (!track || slider.getAttribute('data-sa-slider-ready') === '1') {
			return;
		}
		var slides = Array.prototype.slice.call(track.querySelectorAll('.sa-slider__slide'));
		var prev = slider.querySelector('.sa-slider__nav--prev');
		var next = slider.querySelector('.sa-slider__nav--next');
		var dotsBox = slider.querySelector('.sa-slider__dots');
		var countEl = slider.querySelector('.sa-slider__count');
		var dots = [];
		var active = 0;
		var frame = 0;

		if (slides.length < 2) {
			slider.classList.add('sa-slider--single');
			slider.setAttribute('data-sa-slider-ready', '1');
			return;
		}
		slider.setAttribute('data-sa-slider-ready', '1');

		var isRtl = window.getComputedStyle(track).direction === 'rtl';

		function slideStep() {
			var first = slides[0].getBoundingClientRect();
			var second = slides[1].getBoundingClientRect();
			var step = isRtl ? first.left - second.left : second.left - first.left;
			return step > 4 ? step : first.width;
		}

		function readIndex() {
			var trackRect = track.getBoundingClientRect();
			var firstRect = slides[0].getBoundingClientRect();
			var lastRect = slides[slides.length - 1].getBoundingClientRect();
			// لبهٔ آغاز: در راست‌به‌چپ لبهٔ راستِ اولین قاب روی لبهٔ راستِ ترک است.
			var atStart = isRtl
				? Math.abs(firstRect.right - trackRect.right) <= 2
				: Math.abs(firstRect.left - trackRect.left) <= 2;
			if (atStart) {
				return 0;
			}
			// لبهٔ پایان: چند قاب آخر دیگر به لبهٔ «آغاز» نمی‌رسند (ترک تا ته رفته)،
			// پس هندسهٔ لبه بر تقسیم فاصله اولویت دارد تا شمارنده درست بماند.
			var atEnd = isRtl
				? Math.abs(lastRect.left - trackRect.left) <= 2
				: Math.abs(lastRect.right - trackRect.right) <= 2;
			if (atEnd) {
				return slides.length - 1;
			}
			var travelled = isRtl ? firstRect.right - trackRect.right : trackRect.left - firstRect.left;
			var index = Math.round(travelled / slideStep());
			return Math.max(0, Math.min(slides.length - 1, index));
		}

		function paint(index) {
			active = index;
			dots.forEach(function (dot, i) {
				dot.classList.toggle('is-active', i === index);
				dot.setAttribute('aria-current', i === index ? 'true' : 'false');
			});
			if (countEl) {
				countEl.textContent = faNumber(index + 1) + ' از ' + faNumber(slides.length);
			}
			var fitsAll = track.scrollWidth <= track.clientWidth + 4;
			slider.classList.toggle('sa-slider--fits', fitsAll);
			if (prev) {
				prev.disabled = index === 0;
			}
			if (next) {
				var lastRect = slides[slides.length - 1].getBoundingClientRect();
				var trackRect = track.getBoundingClientRect();
				var atEnd = isRtl
					? Math.abs(lastRect.left - trackRect.left) <= 3
					: Math.abs(lastRect.right - trackRect.right) <= 3;
				next.disabled = atEnd || index === slides.length - 1;
			}
		}

		function goTo(index) {
			var target = Math.max(0, Math.min(slides.length - 1, index));
			// scrollIntoView جهت راست‌به‌چپ را خودش می‌فهمد؛ علامت scrollLeft در
			// مرورگرهای مختلف یکی نیست، پس هندسه را به مرورگر می‌سپاریم.
			slides[target].scrollIntoView({
				behavior: 'smooth',
				inline: 'start',
				block: 'nearest'
			});
			paint(target);
		}

		if (dotsBox) {
			slides.forEach(function (slide, i) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'sa-slider__dot';
				dot.setAttribute('aria-label', 'نمای ' + faNumber(i + 1));
				dot.addEventListener('click', function () {
					goTo(i);
				});
				dotsBox.appendChild(dot);
				dots.push(dot);
			});
		}

		if (prev) {
			prev.addEventListener('click', function () {
				goTo(readIndex() - 1);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				goTo(readIndex() + 1);
			});
		}

		track.addEventListener('keydown', function (event) {
			// در چیدمان راست‌به‌چپ، کلید چپ یعنی نمای بعدی.
			if (event.key === 'ArrowLeft') {
				event.preventDefault();
				goTo(readIndex() + 1);
			} else if (event.key === 'ArrowRight') {
				event.preventDefault();
				goTo(readIndex() - 1);
			} else if (event.key === 'Home') {
				event.preventDefault();
				goTo(0);
			} else if (event.key === 'End') {
				event.preventDefault();
				goTo(slides.length - 1);
			}
		});

		track.addEventListener('scroll', function () {
			if (frame) {
				window.cancelAnimationFrame(frame);
			}
			frame = window.requestAnimationFrame(function () {
				frame = 0;
				var index = readIndex();
				if (index !== active) {
					paint(index);
				}
			});
		}, { passive: true });

		// تصویرها lazy بار می‌شوند؛ پس از نشست‌کردن ابعاد، نمای فعال دوباره خوانده می‌شود.
		window.addEventListener('load', function () {
			paint(readIndex());
		});
		window.addEventListener('resize', function () {
			paint(readIndex());
		});

		paint(readIndex());
	}

	function initCountyZoom() {
		document.querySelectorAll('.sa-county-gallery').forEach(function (gallery) {
			if (gallery.getAttribute('data-sa-zoom-ready') === '1') {
				return;
			}
			gallery.setAttribute('data-sa-zoom-ready', '1');
			var frames = Array.prototype.slice.call(gallery.querySelectorAll('.sa-strip__frame[data-sa-zoom]'));
			if (!frames.length) {
				return;
			}
			var headingEl = gallery.querySelector('.sa-strip__title');
			var albumTitle = headingEl ? headingEl.textContent.trim() : 'گالری تصاویر شهرستان';
			var images = frames.map(function (el) {
				return {
					src: el.getAttribute('data-sa-zoom') || '',
					place: el.getAttribute('data-sa-title') || albumTitle,
					caption: el.getAttribute('data-sa-note') || '',
					contributor: el.getAttribute('data-sa-author') || '',
					alt: el.getAttribute('data-sa-title') || albumTitle,
					noDownload: true
				};
			});
			frames.forEach(function (el, index) {
				var downX = 0;
				var downY = 0;
				function openAt() {
					if (typeof window.saCountyOpenGallery === 'function') {
						window.saCountyOpenGallery({ title: albumTitle, images: images }, index);
					}
				}
				// کشیدن انگشت روی قاب برای ورق‌زدن است، نه بازکردن لایت‌باکس؛ پس فقط
				// وقتی باز می‌شود که انگشت/ماوس تقریباً همان‌جا رها شده باشد.
				el.addEventListener('pointerdown', function (event) {
					downX = event.clientX;
					downY = event.clientY;
				}, { passive: true });
				el.addEventListener('click', function (event) {
					var moved = Math.abs(event.clientX - downX) + Math.abs(event.clientY - downY);
					if (moved > 12) {
						return;
					}
					openAt();
				});
				el.addEventListener('keydown', function (event) {
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						openAt();
					}
				});
			});
		});
	}

	function initSliders() {
		document.querySelectorAll('[data-sa-slider]').forEach(initSlider);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSliders);
	} else {
		initSliders();
	}
}());
