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
			if ( count( $sa_nearby ) >= 8 ) {
				break;
			}
		}
	}
	$sa_coords  = function_exists( 'sa_get_coords' ) ? sa_get_coords( $sa_id ) : null;
	$sa_map_url = function_exists( 'sa_map_url' ) ? sa_map_url( $sa_id ) : '';
	$sa_summary = function_exists( 'sa_summary' ) ? sa_summary( get_post(), 55 ) : '';
	$sa_terms   = get_the_terms( $sa_id, 'attraction_type' );
	$sa_type    = ( $sa_terms && ! is_wp_error( $sa_terms ) ) ? $sa_terms[0]->name : 'جاذبه دیدنی';
	// Owner brief: a stable public code in the POI-<COUNTY>-<NNN> shape.
	$sa_short   = function_exists( 'sa_2026_poi_code' ) ? sa_2026_poi_code( $sa_id ) : 'POI-' . $sa_id;
	$sa_cover   = sa_2026_cover( $sa_id, 'large' );
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
			<span class="hidden sm:inline-block font-mono text-[11px] text-brand-forest bg-brand-forestSoft px-2.5 py-0.5 rounded-md font-semibold">
				SYS.ID // <?php echo esc_html( $sa_short ); ?>
			</span>
		</div>
	</div>

	<div class="sticky top-16 z-40 bg-canvas/95 backdrop-blur-md border-b border-line shadow-2xs">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<nav aria-label="بخش‌های صفحه دیدنی" class="flex items-center gap-1.5 sm:gap-2 py-2.5 overflow-x-auto no-scrollbar text-xs font-semibold">
				<span class="text-muted ml-1 shrink-0 hidden sm:inline">بخش‌ها:</span>
				<a href="#factsheet-section" class="jump-link is-active shrink-0 px-3 py-1.5 rounded-lg bg-brand-forest text-white transition-colors">شناسنامه مکان</a>
				<a href="#story-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-brand-forestSoft hover:text-brand-forest border border-line transition-colors">روایت دیدنی</a>
				<?php if ( $sa_facts ) : ?>
					<a href="#visitor-guide-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-brand-forestSoft hover:text-brand-forest border border-line transition-colors">راهنمای بازدید و دسترسی</a>
				<?php endif; ?>
				<?php if ( $sa_nearby ) : ?>
					<a href="#nearby-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-brand-forestSoft hover:text-brand-forest border border-line transition-colors">دیدنی‌های نزدیک</a>
				<?php endif; ?>
			</nav>
		</div>
	</div>

	<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-12">

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
						<p class="text-slate-600 text-sm sm:text-base leading-relaxed"><?php echo esc_html( $sa_summary ); ?></p>
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

				<div class="lg:col-span-5 space-y-3">
					<div class="relative rounded-2xl overflow-hidden border border-line aspect-[16/10] shadow-sm">
						<img src="<?php echo esc_url( $sa_cover ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"
							class="absolute inset-0 w-full h-full object-cover" loading="eager" fetchpriority="high" decoding="async">
						<div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
						<div class="absolute inset-x-0 bottom-0 p-4 text-white space-y-1">
							<div class="text-xs text-amber-200 font-semibold"><?php echo esc_html( $sa_type ); ?></div>
							<?php if ( $sa_city ) : ?>
								<p class="text-[11px] text-slate-100 leading-relaxed"><?php echo esc_html( get_the_title( $sa_city ) ); ?><?php echo esc_html( $sa_prov ? ' · ' . get_the_title( $sa_prov ) : '' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="grid grid-cols-3 gap-2.5 text-center">
						<div class="bg-canvas rounded-xl border border-line p-2.5">
							<span class="block text-[11px] text-muted">دسته‌بندی</span>
							<strong class="text-xs sm:text-sm font-extrabold text-ink"><?php echo esc_html( $sa_type ); ?></strong>
						</div>
						<div class="bg-canvas rounded-xl border border-line p-2.5">
							<span class="block text-[11px] text-muted">شهرستان</span>
							<strong class="text-xs sm:text-sm font-extrabold text-brand-terracotta"><?php echo esc_html( $sa_city ? get_the_title( $sa_city ) : '—' ); ?></strong>
						</div>
						<div class="bg-canvas rounded-xl border border-line p-2.5">
							<span class="block text-[11px] text-muted">استان</span>
							<strong class="text-xs sm:text-sm font-extrabold text-brand-forest"><?php echo esc_html( $sa_prov ? get_the_title( $sa_prov ) : '—' ); ?></strong>
						</div>
					</div>
				</div>
			</div>
		</section>

		<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
			<section id="story-section" aria-labelledby="story-heading" class="lg:col-span-7 scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-2xs space-y-5">
				<div class="border-b border-line pb-4">
					<span class="text-xs font-bold text-brand-terracotta">روایت دیدنی</span>
					<h2 id="story-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">
						<?php echo esc_html( 'درباره ' . get_the_title() ); ?>
					</h2>
				</div>
				<div class="sa-2026-prose">
					<?php sa_2026_relation_content( $sa_city ? array( get_permalink( $sa_city ) ) : array() ); ?>
				</div>
			</section>

			<aside id="factsheet-section" aria-labelledby="factsheet-heading" class="lg:col-span-5 scroll-mt-32 bg-surface rounded-3xl border border-line p-6 shadow-2xs space-y-4">
				<div class="flex items-center justify-between border-b border-line pb-3.5 gap-2">
					<h2 id="factsheet-heading" class="text-base font-extrabold text-brand-forest flex items-center gap-2">
						<svg class="w-4 h-4 text-brand-terracotta" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
						</svg>
						<span>شناسنامه دیدنی</span>
					</h2>
					<span class="font-mono text-[11px] font-bold text-muted bg-canvas px-2.5 py-1 rounded-lg border border-line">
						<?php echo esc_html( $sa_short ); ?>
					</span>
				</div>

				<dl class="space-y-2.5 text-xs sm:text-sm">
					<?php if ( $sa_facts ) : ?>
						<?php foreach ( $sa_facts as $sa_fact ) : ?>
							<div class="bg-canvas p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
								<dt class="text-muted text-xs shrink-0"><?php echo esc_html( $sa_fact['label'] ); ?></dt>
								<dd class="font-bold text-ink text-left">
									<?php echo ! empty( $sa_fact['html'] ) ? wp_kses_post( $sa_fact['value'] ) : esc_html( $sa_fact['value'] ); ?>
								</dd>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="bg-canvas p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
							<dt class="text-muted text-xs">موقعیت</dt>
							<dd class="font-bold text-ink text-left">
								<?php echo esc_html( trim( ( $sa_prov ? get_the_title( $sa_prov ) : '' ) . ' · ' . ( $sa_city ? get_the_title( $sa_city ) : '' ), ' ·' ) ); ?>
							</dd>
						</div>
					<?php endif; ?>

					<?php if ( $sa_coords ) : ?>
						<div class="bg-canvas p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
							<dt class="text-muted text-xs">مختصات جغرافیایی</dt>
							<dd class="font-mono text-xs font-bold text-ink text-left" dir="ltr">
								<?php echo esc_html( number_format_i18n( $sa_coords[0], 4 ) . '° N, ' . number_format_i18n( $sa_coords[1], 4 ) . '° E' ); ?>
							</dd>
						</div>
					<?php endif; ?>
				</dl>

				<div class="text-[11px] text-muted leading-relaxed">
					<?php
					if ( function_exists( 'sa_facts_checked_note' ) ) {
						sa_facts_checked_note( $sa_id );
					}
					?>
				</div>
			</aside>
		</div>

		<?php if ( $sa_facts ) : ?>
			<section id="visitor-guide-section" aria-labelledby="guide-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-2xs space-y-5">
				<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-line pb-4">
					<div>
						<span class="text-xs font-bold text-brand-terracotta">برنامه‌ریزی بازدید</span>
						<h2 id="guide-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-0.5">راهنمای عملی بازدید و دسترسی</h2>
					</div>
					<?php if ( $sa_map_url ) : ?>
						<a href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener" class="text-xs font-bold text-brand-forest hover:underline">مسیر روی نقشه ←</a>
					<?php endif; ?>
				</div>

				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					<?php foreach ( $sa_facts as $sa_fact ) : ?>
						<div class="bg-canvas rounded-2xl border border-line p-4 space-y-1.5">
							<span class="text-[11px] font-bold text-brand-terracotta"><?php echo esc_html( $sa_fact['label'] ); ?></span>
							<div class="text-sm font-bold text-ink leading-relaxed">
								<?php echo ! empty( $sa_fact['html'] ) ? wp_kses_post( $sa_fact['value'] ) : esc_html( $sa_fact['value'] ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $sa_nearby ) : ?>
			<section id="nearby-section" aria-labelledby="nearby-heading" class="scroll-mt-32 space-y-5">
				<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
					<div>
						<span class="text-xs font-bold text-brand-terracotta">ادامه مسیر</span>
						<h2 id="nearby-heading" class="text-xl font-extrabold text-ink mt-0.5">
							<?php echo esc_html( 'دیدنی‌های نزدیک' . ( $sa_city ? ' در ' . get_the_title( $sa_city ) : '' ) ); ?>
						</h2>
					</div>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
					<?php foreach ( array_slice( (array) $sa_nearby, 0, 4 ) as $sa_post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>"
							class="group bg-surface rounded-2xl border border-line overflow-hidden hover:border-brand-forest hover:shadow-sm transition-all">
							<span class="block aspect-[16/10] overflow-hidden bg-canvas">
								<img src="<?php echo esc_url( sa_2026_cover( $sa_post->ID, 'medium' ) ); ?>" alt=""
									class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
							</span>
							<span class="block p-4 space-y-1">
								<strong class="block text-sm font-extrabold text-ink group-hover:text-brand-forest transition-colors"><?php echo esc_html( get_the_title( $sa_post ) ); ?></strong>
								<span class="block text-[11px] text-slate-600 line-clamp-2"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $sa_post->post_content ), 16, '…' ) ); ?></span>
							</span>
						</a>
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
