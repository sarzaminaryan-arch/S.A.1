<?php
/**
 * Template Name: داشبورد جامع گردشگری ایران
 * Template Post Type: page
 *
 * داشبورد سایبری و نقشهٔ تعاملی دومرحله‌ای شناسنامهٔ استان‌ها و شهرستان‌های ایران
 * (فضای سایبری تیره با تله‌متری انگلیسی سبز، نقشهٔ مرحلهٔ ۱ ایران -> شناسنامهٔ استان ->
 * نقشهٔ مرحلهٔ ۲ شهرستان‌های استان با برچسب‌های انگلیسی ریز و خوانا -> شناسنامهٔ شهرستان + نمودارها).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sa_dashboard_enabled' ) || ! sa_dashboard_enabled() ) {
	get_header();
	echo '<main id="primary" class="site-main container" dir="rtl"><p>داشبورد در حال حاضر غیرفعال است.</p></main>';
	get_footer();
	return;
}

$sa_dash_data = sa_dashboard_dataset();
$sa_dash_json = wp_json_encode( $sa_dash_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );

get_header();
?>
<main id="primary" class="site-main sa-dash sa-cyber-dash" dir="rtl" lang="fa">

	<!-- ============ هدر فرماندهی سایبری (CYBER HUD HEADER) ============ -->
	<header class="sa-dash__hero">
		<div class="sa-dash__wrap">
			<div class="sa-cyber-hudbar" dir="ltr">
				<span class="sa-cyber-hudbar__dot" aria-hidden="true"></span>
				<span class="sa-cyber-hudbar__item">SYS.GEO.IRAN // V<?php echo esc_html( SA_CHILD_VERSION ); ?></span>
				<span class="sa-cyber-hudbar__sep">//</span>
				<span class="sa-cyber-hudbar__item">NODES: 31 PROVINCES · 483 COUNTIES</span>
				<span class="sa-cyber-hudbar__sep">//</span>
				<span class="sa-cyber-hudbar__item">MODE: 2-STAGE INTERACTIVE IDENTITY MATRIX</span>
				<span class="sa-cyber-hudbar__sep">//</span>
				<span class="sa-cyber-hudbar__status">TELEMETRY: ONLINE</span>
			</div>

			<div class="sa-dash__hero-grid">
				<div class="sa-dash__hero-main">
					<p class="sa-dash__eyebrow">
						<span class="sa-dash__eyebrow-fa">سامانهٔ هوشمند ژئوتوریسم سرزمین آریان</span>
						<span class="sa-dash__eyebrow-en" dir="ltr">IRAN CYBER GEO-TELEMETRY &amp; IDENTITY COMMAND</span>
					</p>
					<h1 class="sa-dash__title"><?php echo esc_html( get_the_title() ? get_the_title() : 'داشبورد جامع گردشگری و شناسنامهٔ تعاملی ایران' ); ?></h1>
					<p class="sa-dash__subtitle">
						در <strong>مرحلهٔ اول</strong> روی هر استان در نقشهٔ ایران لمس یا کلیک کنید تا <strong>شناسنامهٔ کامل استان</strong> و نمودارهای ستونی آن در کادر زیر نقشه نمایش داده شود؛ سپس با زدن دکمهٔ سبز <strong>«ورود به نقشهٔ شهرستان‌ها»</strong> وارد <strong>مرحلهٔ دوم</strong> شوید تا نقشهٔ اختصاصی همان استان با <strong>تفکیک شهرستان‌ها و نام انگلیسی سبز رنگ (EN)</strong> باز شود و با لمس هر شهرستان، <strong>شناسنامهٔ شهرستان</strong> را در کادر زیر آن ببینید.
					</p>
				</div>
				<div class="sa-dash__hero-stats" dir="ltr" aria-label="National Telemetry Summary">
					<div class="sa-cyber-mini-stat">
						<span class="sa-cyber-mini-stat__lbl">PROVINCES // استان‌ها</span>
						<strong class="sa-cyber-mini-stat__val">31</strong>
						<small class="sa-cyber-mini-stat__sub">ISO IR-00..IR-30</small>
					</div>
					<div class="sa-cyber-mini-stat">
						<span class="sa-cyber-mini-stat__lbl">COUNTIES // شهرستان‌ها</span>
						<strong class="sa-cyber-mini-stat__val">483</strong>
						<small class="sa-cyber-mini-stat__sub">478 MAP POLYGONS</small>
					</div>
					<div class="sa-cyber-mini-stat">
						<span class="sa-cyber-mini-stat__lbl">AREA // مساحت کشور</span>
						<strong class="sa-cyber-mini-stat__val">1.648M</strong>
						<small class="sa-cyber-mini-stat__sub">KM² OFFICIAL</small>
					</div>
					<div class="sa-cyber-mini-stat">
						<span class="sa-cyber-mini-stat__lbl">POPULATION // جمعیت</span>
						<strong class="sa-cyber-mini-stat__val">79.92M</strong>
						<small class="sa-cyber-mini-stat__sub">CENSUS 1395</small>
					</div>
				</div>
			</div>

			<nav class="sa-dash__subnav" aria-label="بخش‌های داشبورد">
				<a href="#map" class="is-primary-pill"><span dir="ltr">01 // MAP &amp; ID</span> نقشهٔ ۲مرحله‌ای و شناسنامه</a>
				<a href="#kpis"><span dir="ltr">02 // KPI</span> شاخص‌های کلیدی</a>
				<a href="#charts"><span dir="ltr">03 // CHARTS</span> نمودارها و ستون‌ها</a>
				<a href="#table"><span dir="ltr">04 // MATRIX</span> جدول ۳۱ استان</a>
				<a href="#attractions"><span dir="ltr">05 // SITES</span> جاذبه‌های شاخص</a>
			</nav>
		</div>
	</header>

	<div class="sa-dash__wrap">

		<!-- ==========================================================================
		     بخش ۱ (هستهٔ اصلی): نقشهٔ تعاملی ۲مرحله‌ای ایران و شهرستان‌ها + کادر شناسنامه
		     ========================================================================== -->
		<section id="map" class="sa-dash__sec sa-cyber-stage-sec" aria-label="نقشه تعاملی دومرحله‌ای ایران و شهرستان‌ها">

			<!-- نوار وضعیت مرحله (STAGE SWITCHER & CONTROLS) -->
			<div class="sa-cyber-stage-bar">
				<div class="sa-cyber-stage-tabs" role="tablist" aria-label="مراحل نقشه تعاملی">
					<button type="button" class="sa-cyber-stage-tab is-active" id="sa-tab-stage-1" role="tab" aria-selected="true" aria-controls="sa-stage-1-panel">
						<span class="sa-cyber-stage-tab__code" dir="ltr">STAGE 01 // IRAN-31</span>
						<span class="sa-cyber-stage-tab__title">نقشهٔ ایران با تقسیمات استانی</span>
					</button>
					<button type="button" class="sa-cyber-stage-tab" id="sa-tab-stage-2" role="tab" aria-selected="false" aria-controls="sa-stage-2-panel">
						<span class="sa-cyber-stage-tab__code" dir="ltr">STAGE 02 // COUNTIES-EN</span>
						<span class="sa-cyber-stage-tab__title" id="sa-tab-stage-2-label">نقشهٔ استان و شهرستان‌ها (انتخاب استان)</span>
					</button>
				</div>

				<div class="sa-cyber-stage-tools">
					<div class="sa-field sa-field--cyber">
						<label for="sa-cyber-prov-jump">
							<span>پرش سریع به استان</span>
							<small dir="ltr">PROVINCE SELECT</small>
						</label>
						<select id="sa-cyber-prov-jump">
							<option value="">— انتخاب از میان ۳۱ استان —</option>
						</select>
					</div>
					<div class="sa-field sa-field--cyber" id="sa-map-metric-wrap">
						<label for="sa-map-metric">
							<span>طیف رنگی نقشه</span>
							<small dir="ltr">CHOROPLETH METRIC</small>
						</label>
						<select id="sa-map-metric">
							<option value="population" selected>جمعیت (POPULATION)</option>
							<option value="density">تراکم جمعیت (DENSITY)</option>
							<option value="area">مساحت (AREA KM²)</option>
							<option value="counties">شمار شهرستان‌ها (COUNTIES)</option>
						</select>
					</div>
				</div>
			</div>

			<!-- ==================== STAGE 01: IRAN PROVINCES MAP + PROVINCE IDENTITY CARD ==================== -->
			<div id="sa-stage-1-panel" class="sa-cyber-stage-panel is-active" role="tabpanel" aria-labelledby="sa-tab-stage-1">
				<div class="sa-cyber-stage-hint">
					<span class="sa-cyber-stage-hint__badge" dir="ltr">STAGE 01 // GUIDE</span>
					<span>روی هر استان در نقشه لمس یا کلیک کنید تا <strong>شناسنامهٔ استان</strong> در کادر زیر نمایش داده شود. با زدن دکمهٔ سبز <strong>«ورود به نقشهٔ شهرستان‌های استان»</strong> (یا لمس مجدد همان استان) وارد نقشهٔ مرحلهٔ دوم (شهرستان‌ها) می‌شوید.</span>
				</div>

				<div class="sa-mapgrid sa-mapgrid--stage1">
					<!-- بوم نقشهٔ کل ایران -->
					<div class="sa-mapbox sa-cyber-mapbox" id="sa-mapbox">
						<div class="sa-sk sa-sk--tall"></div>
					</div>

					<!-- ستون کناری تله‌متری سریع و دکمه ورود به مرحله ۲ -->
					<aside class="sa-mapinfo sa-cyber-quickpanel" id="sa-mapinfo" aria-live="polite"></aside>
				</div>

				<!-- کادر شناسنامهٔ کامل استان در زیر نقشهٔ ایران (FRAMED PROVINCE IDENTITY CARD) -->
				<div class="sa-cyber-id-frame" id="sa-province-id-frame" aria-live="polite"></div>
			</div>

			<!-- ==================== STAGE 02: PROVINCE COUNTY MAP + COUNTY IDENTITY CARD ==================== -->
			<div id="sa-stage-2-panel" class="sa-cyber-stage-panel" role="tabpanel" aria-labelledby="sa-tab-stage-2" hidden>
				<div class="sa-cyber-stage2-bar">
					<div class="sa-cyber-stage2-bar__title">
						<span class="sa-cyber-stage2-bar__code" dir="ltr" id="sa-stage2-code">STAGE 02 // COUNTY GRID</span>
						<h3 class="sa-cyber-stage2-bar__h3" id="sa-stage2-heading">نقشهٔ شهرستان‌های استان</h3>
					</div>
					<div class="sa-cyber-stage2-bar__actions">
						<div class="sa-cyber-zoom-group" dir="ltr" aria-label="بزرگ‌نمایی نقشه شهرستان‌ها">
							<button type="button" class="sa-cyber-zoom-btn" id="sa-county-zoom-in" title="بزرگ‌نمایی (+)">+ ZOOM IN</button>
							<button type="button" class="sa-cyber-zoom-btn" id="sa-county-zoom-out" title="کوچک‌نمایی (-)">- ZOOM OUT</button>
							<button type="button" class="sa-cyber-zoom-btn" id="sa-county-zoom-reset" title="بازنشانی نما">RESET 1:1</button>
						</div>
						<button type="button" class="sa-cyber-back-btn" id="sa-back-to-iran">
							<span>→ بازگشت به نقشهٔ کل ایران</span>
							<small dir="ltr">BACK TO STAGE 01 // IRAN</small>
						</button>
					</div>
				</div>

				<div class="sa-cyber-stage-hint sa-cyber-stage-hint--county">
					<span class="sa-cyber-stage-hint__badge" dir="ltr">STAGE 02 // COUNTY EN MAP</span>
					<span>نام شهرستان‌ها به صورت <strong>انگلیسی سبز رنگ (EN)</strong> روی نقشهٔ استان درج شده است. با لمس یا کلیک روی هر شهرستان (روی نقشه یا نوار زیر آن)، <strong>شناسنامهٔ کامل شهرستان</strong> در کادر پایین نمایش داده می‌شود.</span>
				</div>

				<!-- بوم نقشهٔ استان به تفکیک شهرستان‌ها -->
				<div class="sa-cyber-county-mapwrap">
					<div class="sa-mapbox sa-cyber-mapbox sa-cyber-mapbox--county" id="sa-county-mapbox"></div>
					<!-- نوار تراشه‌های شهرستان‌های استان برای دسترسی سریع لمسی -->
					<div class="sa-cyber-county-strip" id="sa-county-strip" aria-label="فهرست شهرستان‌های استان"></div>
				</div>

				<!-- کادر شناسنامهٔ شهرستان در زیر نقشهٔ استان (FRAMED COUNTY IDENTITY CARD) -->
				<div class="sa-cyber-id-frame sa-cyber-id-frame--county" id="sa-county-id-frame" aria-live="polite"></div>
			</div>

		</section>

		<!-- ============ فیلترهای سراسری تله‌متری و نمودارها ============ -->
		<section class="sa-filters" aria-label="فیلترهای سراسری">
			<div class="sa-filters__head">
				<h2 class="sa-dash__h2 sa-dash__h2--inline">
					<span>فیلتر هوشمند تله‌متری و نمودارها</span>
					<small dir="ltr">GLOBAL TELEMETRY FILTERS // SYNCED</small>
				</h2>
				<p class="sa-dash__scope" id="sa-dash-scope" aria-live="polite"></p>
			</div>
			<div class="sa-filters__row">
				<div class="sa-field">
					<label for="sa-f-province">استان <small dir="ltr">PROVINCE</small></label>
					<select id="sa-f-province">
						<option value="all">همهٔ استان‌ها (۳۱)</option>
					</select>
				</div>
				<div class="sa-field">
					<label for="sa-f-pop-min">جمعیت — از <output id="sa-f-pop-min-out" for="sa-f-pop-min">۰</output></label>
					<input type="range" id="sa-f-pop-min" min="0" max="13500000" step="25000" value="0">
				</div>
				<div class="sa-field">
					<label for="sa-f-pop-max">تا <output id="sa-f-pop-max-out" for="sa-f-pop-max">۱۳٬۵۰۰٬۰۰۰</output> نفر</label>
					<input type="range" id="sa-f-pop-max" min="0" max="13500000" step="25000" value="13500000">
				</div>
				<div class="sa-field">
					<label for="sa-f-area-min">مساحت — از <output id="sa-f-area-min-out" for="sa-f-area-min">۰</output></label>
					<input type="range" id="sa-f-area-min" min="0" max="185000" step="1000" value="0">
				</div>
				<div class="sa-field">
					<label for="sa-f-area-max">تا <output id="sa-f-area-max-out" for="sa-f-area-max">۱۸۵٬۰۰۰</output> km²</label>
					<input type="range" id="sa-f-area-max" min="0" max="185000" step="1000" value="185000">
				</div>
				<div class="sa-field sa-field--check">
					<label for="sa-f-border">
						<input type="checkbox" id="sa-f-border">
						فقط استان‌های مرزنشین (BORDER ONLY)
					</label>
				</div>
				<div class="sa-field sa-field--btn">
					<button type="button" class="sa-btn sa-btn--ghost" id="sa-f-reset">بازنشانی (RESET)</button>
				</div>
			</div>
			<p class="sa-filters__note">فیلترها روی شاخص‌های کلیدی، نمودارهای ستونی، جدول ماتریسی، نقشه و جاذبه‌ها به‌صورت هم‌زمان اعمال می‌شوند.</p>
		</section>

		<!-- ============ شاخص‌های کلیدی (CYBER KPI TELEMETRY) ============ -->
		<section id="kpis" class="sa-dash__sec" aria-label="شاخص‌های کلیدی">
			<div class="sa-cyber-sec-head">
				<h2 class="sa-dash__h2">شاخص‌های کلیدی ملی و استانی</h2>
				<span class="sa-cyber-sec-code" dir="ltr">SYS.KPI // NATIONAL AGGREGATE METRICS</span>
			</div>
			<div class="sa-kpis" id="sa-kpis">
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
			</div>
			<p class="sa-dash__fineprint" id="sa-kpi-note"></p>
		</section>

		<!-- ============ نمودارها و ستون‌های بصری سایبری (VISUAL CHARTS & COLUMNS) ============ -->
		<section id="charts" class="sa-dash__sec" aria-label="نمودارها و ستون‌ها">
			<div class="sa-cyber-sec-head">
				<h2 class="sa-dash__h2">نمودارها و ستون‌های تحلیلی استان‌ها</h2>
				<span class="sa-cyber-sec-code" dir="ltr">SYS.CHARTS // COMPARATIVE TELEMETRY COLUMNS</span>
			</div>
			<div class="sa-charts">
				<section class="sa-panel sa-panel--wide" aria-label="مقایسه جمعیت استان‌ها">
					<div class="sa-panel__head">
						<h3>ستون مقایسه‌ای جمعیت ۳۱ استان <small>(نزولی، نفر — با کلیک روی هر استان، شناسنامهٔ آن باز می‌شود)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.01 // POPULATION_BARS</span>
					</div>
					<div class="sa-chart" id="chart-pop"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="سهم مساحت پنج استان بزرگ">
					<div class="sa-panel__head">
						<h3>سهم مساحت؛ ۵ استان پهناور و سایر <small>(کیلومتر مربع)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.02 // AREA_DONUT</span>
					</div>
					<div class="sa-chart" id="chart-area-donut"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="شمار شهرستان‌ها به تفکیک استان">
					<div class="sa-panel__head">
						<h3>ستون شمار شهرستان‌ها به تفکیک استان <small>(از ۴۸۳ شهرستان کشور)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.03 // COUNTY_COUNT</span>
					</div>
					<div class="sa-chart" id="chart-counties"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="استان‌های مرزنشین و کشورهای همسایه">
					<div class="sa-panel__head">
						<h3>مرزهای زمینی و کشورهای همسایه <small>(۱۶ استان مرزی)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.04 // BORDER_MATRIX</span>
					</div>
					<div class="sa-chart" id="chart-borders"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="پراکندگی مساحت در برابر جمعیت">
					<div class="sa-panel__head">
						<h3>ماتریس پراکندگی: مساحت × جمعیت <small>(رنگ = تراکم)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.05 // SCATTER_XY</span>
					</div>
					<div class="sa-chart" id="chart-scatter"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="تراکم جمعیت استان‌ها">
					<div class="sa-panel__head">
						<h3>ستون تراکم نسبی جمعیت استان‌ها <small>(نفر بر کیلومتر مربع)</small></h3>
						<span class="sa-panel__tag" dir="ltr">CHART.06 // DENSITY_INDEX</span>
					</div>
					<div class="sa-chart" id="chart-density"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
			</div>
		</section>

		<!-- ============ جدول ماتریسی استان‌ها ============ -->
		<section id="table" class="sa-dash__sec" aria-label="جدول استان‌ها">
			<div class="sa-cyber-sec-head">
				<h2 class="sa-dash__h2">جدول ماتریسی ۳۱ استان ایران</h2>
				<span class="sa-cyber-sec-code" dir="ltr">SYS.TABLE // PROVINCE DATA MATRIX</span>
			</div>
			<div class="sa-table-tools">
				<div class="sa-field sa-field--grow">
					<label class="sa-visually-hidden" for="sa-t-search">جست‌وجو در جدول</label>
					<input type="search" id="sa-t-search" placeholder="جست‌وجو: نام فارسی یا انگلیسی استان، مرکز یا همسایگان…" autocomplete="off">
				</div>
				<label class="sa-check-inline" for="sa-t-border">
					<input type="checkbox" id="sa-t-border">
					فقط مرزنشین
				</label>
				<button type="button" class="sa-btn sa-btn--gold" id="sa-t-csv">خروجی CSV</button>
				<p class="sa-table-tools__hint" id="sa-t-hint">با کلیک روی هر ردیف استان، نقشه و شناسنامهٔ آن استان در بالای صفحه فعال می‌شود.</p>
			</div>
			<div class="sa-table-wrap" id="sa-table-wrap" tabindex="0" role="region" aria-label="جدول دادهٔ استان‌ها">
				<table class="sa-table" id="sa-table">
					<thead>
						<tr>
							<th scope="col"><button type="button" class="sa-sort" data-key="rank">#</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="name">استان (FA / EN)</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="capital">مرکز</button></th>
							<th scope="col"><button type="button" class="sa-sort is-desc" data-key="population">جمعیت</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="area">مساحت (km²)</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="density">تراکم</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="counties">شهرستان‌ها</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="border">مرز بین‌المللی</button></th>
							<th scope="col">عملیات نقشه</th>
							<th scope="col"><button type="button" class="sa-sort" data-key="attractions">جاذبه‌ها</button></th>
						</tr>
					</thead>
					<tbody id="sa-tbody"><tr><td colspan="10"><div class="sa-sk sa-sk--tall"></div></td></tr></tbody>
				</table>
			</div>
			<nav class="sa-pager" id="sa-pager" aria-label="صفحه‌بندی جدول"></nav>
		</section>

		<!-- ============ جاذبه‌ها ============ -->
		<section id="attractions" class="sa-dash__sec" aria-label="جاذبه‌های گردشگری">
			<div class="sa-cyber-sec-head">
				<h2 class="sa-dash__h2">جاذبه‌های شاخص گردشگری</h2>
				<span class="sa-cyber-sec-code" dir="ltr">SYS.SITES // CURATED ATTRACTIONS</span>
			</div>
			<div class="sa-table-tools">
				<div class="sa-field">
					<label for="sa-a-cat">دسته</label>
					<select id="sa-a-cat">
						<option value="all">همهٔ دسته‌ها</option>
						<option value="historical">تاریخی و معماری</option>
						<option value="natural">طبیعی</option>
						<option value="religious">مذهبی و زیارتی</option>
						<option value="cultural">فرهنگی و موزه‌ای</option>
					</select>
				</div>
				<div class="sa-field">
					<label for="sa-a-sort">مرتب‌سازی</label>
					<select id="sa-a-sort">
						<option value="name">نام (الفبا)</option>
						<option value="province">استان</option>
						<option value="county">شهرستان</option>
						<option value="cat">دسته</option>
					</select>
				</div>
				<p class="sa-table-tools__hint">فهرست جاذبه‌های شاخص به تفکیک استان و شهرستان به همراه وضعیت ثبت ملی و جهانی.</p>
			</div>
			<div class="sa-attr-grid" id="sa-attr-grid"><div class="sa-sk sa-sk--tall"></div></div>
		</section>

		<!-- ============ منابع و شفاف‌سازی داده ============ -->
		<section class="sa-dash__sources" aria-label="منابع داده">
			<div class="sa-cyber-sec-head">
				<h2 class="sa-dash__h2">منابع رسمی و مشخصات فنی نقشهٔ سایبری</h2>
				<span class="sa-cyber-sec-code" dir="ltr">SYS.SOURCES // DATA PROVENANCE</span>
			</div>
			<ul>
				<li><strong>جمعیت و خانوار:</strong> مرکز آمار ایران (سرشماری عمومی نفوس و مسکن ۱۳۹۵) و شناسنامه‌های رسمی استانی.</li>
				<li><strong>تقسیمات سیاسی (شهرستان، بخش، شهر، دهستان و آبادی):</strong> فهرست رسمی ۴۸۳ شهرستان کشور و سالنامهٔ آماری استان‌ها.</li>
				<li><strong>مرزهای نقشهٔ ۳۱ استان و ۴۷۸ شهرستان + جزایر:</strong> © مشارکت‌کنندگان OpenStreetMap (پروانهٔ ODbL) از پروژهٔ ایران‌مپ‌کور (پروانهٔ MIT) با تصویر مخروطی هم‌تراز لامبرت (LCC).</li>
			</ul>
		</section>

		<noscript><p class="sa-dash__noscript">برای نمایش نقشهٔ تعاملی دومرحله‌ای، شناسنامهٔ استان‌ها و شهرستان‌ها و نمودارها، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
		<div class="sa-tooltip" id="sa-tooltip" role="tooltip" hidden></div>
	</div>
	<script type="application/json" id="sa-dash-data"><?php echo $sa_dash_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode ?></script>
</main>
<?php
get_footer();
