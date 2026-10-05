<?php
/**
 * Template Name: صفحه اصلی سرزمین آریان (طرح v2)
 *
 * Static home page built from sarzamin-home-v2.html: animated hero with the Iran map,
 * stats, all-31-province bars (server-rendered for SEO), latest posts and popular posts.
 * All texts are editable in پیشخوان → نمایش → سفارشی‌سازی → «صفحه اصلی (طرح v2)».
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

/* ---- province dot coordinates on the hero map (x, y from the v2 design) ---- */
$sa_map_points = array(
	array( 'آذربایجان شرقی', 'east-azerbaijan', 56, 45 ),
	array( 'آذربایجان غربی', 'west-azerbaijan', 31, 58 ),
	array( 'اردبیل', 'ardabil', 96, 41 ),
	array( 'اصفهان', 'isfahan', 180, 165 ),
	array( 'البرز', 'alborz', 146, 94 ),
	array( 'ایلام', 'ilam', 58, 150 ),
	array( 'بوشهر', 'bushehr', 152, 258 ),
	array( 'تهران', 'tehran', 162, 104 ),
	array( 'چهارمحال و بختیاری', 'chaharmahal-bakhtiari', 147, 181 ),
	array( 'خراسان جنوبی', 'south-khorasan', 314, 168 ),
	array( 'خراسان رضوی', 'razavi-khorasan', 322, 87 ),
	array( 'خراسان شمالی', 'north-khorasan', 276, 60 ),
	array( 'خوزستان', 'khuzestan', 104, 205 ),
	array( 'زنجان', 'zanjan', 100, 78 ),
	array( 'سمنان', 'semnan', 198, 104 ),
	array( 'سیستان و بلوچستان', 'sistan-baluchestan', 347, 248 ),
	array( 'فارس', 'fars', 181, 245 ),
	array( 'قزوین', 'qazvin', 130, 88 ),
	array( 'قم', 'qom', 148, 127 ),
	array( 'کردستان', 'kurdistan', 70, 111 ),
	array( 'کرمان', 'kerman', 271, 229 ),
	array( 'کرمانشاه', 'kermanshah', 71, 134 ),
	array( 'کهگیلویه و بویراحمد', 'kohgiluyeh-boyer-ahmad', 162, 220 ),
	array( 'گلستان', 'golestan', 219, 75 ),
	array( 'گیلان', 'gilan', 124, 70 ),
	array( 'لرستان', 'lorestan', 97, 154 ),
	array( 'مازندران', 'mazandaran', 191, 81 ),
	array( 'مرکزی', 'markazi', 124, 139 ),
	array( 'هرمزگان', 'hormozgan', 254, 296 ),
	array( 'همدان', 'hamadan', 100, 123 ),
	array( 'یزد', 'yazd', 217, 191 ),
);
$sa_colors = array( '#0b2a4a', '#123f73', '#18558f', '#246da8', '#174f82', '#2b6094', '#387aaf', '#0f355f' );

/* ---- v2.1.0: فقط استان‌هایی که واقعاً منتشر شده‌اند لینک می‌شوند ----
   پیش از این، هر ۳۱ استان از آرایه‌ی بالا لینک می‌شدند؛ چون ۴ استان منتشر بود،
   صفحه‌ی اصلی ۵۴ لینک به صفحه‌های ۴۰۴ می‌داد (۲۷ نقطه‌ی نقشه + ۲۷ نوار).
   این بدترین جای ممکن برای لینک مرده است: گوگل صفحه‌ی اصلی را بیش از هر
   صفحه‌ی دیگری می‌خزد. با انتشار هر استان، لینکش خودبه‌خود برمی‌گردد. */
