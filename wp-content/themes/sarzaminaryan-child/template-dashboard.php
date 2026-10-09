<?php
/**
 * Template Name: داشبورد جامع گردشگری ایران
 *
 * داشبورد راست‌به‌چپ و دوزبانه‌ی عددی (فارسی) برای مدیریت محتوا و تحریریه:
 * ۳۱ استان، ۴۸۳ شهرستان، جاذبه‌های شاخص، نقشه، جدول و خروجی CSV.
 * داده‌ها از فایل‌های داخلی `data/` اسمبل می‌شوند (inc/dashboard.php) و همه‌ی
 * نمودارها/جدول/نقشه سمت مرورگر از همان یک بسته‌ی JSON رندر می‌شوند.
 *
 * نصب: صفحه‌ای بسازید و قالب «داشبورد جامع گردشگری ایران» را برایش انتخاب کنید.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sa_dashboard_dataset' ) ) {
	get_header();
	echo '<main id="primary" class="site-main container"><p>ماژول داشبورد فعال نیست.</p></main>';
	get_footer();
	return;
}

$sa_dash      = sa_dashboard_dataset();
$sa_dash_json = wp_json_encode( $sa_dash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );

get_header();
?>
<main id="primary" class="site-main container sa-dash-main">
	<div class="sa-dash" id="sa-dash">

		<!-- ============ سربرگ داشبورد ============ -->
		<header class="sa-dash__top">
			<div class="sa-dash__brand">
				<h1 class="sa-dash__title">داشبورد جامع گردشگری ایران</h1>
				<p class="sa-dash__sub">
					<span class="sa-dash__sub-site">سرزمین آریان</span>
					— ۳۱ استان، ۴۸۳ شهرستان و جاذبه‌های شاخص ملی در یک نگاه
				</p>
				<p class="sa-dash__chips" role="note">
					<span class="sa-chip sa-chip--src">مبنای جمعیت: سرشماری ۱۳۹۵</span>
					<span class="sa-chip sa-chip--src">فهرست شهرستان‌ها: ۱۴۰۵/۰۷/۱۰</span>
					<span class="sa-chip sa-chip--warn">داده‌ها داخلی است؛ به‌روزرسانی خودکار ندارد</span>
				</p>
			</div>
			<div class="sa-dash__actions">
				<button type="button" class="sa-btn" id="sa-theme-toggle" aria-pressed="false">
					<span aria-hidden="true" class="sa-btn__ico" id="sa-theme-ico">🌙</span>
					<span id="sa-theme-label">حالت تیره</span>
				</button>
				<button type="button" class="sa-btn" id="sa-print-btn">
					<span aria-hidden="true" class="sa-btn__ico">🖨</span>
					چاپ گزارش
				</button>
				<button type="button" class="sa-btn" id="sa-share-btn">
					<span aria-hidden="true" class="sa-btn__ico">🔗</span>
					اشتراک‌گذاری
				</button>
			</div>
		</header>

		<nav class="sa-dash__nav" aria-label="بخش‌های داشبورد">
			<a href="#kpis">شاخص‌ها</a>
			<a href="#charts">نمودارها</a>
			<a href="#table">جدول استان‌ها</a>
			<a href="#map">نقشه</a>
			<a href="#attractions">جاذبه‌ها</a>
		</nav>

		<!-- ============ کنترل‌ها / فیلترها ============ -->
		<section class="sa-dash__panel sa-filters" aria-label="فیلترهای داشبورد">
			<h2 class="sa-visually-hidden">فیلترها</h2>
			<div class="sa-filters__grid">
				<div class="sa-field">
					<label for="sa-f-province">استان</label>
					<select id="sa-f-province">
						<option value="all">همهٔ استان‌ها</option>
						<?php foreach ( $sa_dash['provinces'] as $sa_p ) : ?>
							<option value="<?php echo esc_attr( $sa_p['slug'] ); ?>"><?php echo esc_html( $sa_p['name'] ); ?></option>
						<?php endforeach; ?>
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
					<label for="sa-f-area-max">تا <output id="sa-f-area-max-out" for="sa-f-area-max">۱۸۵٬۰۰۰</output> کیلومتر مربع</label>
					<input type="range" id="sa-f-area-max" min="0" max="185000" step="1000" value="185000">
				</div>
				<div class="sa-field sa-field--check">
					<label for="sa-f-border">
						<input type="checkbox" id="sa-f-border">
						فقط استان‌های مرزنشین (مرز زمینی)
					</label>
				</div>
				<div class="sa-field sa-field--btn">
					<button type="button" class="sa-btn sa-btn--ghost" id="sa-f-reset">بازنشانی فیلترها</button>
				</div>
			</div>
			<p class="sa-filters__note">فیلترها روی شاخص‌ها، همهٔ نمودارها، جدول، نقشه و جاذبه‌ها به‌طور هم‌زمان اعمال می‌شوند.</p>
		</section>

		<p class="sa-dash__scope" id="sa-dash-scope" aria-live="polite"></p>

		<!-- ============ شاخص‌های کلیدی ============ -->
		<section id="kpis" class="sa-dash__sec" aria-label="شاخص‌های کلیدی">
			<h2 class="sa-dash__h2">شاخص‌های کلیدی</h2>
			<div class="sa-kpis" id="sa-kpis">
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
				<article class="sa-kpi sa-sk" aria-hidden="true"></article>
			</div>
			<p class="sa-dash__fineprint" id="sa-kpi-note"></p>
		</section>

		<!-- ============ نمودارها ============ -->
		<section id="charts" class="sa-dash__sec" aria-label="نمودارها">
			<h2 class="sa-dash__h2">نمودارها</h2>
			<div class="sa-charts">
				<section class="sa-panel sa-panel--wide" aria-label="مقایسه جمعیت استان‌ها">
					<h3>جمعیت استان‌ها <small>(نزولی، نفر)</small></h3>
					<div class="sa-chart" id="chart-pop"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="سهم مساحت پنج استان بزرگ">
					<h3>مساحت؛ پنج استان بزرگ و سایر <small>(کیلومتر مربع)</small></h3>
					<div class="sa-chart" id="chart-area-donut"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="شمار شهرستان‌ها به تفکیک استان">
					<h3>شمار شهرستان‌ها <small>(از فهرست رسمی ۴۸۳تایی)</small></h3>
					<div class="sa-chart" id="chart-counties"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="استان‌های مرزنشین و کشورهای همسایه">
					<h3>مرزهای زمینی و کشورهای همسایه <small>(۱۳ استان مرزی)</small></h3>
					<div class="sa-chart" id="chart-borders"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="پراکندگی مساحت در برابر جمعیت">
					<h3>پراکندگی: مساحت × جمعیت <small>(رنگ = تراکم)</small></h3>
					<div class="sa-chart" id="chart-scatter"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
				<section class="sa-panel" aria-label="تراکم جمعیت استان‌ها">
					<h3>تراکم جمعیت <small>(نفر بر کیلومتر مربع، نزولی)</small></h3>
					<div class="sa-chart" id="chart-density"><div class="sa-sk sa-sk--tall"></div></div>
				</section>
			</div>
		</section>

		<!-- ============ جدول استان‌ها ============ -->
		<section id="table" class="sa-dash__sec" aria-label="جدول استان‌ها">
			<h2 class="sa-dash__h2">جدول استان‌ها</h2>
			<div class="sa-table-tools">
				<div class="sa-field sa-field--grow">
					<label class="sa-visually-hidden" for="sa-t-search">جست‌وجو در جدول</label>
					<input type="search" id="sa-t-search" placeholder="جست‌وجو: نام استان، مرکز یا همسایگان…" autocomplete="off">
				</div>
				<label class="sa-check-inline" for="sa-t-border">
					<input type="checkbox" id="sa-t-border">
					فقط مرزنشین
				</label>
				<button type="button" class="sa-btn sa-btn--gold" id="sa-t-csv">خروجی CSV</button>
				<p class="sa-table-tools__hint" id="sa-t-hint">خروجی از همهٔ ردیف‌های فیلترشده و مرتب‌شدهٔ فعلی گرفته می‌شود (فرمت CSV واقعی؛ کتابخانهٔ XLSX در قالب نیست).</p>
			</div>
			<div class="sa-table-wrap" id="sa-table-wrap" tabindex="0" role="region" aria-label="جدول دادهٔ استان‌ها — برای پیمایش افقی از کلیدهای جهت استفاده کنید">
				<table class="sa-table" id="sa-table">
					<thead>
						<tr>
							<th scope="col"><button type="button" class="sa-sort" data-key="rank">رتبه</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="name">استان</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="capital">مرکز</button></th>
							<th scope="col"><button type="button" class="sa-sort is-desc" data-key="population">جمعیت</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="area">مساحت (کیلومتر مربع)</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="density">تراکم</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="counties">شهرستان‌ها</button></th>
							<th scope="col"><button type="button" class="sa-sort" data-key="border">مرز بین‌المللی</button></th>
							<th scope="col">کشورهای همسایه</th>
							<th scope="col"><button type="button" class="sa-sort" data-key="attractions">جاذبه‌ها</button></th>
						</tr>
					</thead>
					<tbody id="sa-tbody"><tr><td colspan="10"><div class="sa-sk sa-sk--tall"></div></td></tr></tbody>
				</table>
			</div>
			<nav class="sa-pager" id="sa-pager" aria-label="صفحه‌بندی جدول"></nav>
		</section>

		<!-- ============ نقشه ============ -->
		<section id="map" class="sa-dash__sec" aria-label="نقشه تعاملی استان‌ها">
			<h2 class="sa-dash__h2">نقشه تعاملی استان‌ها</h2>
			<div class="sa-map-tools">
				<div class="sa-field">
					<label for="sa-map-metric">رنگ‌بندی بر اساس</label>
					<select id="sa-map-metric">
						<option value="population" selected>جمعیت</option>
						<option value="density">تراکم جمعیت</option>
						<option value="area">مساحت</option>
						<option value="counties">شمار شهرستان‌ها</option>
					</select>
				</div>
				<p class="sa-map-tools__hint">استان‌های خارج از فیلتر کم‌رنگ می‌شوند. با کلید Tab روی استان‌ها حرکت و با Enter انتخاب کنید؛ جزئیات هر استان در کنار نقشه و در جدول نیز در دسترس است.</p>
			</div>
			<div class="sa-mapgrid">
				<div class="sa-mapbox" id="sa-mapbox"><div class="sa-sk sa-sk--tall"></div></div>
				<aside class="sa-mapinfo" id="sa-mapinfo" aria-live="polite"></aside>
			</div>
		</section>

		<!-- ============ جاذبه‌ها ============ -->
		<section id="attractions" class="sa-dash__sec" aria-label="جاذبه‌های گردشگری">
			<h2 class="sa-dash__h2">جاذبه‌های شاخص گردشگری</h2>
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
				<p class="sa-table-tools__hint">گزینش تحریریه؛ دادهٔ محبوبیت در منبع موجود نبود، بنابراین امتیازی نمایش داده نمی‌شود. تصویر لایسنس‌شده‌ای در مخزن نیست و کارت‌ها از جای‌نگار گرافیکی استفاده می‌کنند.</p>
			</div>
			<div class="sa-attr-grid" id="sa-attr-grid"><div class="sa-sk sa-sk--tall"></div></div>
		</section>

		<!-- ============ منابع و شفاف‌سازی داده ============ -->
		<section class="sa-dash__sources" aria-label="منابع داده">
			<h2 class="sa-dash__h2">منابع و وضعیت داده‌ها</h2>
			<ul>
				<li><strong>جمعیت:</strong> مجموعه‌دادهٔ داخلی (مأخوذ از پروژهٔ Iran Map نسخهٔ ۰٫۸٫۰) — مجموع استان‌ها دقیقاً برابر رقم رسمی سرشماری ۱۳۹۵ است.</li>
				<li><strong>مساحت:</strong> همان مجموعه‌داده؛ مجموع استانی ۱۰٬۱۴۲ کیلومتر مربع کمتر از رقم رسمی کشور (۱٬۶۴۸٬۱۹۵) است که در شاخص‌ها با هر دو رقم اشاره شده.</li>
				<li><strong>شهرستان‌ها:</strong> فهرست رسمی ۴۸۳تایی مخزن (تولید ۱۴۰۵/۰۷/۱۰).</li>
				<li><strong>مرزها و همسایگان:</strong> فهرست تحریریه‌ای استان‌های دارای مرز زمینی؛ منبع رسمی در مخزن نبود و نیازمند بازبینی است.</li>
				<li><strong>جاذبه‌ها:</strong> گزینش تحریریهٔ جاذبه‌های شناخته‌شده؛ بدون امتیاز محبوبیت. نشان‌های جهانی فقط برای ثبت‌های قطعی درج شده‌اند.</li>
				<li><strong>مرزهای نقشه:</strong> © مشارکت‌کنندگان OpenStreetMap (پروانهٔ ODbL) از پروژهٔ ایران‌مپ‌کور (پروانهٔ MIT) — ساده‌شده و مناسب مقیاس‌سنجی دقیق نیست.</li>
			</ul>
		</section>

		<noscript><p class="sa-dash__noscript">برای نمایش نمودارها، جدول و نقشه، جاوااسکریپت را فعال کنید.</p></noscript>
		<div class="sa-tooltip" id="sa-tooltip" role="tooltip" hidden></div>
	</div>
	<script type="application/json" id="sa-dash-data"><?php echo $sa_dash_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode ?></script>
</main>
<?php
get_footer();
