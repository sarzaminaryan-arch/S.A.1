<?php
/**
 * Single attraction — 2026 design refresh ("گنجینه دیدنی‌های ایران" palette).
 *
 * Approved mockup: hero with cover + quick actions → editorial story beside
 * the POI fact sheet → visitor guide → nearby attractions → return banner.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'single-attraction' ) ) {
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$sa_id      = get_the_ID();
	$sa_city    = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_id, 'city' ) : null;
	if ( $sa_city && 'city' !== $sa_city->post_type ) {
		$sa_city = null;
	}
	$sa_prov    = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_id, 'province' ) : null;
	$sa_facts   = function_exists( 'sa_entity_facts' ) ? sa_entity_facts( $sa_id, false ) : array();
	$sa_nearby  = array();
	if ( $sa_city instanceof WP_Post && function_exists( 'sa_get_children' ) ) {
		foreach ( (array) sa_get_children( $sa_city->ID, 'attraction' ) as $sa_nearby_post ) {
			if ( ! $sa_nearby_post instanceof WP_Post || (int) $sa_nearby_post->ID === (int) $sa_id || 'publish' !== $sa_nearby_post->post_status ) {
				continue;
			}
			$sa_nearby[] = $sa_nearby_post;
			if ( count( $sa_nearby ) >= 4 ) {
				break;
			}
		}
	}
	// Only reserve destinations that actually have a card on this page.
	$sa_card_urls = array_map( 'get_permalink', $sa_nearby );
	if ( $sa_city ) {
		$sa_card_urls[] = get_permalink( $sa_city );
	}
	$sa_coords  = function_exists( 'sa_get_coords' ) ? sa_get_coords( $sa_id ) : null;
	$sa_map_url = function_exists( 'sa_map_url' ) ? sa_map_url( $sa_id ) : '';
	$sa_summary = function_exists( 'sa_summary' ) ? sa_summary( get_post(), 55 ) : '';
	$sa_terms   = get_the_terms( $sa_id, 'attraction_type' );
	$sa_type    = ( $sa_terms && ! is_wp_error( $sa_terms ) ) ? $sa_terms[0]->name : 'جاذبه دیدنی';
	// Owner brief: a stable public code in the POI-<COUNTY>-<NNN> shape.
	$sa_short     = function_exists( 'sa_2026_poi_code' ) ? sa_2026_poi_code( $sa_id ) : 'POI-' . $sa_id;
	$sa_cover     = sa_2026_cover( $sa_id, 'large' );
	$sa_hero      = function_exists( 'sa_2026_hero_media' )
		? sa_2026_hero_media( $sa_id, true, get_the_title() . ( $sa_city ? ' در ' . get_the_title( $sa_city ) : '' ) )
		: array( 'url' => $sa_cover, 'width' => 1200, 'height' => 750, 'alt' => get_the_title(), 'credit_html' => '' );

	// Build unified Quick Visit Info Grid rows (immediately below the Hero section).
	$sa_quick_rows = array();
	$sa_seen_lbl   = array();
	$sa_add_quick  = static function ( $label, $value, $is_html = false, $ltr = false ) use ( &$sa_quick_rows, &$sa_seen_lbl ) {
		$label = trim( (string) $label );
		$value = trim( (string) $value );
		$sa_empty_sentinels = array( '', '0', '۰', '0 متر', '۰ متر', 'نامشخص', '—', '-', 'ندارد', '0 دقیقه', '۰ دقیقه' );
		if ( '' === $label || in_array( $value, $sa_empty_sentinels, true ) || isset( $sa_seen_lbl[ $label ] ) ) {
			return;
		}
		$sa_seen_lbl[ $label ] = true;
		$sa_quick_rows[]       = array(
			'label' => $label,
			'value' => $value,
			'html'  => (bool) $is_html,
			'ltr'   => (bool) $ltr,
		);
	};

	$sa_loc_parts = array_filter(
		array(
			$sa_city ? get_the_title( $sa_city ) : '',
			$sa_prov ? get_the_title( $sa_prov ) : '',
		)
	);
	if ( $sa_loc_parts ) {
		$sa_add_quick( 'شهرستان و استان', implode( ' · ', $sa_loc_parts ) );
	}
	$sa_add_quick( 'دسته‌بندی جاذبه', $sa_type );
	$sa_area_meta = trim( (string) get_post_meta( $sa_id, 'sa_attr_area', true ) );
	if ( '' === $sa_area_meta ) {
		$sa_area_meta = trim( (string) get_post_meta( $sa_id, 'sa_address', true ) );
	}
	$sa_add_quick( 'محدوده مکانی', $sa_area_meta );
	$sa_best_visit = trim( (string) get_post_meta( $sa_id, 'sa_best_time', true ) );
	if ( '' === $sa_best_visit ) {
		$sa_best_visit = trim( (string) get_post_meta( $sa_id, 'sa_best_season', true ) );
	}
	$sa_add_quick( 'بهترین زمان بازدید', $sa_best_visit );
	$sa_add_quick( 'مدت‌زمان پیشنهادی بازدید', (string) get_post_meta( $sa_id, 'sa_visit_duration', true ) );
	$sa_add_quick( 'مناسب برای', (string) get_post_meta( $sa_id, 'sa_suitable_for', true ) );
	foreach ( (array) $sa_facts as $sa_fact ) {
		if ( ! empty( $sa_fact['label'] ) && isset( $sa_fact['value'] ) ) {
			$sa_add_quick( $sa_fact['label'], $sa_fact['value'], ! empty( $sa_fact['html'] ) );
		}
	}
	if ( $sa_coords ) {
		$sa_add_quick(
			'مختصات جغرافیایی',
			number_format_i18n( $sa_coords[0], 4 ) . '° N, ' . number_format_i18n( $sa_coords[1], 4 ) . '° E',
			false,
			true
		);
	}
	?>

	<div class="bg-surface border-b border-line/70">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-2 text-xs text-muted">
			<nav aria-label="مسیر صفحه" class="flex items-center gap-1.5 flex-wrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-brand-forest transition-colors">خانه</a>
				<span aria-hidden="true">›</span>
				<a href="<?php echo esc_url( function_exists( 'sa_archive_url' ) ? sa_archive_url( 'attraction' ) : home_url( '/' ) ); ?>" class="hover:text-brand-forest transition-colors">گنجینه دیدنی‌های ایران</a>
				<?php if ( $sa_prov ) : ?>
					<span aria-hidden="true">›</span>
					<a href="<?php echo esc_url( get_permalink( $sa_prov ) ); ?>" class="hover:text-brand-forest transition-colors"><?php echo esc_html( get_the_title( $sa_prov ) ); ?></a>
				<?php endif; ?>
				<?php if ( $sa_city ) : ?>
					<span aria-hidden="true">›</span>
					<a href="<?php echo esc_url( get_permalink( $sa_city ) ); ?>" data-sa-relation class="hover:text-brand-forest transition-colors"><?php echo esc_html( get_the_title( $sa_city ) ); ?></a>
				<?php endif; ?>
				<span aria-hidden="true">›</span>
				<span class="text-ink font-bold" aria-current="page"><?php the_title(); ?></span>
			</nav>
			<div class="hidden sm:flex items-center gap-2 shrink-0">
				<span class="text-[11px] text-muted"><?php echo esc_html( 'به‌روزرسانی: ' . get_the_modified_date() ); ?></span>
				<span class="font-mono text-[11px] text-brand-forest bg-brand-forestSoft px-2.5 py-0.5 rounded-md font-semibold">
					SYS.ID // <?php echo esc_html( $sa_short ); ?>
				</span>
			</div>
		</div>
	</div>

	<div class="sticky top-16 z-40 bg-canvas/95 backdrop-blur-md border-b border-line shadow-2xs">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<nav aria-label="بخش‌های صفحه دیدنی" class="flex items-center gap-1.5 sm:gap-2 py-2.5 overflow-x-auto no-scrollbar text-xs font-semibold">
				<span class="text-muted ml-1 shrink-0 hidden sm:inline">پرش سریع:</span>
				<a href="#factsheet-section" class="jump-link is-active shrink-0 px-3 py-1.5 rounded-lg bg-brand-forest text-white transition-colors">اطلاعات سریع بازدید</a>
				<a href="#story-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-brand-forestSoft hover:text-brand-forest border border-line transition-colors">معرفی، دسترسی و تجربه بازدید</a>
				<?php if ( $sa_nearby ) : ?>
					<a href="#nearby-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-brand-forestSoft hover:text-brand-forest border border-line transition-colors">دیدنی‌های نزدیک و هم‌مسیر</a>
				<?php endif; ?>
			</nav>
		</div>
	</div>

	<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-10">

		<section aria-labelledby="poi-title" class="bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-2xs space-y-8 relative overflow-hidden">
			<div class="absolute -left-24 -top-24 w-80 h-80 rounded-full bg-brand-forestSoft/60 pointer-events-none"></div>
			<div class="absolute -left-6 top-40 w-40 h-40 rounded-full bg-brand-terracottaSoft/70 pointer-events-none"></div>

			<div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
				<div class="lg:col-span-7 space-y-5">
					<div class="flex flex-wrap items-center gap-2">
						<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-terracottaSoft text-brand-terracotta text-xs font-bold border border-brand-terracotta/20">
							<span class="w-1.5 h-1.5 rounded-full bg-brand-terracotta"></span>
							<?php echo esc_html( $sa_type ); ?>
						</span>
						<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-brand-forestSoft text-brand-forest text-xs font-semibold">
							گنجینه دیدنی‌های ایران
						</span>
					</div>

					<h1 id="poi-title" class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-ink leading-tight">
						<?php the_title(); ?>
					</h1>

					<?php if ( $sa_summary ) : ?>
						<div class="sa-tldr-box rounded-2xl border border-brand-forest/20 bg-canvas/90 p-4 sm:p-5 space-y-1.5">
							<span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-brand-forest">
								<span class="w-1.5 h-1.5 rounded-full bg-brand-forest"></span>
								<span>چکیده پاسخ سریع</span>
							</span>
							<p class="text-slate-700 text-sm sm:text-base leading-relaxed"><?php echo esc_html( $sa_summary ); ?></p>
						</div>
					<?php endif; ?>

					<div class="pt-1 flex flex-wrap items-center gap-3">
						<?php if ( $sa_map_url ) : ?>
							<a href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener"
								class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-forest hover:bg-brand-forestLight text-white text-xs sm:text-sm font-bold shadow-xs transition-colors">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
								</svg>
								<span>مسیر دسترسی روی نقشه</span>
							</a>
						<?php endif; ?>

						<button type="button" data-sa-bookmark
							aria-pressed="false"
							class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-canvas hover:bg-brand-terracottaSoft text-ink hover:text-brand-terracotta border border-line text-xs sm:text-sm font-semibold transition-colors">
							<svg class="w-4 h-4 text-brand-terracotta" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
							</svg>
							<span data-sa-bookmark-label>ذخیره در برنامه سفر</span>
						</button>

						<button type="button" data-sa-open-experience
							class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface hover:bg-slate-50 text-slate-700 border border-line text-xs sm:text-sm font-semibold transition-colors">
							<svg class="w-4 h-4 text-brand-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
							</svg>
							<span>ثبت تجربه بازدید</span>
						</button>
					</div>
				</div>

				<figure class="lg:col-span-5 sa-hero-media space-y-1.5">
					<div class="relative rounded-2xl overflow-hidden border border-line aspect-[16/10] shadow-sm bg-slate-100">
						<img
							src="<?php echo esc_url( $sa_hero['url'] ); ?>"
							alt="<?php echo esc_attr( $sa_hero['alt'] ); ?>"
							width="<?php echo (int) $sa_hero['width']; ?>"
							height="<?php echo (int) $sa_hero['height']; ?>"
							class="absolute inset-0 w-full h-full object-cover"
							loading="eager"
							fetchpriority="high"
							decoding="async">
						<div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent pointer-events-none"></div>
						<div class="absolute inset-x-0 bottom-0 p-4 text-white space-y-0.5 pointer-events-none">
							<div class="text-xs text-amber-200 font-semibold"><?php echo esc_html( $sa_type ); ?></div>
							<?php if ( $sa_city ) : ?>
								<p class="text-[11px] text-slate-100 leading-relaxed"><?php echo esc_html( get_the_title( $sa_city ) ); ?><?php echo esc_html( $sa_prov ? ' · ' . get_the_title( $sa_prov ) : '' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php
					if ( ! empty( $sa_hero['credit_html'] ) ) {
						echo wp_kses_post( $sa_hero['credit_html'] );
					}
					?>
				</figure>
			</div>
		</section>

		<section id="factsheet-section" aria-labelledby="factsheet-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-2xs space-y-5">
			<div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-line pb-4 gap-2">
				<div>
					<span class="text-xs font-bold text-brand-terracotta">تصمیم‌گیری سریع پیش از حرکت</span>
					<h2 id="factsheet-heading" class="text-lg sm:text-xl font-extrabold text-brand-forest flex items-center gap-2 mt-0.5">
						<svg class="w-4 h-4 text-brand-terracotta" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
						</svg>
						<span>کارت اطلاعات سریع بازدید</span>
					</h2>
				</div>
				<div class="flex items-center gap-2 text-[11px] text-muted">
					<?php
					if ( function_exists( 'sa_facts_checked_note' ) ) {
						sa_facts_checked_note( $sa_id );
					}
					?>
					<span class="font-mono font-bold bg-canvas px-2.5 py-1 rounded-lg border border-line">
						<?php echo esc_html( $sa_short ); ?>
					</span>
				</div>
			</div>

			<dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs sm:text-sm">
				<?php foreach ( $sa_quick_rows as $sa_qrow ) : ?>
					<div class="bg-canvas rounded-2xl border border-line/80 p-4 space-y-1">
						<dt class="text-[11px] font-bold text-muted"><?php echo esc_html( $sa_qrow['label'] ); ?></dt>
						<dd class="font-bold text-ink leading-relaxed<?php echo $sa_qrow['ltr'] ? ' font-mono text-xs text-left' : ''; ?>"<?php echo $sa_qrow['ltr'] ? ' dir="ltr"' : ''; ?>>
							<?php echo $sa_qrow['html'] ? wp_kses_post( $sa_qrow['value'] ) : esc_html( $sa_qrow['value'] ); ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>

		<section id="story-section" aria-labelledby="story-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-2xs space-y-5">
			<div class="border-b border-line pb-4">
				<span class="text-xs font-bold text-brand-terracotta">راهنمای میدانی و بازدید</span>
				<h2 id="story-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">
					<?php echo esc_html( 'معرفی، مسیر دسترسی و راهنمای بازدید از ' . get_the_title() ); ?>
				</h2>
			</div>
			<div class="sa-2026-prose">
				<?php sa_2026_relation_content( $sa_card_urls ); ?>
			</div>
		</section>

		<?php if ( $sa_nearby ) : ?>
			<section id="nearby-section" aria-labelledby="nearby-heading" class="scroll-mt-32 space-y-5">
				<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
					<div>
						<span class="text-xs font-bold text-brand-terracotta">ادامه مسیر و تکمیل برنامه گشت</span>
						<h2 id="nearby-heading" class="text-xl font-extrabold text-ink mt-0.5">
							<?php echo esc_html( 'جاذبه‌های نزدیک و هم‌مسیر' . ( $sa_city ? ' در ' . get_the_title( $sa_city ) : '' ) ); ?>
						</h2>
					</div>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
					<?php foreach ( $sa_nearby as $sa_post ) : ?>
						<article class="sa-card-linked group bg-surface rounded-2xl border border-line overflow-hidden hover:border-brand-forest hover:shadow-sm transition-all flex flex-col">
							<div class="aspect-[16/10] overflow-hidden bg-canvas">
								<img
									src="<?php echo esc_url( sa_2026_cover( $sa_post->ID, 'medium' ) ); ?>"
									alt="<?php echo esc_attr( get_the_title( $sa_post ) ); ?>"
									width="640"
									height="400"
									class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
									loading="lazy"
									decoding="async">
							</div>
							<div class="p-4 space-y-1 flex-1">
								<h3 class="text-sm font-extrabold text-ink group-hover:text-brand-forest transition-colors">
									<a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>" class="sa-stretched-link"><?php echo esc_html( get_the_title( $sa_post ) ); ?></a>
								</h3>
								<p class="text-[11px] text-slate-600 line-clamp-2"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $sa_post->post_content ), 16, '…' ) ); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section aria-labelledby="cta-heading" class="bg-brand-forest text-white rounded-3xl p-6 sm:p-10 shadow-2xs relative overflow-hidden">
			<div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-white/5 pointer-events-none"></div>
			<div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
				<div class="space-y-2.5 max-w-2xl">
					<span class="inline-block text-xs font-bold text-amber-300 uppercase tracking-wider">مشارکت در گنجینه دیدنی‌ها</span>
					<h2 id="cta-heading" class="text-xl sm:text-2xl lg:text-3xl font-extrabold">
						<?php echo esc_html( 'از بازدید ' . get_the_title() . ' چه تجربه‌ای دارید؟' ); ?>
					</h2>
					<p class="text-xs sm:text-sm text-slate-200 leading-relaxed">
						ساعت کاری تازه، تغییر مسیر دسترسی، قیمت بلیت یا نکته‌ای که در متن نیست؟ تجربه‌تان را ثبت کنید تا پس از بررسی تحریریه به نام شما منتشر شود.
					</p>
				</div>
				<div class="flex flex-wrap items-center gap-3 shrink-0">
					<button type="button" class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-brand-terracotta hover:bg-brand-terracotta/90 text-white font-bold text-xs sm:text-sm shadow-md transition-all" data-sa-open-experience>
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
						</svg>
						<span>ثبت تجربه بازدید</span>
					</button>
				</div>
			</div>
		</section>

		<?php sa_comments_2026_render(); ?>

	</main>

	<?php
endwhile;

get_footer();
