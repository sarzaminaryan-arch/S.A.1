<?php
/**
 * Front page — 2026 design refresh (petrol / ochre palette).
 *
 * Approved mockup: hero with instant search → one combined petrol card (site
 * telemetry + the cyber dashboard entry point) → four exploration pillars →
 * featured provinces → latest updates → about the encyclopedia. The previous
 * landing template is no longer used.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'front-page' ) ) {
	return;
}

get_header();

$sa_stats     = sa_2026_site_stats();
$sa_provinces = sa_2026_provinces( 8 );
$sa_latest    = sa_2026_latest( 6 );
$sa_archive_p = function_exists( 'sa_archive_url' ) ? sa_archive_url( 'province' ) : home_url( '/provinces/' );
$sa_archive_c = function_exists( 'sa_archive_url' ) ? sa_archive_url( 'city' ) : home_url( '/cities/' );
$sa_archive_a = function_exists( 'sa_archive_url' ) ? sa_archive_url( 'attraction' ) : home_url( '/attractions/' );
$sa_guide     = function_exists( 'sa_2026_guide_url' ) ? sa_2026_guide_url() : home_url( '/travel-guide/' );
$sa_contrib   = sa_2026_contrib_url();

/*
 * Owner 2026-11-22: «داشبورد جامع گردشگری ایران» (the cyber dashboard) used to be
 * reachable from the previous landing page; the 2026 front page dropped it. The
 * dedicated page still ships (template-dashboard.php, self-healed on upgrade), so
 * the home page links to it again — only when the page really exists and is
 * published, never to a guessed URL.
 */
$sa_dash = function_exists( 'sa_dashboard_page_url' ) ? sa_dashboard_page_url() : '';
$sa_dash_provinces = function_exists( 'sa_2026_count' ) ? (int) sa_2026_count( 'province' ) : 0;
$sa_dash_counties  = function_exists( 'sa_counties' ) ? count( (array) sa_counties() ) : 0;

/* Instant-search index: provinces, counties and the newest attractions. */
$sa_index = array();
foreach ( $sa_provinces as $sa_row ) {
	$sa_index[] = array(
		'title' => $sa_row['title'],
		'url'   => $sa_row['url'],
		'kind'  => 'استان‌ها',
	);
}
if ( function_exists( 'sa_counties' ) ) {
	foreach ( sa_counties() as $sa_row ) {
		$sa_county_url = function_exists( 'sa_county_permalink' ) ? sa_county_permalink( $sa_row['slug'] ) : '';
		if ( ! $sa_county_url ) {
			continue;
		}
		$sa_index[] = array(
			'title' => 'شهرستان ' . $sa_row['name'],
			'url'   => $sa_county_url,
			'kind'  => 'شهرستان‌ها',
		);
	}
}
foreach ( get_posts(
	array(
		'post_type'      => 'attraction',
		'post_status'    => 'publish',
		'posts_per_page' => 80,
		'no_found_rows'  => true,
	)
) as $sa_att ) {
	$sa_index[] = array(
		'title' => get_the_title( $sa_att ),
		'url'   => (string) get_permalink( $sa_att ),
		'kind'  => 'گنجینه دیدنی‌ها',
	);
}
?>

