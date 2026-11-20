<?php
/**
 * «آموزش سفر» listing (2026 design) — used by the `/travel-guide/` hub and by every
 * category archive of the guides (`category.php`).
 *
 * The owner's brief for the fourth main section: it must stop 404-ing and it must
 * look like the rest of the refreshed site. So the same hero/chips/card vocabulary
 * of «گنجینه دیدنی‌های ایران» is reused here, with the teal palette.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_ctx    = function_exists( 'sa_guide_context' ) ? sa_guide_context() : array();
$sa_posts  = (int) $GLOBALS['wp_query']->post_count;
$sa_found  = isset( $sa_ctx['found'] ) ? (int) $sa_ctx['found'] : 0;
$sa_topics = isset( $sa_ctx['topics'] ) ? (array) $sa_ctx['topics'] : array();
$sa_search = isset( $sa_ctx['search'] ) ? (string) $sa_ctx['search'] : '';
$sa_hub    = isset( $sa_ctx['hub_url'] ) ? (string) $sa_ctx['hub_url'] : home_url( '/travel-guide/' );
$sa_is_cat = ! empty( $sa_ctx['is_cat'] );
$sa_title  = isset( $sa_ctx['title'] ) ? (string) $sa_ctx['title'] : 'آموزش سفر';
$sa_lead   = isset( $sa_ctx['lead'] ) ? (string) $sa_ctx['lead'] : '';
$sa_badge  = isset( $sa_ctx['badge'] ) ? (string) $sa_ctx['badge'] : 'بخش آموزش سفر';
$sa_total  = isset( $sa_ctx['total'] ) ? (int) $sa_ctx['total'] : 0;

// Pagination: the hub owns `/travel-guide/page/N/`, categories use core's links.
$sa_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$sa_max   = (int) $GLOBALS['wp_query']->max_num_pages;
if ( $sa_paged < 1 ) {
	$sa_paged = 1;
}

$sa_paginate_args = array(
	'total'     => max( 1, $sa_max ),
	'current'   => $sa_paged,
	'mid_size'  => 1,
	'prev_text' => 'قبلی',
	'next_text' => 'بعدی',
	'type'      => 'array',
);
if ( ! empty( $sa_ctx['is_hub'] ) ) {
	$sa_paginate_args['base']   = trailingslashit( $sa_hub ) . 'page/%#%/';
	$sa_paginate_args['format'] = '';
} elseif ( '' !== $sa_search ) {
	$sa_paginate_args['add_args'] = array( 's' => rawurlencode( $sa_search ) );
}
?>

<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">

	<nav aria-label="مسیر صفحه" class="flex items-center justify-between gap-3 text-[11px] sm:text-xs text-muted border-b border-line pb-3">
		<ol class="flex items-center gap-1.5 flex-wrap">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-petrol transition-colors">خانه</a></li>
			<li aria-hidden="true">/</li>
			<?php if ( $sa_is_cat ) : ?>
				<li><a href="<?php echo esc_url( $sa_hub ); ?>" class="hover:text-petrol transition-colors">آموزش سفر</a></li>
				<li aria-hidden="true">/</li>
			<?php endif; ?>
			<li aria-current="page" class="text-ink font-semibold"><?php echo esc_html( $sa_title ); ?></li>
		</ol>
		<span class="font-mono text-[10px] text-slate-400 bg-canvas border border-line px-2 py-1 rounded-lg shrink-0 hidden sm:inline">SYS.ID // GUIDE-HUB</span>
	</nav>

	<section class="bg-petrol text-white rounded-3xl p-6 sm:p-8 lg:p-10 shadow-floating relative overflow-hidden grid-pattern space-y-5">
		<div class="absolute -left-20 -bottom-24 w-64 h-64 rounded-full bg-ochre/20 blur-3xl pointer-events-none"></div>
		<div class="relative z-10 space-y-3 max-w-3xl">
			<span class="text-xs font-bold text-amber-200 bg-white/10 px-3 py-1 rounded-full inline-block"><?php echo esc_html( $sa_badge ); ?></span>
			<h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight leading-[1.3]"><?php echo esc_html( $sa_title ); ?></h1>
			<p class="text-sm sm:text-base text-white/85 leading-relaxed"><?php echo esc_html( $sa_lead ); ?></p>
		</div>

		<div class="relative z-10 flex flex-wrap items-center gap-2 text-xs">
			<span class="px-3 py-1.5 rounded-xl bg-white/10 border border-white/15">
				<?php echo esc_html( sa_2026_num( $sa_found ) . ' راهنما در این بخش' ); ?>
			</span>
			<span class="px-3 py-1.5 rounded-xl bg-white/10 border border-white/15">
				<?php echo esc_html( sa_2026_num( $sa_total ) . ' راهنمای منتشرشده در دانشنامه' ); ?>
			</span>
			<?php if ( $sa_topics ) : ?>
				<a href="#guide-topics" class="px-3 py-1.5 rounded-xl bg-ochre hover:bg-ochreDark text-white font-bold transition-colors">
					<?php echo esc_html( sa_2026_num( count( $sa_topics ) ) . ' موضوع' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<form method="get" action="<?php echo esc_url( $sa_hub ); ?>" class="relative z-10 flex flex-col sm:flex-row gap-2 max-w-xl" role="search">
			<label for="sa-guide-search" class="screen-reader-text">جست‌وجو در آموزش سفر</label>
			<input type="search" id="sa-guide-search" name="s" value="<?php echo esc_attr( $sa_search ); ?>"
				placeholder="چه مهارتی را می‌خواهید یاد بگیرید؟ مثلاً کمک‌های اولیه"
				class="flex-1 bg-white/10 border border-white/20 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder:text-white/60 focus:outline-none focus:border-ochre focus:bg-white/15">
			<button type="submit" class="px-5 py-2.5 rounded-xl bg-ochre hover:bg-ochreDark text-white text-sm font-bold transition-colors">
				جست‌وجو
			</button>
		</form>
	</section>

	<?php if ( $sa_topics ) : ?>
		<section id="guide-topics" aria-labelledby="guide-topics-heading" class="bg-surface rounded-3xl border border-line p-5 sm:p-6 shadow-card space-y-3">
			<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
				<h2 id="guide-topics-heading" class="text-sm font-extrabold text-ink">موضوع‌های آموزش سفر</h2>
				<p class="text-xs text-muted">هر موضوع یک پروندهٔ کامل با راهنماهای مرتبط است.</p>
			</div>
			<div class="flex flex-wrap items-center gap-2 text-xs">
				<a href="<?php echo esc_url( $sa_hub ); ?>"
					class="px-3 py-1.5 rounded-xl <?php echo $sa_is_cat ? 'bg-surface border border-line text-ink hover:border-petrol transition-colors' : 'bg-petrol text-white font-bold'; ?>">
					همهٔ راهنماها
				</a>
				<?php foreach ( $sa_topics as $sa_topic ) : ?>
					<a href="<?php echo esc_url( $sa_topic['url'] ); ?>"
						class="px-3 py-1.5 rounded-xl <?php echo ! empty( $sa_topic['active'] ) ? 'bg-petrol text-white font-bold' : 'bg-surface border border-line text-ink hover:border-petrol hover:bg-petrolSoft/40 transition-colors'; ?>">
						<?php echo esc_html( $sa_topic['label'] . ' · ' . sa_2026_num( $sa_topic['count'] ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section aria-labelledby="guide-list-heading" class="space-y-5">
		<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
			<div class="space-y-1">
				<h2 id="guide-list-heading" class="text-lg font-extrabold text-ink">
					<?php echo esc_html( '' !== $sa_search ? 'نتیجهٔ جست‌وجو برای «' . $sa_search . '»' : 'راهنماهای این بخش' ); ?>
				</h2>
				<p class="text-xs text-muted">
					<?php
					echo esc_html(
						$sa_posts
							? sa_2026_num( $sa_found ) . ' راهنما · ' . ( $sa_max > 1 ? 'صفحهٔ ' . sa_2026_num( $sa_paged ) . ' از ' . sa_2026_num( $sa_max ) : 'همه در یک صفحه' )
							: 'موردی برای نمایش نیست'
					);
					?>
				</p>
			</div>
			<?php if ( '' !== $sa_search ) : ?>
				<a href="<?php echo esc_url( $sa_hub ); ?>"
					class="text-xs font-bold text-petrol hover:underline">پاک کردن جست‌وجو و دیدن همه</a>
			<?php endif; ?>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
				<?php
				while ( have_posts() ) :
					the_post();

					$sa_post_id   = (int) get_the_ID();
					$sa_terms     = get_the_category( $sa_post_id );
					$sa_term      = array();
					// Prefer the most specific category (a sub-category), and never the
					// WordPress placeholder.
					usort(
						(array) $sa_terms,
						static function ( $a, $b ) {
							return (int) $b->parent <=> (int) $a->parent;
						}
					);
					foreach ( (array) $sa_terms as $sa_candidate ) {
						if ( 'uncategorized' === $sa_candidate->slug ) {
							continue;
						}
						$sa_term = $sa_candidate;
						break;
					}
					$sa_label     = $sa_term ? (string) $sa_term->name : 'آموزش سفر';
					$sa_excerpt   = function_exists( 'sa_summary' ) ? sa_summary( get_post(), 26 ) : '';
					$sa_minutes   = function_exists( 'sa_guide_reading_time' ) ? sa_guide_reading_time( $sa_post_id ) : 0;
					?>
					<article class="group bg-surface rounded-2xl border border-line overflow-hidden hover:border-petrol hover:shadow-card transition-all flex flex-col">
						<a href="<?php the_permalink(); ?>" class="block aspect-[16/10] overflow-hidden bg-sand" tabindex="-1" aria-hidden="true">
							<?php
							echo function_exists( 'sa_2026_cover_markup' )
								? sa_2026_cover_markup( $sa_post_id, 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300', $sa_label ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								: '';
							?>
						</a>
						<div class="p-4 flex flex-col justify-between flex-1 gap-3">
							<div class="space-y-1.5">
								<span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-lg bg-petrolSoft text-petrol"><?php echo esc_html( $sa_label ); ?></span>
								<h3 class="text-base font-extrabold text-ink leading-snug">
									<a href="<?php the_permalink(); ?>" class="group-hover:text-petrol transition-colors"><?php the_title(); ?></a>
								</h3>
								<?php if ( $sa_excerpt ) : ?>
									<p class="text-xs text-muted leading-relaxed"><?php echo esc_html( $sa_excerpt ); ?></p>
								<?php endif; ?>
							</div>
							<div class="pt-2.5 border-t border-line/80 flex items-center justify-between gap-2 text-[11px] text-muted">
								<span class="truncate">
									<?php echo esc_html( get_the_date() ); ?>
									<?php if ( $sa_minutes ) : ?>
										· <?php echo esc_html( sa_2026_num( $sa_minutes ) . ' دقیقه مطالعه' ); ?>
									<?php endif; ?>
								</span>
								<span class="font-bold text-petrol shrink-0">مطالعه ←</span>
							</div>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<?php
			$sa_links = ( $sa_max > 1 ) ? paginate_links( $sa_paginate_args ) : array();
			if ( $sa_links ) :
				?>
				<nav aria-label="صفحه‌بندی آموزش سفر" class="sa-pagination flex items-center justify-center gap-2 pt-2">
					<div class="nav-links">
						<?php foreach ( $sa_links as $sa_link ) : ?>
							<?php echo wp_kses_post( $sa_link ); ?>
						<?php endforeach; ?>
					</div>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<div class="bg-surface rounded-3xl border border-line p-8 sm:p-10 text-center space-y-3">
				<h3 class="text-lg font-extrabold text-ink">
					<?php echo esc_html( '' !== $sa_search ? 'برای این جست‌وجو راهنمایی پیدا نشد' : 'این بخش هنوز راهنمایی ندارد' ); ?>
				</h3>
				<p class="text-sm text-muted">عبارت کوتاه‌تری امتحان کنید یا از فهرست کامل راهنماها شروع کنید.</p>
				<a href="<?php echo esc_url( $sa_hub ); ?>"
					class="inline-flex items-center px-4 py-2.5 rounded-xl bg-petrol hover:bg-petrolDark text-white text-xs font-bold transition-colors">
					نمایش همهٔ راهنماها
				</a>
			</div>
		<?php endif; ?>
	</section>

	<section class="bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-card flex flex-col sm:flex-row sm:items-center justify-between gap-4">
		<div class="space-y-1">
			<h2 class="text-lg font-extrabold text-ink">تجربهٔ میدانی‌تان را اضافه کنید</h2>
			<p class="text-xs sm:text-sm text-muted">مسیر، وضعیت جاده یا نکته‌ای که در این راهنماها نیست را ثبت کنید؛ پس از بررسی تحریریه با نام خودتان منتشر می‌شود.</p>
		</div>
		<div class="flex flex-wrap items-center gap-2 shrink-0">
			<button type="button" data-sa-open-experience
				class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-ochre hover:bg-ochreDark text-white text-sm font-bold transition-colors">
				<span>ثبت تجربه</span>
				<span aria-hidden="true">←</span>
			</button>
			<a href="<?php echo esc_url( function_exists( 'sa_archive_url' ) ? sa_archive_url( 'city' ) : home_url( '/city/' ) ); ?>"
				class="inline-flex items-center gap-2 px-4 py-3 rounded-2xl bg-canvas hover:bg-sand border border-line text-sm font-semibold text-ink transition-colors">
				فهرست شهرستان‌ها
			</a>
		</div>
	</section>

</main>
