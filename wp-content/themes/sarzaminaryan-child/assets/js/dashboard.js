/**
 * Sarzamin Aryan Child — داشبورد سایبری و نقشهٔ تعاملی دومرحله‌ای شناسنامهٔ ایران (v2.19.3).
 *
 * صفر وابستگی:
 *  - مرحلهٔ ۱ (STAGE 01): نقشهٔ ایران با تقسیمات ۳۱ استان (`window.SA_PROVINCE_SHAPES`) +
 *    نمایش «شناسنامهٔ استان» و ستون‌های تله‌متری در کادر زیر نقشه با لمس/کلیک هر استان +
 *    دکمهٔ ورود به نقشهٔ شهرستان‌های همان استان.
 *  - مرحلهٔ ۲ (STAGE 02): نقشهٔ اختصاصی استان انتخاب‌شده به تفکیک شهرستان‌ها (`window.SA_COUNTY_SHAPES`)
 *    با نوشته‌های انگلیسی سبز رنگ ریز و خوانا روی نقشه + نمایش «شناسنامهٔ شهرستان» در کادر زیر نقشه
 *    با لمس/کلیک هر شهرستان.
 *  - نمودارها و ستون‌های مقایسه‌ای، جدول ماتریسی ۳۱ استان، خروجی CSV و جاذبه‌های شاخص.
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
	var COUNTY_SHAPES = window.SA_COUNTY_SHAPES || {};

	/* ------------------------------------------------------------------ *
	 * ابزارهای عمومی
	 * ------------------------------------------------------------------ */

	var NF = new Intl.NumberFormat( 'fa-IR' );
	var NF1 = new Intl.NumberFormat( 'fa-IR', { maximumFractionDigits: 1 } );

	function faN( n ) {
		return NF.format( Number( n ) || 0 );
	}
	function faN1( n ) {
		return n === null || typeof n === 'undefined' ? '—' : NF1.format( n );
	}
	function esc( s ) {
		return String( s === null || typeof s === 'undefined' ? '' : s ).replace( /[&<>"']/g, function ( c ) {
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

	var PROVINCES = ( DATA.provinces || [] ).slice();
	var PROV_BY_SLUG = {};
	PROVINCES.forEach( function ( p ) {
		PROV_BY_SLUG[ p.slug ] = p;
	} );

	var SHAPE_BY_SLUG = {};
	if ( SHAPES && Array.isArray( SHAPES.provinces ) ) {
		SHAPES.provinces.forEach( function ( s ) {
			SHAPE_BY_SLUG[ s.slug ] = s;
		} );
	}

	// رتبه‌های ملی جمعیت، مساحت و تراکم از کل ۳۱ استان
	var POP_RANK = {};
	var AREA_RANK = {};
	var DENS_RANK = {};
	var TOTAL_POP = 0;
	var TOTAL_AREA = 0;

	PROVINCES.forEach( function ( p ) {
		TOTAL_POP += p.population || 0;
		TOTAL_AREA += p.area || 0;
	} );

	PROVINCES.slice().sort( function ( a, b ) {
		return b.population - a.population;
	} ).forEach( function ( p, i ) {
		POP_RANK[ p.slug ] = i + 1;
	} );

	PROVINCES.slice().sort( function ( a, b ) {
		return b.area - a.area;
	} ).forEach( function ( p, i ) {
		AREA_RANK[ p.slug ] = i + 1;
	} );

	PROVINCES.slice().sort( function ( a, b ) {
		return ( b.density || 0 ) - ( a.density || 0 );
	} ).forEach( function ( p, i ) {
		DENS_RANK[ p.slug ] = i + 1;
	} );

	var MAX_POP = Math.max.apply( null, PROVINCES.map( function ( p ) { return p.population || 1; } ) );
	var MAX_AREA = Math.max.apply( null, PROVINCES.map( function ( p ) { return p.area || 1; } ) );
	var MAX_DENS = Math.max.apply( null, PROVINCES.map( function ( p ) { return p.density || 1; } ) );
	var MAX_COUNTIES = Math.max.apply( null, PROVINCES.map( function ( p ) { return p.counties || 1; } ) );

	var CAT_LABEL = {
		historical: 'تاریخی و معماری',
		natural: 'طبیعی',
		religious: 'مذهبی و زیارتی',
		cultural: 'فرهنگی و موزه‌ای'
	};

	var COUNTRY_COLORS = {
		'ترکیه': '#10b981',
		'عراق': '#06b6d4',
		'ارمنستان': '#f59e0b',
		'جمهوری آذربایجان': '#3b82f6',
		'ترکمنستان': '#8b5cf6',
		'افغانستان': '#ec4899',
		'پاکستان': '#14b8a6'
	};

	var DONUT_COLORS = [ '#00ff9d', '#06b6d4', '#3b82f6', '#f59e0b', '#8b5cf6', '#334155' ];

	var METRIC_LABEL = {
		population: 'جمعیت (نفر)',
		density: 'تراکم (نفر بر کیلومتر مربع)',
		area: 'مساحت (کیلومتر مربع)',
		counties: 'شمار شهرستان‌ها'
	};

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

	/* ----------------------- وضعیت سراسری ----------------------- */

	var state = {
		stage: 1, // 1 = Iran Map, 2 = Province Counties Map
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
		countySel: '',
		countyZoom: 1,
		attrCat: 'all',
		attrSort: 'name'
	};

	var POP_LIMIT = MAX_POP;
	var AREA_LIMIT = MAX_AREA;

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
			return 'STATUS: ALL 31 PROVINCES ACTIVE // نمایش همهٔ ۳۱ استان کشور';
		}
		if ( ! list.length ) {
			return 'STATUS: ZERO MATCH // هیچ استانی با فیلترهای فعلی یافت نشد';
		}
		return 'FILTER ACTIVE: ' + list.length + '/31 // نمایش ' + faN( list.length ) + ' استان از ' + faN( PROVINCES.length ) + ' استان';
	}

	function emptyHTML( msg ) {
		return '<div class="sa-empty" role="status"><p>' + esc( msg ) + '</p>' +
			'<button type="button" class="sa-btn sa-btn--ghost sa-empty__reset">بازنشانی فیلترها (RESET)</button></div>';
	}
	function bindEmptyResets( root ) {
		root.querySelectorAll( '.sa-empty__reset' ).forEach( function ( b ) {
			b.addEventListener( 'click', resetFilters );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * شاخص‌های کلیدی (۵ کارت سایبری)
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
			{ key: 'provinces', code: 'KPI.01 // PROVINCES', label: 'استان‌های فعال', value: faN( list.length ), unit: 'استان', tip: 'شمار استان‌های داخل فیلتر جاری' },
			{ key: 'counties', code: 'KPI.02 // COUNTIES', label: 'شهرستان‌های کشور', value: faN( counties ), unit: 'شهرستان', tip: 'مجموع شهرستان‌های استان‌های فیلترشده از فهرست رسمی ۴۸۳تایی' },
			{ key: 'attractions', code: 'KPI.03 // SITES', label: 'جاذبه‌های شاخص', value: faN( attrs ), unit: 'پرونده', tip: 'گزینش تحریریه؛ فهرست کامل در بخش جاذبه‌ها' },
			{ key: 'area', code: 'KPI.04 // AREA_KM2', label: 'مساحت کل پهنه', value: faN( area ), unit: 'km²', tip: 'مجموع مساحت استان‌های فیلترشده' },
			{ key: 'population', code: 'KPI.05 // POPULATION', label: 'جمعیت ثبت‌شده', value: faN( pop ), unit: 'نفر', tip: 'مجموع جمعیت استان‌های فیلترشده؛ مبنا سرشماری ۱۳۹۵' }
		];

		box.innerHTML = '';
		cards.forEach( function ( c ) {
			var el = make( 'article', 'sa-kpi sa-kpi--' + c.key );
			el.setAttribute( 'tabindex', '0' );
			el.innerHTML =
				'<div class="sa-kpi__top" dir="ltr"><span class="sa-kpi__code">' + esc( c.code ) + '</span><span class="sa-kpi__icon">' + KPI_ICONS[ c.key ] + '</span></div>' +
				'<div class="sa-kpi__body">' +
					'<span class="sa-kpi__value">' + esc( c.value ) + ( c.unit ? ' <small>' + esc( c.unit ) + '</small>' : '' ) + '</span>' +
					'<span class="sa-kpi__label">' + esc( c.label ) + '</span>' +
				'</div>';
			bindTip( el, c.tip );
			box.appendChild( el );
		} );

		var note = byId( 'sa-kpi-note' );
		if ( note ) {
			if ( list.length === PROVINCES.length ) {
				note.textContent = 'SYS.NOTE // مجموع جمعیت ۳۱ استان برابر رقم رسمی سرشماری ۱۳۹۵ (۷۹٬۹۲۶٬۲۷۰ نفر) و مساحت رسمی کشور ۱٬۶۴۸٬۱۹۵ کیلومتر مربع است.';
			} else {
				note.textContent = 'SYS.NOTE // شاخص‌ها بر اساس فیلترهای فعال شما به‌روز شده‌اند.';
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * مرحلهٔ ۱ و ۲ نقشهٔ سایبری (STAGE 01: IRAN -> STAGE 02: COUNTIES)
	 * ------------------------------------------------------------------ */

	var mapSvg = null;
	var countySvg = null;

	function metricValue( p, m ) {
		if ( m === 'density' ) {
			return p.density || 0;
		}
		if ( m === 'area' ) {
			return p.area || 0;
		}
		if ( m === 'counties' ) {
			return p.counties || 0;
		}
		return p.population || 0;
	}

	function lerpColor( hexA, hexB, t ) {
		t = Math.max( 0, Math.min( 1, t ) );
		var a = parseInt( hexA.slice( 1 ), 16 );
		var b = parseInt( hexB.slice( 1 ), 16 );
		var r = Math.round( ( ( a >> 16 ) & 255 ) + ( ( ( b >> 16 ) & 255 ) - ( ( a >> 16 ) & 255 ) ) * t );
		var g = Math.round( ( ( a >> 8 ) & 255 ) + ( ( ( b >> 8 ) & 255 ) - ( ( a >> 8 ) & 255 ) ) * t );
		var bl = Math.round( ( a & 255 ) + ( ( b & 255 ) - ( a & 255 ) ) * t );
		return 'rgb(' + r + ',' + g + ',' + bl + ')';
	}

	function buildMap() {
		var box = byId( 'sa-mapbox' );
		if ( ! box ) {
			return;
		}
		if ( ! SHAPES || ! Array.isArray( SHAPES.provinces ) || ! SHAPES.provinces.length ) {
			box.innerHTML = emptyHTML( 'دادهٔ مرزهای نقشه در دسترس نیست.' );
			return;
		}

		var ns = 'http://www.w3.org/2000/svg';
		mapSvg = document.createElementNS( ns, 'svg' );
		mapSvg.setAttribute( 'viewBox', SHAPES.viewBox );
		mapSvg.setAttribute( 'class', 'sa-mapsvg sa-cyber-svg' );
		mapSvg.setAttribute( 'role', 'group' );
		mapSvg.setAttribute( 'aria-label', 'نقشه تعاملی ۳۱ استان ایران' );

		// شبکهٔ راداری پس‌زمینه (Cyber Radar Grid)
		var gridG = document.createElementNS( ns, 'g' );
		gridG.setAttribute( 'class', 'sa-cyber-svg-grid' );
		gridG.setAttribute( 'aria-hidden', 'true' );
		var gx, gy;
		for ( gx = 100; gx < 1000; gx += 100 ) {
			var vline = document.createElementNS( ns, 'line' );
			vline.setAttribute( 'x1', gx );
			vline.setAttribute( 'y1', 0 );
			vline.setAttribute( 'x2', gx );
			vline.setAttribute( 'y2', 825 );
			gridG.appendChild( vline );
		}
		for ( gy = 100; gy < 825; gy += 100 ) {
			var hline = document.createElementNS( ns, 'line' );
			hline.setAttribute( 'x1', 0 );
			hline.setAttribute( 'y1', gy );
			hline.setAttribute( 'x2', 1000 );
			hline.setAttribute( 'y2', gy );
			gridG.appendChild( hline );
		}
		mapSvg.appendChild( gridG );

		// برچسب پهنه‌های آبی ایران
		var seas = [
			{ x: 375, y: 120, en: 'CASPIAN SEA', fa: 'دریای خزر (مازندران)' },
			{ x: 390, y: 695, en: 'PERSIAN GULF', fa: 'خلیج همیشه فارس' },
			{ x: 705, y: 780, en: 'SEA OF OMAN', fa: 'دریای عمان' }
		];
		var seaG = document.createElementNS( ns, 'g' );
		seaG.setAttribute( 'class', 'sa-cyber-sea-labels' );
		seaG.setAttribute( 'aria-hidden', 'true' );
		seas.forEach( function ( s ) {
			var tEn = document.createElementNS( ns, 'text' );
			tEn.setAttribute( 'x', s.x );
			tEn.setAttribute( 'y', s.y );
			tEn.setAttribute( 'text-anchor', 'middle' );
			tEn.setAttribute( 'class', 'sa-cyber-sea-en' );
			tEn.textContent = s.en;
			seaG.appendChild( tEn );

			var tFa = document.createElementNS( ns, 'text' );
			tFa.setAttribute( 'x', s.x );
			tFa.setAttribute( 'y', s.y + 16 );
			tFa.setAttribute( 'text-anchor', 'middle' );
			tFa.setAttribute( 'class', 'sa-cyber-sea-fa' );
			tFa.textContent = s.fa;
			seaG.appendChild( tFa );
		} );
		mapSvg.appendChild( seaG );

		var pathsG = document.createElementNS( ns, 'g' );
		pathsG.setAttribute( 'class', 'sa-cyber-provinces-layer' );
		var labelsG = document.createElementNS( ns, 'g' );
		labelsG.setAttribute( 'class', 'sa-cyber-prov-labels-layer' );

		SHAPES.provinces.forEach( function ( s ) {
			var path = document.createElementNS( ns, 'path' );
			path.setAttribute( 'd', s.path );
			path.setAttribute( 'data-slug', s.slug );
			path.setAttribute( 'tabindex', '0' );
			path.setAttribute( 'role', 'button' );
			var p = PROV_BY_SLUG[ s.slug ];
			path.setAttribute( 'aria-label', p ? 'استان ' + s.fa + ' (' + s.en + ') — مرکز: ' + p.capital : s.fa );

			path.addEventListener( 'click', function () {
				if ( state.mapSel === s.slug ) {
					// لمس/کلیک مجدد روی استان انتخاب‌شده: ورود مستقیم به نقشهٔ شهرستان‌های استان
					openProvinceCountyMap( s.slug );
				} else {
					selectProvince( s.slug, true );
				}
			} );
			path.addEventListener( 'keydown', function ( ev ) {
				if ( ev.key === 'Enter' || ev.key === ' ' ) {
					ev.preventDefault();
					selectProvince( s.slug, true );
				}
			} );
			path.addEventListener( 'pointerenter', function ( ev ) {
				var pp = PROV_BY_SLUG[ s.slug ];
				if ( pp ) {
					showTip( s.fa + ' [' + s.en.toUpperCase() + '] — جمعیت: ' + faN( pp.population ) + ' نفر · شهرستان‌ها: ' + faN( pp.counties ), ev.clientX, ev.clientY );
				}
			} );
			path.addEventListener( 'pointermove', function ( ev ) {
				var pp = PROV_BY_SLUG[ s.slug ];
				if ( pp ) {
					showTip( s.fa + ' [' + s.en.toUpperCase() + '] — جمعیت: ' + faN( pp.population ) + ' نفر · شهرستان‌ها: ' + faN( pp.counties ), ev.clientX, ev.clientY );
				}
			} );
			path.addEventListener( 'pointerleave', hideTip );

			pathsG.appendChild( path );

			// برچسب انگلیسی سبز رنگ + نام فارسی روی هر استان در نقشهٔ ایران
			if ( typeof s.lx === 'number' && typeof s.ly === 'number' ) {
				var lg = document.createElementNS( ns, 'g' );
				lg.setAttribute( 'class', 'sa-prov-label' );
				lg.setAttribute( 'data-slug', s.slug );

				var dot = document.createElementNS( ns, 'circle' );
				dot.setAttribute( 'cx', s.lx );
				dot.setAttribute( 'cy', s.ly );
				dot.setAttribute( 'r', '2.3' );
				dot.setAttribute( 'class', 'sa-prov-label__dot' );
				lg.appendChild( dot );

				var txtEn = document.createElementNS( ns, 'text' );
				txtEn.setAttribute( 'x', s.lx );
				txtEn.setAttribute( 'y', s.ly - 4 );
				txtEn.setAttribute( 'text-anchor', 'middle' );
				txtEn.setAttribute( 'class', 'sa-prov-label__en' );
				txtEn.textContent = s.en;
				lg.appendChild( txtEn );

				var txtFa = document.createElementNS( ns, 'text' );
				txtFa.setAttribute( 'x', s.lx );
				txtFa.setAttribute( 'y', s.ly + 9 );
				txtFa.setAttribute( 'text-anchor', 'middle' );
				txtFa.setAttribute( 'class', 'sa-prov-label__fa' );
				txtFa.textContent = s.fa;
				lg.appendChild( txtFa );

				labelsG.appendChild( lg );
			}
		} );

		mapSvg.appendChild( pathsG );
		mapSvg.appendChild( labelsG );

		box.innerHTML = '';
		var hudTop = make( 'div', 'sa-cyber-map-hud',
			'<span class="sa-cyber-map-hud__left" dir="ltr">RADAR.GRID // IRAN-31 PROVINCES · LCC PROJECTION</span>' +
			'<span class="sa-cyber-map-hud__right" dir="ltr">CLICK 1: PROVINCE ID · CLICK 2: COUNTY MAP</span>'
		);
		box.appendChild( hudTop );
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
		mapSvg.querySelectorAll( '.sa-cyber-provinces-layer path' ).forEach( function ( path ) {
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
			path.style.fill = lerpColor( '#092232', '#059669', t );
			if ( slug === state.mapSel ) {
				path.classList.add( 'is-sel' );
			}
		} );
		mapSvg.querySelectorAll( '.sa-prov-label' ).forEach( function ( lg ) {
			var slug = lg.getAttribute( 'data-slug' );
			lg.classList.toggle( 'is-sel', slug === state.mapSel );
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
		var steps = 5;
		var html = '<span class="sa-maplegend__cap">' + esc( METRIC_LABEL[ metric ] ) + ':</span>';
		for ( var i = 0; i < steps; i++ ) {
			var v = min + ( ( max - min ) * i ) / ( steps - 1 );
			html += '<span class="sa-maplegend__step"><span class="swatch" style="background:' + lerpColor( '#092232', '#059669', i / ( steps - 1 ) ) + '"></span>' +
				( metric === 'density' ? faN1( v ) : faN( Math.round( v ) ) ) + '</span>';
		}
		leg.innerHTML = html;
		box.appendChild( leg );
	}

	/* ------------------------------------------------------------------ *
	 * انتخاب استان و رندر «شناسنامهٔ استان» در کادر زیر نقشه (STAGE 01)
	 * ------------------------------------------------------------------ */

	function selectProvince( slug, userInitiated ) {
		if ( ! PROV_BY_SLUG[ slug ] ) {
			return;
		}
		state.mapSel = slug;

		var jumpSel = byId( 'sa-cyber-prov-jump' );
		if ( jumpSel && jumpSel.value !== slug ) {
			jumpSel.value = slug;
		}

		var stage2Lbl = byId( 'sa-tab-stage-2-label' );
		if ( stage2Lbl ) {
			stage2Lbl.textContent = 'نقشهٔ شهرستان‌های ' + PROV_BY_SLUG[ slug ].name + ' (' + ( PROV_BY_SLUG[ slug ].en || '' ) + ')';
		}

		if ( mapSvg ) {
			mapSvg.querySelectorAll( '.sa-cyber-provinces-layer path.is-sel' ).forEach( function ( p ) {
				p.classList.remove( 'is-sel' );
			} );
			var sel = mapSvg.querySelector( '.sa-cyber-provinces-layer path[data-slug="' + slug + '"]' );
			if ( sel ) {
				sel.classList.add( 'is-sel' );
			}
			mapSvg.querySelectorAll( '.sa-prov-label' ).forEach( function ( lg ) {
				lg.classList.toggle( 'is-sel', lg.getAttribute( 'data-slug' ) === slug );
			} );
		}

		renderMapInfo();
		renderProvinceIdentityCard( slug );

		if ( userInitiated && window.innerWidth < 920 ) {
			var frame = byId( 'sa-province-id-frame' );
			if ( frame ) {
				frame.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
		}
	}

	function renderMapInfo() {
		var box = byId( 'sa-mapinfo' );
		if ( ! box ) {
			return;
		}
		var p = PROV_BY_SLUG[ state.mapSel ];
		var s = SHAPE_BY_SLUG[ state.mapSel ] || {};
		var cData = COUNTY_SHAPES[ state.mapSel ] || null;
		if ( ! p ) {
			box.innerHTML = '';
			return;
		}

		var popPct = TOTAL_POP > 0 ? ( ( p.population / TOTAL_POP ) * 100 ).toFixed( 1 ) : '0.0';
		var areaPct = TOTAL_AREA > 0 ? ( ( p.area / TOTAL_AREA ) * 100 ).toFixed( 1 ) : '0.0';
		var popBarW = Math.max( 4, Math.round( ( p.population / MAX_POP ) * 100 ) );
		var areaBarW = Math.max( 4, Math.round( ( p.area / MAX_AREA ) * 100 ) );
		var densBarW = Math.max( 4, Math.round( ( ( p.density || 0 ) / MAX_DENS ) * 100 ) );
		var cntBarW = Math.max( 4, Math.round( ( p.counties / MAX_COUNTIES ) * 100 ) );

		box.innerHTML =
			'<div class="sa-cyber-qp__head" dir="ltr">' +
				'<span class="sa-cyber-qp__code">TARGET // ' + esc( s.code || 'IR' ) + ' · ' + esc( ( p.en || p.slug ).toUpperCase() ) + '</span>' +
				'<span class="sa-cyber-qp__rank">POP #' + faN( POP_RANK[ p.slug ] ) + '</span>' +
			'</div>' +
			'<h3 class="sa-mapinfo__name">' +
				'<span>استان ' + esc( p.name ) + '</span>' +
				'<small dir="ltr">' + esc( p.en ) + ' Province</small>' +
			'</h3>' +
			'<button type="button" class="sa-cyber-drill-btn" id="sa-qp-drill-btn">' +
				'<span class="sa-cyber-drill-btn__main">ورود به نقشهٔ شهرستان‌های ' + esc( p.name ) + ' ←</span>' +
				'<span class="sa-cyber-drill-btn__sub" dir="ltr">ENTER STAGE 02 // ' + esc( cData ? cData.counties.length : p.counties ) + ' COUNTIES MAP (EN)</span>' +
			'</button>' +
			'<div class="sa-cyber-qp__bars">' +
				'<div class="sa-cyber-bar-item">' +
					'<div class="sa-cyber-bar-item__top"><span dir="ltr">POPULATION_INDEX</span><strong>' + faN( p.population ) + ' نفر (' + faN1( popPct ) + '٪ کشور)</strong></div>' +
					'<div class="sa-cyber-bar-track"><span style="width:' + popBarW + '%"></span></div>' +
				'</div>' +
				'<div class="sa-cyber-bar-item">' +
					'<div class="sa-cyber-bar-item__top"><span dir="ltr">AREA_INDEX (KM²)</span><strong>' + faN( p.area ) + ' km² (' + faN1( areaPct ) + '٪ کشور)</strong></div>' +
					'<div class="sa-cyber-bar-track"><span style="width:' + areaBarW + '%"></span></div>' +
				'</div>' +
				'<div class="sa-cyber-bar-item">' +
					'<div class="sa-cyber-bar-item__top"><span dir="ltr">DENSITY_INDEX</span><strong>' + faN1( p.density ) + ' نفر/km² (رتبهٔ ' + faN( DENS_RANK[ p.slug ] ) + ')</strong></div>' +
					'<div class="sa-cyber-bar-track"><span style="width:' + densBarW + '%"></span></div>' +
				'</div>' +
				'<div class="sa-cyber-bar-item">' +
					'<div class="sa-cyber-bar-item__top"><span dir="ltr">COUNTY_DIVISIONS</span><strong>' + faN( p.counties ) + ' شهرستان · ' + faN( p.city_count || 0 ) + ' شهر</strong></div>' +
					'<div class="sa-cyber-bar-track"><span style="width:' + cntBarW + '%"></span></div>' +
				'</div>' +
			'</div>' +
			'<p class="sa-cyber-qp__foot">شناسنامهٔ جدول‌بندی‌شدهٔ کامل استان ' + esc( p.name ) + ' در کادر پایین نقشه درج شده است.</p>';

		var drillBtn = byId( 'sa-qp-drill-btn' );
		if ( drillBtn ) {
			drillBtn.addEventListener( 'click', function () {
				openProvinceCountyMap( p.slug );
			} );
		}
	}

	function renderProvinceIdentityCard( slug ) {
		var frame = byId( 'sa-province-id-frame' );
		if ( ! frame ) {
			return;
		}
		var p = PROV_BY_SLUG[ slug ];
		var s = SHAPE_BY_SLUG[ slug ] || {};
		var cData = COUNTY_SHAPES[ slug ] || null;
		if ( ! p ) {
			frame.innerHTML = '';
			return;
		}

		// یافتن مختصات مرکز استان از دادهٔ شهرستان‌ها
		var capCoord = 'نیازمند بازبینی';
		var capEn = p.en || '';
		if ( cData && Array.isArray( cData.counties ) && cData.counties.length ) {
			var capCounty = cData.counties.filter( function ( c ) {
				return c.dist_km === 0 || c.fa === p.capital || c.center_fa === p.capital;
			} )[ 0 ] || cData.counties[ 0 ];
			if ( capCounty && capCounty.lat && capCounty.lng ) {
				capCoord = capCounty.lat.toFixed( 4 ) + '° N, ' + capCounty.lng.toFixed( 4 ) + '° E';
				capEn = capCounty.center_en || capCounty.en || capEn;
			}
		}

		var popRank = POP_RANK[ p.slug ] || '—';
		var areaRank = AREA_RANK[ p.slug ] || '—';
		var divisionsText = faN( p.counties ) + ' شهرستان' + ( p.district_count ? ' · ' + faN( p.district_count ) + ' بخش' : '' );
		var citiesText = ( p.city_count ? faN( p.city_count ) + ' شهر' : '—' ) +
			( p.rural_district_count ? ' · ' + faN( p.rural_district_count ) + ' دهستان' : '' ) +
			( p.settlement_count ? ' · ' + faN( p.settlement_count ) + ' آبادی' : '' );

		var neighborsHtml = esc( p.neighbors || 'نیازمند بازبینی' );
		if ( Array.isArray( p.neighbor_groups ) && p.neighbor_groups.length ) {
			neighborsHtml = p.neighbor_groups.map( function ( ng ) {
				return '<span class="sa-cyber-id-chip"><strong>' + esc( ng.direction ) + ':</strong> ' + esc( ( ng.places || [] ).join( '، ' ) ) + '</span>';
			} ).join( ' ' );
		}
		if ( p.border && Array.isArray( p.countries ) && p.countries.length ) {
			neighborsHtml += ' <span class="sa-cyber-id-chip sa-cyber-id-chip--border">مرز بین‌المللی: ' + esc( p.countries.join( '، ' ) ) + '</span>';
		}

		var countyChipsHtml = '';
		if ( cData && Array.isArray( cData.counties ) ) {
			countyChipsHtml = cData.counties.map( function ( c ) {
				return '<button type="button" class="sa-cyber-county-pill" data-cslug="' + esc( c.slug ) + '">' +
					'<span class="sa-cyber-county-pill__fa">' + esc( c.fa ) + '</span>' +
					'<span class="sa-cyber-county-pill__en" dir="ltr">' + esc( c.en ) + '</span>' +
				'</button>';
			} ).join( '' );
		}

		frame.innerHTML =
			'<section class="sa-cyber-idcard sa-cyber-idcard--province" aria-label="شناسنامه استان ' + esc( p.name ) + '">' +
				'<header class="sa-cyber-idcard__head">' +
					'<div class="sa-cyber-idcard__titles">' +
						'<span class="sa-cyber-idcard__badge">شناسنامهٔ استان</span>' +
						'<h3 class="sa-cyber-idcard__h3">استان ' + esc( p.name ) + '</h3>' +
						'<span class="sa-cyber-idcard__en" dir="ltr">PROVINCE IDENTITY // ' + esc( ( p.en || p.slug ).toUpperCase() ) + ' [' + esc( s.code || 'IR' ) + ']</span>' +
					'</div>' +
					'<div class="sa-cyber-idcard__actions">' +
						'<button type="button" class="sa-cyber-action-btn sa-cyber-action-btn--primary" id="sa-card-drill-btn">' +
							'<span>نقشهٔ شهرستان‌های ' + esc( p.name ) + ' (مرحلهٔ ۲) ←</span>' +
							'<small dir="ltr">OPEN COUNTY MAP // ' + esc( cData ? cData.counties.length : p.counties ) + ' COUNTIES</small>' +
						'</button>' +
						( p.url ? '<a class="sa-cyber-action-btn sa-cyber-action-btn--ghost" href="' + esc( p.url ) + '">صفحهٔ کامل استان ↗</a>' : '' ) +
					'</div>' +
				'</header>' +
				'<div class="sa-cyber-idcard__grid" role="table" aria-label="جدول اطلاعات شناسنامه استان">' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">01 // PROVINCE NAME (EN)</span>' +
						'<span class="sa-cyber-idcard__lbl">نام انگلیسی و کد ایزو</span>' +
						'<strong class="sa-cyber-idcard__val sa-cyber-idcard__val--en" dir="ltr">' + esc( p.en || '—' ) + ' (' + esc( s.code || 'IR' ) + ')</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">02 // CAPITAL CITY</span>' +
						'<span class="sa-cyber-idcard__lbl">مرکز استان</span>' +
						'<strong class="sa-cyber-idcard__val">' + esc( p.capital ) + ' <small dir="ltr">(' + esc( capEn ) + ')</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">03 // POPULATION (CENSUS)</span>' +
						'<span class="sa-cyber-idcard__lbl">جمعیت استان (رتبهٔ #' + faN( popRank ) + ' کشور)</span>' +
						'<strong class="sa-cyber-idcard__val">' + faN( p.population ) + ' نفر' + ( p.household_count ? ' <small>(' + faN( p.household_count ) + ' خانوار)</small>' : '' ) + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">04 // TOTAL AREA &amp; DENSITY</span>' +
						'<span class="sa-cyber-idcard__lbl">مساحت کل (رتبهٔ #' + faN( areaRank ) + ') و تراکم</span>' +
						'<strong class="sa-cyber-idcard__val">' + faN( p.area ) + ' km² <small>· تراکم: ' + faN1( p.density ) + ' نفر/km²</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">05 // POLITICAL DIVISIONS</span>' +
						'<span class="sa-cyber-idcard__lbl">تقسیمات سیاسی (شهرستان و بخش)</span>' +
						'<strong class="sa-cyber-idcard__val">' + divisionsText + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">06 // CITIES &amp; SETTLEMENTS</span>' +
						'<span class="sa-cyber-idcard__lbl">شهرها، دهستان‌ها و آبادی‌ها</span>' +
						'<strong class="sa-cyber-idcard__val">' + citiesText + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">07 // DOMINANT CLIMATE</span>' +
						'<span class="sa-cyber-idcard__lbl">اقلیم غالب</span>' +
						'<strong class="sa-cyber-idcard__val">' + esc( p.climate || 'نیازمند بازبینی' ) + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">08 // CAPITAL COORDINATES</span>' +
						'<span class="sa-cyber-idcard__lbl">مختصات جغرافیایی مرکز استان</span>' +
						'<strong class="sa-cyber-idcard__val sa-cyber-idcard__val--en" dir="ltr">' + esc( capCoord ) + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell sa-cyber-idcard__cell--wide" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">09 // NEIGHBORING PROVINCES &amp; BORDERS</span>' +
						'<span class="sa-cyber-idcard__lbl">همسایگان استانی و مرزهای پیرامونی</span>' +
						'<div class="sa-cyber-idcard__chips">' + neighborsHtml + '</div>' +
					'</div>' +
					( countyChipsHtml ?
					'<div class="sa-cyber-idcard__cell sa-cyber-idcard__cell--wide" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">10 // COUNTIES DIRECT DRILL-DOWN (CLICK ANY COUNTY TO OPEN STAGE 02)</span>' +
						'<span class="sa-cyber-idcard__lbl">فهرست شهرستان‌های استان ' + esc( p.name ) + ' (با لمس هر شهرستان، نقشهٔ مرحلهٔ ۲ و شناسنامهٔ همان شهرستان باز می‌شود)</span>' +
						'<div class="sa-cyber-idcard__counties">' + countyChipsHtml + '</div>' +
					'</div>' : '' ) +
				'</div>' +
			'</section>';

		var cardDrill = byId( 'sa-card-drill-btn' );
		if ( cardDrill ) {
			cardDrill.addEventListener( 'click', function () {
				openProvinceCountyMap( p.slug );
			} );
		}

		frame.querySelectorAll( '.sa-cyber-county-pill' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				openProvinceCountyMap( p.slug, btn.getAttribute( 'data-cslug' ) );
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * مرحلهٔ ۲: نقشهٔ استان به تفکیک شهرستان‌ها با نام انگلیسی سبز ریز و خوانا
	 * + نمایش «شناسنامهٔ شهرستان» در کادر زیر نقشه
	 * ------------------------------------------------------------------ */

	function switchStage( stageNum ) {
		state.stage = stageNum;
		var tab1 = byId( 'sa-tab-stage-1' );
		var tab2 = byId( 'sa-tab-stage-2' );
		var panel1 = byId( 'sa-stage-1-panel' );
		var panel2 = byId( 'sa-stage-2-panel' );
		var metricWrap = byId( 'sa-map-metric-wrap' );

		if ( stageNum === 2 ) {
			if ( tab1 ) {
				tab1.classList.remove( 'is-active' );
				tab1.setAttribute( 'aria-selected', 'false' );
			}
			if ( tab2 ) {
				tab2.classList.add( 'is-active' );
				tab2.setAttribute( 'aria-selected', 'true' );
			}
			if ( panel1 ) {
				panel1.classList.remove( 'is-active' );
				panel1.hidden = true;
			}
			if ( panel2 ) {
				panel2.classList.add( 'is-active' );
				panel2.hidden = false;
			}
			if ( metricWrap ) {
				metricWrap.style.display = 'none';
			}
		} else {
			if ( tab1 ) {
				tab1.classList.add( 'is-active' );
				tab1.setAttribute( 'aria-selected', 'true' );
			}
			if ( tab2 ) {
				tab2.classList.remove( 'is-active' );
				tab2.setAttribute( 'aria-selected', 'false' );
			}
			if ( panel1 ) {
				panel1.classList.add( 'is-active' );
				panel1.hidden = false;
			}
			if ( panel2 ) {
				panel2.classList.remove( 'is-active' );
				panel2.hidden = true;
			}
			if ( metricWrap ) {
				metricWrap.style.display = '';
			}
		}
	}

	function openProvinceCountyMap( provinceSlug, initialCountySlug ) {
		if ( ! PROV_BY_SLUG[ provinceSlug ] ) {
			return;
		}
		state.mapSel = provinceSlug;
		state.countyZoom = 1;
		selectProvince( provinceSlug, false );
		switchStage( 2 );

		var p = PROV_BY_SLUG[ provinceSlug ];
		var cData = COUNTY_SHAPES[ provinceSlug ];
		var codeEl = byId( 'sa-stage2-code' );
		var headEl = byId( 'sa-stage2-heading' );
		if ( codeEl ) {
			codeEl.textContent = 'STAGE 02 // ' + ( p.en || provinceSlug ).toUpperCase() + ' COUNTY DIVISIONS (' + ( cData ? cData.counties.length : p.counties ) + ' COUNTIES)';
		}
		if ( headEl ) {
			headEl.textContent = 'نقشهٔ شهرستان‌های استان ' + p.name + ' (' + ( p.en || '' ) + ')';
		}

		buildProvinceCountySvg( provinceSlug );

		// انتخاب پیش‌فرض: شهرستان درخواستی یا مرکز استان
		var targetCounty = initialCountySlug || '';
		if ( cData && Array.isArray( cData.counties ) && cData.counties.length ) {
			if ( ! targetCounty ) {
				var capC = cData.counties.filter( function ( c ) {
					return c.dist_km === 0 || c.fa === p.capital;
				} )[ 0 ] || cData.counties[ 0 ];
				targetCounty = capC.slug;
			}
			selectCounty( provinceSlug, targetCounty, false );
		}

		var mapSec = byId( 'map' );
		if ( mapSec ) {
			mapSec.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	function buildProvinceCountySvg( provinceSlug ) {
		var box = byId( 'sa-county-mapbox' );
		var strip = byId( 'sa-county-strip' );
		if ( ! box ) {
			return;
		}
		var cData = COUNTY_SHAPES[ provinceSlug ];
		var p = PROV_BY_SLUG[ provinceSlug ];
		if ( ! cData || ! Array.isArray( cData.counties ) || ! cData.counties.length ) {
			box.innerHTML = emptyHTML( 'نقشهٔ شهرستان‌های این استان در دسترس نیست.' );
			return;
		}

		var ns = 'http://www.w3.org/2000/svg';
		countySvg = document.createElementNS( ns, 'svg' );
		countySvg.setAttribute( 'viewBox', cData.viewBox );
		countySvg.setAttribute( 'data-base-vb', cData.viewBox );
		countySvg.setAttribute( 'class', 'sa-mapsvg sa-cyber-svg sa-cyber-county-svg' );
		countySvg.setAttribute( 'role', 'group' );
		countySvg.setAttribute( 'aria-label', 'نقشه شهرستان‌های استان ' + ( p ? p.name : provinceSlug ) );

		var vbParts = cData.viewBox.split( /\s+/ ).map( Number );
		var vx = vbParts[ 0 ], vy = vbParts[ 1 ], vw = vbParts[ 2 ], vh = vbParts[ 3 ];
		var span = Math.max( vw, vh );

		// خطوط شبکهٔ سایبری متناسب با ابعاد استان
		var gridG = document.createElementNS( ns, 'g' );
		gridG.setAttribute( 'class', 'sa-cyber-svg-grid' );
		gridG.setAttribute( 'aria-hidden', 'true' );
		var step = span / 8;
		var i;
		for ( i = 1; i < 8; i++ ) {
			var vl = document.createElementNS( ns, 'line' );
			vl.setAttribute( 'x1', ( vx + step * i ).toFixed( 1 ) );
			vl.setAttribute( 'y1', vy );
			vl.setAttribute( 'x2', ( vx + step * i ).toFixed( 1 ) );
			vl.setAttribute( 'y2', ( vy + vh ).toFixed( 1 ) );
			gridG.appendChild( vl );

			var hl = document.createElementNS( ns, 'line' );
			hl.setAttribute( 'x1', vx );
			hl.setAttribute( 'y1', ( vy + ( vh / 8 ) * i ).toFixed( 1 ) );
			hl.setAttribute( 'x2', ( vx + vw ).toFixed( 1 ) );
			hl.setAttribute( 'y2', ( vy + ( vh / 8 ) * i ).toFixed( 1 ) );
			gridG.appendChild( hl );
		}
		countySvg.appendChild( gridG );

		var pathsG = document.createElementNS( ns, 'g' );
		pathsG.setAttribute( 'class', 'sa-cyber-counties-layer' );
		var labelsG = document.createElementNS( ns, 'g' );
		labelsG.setAttribute( 'class', 'sa-cyber-county-labels-layer' );

		// اندازهٔ فونت انگلیسی سبز رنگ متناسب با viewBox استان (خیلی ریز، دقیق و کاملاً خوانا روی نقشه)
		var countN = cData.counties.length;
		var densityFactor = countN > 24 ? 0.0165 : ( countN > 14 ? 0.019 : 0.022 );
		var baseFontSize = Math.max( 0.85, Math.min( 7.5, span * densityFactor ) );
		var dotR = Math.max( 0.28, baseFontSize * 0.22 );
		var haloW = Math.max( 0.24, baseFontSize * 0.26 );

		var maxShare = Math.max.apply( null, cData.counties.map( function ( c ) { return c.area_share || 1; } ) );

		cData.counties.forEach( function ( c, idx ) {
			var path = document.createElementNS( ns, 'path' );
			path.setAttribute( 'd', c.path );
			path.setAttribute( 'data-county', c.slug );
			path.setAttribute( 'tabindex', '0' );
			path.setAttribute( 'role', 'button' );
			path.setAttribute( 'aria-label', 'شهرستان ' + c.fa + ' (' + c.en + ')' );

			var t = maxShare > 0 ? ( c.area_share || 1 ) / maxShare : 0.5;
			path.style.fill = lerpColor( '#071d2c', '#0d4a42', 0.25 + 0.65 * t );

			path.addEventListener( 'click', function () {
				selectCounty( provinceSlug, c.slug, true );
			} );
			path.addEventListener( 'keydown', function ( ev ) {
				if ( ev.key === 'Enter' || ev.key === ' ' ) {
					ev.preventDefault();
					selectCounty( provinceSlug, c.slug, true );
				}
			} );
			path.addEventListener( 'pointerenter', function ( ev ) {
				showTip( 'شهرستان ' + c.fa + ' [' + c.en + '] — مرکز: ' + c.center_fa + ' · ' + c.lat.toFixed( 2 ) + '°N, ' + c.lng.toFixed( 2 ) + '°E', ev.clientX, ev.clientY );
			} );
			path.addEventListener( 'pointermove', function ( ev ) {
				showTip( 'شهرستان ' + c.fa + ' [' + c.en + '] — مرکز: ' + c.center_fa + ' · ' + c.lat.toFixed( 2 ) + '°N, ' + c.lng.toFixed( 2 ) + '°E', ev.clientX, ev.clientY );
			} );
			path.addEventListener( 'pointerleave', hideTip );

			pathsG.appendChild( path );

			// برچسب انگلیسی سبز رنگ ریز و خوانا روی نقشهٔ هر شهرستان
			var lg = document.createElementNS( ns, 'g' );
			lg.setAttribute( 'class', 'sa-county-label' );
			lg.setAttribute( 'data-county', c.slug );

			var cFontSize = ( c.area_share < 1.8 ? baseFontSize * 0.84 : baseFontSize ).toFixed( 2 );

			var dot = document.createElementNS( ns, 'circle' );
			dot.setAttribute( 'cx', c.lx );
			dot.setAttribute( 'cy', c.ly );
			dot.setAttribute( 'r', dotR.toFixed( 2 ) );
			dot.setAttribute( 'class', 'sa-county-label__dot' );
			lg.appendChild( dot );

			var txtEn = document.createElementNS( ns, 'text' );
			txtEn.setAttribute( 'x', c.lx );
			txtEn.setAttribute( 'y', ( c.ly - dotR * 1.5 ).toFixed( 2 ) );
			txtEn.setAttribute( 'text-anchor', 'middle' );
			txtEn.setAttribute( 'class', 'sa-county-label__en' );
			txtEn.setAttribute( 'style', 'font-size:' + cFontSize + 'px;stroke-width:' + haloW.toFixed( 2 ) + 'px;' );
			txtEn.textContent = c.en;
			lg.appendChild( txtEn );

			labelsG.appendChild( lg );
		} );

		countySvg.appendChild( pathsG );
		countySvg.appendChild( labelsG );

		box.innerHTML = '';
		var hudTop = make( 'div', 'sa-cyber-map-hud sa-cyber-map-hud--county',
			'<span class="sa-cyber-map-hud__left" dir="ltr">COUNTY.GRID // ' + esc( ( p ? p.en : provinceSlug ).toUpperCase() ) + ' · ' + cData.counties.length + ' SECTORS</span>' +
			'<span class="sa-cyber-map-hud__right" dir="ltr">TOUCH ANY COUNTY FOR IDENTITY CARD ↓</span>'
		);
		box.appendChild( hudTop );
		box.appendChild( countySvg );

		// نوار تراشه‌های شهرستان‌ها در زیر نقشه برای دسترسی سریع
		if ( strip ) {
			strip.innerHTML = cData.counties.map( function ( c, i ) {
				var idxStr = ( i + 1 ) < 10 ? '0' + ( i + 1 ) : String( i + 1 );
				return '<button type="button" class="sa-cyber-strip-btn" data-county="' + esc( c.slug ) + '">' +
					'<span class="sa-cyber-strip-btn__idx" dir="ltr">' + idxStr + '</span>' +
					'<span class="sa-cyber-strip-btn__fa">' + esc( c.fa ) + '</span>' +
					'<span class="sa-cyber-strip-btn__en" dir="ltr">' + esc( c.en ) + '</span>' +
				'</button>';
			} ).join( '' );

			strip.querySelectorAll( '.sa-cyber-strip-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					selectCounty( provinceSlug, btn.getAttribute( 'data-county' ), true );
				} );
			} );
		}
	}

	function applyCountyZoom( factor ) {
		if ( ! countySvg ) {
			return;
		}
		var baseVb = countySvg.getAttribute( 'data-base-vb' );
		if ( ! baseVb ) {
			return;
		}
		state.countyZoom = Math.max( 1, Math.min( 3.2, factor ) );
		var parts = baseVb.split( /\s+/ ).map( Number );
		var bx = parts[ 0 ], by = parts[ 1 ], bw = parts[ 2 ], bh = parts[ 3 ];
		if ( state.countyZoom === 1 ) {
			countySvg.setAttribute( 'viewBox', baseVb );
			return;
		}
		// مرکز زوم روی شهرستان انتخابی فعلی یا مرکز استان
		var cx = bx + bw / 2;
		var cy = by + bh / 2;
		var cData = COUNTY_SHAPES[ state.mapSel ];
		if ( cData && state.countySel ) {
			var selC = cData.counties.filter( function ( c ) { return c.slug === state.countySel; } )[ 0 ];
			if ( selC ) {
				cx = selC.lx;
				cy = selC.ly;
			}
		}
		var nw = bw / state.countyZoom;
		var nh = bh / state.countyZoom;
		var nx = Math.max( bx, Math.min( bx + bw - nw, cx - nw / 2 ) );
		var ny = Math.max( by, Math.min( by + bh - nh, cy - nh / 2 ) );
		countySvg.setAttribute( 'viewBox', nx.toFixed( 1 ) + ' ' + ny.toFixed( 1 ) + ' ' + nw.toFixed( 1 ) + ' ' + nh.toFixed( 1 ) );
	}

	function selectCounty( provinceSlug, countySlug, shouldScroll ) {
		var cData = COUNTY_SHAPES[ provinceSlug ];
		var p = PROV_BY_SLUG[ provinceSlug ];
		if ( ! cData || ! p ) {
			return;
		}
		var county = cData.counties.filter( function ( c ) {
			return c.slug === countySlug;
		} )[ 0 ] || cData.counties[ 0 ];
		if ( ! county ) {
			return;
		}

		state.countySel = county.slug;

		if ( countySvg ) {
			countySvg.querySelectorAll( '.sa-cyber-counties-layer path' ).forEach( function ( path ) {
				path.classList.toggle( 'is-sel', path.getAttribute( 'data-county' ) === county.slug );
			} );
			countySvg.querySelectorAll( '.sa-county-label' ).forEach( function ( lg ) {
				lg.classList.toggle( 'is-sel', lg.getAttribute( 'data-county' ) === county.slug );
			} );
		}

		var strip = byId( 'sa-county-strip' );
		if ( strip ) {
			strip.querySelectorAll( '.sa-cyber-strip-btn' ).forEach( function ( btn ) {
				btn.classList.toggle( 'is-sel', btn.getAttribute( 'data-county' ) === county.slug );
			} );
		}

		renderCountyIdentityCard( provinceSlug, county );

		if ( shouldScroll ) {
			var cFrame = byId( 'sa-county-id-frame' );
			if ( cFrame ) {
				cFrame.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
		}
	}

	function renderCountyIdentityCard( provinceSlug, c ) {
		var frame = byId( 'sa-county-id-frame' );
		if ( ! frame ) {
			return;
		}
		var p = PROV_BY_SLUG[ provinceSlug ];
		var cData = COUNTY_SHAPES[ provinceSlug ];
		var countyBySlug = {};
		if ( cData && Array.isArray( cData.counties ) ) {
			cData.counties.forEach( function ( item ) {
				countyBySlug[ item.slug ] = item;
			} );
		}

		var countyUrl = ( DATA.county_urls && DATA.county_urls[ c.slug ] ) ? DATA.county_urls[ c.slug ] : '';
		var distText = c.dist_km === 0
			? '۰ کیلومتر (شهرستان مرکز استان ' + p.name + ')'
			: 'حدود ' + faN( c.dist_km ) + ' کیلومتر تا ' + p.capital;

		var neighHtml = '';
		if ( Array.isArray( c.neighbors ) && c.neighbors.length ) {
			neighHtml = c.neighbors.map( function ( nSlug ) {
				var nc = countyBySlug[ nSlug ];
				if ( ! nc ) {
					return '';
				}
				return '<button type="button" class="sa-cyber-county-pill sa-cyber-neighbor-btn" data-cslug="' + esc( nc.slug ) + '">' +
					'<span class="sa-cyber-county-pill__fa">شهرستان ' + esc( nc.fa ) + '</span>' +
					'<span class="sa-cyber-county-pill__en" dir="ltr">' + esc( nc.en ) + '</span>' +
				'</button>';
			} ).join( '' );
		} else {
			neighHtml = '<span class="sa-cyber-id-chip">مرز مستقل استانی / جزیره‌ای در نقشهٔ استان ' + esc( p.name ) + '</span>';
		}

		// جاذبه‌های ثبت‌شده در این شهرستان یا استان
		var countyAttrs = ( DATA.attractions || [] ).filter( function ( a ) {
			return a.province === provinceSlug && ( a.county === c.fa || a.county === c.center_fa );
		} );
		var attrsHtml = '';
		if ( countyAttrs.length ) {
			attrsHtml = countyAttrs.map( function ( a ) {
				return '<span class="sa-cyber-id-chip sa-cyber-id-chip--attr">★ ' + esc( a.name ) + ( a.badge ? ' (' + esc( a.badge ) + ')' : '' ) + '</span>';
			} ).join( ' ' );
		}

		frame.innerHTML =
			'<section class="sa-cyber-idcard sa-cyber-idcard--county" aria-label="شناسنامه شهرستان ' + esc( c.fa ) + '">' +
				'<header class="sa-cyber-idcard__head">' +
					'<div class="sa-cyber-idcard__titles">' +
						'<span class="sa-cyber-idcard__badge sa-cyber-idcard__badge--county">شناسنامهٔ شهرستان</span>' +
						'<h3 class="sa-cyber-idcard__h3">شهرستان ' + esc( c.fa ) + ' <small>— استان ' + esc( p.name ) + '</small></h3>' +
						'<span class="sa-cyber-idcard__en" dir="ltr">COUNTY IDENTITY // ' + esc( c.en.toUpperCase() ) + ' COUNTY · ' + esc( ( p.en || '' ).toUpperCase() ) + '</span>' +
					'</div>' +
					'<div class="sa-cyber-idcard__actions">' +
						( countyUrl ? '<a class="sa-cyber-action-btn sa-cyber-action-btn--primary" href="' + esc( countyUrl ) + '"><span>مشاهدهٔ دانشنامهٔ کامل شهرستان ' + esc( c.fa ) + ' ↗</span><small dir="ltr">OPEN FULL COUNTY ARTICLE</small></a>' : '' ) +
						( p.url ? '<a class="sa-cyber-action-btn sa-cyber-action-btn--ghost" href="' + esc( p.url ) + '">صفحهٔ استان ' + esc( p.name ) + ' ↗</a>' : '' ) +
					'</div>' +
				'</header>' +
				'<div class="sa-cyber-idcard__grid" role="table" aria-label="جدول اطلاعات شناسنامه شهرستان">' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">01 // COUNTY NAME (EN)</span>' +
						'<span class="sa-cyber-idcard__lbl">نام انگلیسی شهرستان</span>' +
						'<strong class="sa-cyber-idcard__val sa-cyber-idcard__val--en" dir="ltr">' + esc( c.en ) + ' County</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">02 // PARENT PROVINCE</span>' +
						'<span class="sa-cyber-idcard__lbl">استان متبوع</span>' +
						'<strong class="sa-cyber-idcard__val">استان ' + esc( p.name ) + ' <small dir="ltr">(' + esc( p.en ) + ')</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">03 // ADMINISTRATIVE CENTER</span>' +
						'<span class="sa-cyber-idcard__lbl">مرکز شهرستان</span>' +
						'<strong class="sa-cyber-idcard__val">شهر ' + esc( c.center_fa ) + ' <small dir="ltr">(' + esc( c.center_en ) + ')</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">04 // GEO COORDINATES</span>' +
						'<span class="sa-cyber-idcard__lbl">مختصات جغرافیایی مرکز شهرستان</span>' +
						'<strong class="sa-cyber-idcard__val sa-cyber-idcard__val--en" dir="ltr">' + c.lat.toFixed( 4 ) + '° N, ' + c.lng.toFixed( 4 ) + '° E</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">05 // PROVINCE SECTOR</span>' +
						'<span class="sa-cyber-idcard__lbl">موقعیت نسبی در استان</span>' +
						'<strong class="sa-cyber-idcard__val">' + esc( c.pos_fa ) + ' <small dir="ltr">(' + esc( c.pos_en ) + ')</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">06 // DISTANCE TO PROVINCE CAPITAL</span>' +
						'<span class="sa-cyber-idcard__lbl">فاصلهٔ هوایی تا مرکز استان (' + esc( p.capital ) + ')</span>' +
						'<strong class="sa-cyber-idcard__val">' + esc( distText ) + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">07 // AREA SHARE IN PROVINCE</span>' +
						'<span class="sa-cyber-idcard__lbl">سهم پهنه‌ای در نقشهٔ استان ' + esc( p.name ) + '</span>' +
						'<strong class="sa-cyber-idcard__val">' + faN1( c.area_share ) + '٪ از پهنهٔ استان <small dir="ltr">(' + esc( c.srcId ) + ')</small></strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">08 // REGIONAL CLIMATE</span>' +
						'<span class="sa-cyber-idcard__lbl">اقلیم پهنهٔ استانی</span>' +
						'<strong class="sa-cyber-idcard__val">' + esc( p.climate || 'نیازمند بازبینی' ) + '</strong>' +
					'</div>' +
					'<div class="sa-cyber-idcard__cell sa-cyber-idcard__cell--wide" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">09 // ADJACENT COUNTIES IN ' + esc( ( p.en || '' ).toUpperCase() ) + ' (CLICK TO SWITCH COUNTY)</span>' +
						'<span class="sa-cyber-idcard__lbl">شهرستان‌های هم‌مرز و همجوار در استان ' + esc( p.name ) + ' (با کلیک روی هر همسایه، شناسنامهٔ آن نمایش داده می‌شود)</span>' +
						'<div class="sa-cyber-idcard__counties">' + neighHtml + '</div>' +
					'</div>' +
					( attrsHtml ?
					'<div class="sa-cyber-idcard__cell sa-cyber-idcard__cell--wide" role="cell">' +
						'<span class="sa-cyber-idcard__k" dir="ltr">10 // FEATURED HERITAGE &amp; NATURAL SITES</span>' +
						'<span class="sa-cyber-idcard__lbl">جاذبه‌های شاخص ثبت‌شده در این شهرستان</span>' +
						'<div class="sa-cyber-idcard__chips">' + attrsHtml + '</div>' +
					'</div>' : '' ) +
				'</div>' +
			'</section>';

		frame.querySelectorAll( '.sa-cyber-neighbor-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				selectCounty( provinceSlug, btn.getAttribute( 'data-cslug' ), false );
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * نمودارها و ستون‌های بصری سایبری
	 * ------------------------------------------------------------------ */

	function hbarRow( p, value, max, opts ) {
		opts = opts || {};
		var pct = max > 0 ? Math.max( 1.5, ( value / max ) * 100 ) : 0;
		var row = make( 'div', 'hbar' + ( opts.cls ? ' ' + opts.cls : '' ) );
		row.setAttribute( 'tabindex', '0' );
		row.setAttribute( 'role', 'button' );
		row.innerHTML =
			'<span class="hbar__name">' + esc( p.name ) + ' <small dir="ltr">' + esc( p.en || '' ) + '</small></span>' +
			'<span class="hbar__track" role="presentation"><span class="hbar__fill" style="width:' + pct.toFixed( 2 ) + '%"></span></span>' +
			'<span class="hbar__val">' + esc( opts.valueText ) + '</span>';
		if ( opts.tip ) {
			bindTip( row, opts.tip );
		}
		row.addEventListener( 'click', function () {
			switchStage( 1 );
			selectProvince( p.slug, true );
			var mapEl = byId( 'map' );
			if ( mapEl ) {
				mapEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
		return row;
	}

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
			var row = hbarRow( p, p.population, max, {
				cls: cls,
				valueText: faN( p.population ),
				tip: p.name + ' (' + p.en + ') — رتبهٔ ' + faN( i + 1 ) + ' جمعیت: ' + faN( p.population ) + ' نفر'
			} );
			box.appendChild( row );
		} );
	}

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
			return { name: p.name + ' (' + p.en + ')', value: p.area };
		} );
		if ( rest.length ) {
			segs.push( {
				name: 'سایر استان‌ها (OTHER)',
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
				' stroke-width="28" stroke-dasharray="' + dash.toFixed( 2 ) + ' ' + ( C - dash ).toFixed( 2 ) + '"' +
				' stroke-dashoffset="' + ( -offset ).toFixed( 2 ) + '" transform="rotate(-90 ' + CX + ' ' + CY + ')">' +
				'<title>' + esc( s.name ) + ': ' + faN( s.value ) + ' کیلومتر مربع</title></circle>';
			offset += frac * C;
		} );
		svg += '<text x="' + CX + '" y="' + ( CY - 6 ) + '" text-anchor="middle" class="donut__num">' + faN( total ) + '</text>' +
			'<text x="' + CX + '" y="' + ( CY + 14 ) + '" text-anchor="middle" class="donut__cap">KM² TOTAL</text></svg>';

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
			var row = hbarRow( p, p.counties, max, {
				cls: p.counties === max ? 'is-gold' : '',
				valueText: faN( p.counties ),
				tip: p.name + ' (' + p.en + '): ' + faN( p.counties ) + ' شهرستان'
			} );
			box.appendChild( row );
		} );
	}

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
			var color = COUNTRY_COLORS[ country ] || '#10b981';
			var provs = borderProvs.filter( function ( p ) {
				return p.countries.indexOf( country ) !== -1;
			} );
			html += '<div class="border-row' + ( provs.length ? '' : ' is-empty' ) + '">' +
				'<span class="border-row__country"><span class="swatch" style="background:' + color + '"></span>' + esc( country ) + '</span>' +
				'<span class="border-row__count">' + faN( provs.length ) + ' استان</span>' +
				'<span class="border-row__provs">' +
				( provs.length ? provs.map( function ( p ) {
					return '<button type="button" class="chip sa-border-prov-chip" data-slug="' + esc( p.slug ) + '">' + esc( p.name ) + '</button>';
				} ).join( '' ) : '<span class="chip chip--none">—</span>' ) +
				'</span></div>';
		} );
		html += '</div>';
		box.innerHTML = html;
		box.querySelectorAll( '.sa-border-prov-chip' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				switchStage( 1 );
				selectProvince( btn.getAttribute( 'data-slug' ), true );
				byId( 'map' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );
	}

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
			return d >= t2 ? '#00ff9d' : d >= t1 ? '#38bdf8' : '#8b5cf6';
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
		svg += '<text x="' + ( ( W - mL + mR ) / 2 ) + '" y="' + ( H - 6 ) + '" text-anchor="middle" class="axis-label">AREA (KM²) // مساحت</text>' +
			'<text x="16" y="' + ( ( H - mB + mT ) / 2 ) + '" text-anchor="middle" class="axis-label" transform="rotate(-90 16 ' + ( ( H - mB + mT ) / 2 ) + ')">POPULATION // جمعیت</text>';

		list.forEach( function ( p ) {
			var cx = x( p.area ).toFixed( 1 ), cy = y( p.population ).toFixed( 1 );
			svg += '<circle cx="' + cx + '" cy="' + cy + '" r="7" fill="' + dcolor( p.density || 0 ) + '" data-slug="' + esc( p.slug ) + '" tabindex="0" role="button"' +
				' aria-label="' + esc( p.name ) + ': جمعیت ' + faN( p.population ) + ' نفر، مساحت ' + faN( p.area ) + ' کیلومتر مربع"></circle>';
		} );
		svg += '</svg>';

		var legend = '<p class="scatter-legend">' +
			'<span><span class="swatch" style="background:#8b5cf6"></span> تراکم کمتر</span>' +
			'<span><span class="swatch" style="background:#38bdf8"></span> تراکم میانه</span>' +
			'<span><span class="swatch" style="background:#00ff9d"></span> تراکم بالا</span></p>';

		box.innerHTML = svg + legend;

		box.querySelectorAll( 'circle' ).forEach( function ( c ) {
			var p = PROV_BY_SLUG[ c.getAttribute( 'data-slug' ) ];
			if ( ! p ) {
				return;
			}
			var text = p.name + ' (' + p.en + ') — جمعیت: ' + faN( p.population ) + ' نفر · مساحت: ' + faN( p.area ) + ' km²';
			bindTip( c, text );
			c.addEventListener( 'click', function () {
				switchStage( 1 );
				selectProvince( p.slug, true );
				byId( 'map' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );
	}

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
			var row = hbarRow( p, p.density || 0, max, {
				cls: i < 3 ? 'is-top is-top-' + ( i + 1 ) : '',
				valueText: faN1( p.density ),
				tip: p.name + ' (' + p.en + '): ' + faN1( p.density ) + ' نفر بر کیلومتر مربع'
			} );
			box.appendChild( row );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * جدول ماتریسی ۳۱ استان
	 * ------------------------------------------------------------------ */

	var PAGE_SIZE = 11;

	function tableRows() {
		var list = filtered();
		var q = state.search.trim().toLowerCase();
		if ( q ) {
			list = list.filter( function ( p ) {
				return ( p.name + ' ' + p.capital + ' ' + p.neighbors + ' ' + p.countries.join( ' ' ) + ' ' + ( p.en || '' ) ).toLowerCase().indexOf( q ) !== -1;
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
			return '<tr class="sa-table-row' + ( p.slug === state.mapSel ? ' is-active-row' : '' ) + '" data-slug="' + esc( p.slug ) + '">' +
				'<td><span class="rank' + medal + '">' + faN( rank ) + '</span></td>' +
				'<th scope="row"><div class="sa-t-prov"><strong>' + esc( p.name ) + '</strong><small dir="ltr">' + esc( p.en ) + '</small></div></th>' +
				'<td>' + esc( p.capital ) + '</td>' +
				'<td class="num">' + faN( p.population ) + '</td>' +
				'<td class="num">' + faN( p.area ) + '</td>' +
				'<td class="num">' + faN1( p.density ) + '</td>' +
				'<td class="num">' + faN( p.counties ) + '</td>' +
				'<td>' + ( p.border ? '<span class="pill pill--yes">مرزی (' + esc( p.countries.join( '، ' ) ) + ')</span>' : '<span class="pill pill--no">داخلی</span>' ) + '</td>' +
				'<td>' +
					'<div class="sa-t-actions">' +
						'<button type="button" class="sa-t-btn sa-t-btn--id" data-action="id" data-slug="' + esc( p.slug ) + '">شناسنامه استان</button>' +
						'<button type="button" class="sa-t-btn sa-t-btn--counties" data-action="counties" data-slug="' + esc( p.slug ) + '">نقشه شهرستان‌ها ←</button>' +
					'</div>' +
				'</td>' +
				'<td class="num">' + faN( p.attractions ) + '</td>' +
			'</tr>';
		} ).join( '' );

		tbody.querySelectorAll( '.sa-t-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();
				var s = btn.getAttribute( 'data-slug' );
				var act = btn.getAttribute( 'data-action' );
				if ( act === 'counties' ) {
					openProvinceCountyMap( s );
				} else {
					switchStage( 1 );
					selectProvince( s, true );
					byId( 'map' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} );
		} );

		tbody.querySelectorAll( '.sa-table-row' ).forEach( function ( tr ) {
			tr.addEventListener( 'click', function () {
				var s = tr.getAttribute( 'data-slug' );
				switchStage( 1 );
				selectProvince( s, true );
				byId( 'map' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );

		if ( pager ) {
			if ( pages <= 1 ) {
				pager.innerHTML = '';
			} else {
				var phtml = '';
				for ( var i = 1; i <= pages; i++ ) {
					phtml += '<button type="button" class="sa-pager__btn' + ( i === state.page ? ' is-current' : '' ) + '" data-p="' + i + '">' + faN( i ) + '</button>';
				}
				pager.innerHTML = phtml;
				pager.querySelectorAll( 'button' ).forEach( function ( b ) {
					b.addEventListener( 'click', function () {
						state.page = parseInt( b.getAttribute( 'data-p' ), 10 ) || 1;
						renderTable();
					} );
				} );
			}
		}
	}

	function renderTableHead() {
		document.querySelectorAll( '#sa-table .sa-sort' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var k = btn.getAttribute( 'data-key' );
				if ( state.sortKey === k ) {
					state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
				} else {
					state.sortKey = k;
					state.sortDir = ( k === 'name' || k === 'capital' || k === 'rank' ) ? 'asc' : 'desc';
				}
				document.querySelectorAll( '#sa-table .sa-sort' ).forEach( function ( b ) {
					b.classList.remove( 'is-asc', 'is-desc' );
				} );
				btn.classList.add( state.sortDir === 'asc' ? 'is-asc' : 'is-desc' );
				renderTable();
			} );
		} );
	}

	function exportCsv() {
		var rows = tableRows();
		if ( ! rows.length ) {
			toast( 'ردیفی برای خروجی CSV وجود ندارد.' );
			return;
		}
		var lines = [ [ 'رتبه جمعیت', 'استان', 'نام انگلیسی', 'مرکز', 'جمعیت', 'مساحت (کیلومتر مربع)', 'تراکم', 'شهرستان‌ها', 'مرز بین‌المللی', 'کشورهای همسایه' ].join( ',' ) ];
		rows.forEach( function ( p ) {
			lines.push( [
				POP_RANK[ p.slug ],
				'"' + p.name + '"',
				'"' + ( p.en || '' ) + '"',
				'"' + p.capital + '"',
				p.population,
				p.area,
				p.density || 0,
				p.counties,
				p.border ? 'بله' : 'خیر',
				'"' + p.countries.join( ' | ' ) + '"'
			].join( ',' ) );
		} );
		var blob = new Blob( [ '\uFEFF' + lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8;' } );
		var a = document.createElement( 'a' );
		a.href = URL.createObjectURL( blob );
		a.download = 'iran-provinces-telemetry.csv';
		document.body.appendChild( a );
		a.click();
		a.remove();
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
		var list = ( DATA.attractions || [] ).slice();
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
						( a.badge ? ' <span class="pill pill--gold">★ ' + esc( a.badge ) + '</span>' : '' ) +
					'</p>' +
					( a.note ? '<p class="sa-acard__note">' + esc( a.note ) + '</p>' : '' ) +
				'</div>' +
			'</article>';
		} ).join( '' );
	}

	/* ------------------------------------------------------------------ *
	 * کنترل‌ها و راه‌اندازی
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
		if ( list.length && ! list.some( function ( p ) { return p.slug === state.mapSel; } ) ) {
			state.mapSel = list[ 0 ].slug;
		}
		renderMapInfo();
		renderProvinceIdentityCard( state.mapSel );
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
		byId( 'sa-f-border' ).checked = false;
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

	function populateSelects() {
		var sortedProvs = PROVINCES.slice().sort( function ( a, b ) {
			return faSort( a.name, b.name );
		} );
		var fProv = byId( 'sa-f-province' );
		var jumpProv = byId( 'sa-cyber-prov-jump' );

		sortedProvs.forEach( function ( p ) {
			if ( fProv ) {
				var o1 = document.createElement( 'option' );
				o1.value = p.slug;
				o1.textContent = p.name + ' (' + p.en + ')';
				fProv.appendChild( o1 );
			}
			if ( jumpProv ) {
				var o2 = document.createElement( 'option' );
				o2.value = p.slug;
				o2.textContent = p.name + ' — ' + p.en + ' (' + faN( p.counties ) + ' شهرستان)';
				jumpProv.appendChild( o2 );
			}
		} );
	}

	function bindControls() {
		var tab1 = byId( 'sa-tab-stage-1' );
		var tab2 = byId( 'sa-tab-stage-2' );
		var backBtn = byId( 'sa-back-to-iran' );
		var jumpProv = byId( 'sa-cyber-prov-jump' );

		if ( tab1 ) {
			tab1.addEventListener( 'click', function () {
				switchStage( 1 );
			} );
		}
		if ( tab2 ) {
			tab2.addEventListener( 'click', function () {
				openProvinceCountyMap( state.mapSel || 'tehran' );
			} );
		}
		if ( backBtn ) {
			backBtn.addEventListener( 'click', function () {
				switchStage( 1 );
			} );
		}
		if ( jumpProv ) {
			jumpProv.addEventListener( 'change', function ( e ) {
				var val = e.target.value;
				if ( ! val ) {
					return;
				}
				if ( state.stage === 2 ) {
					openProvinceCountyMap( val );
				} else {
					selectProvince( val, true );
				}
			} );
		}

		var zIn = byId( 'sa-county-zoom-in' );
		var zOut = byId( 'sa-county-zoom-out' );
		var zReset = byId( 'sa-county-zoom-reset' );
		if ( zIn ) {
			zIn.addEventListener( 'click', function () {
				applyCountyZoom( state.countyZoom * 1.35 );
			} );
		}
		if ( zOut ) {
			zOut.addEventListener( 'click', function () {
				applyCountyZoom( state.countyZoom / 1.35 );
			} );
		}
		if ( zReset ) {
			zReset.addEventListener( 'click', function () {
				applyCountyZoom( 1 );
			} );
		}

		byId( 'sa-f-province' ).addEventListener( 'change', function ( e ) {
			state.province = e.target.value;
			if ( state.province !== 'all' ) {
				selectProvince( state.province, false );
			}
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
		if ( searchEl ) {
			searchEl.addEventListener( 'input', function ( e ) {
				state.search = e.target.value;
				state.page = 1;
				renderTable();
			} );
		}

		byId( 'sa-t-csv' ).addEventListener( 'click', exportCsv );

		var metricSel = byId( 'sa-map-metric' );
		if ( metricSel ) {
			metricSel.addEventListener( 'change', function ( e ) {
				state.mapMetric = e.target.value;
				colorMap();
			} );
		}

		byId( 'sa-a-cat' ).addEventListener( 'change', function ( e ) {
			state.attrCat = e.target.value;
			renderAttractions();
		} );
		byId( 'sa-a-sort' ).addEventListener( 'change', function ( e ) {
			state.attrSort = e.target.value;
			renderAttractions();
		} );
	}

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

	function init() {
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

		populateSelects();
		bindControls();
		renderTableHead();
		buildMap();
		selectProvince( 'tehran', false );
		syncOutputs();
		applyAll();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
