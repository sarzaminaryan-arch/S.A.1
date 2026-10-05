/**
 * «مقالات آمادهٔ نمای برتر» — white featured-image card generator.
 *
 * Draws the mandated card on a 1200×600 canvas: pure white background, one
 * colored corner icon, centered Persian/English text in navy / dark green /
 * dark gold and a deep shadow around the white cover — nothing else.  The
 * bitmap is rendered in the browser (correct Persian shaping) and posted to
 * the REST route that stores it as the post's featured image.
 *
 * @package Sarzaminaryan_Child
 */
( function () {
	'use strict';

	var cfg = window.saReadyArticles;
	if ( ! cfg ) {
		return;
	}

	var P    = cfg.palette || {};
	var RTL  = 'rtl';
	var FONT = '"Vazirmatn","Segoe UI",Tahoma,Arial,sans-serif';
	var W    = ( cfg.size && cfg.size.w ) || 1200;
	var H    = ( cfg.size && cfg.size.h ) || 600;

	function roundRect( ctx, x, y, w, h, r ) {
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.lineTo( x + w - r, y );
		ctx.quadraticCurveTo( x + w, y, x + w, y + r );
		ctx.lineTo( x + w, y + h - r );
		ctx.quadraticCurveTo( x + w, y + h, x + w - r, y + h );
		ctx.lineTo( x + r, y + h );
		ctx.quadraticCurveTo( x, y + h, x, y + h - r );
		ctx.lineTo( x, y + r );
		ctx.quadraticCurveTo( x, y, x + r, y );
		ctx.closePath();
	}

	/* ---------------------------------------------------------------- icons */

	function drawIcon( ctx, kind, cx, cy, r ) {
		ctx.save();
		ctx.beginPath();
		ctx.arc( cx, cy, r, 0, Math.PI * 2 );
		ctx.fillStyle = P.mint || '#e8f6ee';
		ctx.fill();
		ctx.lineWidth = 4;
		ctx.strokeStyle = P.navy || '#011f3d';
		ctx.stroke();

		ctx.save();
		ctx.beginPath();
		ctx.arc( cx, cy, r - 2, 0, Math.PI * 2 );
		ctx.clip();
		ctx.translate( cx, cy );

		if ( 'waterfall' === kind ) {
			ctx.fillStyle = P.green || '#0f5132';
			ctx.beginPath();
			ctx.moveTo( -r, -r );
			ctx.lineTo( r, -r );
			ctx.lineTo( r, -r * 0.25 );
			ctx.quadraticCurveTo( 0, -r * 0.5, -r, -r * 0.15 );
			ctx.closePath();
			ctx.fill();
			ctx.fillStyle = '#ffffff';
			[-0.42, 0, 0.42].forEach( function ( k ) {
				ctx.beginPath();
				ctx.moveTo( k * r - 0.09 * r, -r * 0.4 );
				ctx.lineTo( k * r + 0.09 * r, -r * 0.4 );
				ctx.lineTo( k * r + 0.13 * r, r * 0.35 );
				ctx.lineTo( k * r - 0.13 * r, r * 0.35 );
				ctx.closePath();
				ctx.fill();
			} );
			ctx.fillStyle = P.gold || '#9b6a16';
			ctx.beginPath();
			ctx.ellipse( 0, r * 0.52, r * 0.72, r * 0.26, 0, 0, Math.PI * 2 );
			ctx.fill();
		} else if ( 'lake' === kind ) {
			ctx.fillStyle = P.navy || '#011f3d';
			ctx.beginPath();
			ctx.moveTo( -r, r * 0.18 );
			ctx.lineTo( -r * 0.25, -r * 0.62 );
			ctx.lineTo( r * 0.2, r * 0.02 );
			ctx.lineTo( r * 0.62, -r * 0.34 );
			ctx.lineTo( r, r * 0.18 );
			ctx.closePath();
			ctx.fill();
			ctx.fillStyle = P.green || '#0f5132';
			ctx.beginPath();
			ctx.ellipse( 0, r * 0.52, r * 0.82, r * 0.3, 0, 0, Math.PI * 2 );
			ctx.fill();
			ctx.strokeStyle = P.gold || '#9b6a16';
			ctx.lineWidth = 4;
			ctx.beginPath();
			ctx.moveTo( -r * 0.5, r * 0.5 );
			ctx.quadraticCurveTo( 0, r * 0.34, r * 0.5, r * 0.5 );
			ctx.stroke();
		} else if ( 'city' === kind ) {
			ctx.fillStyle = P.green || '#0f5132';
			ctx.fillRect( -r, r * 0.6, r * 2, r * 0.5 );
			ctx.fillStyle = P.navy || '#011f3d';
			ctx.fillRect( -r * 0.72, -r * 0.1, r * 0.46, r * 0.72 );
			ctx.fillRect( -r * 0.12, -r * 0.6, r * 0.5, r * 1.22 );
			ctx.fillRect( r * 0.46, -r * 0.28, r * 0.34, r * 0.9 );
			ctx.fillStyle = P.gold || '#9b6a16';
			ctx.fillRect( -r * 0.62, r * 0.1, r * 0.12, r * 0.12 );
			ctx.fillRect( -r * 0.62, r * 0.36, r * 0.12, r * 0.12 );
			ctx.fillRect( 0, -r * 0.42, r * 0.12, r * 0.12 );
			ctx.fillRect( 0, -r * 0.12, r * 0.12, r * 0.12 );
			ctx.fillRect( 0, r * 0.18, r * 0.12, r * 0.12 );
			ctx.fillRect( r * 0.56, -r * 0.12, r * 0.12, r * 0.12 );
		} else if ( 'castle' === kind ) {
			/* Historical stronghold: crenellated tower on a hill. */
			ctx.fillStyle = P.green || '#0f5132';
			ctx.beginPath();
			ctx.moveTo( -r, r * 0.62 );
			ctx.lineTo( -r * 0.3, r * 0.02 );
			ctx.lineTo( r * 0.38, r * 0.5 );
			ctx.lineTo( r, r * 0.3 );
			ctx.lineTo( r, r * 0.62 );
			ctx.closePath();
			ctx.fill();
			ctx.fillStyle = P.navy || '#011f3d';
			ctx.fillRect( -r * 0.9, r * 0.08, r * 0.92, r * 0.54 );
			ctx.fillRect( -r * 0.9, -r * 0.08, r * 0.17, r * 0.18 );
			ctx.fillRect( -r * 0.62, -r * 0.08, r * 0.17, r * 0.18 );
			ctx.fillRect( -r * 0.34, -r * 0.08, r * 0.17, r * 0.18 );
			ctx.fillRect( r * 0.14, -r * 0.34, r * 0.6, r * 0.96 );
			ctx.fillRect( r * 0.14, -r * 0.5, r * 0.14, r * 0.18 );
			ctx.fillRect( r * 0.36, -r * 0.5, r * 0.14, r * 0.18 );
			ctx.fillRect( r * 0.58, -r * 0.5, r * 0.14, r * 0.18 );
			ctx.fillStyle = P.gold || '#9b6a16';
			ctx.fillRect( r * 0.34, r * 0.26, r * 0.18, r * 0.36 );
			ctx.fillRect( -r * 0.66, r * 0.24, r * 0.16, r * 0.38 );
		} else {
			/* mountain / park / cave / village fall back to a mountain + sun. */
			ctx.fillStyle = P.gold || '#9b6a16';
			ctx.beginPath();
			ctx.arc( r * 0.42, -r * 0.42, r * 0.24, 0, Math.PI * 2 );
			ctx.fill();
			ctx.fillStyle = P.navy || '#011f3d';
			ctx.beginPath();
			ctx.moveTo( -r, r * 0.52 );
			ctx.lineTo( -r * 0.18, -r * 0.44 );
			ctx.lineTo( r * 0.42, r * 0.52 );
			ctx.closePath();
			ctx.fill();
			ctx.fillStyle = P.green || '#0f5132';
			ctx.beginPath();
			ctx.moveTo( -r * 0.42, r * 0.52 );
			ctx.lineTo( r * 0.3, -r * 0.16 );
			ctx.lineTo( r, r * 0.52 );
			ctx.closePath();
			ctx.fill();
		}

		ctx.restore();
		ctx.restore();
	}

	/* ----------------------------------------------------------------- text */

	function fitFontSize( ctx, text, maxWidth, start, min, weight ) {
		var size = start;
		while ( size > min ) {
			ctx.font = weight + ' ' + size + 'px ' + FONT;
			if ( ctx.measureText( text ).width <= maxWidth ) {
				return size;
			}
			size -= 2;
		}
		return min;
	}

	function wrapLines( ctx, text, maxWidth, weight, size ) {
		ctx.font = weight + ' ' + size + 'px ' + FONT;
		var words = String( text ).split( /\s+/ );
		var lines = [];
		var line  = '';
		words.forEach( function ( word ) {
			var candidate = line ? line + ' ' + word : word;
			if ( ctx.measureText( candidate ).width > maxWidth && line ) {
				lines.push( line );
				line = word;
			} else {
				line = candidate;
			}
		} );
		if ( line ) {
			lines.push( line );
		}
		return lines;
	}

	function rtlText( ctx, text, x, y, size, weight, color ) {
		ctx.save();
		ctx.direction = RTL;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillStyle = color;
		ctx.font = weight + ' ' + size + 'px ' + FONT;
		ctx.fillText( text, x, y );
		ctx.restore();
	}

	function ltrText( ctx, text, x, y, size, weight, color ) {
		ctx.save();
		ctx.direction = 'ltr';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillStyle = color;
		ctx.font = weight + ' ' + size + 'px ' + FONT;
		ctx.fillText( text, x, y );
		ctx.restore();
	}

	/* ----------------------------------------------------------------- card */

	function draw( canvas, data ) {
		ctxReset( canvas );
		var ctx = canvas.getContext( '2d' );
		var navy = P.navy || '#011f3d';
		var green = P.green || '#0f5132';
		var gold = P.gold || '#9b6a16';

		/* Pure white background — no pattern, no decoration. */
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, W, H );

		var x = 56;
		var y = 40;
		var w = W - 112;
		var h = H - 80;
		var radius = 44;

		/* Deep shadow around the white cover. */
		ctx.save();
		ctx.shadowColor = 'rgba(1,31,61,0.30)';
		ctx.shadowBlur = 72;
		ctx.shadowOffsetY = 26;
		roundRect( ctx, x, y, w, h, radius );
		ctx.fillStyle = '#ffffff';
		ctx.fill();
		ctx.shadowColor = 'rgba(1,31,61,0.18)';
		ctx.shadowBlur = 20;
		ctx.shadowOffsetY = 6;
		roundRect( ctx, x, y, w, h, radius );
		ctx.fill();
		ctx.restore();

		ctx.save();
		roundRect( ctx, x, y, w, h, radius );
		ctx.strokeStyle = 'rgba(1,31,61,0.07)';
		ctx.lineWidth = 1.5;
		ctx.stroke();
		ctx.restore();

		/* One colored corner icon (top of the reading side). */
		drawIcon( ctx, data.kind, x + w - 96, y + 92, 62 );

		var center = x + w / 2;
		var title = data.title || '';
		var titleWidth = w - 300;
		var size = fitFontSize( ctx, title, titleWidth, 64, 40, '700' );
		var lines = wrapLines( ctx, title, titleWidth, '700', size );
		if ( lines.length > 2 ) {
			size = fitFontSize( ctx, title, titleWidth, size - 4, 34, '700' );
			lines = wrapLines( ctx, title, titleWidth, '700', size );
		}
		var firstY = lines.length > 1 ? 232 : 258;
		lines.forEach( function ( line, index ) {
			rtlText( ctx, line, center, firstY + index * ( size + 14 ), size, '700', navy );
		} );

		var afterTitle = firstY + ( lines.length - 1 ) * ( size + 14 );
		rtlText( ctx, data.city || '', center, afterTitle + 78, 34, '500', green );
		rtlText( ctx, data.province || '', center, afterTitle + 122, 26, '400', green );

		/* Thin gold divider between the Persian and the English line. */
		ctx.save();
		ctx.fillStyle = gold;
		roundRect( ctx, center - 44, afterTitle + 156, 88, 5, 3 );
		ctx.fill();
		ctx.restore();

		ltrText( ctx, data.english || '', center, Math.min( H - 74, afterTitle + 210 ), 27, '500', gold );
	}

	function ctxReset( canvas ) {
		var ctx = canvas.getContext( '2d' );
		ctx.setTransform( 1, 0, 0, 1, 0, 0 );
		ctx.clearRect( 0, 0, W, H );
	}

	function exportBlob( canvas, done ) {
		if ( canvas.toBlob ) {
			canvas.toBlob( function ( blob ) {
				if ( blob && 'image/webp' === blob.type ) {
					done( blob );
					return;
				}
				canvas.toBlob( function ( png ) {
					done( png );
				}, 'image/png' );
			}, 'image/webp', 0.92 );
			return;
		}
		done( null );
	}

	function setStatus( button, text, isError ) {
		var cell = button.closest( 'td' );
		var hint = cell ? cell.querySelector( '[data-sa-status]' ) : null;
		if ( hint ) {
			hint.textContent = text;
			hint.classList.toggle( 'is-error', !! isError );
		}
	}

	function post( blob, button ) {
		var reader = new FileReader();
		reader.onload = function () {
			setStatus( button, cfg.i18n.saving, false );
			fetch( cfg.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce
				},
				body: JSON.stringify( {
					article: button.getAttribute( 'data-article' ),
					image: reader.result
				} )
			} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						throw new Error( ( result.body && result.body.message ) || cfg.i18n.failed );
					}
					setStatus( button, cfg.i18n.done, false );
					var row = button.closest( 'tr' );
					if ( row ) {
						var badge = row.querySelector( '.sa-ready__badge--cover, .sa-ready__badge--none' );
						if ( badge ) {
							badge.textContent = 'کارت سفید قالب';
							badge.className = 'sa-ready__badge sa-ready__badge--cover';
						}
					}
					window.setTimeout( function () {
						setStatus( button, '', false );
					}, 6000 );
				} )
				.catch( function ( error ) {
					setStatus( button, error.message || cfg.i18n.failed, true );
				} );
		};
		reader.onerror = function () {
			setStatus( button, cfg.i18n.failed, true );
		};
		reader.readAsDataURL( blob );
	}

	function build( button ) {
		var canvas = document.createElement( 'canvas' );
		if ( ! canvas.getContext || ! canvas.getContext( '2d' ) ) {
			setStatus( button, cfg.i18n.nocanvas, true );
			return;
		}
		canvas.width = W;
		canvas.height = H;

		setStatus( button, cfg.i18n.working, false );
		draw( canvas, {
			title: button.getAttribute( 'data-title' ) || '',
			english: button.getAttribute( 'data-english' ) || '',
			city: button.getAttribute( 'data-city' ) || '',
			province: button.getAttribute( 'data-province' ) || '',
			kind: button.getAttribute( 'data-kind' ) || 'mountain'
		} );

		exportBlob( canvas, function ( blob ) {
			if ( ! blob ) {
				setStatus( button, cfg.i18n.nocanvas, true );
				return;
			}
			post( blob, button );
		} );
	}

	function init() {
		var buttons = document.querySelectorAll( '[data-sa-cover]' );
		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				if ( button.disabled ) {
					return;
				}
				button.disabled = true;
				window.setTimeout( function () {
					button.disabled = false;
				}, 1200 );
				build( button );
			} );
		} );
	}

	if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) {
		document.fonts.ready.then( init );
	} else {
		init();
	}
} )();