if ( ! function_exists( 'sa_home_province_url' ) ) {
	/**
	 * نشانی استان اگر منتشر شده باشد، وگرنه رشته‌ی خالی.
	 *
	 * کش داخلی static است، نه متغیر سراسری: فایل‌های قالب داخل load_template()
	 * بارگذاری می‌شوند، پس متغیرهای سطح فایل global نیستند.
	 *
	 * @param string $slug نامک استان.
	 * @return string
	 */
	function sa_home_province_url( $slug ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			$ids = get_posts(
				array(
					'post_type'              => 'province',
					'post_status'            => 'publish',
					'posts_per_page'         => 40,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $ids as $id ) {
				$name = get_post_field( 'post_name', $id );
				if ( $name ) {
					$map[ $name ] = get_permalink( $id );
				}
			}
		}
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}
}

/* ---- logo: custom logo if set, otherwise the bundled official one ---- */
$sa_logo_url = SA_CHILD_URI . 'assets/img/logo.webp';
if ( has_custom_logo() ) {
	$sa_logo_id = (int) get_theme_mod( 'custom_logo' );
	$sa_logo    = wp_get_attachment_image_src( $sa_logo_id, 'full' );
	if ( $sa_logo ) {
		$sa_logo_url = $sa_logo[0];
	}
}

