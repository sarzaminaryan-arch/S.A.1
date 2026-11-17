<?php
/**
 * Template Name: صفحه اصلی سرزمین آریان (طرح v3 — مینیمال)
 *
 * بازطراحی ۱۷ نوامبر ۲۰۲۶ به درخواست مالک: صفحهٔ اول «استاندارد، مینیمال و
 * خوش‌فرم» مثل وب‌سایت‌های معاصر — فضای خالی زیاد، خطوط نازک، یک لهجهٔ رنگی و
 * تایپوگرافی فارسی درست. ساختار استاندارد وردپرس حفظ شده است: هدر، مین، فوتر،
 * نوار جستجو، شمارنده‌ها، نقشه و آرشیوها.
 *
 * همهٔ متن‌ها از پیشخوان → نمایش → سفارشی‌سازی → «صفحه اصلی (طرح v2)» هم‌چنان
 * قابل ویرایش‌اند (کلیدهای theme_mod تغییر نکرده‌اند تا تنظیم‌های موجود نماند).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

/* ---- مختصات نقطه‌های استان روی نقشه (از طرح v2) ---- */
$sa_map_points = sa_iran_provinces();

/* ---- فقط استان‌هایی که منتشر شده‌اند لینک می‌شوند ---- */
$sa_pub_provinces = sa_published_entity_ids( 'province' );

/* ---- متن‌های قابل ویرایش ---- */
$sa_slogan    = get_theme_mod( 'sa_home_slogan', 'چو ایران نباشد، تن من مباد' );
$sa_hero_text = get_theme_mod( 'sa_hero_text', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و دیدنی‌ها طبیعت ایران.' );
$sa_search_ph = get_theme_mod( 'sa_search_placeholder', 'استان، شهر یا دیدنی…' );
/* ۲.۲۲.۱۰۶: شمارنده‌ها مستقیم از sa_entity_types() خوانده می‌شوند، نه از transient.
   کار ۱۳۴ نشان داد سایتی که تازه از قطعی دیتابیس برگشته می‌تواند کش شمارنده را با
   صفر پر کند و یک ساعت «۰ استان» نشان دهد؛ این خواندن سبک است (یک کوئری برای هر
   نوع محتوا) و همیشه مقدار واقعی را می‌دهد. اگر واقعاً صفر بود، خط تیره چاپ می‌شود. */
$sa_counts = array();
if ( function_exists( 'sa_entity_types' ) ) {
	foreach ( sa_entity_types() as $sa_count_type ) {
		$sa_count_obj                   = wp_count_posts( $sa_count_type );
		$sa_counts[ $sa_count_type ]    = isset( $sa_count_obj->publish ) ? (int) $sa_count_obj->publish : 0;
	}
}
$sa_stat1_num = get_theme_mod( 'sa_stat1_num', isset( $sa_counts['province'] ) ? (string) (int) $sa_counts['province'] : '31' );
$sa_stat1_lb  = get_theme_mod( 'sa_stat1_label', 'استان‌ها' );
$sa_stat2_num = get_theme_mod( 'sa_stat2_num', isset( $sa_counts['city'] ) ? (string) (int) $sa_counts['city'] : '0' );
$sa_stat2_suf = get_theme_mod( 'sa_stat2_suffix', '' );
$sa_stat2_lb  = get_theme_mod( 'sa_stat2_label', 'شهرستان‌ها' );
$sa_stat_attr = isset( $sa_counts['attraction'] ) ? (int) $sa_counts['attraction'] : 0;
$sa_edu_nav   = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
$sa_edu_full  = function_exists( 'sa_education_full_label' ) ? sa_education_full_label() : 'آموزش (طبیعت‌گردی و بقا)';
$sa_stat3_lb  = get_theme_mod( 'sa_stat3_label', 'آموزش (طبیعت‌گردی و بقا)' );
?>

<style>
/* ---------------------------------------------------------------------------
   طرح v3 — مینیمال فارسی
   یک لهجهٔ رنگی (سبزآبی آرام)، خطوط ۱ پیکسلی کم‌رنگ، فضای خالی سخاوتمند،
   بدون سایهٔ سنگین و بدون درخشش نئونی. اعداد و متن‌ها راست‌چین با Vazirmatn.
   ------------------------------------------------------------------------- */
.sa-home{
	--ink:#101828;
	--ink-2:#344054;
	--mut:#667085;
	--line:#e4e7ec;
	--paper:#ffffff;
	--soft:#f8fafc;
	--accent:#0f766e;
	--accent-soft:#effcf9;
	direction:rtl;
	font-family:Vazirmatn,'Vazirmatn Fallback',Tahoma,sans-serif;
	color:var(--ink);
	background:var(--paper);
	line-height:1.85;
	-webkit-font-smoothing:antialiased;
}
.sa-home *{box-sizing:border-box}
.sa-home .sa-wrap{max-width:1120px;margin:0 auto;padding-inline:20px}
@media(max-width:640px){.sa-wrap{padding-inline:16px}}

/* --- هیرو ------------------------------------------------------------------ */
.sa-hero{padding:clamp(48px,8vw,104px) 0 clamp(40px,6vw,72px);border-bottom:1px solid var(--line)}
.sa-hw{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.85fr);gap:clamp(28px,5vw,64px);align-items:center}
.sa-hw>*{min-width:0}
.sa-en{display:block;margin:0 0 14px;font-size:.72rem;font-weight:700;letter-spacing:.32em;color:var(--accent);direction:ltr;text-align:right}
.sa-home .sa-hero h1{margin:0 0 14px;font-size:clamp(2rem,4.6vw,3.4rem);line-height:1.22;font-weight:800;letter-spacing:-.02em;color:var(--ink);overflow-wrap:anywhere}
.sa-slogan{margin:0 0 14px;font-size:clamp(1rem,1.9vw,1.22rem);font-weight:600;color:var(--ink-2)}
.sa-hero .sa-intro{margin:0 0 30px;font-size:1rem;line-height:2.05;color:var(--mut);max-width:46ch}

