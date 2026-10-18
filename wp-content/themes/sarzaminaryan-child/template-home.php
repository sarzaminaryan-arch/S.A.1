<?php
/**
 * Template Name: صفحه اصلی سرزمین آریان (طرح v2)
 *
 * Static home page built from sarzamin-home-v2.html: animated hero with the Iran map,
 * search, counters, and archive navigation. Content lists remain on their archives.
 * All texts are editable in پیشخوان → نمایش → سفارشی‌سازی → «صفحه اصلی (طرح v2)».
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

/* ---- province dot coordinates on the hero map (x, y from the v2 design) ---- */
/* v2.13.3: فهرست ۳۱ استان به inc/entity-bars.php منتقل شد (مشترک با آرشیو استان‌ها). */
$sa_map_points = sa_iran_provinces();

/* ---- v2.1.0: فقط استان‌هایی که واقعاً منتشر شده‌اند لینک می‌شوند ----
   پیش از این، هر ۳۱ استان لینک می‌شدند و صفحه‌ی اصلی ده‌ها لینک ۴۰۴ داشت.
   v2.13.3: نقشهٔ هیرو از sa_published_entity_ids('province') (inc/entity-bars.php) استفاده می‌کند. */
$sa_pub_provinces = sa_published_entity_ids( 'province' );

