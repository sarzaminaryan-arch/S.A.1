<?php
/**
 * Single county (city CPT) — 2026 design refresh.
 *
 * Replaces the previous "Cyber-Tech" county layout with the approved mockup:
 * breadcrumb bar → sticky jump bar → identity card (data table) → attraction
 * grid with category tabs → editorial body → field experience → seasons →
 * golden tips → contribution CTA → county chips.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'single-city' ) ) {
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$sa_id   = get_the_ID();
	$sa_data = function_exists( 'sa_county_profile_data' ) ? sa_county_profile_data( $sa_id ) : array();
	$sa_data = is_array( $sa_data ) ? $sa_data : array();
	$sa_get  = static function ( $key, $fallback = '' ) use ( $sa_data ) {
		return isset( $sa_data[ $key ] ) && '' !== $sa_data[ $key ] ? $sa_data[ $key ] : $fallback;
	};

	$sa_county_en = $sa_get( 'name_en' ) ? strtoupper( (string) $sa_get( 'name_en' ) ) : strtoupper( get_post_field( 'post_name', $sa_id ) );
	$sa_prov_name = $sa_get( 'province_name' );
	$sa_prov_url  = $sa_get( 'province_url', home_url( '/' ) );
	$sa_center    = $sa_get( 'center_fa' ) ? $sa_get( 'center_fa' ) : $sa_get( 'main_city' );
	$sa_faq       = function_exists( 'sa_get_valid_faq' ) ? sa_get_valid_faq( $sa_id ) : array();
	$sa_tips      = $sa_get( 'local_tips', array() );
	$sa_places    = $sa_get( 'extra_places', array() );
	$sa_seasons   = $sa_get( 'seasons', array() );
	$sa_access    = $sa_get( 'access_facts', array() );
	$sa_neighbors = $sa_get( 'neighbors', array() );
	$sa_attracts  = function_exists( 'sa_county_profile_attractions' ) ? sa_county_profile_attractions( $sa_id, 8 ) : array();
	$sa_siblings  = function_exists( 'sa_county_profile_sibling_cities' ) ? sa_county_profile_sibling_cities( $sa_id, 8 ) : array();
	$sa_updated   = $sa_get( 'field_update_note' );

	$sa_tip_groups = array(
		'warning'   => array(
			'title' => 'هشدارها و ساعت‌های کاری',
			'box'   => 'bg-amber-50/80 border-amber-200',
			'head'  => 'text-amber-900',
			'badge' => 'bg-amber-500',
			'body'  => 'text-amber-950/90',
			'mark'  => '!',
		),
		'logistics' => array(
			'title' => 'نکات کاربردی گشت‌وگذار',
			'box'   => 'bg-petrolSoft/80 border-petrol/20',
			'head'  => 'text-petrol',
			'badge' => 'bg-petrol',
			'body'  => 'text-slate-700',
			'mark'  => '✓',
		),
		'culture'   => array(
			'title' => 'فرهنگ، خوراکی‌ها و سوغات',
			'box'   => 'bg-emerald-50/80 border-emerald-200',
			'head'  => 'text-emerald-900',
			'badge' => 'bg-emerald-600',
			'body'  => 'text-emerald-950/90',
			'mark'  => '★',
		),
	);
	?>

	<div class="bg-surface border-b border-line">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-2 text-xs text-muted">
			<nav aria-label="مسیر صفحه" class="flex items-center gap-1.5 flex-wrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">خانه</a>
				<span aria-hidden="true">›</span>
				<a href="<?php echo esc_url( function_exists( 'sa_archive_url' ) ? sa_archive_url( 'province' ) : home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">استان‌ها</a>
				<span aria-hidden="true">›</span>
				<?php if ( $sa_prov_name ) : ?>
					<a href="<?php echo esc_url( $sa_prov_url ); ?>" class="hover:text-petrol transition-colors"><?php echo esc_html( $sa_prov_name ); ?></a>
					<span aria-hidden="true">›</span>
				<?php endif; ?>
				<span class="text-ink font-bold" aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
			</nav>
			<span class="hidden sm:inline-block font-mono text-[11px] text-petrol bg-petrolSoft px-2.5 py-0.5 rounded-md font-semibold">
				SYS.ID // CTY-<?php echo esc_html( $sa_county_en ); ?>
			</span>
		</div>
	</div>

	<div class="sticky top-16 z-40 bg-canvas/95 backdrop-blur-md border-b border-line shadow-xs">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<nav aria-label="بخش‌های صفحه شهرستان" class="flex items-center gap-1.5 sm:gap-2 py-2.5 overflow-x-auto no-scrollbar text-xs font-semibold">
				<span class="text-muted ml-1 shrink-0 hidden sm:inline">پرش سریع:</span>
				<a href="#identity-section" class="jump-link is-active shrink-0 px-3 py-1.5 rounded-lg bg-petrol text-white transition-colors">شناسنامه</a>
				<a href="#attractions-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">جاذبه‌های دیدنی</a>
				<a href="#story-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">راهنمای سفر</a>
				<?php if ( $sa_access ) : ?>
					<a href="#field-experience-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">تجربه مسافرها</a>
				<?php endif; ?>
				<?php if ( $sa_seasons ) : ?>
					<a href="#weather-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">آب‌وهوا</a>
				<?php endif; ?>
				<?php if ( $sa_tips ) : ?>
					<a href="#golden-tips-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">نکات سفر</a>
				<?php endif; ?>
				<?php if ( $sa_faq ) : ?>
					<a href="#faq-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">پرسش‌ها</a>
				<?php endif; ?>
				<?php if ( $sa_siblings ) : ?>
					<a href="#neighbor-counties-section" class="jump-link shrink-0 px-3 py-1.5 rounded-lg bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">شهرستان‌های هم‌استانی</a>
				<?php endif; ?>
			</nav>
		</div>
	</div>

	<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-12">

		<section id="identity-section" aria-labelledby="county-title" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-sm relative overflow-hidden">
			<div class="absolute -left-20 -top-20 w-72 h-72 rounded-full bg-petrolSoft/60 pointer-events-none"></div>
			<div class="absolute -left-8 top-32 w-36 h-36 rounded-full bg-ochreSoft/70 pointer-events-none"></div>

			<div class="relative z-10 space-y-8">
				<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
					<div class="lg:col-span-7 space-y-4">
						<div class="flex flex-wrap items-center gap-2">
							<?php if ( $sa_prov_name ) : ?>
								<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-ochreSoft text-ochre text-xs font-bold">
									<span class="w-1.5 h-1.5 rounded-full bg-ochre"></span>
									<?php echo esc_html( 'شهرستان در ' . $sa_prov_name ); ?>
								</span>
							<?php endif; ?>
							<span class="font-mono text-xs text-muted bg-slate-100 px-2.5 py-1 rounded-full">
								SYS.ID // CTY-<?php echo esc_html( $sa_county_en ); ?>
							</span>
						</div>

						<h1 id="county-title" class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-ink leading-tight">
							<?php the_title(); ?>
						</h1>

						<div class="sa-2026-prose text-slate-600 text-sm sm:text-base leading-relaxed">
							<?php
							$sa_lead = $sa_get( 'history_now' );
							if ( ! $sa_lead && function_exists( 'sa_summary' ) ) {
								$sa_lead = sa_summary( get_post(), 60 );
							}
							echo wp_kses_post( $sa_lead );
							?>
						</div>

						<?php if ( $sa_neighbors ) : ?>
							<div class="pt-3 border-t border-line/80 space-y-2.5">
								<div class="flex items-center gap-2 text-xs font-bold text-ink">
									<svg class="w-4 h-4 text-ochre" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
									</svg>
									<span><?php echo esc_html( 'همسایه‌های جغرافیایی ' . get_the_title() . ':' ); ?></span>
								</div>
								<div class="flex flex-wrap items-center gap-2 text-xs">
									<?php
									$sa_nb_seen = array();
									foreach ( $sa_neighbors as $sa_nb ) :
										if ( empty( $sa_nb['label'] ) ) {
											continue;
										}
										$sa_nb_key = trim( str_replace( array( 'شهرستان', 'ي', 'ك', '‌' ), array( '', 'ی', 'ک', ' ' ), (string) $sa_nb['label'] ) );
										$sa_nb_key = preg_replace( '/\s+/u', ' ', $sa_nb_key );
										if ( '' === $sa_nb_key || isset( $sa_nb_seen[ $sa_nb_key ] ) ) {
											continue;
										}
										$sa_nb_seen[ $sa_nb_key ] = true;
										?>
										<a href="<?php echo esc_url( ! empty( $sa_nb['url'] ) ? $sa_nb['url'] : '#' ); ?>"
											class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-canvas hover:bg-petrolSoft text-ink hover:text-petrol border border-line font-medium transition-colors">
											<span class="text-muted"><?php echo esc_html( $sa_nb['direction'] ); ?>:</span>
											<strong class="font-bold"><?php echo esc_html( $sa_nb['label'] ); ?></strong>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<div class="lg:col-span-5 bg-canvas rounded-2xl border border-line p-5">
						<div class="flex items-center justify-between border-b border-line pb-3 mb-4 gap-2">
							<h2 class="text-sm font-bold text-petrol flex items-center gap-2">
								<svg class="w-4 h-4 text-ochre" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
								</svg>
								<span>شناسنامه داده‌محور شهرستان</span>
							</h2>
							<span class="text-[11px] text-muted shrink-0 hidden sm:block">
								<?php
								if ( function_exists( 'sa_facts_checked_note' ) ) {
									sa_facts_checked_note( $sa_id );
								}
								?>
							</span>
						</div>

						<dl class="space-y-2.5 text-xs sm:text-sm">
							<?php if ( $sa_prov_name ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">01 // استان</dt>
									<dd class="font-bold text-petrol"><a href="<?php echo esc_url( $sa_prov_url ); ?>" class="hover:underline"><?php echo esc_html( $sa_prov_name ); ?></a></dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_center ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">02 // مرکز شهرستان</dt>
									<dd class="font-bold text-ink"><?php echo esc_html( $sa_center ); ?></dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'population_shown' ) || $sa_get( 'area_km2' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80">
									<div class="flex items-center justify-between gap-2">
										<dt class="text-muted text-xs font-mono">03 // جمعیت و مساحت</dt>
										<dd class="font-bold text-ink">
											<?php
											$sa_bits = array();
											if ( $sa_get( 'population_shown' ) ) {
												$sa_bits[] = sa_2026_num( $sa_get( 'population' ) ) . ' نفر';
											}
											if ( $sa_get( 'area_km2' ) ) {
												$sa_bits[] = sa_2026_num( $sa_get( 'area_km2' ) ) . ' کیلومتر مربع';
											}
											echo esc_html( implode( ' · ', $sa_bits ) );
											?>
										</dd>
									</div>
									<?php if ( $sa_get( 'population_year' ) ) : ?>
										<p class="text-[11px] text-muted mt-1">
											<?php echo esc_html( 'مبنای جمعیت: سرشماری رسمی ' . sa_2026_num( $sa_get( 'population_year' ) ) . '؛ عدد به نزدیک‌ترین هزار نفر گرد شده است.' ); ?>
										</p>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'lat' ) && $sa_get( 'lng' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2 flex-wrap">
									<dt class="text-muted text-xs font-mono">04 // مختصات و ارتفاع</dt>
									<dd class="font-bold text-ink text-left">
										<span class="font-mono text-xs text-petrol"><?php echo esc_html( number_format_i18n( (float) $sa_get( 'lat' ), 4 ) . '° N, ' . number_format_i18n( (float) $sa_get( 'lng' ), 4 ) . '° E' ); ?></span>
										<?php if ( $sa_get( 'elevation_m' ) ) : ?>
											<span class="text-xs font-normal text-muted mr-1.5"><?php echo esc_html( '| ارتفاع ' . sa_2026_num( $sa_get( 'elevation_m' ) ) . ' متر' ); ?></span>
										<?php endif; ?>
									</dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'language' ) || $sa_get( 'economy' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">05 // زبان و معیشت غالب</dt>
									<dd class="font-bold text-ink text-left">
										<?php echo esc_html( trim( $sa_get( 'language' ) . ( $sa_get( 'language' ) && $sa_get( 'economy' ) ? ' · ' : '' ) . $sa_get( 'economy' ) ) ); ?>
									</dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'plate_code' ) || $sa_get( 'phone_code' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">06 // پلاک و پیش‌شماره</dt>
									<dd class="font-bold text-ink font-mono text-left">
										<?php
										$sa_plate = array();
										if ( $sa_get( 'plate_code' ) ) {
											$sa_plate[] = 'پلاک ' . $sa_get( 'plate_code' );
										}
										if ( $sa_get( 'phone_code' ) ) {
											$sa_plate[] = 'پیش‌شماره ' . str_pad( (string) $sa_get( 'phone_code' ), 3, '0', STR_PAD_LEFT );
										}
										echo esc_html( implode( ' | ', $sa_plate ) );
										?>
									</dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'climate' ) || $sa_get( 'best_season' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">07 // اقلیم و بهترین فصل</dt>
									<dd class="font-bold text-ink text-left">
										<?php echo esc_html( trim( $sa_get( 'climate' ) . ( $sa_get( 'climate' ) && $sa_get( 'best_season' ) ? ' · ' : '' ) . $sa_get( 'best_season' ) ) ); ?>
									</dd>
								</div>
							<?php endif; ?>

							<?php if ( $sa_get( 'distance_km' ) ) : ?>
								<div class="bg-surface p-3 rounded-xl border border-line/80 flex items-center justify-between gap-2">
									<dt class="text-muted text-xs font-mono">08 // فاصله تا مرکز استان</dt>
									<dd class="font-bold text-ink"><?php echo esc_html( sa_2026_num( $sa_get( 'distance_km' ) ) . ' کیلومتر' ); ?></dd>
								</div>
							<?php endif; ?>
						</dl>
					</div>
				</div>
			</div>
		</section>

		<section id="attractions-section" aria-labelledby="attractions-heading" class="scroll-mt-32 space-y-6">
			<div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
				<div>
					<span class="text-xs font-bold text-ochre">دیدنی‌های منتخب و پیرامون</span>
					<h2 id="attractions-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">
						<?php echo esc_html( 'جاذبه‌های شاخص ' . get_the_title() ); ?>
					</h2>
					<p class="text-sm text-muted mt-1">بناهای تاریخی، تفرجگاه‌ها و ییلاق‌های محبوب مسافرها:</p>
				</div>

				<?php if ( $sa_attracts ) : ?>
					<div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1 md:pb-0" role="tablist" aria-label="فیلتر دسته‌بندی جاذبه‌ها">
						<button type="button" data-sa-attr-filter="all" class="attr-tab is-active shrink-0 px-3.5 py-2 rounded-xl text-xs font-bold bg-petrol text-white transition-colors">
							<?php echo esc_html( 'همه جاذبه‌ها (' . sa_2026_num( count( $sa_attracts ) ) . ')' ); ?>
						</button>
						<?php
						$sa_types = array();
						foreach ( $sa_attracts as $sa_row ) {
							if ( ! empty( $sa_row['type'] ) && ! isset( $sa_types[ $sa_row['type'] ] ) ) {
								$sa_types[ $sa_row['type'] ] = 0;
							}
							if ( ! empty( $sa_row['type'] ) ) {
								$sa_types[ $sa_row['type'] ]++;
							}
						}
						foreach ( $sa_types as $sa_type_name => $sa_type_count ) :
							$sa_key = sanitize_title( $sa_type_name );
							?>
							<button type="button" data-sa-attr-filter="<?php echo esc_attr( $sa_key ); ?>" class="attr-tab shrink-0 px-3.5 py-2 rounded-xl text-xs font-semibold bg-surface text-slate-700 hover:bg-petrolSoft hover:text-petrol border border-line transition-colors">
								<?php echo esc_html( $sa_type_name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $sa_attracts ) : ?>
				<div id="attractions-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
					<?php
					foreach ( $sa_attracts as $sa_row ) :
						$sa_row_key = ! empty( $sa_row['type'] ) ? sanitize_title( $sa_row['type'] ) : 'all';
						$sa_is_hist  = (bool) preg_match( '/تاریخ|زیارتی|heritage/u', (string) $sa_row['type'] );
						$sa_is_nat   = (bool) preg_match( '/طبیعت|بکر/u', (string) $sa_row['type'] );
						$sa_chip     = $sa_is_hist ? 'bg-ochreSoft text-ochre' : ( $sa_is_nat ? 'bg-emerald-50 text-emerald-700' : 'bg-petrolSoft text-petrol' );
						$sa_url      = ! empty( $sa_row['url'] ) ? $sa_row['url'] : '';
						?>
						<article data-sa-attr-card="<?php echo esc_attr( $sa_row_key ); ?>"
							class="group bg-surface rounded-2xl border <?php echo $sa_url ? 'border-petrol/35 ring-1 ring-petrol/10' : 'border-line'; ?> p-5 flex flex-col justify-between hover:border-petrol hover:shadow-md transition-all duration-200">
							<div class="space-y-3">
								<div class="flex items-center justify-between gap-2">
									<span class="text-[11px] font-semibold px-2.5 py-1 rounded-lg <?php echo esc_attr( $sa_chip ); ?>">
										<?php echo esc_html( $sa_row['type'] ? $sa_row['type'] : 'جاذبه دیدنی' ); ?>
									</span>
								</div>

								<h3 class="text-base sm:text-lg font-extrabold text-ink group-hover:text-petrol transition-colors leading-snug">
									<?php if ( $sa_url ) : ?>
										<a href="<?php echo esc_url( $sa_url ); ?>" class="focus:outline-none"><?php echo esc_html( $sa_row['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $sa_row['name'] ); ?>
									<?php endif; ?>
								</h3>

								<p class="text-xs text-slate-600 leading-relaxed">
									<?php
									$sa_desc = ! empty( $sa_row['note'] ) ? $sa_row['note'] : ( ! empty( $sa_row['en'] ) ? $sa_row['en'] : 'از جاذبه‌های شناخته‌شده و قابل بازدید این شهرستان.' );
									echo esc_html( $sa_desc );
									?>
								</p>
								<?php if ( ! empty( $sa_row['distance'] ) ) : ?>
									<p class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500">
										<svg class="w-3.5 h-3.5 text-petrol" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
										</svg>
										<span><?php echo esc_html( $sa_row['distance'] ); ?></span>
									</p>
								<?php endif; ?>
							</div>

							<?php if ( $sa_url ) : ?>
								<div class="mt-4 pt-3.5 border-t border-line/70">
									<a href="<?php echo esc_url( $sa_url ); ?>" class="w-full inline-flex items-center justify-between px-3 py-2 rounded-xl bg-canvas group-hover:bg-petrol text-petrol group-hover:text-white text-xs font-bold transition-colors">
										<span>مشاهده راهنمای بازدید</span>
										<svg class="w-3.5 h-3.5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
										</svg>
									</a>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="text-sm text-muted bg-surface border border-line rounded-2xl p-5">
					فهرست جاذبه‌های این شهرستان در حال تکمیل است.
				</p>
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
				<?php the_content(); ?>
			</div>
		</section>

		<?php if ( $sa_access ) : ?>
			<section id="field-experience-section" aria-labelledby="field-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-sm space-y-6">
				<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-line pb-4">
					<div>
						<span class="text-xs font-bold text-ochre">گزارش میدانی و واقعی</span>
						<h2 id="field-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-0.5">از تجربهٔ مسافرها و وضعیت میدانی</h2>
					</div>
					<?php if ( $sa_updated ) : ?>
						<span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full self-start sm:self-auto">
							<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
							<?php echo esc_html( $sa_updated ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
					<?php foreach ( $sa_access as $sa_label => $sa_text ) : ?>
						<article class="bg-canvas rounded-2xl border border-line p-5 space-y-2.5">
							<div class="flex items-center justify-between gap-3">
								<h3 class="text-base font-bold text-ink flex items-center gap-2">
									<span class="w-8 h-8 rounded-xl bg-petrolSoft text-petrol flex items-center justify-center shrink-0">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
										</svg>
									</span>
									<?php echo esc_html( $sa_label ); ?>
								</h3>
							</div>
							<p class="text-xs sm:text-sm text-slate-600 leading-relaxed"><?php echo esc_html( $sa_text ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $sa_seasons ) : ?>
			<section id="weather-section" aria-labelledby="weather-heading" class="scroll-mt-32 space-y-6">
				<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
					<div>
						<span class="text-xs font-bold text-ochre">تقویم سفر</span>
						<h2 id="weather-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">اقلیم و آب‌وهوا، فصل‌به‌فصل</h2>
					</div>
					<p class="text-xs text-muted"><?php echo esc_html( $sa_get( 'climate' ) ); ?></p>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
					<?php
					$sa_season_style = array(
						'بهار'    => array( 'border-emerald-500/40', 'text-emerald-800', 'bg-emerald-600' ),
						'تابستان' => array( 'border-amber-500/40', 'text-amber-800', 'bg-amber-500' ),
						'پاییز'   => array( 'border-ochre/40', 'text-ochreDark', 'bg-ochre' ),
						'زمستان'  => array( 'border-sky-500/40', 'text-sky-800', 'bg-sky-600' ),
					);
					foreach ( $sa_seasons as $sa_season => $sa_text ) :
						$sa_style = isset( $sa_season_style[ $sa_season ] ) ? $sa_season_style[ $sa_season ] : $sa_season_style['بهار'];
						?>
						<article class="bg-surface rounded-2xl border-2 <?php echo esc_attr( $sa_style[0] ); ?> p-5 shadow-sm flex flex-col justify-between space-y-4">
							<div class="space-y-2.5">
								<div class="flex items-center justify-between">
									<span class="text-base font-extrabold <?php echo esc_attr( $sa_style[1] ); ?>"><?php echo esc_html( $sa_season ); ?></span>
									<span class="text-[11px] font-bold text-white px-2.5 py-0.5 rounded-full <?php echo esc_attr( $sa_style[2] ); ?>">فصل سفر</span>
								</div>
								<p class="text-xs text-slate-600 leading-relaxed"><?php echo esc_html( $sa_text ); ?></p>
							</div>
							<div class="pt-3 border-t border-line space-y-1.5 text-xs">
								<div class="flex justify-between gap-2">
									<span class="text-muted">بهترین فصل سفر:</span>
									<span class="font-bold text-ink"><?php echo esc_html( $sa_get( 'best_season' ) ); ?></span>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $sa_tips ) : ?>
			<section id="golden-tips-section" aria-labelledby="tips-heading" class="scroll-mt-32 space-y-6">
				<div>
					<span class="text-xs font-bold text-ochre">راهنمای کاربردی</span>
					<h2 id="tips-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-1">
						<?php echo esc_html( 'نکته‌های طلایی قبل از سفر به ' . get_the_title() ); ?>
					</h2>
				</div>

				<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
					<?php foreach ( $sa_tip_groups as $sa_cat => $sa_group ) : ?>
						<?php
						$sa_rows = array_values(
							array_filter(
								$sa_tips,
								static function ( $row ) use ( $sa_cat ) {
									return isset( $row['category'] ) && $row['category'] === $sa_cat;
								}
							)
						);
						if ( ! $sa_rows ) {
							continue;
						}
						?>
						<div class="<?php echo esc_attr( $sa_group['box'] ); ?> rounded-3xl border p-6 space-y-3">
							<div class="flex items-center gap-2.5 <?php echo esc_attr( $sa_group['head'] ); ?> font-extrabold text-base">
								<span class="w-8 h-8 rounded-xl text-white flex items-center justify-center shrink-0 <?php echo esc_attr( $sa_group['badge'] ); ?>"><?php echo esc_html( $sa_group['mark'] ); ?></span>
								<h3><?php echo esc_html( $sa_group['title'] ); ?></h3>
							</div>
							<ul class="space-y-2.5 text-xs sm:text-sm <?php echo esc_attr( $sa_group['body'] ); ?> leading-relaxed list-disc pr-4">
								<?php foreach ( $sa_rows as $sa_tip ) : ?>
									<li>
										<strong><?php echo esc_html( $sa_tip['title'] ); ?>:</strong>
										<?php echo esc_html( $sa_tip['text'] ); ?>
										<?php if ( ! empty( $sa_tip['evidence'] ) ) : ?>
											<span class="text-[11px] text-muted"><?php echo esc_html( ' — ' . $sa_tip['evidence'] ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $sa_places ) : ?>
					<div class="bg-surface rounded-3xl border border-line p-6 space-y-4">
						<h3 class="text-base font-extrabold text-ink">جاهای دیگری که مسافرها می‌روند</h3>
						<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
							<?php foreach ( $sa_places as $sa_place ) : ?>
								<div class="bg-canvas rounded-2xl border border-line p-4 space-y-1.5">
									<div class="flex items-center justify-between gap-2">
										<h4 class="text-sm font-extrabold text-ink"><?php echo esc_html( $sa_place['name'] ); ?></h4>
										<?php if ( $sa_place['kind'] ) : ?>
											<span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-petrolSoft text-petrol"><?php echo esc_html( $sa_place['kind'] ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $sa_place['access'] ) : ?>
										<p class="text-[11px] text-muted"><?php echo esc_html( $sa_place['access'] ); ?></p>
									<?php endif; ?>
									<?php if ( $sa_place['note'] ) : ?>
										<p class="text-xs text-slate-600 leading-relaxed"><?php echo esc_html( $sa_place['note'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $sa_place['url'] ) ) : ?>
										<a href="<?php echo esc_url( $sa_place['url'] ); ?>" class="inline-flex items-center gap-1 text-[11px] font-bold text-petrol hover:underline">پیوند بیشتر ←</a>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $sa_faq ) : ?>
			<section id="faq-section" aria-labelledby="faq-heading" class="scroll-mt-32 bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-sm space-y-5">
				<div class="border-b border-line pb-4">
					<span class="text-xs font-bold text-ochre">پرسش و پاسخ</span>
					<h2 id="faq-heading" class="text-xl sm:text-2xl font-extrabold text-ink mt-0.5">پرسش‌های رایج درباره <?php echo esc_html( get_the_title() ); ?></h2>
				</div>
				<div class="space-y-3">
					<?php foreach ( $sa_faq as $sa_index => $sa_qa ) : ?>
						<details class="group bg-canvas rounded-2xl border border-line p-4" <?php echo 0 === $sa_index ? 'open' : ''; ?>>
							<summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-bold text-ink">
								<span><?php echo esc_html( $sa_qa['q'] ); ?></span>
								<svg class="w-4 h-4 text-ochre shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
								</svg>
							</summary>
							<div class="sa-2026-prose text-xs sm:text-sm text-slate-600 pt-3"><?php echo wp_kses_post( wpautop( $sa_qa['a'] ) ); ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section aria-labelledby="cta-heading" class="bg-petrol text-white rounded-3xl p-6 sm:p-10 shadow-sm relative overflow-hidden">
			<div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-white/5 pointer-events-none"></div>
			<div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
				<div class="space-y-2.5 max-w-2xl">
					<span class="inline-block text-xs font-bold text-amber-300 uppercase tracking-wider">مشارکت در بخش شهر من</span>
					<h2 id="cta-heading" class="text-xl sm:text-2xl lg:text-3xl font-extrabold">
						<?php echo esc_html( 'تجربه‌ای از سفر یا زندگی در ' . get_the_title() . ' دارید؟' ); ?>
					</h2>
					<p class="text-xs sm:text-sm text-slate-200 leading-relaxed">
						نکته تازه‌ای درباره مسیرهای دسترسی، کافه‌ها، رستوران‌های محلی یا وضعیت جاده‌ها می‌دانید؟ تجربه خودتان را ثبت کنید تا پس از بررسی تحریریه، به نام شما در دانشنامه منتشر شود.
					</p>
				</div>

				<div class="flex flex-wrap items-center gap-3 shrink-0">
					<button type="button" class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-ochre hover:bg-amber-600 text-white font-bold text-xs sm:text-sm shadow-md transition-all" data-sa-open-experience>
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
						</svg>
						<span>ارسال تجربه سفر من</span>
					</button>
					<?php if ( $sa_prov_name ) : ?>
						<a href="<?php echo esc_url( $sa_prov_url ); ?>" class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs sm:text-sm border border-white/20 transition-colors">
							<span><?php echo esc_html( 'بازگشت به ' . $sa_prov_name ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<?php if ( $sa_siblings ) : ?>
			<section id="neighbor-counties-section" aria-labelledby="related-counties-heading" class="scroll-mt-32 space-y-5">
				<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
					<div>
						<span class="text-xs font-bold text-ochre"><?php echo esc_html( 'ادامه کاوش در ' . ( $sa_prov_name ? $sa_prov_name : 'این استان' ) ); ?></span>
						<h2 id="related-counties-heading" class="text-xl font-extrabold text-ink mt-0.5">شهرستان‌های هم‌استانی و مقصدهای پیشنهادی بعدی</h2>
					</div>
					<?php if ( $sa_prov_url ) : ?>
						<a href="<?php echo esc_url( $sa_prov_url ); ?>" class="text-xs font-bold text-petrol hover:underline">مشاهده همه شهرستان‌های استان ←</a>
					<?php endif; ?>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
					<?php foreach ( $sa_siblings as $sa_sibling ) : ?>
						<a href="<?php echo esc_url( $sa_sibling['url'] ); ?>" class="group bg-surface rounded-2xl border border-line p-4 hover:border-petrol hover:shadow-sm transition-all">
							<h3 class="text-base font-extrabold text-ink group-hover:text-petrol transition-colors"><?php echo esc_html( $sa_sibling['label'] ); ?></h3>
							<p class="text-xs text-slate-600 mt-1">شهرستان‌های هم‌استانی و مقصدهای نزدیک را ببینید.</p>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

	</main>

	<?php
endwhile;

get_footer();