/* ---- customizable texts (پیشخوان → سفارشی‌سازی → صفحه اصلی (طرح v2)) ---- */
$sa_slogan    = get_theme_mod( 'sa_home_slogan', 'چو ایران نباشد، تن من مباد' );
$sa_hero_text = get_theme_mod( 'sa_hero_text', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و نمای برتر طبیعت ایران.' );
$sa_search_ph = get_theme_mod( 'sa_search_placeholder', 'استان، شهر یا نمای برتر…' );
/* v2.1.0: پیش‌فرض شمارنده‌ها = تعداد واقعی منتشرشده (sa_entity_counts با کش
   یک‌ساعته در inc/performance.php). ادعای «۴۴۸+ شهرستان» روی سایتی که ۸۲
   شهرستان دارد، تناقضی است که هم کاربر و هم ارزیاب کیفیت می‌بیند.
   اگر در سفارشی‌سازی عدد دستی گذاشته باشید، همان اولویت دارد. */
$sa_counts    = function_exists( 'sa_entity_counts' ) ? (array) sa_entity_counts() : array();
$sa_stat1_num = get_theme_mod( 'sa_stat1_num', isset( $sa_counts['province'] ) ? (string) (int) $sa_counts['province'] : '31' );
$sa_stat1_lb  = get_theme_mod( 'sa_stat1_label', 'استان' );
$sa_stat2_num = get_theme_mod( 'sa_stat2_num', isset( $sa_counts['city'] ) ? (string) (int) $sa_counts['city'] : '0' );
$sa_stat2_suf = get_theme_mod( 'sa_stat2_suffix', '' );
$sa_stat2_lb  = get_theme_mod( 'sa_stat2_label', 'شهرستان' );
$sa_prov_h2   = get_theme_mod( 'sa_sec_prov_title', 'استان‌های ایران' );
$sa_latest_h2 = get_theme_mod( 'sa_sec_latest_title', 'آخرین مقالات' );
$sa_pop_h2    = get_theme_mod( 'sa_sec_pop_title', 'پست‌های معروف' );
$sa_pop_ids   = array_filter( array_map( 'absint', explode( ',', (string) get_theme_mod( 'sa_pop_ids', '' ) ) ) );
?>

<style>
/* فونت Vazirmatn به‌صورت محلی در قالب مادر بارگذاری و preload می‌شود
   (assets/css/fonts.css + wp_head). درخواست به fonts.googleapis.com از داخل
   ایران معمولاً برنمی‌گردد و چون @import بود، رندر را هم بلوک می‌کرد. */
.sa-home{--ink:#102a43;--mut:#49627a;--navy:#0b2a4a;--navy-deep:#071a30;--navy-2:#123f73;--blue:#18558f;--light-blue:#d9edff;direction:rtl;font-family:Vazirmatn,Tahoma,sans-serif;color:var(--ink);background:#f5f9ff;line-height:1.8;overflow:hidden}
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
.sa-brand{display:flex;align-items:center;flex-wrap:wrap;gap:18px;margin-bottom:6px;min-width:0}
.sa-logo{flex:none;width:176px;height:176px;display:grid;place-items:center;background:#fff;border:1px solid rgba(214,166,67,.5);border-radius:50%;padding:10px;box-shadow:0 24px 42px -24px rgba(7,26,48,.55),0 0 0 4px rgba(255,255,255,.58);filter:none;transform:translateZ(0)}
.sa-logo img{width:100%;height:100%;object-fit:contain;display:block;border-radius:50%;filter:none}
.sa-home .sa-hero h1{margin:0;font-size:clamp(28px,5.4vw,64px);line-height:1.25;font-weight:800;letter-spacing:-.5px;min-width:0;overflow-wrap:anywhere;color:var(--navy);text-shadow:0 2px 0 #fff,0 12px 26px rgba(11,42,74,.2)}
.sa-en{color:var(--navy-2);font-weight:800;letter-spacing:4px;font-size:13px;direction:ltr;text-align:right;margin:2px 0 14px}
.sa-slogan{margin:0 0 10px;font-size:clamp(16px,2.2vw,21px);font-weight:700;color:var(--navy-2)}
.sa-hero .sa-intro{margin:0 0 28px;color:var(--mut);font-size:17px;max-width:520px}
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
.sa-map{position:relative}
.sa-map:before{content:"";position:absolute;inset:8% 4%;border-radius:50%;background:radial-gradient(circle,rgba(36,109,168,.24),transparent 68%)}
.sa-map svg{position:relative;display:block;width:100%;height:auto;filter:drop-shadow(0 44px 40px rgba(7,26,48,.34))}
.sa-map .h{fill:transparent}
.sa-map .d{fill:#bdd8f1;filter:drop-shadow(0 0 5px #18558f);animation:sab var(--t) ease-in-out var(--d) infinite;transition:r .2s}
.sa-map a:hover .d{r:5.2}
.sa-map .sa-dot--soon{opacity:.35;pointer-events:none}
.sa-bar--soon{opacity:.45;cursor:default;pointer-events:none}
.sa-bar--soon em{font-style:normal}
@keyframes sab{0%,100%{opacity:.15}50%{opacity:1}}
.sa-scroll{position:absolute;bottom:24px;left:50%;margin-left:-13px;width:26px;height:42px;border:2px solid rgba(11,20,36,.28);border-radius:14px}
.sa-scroll i{position:absolute;top:8px;left:50%;width:4px;height:8px;margin-left:-2px;border-radius:2px;background:var(--blue);animation:saw 1.8s infinite}
@keyframes saw{0%{opacity:0;transform:translateY(0)}30%{opacity:1}100%{opacity:0;transform:translateY(14px)}}
.sa-off *{animation-play-state:paused!important}
.sa-stats .sa-wrap{position:relative;max-width:760px;margin:0 auto;display:grid;grid-template-columns:repeat(2,1fr);gap:22px}
.sa-st{text-align:center;padding:12px 10px;background:linear-gradient(155deg,#fff,#edf6ff);border:1px solid #bdd8f1;border-radius:28px;box-shadow:0 2px 0 #fff inset,0 40px 64px -28px color-mix(in srgb,var(--c) 72%,transparent),0 10px 22px -16px rgba(11,42,74,.35)}
.sa-st b{display:block;font-size:clamp(40px,6vw,58px);line-height:1.3;font-weight:700;color:var(--c)}
.sa-st span{color:var(--mut);font-weight:500}
.sa-sec{padding-top:96px;content-visibility:auto;contain-intrinsic-size:auto 900px}
.sa-head{position:relative;display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:30px;padding:10px 12px;background:linear-gradient(155deg,#fff 0%,#edf6ff 56%,#dcecff 100%);border:1px solid #bdd8f1;border-inline-start:6px solid var(--navy-2);border-radius:18px;box-shadow:0 2px 0 #fff inset,0 3px 0 rgba(11,42,74,.16),0 18px 28px -14px rgba(11,42,74,.34),0 38px 58px -34px rgba(7,26,48,.48)}
.sa-head h2{position:relative;z-index:1;margin:0;font-size:clamp(26px,3.6vw,38px);font-weight:800;color:var(--navy);text-shadow:0 1px 0 #fff,0 8px 18px rgba(11,42,74,.14)}
.sa-head a{position:relative;z-index:1;color:var(--navy-2);font-weight:700;text-decoration:none;white-space:nowrap}
.sa-bars{display:grid;grid-template-columns:repeat(2,1fr);gap:18px 24px}
.sa-bar{display:flex;align-items:center;gap:14px;height:68px;padding:0 12px;border-radius:20px;background:#fff;border:1px solid rgba(11,20,36,.05);color:var(--ink);text-decoration:none;box-shadow:0 22px 38px -18px color-mix(in srgb,var(--c) 80%,transparent),0 2px 6px rgba(11,20,36,.04);transition:opacity .6s,transform .7s cubic-bezier(.2,.8,.2,1),box-shadow .3s}
.sa-bar:before{content:"";flex:none;width:10px;height:10px;border-radius:50%;background:var(--c);box-shadow:0 0 0 5px color-mix(in srgb,var(--c) 18%,transparent)}
.sa-bar b{flex:1;min-width:0;font-weight:800;font-size:clamp(13px,1.55vw,16.5px);line-height:1.3;white-space:nowrap;letter-spacing:-.02em}
.sa-bar em{font-style:normal;color:#7a8799;font-size:13px;letter-spacing:.4px;direction:ltr}
.sa-bar:after{content:"";flex:none;width:9px;height:9px;border:solid var(--c);border-width:2px 0 0 2px;transform:rotate(-45deg);transition:transform .3s}
.sa-bar:hover:after{transform:rotate(-45deg) translate(-3px,-3px)}
.sa-bar:nth-child(even){--x:-40px}
.sa-posts{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.sa-post{display:block;border-radius:24px;overflow:hidden;background:#fff;color:var(--ink);text-decoration:none;box-shadow:0 34px 54px -28px color-mix(in srgb,var(--c,#18558f) 75%,transparent),0 2px 6px rgba(11,20,36,.05);transition:opacity .6s,transform .7s cubic-bezier(.2,.8,.2,1),box-shadow .3s}
.sa-img{aspect-ratio:16/10;background:linear-gradient(135deg,color-mix(in srgb,var(--c,#18558f) 35%,#fff),color-mix(in srgb,var(--c,#18558f) 8%,#fff)) center/cover}
.sa-pb{padding:10px 12px 12px}
.sa-pb h3{margin:0 0 6px;font-size:17px;line-height:1.7;font-weight:700}
.sa-pb p{margin:0 0 10px;color:var(--mut);font-size:14px}
.sa-pb small{color:#8593a6;font-size:12.5px}
.sa-pop{margin-top:96px;padding:84px 0 100px;border-radius:44px 44px 0 0;color:#fff;background:radial-gradient(700px 320px at 85% 0,rgba(36,109,168,.34),transparent 70%),var(--navy-deep);content-visibility:auto;contain-intrinsic-size:auto 700px}
.sa-pop .sa-head{background:linear-gradient(155deg,#174f82,#0b2a4a);border-color:#387aaf;border-inline-start-color:#8eb9e3;box-shadow:0 2px 0 rgba(255,255,255,.16) inset,0 22px 40px -22px rgba(0,0,0,.8)}.sa-pop .sa-head h2{color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.35)}
.sa-pop .sa-post{background:#111d36;color:#fff;box-shadow:0 38px 64px -30px color-mix(in srgb,var(--c,#18558f) 85%,transparent)}
.sa-pop .sa-pb p{color:#9fb0c9}.sa-pop .sa-pb small{color:#7f92b0}
.js .rv{opacity:0;transform:translateY(26px)}
.js .sa-bar.rv{transform:translateX(var(--x,40px))}
.js .rv.in{opacity:1;transform:none}
.js .rv.in:hover{transform:translateY(-5px)}
@media(max-width:900px){.sa-hw{grid-template-columns:1fr}.sa-logo{width:136px;height:136px;padding:8px}.sa-logo img{width:100%;height:100%}.sa-map{max-width:380px;margin:10px auto 0}.sa-posts{grid-template-columns:repeat(2,1fr)}.sa-scroll{display:none}.sa-hero{padding:48px 0 80px}}
@media(max-width:640px){.sa-wrap{padding-inline:7px}.sa-bars,.sa-posts{grid-template-columns:1fr}.sa-brand{gap:14px}.sa-logo{width:108px;height:108px;padding:7px}.sa-logo img{width:100%;height:100%}.sa-search{height:64px;border-radius:22px}.sa-search button{height:46px;padding:0 20px}.sa-stats .sa-wrap{gap:14px}.sa-bar{height:62px}.sa-bar b{font-size:clamp(12px,3.15vw,15px)}.sa-head{padding:7px 8px;border-inline-start-width:4px}}
@media(max-width:480px){.sa-hero{padding:32px 0 60px;min-height:auto}.sa-brand{gap:12px}.sa-logo{width:84px;height:84px;padding:6px}.sa-logo img{width:100%;height:100%}.sa-en{letter-spacing:2px;font-size:12px}.sa-hero .sa-intro{font-size:15.5px;margin-bottom:22px}.sa-chips a{padding:7px 14px;font-size:13px}.sa-stats .sa-wrap{gap:12px}.sa-st{padding:10px 8px;border-radius:20px}.sa-bar{gap:8px;padding-inline:9px}.sa-bar b{font-size:clamp(10.8px,3.15vw,13.5px);letter-spacing:-.04em}.sa-bar em{display:none}}
/* zir-e 430px, dokme be radif-e dovom miravad */
@media(max-width:430px){.sa-search{height:auto;padding:8px;border-radius:22px;row-gap:8px}.sa-search input{order:1;flex:1 1 auto;font-size:16px}.sa-search svg{order:2;margin:0}.sa-search button{order:3;flex:1 0 100%;height:48px;padding:0 16px;border-radius:14px}}
@media(prefers-reduced-motion:reduce){.sa-sparks i{transition:none}.sa-map .d{animation:none}.sa-scroll i{animation:none}.js .rv{opacity:1;transform:none;transition:none}}
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
					<div class="sa-logo"><img src="<?php echo esc_url( $sa_logo_url ); ?>" width="512" height="512" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" fetchpriority="high" decoding="async"></div>
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
				</div>
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
							$sa_url  = sa_home_province_url( $sa_p[1] );
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
	/* ---- ۷ شهر برتر از نگاه مردم (v2.11.2؛ کادر جدا در بالای صفحه) ---- */
	if ( class_exists( 'CC_UI' ) ) {
		CC_UI::top_cities_block( 7 );
	}
	?>

	<section class="sa-stats" id="sa-stats">
		<div class="sa-wrap">
			<div class="sa-st" style="--c:#123f73"><b data-n="<?php echo esc_attr( (int) $sa_stat1_num ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat1_num ) ); ?></b><span><?php echo esc_html( $sa_stat1_lb ); ?></span></div>
			<div class="sa-st" style="--c:#246da8"><b data-n="<?php echo esc_attr( (int) $sa_stat2_num ); ?>" data-s="<?php echo esc_attr( $sa_stat2_suf ); ?>"><?php echo esc_html( number_format_i18n( (int) $sa_stat2_num ) . $sa_stat2_suf ); ?></b><span><?php echo esc_html( $sa_stat2_lb ); ?></span></div>
		</div>
	</section>

	<section class="sa-sec">
		<div class="sa-wrap">
			<div class="sa-head"><h2><?php echo esc_html( $sa_prov_h2 ); ?></h2><a href="<?php echo esc_url( get_post_type_archive_link( 'province' ) ); ?>">همه‌ی استان‌ها</a></div>
			<div class="sa-bars" id="sa-bars">
				<?php
				foreach ( $sa_map_points as $sa_i => $sa_p ) :
					$sa_url = sa_home_province_url( $sa_p[1] );
					$sa_en  = ucwords( str_replace( '-', ' ', $sa_p[1] ) );
					?>
					<?php if ( $sa_url ) : ?>
						<a class="sa-bar" href="<?php echo esc_url( $sa_url ); ?>" style="--c:<?php echo esc_attr( $sa_colors[ $sa_i % 8 ] ); ?>"><b><?php echo esc_html( $sa_p[0] ); ?></b><em><?php echo esc_html( $sa_en ); ?></em></a>
					<?php else : ?>
						<span class="sa-bar sa-bar--soon" style="--c:<?php echo esc_attr( $sa_colors[ $sa_i % 8 ] ); ?>"><b><?php echo esc_html( $sa_p[0] ); ?></b><em>به‌زودی</em></span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php
	/* ---- آخرین مقالات (سرور-رندر؛ بدون وابستگی به REST) ---- */
	$sa_latest = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $sa_latest->have_posts() ) :
		?>
		<section class="sa-sec">
			<div class="sa-wrap">
				<?php $sa_blog = (int) get_option( 'page_for_posts' ); ?>
				<div class="sa-head"><h2><?php echo esc_html( $sa_latest_h2 ); ?></h2><?php if ( $sa_blog ) : ?><a href="<?php echo esc_url( get_permalink( $sa_blog ) ); ?>">همه‌ی مقالات</a><?php endif; ?></div>
				<div class="sa-posts">
					<?php
					while ( $sa_latest->have_posts() ) :
						$sa_latest->the_post();
						$sa_thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
						?>
						<a class="sa-post rv" href="<?php the_permalink(); ?>" style="--c:<?php echo esc_attr( $sa_colors[ ( $sa_latest->current_post * 3 ) % 8 ] ); ?>">
							<div class="sa-img" <?php if ( $sa_thumb ) : ?>style="background-image:url('<?php echo esc_url( $sa_thumb ); ?>')"<?php endif; ?>></div>
							<div class="sa-pb">
								<h3><?php the_title(); ?></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
								<small><?php echo esc_html( get_the_date() ); ?></small>
							</div>
						</a>
					<?php endwhile; ?>
				</div>
			</div>
		</section>
	<?php endif; wp_reset_postdata(); ?>

	<?php
	/* ---- پست‌های معروف: شناسه‌های دستی از سفارشی‌سازی، وگرنه نوشته‌های چسبان ---- */
	$sa_pop_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	);
	if ( $sa_pop_ids ) {
		$sa_pop_args['post__in'] = $sa_pop_ids;
		$sa_pop_args['orderby']  = 'post__in';
		$sa_pop_args['ignore_sticky_posts'] = true;
	} else {
		$sa_pop_args['post__in'] = get_option( 'sticky_posts' );
		$sa_pop_args['orderby']  = 'date';
	}
	$sa_pop = new WP_Query( $sa_pop_args );
	if ( $sa_pop->have_posts() ) :
		?>
		<section class="sa-pop">
			<div class="sa-wrap">
				<div class="sa-head"><h2><?php echo esc_html( $sa_pop_h2 ); ?></h2></div>
				<div class="sa-posts">
					<?php
					while ( $sa_pop->have_posts() ) :
						$sa_pop->the_post();
						$sa_thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
						?>
						<a class="sa-post rv" href="<?php the_permalink(); ?>" style="--c:<?php echo esc_attr( $sa_colors[ ( $sa_pop->current_post * 3 + 2 ) % 8 ] ); ?>">
							<div class="sa-img" <?php if ( $sa_thumb ) : ?>style="background-image:url('<?php echo esc_url( $sa_thumb ); ?>')"<?php endif; ?>></div>
							<div class="sa-pb">
								<h3><?php the_title(); ?></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
								<small><?php echo esc_html( get_the_date() ); ?></small>
							</div>
						</a>
					<?php endwhile; ?>
				</div>
			</div>
		</section>
	<?php endif; wp_reset_postdata(); ?>
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
rv(R.querySelectorAll('.sa-bar,.sa-st,.sa-head'));
if('IntersectionObserver' in window){new IntersectionObserver(function(es){R.classList.toggle('sa-off',!es[0].isIntersecting)}).observe(R.querySelector('.sa-hero'))}
})();
</script>

<?php
get_footer();
