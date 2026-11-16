/**
 * County map facade loader (2.22.87).
 *
 * The map block prints a light facade (no iframe, no external request). This
 * script swaps it for the provider iframe only when it is about to scroll into
 * view (IntersectionObserver, 300px), or on click — and on saveData / 2g
 * connections only on click. Zero first-load cost, zero layout shift.
 */
( function () {
	'use strict';

	var facade = document.querySelector( '.sa-map__facade' );
	if ( ! facade ) {
		return;
	}

	var loaded = false;

	function frameUrl() {
		var lat = parseFloat( facade.dataset.lat ),
			lng = parseFloat( facade.dataset.lng ),
			z = parseInt( facade.dataset.zoom, 10 ) || 10;

		if ( facade.dataset.provider === 'google' ) {
			return 'https://maps.google.com/maps?q=' + lat + ',' + lng + '&z=' + z + '&output=embed&hl=fa';
		}

		/* Keyless official OSM embed: a zoom-sized bbox around the pin. */
		var h = 0.09 * Math.pow( 2, 10 - z ),
			v = h * 0.62;
		return (
			'https://www.openstreetmap.org/export/embed.html?bbox=' +
			( lng - h ) + ',' + ( lat - v ) + ',' + ( lng + h ) + ',' + ( lat + v ) +
			'&layer=mapnik&marker=' + lat + ',' + lng
		);
	}

	function load() {
		if ( loaded ) {
			return;
		}
		loaded = true;

		var frame = document.createElement( 'iframe' );
		frame.className = 'sa-map__frame';
		frame.title = 'نقشهٔ موقعیت ' + ( facade.dataset.label || '' );
		frame.loading = 'lazy';
		frame.setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
		frame.src = frameUrl();

		facade.parentNode.replaceChild( frame, facade );
	}

	var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || {};
	var slow = conn.saveData || /(^|\b)(2g|slow)(\b|$)/.test( conn.effectiveType || conn.effectiveMode || '' );

	facade.addEventListener( 'click', load );
	facade.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			load();
		}
	} );

	if ( slow ) {
		return; /* Click-only on metered/slow connections. */
	}

	if ( 'IntersectionObserver' in window ) {
		var io = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						io.disconnect();
						load();
					}
				} );
			},
			{ rootMargin: '300px' }
		);
		io.observe( facade );
	} else {
		load();
	}
} )();
