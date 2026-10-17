<?php
/**
 * Template Name: صفحه اصلی سرزمین آریان (طرح v2)
 *
 * Full-width search, dashboard, four linked orbital counters and audience ranking.
 * Article feeds intentionally stay on their own archives, not on this landing page.
 * @package Sarzaminaryan_Child
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_style( 'sa-home', SA_CHILD_URI . 'assets/css/home.css', array( 'sarzaminaryan-child' ), SA_CHILD_VERSION );
wp_enqueue_script( 'sa-home', SA_CHILD_URI . 'assets/js/home.js', array(), SA_CHILD_VERSION, true );
get_header();

$sa_map_points = sa_iran_provinces();
$sa_pub_provinces = sa_published_entity_ids( 'province' );
$sa_counts = function_exists( 'sa_entity_counts' ) ? (array) sa_entity_counts() : array();
$sa_nav_types = sa_nav_entity_types();
$sa_edu_nav = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
$sa_dash_url = function_exists( 'sa_dashboard_page_url' ) ? sa_dashboard_page_url() : '';
$sa_slogan = get_theme_mod( 'sa_home_slogan', 'چو ایران نباشد، تن من مباد' );
$sa_hero_text = get_theme_mod( 'sa_hero_text', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و دیدنی‌ها طبیعت ایران.' );
$sa_search_ph = get_theme_mod( 'sa_search_placeholder', 'استان، شهر یا دیدنی…' );

// Keep four visible circles, but never invent links to missing/empty archives.
$sa_home_stats = array(
	array( 'label' => get_theme_mod( 'sa_stat1_label', 'استان‌ها' ), 'number' => max( 0, (int) get_theme_mod( 'sa_stat1_num', $sa_counts['province'] ?? 0 ) ), 'suffix' => '', 'url' => in_array( 'province', $sa_nav_types, true ) ? sa_archive_url( 'province' ) : '' ),
	array( 'label' => get_theme_mod( 'sa_stat2_label', 'شهرستان‌ها' ), 'number' => max( 0, (int) get_theme_mod( 'sa_stat2_num', $sa_counts['city'] ?? 0 ) ), 'suffix' => get_theme_mod( 'sa_stat2_suffix', '' ), 'url' => in_array( 'city', $sa_nav_types, true ) ? sa_archive_url( 'city' ) : '' ),
	array( 'label' => 'دیدنی‌ها', 'number' => max( 0, (int) ( $sa_counts['attraction'] ?? 0 ) ), 'suffix' => '', 'url' => in_array( 'attraction', $sa_nav_types, true ) ? sa_archive_url( 'attraction' ) : '' ),
	array( 'label' => 'آموزش‌ها', 'number' => $sa_edu_nav ? (int) $sa_edu_nav['count'] : 0, 'suffix' => '', 'url' => $sa_edu_nav ? $sa_edu_nav['url'] : '' ),
);
?>
<main class="sa-home" id="sa-home">
	<section class="sa-hero" aria-labelledby="sa-home-title">
		<div class="sa-wrap sa-hero__layout">
			<div class="sa-hero__top">
				<div class="sa-hero__copy">
					<h1 id="sa-home-title"><?php bloginfo( 'name' ); ?></h1>
					<p class="sa-en" lang="en" dir="ltr">SARZAMIN ARYAN</p>
					<p class="sa-slogan"><?php echo esc_html( $sa_slogan ); ?></p>
					<p class="sa-intro"><?php echo esc_html( $sa_hero_text ); ?></p>
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
			<form class="sa-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
				<input type="search" name="s" placeholder="<?php echo esc_attr( $sa_search_ph ); ?>" aria-label="جستجو در سرزمین آریان">
				<button type="submit">جستجو</button>
			</form>
			<?php if ( $sa_dash_url ) : ?>
				<a class="sa-dash-cta" href="<?php echo esc_url( $sa_dash_url ); ?>">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg>
					<span>داشبورد و نقشهٔ تعاملی شناسنامهٔ ایران</span>
					<small lang="en" dir="ltr">IRAN INTERACTIVE MAP</small>
				</a>
			<?php endif; ?>
			<a class="sa-scroll" href="#sa-stats" aria-label="ادامه به چهار بخش اصلی"><span aria-hidden="true">↓</span></a>
		</div>
	</section>

	<section class="sa-stats" id="sa-stats" aria-label="چهار بخش اصلی سرزمین آریان">
		<div class="sa-wrap sa-orb-grid">
			<?php foreach ( $sa_home_stats as $sa_stat ) : ?>
				<?php if ( $sa_stat['url'] ) : ?>
					<a class="sa-st" href="<?php echo esc_url( $sa_stat['url'] ); ?>">
				<?php else : ?>
					<div class="sa-st sa-st--unavailable" aria-disabled="true">
				<?php endif; ?>
					<span class="sa-orbits" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
					<span class="sa-st__content">
						<b class="<?php echo strlen( number_format( $sa_stat['number'] ) . $sa_stat['suffix'] ) > 4 ? 'sa-st__number--compact' : ''; ?>" dir="ltr" data-n="<?php echo esc_attr( $sa_stat['number'] ); ?>" data-s="<?php echo esc_attr( $sa_stat['suffix'] ); ?>"><?php echo esc_html( number_format( $sa_stat['number'] ) . $sa_stat['suffix'] ); ?></b>
						<span class="sa-st__label"><?php echo esc_html( $sa_stat['label'] ); ?></span>
					</span>
				<?php echo $sa_stat['url'] ? '</a>' : '</div>'; ?>
			<?php endforeach; ?>
		</div>
	</section>

	<?php
	// Last content section: the real audience ranking, never a fabricated top seven.
	if ( class_exists( 'CC_UI' ) ) { CC_UI::top_cities_block( 7 ); }
	?>
</main>
<?php get_footer(); ?>
