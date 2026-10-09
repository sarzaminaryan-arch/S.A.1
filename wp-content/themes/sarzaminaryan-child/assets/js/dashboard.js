/**
 * Sarzamin Aryan Child — داشبورد جامع گردشگری ایران.
 *
 * صفر وابستگی: همهٔ نمودارها، جدول، نقشه و خروجی CSV با همین فایل رندر می‌شوند.
 * داده از <script type="application/json" id="sa-dash-data"> و مرزهای نقشه از
 * assets/js/iran-provinces-map.js (پروژهٔ ایران‌مپ‌کور، MIT/ODbL) می‌آید.
 *
 * @package Sarzaminaryan_Child
 */
( function () {
	'use strict';

	var dataEl = document.getElementById( 'sa-dash-data' );
	if ( ! dataEl ) {
		return;
	}

	var DATA;
	try {
		DATA = JSON.parse( dataEl.textContent );
	} catch ( e ) {
		return;
	}

	var SHAPES = window.SA_PROVINCE_SHAPES || null;

	/* ------------------------------------------------------------------ *
	 * ابزارهای عمومی
	 * ------------------------------------------------------------------ */

	var NF = new Intl.NumberFormat( 'fa-IR' );
	var NF1 = new Intl.NumberFormat( 'fa-IR', { maximumFractionDigits: 1 } );

	function faN( n ) {
		return NF.format( n );
	}
	function faN1( n ) {
		return n === null || typeof n === 'undefined' ? '—' : NF1.format( n );
	}
	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function byId( id ) {
		return document.getElementById( id );
	}
	function make( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( html ) {
			n.innerHTML = html;
		}
		return n;
	}
	function faSort( a, b ) {
		return String( a ).localeCompare( String( b ), 'fa' );
	}

	var PROVINCES = DATA.provinces.slice();
	var PROV_BY_SLUG = {};
	PROVINCES.forEach( function ( p ) {
		PROV_BY_SLUG[ p.slug ] = p;
	} );

	// رتبهٔ ملی جمعیت (برای مدال‌ها و ستون رتبه) — از کل داده، مستقل از فیلتر.
	var POP_RANK = {};
	PROVINCES.slice().sort( function ( a, b ) {
		return b.population - a.population;
	} ).forEach( function ( p, i ) {
		POP_RANK[ p.slug ] = i + 1;
	} );

	var CAT_LABEL = {
		historical: 'تاریخی و معماری',
		natural: 'طبیعی',
		religious: 'مذهبی و زیارتی',
		cultural: 'فرهنگی و موزه‌ای'
	};

	var COUNTRY_COLORS = {
		'ترکیه': '#177357',
		'عراق': '#0e93a0',
		'ارمنستان': '#b7862b',
		'جمهوری آذربایجان': '#c96a1f',
		'ترکمنستان': '#2b6a8f',
		'افغانستان': '#b5443c',
		'پاکستان': '#5f8a3d'
	};

	var DONUT_COLORS = [ '#177357', '#0e93a0', '#b7862b', '#c96a1f', '#2b6a8f', '#8aa89e' ];

	/* ----------------------- تولتیپ سراسری ----------------------- */

	var tip = byId( 'sa-tooltip' );
	function showTip( text, x, y ) {
		if ( ! tip ) {
			return;
		}
		tip.textContent = text;
		tip.hidden = false;
		var w = tip.offsetWidth;
		var left = x - w / 2;
		left = Math.max( 8, Math.min( left, document.documentElement.clientWidth - w - 8 ) );
		tip.style.left = left + 'px';
		tip.style.top = Math.max( 8, y - tip.offsetHeight - 14 ) + 'px';
	}
	function hideTip() {
		if ( tip ) {
			tip.hidden = true;
		}
	}
	function bindTip( el, text ) {
		el.addEventListener( 'pointerenter', function ( ev ) {
			showTip( text, ev.clientX, ev.clientY );
		} );
		el.addEventListener( 'pointermove', function ( ev ) {
			showTip( text, ev.clientX, ev.clientY );
		} );
		el.addEventListener( 'pointerleave', hideTip );
		el.addEventListener( 'focus', function () {
			var r = el.getBoundingClientRect();
			showTip( text, r.left + r.width / 2, r.top );
		} );
		el.addEventListener( 'blur', hideTip );
	}

	/* ----------------------- حالت فیلترها ----------------------- */

	var state = {
		province: 'all',
		popMin: 0,
		popMax: Infinity,
		areaMin: 0,
		areaMax: Infinity,
		borderOnly: false,
		search: '',
		tableBorder: false,
		sortKey: 'population',
		sortDir: 'desc',
		page: 1,
		mapMetric: 'population',
		mapSel: 'tehran',
		attrCat: 'all',
		attrSort: 'name'
	};

	var POP_LIMIT = Math.max.apply( null, PROVINCES.map( function ( p ) {
		return p.population;
	} ) );
	var AREA_LIMIT = Math.max.apply( null, PROVINCES.map( function ( p ) {
		return p.area;
	} ) );

	function filtered() {
		return PROVINCES.filter( function ( p ) {
			if ( state.province !== 'all' && p.slug !== state.province ) {
				return false;
			}
			if ( p.population < state.popMin || p.population > state.popMax ) {
				return false;
			}
			if ( p.area < state.areaMin || p.area > state.areaMax ) {
				return false;
			}
			if ( state.borderOnly && ! p.border ) {
				return false;
			}
			return true;
		} );
	}

	function scopeText( list ) {
		if ( list.length === PROVINCES.length ) {
			return 'در حال نمایش همهٔ ' + faN( PROVINCES.length ) + ' استان — بدون فیلتر فعال';
		}
		if ( ! list.length ) {
			return 'هیچ استانی با فیلترهای فعلی یافت نشد.';
		}
		return 'در حال نمایش ' + faN( list.length ) + ' استان از ' + faN( PROVINCES.length ) + ' استان با فیلترهای فعال';
	}

	function emptyHTML( msg ) {
		return '<div class="sa-empty" role="status"><p>🍂 ' + esc( msg ) + '</p>' +
			'<button type="button" class="sa-btn sa-btn--ghost sa-empty__reset">بازنشانی فیلترها</button></div>';
	}
	function bindEmptyResets( root ) {
		root.querySelectorAll( '.sa-empty__reset' ).forEach( function ( b ) {
			b.addEventListener( 'click', resetFilters );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * شاخص‌های کلیدی (۵ کارت)
	 * ------------------------------------------------------------------ */

	var KPI_ICONS = {
		provinces: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg>',
		counties: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="9" width="7" height="12" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/><path d="M6 13h1M6 17h1M17 7h1M17 11h1M17 15h1"/></svg>',
		attractions: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.5 5.2 5.7.8-4.1 4 1 5.7L12 16l-5.1 2.7 1-5.7-4.1-4 5.7-.8z"/></svg>',
		area: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4z" opacity=".4"/><path d="M4 20L20 4"/><path d="M8 4v4M16 20v-4M4 8h4M20 16h-4"/></svg>',
		population: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.6-3.5 2.8-5.2 5.5-5.2s4.9 1.7 5.5 5.2"/><circle cx="17" cy="9.5" r="2.5"/><path d="M15.8 14.9c2.3.2 4.2 1.7 4.7 4.6"/></svg>'
	};

	function renderKpis( list ) {
		var box = byId( 'sa-kpis' );
		if ( ! box ) {
			return;
		}
		var pop = 0, area = 0, counties = 0, attrs = 0;
		list.forEach( function ( p ) {
			pop += p.population;
			area += p.area;
			counties += p.counties;
			attrs += p.attractions;
		} );

		var cards = [
			{ key: 'provinces', label: 'استان', value: faN( list.length ), unit: '', tip: 'شمار استان‌های داخل فیلتر جاری' },
			{ key: 'counties', label: 'شهرستان', value: faN( counties ), unit: '', tip: 'مجموع شهرستان‌های استان‌های فیلترشده از فهرست رسمی ۴۸۳تایی' },
			{ key: 'attractions', label: 'جاذبهٔ شاخص', value: faN( attrs ), unit: '', tip: 'گزینش تحریریه؛ فهرست کامل در بخش جاذبه‌ها' },
			{ key: 'area', label: 'مساحت', value: faN( area ), unit: 'کیلومتر مربع', tip: 'مجموع مساحت استان‌های فیلترشده' },
			{ key: 'population', label: 'جمعیت', value: faN( pop ), unit: 'نفر', tip: 'مجموع جمعیت استان‌های فیلترشده؛ مبنا سرشماری ۱۳۹۵' }
		];

		box.innerHTML = '';
		cards.forEach( function ( c ) {
			var el = make( 'article', 'sa-kpi sa-kpi--' + c.key );
			el.setAttribute( 'tabindex', '0' );
			el.innerHTML =
				'<span class="sa-kpi__icon">' + KPI_ICONS[ c.key ] + '</span>' +
				'<span class="sa-kpi__body">' +
					'<span class="sa-kpi__value">' + esc( c.value ) + ( c.unit ? ' <small>' + esc( c.unit ) + '</small>' : '' ) + '</span>' +
					'<span class="sa-kpi__label">' + esc( c.label ) + '</span>' +
				'</span>';
			bindTip( el, c.tip );
			box.appendChild( el );
		} );

		var note = byId( 'sa-kpi-note' );
		if ( note ) {
			if ( list.length === PROVINCES.length ) {
				note.textContent = 'ارقام ملی: جمعیت برابر مجموع رسمی سرشماری ۱۳۹۵ (۷۹٬۹۲۶٬۲۷۰ نفر)؛ مساحت دادهٔ استانی ۱٬۶۳۸٬۰۵۳ کیلومتر مربع در برابر رقم رسمی ۱٬۶۴۸٬۱۹۵.';
			} else {
				note.textContent = 'شاخص‌ها بر اساس انتخاب و فیلترهای فعلی شما محاسبه شده‌اند.';
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * نمودارها
	 * ------------------------------------------------------------------ */

	function hbarRow( name, value, max, opts ) {
		opts = opts || {};
		var pct = max > 0 ? Math.max( 1.5, ( value / max ) * 100 ) : 0;
		var row = make( 'div', 'hbar' + ( opts.cls ? ' ' + opts.cls : '' ) );
		row.innerHTML =
			'<span class="hbar__name">' + esc( name ) + '</span>' +
			'<span class="hbar__track" role="presentation"><span class="hbar__fill" style="width:' + pct.toFixed( 2 ) + '%"></span></span>' +
			'<span class="hbar__val">' + esc( opts.valueText ) + '</span>';
		if ( opts.tip ) {
			bindTip( row, opts.tip );
		}
		return row;
	}

	// ۱) جمعیت استان‌ها — نزولی، با تأکید بر سه استان نخست.
	function renderPopChart( list ) {
		var box = byId( 'chart-pop' );
		if ( ! box ) {
			return;
		}
		if ( ! list.length ) {
			box.innerHTML = emptyHTML( 'استانی برای نمایش جمعیت پیدا نشد.' );
			bindEmptyResets( box );
			return;
		}
		var rows = list.slice().sort( function ( a, b ) {
			return b.population - a.population;
		} );
		var max = rows[ 0 ].population;
		box.innerHTML = '';
		rows.forEach( function ( p, i ) {
			var cls = i < 3 ? 'is-top is-top-' + ( i + 1 ) : '';
			var row = hbarRow( p.name, p.population, max, {
				cls: cls,
				valueText: faN( p.population ),
				tip: p.name + ' — رتبهٔ ' + faN( i + 1 ) + ' جمعیت: ' + faN( p.population ) + ' نفر'
			} );
			box.appendChild( row );
		} );
	}

	// ۲) دونات مساحت: پنج استان بزرگ + «سایر استان‌ها» (رتبه از داده، نه ثابت).
	function renderDonut( list ) {
		var box = byId( 'chart-area-donut' );
		if ( ! box ) {
			return;
		}
		if ( ! list.length ) {
			box.innerHTML = emptyHTML( 'استانی برای نمایش مساحت پیدا نشد.' );
			bindEmptyResets( box );
			return;
		}
		var sorted = list.slice().sort( function ( a, b ) {
			return b.area - a.area;
		} );
		var top = sorted.slice( 0, 5 );
		var rest = sorted.slice( 5 );
		var segs = top.map( function ( p ) {
			return { name: p.name, value: p.area };
		} );
		if ( rest.length ) {
			segs.push( {
				name: 'سایر استان‌ها',
				value: rest.reduce( function ( s, p ) {
					return s + p.area;
				}, 0 ),
				muted: true
			} );
		}
		var total = segs.reduce( function ( s, x ) {
			return s + x.value;
		}, 0 );

		var R = 74, CX = 100, CY = 100, C = 2 * Math.PI * R;
		var svg = '<svg viewBox="0 0 200 200" class="donut" role="img" aria-label="سهم مساحت پنج استان بزرگ و سایر">';
		var offset = 0;
		segs.forEach( function ( s, i ) {
			var frac = total > 0 ? s.value / total : 0;
			var dash = Math.max( 0.5, frac * C - 2 );
			svg += '<circle cx="' + CX + '" cy="' + CY + '" r="' + R + '" fill="none" stroke="' + DONUT_COLORS[ i % DONUT_COLORS.length ] + '"' +
				' stroke-width="30" stroke-dasharray="' + dash.toFixed( 2 ) + ' ' + ( C - dash ).toFixed( 2 ) + '"' +
				' stroke-dashoffset="' + ( -offset ).toFixed( 2 ) + '" transform="rotate(-90 ' + CX + ' ' + CY + ')">' +
				'<title>' + esc( s.name ) + ': ' + faN( s.value ) + ' کیلومتر مربع</title></circle>';
			offset += frac * C;
		} );
		svg += '<text x="' + CX + '" y="' + ( CY - 6 ) + '" text-anchor="middle" class="donut__num">' + faN( total ) + '</text>' +
			'<text x="' + CX + '" y="' + ( CY + 14 ) + '" text-anchor="middle" class="donut__cap">کیلومتر مربع</text></svg>';

		var legend = '<ul class="donut-legend">';
		segs.forEach( function ( s, i ) {
			var pct = total > 0 ? Math.round( ( s.value / total ) * 1000 ) / 10 : 0;
			legend += '<li' + ( s.muted ? ' class="is-muted"' : '' ) + '><span class="swatch" style="background:' + DONUT_COLORS[ i % DONUT_COLORS.length ] + '"></span>' +
				'<span class="donut-legend__name">' + esc( s.name ) + '</span>' +
				'<bdi class="donut-legend__val">' + faN( s.value ) + ' <small>(' + faN1( pct ) + '٪)</small></bdi></li>';
		} );
		legend += '</ul>';

		box.innerHTML = '<div class="donut-wrap">' + svg + legend + '</div>';
	}

	// ۳) شمار شهرستان‌ها — نزولی، برجسته‌سازی بیشترین مقدار.
	function renderCountyChart( list ) {
		var box = byId( 'chart-counties' );
		if ( ! box ) {
			return;
		}
		if ( ! list.length ) {
			box.innerHTML = emptyHTML( 'استانی برای نمایش شهرستان‌ها پیدا نشد.' );
			bindEmptyResets( box );
			return;
		}
		var rows = list.slice().sort( function ( a, b ) {
			return b.counties - a.counties || faSort( a.name, b.name );
		} );
		var max = rows[ 0 ].counties;
		box.innerHTML = '';
		rows.forEach( function ( p ) {
			var row = hbarRow( p.name, p.counties, max, {
				cls: p.counties === max ? 'is-gold' : '',
				valueText: faN( p.counties ),
				tip: p.name + ': ' + faN( p.counties ) + ' شهرستان از فهرست رسمی'
			} );
			box.appendChild( row );
		} );
	}

	// ۴) مرزهای زمینی: هر کشور همسایه و استان‌های هم‌مرز آن.
	function renderBorderChart( list ) {
		var box = byId( 'chart-borders' );
		if ( ! box ) {
			return;
		}
		var borderProvs = list.filter( function ( p ) {
			return p.border;
		} );
		if ( ! borderProvs.length ) {
			box.innerHTML = emptyHTML( 'در فیلتر فعلی، استان مرزنشینی نیست.' );
			bindEmptyResets( box );
			return;
		}
		var html = '<div class="border-rows">';
		DATA.countries.forEach( function ( country ) {
			var color = COUNTRY_COLORS[ country ] || '#446655';
			var provs = borderProvs.filter( function ( p ) {
				return p.countries.indexOf( country ) !== -1;
			} );
			html += '<div class="border-row' + ( provs.length ? '' : ' is-empty' ) + '">' +
				'<span class="border-row__country"><span class="swatch" style="background:' + color + '"></span>' + esc( country ) + '</span>' +
				'<span class="border-row__count">' + faN( provs.length ) + ' استان</span>' +
				'<span class="border-row__provs">' +
				( provs.length ? provs.map( function ( p ) {
					return '<span class="chip">' + esc( p.name ) + '</span>';
				} ).join( '' ) : '<span class="chip chip--none">—</span>' ) +
				'</span></div>';
		} );
		html += '</div>';
		var multi = borderProvs.filter( function ( p ) {
			return p.countries.length > 1;
		} );
		if ( multi.length ) {
			html += '<p class="sa-dash__fineprint">استان‌های دارای بیش از یک همسایهٔ خاکی: ' +
				multi.map( function ( p ) {
					return esc( p.name ) + ' (' + p.countries.map( esc ).join( '، ' ) + ')';
				} ).join( '؛ ' ) + '.</p>';
		}
		box.innerHTML = html;
	}

	// ۵) پراکندگی مساحت × جمعیت؛ رنگ نقطه = سطح تراکم.
	function niceCeil( v ) {
		var mag = Math.pow( 10, Math.floor( Math.log10( v || 1 ) ) );
		var n = v / mag;
		var f = n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10;
		return f * mag;
	}

	function renderScatter( list ) {
		var box = byId( 'chart-scatter' );
		if ( ! box ) {
			return;
		}
		if ( ! list.length ) {
			box.innerHTML = emptyHTML( 'استانی برای نمایش پراکندگی پیدا نشد.' );
			bindEmptyResets( box );
			return;
		}
		var W = 660, H = 400, mR = 20, mL = 86, mT = 16, mB = 46;
		var xMax = niceCeil( Math.max.apply( null, list.map( function ( p ) {
			return p.area;
		} ) ) );
		var yMax = niceCeil( Math.max.apply( null, list.map( function ( p ) {
			return p.population;
		} ) ) );
		var dens = list.map( function ( p ) {
			return p.density || 0;
		} ).sort( function ( a, b ) {
			return a - b;
		} );
		var t1 = dens[ Math.floor( dens.length / 3 ) ] || 0;
		var t2 = dens[ Math.floor( ( dens.length * 2 ) / 3 ) ] || 0;

		function x( v ) {
			return mR + ( v / xMax ) * ( W - mL - mR );
		}
		function y( v ) {
			return H - mB - ( v / yMax ) * ( H - mT - mB );
		}
		function dcolor( d ) {
			return d >= t2 ? '#c05a2e' : d >= t1 ? '#b7862b' : '#0e93a0';
		}

		var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="scatter" role="img" aria-label="نمودار پراکندگی مساحت در برابر جمعیت">';
		var i, v;
		for ( i = 0; i <= 4; i++ ) {
			v = ( yMax / 4 ) * i;
			svg += '<line x1="' + mR + '" x2="' + ( W - mL ) + '" y1="' + y( v ) + '" y2="' + y( v ) + '" class="grid"/>' +
				'<text x="' + ( mR - 8 ) + '" y="' + ( y( v ) + 4 ) + '" text-anchor="end" class="tick">' + faN( v ) + '</text>';
		}
		for ( i = 0; i <= 4; i++ ) {
			v = ( xMax / 4 ) * i;
			svg += '<line x1="' + x( v ) + '" x2="' + x( v ) + '" y1="' + mT + '" y2="' + ( H - mB ) + '" class="grid"/>' +
				'<text x="' + x( v ) + '" y="' + ( H - mB + 20 ) + '" text-anchor="middle" class="tick">' + faN( v ) + '</text>';
		}
		svg += '<text x="' + ( ( W - mL + mR ) / 2 ) + '" y="' + ( H - 6 ) + '" text-anchor="middle" class="axis-label">مساحت (کیلومتر مربع)</text>' +
			'<text x="16" y="' + ( ( H - mB + mT ) / 2 ) + '" text-anchor="middle" class="axis-label" transform="rotate(-90 16 ' + ( ( H - mB + mT ) / 2 ) + ')">جمعیت (نفر)</text>';

		list.forEach( function ( p ) {
			var cx = x( p.area ).toFixed( 1 ), cy = y( p.population ).toFixed( 1 );
			svg += '<circle cx="' + cx + '" cy="' + cy + '" r="7" fill="' + dcolor( p.density || 0 ) + '" data-slug="' + esc( p.slug ) + '" tabindex="0" role="img"' +
				' aria-label="' + esc( p.name ) + ': جمعیت ' + faN( p.population ) + ' نفر، مساحت ' + faN( p.area ) + ' کیلومتر مربع، تراکم ' + faN1( p.density ) + '"></circle>';
		} );
		svg += '</svg>';

		var legend = '<p class="scatter-legend">' +
			'<span><span class="swatch" style="background:#0e93a0"></span> تراکم کمتر</span>' +
			'<span><span class="swatch" style="background:#b7862b"></span> تراکم میانه</span>' +
			'<span><span class="swatch" style="background:#c05a2e"></span> تراکم بیشتر</span>' +
			'<span class="scatter-legend__th">آستانه‌ها از دادهٔ فیلترشده: ' + faN1( t1 ) + ' و ' + faN1( t2 ) + ' نفر/کیلومتر مربع</span></p>';

		box.innerHTML = svg + legend;

		box.querySelectorAll( 'circle' ).forEach( function ( c ) {
			var p = PROV_BY_SLUG[ c.getAttribute( 'data-slug' ) ];
			if ( ! p ) {
				return;
			}
			var text = p.name + ' — جمعیت ' + faN( p.population ) + ' نفر؛ مساحت ' + faN( p.area ) + ' کیلومتر مربع؛ تراکم ' + faN1( p.density ) + ' نفر/کیلومتر مربع';
			bindTip( c, text );
			c.addEventListener( 'click', function () {
				selectProvince( p.slug );
			} );
			c.addEventListener( 'keydown', function ( ev ) {
				if ( ev.key === 'Enter' || ev.key === ' ' ) {
					ev.preventDefault();
					selectProvince( p.slug );
				}
			} );
		} );
	}

	// ۶) تراکم جمعیت — میله‌ای افقی نزولی (خوانا برای ۳۱ دسته).
	function renderDensityChart( list ) {
		var box = byId( 'chart-density' );
		if ( ! box ) {
			return;
		}
		if ( ! list.length ) {
			box.innerHTML = emptyHTML( 'استانی برای نمایش تراکم پیدا نشد.' );
			bindEmptyResets( box );
			return;
		}
		var rows = list.slice().sort( function ( a, b ) {
			return ( b.density || 0 ) - ( a.density || 0 );
		} );
		var max = rows[ 0 ].density || 1;
		box.innerHTML = '';
		rows.forEach( function ( p, i ) {
			var row = hbarRow( p.name, p.density || 0, max, {
				cls: i < 3 ? 'is-top is-top-' + ( i + 1 ) : '',
				valueText: faN1( p.density ),
				tip: p.name + ': ' + faN1( p.density ) + ' نفر بر کیلومتر مربع (جمعیت ' + faN( p.population ) + ' تقسیم بر مساحت ' + faN( p.area ) + ')'
			} );
			box.appendChild( row );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * جدول استان‌ها
	 * ------------------------------------------------------------------ */

	var PAGE_SIZE = 10;

	function tableRows() {
		var list = filtered();
		var q = state.search.trim();
		if ( q ) {
			list = list.filter( function ( p ) {
				return ( p.name + ' ' + p.capital + ' ' + p.neighbors + ' ' + p.countries.join( ' ' ) + ' ' + p.en ).indexOf( q ) !== -1;
			} );
		}
		if ( state.tableBorder ) {
			list = list.filter( function ( p ) {
				return p.border;
			} );
		}
		var dir = state.sortDir === 'asc' ? 1 : -1;
		list.sort( function ( a, b ) {
			var k = state.sortKey, va, vb;
			if ( k === 'rank' ) {
				return dir * ( POP_RANK[ a.slug ] - POP_RANK[ b.slug ] );
			}
			if ( k === 'name' || k === 'capital' ) {
				return dir * faSort( a[ k ], b[ k ] );
			}
			if ( k === 'border' ) {
				va = a.border ? 1 : 0;
				vb = b.border ? 1 : 0;
				return dir * ( va - vb ) || faSort( a.name, b.name );
			}
			va = a[ k ] || 0;
			vb = b[ k ] || 0;
			return dir * ( va - vb ) || faSort( a.name, b.name );
		} );
		return list;
	}

	var MEDALS = { 1: 'مدال طلا', 2: 'مدال نقره', 3: 'مدال برنز' };

	function renderTable() {
		var tbody = byId( 'sa-tbody' );
		var pager = byId( 'sa-pager' );
		if ( ! tbody ) {
			return;
		}
		var rows = tableRows();
		if ( ! rows.length ) {
			tbody.innerHTML = '<tr><td colspan="10">' + emptyHTML( 'هیچ استانی با جست‌وجو/فیلترهای فعلی پیدا نشد.' ) + '</td></tr>';
			bindEmptyResets( tbody );
			if ( pager ) {
				pager.innerHTML = '';
			}
			return;
		}
		var pages = Math.max( 1, Math.ceil( rows.length / PAGE_SIZE ) );
		if ( state.page > pages ) {
			state.page = pages;
		}
		var start = ( state.page - 1 ) * PAGE_SIZE;
		var slice = rows.slice( start, start + PAGE_SIZE );

		tbody.innerHTML = slice.map( function ( p ) {
			var rank = POP_RANK[ p.slug ];
			var medal = rank <= 3 ? ' is-medal is-medal-' + rank : '';
			return '<tr class="' + ( p.border ? 'is-border-row' : '' ) + '">' +
				'<td><span class="rank' + medal + '" title="' + ( MEDALS[ rank ] || '' ) + '">' + faN( rank ) + '</span></td>' +
				'<th scope="row">' + esc( p.name ) + ( p.border ? ' <span class="badge-badge">مرزنشین</span>' : '' ) + '</th>' +
				'<td>' + esc( p.capital ) + '</td>' +
				'<td class="num">' + faN( p.population ) + '</td>' +
				'<td class="num">' + faN( p.area ) + '</td>' +
				'<td class="num">' + faN1( p.density ) + '</td>' +
				'<td class="num">' + faN( p.counties ) + '</td>' +
				'<td>' + ( p.border ? '<span class="pill pill--yes">دارد</span>' : '<span class="pill pill--no">ندارد</span>' ) + '</td>' +
				'<td>' + ( p.countries.length ? p.countries.map( function ( c ) {
					return '<span class="chip" style="--chipc:' + ( COUNTRY_COLORS[ c ] || '#446655' ) + '">' + esc( c ) + '</span>';
				} ).join( '' ) : '<span class="chip chip--none">—</span>' ) + '</td>' +
				'<td class="num">' + faN( p.attractions ) + '</td>' +
				'</tr>';
		} ).join( '' );

		if ( pager ) {
			var html = '<button type="button" class="sa-btn sa-btn--ghost" data-page="prev"' + ( state.page <= 1 ? ' disabled' : '' ) + '>قبلی</button>';
			for ( var i = 1; i <= pages; i++ ) {
				html += '<button type="button" class="sa-btn sa-btn--ghost' + ( i === state.page ? ' is-on' : '' ) + '" data-page="' + i + '"' +
					( i === state.page ? ' aria-current="page"' : '' ) + '>' + faN( i ) + '</button>';
			}
			html += '<button type="button" class="sa-btn sa-btn--ghost" data-page="next"' + ( state.page >= pages ? ' disabled' : '' ) + '>بعدی</button>';
			html += '<span class="sa-pager__info">' + faN( rows.length ) + ' ردیف — صفحهٔ ' + faN( state.page ) + ' از ' + faN( pages ) + '</span>';
			pager.innerHTML = html;
			pager.querySelectorAll( 'button[data-page]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var v = b.getAttribute( 'data-page' );
					if ( v === 'prev' ) {
						state.page = Math.max( 1, state.page - 1 );
					} else if ( v === 'next' ) {
						state.page = Math.min( pages, state.page + 1 );
					} else {
						state.page = parseInt( v, 10 ) || 1;
					}
					renderTable();
				} );
			} );
		}
	}

	function renderTableHead() {
		document.querySelectorAll( '.sa-sort' ).forEach( function ( b ) {
			b.setAttribute( 'aria-label', 'مرتب‌سازی بر اساس ' + b.textContent );
			b.addEventListener( 'click', function () {
				var key = b.getAttribute( 'data-key' );
				if ( state.sortKey === key ) {
					state.sortDir = state.sortDir === 'desc' ? 'asc' : 'desc';
				} else {
					state.sortKey = key;
					state.sortDir = key === 'name' || key === 'capital' ? 'asc' : 'desc';
				}
				state.page = 1;
				document.querySelectorAll( '.sa-sort' ).forEach( function ( x ) {
					x.classList.remove( 'is-asc', 'is-desc' );
				} );
				b.classList.add( state.sortDir === 'asc' ? 'is-asc' : 'is-desc' );
				renderTable();
			} );
		} );
	}

	// خروجی CSV — از ردیف‌های فیلترشده و مرتب‌شدهٔ فعلی (همهٔ صفحه‌ها).
	function exportCsv() {
		var rows = tableRows();
		var header = [ 'رتبه جمعیت کشور', 'استان', 'نام انگلیسی', 'مرکز', 'جمعیت (سرشماری ۱۳۹۵)', 'مساحت (کیلومتر مربع)', 'تراکم (نفر/کیلومتر مربع)', 'شمار شهرستان‌ها', 'مرز زمینی', 'کشورهای همسایه', 'شمار جاذبه‌های فهرست' ];
		var lines = [ header.join( ',' ) ];
		rows.forEach( function ( p ) {
			lines.push( [
				POP_RANK[ p.slug ],
				csvCell( p.name ),
				csvCell( p.en ),
				csvCell( p.capital ),
				p.population,
				p.area,
				p.density === null ? '' : p.density,
				p.counties,
				p.border ? 'بله' : 'خیر',
				csvCell( p.countries.join( '؛ ' ) ),
				p.attractions
			].join( ',' ) );
		} );
		var blob = new Blob( [ '\uFEFF' + lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = 'sarzaminaryan-provinces.csv';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 4000 );
		toast( 'فایل CSV با ' + faN( rows.length ) + ' ردیف دانلود شد.' );
	}

	function csvCell( s ) {
		s = String( s === null || typeof s === 'undefined' ? '' : s );
		if ( /[",\n\r]/.test( s ) ) {
			return '"' + s.replace( /"/g, '""' ) + '"';
		}
		return s;
	}

	/* ------------------------------------------------------------------ *
	 * نقشه
	 * ------------------------------------------------------------------ */

	var mapSvg = null;

	function metricValue( p, metric ) {
		if ( metric === 'density' ) {
			return p.density || 0;
		}
		return p[ metric ] || 0;
	}
	var METRIC_LABEL = {
		population: 'جمعیت (نفر)',
		density: 'تراکم (نفر/کیلومتر مربع)',
		area: 'مساحت (کیلومتر مربع)',
		counties: 'شمار شهرستان‌ها'
	};

	function lerpColor( a, b, t ) {
		var pa = parseInt( a.slice( 1 ), 16 ), pb = parseInt( b.slice( 1 ), 16 );
		var r = Math.round( ( ( pa >> 16 ) & 255 ) + t * ( ( ( pb >> 16 ) & 255 ) - ( ( pa >> 16 ) & 255 ) ) );
		var g = Math.round( ( ( pa >> 8 ) & 255 ) + t * ( ( ( pb >> 8 ) & 255 ) - ( ( pa >> 8 ) & 255 ) ) );
		var bl = Math.round( ( pa & 255 ) + t * ( ( pb & 255 ) - ( pa & 255 ) ) );
		return 'rgb(' + r + ',' + g + ',' + bl + ')';
	}

	function buildMap() {
		var box = byId( 'sa-mapbox' );
		if ( ! box ) {
			return;
		}
		if ( ! SHAPES || ! SHAPES.provinces || ! SHAPES.provinces.length ) {
			box.innerHTML = '<div class="sa-empty" role="status"><p>فایل مرزهای استان‌ها در دسترس نیست؛ برای کروپلت واقعی به دارایی مرزی معتبر نیاز است. جزئیات هر استان در جدول و فهرست جاذبه‌ها در دسترس است.</p></div>';
			return;
		}
		var ns = 'http://www.w3.org/2000/svg';
		mapSvg = document.createElementNS( ns, 'svg' );
		mapSvg.setAttribute( 'viewBox', SHAPES.viewBox );
		mapSvg.setAttribute( 'class', 'sa-mapsvg' );
		mapSvg.setAttribute( 'role', 'group' );
		mapSvg.setAttribute( 'aria-label', 'نقشه استان‌های ایران' );

		SHAPES.provinces.forEach( function ( s ) {
			var path = document.createElementNS( ns, 'path' );
			path.setAttribute( 'd', s.path );
			path.setAttribute( 'data-slug', s.slug );
			path.setAttribute( 'tabindex', '0' );
			path.setAttribute( 'role', 'button' );
			var p = PROV_BY_SLUG[ s.slug ];
			path.setAttribute( 'aria-label', p ? s.fa + ' — ' + p.capital : s.fa );
			path.addEventListener( 'click', function () {
				selectProvince( s.slug );
			} );
			path.addEventListener( 'keydown', function ( ev ) {
				if ( ev.key === 'Enter' || ev.key === ' ' ) {
					ev.preventDefault();
					selectProvince( s.slug );
				}
			} );
			path.addEventListener( 'focus', function () {
				var r = path.getBoundingClientRect();
				var pp = PROV_BY_SLUG[ s.slug ];
				if ( pp ) {
					showTip( s.fa + ' — جمعیت ' + faN( pp.population ) + ' نفر', r.left + r.width / 2, r.top );
				}
			} );
			path.addEventListener( 'blur', hideTip );
			mapSvg.appendChild( path );
		} );
		box.innerHTML = '';
		box.appendChild( mapSvg );
		colorMap();
	}

	function colorMap() {
		if ( ! mapSvg ) {
			return;
		}
		var metric = state.mapMetric;
		var list = filtered();
		var inSet = {};
		list.forEach( function ( p ) {
			inSet[ p.slug ] = true;
		} );
		var vals = list.map( function ( p ) {
			return metricValue( p, metric );
		} );
		var min = vals.length ? Math.min.apply( null, vals ) : 0;
		var max = vals.length ? Math.max.apply( null, vals ) : 1;
		if ( max === min ) {
			max = min + 1;
		}
		mapSvg.querySelectorAll( 'path' ).forEach( function ( path ) {
			var slug = path.getAttribute( 'data-slug' );
			var p = PROV_BY_SLUG[ slug ];
			path.classList.remove( 'is-out', 'is-sel' );
			if ( ! p || ! inSet[ slug ] ) {
				path.classList.add( 'is-out' );
				path.setAttribute( 'aria-disabled', 'true' );
				return;
			}
			path.removeAttribute( 'aria-disabled' );
			var t = ( metricValue( p, metric ) - min ) / ( max - min );
			path.style.fill = lerpColor( '#cdeee4', '#0e5b43', t );
			if ( slug === state.mapSel ) {
				path.classList.add( 'is-sel' );
			}
		} );
		renderMapLegend( min, max, metric );
	}

	function renderMapLegend( min, max, metric ) {
		var box = byId( 'sa-mapbox' );
		if ( ! box ) {
			return;
		}
		var old = box.querySelector( '.sa-maplegend' );
		if ( old ) {
			old.remove();
		}
		var leg = make( 'div', 'sa-maplegend' );
		var steps = 5, html = '<span class="sa-maplegend__cap">' + esc( METRIC_LABEL[ metric ] ) + ' — </span>';
		for ( var i = 0; i < steps; i++ ) {
			var v = min + ( ( max - min ) * i ) / ( steps - 1 );
			html += '<span class="sa-maplegend__step"><span class="swatch" style="background:' + lerpColor( '#cdeee4', '#0e5b43', i / ( steps - 1 ) ) + '"></span>' +
				( metric === 'density' ? faN1( v ) : faN( Math.round( v ) ) ) + '</span>';
		}
		html += '<span class="sa-maplegend__step is-out-step"><span class="swatch swatch--out"></span>خارج از فیلتر</span>';
		leg.innerHTML = html;
		box.appendChild( leg );
	}

	function selectProvince( slug ) {
		if ( ! PROV_BY_SLUG[ slug ] ) {
			return;
		}
		state.mapSel = slug;
		if ( mapSvg ) {
			mapSvg.querySelectorAll( 'path.is-sel' ).forEach( function ( p ) {
				p.classList.remove( 'is-sel' );
			} );
			var sel = mapSvg.querySelector( 'path[data-slug="' + slug + '"]' );
			if ( sel ) {
				sel.classList.add( 'is-sel' );
			}
		}
		renderMapInfo();
	}

	function renderMapInfo() {
		var box = byId( 'sa-mapinfo' );
		if ( ! box ) {
			return;
		}
		var p = PROV_BY_SLUG[ state.mapSel ];
		if ( ! p ) {
			box.innerHTML = '';
			return;
		}
		box.innerHTML =
			'<h3 class="sa-mapinfo__name">' + esc( p.name ) + ( p.en ? ' <small>(' + esc( p.en ) + ')</small>' : '' ) + '</h3>' +
			'<dl class="sa-mapinfo__dl">' +
			'<dt>مرکز</dt><dd>' + esc( p.capital ) + '</dd>' +
			'<dt>جمعیت (۱۳۹۵)</dt><dd>' + faN( p.population ) + ' نفر</dd>' +
			'<dt>مساحت</dt><dd>' + faN( p.area ) + ' کیلومتر مربع</dd>' +
			'<dt>تراکم</dt><dd>' + faN1( p.density ) + ' نفر/کیلومتر مربع</dd>' +
			'<dt>شهرستان‌ها</dt><dd>' + faN( p.counties ) + '</dd>' +
			'<dt>اقلیم</dt><dd>' + esc( p.climate || '—' ) + '</dd>' +
			'<dt>همسایگان داخلی</dt><dd>' + esc( p.neighbors || '—' ) + '</dd>' +
			'<dt>مرز بین‌المللی</dt><dd>' + ( p.border ?
				'<span class="badge-badge">مرزنشین</span> ' + p.countries.map( function ( c ) {
					return '<span class="chip" style="--chipc:' + ( COUNTRY_COLORS[ c ] || '#446655' ) + '">' + esc( c ) + '</span>';
				} ).join( '' ) : 'ندارد' ) + '</dd>' +
			'<dt>جاذبه‌های فهرست</dt><dd>' + faN( p.attractions ) + '</dd>' +
			'</dl>';
	}

	/* ------------------------------------------------------------------ *
	 * جاذبه‌ها
	 * ------------------------------------------------------------------ */

	var CAT_ICONS = {
		historical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 21h16M5 21v-8h14v8M7 13V9.5M12 13V9.5M17 13V9.5M4 9.5h16L12 3z"/></svg>',
		natural: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 20l6-11 4 6.5L16 10l5 10z"/><circle cx="8.5" cy="5.5" r="1.8"/></svg>',
		religious: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 3c2.8 2 4.5 4 4.5 6.5V12h-9V9.5C7.5 7 9.2 5 12 3zM5 21v-7h14v7M9.5 21v-3.5a2.5 2.5 0 015 0V21"/></svg>',
		cultural: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 21h18M4 8h16M6 8v10M10 8v10M14 8v10M18 8v10M12 3L4 8h16z"/></svg>'
	};

	function renderAttractions() {
		var grid = byId( 'sa-attr-grid' );
		if ( ! grid ) {
			return;
		}
		var list = DATA.attractions.slice();
		if ( state.province !== 'all' ) {
			list = list.filter( function ( a ) {
				return a.province === state.province;
			} );
		}
		if ( state.attrCat !== 'all' ) {
			list = list.filter( function ( a ) {
				return a.cat === state.attrCat;
			} );
		}
		list.sort( function ( a, b ) {
			if ( state.attrSort === 'name' ) {
				return faSort( a.name, b.name );
			}
			var pa = PROV_BY_SLUG[ a.province ], pb = PROV_BY_SLUG[ b.province ];
			if ( state.attrSort === 'province' ) {
				return faSort( pa ? pa.name : '', pb ? pb.name : '' ) || faSort( a.name, b.name );
			}
			if ( state.attrSort === 'county' ) {
				return faSort( a.county, b.county ) || faSort( a.name, b.name );
			}
			return faSort( CAT_LABEL[ a.cat ], CAT_LABEL[ b.cat ] ) || faSort( a.name, b.name );
		} );

		if ( ! list.length ) {
			grid.innerHTML = emptyHTML( 'برای این استان یا دسته، جاذبه‌ای در گزینش تحریریه نیست.' );
			bindEmptyResets( grid );
			return;
		}

		grid.innerHTML = list.map( function ( a ) {
			var p = PROV_BY_SLUG[ a.province ];
			return '<article class="sa-acard sa-acard--' + esc( a.cat ) + '">' +
				'<div class="sa-acard__media" aria-hidden="true">' + ( CAT_ICONS[ a.cat ] || '' ) + '</div>' +
				'<div class="sa-acard__body">' +
					'<h3>' + esc( a.name ) + '</h3>' +
					'<p class="sa-acard__loc">' +
						'<span>' + esc( p ? p.name : a.province ) + '</span> · <span>شهرستان ' + esc( a.county ) + '</span>' +
					'</p>' +
					'<p class="sa-acard__meta">' +
						'<span class="pill">' + esc( CAT_LABEL[ a.cat ] || a.cat ) + '</span>' +
						( a.badge ? ' <span class="pill pill--gold">🏵 ' + esc( a.badge ) + '</span>' : '' ) +
					'</p>' +
					( a.note ? '<p class="sa-acard__note">' + esc( a.note ) + '</p>' : '' ) +
				'</div>' +
			'</article>';
		} ).join( '' );
	}

	/* ------------------------------------------------------------------ *
	 * کنترل‌ها
	 * ------------------------------------------------------------------ */

	function clampPair( minEl, maxEl ) {
		var mn = parseInt( minEl.value, 10 ) || 0;
		var mx = parseInt( maxEl.value, 10 ) || 0;
		if ( mn > mx ) {
			var t = mn;
			mn = mx;
			mx = t;
		}
		return [ mn, mx ];
	}

	function syncOutputs() {
		var pop = clampPair( byId( 'sa-f-pop-min' ), byId( 'sa-f-pop-max' ) );
		var area = clampPair( byId( 'sa-f-area-min' ), byId( 'sa-f-area-max' ) );
		byId( 'sa-f-pop-min-out' ).textContent = faN( pop[ 0 ] );
		byId( 'sa-f-pop-max-out' ).textContent = faN( pop[ 1 ] );
		byId( 'sa-f-area-min-out' ).textContent = faN( area[ 0 ] );
		byId( 'sa-f-area-max-out' ).textContent = faN( area[ 1 ] );
	}

	function applyAll() {
		var list = filtered();
		byId( 'sa-dash-scope' ).textContent = scopeText( list );
		renderKpis( list );
		renderPopChart( list );
		renderDonut( list );
		renderCountyChart( list );
		renderBorderChart( list );
		renderScatter( list );
		renderDensityChart( list );
		state.page = 1;
		renderTable();
		colorMap();
		if ( ! PROV_BY_SLUG[ state.mapSel ] || filtered().length ) {
			if ( list.length && ! list.some( function ( p ) {
				return p.slug === state.mapSel;
			} ) ) {
				state.mapSel = list[ 0 ].slug;
			}
		}
		renderMapInfo();
		renderAttractions();
	}

	function resetFilters() {
		state.province = 'all';
		state.popMin = 0;
		state.popMax = POP_LIMIT;
		state.areaMin = 0;
		state.areaMax = AREA_LIMIT;
		state.borderOnly = false;
		byId( 'sa-f-province' ).value = 'all';
		var popMin = byId( 'sa-f-pop-min' ), popMax = byId( 'sa-f-pop-max' );
		var areaMin = byId( 'sa-f-area-min' ), areaMax = byId( 'sa-f-area-max' );
		popMin.value = 0;
		popMax.value = popMax.max;
		areaMin.value = 0;
		areaMax.value = areaMax.max;
		var borderBox = byId( 'sa-f-border' );
		borderBox.checked = false;
		var tborder = byId( 'sa-t-border' );
		if ( tborder ) {
			tborder.checked = false;
			state.tableBorder = false;
		}
		state.search = '';
		var searchEl = byId( 'sa-t-search' );
		if ( searchEl ) {
			searchEl.value = '';
		}
		syncOutputs();
		applyAll();
		toast( 'فیلترها بازنشانی شد.' );
	}

	function bindControls() {
		byId( 'sa-f-province' ).addEventListener( 'change', function ( e ) {
			state.province = e.target.value;
			applyAll();
		} );

		[ [ 'sa-f-pop-min', 'sa-f-pop-max', 'popMin', 'popMax' ], [ 'sa-f-area-min', 'sa-f-area-max', 'areaMin', 'areaMax' ] ].forEach( function ( cfg ) {
			var mn = byId( cfg[ 0 ] ), mx = byId( cfg[ 1 ] );
			function upd() {
				var pair = clampPair( mn, mx );
				state[ cfg[ 2 ] ] = pair[ 0 ];
				state[ cfg[ 3 ] ] = pair[ 1 ];
				syncOutputs();
				applyAll();
			}
			mn.addEventListener( 'input', upd );
			mx.addEventListener( 'input', upd );
		} );

		byId( 'sa-f-border' ).addEventListener( 'change', function ( e ) {
			state.borderOnly = e.target.checked;
			var tborder = byId( 'sa-t-border' );
			if ( tborder ) {
				tborder.checked = e.target.checked;
				state.tableBorder = e.target.checked;
			}
			applyAll();
		} );

		var tborder2 = byId( 'sa-t-border' );
		if ( tborder2 ) {
			tborder2.addEventListener( 'change', function ( e ) {
				state.tableBorder = e.target.checked;
				byId( 'sa-f-border' ).checked = e.target.checked;
				state.borderOnly = e.target.checked;
				applyAll();
			} );
		}

		byId( 'sa-f-reset' ).addEventListener( 'click', resetFilters );

		var searchEl = byId( 'sa-t-search' );
		searchEl.addEventListener( 'input', function ( e ) {
			state.search = e.target.value;
			state.page = 1;
			renderTable();
		} );

		byId( 'sa-t-csv' ).addEventListener( 'click', exportCsv );

		byId( 'sa-map-metric' ).addEventListener( 'change', function ( e ) {
			state.mapMetric = e.target.value;
			colorMap();
		} );

		byId( 'sa-a-cat' ).addEventListener( 'change', function ( e ) {
			state.attrCat = e.target.value;
			renderAttractions();
		} );
		byId( 'sa-a-sort' ).addEventListener( 'change', function ( e ) {
			state.attrSort = e.target.value;
			renderAttractions();
		} );

		// تم روشن/تیره
		var themeBtn = byId( 'sa-theme-toggle' );
		function setTheme( mode ) {
			var root = byId( 'sa-dash' );
			if ( root ) {
				root.setAttribute( 'data-theme', mode );
			}
			themeBtn.setAttribute( 'aria-pressed', mode === 'dark' ? 'true' : 'false' );
			byId( 'sa-theme-ico' ).textContent = mode === 'dark' ? '☀️' : '🌙';
			byId( 'sa-theme-label' ).textContent = mode === 'dark' ? 'حالت روشن' : 'حالت تیره';
			try {
				window.localStorage.setItem( 'sa-dash-theme', mode );
			} catch ( e ) { /* ذخیره تم ممکن نیست — نادیده گرفته می‌شود */ }
		}
		var saved = null;
		try {
			saved = window.localStorage.getItem( 'sa-dash-theme' );
		} catch ( e ) { /* همان حالت پیش‌فرض */ }
		if ( saved === 'dark' || saved === 'light' ) {
			setTheme( saved );
		} else if ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ) {
			setTheme( 'dark' );
		} else {
			setTheme( 'light' );
		}
		themeBtn.addEventListener( 'click', function () {
			setTheme( byId( 'sa-dash' ).getAttribute( 'data-theme' ) === 'dark' ? 'light' : 'dark' );
		} );

		// چاپ و اشتراک‌گذاری
		byId( 'sa-print-btn' ).addEventListener( 'click', function () {
			window.print();
		} );
		byId( 'sa-share-btn' ).addEventListener( 'click', function () {
			var url = window.location.href;
			if ( navigator.share ) {
				navigator.share( { title: document.title, url: url } ).catch( function () { /* کاربر لغو کرد */ } );
			} else if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( function () {
					toast( 'پیوند صفحه کپی شد.' );
				} ).catch( function () {
					toast( 'کپی پیوند ممکن نشد.' );
				} );
			} else {
				toast( 'اشتراک‌گذاری در این مرورگر در دسترس نیست.' );
			}
		} );
	}

	/* ---------------------------- توست ---------------------------- */

	var toastEl = null;
	function toast( msg ) {
		if ( ! toastEl ) {
			toastEl = make( 'div', 'sa-toast' );
			toastEl.setAttribute( 'role', 'status' );
			document.body.appendChild( toastEl );
		}
		toastEl.textContent = msg;
		toastEl.classList.add( 'is-on' );
		clearTimeout( toastEl._t );
		toastEl._t = setTimeout( function () {
			toastEl.classList.remove( 'is-on' );
		}, 2600 );
	}

	/* ---------------------------- شروع ---------------------------- */

	function init() {
		// حدود اسلایدرها از خود داده گرفته شود.
		var popMin = byId( 'sa-f-pop-min' ), popMax = byId( 'sa-f-pop-max' );
		var areaMin = byId( 'sa-f-area-min' ), areaMax = byId( 'sa-f-area-max' );
		popMax.max = String( POP_LIMIT );
		popMin.max = String( POP_LIMIT );
		popMax.value = String( POP_LIMIT );
		areaMax.max = String( AREA_LIMIT );
		areaMin.max = String( AREA_LIMIT );
		areaMax.value = String( AREA_LIMIT );
		state.popMax = POP_LIMIT;
		state.areaMax = AREA_LIMIT;

		bindControls();
		renderTableHead();
		buildMap();
		selectProvince( 'tehran' );
		syncOutputs();
		applyAll();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
