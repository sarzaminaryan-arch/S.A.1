/**
 * County map facade loader (2.22.87).
 *
 * The map block prints an original schematic SVG (no iframe or external map
 * request). Load the interactive provider iframe only on an explicit click;
 * this keeps the schematic visible by default and works without WebGL.
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

	facade.addEventListener( 'click', load );
	facade.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			load();
		}
	} );
} )();
