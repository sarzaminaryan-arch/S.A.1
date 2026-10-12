/**
 * Sarzamin Aryan Child — progressive enhancements (no jQuery).
 * 1) Keyboard-accessible dropdown submenus. 2) Smooth in-page anchors respecting reduced motion.
 */
( function () {
	'use strict';

	// Submenu toggles on touch/keyboard: first tap opens, second follows the link.
	document.querySelectorAll( '.main-navigation .menu-item-has-children > a' ).forEach( function ( link ) {
		link.setAttribute( 'aria-haspopup', 'true' );
		link.setAttribute( 'aria-expanded', 'false' );
		link.addEventListener( 'click', function ( e ) {
			var li = link.parentElement;
			var open = li.classList.contains( 'is-open' );
			if ( window.matchMedia( '(hover: none)' ).matches || window.innerWidth <= 768 ) {
				if ( ! open ) {
					e.preventDefault();
					document.querySelectorAll( '.main-navigation .is-open' ).forEach( function ( o ) {
						o.classList.remove( 'is-open' );
						o.querySelector( 'a' ).setAttribute( 'aria-expanded', 'false' );
					} );
					li.classList.add( 'is-open' );
					link.setAttribute( 'aria-expanded', 'true' );
				}
			}
		} );
	} );

	var style = document.createElement( 'style' );
	style.textContent = '.main-navigation li.is-open > .sub-menu{display:flex;position:static;box-shadow:none;border:0;padding-inline-start:1rem}';
	document.head.appendChild( style );

	/**
	 * v2.3.0 — روی موبایل پنل منو و پنل جست‌وجو هر دو از زیر هدر باز می‌شوند.
	 * اگر هم‌زمان باز باشند روی هم می‌افتند، پس هرکدام باز شد، دیگری بسته شود.
	 */
	( function () {
		var nav    = document.getElementById( 'site-navigation' );
		var navBtn = document.querySelector( '.menu-toggle' );
		var srch   = document.getElementById( 'header-search' );
		var srchBtn = document.querySelector( '.search-toggle' );
		if ( ! nav || ! srch ) {
			return;
		}
		function closeSearch() {
			if ( ! srch.hidden ) {
				srch.hidden = true;
				if ( srchBtn ) {
					srchBtn.setAttribute( 'aria-expanded', 'false' );
				}
			}
		}
		function closeNav() {
			if ( nav.classList.contains( 'is-open' ) ) {
				nav.classList.remove( 'is-open' );
				if ( navBtn ) {
					navBtn.setAttribute( 'aria-expanded', 'false' );
				}
			}
		}
		if ( navBtn ) {
			navBtn.addEventListener( 'click', closeSearch );
		}
		if ( srchBtn ) {
			srchBtn.addEventListener( 'click', closeNav );
		}
		// کلیک بیرون، هر دو را می‌بندد.
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.sa-hf-head' ) ) {
				closeNav();
				closeSearch();
			}
		} );
	}() );

	// Reduced motion: disable smooth scrolling set by parent CSS.
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		document.documentElement.style.scrollBehavior = 'auto';
	}

	/**
	 * v2.16.0 — کادرها آرام و لغزنده یکی‌یکی ظاهر می‌شوند (درخواست مالک):
	 * نوارهای «آموزش‌ها»/استان/شهرستان/دیدنی و شبکهٔ کارت‌های «مقالات مرتبط».
	 * این فقط یک بهبود تدریجی است: کلاس‌ها را همین‌جا می‌گذاریم و اگر جاوااسکریپت
	 * نباشد یا کاربر «کاهش حرکت» خواسته باشد، همه‌چیز ساده و کامل دیده می‌شود.
	 */
	( function () {
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var groups = document.querySelectorAll( '.sa-bars, .sa-grid-cards' );
		if ( ! groups.length ) {
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				entry.target.classList.add( 'sa-in' );
				io.unobserve( entry.target );
			} );
		}, { threshold: 0.1 } );

		Array.prototype.forEach.call( groups, function ( group ) {
			var items = group.children;
			if ( ! items.length ) {
				return;
			}
			Array.prototype.forEach.call( items, function ( item, i ) {
				// تأخیر پله‌ای تا ۸ کادر؛ بعد از آن همه با یک تأخیر می‌آیند تا انتظار طولانی نشود.
				item.style.setProperty( '--sa-i', Math.min( i, 8 ) );
			} );
			group.classList.add( 'sa-stagger' );
			io.observe( group );
		} );
	}() );
} )();
