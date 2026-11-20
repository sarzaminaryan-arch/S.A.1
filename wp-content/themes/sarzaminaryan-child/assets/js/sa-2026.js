/**
 * Sarzamin Aryan — 2026 design refresh interactions.
 *
 * Vanilla JS, no dependencies. Covers:
 *   • mobile navigation toggle
 *   • "ثبت تجربه" dialog (header CTA, page CTAs, footer)
 *   • instant search of the home hero (provinces / counties / attractions)
 *   • county search + empty state on province pages
 *   • attraction category tabs on county pages
 *   • scroll-spy for the sticky jump bars
 *   • "ذخیره در برنامه سفر" bookmark on attraction pages
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initNav();
		initExperienceModal();
		initHomeSearch();
		initCountySearch();
		initAttractionTabs();
		initAutoSubmit();
		initScrollSpy();
		initBookmark();
	});

	function $(selector, scope) {
		return (scope || document).querySelector(selector);
	}

	function $$(selector, scope) {
		return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
	}

	/** Persian search normalisation (ی/ك/‌) so "كرمان" finds "کرمان". */
	function normalize(value) {
		return (value || '')
			.replace(/[\u064A\u0649]/g, 'ی')
			.replace(/\u0643/g, 'ک')
			.replace(/[\u200C\u200F\u200E]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim()
			.toLowerCase();
	}

	/* ------------------------------------------------------------------ */
	/* Mobile navigation                                                   */
	/* ------------------------------------------------------------------ */
	function initNav() {
		var toggle = $('[data-sa-toggle-nav]');
		var panel = $('#sa-mobile-nav');
		if (!toggle || !panel) {
			return;
		}

		toggle.addEventListener('click', function () {
			var open = panel.classList.toggle('hidden') === false;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ------------------------------------------------------------------ */
	/* Experience dialog                                                   */
	/* ------------------------------------------------------------------ */
	function initExperienceModal() {
		var modal = $('[data-sa-experience-modal]');
		if (!modal) {
			return;
		}

		function open(event) {
			if (event) {
				event.preventDefault();
			}
			modal.classList.remove('hidden');
			modal.classList.add('flex');
			document.documentElement.style.overflow = 'hidden';
			var focusable = $('[data-sa-close-experience]', modal);
			if (focusable) {
				focusable.focus();
			}
		}

		function close() {
			modal.classList.add('hidden');
			modal.classList.remove('flex');
			document.documentElement.style.overflow = '';
		}

		$$('[data-sa-open-experience]').forEach(function (trigger) {
			trigger.addEventListener('click', open);
		});
		$$('[data-sa-close-experience]', modal).forEach(function (button) {
			button.addEventListener('click', close);
		});
		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				close();
			}
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
				close();
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Home instant search                                                 */
	/* ------------------------------------------------------------------ */
	function initHomeSearch() {
		var input = $('[data-sa-search-input]');
		var results = $('[data-sa-search-results]');
		var indexNode = $('#sa-search-index');
		if (!input || !results || !indexNode) {
			return;
		}

		var index;
		try {
			index = JSON.parse(indexNode.textContent || '[]');
		} catch (error) {
			index = [];
		}

		var heading = $('[data-sa-search-heading]');
		var counter = $('[data-sa-search-count]');
		var chips = $$('[data-sa-search-chip]');
		var activeChip = 'all';
		var MAX = 6;

		function render() {
			var query = normalize(input.value);
			var list = index.filter(function (item) {
				if (activeChip !== 'all' && item.kind !== activeChip) {
					return false;
				}
				return query === '' || normalize(item.title).indexOf(query) !== -1;
			});

			results.innerHTML = '';
			list.slice(0, MAX).forEach(function (item) {
				var link = document.createElement('a');
				link.href = item.url;
				link.className =
					'flex items-center justify-between gap-2 bg-surface hover:bg-petrolSoft border border-line hover:border-petrol/40 rounded-xl px-3 py-2.5 transition-colors';
				link.innerHTML =
					'<span class="text-xs sm:text-sm font-bold text-ink">' +
					item.title.replace(/[&<>"']/g, '') +
					'</span><span class="text-[11px] text-muted shrink-0">' +
					item.kind +
					'</span>';
				results.appendChild(link);
			});

			if (!list.length) {
				var empty = document.createElement('p');
				empty.className = 'text-xs text-muted col-span-full';
				empty.textContent = 'نتیجه‌ای پیدا نشد؛ عبارت دیگری را امتحان کنید.';
				results.appendChild(empty);
			}

			if (heading) {
				heading.textContent = query === '' ? 'پیشنهادهای پرجستجو و سریع' : 'نتایج جست‌وجو';
			}
			if (counter) {
				counter.textContent = list.length.toLocaleString('fa-IR') + ' مورد';
			}
		}

		input.addEventListener('input', render);
		input.addEventListener('focus', render);
		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				activeChip = chip.getAttribute('data-sa-search-chip') || 'all';
				chips.forEach(function (other) {
					other.classList.remove('is-active', 'bg-petrol', 'text-white', 'font-bold');
					other.classList.add('bg-sand', 'text-ink', 'border', 'border-line');
				});
				chip.classList.add('is-active', 'bg-petrol', 'text-white', 'font-bold');
				chip.classList.remove('bg-sand', 'text-ink', 'border', 'border-line');
				render();
			});
		});

		render();
	}

	/* ------------------------------------------------------------------ */
	/* Province page: filter the county grid                               */
	/* ------------------------------------------------------------------ */
	function initCountySearch() {
		var input = $('[data-sa-county-search]');
		var grid = $('#counties-grid');
		var empty = $('#empty-state');
		var visible = $('#visible-count');
		if (!input || !grid) {
			return;
		}

		var cards = $$('[data-sa-county-card]', grid);

		function filter() {
			var query = normalize(input.value);
			var shown = 0;
			cards.forEach(function (card) {
				var haystack = normalize(card.getAttribute('data-sa-county-name') + ' ' + card.textContent);
				var match = query === '' || haystack.indexOf(query) !== -1;
				card.classList.toggle('hidden', !match);
				if (match) {
					shown++;
				}
			});
			if (empty) {
				empty.classList.toggle('hidden', shown !== 0);
			}
			if (visible) {
				visible.textContent = shown.toLocaleString('fa-IR');
			}
		}

		input.addEventListener('input', filter);

		$$('[data-sa-reset-counties]').forEach(function (button) {
			button.addEventListener('click', function () {
				input.value = '';
				filter();
				input.focus();
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* County page: attraction category tabs                               */
	/* ------------------------------------------------------------------ */
	function initAttractionTabs() {
		var tabs = $$('[data-sa-attr-filter]');
		var cards = $$('[data-sa-attr-card]');
		if (!tabs.length || !cards.length) {
			return;
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				var category = tab.getAttribute('data-sa-attr-filter');
				tabs.forEach(function (other) {
					other.classList.remove('is-active', 'bg-petrol', 'text-white', 'font-bold');
					other.classList.add('bg-surface', 'text-slate-700', 'font-semibold', 'border', 'border-line');
				});
				tab.classList.add('is-active', 'bg-petrol', 'text-white', 'font-bold');
				tab.classList.remove('bg-surface', 'text-slate-700', 'font-semibold', 'border', 'border-line');

				cards.forEach(function (card) {
					var cardCategory = card.getAttribute('data-sa-attr-card');
					card.classList.toggle('hidden', category !== 'all' && cardCategory !== category);
				});
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* Filter selects that submit their GET form on change                 */
	/* ------------------------------------------------------------------ */
	function initAutoSubmit() {
		$$('[data-sa-auto-submit]').forEach(function (field) {
			field.addEventListener('change', function () {
				var form = field.form;
				if (!form) {
					return;
				}
				// Keep the free-text query when a select changes.
				if (typeof form.requestSubmit === 'function') {
					form.requestSubmit();
				} else {
					form.submit();
				}
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* Sticky jump bar scroll-spy                                          */
	/* ------------------------------------------------------------------ */
	function initScrollSpy() {
		var links = $$('.jump-link');
		if (!links.length || !('IntersectionObserver' in window)) {
			return;
		}

		var map = {};
		var sections = [];
		links.forEach(function (link) {
			var id = (link.getAttribute('href') || '').replace('#', '');
			var section = id ? document.getElementById(id) : null;
			if (section) {
				map[id] = link;
				sections.push(section);
			}
		});
		if (!sections.length) {
			return;
		}

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					links.forEach(function (link) {
						link.classList.remove('is-active', 'bg-petrol', 'bg-brand-forest', 'text-white');
						link.classList.add('bg-surface', 'text-slate-700');
					});
					var active = map[entry.target.id];
					if (active) {
						active.classList.add('is-active', 'text-white');
						active.classList.remove('bg-surface', 'text-slate-700');
					}
				});
			},
			{ rootMargin: '-140px 0px -60% 0px', threshold: 0 }
		);

		sections.forEach(function (section) {
			observer.observe(section);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Attraction bookmark (localStorage, no server round-trip)            */
	/* ------------------------------------------------------------------ */
	function initBookmark() {
		var button = $('[data-sa-bookmark]');
		if (!button || !window.localStorage) {
			return;
		}

		var key = 'sa-trip-plan';
		var id = window.location.pathname;
		var label = $('[data-sa-bookmark-label]', button);

		function read() {
			try {
				return JSON.parse(window.localStorage.getItem(key) || '[]');
			} catch (error) {
				return [];
			}
		}

		function paint(saved) {
			button.setAttribute('aria-pressed', saved ? 'true' : 'false');
			button.classList.toggle('bg-brand-forestSoft', saved);
			if (label) {
				label.textContent = saved ? 'در برنامه سفر ذخیره شد' : 'ذخیره در برنامه سفر';
			}
		}

		paint(read().indexOf(id) !== -1);

		button.addEventListener('click', function () {
			var list = read();
			var position = list.indexOf(id);
			if (position === -1) {
				list.push(id);
			} else {
				list.splice(position, 1);
			}
			try {
				window.localStorage.setItem(key, JSON.stringify(list.slice(-60)));
			} catch (error) {
				// Storage full / disabled: keep the visual state only.
			}
			paint(position === -1);
		});
	}
})();