/* جستجو: یک قاعدهٔ نازک، بدون جعبهٔ پرزرق‌وبرق */
.sa-search{display:flex;align-items:center;gap:10px;max-width:520px;padding:5px 6px 5px 6px;border:1px solid var(--line);border-radius:14px;background:var(--paper);transition:border-color .2s ease,box-shadow .2s ease}
.sa-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.sa-search svg{flex:none;margin-inline-start:6px;color:var(--mut)}
.sa-search input{flex:1 1 0;width:0;min-width:0;border:0;outline:0;background:transparent;color:var(--ink);font:inherit;font-size:.98rem}
.sa-search input::placeholder{color:#98a2b3;opacity:1}
.sa-search button{flex:none;border:0;cursor:pointer;font:inherit;font-size:.92rem;font-weight:700;color:#fff;padding:11px 22px;border-radius:10px;background:var(--accent);transition:filter .2s ease,transform .2s ease}
.sa-search button:hover{filter:brightness(1.08)}
.sa-search button:active{transform:translateY(1px)}

/* ریزپیوندها */
.sa-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:26px;padding:0;list-style:none}
.sa-chips a{display:inline-block;padding:7px 14px;border:1px solid var(--line);border-radius:99px;color:var(--ink-2);font-size:.86rem;font-weight:600;text-decoration:none;transition:border-color .18s ease,color .18s ease,background .18s ease}
.sa-chips a:hover,.sa-chips a:focus-visible{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);outline:0}
.sa-chips a.sa-chip--edu{border-color:#cfe8e2;color:#0f6b5f}

.sa-quiet{display:flex;flex-wrap:wrap;align-items:center;gap:8px 18px;margin-top:26px;font-size:.86rem;color:var(--mut)}
.sa-quiet a{color:var(--ink-2);text-decoration:none;border-bottom:1px solid var(--line);padding-bottom:1px;transition:color .18s,border-color .18s}
.sa-quiet a:hover,.sa-quiet a:focus-visible{color:var(--accent);border-color:var(--accent);outline:0}

/* نقشه: تک‌رنگ و آرام */
.sa-map{position:relative}
.sa-map svg{display:block;width:100%;height:auto}
.sa-map .h{fill:transparent}
.sa-map .d{fill:#0f766e;opacity:.16;transition:opacity .35s ease,r .2s ease}
.sa-map a:hover .d,.sa-map a:focus-visible .d{opacity:1}
.sa-map .sa-dot--soon{opacity:.08;pointer-events:none}
.sa-map__hint{margin:6px 0 0;font-size:.74rem;color:#98a2b3;text-align:center}

/* --- شمارنده‌ها ------------------------------------------------------------ */
.sa-stats{padding:clamp(34px,5vw,56px) 0;border-bottom:1px solid var(--line)}
.sa-stats .sa-wrap{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0}
.sa-stat{display:block;padding:6px clamp(10px,2vw,26px);text-align:center;text-decoration:none;color:inherit;border-inline-end:1px solid var(--line);transition:color .2s ease}
.sa-stat:last-child{border-inline-end:0}
.sa-stat b{display:block;font-size:clamp(1.7rem,3.6vw,2.5rem);line-height:1.3;font-weight:800;color:var(--ink);font-variant-numeric:tabular-nums;transition:color .2s ease}
.sa-stat span{display:block;margin-top:2px;font-size:.82rem;color:var(--mut);line-height:1.9}
a.sa-stat:hover b,a.sa-stat:focus-visible b{color:var(--accent)}
a.sa-stat:focus-visible{outline:2px solid var(--accent);outline-offset:6px;border-radius:6px}

@media(max-width:900px){
	.sa-hw{grid-template-columns:1fr}
	.sa-map{order:-1;max-width:380px;margin:0 auto}
	.sa-stats .sa-wrap{grid-template-columns:repeat(2,minmax(0,1fr));row-gap:22px}
	.sa-stat:nth-child(2n){border-inline-end:0}
	.sa-stat:nth-child(-n+2){padding-bottom:18px;border-bottom:1px solid var(--line)}
}
@media(max-width:430px){
	.sa-search{flex-wrap:wrap;padding:8px}
	.sa-search input{order:1;flex:1 1 auto}
	.sa-search button{order:3;flex:1 0 100%;padding:12px 16px}
}
@media(prefers-reduced-motion:reduce){
	.sa-home *{transition:none!important;animation:none!important}
}
</style>

<main class="sa-home" id="primary">
	<section class="sa-hero">
		<div class="sa-wrap sa-hw">
			<div>
				<span class="sa-en">SARZAMIN ARYAN</span>
				<h1><?php bloginfo( 'name' ); ?></h1>
				<p class="sa-slogan"><?php echo esc_html( $sa_slogan ); ?></p>
				<p class="sa-intro"><?php echo esc_html( $sa_hero_text ); ?></p>

				<form class="sa-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
					<input type="search" name="s" placeholder="<?php echo esc_attr( $sa_search_ph ); ?>" aria-label="جستجو">
					<button type="submit">جستجو</button>
				</form>

				<nav class="sa-chips" aria-label="بخش‌های اصلی">
					<?php foreach ( sa_nav_entity_types() as $sa_type ) : ?>
						<a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></a>
					<?php endforeach; ?>
					<?php if ( $sa_edu_nav ) : ?>
						<a class="sa-chip--edu" href="<?php echo esc_url( $sa_edu_nav['url'] ); ?>"><?php echo esc_html( $sa_edu_full ); ?></a>
					<?php endif; ?>
				</nav>

				<?php $sa_dashboard_url = function_exists( 'sa_dashboard_page_url' ) ? sa_dashboard_page_url() : ''; ?>
				<?php if ( $sa_dashboard_url ) : ?>
					<p class="sa-quiet">
						<a href="<?php echo esc_url( $sa_dashboard_url ); ?>">داشبورد و نقشهٔ تعاملی شناسنامهٔ ایران</a>
					</p>
				<?php endif; ?>
			</div>

			<div class="sa-map">
				<svg viewBox="-10 -10 420 380" role="img" aria-label="نقشهٔ ایران با نقطهٔ هر استان">
					<path d="M10.6 14.2 26 8.3 40 24.8 54 27.1 60 20 78 9.4 90 14.2 96 28.3 108 37.8 118 61.4 136 70.8 160 76.7 182 77.9 208 73.2 210 62.5 224 59 240 46 258 41.3 274 49.6 294 54.3 316 59 336 80.2 354 82.6 342 101.5 348 127.4 340 151 348 177 364 203 366 217 348 238 358 252.5 380 278 396 302 384 321 362 349 340 344.6 310 344.6 286 337.5 270 309 256 301 240 306 220 316 200 313.7 180 297 160 285.5 146 262 134 236 116 231 94 226.6 86 214 84 188.8 64 170 52 163 44 144 40 120.4 38 99 28 85 16 66 16 42.5 14 30.7Z" fill="#f2f5f9" stroke="#d0d5dd" stroke-width="2" stroke-linejoin="round"/>
					<g id="sa-dots">
						<?php
						foreach ( $sa_map_points as $sa_p ) :
							$sa_url = isset( $sa_pub_provinces[ $sa_p[1] ] ) ? (string) get_permalink( $sa_pub_provinces[ $sa_p[1] ] ) : '';
							?>
							<?php if ( $sa_url ) : ?>
								<a href="<?php echo esc_url( $sa_url ); ?>" aria-label="<?php echo esc_attr( $sa_p[0] ); ?>" transform="translate(<?php echo esc_attr( $sa_p[2] . ' ' . $sa_p[3] ); ?>)"><title><?php echo esc_html( $sa_p[0] ); ?></title><circle class="h" r="10"/><circle class="d" r="3.6"/></a>
							<?php else : ?>
								<g class="sa-dot--soon" aria-hidden="true" transform="translate(<?php echo esc_attr( $sa_p[2] . ' ' . $sa_p[3] ); ?>)"><title><?php echo esc_html( $sa_p[0] ); ?></title><circle class="h" r="10"/><circle class="d" r="2.6"/></g>
							<?php endif; ?>
						<?php endforeach; ?>
					</g>
				</svg>
				<p class="sa-map__hint">هر نقطه یک استان؛ استان‌های منتشرشده پررنگ‌اند.</p>
			</div>
		</div>
	</section>

	<?php
	$sa_nav_types = sa_nav_entity_types();
	$sa_has_prov  = in_array( 'province', $sa_nav_types, true );
	$sa_has_city  = in_array( 'city', $sa_nav_types, true );
	$sa_has_attr  = in_array( 'attraction', $sa_nav_types, true ) && $sa_stat_attr > 0;
	?>
	<section class="sa-stats" id="sa-stats" aria-label="شمار محتوای سرزمین آریان">
		<div class="sa-wrap">
			<?php
			/**
			 * یک شمارنده را چاپ می‌کند: عدد، برچسب، و اگر نشانی داشت پیوند.
			 *
			 * @param string $num   عدد.
			 * @param string $suf   پسوند عدد.
			 * @param string $label برچسب.
			 * @param string $url   نشانی (اختیاری).
			 */
			$sa_print_stat = function ( $num, $suf, $label, $url = '' ) {
				// اگر شمار واقعی صفر باشد، «۰» نمی‌نویسیم؛ خط تیره صادقانه‌تر است و
				// بازدیدکننده فکر نمی‌کند سایت خالی است.
				$shown = (int) $num > 0 ? number_format_i18n( (int) $num ) . $suf : '—';
				$inner = '<b data-n="' . esc_attr( (int) $num ) . '">' . esc_html( $shown ) . '</b>'
					. '<span>' . esc_html( $label ) . '</span>';
				if ( '' !== $url ) {
					echo '<a class="sa-stat" href="' . esc_url( $url ) . '">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<div class="sa-stat">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			};
			$sa_print_stat( $sa_stat1_num, '', $sa_stat1_lb, $sa_has_prov ? sa_archive_url( 'province' ) : '' );
			$sa_print_stat( $sa_stat2_num, $sa_stat2_suf, $sa_stat2_lb, $sa_has_city ? sa_archive_url( 'city' ) : '' );
			if ( $sa_has_attr ) {
				$sa_print_stat( $sa_stat_attr, '', 'دیدنی‌ها', sa_archive_url( 'attraction' ) );
			}
			if ( $sa_edu_nav ) {
				$sa_print_stat( (string) $sa_edu_nav['count'], '', $sa_stat3_lb, (string) $sa_edu_nav['url'] );
			}
			?>
		</div>
	</section>

	<div class="sa-wrap">
		<?php
		/* ---- هفت شهرستان برتر (آکاردئون، همچنان زیر شمارنده‌ها) ---- */
		if ( class_exists( 'CC_UI' ) ) {
			CC_UI::top_cities_block( 7 );
		}
		?>
	</div>
</main>

<?php
/* ۲.۲۲.۱۰۷: انیمیشن شمارنده حذف شد. اعداد از سرور می‌آیند (۳۱ / ۴۸۵+ / ۳۸ / ۶۷) و
   هر انیمیشنی که از صفر شروع شود، در لحظهٔ اول «۰» نشان می‌دهد — همان چیزی که در
   پیش‌نمایش و اسکرین‌شات دیده شد. عدد درست، بهتر از جلوهٔ شمارش است. */ ?>


<?php
get_footer();
?>
