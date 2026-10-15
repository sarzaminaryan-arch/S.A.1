/**
 * «مقالات آمادهٔ دیدنی‌ها» — دو‌نیمهٔ featured-image card generator.
 *
 * Draws the 1200×600 card on a canvas so the result matches the bundled
 * `assets/img/attraction-covers/*.webp` artwork: white, light background with
 * two halves — a watercolour painting of the place on the left and, on the
 * right (near the centre), the Persian name in bold navy with the smaller
 * spaced English name underneath, then the district/province line.  The bitmap
 * is rendered in the browser (correct Persian shaping) and posted to the REST
 * route that stores it as the post's featured image.
 *
 * @package Sarzaminaryan_Child
 */
( function () {
	'use strict';

	var cfg = window.saReadyArticles;
	if ( ! cfg ) {
		return;
	}

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

	/* --------------------------------------------------------------- layout */

	var CARD_BOX = [ 30, 30, W - 60, H - 60 ];   /* کادر سفید کارت */
	var ART      = [ 58, 58, 530, 484 ];         /* نیمهٔ نقاشی (چپ) */
	var TEXT_CX  = 872;                           /* مرکز ستون نوشته */
	var TITLE_Y  = 258;
	var LATIN_Y  = 344;
	var PLACE_Y  = 416;
	var FOOT_Y   = 506;

	var NAVY  = '#0d3a63';   /* آبی ناوی نام فارسی */
	var CYBER = '#3d7ea6';   /* نام انگلیسی و برچسب نوع */

	function spaced( text ) {
		return String( text ).toUpperCase().split( '' ).join( ' ' );
	}

	function chip( ctx, cx, cy, text, size, fill, color ) {
		ctx.save();
		ctx.font = '500 ' + size + 'px ' + FONT;
		var w = ctx.measureText( text ).width;
		var padX = 16;
		var padY = 9;
		roundRect( ctx, cx - w / 2 - padX, cy - size * 0.5 - padY, w + padX * 2, size + padY * 2, 14 );
		ctx.fillStyle = fill;
		ctx.fill();
		ctx.fillStyle = color;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillText( text, cx, cy + 1 );
		ctx.restore();
	}

	/* ------------------------------------------------------- آبرنگ (نیمهٔ چپ) */

	function blob( ctx, cx, cy, r, ry, color, alpha ) {
		ctx.save();
		ctx.globalAlpha = alpha;
		ctx.fillStyle = color;
		ctx.beginPath();
		ctx.ellipse( cx, cy, r, ry || r, 0, 0, Math.PI * 2 );
		ctx.filter = 'blur(6px)';
		ctx.fill();
		ctx.restore();
	}

	function softShape( ctx, pts, color, alpha, blur ) {
		ctx.save();
		ctx.globalAlpha = alpha;
		ctx.fillStyle = color;
		ctx.filter = 'blur(' + ( blur || 3 ) + 'px)';
		ctx.beginPath();
		ctx.moveTo( pts[ 0 ][ 0 ], pts[ 0 ][ 1 ] );
		pts.slice( 1 ).forEach( function ( pt ) {
			ctx.lineTo( pt[ 0 ], pt[ 1 ] );
		} );
		ctx.closePath();
		ctx.fill();
		ctx.restore();
	}

	/* یک نقاشی آبرنگی ساده اما رنگین از همان مکان. */
	function paintScene( ctx, kind ) {
		var x = ART[ 0 ];
		var y = ART[ 1 ];
		var w = ART[ 2 ];
		var h = ART[ 3 ];
		var cx = x + w / 2;
		var horizon = y + h * 0.62;

		ctx.save();
		roundRect( ctx, x, y, w, h, 26 );
		ctx.clip();

		/* آسمان: گرادیان ملایم */
		var sky = ctx.createLinearGradient( 0, y, 0, horizon );
		sky.addColorStop( 0, '#9fd0f2' );
		sky.addColorStop( 1, '#e8f5fd' );
		ctx.fillStyle = sky;
		ctx.fillRect( x, y, w, h );

		/* خورشید با هالهٔ نرم */
		var sunX = x + w * ( 'desert' === kind ? 0.30 : 0.72 );
		var sunY = y + h * 0.16;
		var halo = ctx.createRadialGradient( sunX, sunY, 6, sunX, sunY, h * 0.30 );
		halo.addColorStop( 0, 'rgba(255,236,170,0.95)' );
		halo.addColorStop( 1, 'rgba(255,236,170,0)' );
		ctx.fillStyle = halo;
		ctx.fillRect( x, y, w, h );
		blob( ctx, sunX, sunY, w * 0.052, w * 0.052, '#ffc93c', 0.95 );

		/* ابرها */
		blob( ctx, x + w * 0.28, y + h * 0.15, w * 0.10, h * 0.030, '#ffffff', 0.55 );
		blob( ctx, x + w * 0.40, y + h * 0.20, w * 0.07, h * 0.022, '#ffffff', 0.45 );

		var peaks = function ( baseY, height, color, alpha ) {
			softShape( ctx, [
				[ x - 20, baseY ], [ cx - w * 0.22, baseY - height ],
				[ cx + w * 0.06, baseY ], [ x + w * 0.62, baseY - height * 0.78 ],
				[ x + w + 20, baseY ], [ x + w + 20, y + h + 20 ], [ x - 20, y + h + 20 ]
			], color, alpha, 4 );
		};

		if ( 'desert' === kind ) {
			softShape( ctx, [ [ x - 20, horizon ], [ cx, horizon - h * 0.16 ],
				[ x + w + 20, horizon - h * 0.05 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#f6c76e', 0.9, 5 );
			for ( var d = 0; d < 5; d++ ) {
				var dx = x + w * ( 0.12 + d * 0.19 );
				softShape( ctx, [ [ dx - w * 0.06, y + h * 0.94 ], [ dx - w * 0.04, y + h * 0.58 ],
					[ dx, y + h * 0.48 ], [ dx + w * 0.05, y + h * 0.62 ],
					[ dx + w * 0.07, y + h * 0.94 ] ], d % 2 ? '#e0a05a' : '#edc07a', 0.9, 3 );
			}
			softShape( ctx, [ [ x - 20, y + h * 0.80 ], [ cx, y + h * 0.74 ],
				[ x + w + 20, y + h * 0.82 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#f8dca6', 0.85, 5 );
		} else if ( 'castle' === kind || 'monument' === kind || 'historical' === kind ) {
			softShape( ctx, [ [ x - 20, horizon + h * 0.06 ], [ cx - w * 0.24, horizon - h * 0.06 ],
				[ cx + w * 0.20, horizon + h * 0.02 ], [ x + w + 20, horizon + h * 0.08 ],
				[ x + w + 20, y + h ], [ x - 20, y + h ] ], '#8ec97a', 0.85, 5 );
			softShape( ctx, [ [ cx - w * 0.36, horizon + h * 0.14 ], [ cx - w * 0.36, horizon - h * 0.16 ],
				[ cx + w * 0.36, horizon - h * 0.16 ], [ cx + w * 0.36, horizon + h * 0.14 ] ],
				'monument' === kind ? '#d9a173' : '#d2855a', 0.94, 2 );
			for ( var t = 0; t < 3; t++ ) {
				var tx = cx + ( t - 1 ) * w * 0.24;
				var th = h * ( t === 1 ? 0.34 : 0.26 );
				softShape( ctx, [ [ tx - w * 0.055, horizon + h * 0.14 ], [ tx - w * 0.055, horizon - th ],
					[ tx + w * 0.055, horizon - th ], [ tx + w * 0.055, horizon + h * 0.14 ] ],
					'monument' === kind ? '#d9a173' : '#d2855a', 0.95, 2 );
				if ( 'monument' === kind ) {
					blob( ctx, tx, horizon - th - h * 0.02, w * 0.05, w * 0.035, '#2fbcba', 0.92 );
				}
			}
			if ( 'monument' === kind ) {
				blob( ctx, cx, horizon - h * 0.36, w * 0.15, w * 0.13, '#2fbcba', 0.92 );
			}
		} else if ( 'waterfall' === kind ) {
			softShape( ctx, [ [ x - 20, y + h ], [ x - 20, y + h * 0.10 ], [ x + w * 0.30, y + h * 0.16 ],
				[ x + w * 0.26, y + h ], ], '#d99a68', 0.92, 4 );
			softShape( ctx, [ [ x + w + 20, y + h ], [ x + w + 20, y + h * 0.06 ],
				[ x + w * 0.68, y + h * 0.14 ], [ x + w * 0.72, y + h ] ], '#c67d52', 0.92, 4 );
			softShape( ctx, [ [ cx - w * 0.10, y + h * 0.16 ], [ cx + w * 0.10, y + h * 0.18 ],
				[ cx + w * 0.13, horizon ], [ cx - w * 0.13, horizon ] ], '#f4fbff', 0.96, 3 );
			for ( var v = -1; v <= 1; v++ ) {
				ctx.save();
				ctx.globalAlpha = 0.5;
				ctx.strokeStyle = '#9fd8f2';
				ctx.lineWidth = 4;
				ctx.filter = 'blur(2px)';
				ctx.beginPath();
				ctx.moveTo( cx + v * w * 0.06, y + h * 0.20 );
				ctx.lineTo( cx + v * w * 0.08, horizon );
				ctx.stroke();
				ctx.restore();
			}
			blob( ctx, cx, horizon + h * 0.05, w * 0.34, h * 0.09, '#2b93d6', 0.92 );
			blob( ctx, cx, horizon + h * 0.02, w * 0.26, h * 0.04, '#f7fdff', 0.6 );
			softShape( ctx, [ [ x - 20, y + h * 0.84 ], [ x + w * 0.5, y + h * 0.80 ],
				[ x + w + 20, y + h * 0.86 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#7cc476', 0.9, 5 );
		} else if ( 'lake' === kind || 'wetland' === kind ) {
			peaks( horizon, h * 0.40, '#8e9ac4', 0.9 );
			blob( ctx, cx + w * 0.02, horizon - h * 0.24, w * 0.16, h * 0.09, '#ffffff', 0.75 );
			softShape( ctx, [ [ x - 20, horizon - h * 0.02 ], [ cx, horizon - h * 0.06 ],
				[ x + w + 20, horizon - h * 0.02 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#7ec877', 0.85, 5 );
			blob( ctx, cx, horizon + h * 0.07, w * 0.42, h * 0.10, '#2b93d6', 0.95 );
			blob( ctx, cx, horizon + h * 0.05, w * 0.34, h * 0.06, '#0f5fa8', 0.55 );
			blob( ctx, cx - w * 0.10, horizon + h * 0.045, w * 0.12, h * 0.015, '#cdebff', 0.6 );
			if ( 'wetland' === kind ) {
				for ( var r = 0; r < 9; r++ ) {
					var rx = x + w * ( 0.06 + r * 0.035 );
					ctx.save();
					ctx.globalAlpha = 0.75;
					ctx.strokeStyle = '#1f7a54';
					ctx.lineWidth = 3;
					ctx.beginPath();
					ctx.moveTo( rx, horizon + h * 0.02 );
					ctx.lineTo( rx + 3, horizon - h * 0.12 );
					ctx.stroke();
					ctx.restore();
				}
			}
		} else if ( 'city' === kind || 'village' === kind ) {
			peaks( horizon, h * 0.24, '#a8b3d6', 0.75 );
			softShape( ctx, [ [ x - 20, horizon - h * 0.04 ], [ cx, horizon - h * 0.08 ],
				[ x + w + 20, horizon - h * 0.04 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#7ec877', 0.85, 5 );
			for ( var row = 0; row < 3; row++ ) {
				for ( var col = 0; col < 4; col++ ) {
					var hx = x + w * ( 0.10 + col * 0.22 ) + row * w * 0.03;
					var hy = horizon - h * 0.30 + row * h * 0.11;
					softShape( ctx, [ [ hx, hy ], [ hx + w * 0.16, hy ], [ hx + w * 0.16, hy + h * 0.13 ],
						[ hx, hy + h * 0.13 ] ], '#faf1d6', 0.95, 2 );
					softShape( ctx, [ [ hx - 3, hy + 1 ], [ hx + w * 0.16 + 3, hy + 1 ],
						[ hx + w * 0.14, hy - h * 0.05 ], [ hx + w * 0.02, hy - h * 0.05 ] ], '#e0644a', 0.95, 2 );
					softShape( ctx, [ [ hx + w * 0.05, hy + h * 0.05 ], [ hx + w * 0.09, hy + h * 0.05 ],
						[ hx + w * 0.09, hy + h * 0.10 ], [ hx + w * 0.05, hy + h * 0.10 ] ], '#334c78', 0.9, 1 );
				}
			}
		} else if ( 'cave' === kind ) {
			softShape( ctx, [ [ x - 20, y + h ], [ x - 20, y + h * 0.30 ], [ cx - w * 0.30, y + h * 0.16 ],
				[ cx + w * 0.34, y + h * 0.22 ], [ x + w + 20, y + h * 0.38 ], [ x + w + 20, y + h ] ],
				'#d9a173', 0.95, 4 );
			softShape( ctx, [ [ cx - w * 0.24, y + h * 0.86 ], [ cx - w * 0.24, y + h * 0.52 ],
				[ cx, y + h * 0.34 ], [ cx + w * 0.24, y + h * 0.52 ], [ cx + w * 0.24, y + h * 0.86 ] ],
				'#31415f', 0.95, 3 );
			blob( ctx, cx, y + h * 0.46, w * 0.13, h * 0.06, '#ffe8a8', 0.5 );
			blob( ctx, cx, horizon + h * 0.16, w * 0.34, h * 0.08, '#2fbcba', 0.9 );
		} else if ( 'forest' === kind || 'park' === kind ) {
			softShape( ctx, [ [ x - 20, horizon - h * 0.10 ], [ cx, horizon - h * 0.18 ],
				[ x + w + 20, horizon - h * 0.08 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#6bbf6f', 0.8, 6 );
			softShape( ctx, [ [ x - 20, horizon + h * 0.06 ], [ cx, horizon - h * 0.02 ],
				[ x + w + 20, horizon + h * 0.06 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#86cd7c', 0.9, 5 );
			for ( var tr = 0; tr < 5; tr++ ) {
				var tx2 = x + w * ( 0.10 + tr * 0.20 );
				blob( ctx, tx2, horizon - h * 0.02, w * 0.075, h * 0.075,
					tr % 2 ? '#3f9c62' : '#57b264', 0.92 );
				if ( 'forest' === kind ) {
					softShape( ctx, [ [ tx2 - w * 0.02, horizon + h * 0.02 ], [ tx2 - w * 0.01, horizon - h * 0.14 ],
						[ tx2 + w * 0.01, horizon - h * 0.14 ], [ tx2 + w * 0.02, horizon + h * 0.02 ] ], '#2f7a54', 0.9, 2 );
				}
			}
		} else {
			peaks( horizon, h * 0.40, '#8e9ac4', 0.9 );
			blob( ctx, cx - w * 0.22, horizon - h * 0.28, w * 0.13, h * 0.07, '#ffffff', 0.8 );
			softShape( ctx, [ [ x - 20, horizon - h * 0.02 ], [ cx, horizon - h * 0.06 ],
				[ x + w + 20, horizon - h * 0.02 ], [ x + w + 20, y + h ], [ x - 20, y + h ] ], '#82c97a', 0.88, 5 );
			var stream = ctx.createLinearGradient( 0, horizon, 0, y + h );
			stream.addColorStop( 0, 'rgba(43,147,214,0.85)' );
			stream.addColorStop( 1, 'rgba(43,147,214,0.25)' );
			ctx.save();
			ctx.fillStyle = stream;
			ctx.beginPath();
			ctx.moveTo( cx - w * 0.05, horizon );
			ctx.quadraticCurveTo( cx + w * 0.08, horizon + h * 0.12, cx - w * 0.02, y + h * 0.98 );
			ctx.lineTo( cx + w * 0.12, y + h * 0.98 );
			ctx.quadraticCurveTo( cx + w * 0.18, horizon + h * 0.12, cx + w * 0.05, horizon );
			ctx.closePath();
			ctx.filter = 'blur(3px)';
			ctx.fill();
			ctx.restore();
		}

		ctx.restore();

		/* کادر نرم نیمهٔ نقاشی */
		roundRect( ctx, x, y, w, h, 26 );
		ctx.strokeStyle = '#e3ecf3';
		ctx.lineWidth = 1.5;
		ctx.stroke();
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

	/* ----------------------------------------------------------------- card */

	/* نام فارسی: درشت، بولد و آبی ناوی؛ در صورت نیاز دو خط. */
	function faTitle( ctx, text, cx, y, maxWidth ) {
		ctx.save();
		ctx.direction = RTL;
		var size = fitFontSize( ctx, text, maxWidth, 92, 44, '800' );
		var lines = [ text ];
		if ( ctx.measureText( text ).width > maxWidth ) {
			var body = String( text );
			var brack = body.indexOf( '(' );
			if ( brack > 0 ) {
				lines = [ body.slice( 0, brack ).trim(), body.slice( brack ).trim() ];
			} else {
				lines = wrapLines( ctx, body, maxWidth, '800', size );
			}
		}
		var step = size * 1.06;
		var top = y - ( lines.length - 1 ) * step / 2;
		ctx.font = '800 ' + size + 'px ' + FONT;
		ctx.fillStyle = NAVY;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		lines.forEach( function ( line, index ) {
			ctx.fillText( line, cx, top + index * step );
		} );
		ctx.restore();
		return lines.length;
	}

	/* نام انگلیسی: کوچک‌تر، با فاصلهٔ حروف (حس قلم نمایشی) و در یک یا دو خط. */
	function latinTitle( ctx, text, cx, y, maxWidth ) {
		var upper = String( text || '' ).toUpperCase();
		var size = fitFontSize( ctx, spaced( upper ), maxWidth, 33, 17, '600' );
		var line = spaced( upper );
		var lines = [ line ];
		if ( ctx.measureText( line ).width > maxWidth && upper.indexOf( ' ' ) > 0 ) {
			var mid = upper.split( ' ' );
			var half = Math.ceil( mid.length / 2 );
			lines = [ spaced( mid.slice( 0, half ).join( ' ' ) ), spaced( mid.slice( half ).join( ' ' ) ) ];
			size = Math.max( 17, Math.round( size * 0.66 ) );
		}
		var step = size * 1.16;
		var top = y - ( lines.length - 1 ) * step / 2;
		ctx.save();
		ctx.font = '600 ' + size + 'px ' + FONT;
		ctx.fillStyle = CYBER;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		lines.forEach( function ( text2, index ) {
			ctx.fillText( text2, cx, top + index * step );
		} );
		ctx.restore();
	}

	function draw( canvas, data ) {
		var ctx = canvas.getContext( '2d' );
		var kind = data.kind || 'mountain';
		var chipText = data.chipFa || 'نمای برتر';

		ctxReset( canvas );
		ctx.direction = 'ltr';
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, W, H );

		/* کارت سفید لایت با سایهٔ نرم */
		ctx.save();
		ctx.shadowColor = 'rgba(1,31,61,0.18)';
		ctx.shadowBlur = 64;
		ctx.shadowOffsetY = 16;
		roundRect( ctx, CARD_BOX[ 0 ], CARD_BOX[ 1 ], CARD_BOX[ 2 ], CARD_BOX[ 3 ], 38 );
		ctx.fillStyle = '#ffffff';
		ctx.fill();
		ctx.restore();

		roundRect( ctx, CARD_BOX[ 0 ], CARD_BOX[ 1 ], CARD_BOX[ 2 ], CARD_BOX[ 3 ], 38 );
		ctx.strokeStyle = '#e7eef4';
		ctx.lineWidth = 1.4;
		ctx.stroke();

		/* نیمهٔ چپ: نقاشی آبرنگ همان مکان */
		paintScene( ctx, kind );

		/* نیمهٔ راست: نام فارسی (ناوی) + نام انگلیسی کوچک‌تر زیر آن */
		chip( ctx, TEXT_CX, 152, chipText, 18, '#eef4fb', CYBER );
		faTitle( ctx, data.title || '', TEXT_CX, TITLE_Y, 470 );
		latinTitle( ctx, data.english || '', TEXT_CX, LATIN_Y, 452 );

		ctx.save();
		ctx.direction = RTL;
		ctx.font = '500 22px ' + FONT;
		ctx.fillStyle = '#4d6b7d';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillText( data.location || '', TEXT_CX, PLACE_Y );
		ctx.restore();

		ctx.save();
		ctx.font = '500 15px ' + FONT;
		ctx.fillStyle = '#a9b8c6';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillText( 'sarzaminaryan.ir', TEXT_CX, FOOT_Y );
		ctx.restore();
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
							badge.textContent = 'کارت قالب';
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
			chipFa: button.getAttribute( 'data-chip-fa' ) || 'نمای برتر',
			location: button.getAttribute( 'data-location' ) || '',
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
