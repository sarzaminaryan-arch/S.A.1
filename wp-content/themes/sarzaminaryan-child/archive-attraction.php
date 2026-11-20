<?php
/**
 * گنجینه دیدنی‌های ایران — attractions hub (2026 design refresh).
 *
 * Owner brief (29 آبان ۱۴۰۵): the attractions mother page lists every published
 * attraction and filters them by province, county and type (تاریخی / طبیعی /
 * مذهبی / فرهنگی), with the forest palette of the treasure design.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sa_filters   = sa_2026_treasure_filters();
$sa_types     = sa_2026_treasure_types();
$sa_provinces = sa_2026_treasure_provinces();
$sa_counties  = function_exists( 'sa_counties' ) ? sa_counties() : array();
$sa_total     = (int) wp_count_posts( 'attraction' )->publish;
$sa_has_filt  = '' !== $sa_filters['province'] || '' !== $sa_filters['county'] || '' !== $sa_filters['type'];
$sa_found     = (int) $GLOBALS['wp_query']->found_posts;
$sa_prov_map  = array();
foreach ( $sa_provinces as $sa_prov_row ) {
	$sa_prov_map[ $sa_prov_row['slug'] ] = $sa_prov_row['label'];
}
$sa_type_map  = array();
foreach ( $sa_types as $sa_type_row ) {
	$sa_type_map[ $sa_type_row['slug'] ] = $sa_type_row['label'];
}
$sa_county_map = array();
foreach ( $sa_counties as $sa_county_row ) {
	$sa_county_map[ $sa_county_row['slug'] ] = $sa_county_row['name'];
}
$sa_cat_map   = array(
	'historical' => 'تاریخی',
	'natural'    => 'طبیعی',
	'religious'  => 'مذهبی',
	'cultural'   => 'فرهنگی و بوم‌گردی',
);
?>

<main id="primary" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-10">

	<nav aria-label="مسیر صفحه" class="flex items-center justify-between gap-3 text-[11px] sm:text-xs text-muted border-b border-line pb-3">
		<ol class="flex items-center gap-1.5 flex-wrap">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-brand-forest transition-colors">خانه</a></li>
			<li aria-hidden="true">/</li>
			<li aria-current="page" class="text-ink font-semibold">گنجینه دیدنی‌های ایران</li>
		</ol>
		<span class="font-mono text-[10px] text-slate-400 bg-canvas border border-line px-2 py-1 rounded-lg shrink-0 hidden sm:inline">SYS.ID // POI-HUB</span>
	</nav>

	<section class="bg-brand-forest text-white rounded-3xl p-6 sm:p-8 lg:p-10 shadow-floating relative overflow-hidden grid-pattern space-y-5">
		<div class="absolute -left-20 -bottom-20 w-64 h-64 rounded-full bg-brand-terracotta/20 blur-3xl pointer-events-none"></div>
		<div class="relative z-10 space-y-3 max-w-3xl">
			<span class="text-xs font-bold text-amber-200 bg-white/10 px-3 py-1 rounded-full inline-block">بخش دیدنی‌های ایران</span>
			<h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight leading-[1.3]">گنجینه دیدنی‌های ایران</h1>
			<p class="text-sm sm:text-base text-white/85 leading-relaxed">
				میراث جهانی، آثار ملی، طبیعت بکر و زیارتگاه‌ها؛ هر دیدنی با شناسنامهٔ مکان، ساعات بازدید، مسیر دسترسی، نکات میدانی و پیوند مستقیم به شهرستان و استان خودش.
			</p>
		</div>
		<div class="relative z-10 flex flex-wrap items-center gap-2 text-xs">
			<span class="px-3 py-1.5 rounded-xl bg-white/10 border border-white/15"><?php echo esc_html( sa_2026_num( $sa_total ) . ' دیدنی منتشرشده' ); ?></span>
			<?php foreach ( $sa_types as $sa_type ) : ?>
				<a href="<?php echo esc_url( sa_2026_treasure_filter_url( array( 'attraction_type' => $sa_type['slug'], 'sa_county' => '', 'province_tax' => '' ) ) ); ?>"
					class="px-3 py-1.5 rounded-xl <?php echo $sa_filters['type'] === $sa_type['slug'] ? 'bg-brand-terracotta text-white font-bold' : 'bg-white/10 border border-white/15 hover:bg-white/20 transition-colors'; ?>">
					<?php echo esc_html( $sa_type['label'] . ' · ' . sa_2026_num( $sa_type['count'] ) ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<section aria-labelledby="treasure-filter-heading" class="bg-surface rounded-3xl border border-line p-5 sm:p-6 shadow-card space-y-4">
		<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
			<h2 id="treasure-filter-heading" class="text-sm font-extrabold text-ink">فیلتر گنجینه بر پایهٔ استان، شهرستان و نوع</h2>
			<p class="text-xs text-muted">
				<?php echo esc_html( $sa_has_filt ? sa_2026_num( $sa_found ) . ' نتیجه با فیلتر فعلی' : 'همهٔ دیدنی‌های منتشرشده' ); ?>
			</p>
		</div>

		<form method="get" action="<?php echo esc_url( sa_2026_treasure_url() ); ?>" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
			<label class="block space-y-1.5">
				<span class="text-[11px] font-semibold text-muted">استان</span>
				<select name="province_tax" data-sa-auto-submit
					class="w-full bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm text-ink focus:outline-none focus:border-brand-forest">
					<option value="">همهٔ استان‌ها</option>
					<?php foreach ( $sa_provinces as $sa_province ) : ?>
						<?php /* Term counts on province_tax cover every entity type, so no count here (no invented numbers). */ ?>
						<option value="<?php echo esc_attr( $sa_province['slug'] ); ?>" <?php selected( $sa_filters['province'], $sa_province['slug'] ); ?>>
							<?php echo esc_html( $sa_province['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="block space-y-1.5">
				<span class="text-[11px] font-semibold text-muted">شهرستان</span>
				<select name="sa_county" data-sa-auto-submit
					class="w-full bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm text-ink focus:outline-none focus:border-brand-forest">
					<option value="">همهٔ شهرستان‌ها</option>
					<?php foreach ( $sa_counties as $sa_county ) : ?>
						<option value="<?php echo esc_attr( $sa_county['slug'] ); ?>" <?php selected( $sa_filters['county'], $sa_county['slug'] ); ?>>
							<?php echo esc_html( $sa_county['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="block space-y-1.5">
				<span class="text-[11px] font-semibold text-muted">نوع دیدنی</span>
				<select name="attraction_type" data-sa-auto-submit
					class="w-full bg-canvas border border-line rounded-xl px-3 py-2.5 text-sm text-ink focus:outline-none focus:border-brand-forest">
					<option value="">همهٔ انواع</option>
					<?php foreach ( $sa_types as $sa_type ) : ?>
						<option value="<?php echo esc_attr( $sa_type['slug'] ); ?>" <?php selected( $sa_filters['type'], $sa_type['slug'] ); ?>>
							<?php echo esc_html( $sa_type['label'] . ' (' . sa_2026_num( $sa_type['count'] ) . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

		</form>

		<?php if ( $sa_has_filt ) : ?>
			<div class="flex flex-wrap items-center gap-2 pt-3 border-t border-line text-xs">
				<span class="text-muted font-semibold">فیلترهای فعال:</span>
				<?php if ( '' !== $sa_filters['province'] ) : ?>
					<a href="<?php echo esc_url( sa_2026_treasure_filter_url( array( 'province_tax' => '' ) ) ); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-forestSoft text-brand-forest font-semibold">
						استان: <?php echo esc_html( isset( $sa_prov_map[ $sa_filters['province'] ] ) ? $sa_prov_map[ $sa_filters['province'] ] : $sa_filters['province'] ); ?> <span aria-hidden="true">✕</span>
					</a>
				<?php endif; ?>
				<?php if ( '' !== $sa_filters['county'] ) : ?>
					<a href="<?php echo esc_url( sa_2026_treasure_filter_url( array( 'sa_county' => '' ) ) ); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-forestSoft text-brand-forest font-semibold">
						شهرستان: <?php echo esc_html( isset( $sa_county_map[ $sa_filters['county'] ] ) ? $sa_county_map[ $sa_filters['county'] ] : $sa_filters['county'] ); ?> <span aria-hidden="true">✕</span>
					</a>
				<?php endif; ?>
				<?php if ( '' !== $sa_filters['type'] ) : ?>
					<a href="<?php echo esc_url( sa_2026_treasure_filter_url( array( 'attraction_type' => '' ) ) ); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-forestSoft text-brand-forest font-semibold">
						نوع: <?php echo esc_html( isset( $sa_type_map[ $sa_filters['type'] ] ) ? $sa_type_map[ $sa_filters['type'] ] : $sa_filters['type'] ); ?> <span aria-hidden="true">✕</span>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( sa_2026_treasure_url() ); ?>" class="px-2.5 py-1 rounded-lg bg-canvas border border-line font-semibold text-ink hover:border-brand-forest transition-colors">پاک کردن همه</a>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( have_posts() ) : ?>
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
			<?php
			while ( have_posts() ) :
				the_post();

				$sa_att_id    = get_the_ID();
				$sa_city_id   = function_exists( 'sa_meta' ) ? (int) sa_meta( $sa_att_id, 'sa_city_id' ) : 0;
				$sa_city_name = $sa_city_id ? get_the_title( $sa_city_id ) : '';
				$sa_terms     = get_the_terms( $sa_att_id, 'attraction_type' );
				$sa_term      = is_array( $sa_terms ) && $sa_terms ? $sa_terms[0] : null;
				$sa_cat       = $sa_term ? (string) $sa_term->name : '';
				$sa_cover     = function_exists( 'sa_2026_cover' ) ? sa_2026_cover( $sa_att_id, 'large' ) : '';
				?>
				<article class="group bg-surface rounded-2xl border border-line overflow-hidden hover:border-brand-forest hover:shadow-card transition-all flex flex-col">
					<a href="<?php the_permalink(); ?>" class="block aspect-[16/10] overflow-hidden bg-brand-forestSoft">
						<img src="<?php echo esc_url( $sa_cover ); ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
					</a>
					<div class="p-4 flex flex-col justify-between flex-1 gap-3">
						<div class="space-y-1.5">
							<?php if ( $sa_cat ) : ?>
								<span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-lg bg-brand-terracottaSoft text-brand-terracotta"><?php echo esc_html( $sa_cat ); ?></span>
							<?php endif; ?>
							<h2 class="text-base font-extrabold text-ink leading-snug">
								<a href="<?php the_permalink(); ?>" class="group-hover:text-brand-forest transition-colors"><?php the_title(); ?></a>
							</h2>
							<p class="font-mono text-[10px] text-muted">SYS.ID // <?php echo esc_html( sa_2026_poi_code( $sa_att_id ) ); ?></p>
						</div>
						<div class="pt-2.5 border-t border-line/80 flex items-center justify-between gap-2 text-[11px] text-muted">
							<span class="truncate"><?php echo esc_html( $sa_city_name ? 'شهرستان ' . $sa_city_name : 'ایران' ); ?></span>
							<span class="font-bold text-brand-forest shrink-0">راهنما ←</span>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<nav aria-label="صفحه‌بندی گنجینه" class="sa-pagination flex items-center justify-center gap-2 pt-4">
			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'prev_text'          => 'قبلی',
					'next_text'          => 'بعدی',
					'screen_reader_text' => 'صفحه‌بندی گنجینه دیدنی‌ها',
				)
			);
			?>
		</nav>
	<?php else : ?>
		<div class="bg-surface rounded-3xl border border-line p-8 sm:p-10 text-center space-y-3">
			<h2 class="text-lg font-extrabold text-ink">با این فیلترها نتیجه‌ای پیدا نشد</h2>
			<p class="text-sm text-muted">فیلترها را ساده‌تر کنید یا از فهرست کامل دیدنی‌ها شروع کنید.</p>
			<a href="<?php echo esc_url( sa_2026_treasure_url() ); ?>" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand-forest hover:bg-brand-forest/90 text-white text-xs font-bold transition-colors">
				نمایش همهٔ دیدنی‌ها
			</a>
		</div>
	<?php endif; ?>

	<section class="bg-surface rounded-3xl border border-line p-6 sm:p-8 shadow-card flex flex-col sm:flex-row sm:items-center justify-between gap-4">
		<div class="space-y-1">
			<h2 class="text-lg font-extrabold text-ink">دیدنی‌ای می‌شناسید که در گنجینه نیست؟</h2>
			<p class="text-xs sm:text-sm text-muted">تجربه و اطلاعات خودتان را ثبت کنید؛ پس از بررسی تحریریه با نام خودتان منتشر می‌شود.</p>
		</div>
		<button type="button" data-sa-open-experience
			class="shrink-0 inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-terracotta hover:bg-brand-terracotta/90 text-white text-sm font-bold transition-colors">
			<span>ثبت تجربه یا پیشنهاد</span>
			<span aria-hidden="true">←</span>
		</button>
	</section>

</main>

<?php
get_footer();