/* ---- customizable texts (پیشخوان → سفارشی‌سازی → صفحه اصلی (طرح v2)) ---- */
$sa_slogan    = get_theme_mod( 'sa_home_slogan', 'چو ایران نباشد، تن من مباد' );
$sa_hero_text = get_theme_mod( 'sa_hero_text', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و دیدنی‌ها طبیعت ایران.' );
$sa_search_ph = get_theme_mod( 'sa_search_placeholder', 'استان، شهر یا دیدنی…' );
/* v2.1.0: پیش‌فرض شمارنده‌ها = تعداد واقعی منتشرشده (sa_entity_counts با کش
   یک‌ساعته در inc/performance.php). ادعای «۴۴۸+ شهرستان» روی سایتی که ۸۲
   شهرستان دارد، تناقضی است که هم کاربر و هم ارزیاب کیفیت می‌بیند.
   اگر در سفارشی‌سازی عدد دستی گذاشته باشید، همان اولویت دارد. */
$sa_counts    = function_exists( 'sa_entity_counts' ) ? (array) sa_entity_counts() : array();
$sa_stat1_num = get_theme_mod( 'sa_stat1_num', isset( $sa_counts['province'] ) ? (string) (int) $sa_counts['province'] : '31' );
$sa_stat1_lb  = get_theme_mod( 'sa_stat1_label', 'استان‌ها' );
$sa_stat2_num = get_theme_mod( 'sa_stat2_num', isset( $sa_counts['city'] ) ? (string) (int) $sa_counts['city'] : '0' );
$sa_stat2_suf = get_theme_mod( 'sa_stat2_suffix', '' );
$sa_stat2_lb  = get_theme_mod( 'sa_stat2_label', 'شهرستان‌ها' );
$sa_stat_attr = isset( $sa_counts['attraction'] ) ? (int) $sa_counts['attraction'] : 0;
/* v2.13.5: بخش «پست‌های معروف» به درخواست مالک از صفحهٔ اول حذف شد. */
/* v2.22.12: صفحهٔ اول فقط مسیرهای ناوبری و شمارنده‌های اصلی را نگه می‌دارد؛ فهرست محتوا در آرشیوهاست. */
$sa_edu_nav   = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
$sa_edu_full  = function_exists( 'sa_education_full_label' ) ? sa_education_full_label() : 'آموزش (طبیعت‌گردی و بقا)';
$sa_stat3_lb  = get_theme_mod( 'sa_stat3_label', 'آموزش (طبیعت‌گردی و بقا)' );
?>

<style>
/* فونت Vazirmatn به‌صورت محلی در قالب مادر بارگذاری و preload می‌شود
   (assets/css/fonts.css + wp_head). درخواست به fonts.googleapis.com از داخل
   ایران معمولاً برنمی‌گردد و چون @import بود، رندر را هم بلوک می‌کرد. */
.sa-home{--ink:#102a43;--mut:#49627a;--navy:#0b2a4a;--navy-deep:#071a30;--navy-2:#123f73;--blue:#18558f;--light-blue:#d9edff;direction:rtl;font-family:Vazirmatn,Tahoma,sans-serif;color:var(--ink);background:#f5f9ff;line-height:1.75;overflow:hidden}
.sa-home *{box-sizing:border-box}
.sa-wrap{max-width:1180px;margin:0 auto;padding:0 10px}
.sa-hero{position:relative;overflow:hidden;min-height:min(100vh,800px);display:flex;align-items:center;padding:64px 0 96px;background:radial-gradient(760px 480px at 18% 55%,rgba(36,109,168,.16),transparent 70%),linear-gradient(#fff,#edf6ff)}
.sa-home .sa-hero::after{content:none!important}
.sa-hero::before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(71,85,105,.20) 1.3px,transparent 1.4px);background-size:16px 16px;pointer-events:none}
.sa-sparks{position:absolute;inset:0;pointer-events:none}
.sa-sparks i{position:absolute;width:5px;height:5px;border-radius:50%;background:#9fb0c9;opacity:.25;transform:scale(.8);transition:opacity .7s,transform .7s,box-shadow .7s}
.sa-sparks i.lit{opacity:1;transform:scale(1.5);background:#8eb9e3;box-shadow:0 0 14px 4px rgba(24,85,143,.7)}
.sa-hw{position:relative;width:100%;display:grid;grid-template-columns:1.05fr .95fr;gap:36px;align-items:center}
.sa-hw>*{min-width:0}
.sa-brand{margin-bottom:6px;min-width:0}
.sa-home .sa-hero h1{margin:0;font-size:clamp(28px,5vw,56px);line-height:1.25;font-weight:800;letter-spacing:-.5px;min-width:0;overflow-wrap:anywhere;color:var(--navy);text-shadow:0 2px 0 #fff,0 12px 26px rgba(11,42,74,.2)}
.sa-en{color:var(--navy-2);font-weight:800;letter-spacing:4px;font-size:13px;direction:ltr;text-align:right;margin:2px 0 14px}
.sa-slogan{margin:0 0 10px;font-size:clamp(16px,2.2vw,21px);font-weight:700;color:var(--navy-2)}
.sa-hero .sa-intro{margin:0 0 28px;color:var(--mut);font-size:16px;max-width:520px}
.sa-search{display:flex;align-items:center;flex-wrap:wrap;gap:12px;max-width:100%;height:74px;padding:0 14px 0 10px;border:1px solid #bdd8f1;border-radius:26px;background:linear-gradient(145deg,#f2f8ff,var(--light-blue));box-shadow:0 2px 0 rgba(255,255,255,.95) inset,0 38px 62px -32px rgba(11,42,74,.55),0 12px 24px -14px rgba(11,42,74,.34);transition:box-shadow .3s,border-color .3s}
.sa-search:focus-within{border-color:var(--blue);box-shadow:0 2px 0 #fff inset,0 34px 62px -30px rgba(11,42,74,.62),0 0 0 3px rgba(36,109,168,.22)}
.sa-search svg{flex:none;margin-right:10px}
.sa-search input{flex:1 1 0;width:0;min-width:0;border:0;outline:0;background:transparent;color:var(--navy-deep);font:inherit;font-size:16px;font-weight:600}
.sa-search input::placeholder{color:#58718a;opacity:1}
.sa-search button{flex:none;border:1px solid var(--navy-deep);cursor:pointer;font:inherit;font-weight:700;font-size:16px;color:#fff;height:54px;padding:0 32px;border-radius:18px;background:linear-gradient(145deg,var(--navy-2),var(--navy-deep));box-shadow:0 14px 26px -12px rgba(7,26,48,.78);transition:transform .25s,box-shadow .25s}
.sa-search button:hover{transform:translateY(-2px)}
.sa-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.sa-chips a{padding:6px 16px;border-radius:99px;background:#edf6ff;border:1px solid #bdd8f1;color:var(--navy);font-size:14px;font-weight:600;text-decoration:none;transition:.25s}
.sa-chips a:hover,.sa-chips a:focus-visible{background:var(--navy);border-color:var(--navy);color:#fff;outline:0}
.sa-dash-cta{display:inline-flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:22px;padding:13px 24px;border-radius:14px;background:linear-gradient(135deg,#04101f 0%,#071f36 100%);border:1.5px solid #00ff9d;color:#fff;font-size:15px;font-weight:800;text-decoration:none;box-shadow:0 14px 30px -10px rgba(0,255,157,.38),0 2px 8px rgba(7,26,48,.35);transition:transform .25s ease,box-shadow .25s ease,filter .25s ease}
.sa-dash-cta svg{flex:none;color:#00ff9d}
.sa-dash-cta:hover,.sa-dash-cta:focus-visible{transform:translateY(-2px);box-shadow:0 20px 38px -12px rgba(0,255,157,.55),0 3px 8px rgba(7,26,48,.4);filter:brightness(1.08);color:#fff;outline:0}
.sa-dash-cta small{font-family:Consolas,"JetBrains Mono",monospace;font-size:11.5px;font-weight:800;color:#00ff9d;letter-spacing:.05em}
@media(prefers-reduced-motion:reduce){.sa-dash-cta{transition:none}.sa-dash-cta:hover,.sa-dash-cta:focus-visible{transform:none}}
.sa-map{position:relative}
.sa-map:before{content:"";position:absolute;inset:8% 4%;border-radius:50%;background:radial-gradient(circle,rgba(36,109,168,.24),transparent 68%)}
.sa-map svg{position:relative;display:block;width:100%;height:auto;filter:drop-shadow(0 44px 40px rgba(7,26,48,.34))}
.sa-map .h{fill:transparent}
.sa-map .d{fill:#bdd8f1;filter:drop-shadow(0 0 5px #18558f);animation:sab var(--t) ease-in-out var(--d) infinite;transition:r .2s}
.sa-map a:hover .d{r:5.2}
.sa-map .sa-dot--soon{opacity:.35;pointer-events:none}
@keyframes sab{0%,100%{opacity:.15}50%{opacity:1}}
.sa-scroll{position:absolute;bottom:24px;left:50%;margin-left:-13px;width:26px;height:42px;border:2px solid rgba(11,20,36,.28);border-radius:14px}
.sa-scroll i{position:absolute;top:8px;left:50%;width:4px;height:8px;margin-left:-2px;border-radius:2px;background:var(--blue);animation:saw 1.8s infinite}
@keyframes saw{0%{opacity:0;transform:translateY(0)}30%{opacity:1}100%{opacity:0;transform:translateY(14px)}}
.sa-off *{animation-play-state:paused!important}
.sa-stats .sa-wrap{position:relative;max-width:760px;margin:0 auto;display:grid;grid-template-columns:repeat(2,1fr);gap:22px}
.sa-st{text-align:center;padding:12px 10px;background:linear-gradient(155deg,#fff,#edf6ff);border:1px solid #bdd8f1;border-radius:28px;box-shadow:0 2px 0 #fff inset,0 40px 64px -28px color-mix(in srgb,var(--c) 72%,transparent),0 10px 22px -16px rgba(11,42,74,.35)}
.sa-st b{display:block;font-size:clamp(36px,5.2vw,52px);line-height:1.3;font-weight:700;color:var(--c)}
.sa-st span{color:var(--mut);font-weight:500}
.js .rv{opacity:0;transform:translateY(26px)}
.js .rv.in{opacity:1;transform:none}
.js .rv.in:hover{transform:translateY(-5px)}
@media(max-width:900px){.sa-hw{grid-template-columns:1fr}.sa-map{max-width:380px;margin:10px auto 0}.sa-scroll{display:none}.sa-hero{padding:48px 0 80px}}
@media(max-width:640px){.sa-wrap{padding-inline:7px}.sa-search{height:64px;border-radius:22px}.sa-search button{height:46px;padding:0 20px}.sa-stats .sa-wrap{gap:14px}}
@media(max-width:480px){.sa-hero{padding:32px 0 60px;min-height:auto}.sa-en{letter-spacing:2px;font-size:12px}.sa-hero .sa-intro{font-size:15.5px;margin-bottom:22px}.sa-chips a{padding:7px 14px;font-size:13px}.sa-stats .sa-wrap{gap:12px}.sa-stats .sa-wrap--3{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-st{padding:10px 8px;border-radius:20px}.sa-dash-cta{width:100%;justify-content:center;flex-wrap:wrap;font-size:15px;padding:13px 18px}.sa-dash-cta small{flex-basis:100%;text-align:center}}
/* zir-e 430px, dokme be radif-e dovom miravad */
@media(max-width:430px){.sa-search{height:auto;padding:8px;border-radius:22px;row-gap:8px}.sa-search input{order:1;flex:1 1 auto;font-size:16px}.sa-search svg{order:2;margin:0}.sa-search button{order:3;flex:1 0 100%;height:48px;padding:0 16px;border-radius:14px}}
@media(prefers-reduced-motion:reduce){.sa-sparks i{transition:none}.sa-map .d{animation:none}.sa-scroll i{animation:none}.js .rv{opacity:1;transform:none;transition:none}}
/* v2.13.1 — آموزش‌ها کنار استان/شهرستان/دیدنی */
.sa-chips a.sa-chip--edu{background:#e8f6ee;border-color:#9bd1b3;color:#0f5132}
.sa-chips a.sa-chip--edu:hover,.sa-chips a.sa-chip--edu:focus-visible{background:#0f5132;border-color:#0f5132;color:#fff}
.sa-stats .sa-wrap--3{max-width:980px;grid-template-columns:repeat(3,1fr)}
.sa-stats .sa-wrap--4{max-width:1180px;grid-template-columns:repeat(4,1fr)}
.sa-stats a.sa-st{color:inherit;text-decoration:none;transition:transform .3s}.sa-stats a.sa-st:hover,.sa-stats a.sa-st:focus-visible{transform:translateY(-3px)}
@media(max-width:900px){.sa-stats .sa-wrap--4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.sa-stats .sa-wrap--3,.sa-stats .sa-wrap--4{gap:10px}.sa-wrap--3 .sa-st,.sa-wrap--4 .sa-st{padding:10px 6px;border-radius:20px}.sa-wrap--3 .sa-st b,.sa-wrap--4 .sa-st b{font-size:clamp(26px,8vw,42px)}.sa-wrap--3 .sa-st span,.sa-wrap--4 .sa-st span{font-size:13px}}
</style>

<div class="sa-home" id="sa-home">
	<section class="sa-hero">
		<div class="sa-sparks" aria-hidden="true"><?php
			for ( $sa_i = 0; $sa_i < 42; $sa_i++ ) {
				echo '<i style="left:' . esc_attr( number_format( wp_rand( 2, 97 ), 1 ) ) . '%;top:' . esc_attr( number_format( wp_rand( 4, 92 ), 1 ) ) . '%"></i>';
			}
		?></div>
		<div class="sa-wrap sa-hw">
			<div>
				<div class="sa-brand">
					<h1><?php bloginfo( 'name' ); ?></h1>
				</div>
				<div class="sa-en">SARZAMIN ARYAN</div>
				<p class="sa-slogan"><?php echo esc_html( $sa_slogan ); ?></p>
				<p class="sa-intro"><?php echo esc_html( $sa_hero_text ); ?></p>
				<form class="sa-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#123f73" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
					<input type="search" name="s" placeholder="<?php echo esc_attr( $sa_search_ph ); ?>" aria-label="جستجو">
					<button type="submit">جستجو</button>
				</form>
				<div class="sa-chips">
					<?php foreach ( sa_nav_entity_types() as $sa_type ) : ?>
						<a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></a>
					<?php endforeach; ?>
					<?php if ( $sa_edu_nav ) : ?>
						<a class="sa-chip--edu" href="<?php echo esc_url( $sa_edu_nav['url'] ); ?>"><?php echo esc_html( $sa_edu_full ); ?></a>
					<?php endif; ?>
				</div>
				<?php $sa_dash_url = function_exists( 'sa_dashboard_page_url' ) ? sa_dashboard_page_url() : ''; ?>
				<?php if ( $sa_dash_url ) : ?>
					<a class="sa-dash-cta" href="<?php echo esc_url( $sa_dash_url ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg>
						<span>داشبورد سایبری و نقشهٔ تعاملی شناسنامهٔ ایران</span>
						<small dir="ltr">CYBER MAP // 31 PROVINCES · 483 COUNTIES</small>
					</a>
				<?php endif; ?>
			</div>
			<div class="sa-map">
				<svg viewBox="-10 -10 420 380" role="img" aria-label="نقشه ایران با نقطه‌ی هر استان">
					<defs>
						<path id="sam" d="M10.6 14.2 26 8.3 40 24.8 54 27.1 60 20 78 9.4 90 14.2 96 28.3 108 37.8 118 61.4 136 70.8 160 76.7 182 77.9 208 73.2 210 62.5 224 59 240 46 258 41.3 274 49.6 294 54.3 316 59 336 80.2 354 82.6 342 101.5 348 127.4 340 151 348 177 364 203 366 217 348 238 358 252.5 380 278 396 302 384 321 362 349 340 344.6 310 344.6 286 337.5 270 309 256 301 240 306 220 316 200 313.7 180 297 160 285.5 146 262 134 236 116 231 94 226.6 86 214 84 188.8 64 170 52 163 44 144 40 120.4 38 99 28 85 16 66 16 42.5 14 30.7Z"/>
						<pattern id="sad" width="8" height="8" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="#5d7fc4" opacity=".4"/></pattern>
						<linearGradient id="sag" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1b2c52"/><stop offset="1" stop-color="#071a30"/></linearGradient>
					</defs>
					<use href="#sam" fill="url(#sag)" stroke="#2f4a86" stroke-width="2" stroke-linejoin="round"/>
					<use href="#sam" fill="url(#sad)"/>
					<g id="sa-dots">
						<?php
						foreach ( $sa_map_points as $sa_p ) :
							$sa_url  = isset( $sa_pub_provinces[ $sa_p[1] ] ) ? (string) get_permalink( $sa_pub_provinces[ $sa_p[1] ] ) : '';
							$sa_anim = 'style="--t:' . esc_attr( number_format( 2 + wp_rand( 0, 40 ) / 10, 1 ) ) . 's;--d:-' . esc_attr( number_format( wp_rand( 0, 60 ) / 10, 1 ) ) . 's"';
							?>
							<?php if ( $sa_url ) : ?>
								<a href="<?php echo esc_url( $sa_url ); ?>" aria-label="<?php echo esc_attr( $sa_p[0] ); ?>" transform="translate(<?php echo esc_attr( $sa_p[2] . ' ' . $sa_p[3] ); ?>)"><title><?php echo esc_html( $sa_p[0] ); ?></title><circle class="h" r="10"/><circle class="d" r="3.2" <?php echo $sa_anim; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/></a>
							<?php else : ?>
								<g class="sa-dot--soon" aria-hidden="true" transform="translate(<?php echo esc_attr( $sa_p[2] . ' ' . $sa_p[3] ); ?>)"><title><?php echo esc_html( $sa_p[0] . ' — به‌زودی' ); ?></title><circle class="h" r="10"/><circle class="d" r="3.2" <?php echo $sa_anim; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/></g>
							<?php endif; ?>
						<?php endforeach; ?>
					</g>
				</svg>
			</div>
		</div>
		<a class="sa-scroll" href="#sa-stats" aria-label="ادامه"><i></i></a>
	</section>

	<?php
	/* ---- بلوک مستقل رتبه‌بندی ۷ شهر (بدون لوگوی داخلی؛ v2.22.12) ---- */
	if ( class_exists( 'CC_UI' ) ) {
		CC_UI::top_cities_block( 7 );
	}
	?>

	<?php
	$sa_nav_types   = sa_nav_entity_types();
	$sa_has_prov    = in_array( 'province', $sa_nav_types, true );
	$sa_has_city    = in_array( 'city', $sa_nav_types, true );
	$sa_has_attr    = in_array( 'attraction', $sa_nav_types, true ) && $sa_stat_attr > 0;
	$sa_stats_cols  = 2 + ( $sa_has_attr ? 1 : 0 ) + ( $sa_edu_nav ? 1 : 0 );
	$sa_stats_class = 4 === $sa_stats_cols ? ' sa-wrap--4' : ( 3 === $sa_stats_cols ? ' sa-wrap--3' : '' );
	?>
	<section class="sa-stats" id="sa-stats" aria-label="چهار بخش اصلی سرزمین آریان">
		<div class="sa-wrap<?php echo esc_attr( $sa_stats_class ); ?>">
				<?php if ( $sa_has_prov ) : ?>
					<a class="counter-btn sa-st" style="--c:#123f73" href="<?php echo esc_url( sa_archive_url( 'province' ) ); ?>">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_stat1_num ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat1_num ) ); ?></b></span>
						<span class="counter-label"><?php echo esc_html( $sa_stat1_lb ); ?></span>
					</a>
				<?php else : ?>
					<div class="counter-btn sa-st" style="--c:#123f73">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_stat1_num ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat1_num ) ); ?></b></span>
						<span class="counter-label"><?php echo esc_html( $sa_stat1_lb ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $sa_has_city ) : ?>
					<a class="counter-btn sa-st" style="--c:#246da8" href="<?php echo esc_url( sa_archive_url( 'city' ) ); ?>">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_stat2_num ); ?>" data-s="<?php echo esc_attr( $sa_stat2_suf ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat2_num ) . $sa_stat2_suf ); ?></b></span>
						<span class="counter-label"><?php echo esc_html( $sa_stat2_lb ); ?></span>
					</a>
				<?php else : ?>
					<div class="counter-btn sa-st" style="--c:#246da8">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_stat2_num ); ?>" data-s="<?php echo esc_attr( $sa_stat2_suf ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat2_num ) . $sa_stat2_suf ); ?></b></span>
						<span class="counter-label"><?php echo esc_html( $sa_stat2_lb ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $sa_has_attr ) : ?>
					<a class="counter-btn sa-st" style="--c:#0b7285" href="<?php echo esc_url( sa_archive_url( 'attraction' ) ); ?>">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_stat_attr ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat_attr ) ); ?></b></span>
						<span class="counter-label">دیدنی‌ها</span>
					</a>
				<?php endif; ?>
				<?php if ( $sa_edu_nav ) : ?>
					<a class="counter-btn sa-st" style="--c:#177357" href="<?php echo esc_url( $sa_edu_nav['url'] ); ?>">
						<span class="counter-circle"><b data-n="<?php echo esc_attr( (int) $sa_edu_nav['count'] ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_edu_nav['count'] ) ); ?></b></span>
						<span class="counter-label"><?php echo esc_html( $sa_stat3_lb ); ?></span>
					</a>
				<?php endif; ?>
		</div>
	</section>

	<?php /* v2.13.3: بخش «استان‌های ایران» (نوارهای سفید) به درخواست مالک از صفحهٔ اول حذف و به آرشیو استان‌ها (/province/) منتقل شد. */ ?>

	<?php /* v2.22.12: فهرست‌های دیدنی‌ها، آموزش‌ها و مقالات از صفحهٔ اول حذف شدند؛ دسترسی آن‌ها از آرشیوها و ناوبری باقی است. */ ?>

</div>

<script>
(function(){
var R=document.getElementById('sa-home');if(!R)return;R.classList.add('js');
var rnd=Math.random;

/* هفت نقطه‌ی تصادفی روشن، هر سه ثانیه */
var SP=R.querySelectorAll('.sa-sparks i');
function lit7(){Array.prototype.forEach.call(SP,function(d){d.classList.remove('lit')});
for(var k=0;k<7&&SP.length;k++){SP[Math.floor(rnd()*SP.length)].classList.add('lit')}}
if(SP.length){lit7();if(!matchMedia('(prefers-reduced-motion:reduce)').matches)setInterval(lit7,3000)}


/* نمایش با اسکرول و شمارنده */
var fa=function(n){return n.toLocaleString('fa-IR')};
function count(b){var n=+b.dataset.n,s=b.dataset.s||'',t0=null;(function f(t){t0=t0||t;var p=Math.min((t-t0)/1300,1);b.textContent=fa(Math.round(n*(1-Math.pow(1-p,3))))+s;if(p<1)requestAnimationFrame(f)})(performance.now())}
var calm=matchMedia('(prefers-reduced-motion:reduce)').matches,io=('IntersectionObserver' in window&&!calm)?new IntersectionObserver(function(es){var k=0;
es.forEach(function(x){if(!x.isIntersecting)return;var e=x.target;e.style.transitionDelay=(k++%4)*80+'ms';e.classList.add('in');io.unobserve(e);
setTimeout(function(){e.style.transitionDelay=''},1100);var b=e.querySelector('b[data-n]');if(b)count(b)})},{threshold:.12}):null;
function rv(list){Array.prototype.forEach.call(list,function(e){e.classList.add('rv');if(io)io.observe(e);else e.classList.add('in')})}
rv(R.querySelectorAll('.sa-st')); /* counters only: removed homepage content lists stay on their archives */
if('IntersectionObserver' in window){new IntersectionObserver(function(es){R.classList.toggle('sa-off',!es[0].isIntersecting)}).observe(R.querySelector('.sa-hero'))}
})();
</script>

<?php
get_footer();
