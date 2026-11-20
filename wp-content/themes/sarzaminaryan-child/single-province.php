<?php
/**
 * Single province — 2026 design refresh.
 *
 * Approved mockup: breadcrumb → hero + data sheet → county search & grid →
 * editorial body → suggested routes → climate/tips → neighbouring provinces.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'single-province' ) ) {
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$sa_id   = get_the_ID();
	$sa_slug = (string) get_post_field( 'post_name', $sa_id );

	$sa_regions = function_exists( 'sa_region_facts' ) ? sa_region_facts() : array( 'provinces' => array() );
	$sa_fact    = isset( $sa_regions['provinces'][ $sa_slug ] ) ? $sa_regions['provinces'][ $sa_slug ] : array();

	$sa_profile  = function_exists( 'sa_province_profile' ) ? sa_province_profile( $sa_slug ) : array();
	$sa_stats    = isset( $sa_profile['stats'] ) && is_array( $sa_profile['stats'] ) ? $sa_profile['stats'] : array();
	$sa_counties = sa_2026_province_counties( $sa_id );
	$sa_routes   = function_exists( 'sa_get_children' ) ? sa_get_children( $sa_id, 'travel_route' ) : array();
	$sa_neigh    = sa_2026_province_neighbours( $sa_id, 10 );
	if ( ! $sa_neigh && ! empty( $sa_fact['neighbors'] ) ) {
		foreach ( explode( '،', (string) $sa_fact['neighbors'] ) as $sa_neighbor_name ) {
			$sa_neighbor_name = trim( $sa_neighbor_name );
			if ( '' === $sa_neighbor_name ) {
				continue;
			}
			$sa_neigh[] = array( 'id' => 0, 'title' => $sa_neighbor_name, 'url' => '#' );
		}
	}

	$sa_center      = isset( $sa_fact['capital'] ) ? (string) $sa_fact['capital'] : '';
	$sa_capital_url = '';
	if ( $sa_center ) {
		$sa_capital_post = get_page_by_path( sanitize_title( $sa_center ), OBJECT, 'city' );
		if ( ! $sa_capital_post ) {
			$sa_capital_post = get_page_by_path( sanitize_title( $sa_center ), OBJECT, 'post' );
		}
		if ( $sa_capital_post instanceof WP_Post && 'publish' === $sa_capital_post->post_status ) {
			$sa_capital_url = (string) get_permalink( $sa_capital_post );
		}
	}

	$sa_county_count = isset( $sa_stats['county_count'] ) ? (int) $sa_stats['county_count'] : count( $sa_counties );
	$sa_population   = isset( $sa_stats['population'] ) ? (int) $sa_stats['population'] : (int) ( $sa_fact['population'] ?? 0 );
	$sa_pop_year     = isset( $sa_profile['population_year'] ) ? (int) $sa_profile['population_year'] : 1395;
	$sa_area         = isset( $sa_fact['area'] ) ? (float) $sa_fact['area'] : 0;
	$sa_climate      = isset( $sa_fact['climate'] ) ? (string) $sa_fact['climate'] : '';
	$sa_short_id     = 'PRV-' . strtoupper( substr( preg_replace( '/[^a-z]/', '', $sa_slug ), 0, 3 ) );
	$sa_excerpt      = function_exists( 'sa_summary' ) ? sa_summary( get_post(), 70 ) : '';
	?>

	<div class="bg-surface border-b border-line/70">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between text-xs text-muted">
			<nav aria-label="مسیر صفحه" class="flex items-center gap-1.5 flex-wrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">خانه</a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( function_exists( 'sa_archive_url' ) ? sa_archive_url( 'province' ) : home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">استان‌ها</a>
				<span aria-hidden="true">/</span>
				<span class="text-ink font-semibold" aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
			</nav>
			<span class="hidden sm:inline-block font-mono text-[11px] text-muted bg-slate-100 px-2 py-0.5 rounded">
				SYS.ID // <?php echo esc_html( $sa_short_id ); ?>
			</span>
		</div>
	</div>

	<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-12">

		<section aria-labelledby="province-title" class="bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-sm relative overflow-hidden">
			<div class="absolute -left-16 -top-16 w-64 h-64 rounded-full bg-petrolSoft/60 pointer-events-none"></div>
			<div class="absolute -left-6 top-24 w-32 h-32 rounded-full bg-ochreSoft/70 pointer-events-none"></div>

			<div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
				<div class="lg:col-span-7 space-y-4">
					<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-ochreSoft text-ochre text-xs font-semibold">
						<span class="w-1.5 h-1.5 rounded-full bg-ochre"></span>
						<span>راهنمای جامع استان</span>
					</div>

					<h1 id="province-title" class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-ink leading-tight">
						<?php the_title(); ?><?php echo esc_html( '؛ راهنمای سفر، شهرستان‌ها و جاذبه‌ها' ); ?>
					</h1>

					<?php if ( $sa_excerpt ) : ?>
						<p class="text-slate-600 text-sm sm:text-base leading-relaxed"><?php echo esc_html( $sa_excerpt ); ?></p>
					<?php endif; ?>

					<div class="pt-2 flex flex-wrap items-center gap-2 text-xs font-medium">
						<span class="text-muted ml-1">دسترسی سریع:</span>
						<a href="#counties-section" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-petrolSoft hover:text-petrol transition-colors">شهرستان‌های استان</a>
						<a href="#story-section" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-petrolSoft hover:text-petrol transition-colors">راهنمای سفر</a>
						<?php if ( $sa_routes ) : ?>
							<a href="#routes-section" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-petrolSoft hover:text-petrol transition-colors">مسیرهای پیشنهادی سفر</a>
						<?php endif; ?>
						<a href="#climate-section" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-petrolSoft hover:text-petrol transition-colors">اقلیم و زمان سفر</a>
						<?php if ( $sa_neigh ) : ?>
							<a href="#neighbors-section" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-petrolSoft hover:text-petrol transition-colors">استان‌های همسایه</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="lg:col-span-5 bg-canvas rounded-2xl border border-line p-5">
					<div class="flex items-center justify-between border-b border-line pb-3 mb-4">
						<h2 class="text-sm font-bold text-petrol flex items-center gap-2">
							<svg class="w-4 h-4 text-ochre" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
							</svg>
							<span>شناسنامه و داده‌های کلیدی استان</span>
						</h2>
						<span class="font-mono text-[11px] text-muted"><?php echo esc_html( $sa_short_id ); ?></span>
					</div>

					<dl class="grid grid-cols-2 gap-3 text-xs sm:text-sm">
						<?php if ( $sa_center ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">مرکز استان</dt>
								<dd class="font-bold text-ink">
									<?php if ( $sa_capital_url && ! in_array( $sa_capital_url, array_column( $sa_counties, 'url' ), true ) ) : ?>
										<a href="<?php echo esc_url( $sa_capital_url ); ?>" class="text-petrol hover:underline"><?php echo esc_html( $sa_center ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $sa_center ); ?>
									<?php endif; ?>
								</dd>
							</div>
						<?php endif; ?>

						<div class="bg-surface p-3 rounded-xl border border-line/70">
							<dt class="text-muted text-xs mb-0.5">تعداد شهرستان‌ها</dt>
							<dd class="font-bold text-ink">
								<?php echo esc_html( sa_2026_num( $sa_county_count ) . ' شهرستان' ); ?>
								<?php if ( count( $sa_counties ) ) : ?>
									<span class="text-xs font-normal text-muted"><?php echo esc_html( '(' . sa_2026_num( count( $sa_counties ) ) . ' راهنمای فعال)' ); ?></span>
								<?php endif; ?>
							</dd>
						</div>

						<?php if ( $sa_area ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">مساحت استان</dt>
								<dd class="font-bold text-ink"><?php echo esc_html( sa_2026_num( round( $sa_area ) ) . ' کیلومتر مربع' ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( $sa_climate ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">اقلیم غالب</dt>
								<dd class="font-bold text-ink"><?php echo esc_html( $sa_climate ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( $sa_population ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">جمعیت</dt>
								<dd class="font-bold text-ink">
									<?php echo esc_html( sa_2026_num( round( $sa_population / 1000 ) * 1000 ) . ' نفر' ); ?>
									<span class="text-xs font-normal text-muted"><?php echo esc_html( '(' . sa_2026_num( $sa_pop_year ) . ')' ); ?></span>
								</dd>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $sa_stats['city_count'] ) ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">تعداد شهرها</dt>
								<dd class="font-bold text-ink"><?php echo esc_html( sa_2026_num( $sa_stats['city_count'] ) ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $sa_fact['neighbors'] ) ) : ?>
							<div class="bg-surface p-3 rounded-xl border border-line/70">
								<dt class="text-muted text-xs mb-0.5">استان‌های همسایه</dt>
								<dd class="font-bold text-ink"><?php echo esc_html( (string) $sa_fact['neighbors'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				</div>
			</div>
		</section>

		<section id="counties-section" aria-labelledby="counties-heading" class="scroll-mt-32 space-y-6">
			<div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
				<div>
					<span class="text-xs font-bold text-ochre uppercase tracking-wider">کاوش جغرافیایی</span>
					<h2 id="counties-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">
						<?php echo esc_html( 'شهرستان‌های ' . get_the_title() ); ?>
					</h2>
					<p class="text-sm text-muted mt-1">برای مشاهده راهنمای کامل سفر، جاذبه‌ها و تجربه‌های مسافرها، شهرستان مورد نظرتان را انتخاب یا فیلتر کنید.</p>
				</div>
				<div class="text-xs text-muted bg-surface px-3 py-2 rounded-xl border border-line self-start md:self-auto">
					نمایش <strong id="visible-count" class="text-petrol font-bold"><?php echo esc_html( sa_2026_num( count( $sa_counties ) ) ); ?></strong> شهرستان منتشرشده
				</div>
			</div>

			<?php if ( $sa_counties ) : ?>
				<div class="bg-surface p-4 rounded-2xl border border-line shadow-sm">
					<div class="relative">
						<label for="county-search" class="sr-only">جست‌وجو در شهرستان‌ها</label>
						<input type="text" id="county-search" data-sa-county-search
							placeholder="نام شهرستان یا جاذبه را بنویسید…"
							class="w-full pl-10 pr-4 py-2.5 text-sm bg-canvas border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-petrol/30 focus:border-petrol transition-all">
						<svg class="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
						</svg>
					</div>
				</div>

				<div id="counties-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
					<?php foreach ( $sa_counties as $sa_county ) : ?>
						<a href="<?php echo esc_url( $sa_county['url'] ); ?>" data-sa-relation
							data-sa-county-card
							data-sa-county-name="<?php echo esc_attr( $sa_county['title'] ); ?>"
							class="group bg-surface rounded-2xl border border-line p-4 hover:border-petrol hover:shadow-sm transition-all flex flex-col justify-between">
							<div class="space-y-2">
								<h3 class="text-base font-extrabold text-ink group-hover:text-petrol transition-colors"><?php echo esc_html( $sa_county['title'] ); ?></h3>
								<?php if ( $sa_county['summary'] ) : ?>
									<p class="text-xs text-slate-600 leading-relaxed line-clamp-2"><?php echo esc_html( $sa_county['summary'] ); ?></p>
								<?php endif; ?>
							</div>
							<span class="mt-4 pt-3 border-t border-line/70 text-xs font-bold text-petrol inline-flex items-center justify-between">
								<span>راهنمای سفر</span>
								<svg class="w-3.5 h-3.5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
								</svg>
							</span>
						</a>
					<?php endforeach; ?>
				</div>

				<div id="empty-state" class="hidden bg-surface rounded-2xl border border-line p-8 text-center space-y-3">
					<p class="text-base font-bold text-ink">شهرستانی با این مشخصات پیدا نشد</p>
					<p class="text-xs text-muted">عبارت جست‌وجو را تغییر دهید.</p>
					<button type="button" data-sa-reset-counties class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold bg-petrol text-white hover:bg-petrolDark transition-colors">
						نمایش همه شهرستان‌ها
					</button>
				</div>
			<?php else : ?>
				<p class="text-sm text-muted bg-surface border border-line rounded-2xl p-5">فهرست شهرستان‌های این استان در حال تکمیل است.</p>
			<?php endif; ?>
		</section>

		<section id="story-section" aria-labelledby="story-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-sm space-y-5">
			<div class="border-b border-line pb-4">
				<span class="text-xs font-bold text-ochre">روایت تحریریه</span>
				<h2 id="story-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-0.5">
					<?php echo esc_html( 'راهنمای کامل سفر به ' . get_the_title() ); ?>
				</h2>
			</div>
			<div class="sa-2026-prose">
				<?php sa_2026_relation_content( array_column( $sa_counties, 'url' ) ); ?>
			</div>
		</section>

		<?php
		$sa_route_posts = array();
		foreach ( (array) $sa_routes as $sa_route ) {
			if ( $sa_route instanceof WP_Post && 'publish' === $sa_route->post_status ) {
				$sa_route_posts[] = $sa_route;
			}
		}
		if ( $sa_route_posts ) :
			?>
			<section id="routes-section" aria-labelledby="routes-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-sm space-y-6">
				<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-line pb-4">
					<div>
						<span class="text-xs font-bold text-ochre">برنامه‌ریزی هوشمند</span>
						<h2 id="routes-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-0.5">کجا را اول ببینیم؟ مسیرهای پیشنهادی سفر در استان</h2>
					</div>
					<span class="text-xs text-muted">پیشنهاد تحریریه</span>
				</div>

				<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
					<?php foreach ( array_slice( $sa_route_posts, 0, 3 ) as $sa_route ) : ?>
						<article class="bg-canvas rounded-2xl border border-line p-5 flex flex-col justify-between hover:border-petrol/50 transition-colors">
							<div class="space-y-3">
								<div class="flex items-center justify-between gap-2">
									<span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-petrolSoft text-petrol">مسیر پیشنهادی</span>
									<span class="text-xs text-muted"><?php echo esc_html( get_the_date( '', $sa_route ) ); ?></span>
								</div>
								<h3 class="text-base font-bold text-ink">
									<a href="<?php echo esc_url( get_permalink( $sa_route ) ); ?>" class="hover:text-petrol transition-colors"><?php echo esc_html( get_the_title( $sa_route ) ); ?></a>
								</h3>
								<p class="text-xs text-slate-600 leading-relaxed"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $sa_route->post_content ), 28, '…' ) ); ?></p>
							</div>
							<div class="pt-4 mt-4 border-t border-line/80">
								<a href="<?php echo esc_url( get_permalink( $sa_route ) ); ?>" class="text-xs font-bold text-petrol hover:underline">مشاهده مسیر ←</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section id="climate-section" aria-labelledby="climate-heading" class="scroll-mt-32 grid grid-cols-1 lg:grid-cols-12 gap-6">
			<div class="lg:col-span-7 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-sm space-y-5">
				<div>
					<span class="text-xs font-bold text-ochre">تقویم سفر</span>
					<h2 id="climate-heading" class="text-xl font-extrabold text-ink mt-0.5">
						<?php echo esc_html( 'اقلیم و بهترین زمان سفر به ' . get_the_title() ); ?>
					</h2>
				</div>
				<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
					<?php if ( $sa_climate ) : ?>
						<div class="bg-canvas rounded-2xl border border-line p-4">
							<span class="text-xs font-bold text-petrol">اقلیم غالب</span>
							<p class="text-sm text-slate-600 mt-1"><?php echo esc_html( $sa_climate ); ?></p>
						</div>
					<?php endif; ?>
					<div class="bg-canvas rounded-2xl border border-line p-4">
						<span class="text-xs font-bold text-petrol">بهترین فصل سفر</span>
						<p class="text-sm text-slate-600 mt-1">بهار و اوایل پاییز برای بیشتر شهرستان‌ها؛ تابستان برای ارتفاعات و ییلاق‌ها.</p>
					</div>
				</div>
			</div>

			<div class="lg:col-span-5 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-sm space-y-4">
				<h2 class="text-base font-extrabold text-ink">۳ نکته کاربردی برای سفر در این استان</h2>
				<ul class="space-y-2.5 text-xs sm:text-sm text-slate-600 leading-relaxed list-disc pr-4">
					<li>برای سفر جاده‌ای، مسیرها را پیش از حرکت با وضعیت آب‌وهوا بررسی کنید؛ گردنه‌های کوهستانی در فصل سرد لغزنده می‌شوند.</li>
					<li>بازدید از جاذبه‌های تاریخی را در ساعات میانی روز و جاذبه‌های طبیعی را صبح‌ها برنامه‌ریزی کنید.</li>
					<li>اقامتگاه و بلیت جاذبه‌های پرطرفدار را در تعطیلات رسمی از قبل رزرو کنید.</li>
				</ul>
			</div>
		</section>

		<section aria-labelledby="cta-heading" class="bg-petrol text-white rounded-3xl p-6 sm:p-10 shadow-sm relative overflow-hidden">
			<div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-white/5 pointer-events-none"></div>
			<div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
				<div class="space-y-2.5 max-w-2xl">
					<span class="inline-block text-xs font-bold text-amber-300 uppercase tracking-wider">مشارکت در بخش شهر من</span>
					<h2 id="cta-heading" class="text-xl sm:text-2xl lg:text-3xl font-extrabold">تجربه‌ای از سفر در این استان دارید؟</h2>
					<p class="text-xs sm:text-sm text-slate-200 leading-relaxed">
						نکته تازه‌ای درباره جاده‌ها، اقامتگاه‌ها یا جاذبه‌های کمتر شناخته‌شده می‌دانید؟ تجربه خودتان را ثبت کنید تا پس از بررسی تحریریه منتشر شود.
					</p>
				</div>
				<div class="flex flex-wrap items-center gap-3 shrink-0">
					<button type="button" class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-ochre hover:bg-amber-600 text-white font-bold text-xs sm:text-sm shadow-md transition-all" data-sa-open-experience>
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
						</svg>
						<span>ارسال تجربه سفر من</span>
					</button>
				</div>
			</div>
		</section>

		<?php if ( $sa_neigh ) : ?>
			<section id="neighbors-section" aria-labelledby="neighbors-heading" class="scroll-mt-32 space-y-5">
				<div>
					<span class="text-xs font-bold text-ochre">لینک‌سازی افقی</span>
					<h2 id="neighbors-heading" class="text-xl font-extrabold text-ink mt-0.5">استان‌های همسایه</h2>
				</div>
				<div class="flex flex-wrap items-center gap-2 text-xs">
					<?php foreach ( $sa_neigh as $sa_neighbor ) : ?>
						<a href="<?php echo esc_url( $sa_neighbor['url'] ); ?>"
							class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface hover:bg-petrolSoft text-ink hover:text-petrol border border-line font-medium transition-colors">
							<?php echo esc_html( $sa_neighbor['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php sa_comments_2026_render(); ?>

	</main>

	<?php
endwhile;

get_footer();
