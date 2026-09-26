/**
 * Sarzamin Aryan main scripts (no jQuery).
 */
( function () {
	'use strict';

	var menuToggle = document.querySelector( '.menu-toggle' );
	var navigation = document.querySelector( '.main-navigation' );

	if ( menuToggle && navigation ) {
		menuToggle.addEventListener( 'click', function () {
			var expanded = menuToggle.getAttribute( 'aria-expanded' ) === 'true';
			menuToggle.setAttribute( 'aria-expanded', String( ! expanded ) );
			navigation.classList.toggle( 'toggled' );
		} );
	}

	var searchToggle = document.querySelector( '.search-toggle' );
	var headerSearch = document.getElementById( 'header-search' );

	if ( searchToggle && headerSearch ) {
		searchToggle.addEventListener( 'click', function () {
			var hidden = headerSearch.hidden;
			headerSearch.hidden = ! hidden;
			searchToggle.setAttribute( 'aria-expanded', String( hidden ) );
			if ( hidden ) {
				var field = headerSearch.querySelector( '.search-field' );
				if ( field ) {
					field.focus();
				}
			}
		} );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		if ( navigation && navigation.classList.contains( 'toggled' ) ) {
			navigation.classList.remove( 'toggled' );
			menuToggle.setAttribute( 'aria-expanded', 'false' );
			menuToggle.focus();
		}
		if ( headerSearch && ! headerSearch.hidden ) {
			headerSearch.hidden = true;
			searchToggle.setAttribute( 'aria-expanded', 'false' );
			searchToggle.focus();
		}
	} );
} )();
