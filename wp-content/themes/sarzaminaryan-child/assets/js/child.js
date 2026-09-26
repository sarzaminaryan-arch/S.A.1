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

	// Reduced motion: disable smooth scrolling set by parent CSS.
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		document.documentElement.style.scrollBehavior = 'auto';
	}
} )();