<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-14">

	<section aria-labelledby="hero-heading" class="w-full">
		<div class="w-full bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-card flex flex-col justify-between space-y-6 relative">
			<div class="space-y-4">
				<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-ochreSoft text-ochreDark text-xs font-bold border border-ochre/20">
					<span class="w-2 h-2 rounded-full bg-ochre"></span>
					<span>راهنمای هوشمند سفر و شناخت ایران</span>
				</div>

				<h1 id="hero-heading" class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-ink tracking-tight leading-[1.2]">
					ایران را دوباره کشف کن.
				</h1>

				<p class="text-muted text-sm sm:text-base leading-relaxed max-w-2xl">
					در <strong class="text-ink font-bold">سرزمین آریان</strong>، هر استان، شهرستان و دیدنی با داده‌های دقیق جغرافیایی، اقلیم فصلی، رتبه‌بندی مقصدها و روایت میدانی مسافرها کنار هم آمده تا سفری آگاهانه و ماندگار بسازید.
				</p>
			</div>

			<div class="space-y-3.5 relative">
				<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="relative" data-sa-search-form>
					<label for="hero-search-input" class="sr-only">جست‌وجوی استان، شهرستان، دیدنی یا راهنمای سفر</label>
					<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-canvas p-2 rounded-2xl border border-line focus-within:border-petrol focus-within:ring-4 focus-within:ring-petrol/10 transition-all">
						<span class="relative flex-1 flex items-center">
							<svg class="w-5 h-5 text-petrol mr-3 shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
							</svg>
							<input id="hero-search-input" type="search" name="s" autocomplete="off"
								placeholder="نام استان، شهرستان، دیدنی یا موضوع را بنویسید (مثلاً: تبریز، شیراز، کاشان، کویر...)"
								class="w-full bg-transparent px-3 py-2.5 text-sm sm:text-base text-ink placeholder:text-muted/80 focus:outline-none"
								data-sa-search-input>
						</span>
						<button type="submit" class="px-5 py-3 rounded-xl bg-petrol hover:bg-petrolDark text-white text-sm font-bold transition-colors shrink-0 shadow-xs">
							جست‌وجو در دانشنامه
						</button>
					</div>
				</form>

				<div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1" role="group" aria-label="فیلتر سریع بخش‌ها">
					<span class="text-xs text-muted font-semibold shrink-0 ml-1">فیلتر سریع:</span>
					<button type="button" data-sa-search-chip="all" class="search-chip is-active shrink-0 px-3 py-1.5 rounded-xl text-xs font-bold bg-petrol text-white transition-colors">همه بخش‌ها</button>
					<?php
					foreach ( array( 'استان‌ها', 'شهرستان‌ها', 'گنجینه دیدنی‌ها' ) as $sa_chip ) :
						?>
						<button type="button" data-sa-search-chip="<?php echo esc_attr( $sa_chip ); ?>" class="search-chip shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold bg-sand hover:bg-petrolSoft text-ink hover:text-petrol border border-line transition-colors">
							<?php echo esc_html( $sa_chip ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="bg-canvas rounded-2xl border border-line p-4 space-y-3">
					<div class="flex items-center justify-between text-xs">
						<span class="font-bold text-petrol" data-sa-search-heading>پیشنهادهای پرجستجو و سریع</span>
						<span class="text-muted font-mono" data-sa-search-count><?php echo esc_html( sa_2026_num( 6 ) . ' مورد' ); ?></span>
					</div>
					<div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" data-sa-search-results></div>
				</div>
			</div>
		</div>

	</section>

	<section id="dashboard-section" aria-labelledby="dashboard-heading" class="scroll-mt-24">
		<div class="bg-petrol text-white rounded-3xl p-6 sm:p-8 lg:p-10 shadow-floating relative overflow-hidden grid-pattern">
			<div class="absolute -left-20 -bottom-24 w-64 h-64 rounded-full bg-ochre/20 blur-2xl pointer-events-none"></div>

			<div class="relative z-10 space-y-6">
				<div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/15 pb-4">
					<div class="flex flex-wrap items-center gap-2">
						<span class="font-mono text-[11px] sm:text-xs text-amber-200 tracking-wide bg-white/10 px-3 py-1 rounded-lg">SYS.GEO.IRAN // CYBER DASHBOARD</span>
						<span class="text-[11px] font-bold text-white bg-ochre px-2.5 py-1 rounded-lg">شاخص جامع</span>
						<span class="text-[11px] font-semibold text-white/85 bg-white/10 px-2.5 py-1 rounded-lg">نقشهٔ تعاملی دومرحله‌ای</span>
					</div>
					<span class="text-[11px] text-white/75">تله‌متری داده‌ها: برخط</span>
				</div>

				<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
					<div class="lg:col-span-7 space-y-3">
						<span class="text-xs font-semibold text-amber-200 block">نبض داده‌های ایران‌شناسی در سرزمین آریان</span>
						<h2 id="dashboard-heading" class="text-2xl sm:text-3xl font-extrabold leading-snug">داشبورد جامع گردشگری ایران</h2>
						<p class="text-sm text-white/80 leading-relaxed">
							نقشهٔ جامع مقاصد، رتبه‌بندی و تجربه‌های میدانی در یک نگاه: همهٔ استان‌ها و شهرستان‌ها با شناسنامهٔ یکپارچه، اقلیم چهارفصل و پیوند مستقیم به دیدنی‌های شاخص. روی نقشه استان را انتخاب کنید تا شناسنامهٔ کامل و نمودارهایش باز شود، سپس با یک دکمه به نقشهٔ شهرستان‌های همان استان بروید.
						</p>
						<ul class="flex flex-wrap items-center gap-2 text-[11px] text-white/85 list-none m-0 p-0">
							<li class="bg-white/10 border border-white/15 rounded-lg px-2.5 py-1">مرحلهٔ ۱: نقشهٔ ایران ← شناسنامهٔ استان</li>
							<li class="bg-white/10 border border-white/15 rounded-lg px-2.5 py-1">مرحلهٔ ۲: نقشهٔ شهرستان‌ها ← شناسنامهٔ شهرستان</li>
						</ul>
					</div>

					<div class="lg:col-span-5 space-y-4">
						<dl class="grid grid-cols-2 gap-3 m-0">
							<?php foreach ( $sa_stats as $sa_stat ) : ?>
								<div class="bg-white/10 backdrop-blur-xs rounded-2xl p-3.5 border border-white/15">
									<dt class="text-[11px] text-white/75"><?php echo esc_html( $sa_stat['label'] ); ?></dt>
									<dd class="text-lg sm:text-xl font-extrabold text-white mt-0.5"><?php echo esc_html( $sa_stat['value'] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>

						<?php if ( '' !== $sa_dash ) : ?>
							<a href="<?php echo esc_url( $sa_dash ); ?>"
								class="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-xl bg-ochre hover:bg-ochreDark text-white text-sm font-bold transition-colors">
								<span>ورود به داشبورد تعاملی</span>
								<span aria-hidden="true">←</span>
							</a>
						<?php endif; ?>
						<a href="<?php echo esc_url( $sa_archive_p ); ?>" class="block text-center text-xs font-semibold text-white/85 hover:text-white hover:underline">
							یا کاوش در فهرست استان‌ها
						</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="pillars-section" aria-labelledby="pillars-heading" class="space-y-6 scroll-mt-24">
		<div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
			<div>
				<span class="text-xs font-bold text-ochreDark uppercase tracking-wider">معماری دانشنامه</span>
				<h2 id="pillars-heading" class="text-2xl sm:text-3xl font-extrabold text-ink mt-1">چهار مسیر اصلی کاوش در سرزمین آریان</h2>
				<p class="text-sm text-muted mt-1">از شناخت کلان استان‌ها و جزئیات شهرستان‌ها تا گنجینه دیدنی‌ها و آموزش عملی سفر.</p>
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
			<?php
			$sa_pillars = array(
				array(
					'no'    => '۰۱',
					'title' => 'استان‌های ایران',
					'sub'   => 'پهنه‌های جغرافیایی و اقلیمی',
					'head'  => 'شناخت استان‌ها، همسایه‌ها و مسیرهای پیشنهادی سفر',
					'text'  => 'بررسی جامع هر استان همراه با شناسنامه جغرافیایی، فهرست شهرستان‌ها، بهترین فصل سفر و ارتباط با استان‌های هم‌جوار.',
					'chip'  => 'bg-petrolSoft text-petrol',
					'url'   => $sa_archive_p,
					'cta'   => 'کاوش در فهرست استان‌های ایران',
					'count' => sa_2026_num( max( 31, sa_2026_count( 'province' ) ) ) . ' استان',
				),
				array(
					'no'    => '۰۲',
					'title' => 'شهرستان‌ها',
					'sub'   => 'شناسنامه، آب‌وهوا و وضعیت میدانی',
					'head'  => 'راهنمای کاربردی هر شهرستان برای سفر واقعی',
					'text'  => 'داده‌های جمعیتی و جغرافیایی، وضعیت جاده و پارکینگ، تجربهٔ مسافرها و جاذبه‌های شاخص هر شهرستان در یک صفحه.',
					'chip'  => 'bg-ochreSoft text-ochreDark',
					'url'   => $sa_archive_c,
					'cta'   => 'کاوش در فهرست شهرستان‌ها',
					'count' => sa_2026_num( function_exists( 'sa_counties' ) ? count( sa_counties() ) : 0 ) . ' شهرستان',
				),
				array(
					'no'    => '۰۳',
					'title' => 'گنجینه دیدنی‌ها',
					'sub'   => 'میراث جهانی، ملی و طبیعی',
					'head'  => 'روایت دیدنی‌ها با شناسنامه بازدید و مسیر دسترسی',
					'text'  => 'هر دیدنی با شناسنامه مکان، ساعت بازدید، مسیر دسترسی، نکات میدانی و دیدنی‌های نزدیک معرفی می‌شود.',
					'chip'  => 'bg-emerald-50 text-emerald-700',
					'url'   => $sa_archive_a,
					'cta'   => 'کاوش در گنجینه دیدنی‌ها',
					'count' => sa_2026_num( sa_2026_count( 'attraction' ) ) . ' دیدنی',
				),
				array(
					'no'    => '۰۴',
					'title' => 'آموزش سفر',
					'sub'   => 'راهنمای عملی و تجربهٔ میدانی',
					'head'  => 'آموزش سفر، کمپینگ و مهارت‌های جاده',
					'text'  => 'راهنماهای کاربردی برای برنامه‌ریزی، بسته‌بندی، کمپینگ ایمن و سفر جاده‌ای در ایران.',
					'chip'  => 'bg-sand text-ochreDark',
					'url'   => $sa_guide,
					'cta'   => 'ورود به آموزش سفر',
					'count' => sa_2026_num( function_exists( 'sa_guide_count' ) ? sa_guide_count() : sa_2026_count( 'post' ) ) . ' راهنما',
				),
			);
			foreach ( $sa_pillars as $sa_pillar ) :
				?>
				<article class="bg-surface rounded-3xl border border-line p-6 sm:p-7 shadow-card hover:border-petrol/50 transition-all flex flex-col justify-between space-y-5">
					<div class="space-y-3">
						<div class="flex items-center justify-between gap-2">
							<span class="font-mono text-xs font-extrabold px-3 py-1 rounded-xl <?php echo esc_attr( $sa_pillar['chip'] ); ?>">
								<?php echo esc_html( $sa_pillar['no'] . ' / ' . $sa_pillar['title'] ); ?>
							</span>
							<span class="text-xs text-muted font-medium"><?php echo esc_html( $sa_pillar['sub'] ); ?></span>
						</div>
						<h3 class="text-xl font-extrabold text-ink"><?php echo esc_html( $sa_pillar['head'] ); ?></h3>
						<p class="text-sm text-muted leading-relaxed"><?php echo esc_html( $sa_pillar['text'] ); ?></p>
					</div>

					<div class="space-y-4 pt-3 border-t border-line/80">
						<div class="flex flex-wrap items-center gap-2 text-xs">
							<span class="text-muted font-semibold ml-1">در دانشنامه:</span>
							<span class="px-2.5 py-1 rounded-lg bg-canvas text-petrol font-semibold border border-line"><?php echo esc_html( $sa_pillar['count'] ); ?></span>
						</div>
						<a href="<?php echo esc_url( $sa_pillar['url'] ); ?>"
							class="w-full inline-flex items-center justify-between px-4 py-3 rounded-2xl bg-canvas hover:bg-petrol text-petrol hover:text-white border border-line text-xs sm:text-sm font-bold transition-colors">
							<span><?php echo esc_html( $sa_pillar['cta'] ); ?></span>
							<span aria-hidden="true">←</span>
						</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section id="spotlight-section" aria-labelledby="spotlight-heading" class="space-y-6 scroll-mt-24">
		<div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
			<div>
				<span class="text-xs font-bold text-ochreDark uppercase tracking-wider">منتخب‌های دانشنامه</span>
				<h2 id="spotlight-heading" class="text-2xl sm:text-3xl font-extrabold text-ink mt-1">استان‌های شاخص برای شروع کاوش</h2>
				<p class="text-sm text-muted mt-1">هر استان با شناسنامه جغرافیایی، شهرستان‌ها و دیدنی‌های شاخص.</p>
			</div>
			<a href="<?php echo esc_url( $sa_archive_p ); ?>" class="text-xs font-bold text-petrol hover:underline shrink-0">مشاهده همه استان‌ها ←</a>
		</div>

		<?php if ( $sa_provinces ) : ?>
			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
				<?php foreach ( $sa_provinces as $sa_province ) : ?>
					<article class="sa-card-linked group bg-surface rounded-2xl border border-line overflow-hidden hover:border-petrol hover:shadow-card transition-all flex flex-col">
						<div class="aspect-[16/10] overflow-hidden bg-sand">
							<?php
							echo function_exists( 'sa_2026_cover_markup' )
								? sa_2026_cover_markup( $sa_province['id'], 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300', $sa_province['title'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								: '';
							?>
						</div>
						<div class="p-4 space-y-1.5">
							<strong class="block text-base font-extrabold text-ink group-hover:text-petrol transition-colors">
								<a href="<?php echo esc_url( $sa_province['url'] ); ?>" class="sa-stretched-link"><?php echo esc_html( $sa_province['title'] ); ?></a>
							</strong>
							<?php if ( $sa_province['counties'] ) : ?>
								<span class="block text-[11px] text-muted"><?php echo esc_html( sa_2026_num( $sa_province['counties'] ) . ' شهرستان' ); ?></span>
							<?php endif; ?>
							<span aria-hidden="true" class="inline-flex items-center gap-1 text-[11px] font-bold text-ochreDark pt-1">راهنمای استان <span aria-hidden="true">←</span></span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="text-sm text-muted bg-surface border border-line rounded-2xl p-5">فهرست استان‌ها در حال آماده‌سازی است.</p>
		<?php endif; ?>
	</section>

	<section id="latest-section" aria-labelledby="latest-heading" class="space-y-6 scroll-mt-24">
		<div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
			<div>
				<span class="text-xs font-bold text-ochreDark uppercase tracking-wider">تازه‌ترین‌ها</span>
				<h2 id="latest-heading" class="text-2xl sm:text-3xl font-extrabold text-ink mt-1">تازه‌ترین به‌روزرسانی‌های دانشنامه</h2>
			</div>
		</div>

		<?php if ( $sa_latest ) : ?>
			<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
				<?php foreach ( $sa_latest as $sa_post ) : ?>
					<article class="sa-card-linked group bg-surface rounded-2xl border border-line p-5 hover:border-petrol hover:shadow-card transition-all flex flex-col justify-between space-y-4">
						<div class="space-y-2">
							<?php
							$sa_badge = function_exists( 'sa_type_badge' ) ? wp_strip_all_tags( (string) sa_type_badge( get_post_type( $sa_post ) ) ) : '';
							if ( '' === trim( $sa_badge ) ) {
								$sa_type_object = get_post_type_object( get_post_type( $sa_post ) );
								$sa_badge       = $sa_type_object ? $sa_type_object->labels->singular_name : '';
							}
							?>
							<span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-lg bg-petrolSoft text-petrol">
								<?php echo esc_html( $sa_badge ); ?>
							</span>
							<h3 class="text-base font-extrabold text-ink group-hover:text-petrol transition-colors">
								<a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>" class="sa-stretched-link"><?php echo esc_html( get_the_title( $sa_post ) ); ?></a>
							</h3>
							<p class="text-xs text-muted leading-relaxed line-clamp-3"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $sa_post->post_content ), 24, '…' ) ); ?></p>
						</div>
						<div class="pt-3 border-t border-line/80 flex items-center justify-between text-[11px] text-muted">
							<span><?php echo esc_html( get_the_date( '', $sa_post ) ); ?></span>
							<span aria-hidden="true" class="font-bold text-petrol">مشاهده ←</span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="text-sm text-muted bg-surface border border-line rounded-2xl p-5">هنوز مطلبی منتشر نشده است.</p>
		<?php endif; ?>
	</section>

	<section id="about-section" aria-labelledby="about-heading" class="bg-surface rounded-3xl border border-line p-6 sm:p-8 lg:p-10 shadow-card space-y-6 scroll-mt-24">
		<div class="max-w-3xl space-y-3">
			<span class="text-xs font-bold text-ochreDark uppercase tracking-wider">درباره دانشنامه</span>
			<h2 id="about-heading" class="text-2xl sm:text-3xl font-extrabold text-ink">سرزمین آریان؛ دانشنامه سفر و شناخت ایران</h2>
			<p class="text-sm sm:text-base text-muted leading-relaxed">
				هدف ما ساختن مرجعی روشن و کاربردی است: داده‌های جغرافیایی دقیق، شناسنامهٔ هر مکان، اقلیم فصلی و تجربه‌های واقعی مسافرها — بدون ادعای تأییدنشده و بدون داده‌های کهنه به‌عنوان آمار امروز.
			</p>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
			<div class="bg-canvas rounded-2xl border border-line p-5 space-y-2">
				<h3 class="text-base font-extrabold text-ink">داده‌محور</h3>
				<p class="text-xs text-slate-600 leading-relaxed">هر عدد با منبع و سال مشخص؛ جمعیت و مساحت تقریبی و با ذکر مبنا.</p>
			</div>
			<div class="bg-canvas rounded-2xl border border-line p-5 space-y-2">
				<h3 class="text-base font-extrabold text-ink">تجربهٔ میدانی</h3>
				<p class="text-xs text-slate-600 leading-relaxed">وضعیت جاده، پارکینگ، آنتن و نکات واقعی که مسافرها گزارش کرده‌اند.</p>
			</div>
			<div class="bg-canvas rounded-2xl border border-line p-5 space-y-2">
				<h3 class="text-base font-extrabold text-ink">مشارکت مردمی</h3>
				<p class="text-xs text-slate-600 leading-relaxed">
					تجربه‌تان را در بخش «شهر من» ثبت کنید؛ پس از بررسی تحریریه منتشر می‌شود.
					<a href="<?php echo esc_url( $sa_contrib ); ?>" class="font-bold text-petrol hover:underline block pt-1">ثبت تجربه ←</a>
				</p>
			</div>
		</div>
	</section>

</main>

<script type="application/json" id="sa-search-index"><?php echo wp_json_encode( $sa_index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<?php
get_footer();
