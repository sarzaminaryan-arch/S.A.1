<?php
/**
 * Footer — Sarzamin Aryan, 2026 design refresh.
 *
 * Clean three-column footer from the approved mockups plus the shared
 * "ثبت تجربه" dialog used by the header CTA and the page-level CTAs.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_brand     = function_exists( 'sa_brand_name' ) ? sa_brand_name() : get_bloginfo( 'name' );
$sa_explore   = function_exists( 'sa_2026_footer_explore' ) ? sa_2026_footer_explore() : array();
$sa_about_url = home_url( '/about/' );
$sa_contact   = home_url( '/contact/' );
$sa_contrib   = function_exists( 'sa_2026_contrib_url' ) ? sa_2026_contrib_url() : '#';
$sa_year       = function_exists( 'sa_jalali_year' ) ? sa_jalali_year() : '۱۴۰۵';
?>
<footer class="bg-surface border-t border-line mt-12">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
		<div class="grid grid-cols-1 md:grid-cols-12 gap-8 pb-8 border-b border-line">
			<div class="md:col-span-5 space-y-3">
				<div class="flex items-center gap-2.5">
					<?php /* Official dark lockup for the light footer surface (brand rule: no redrawn logo). */ ?>
					<img src="<?php echo esc_url( sa_brand_logo_url( 'dark', 480 ) ); ?>" alt="<?php echo esc_attr( $sa_brand ); ?>"
						width="72" height="30" class="h-[30px] w-auto object-contain" loading="lazy" decoding="async">
					<span class="w-2.5 h-2.5 rounded-full bg-ochre" aria-hidden="true"></span>
					<span class="text-[11px] font-bold text-ochreDark">دانشنامه سفر و شناخت ایران</span>
				</div>
				<p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-md">
					دانشنامه سفر و شناخت ایران؛ مرجع معرفی استان‌ها، شهرستان‌ها و جاذبه‌های گردشگری با تکیه بر داده‌های دقیق جغرافیایی و تجربه واقعی مسافرها.
				</p>
			</div>

			<div class="md:col-span-4 space-y-2.5">
				<h2 class="text-xs font-bold text-ink uppercase tracking-wider">کاوش در دانشنامه</h2>
				<ul class="grid grid-cols-2 gap-2 text-xs sm:text-sm text-slate-600">
					<?php foreach ( $sa_explore as $sa_link ) : ?>
						<li><a href="<?php echo esc_url( $sa_link['url'] ); ?>" class="hover:text-petrol transition-colors"><?php echo esc_html( $sa_link['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="md:col-span-3 space-y-2.5">
				<h2 class="text-xs font-bold text-ink uppercase tracking-wider">ارتباط و مشارکت</h2>
				<ul class="space-y-2 text-xs sm:text-sm text-slate-600">
					<li><a href="<?php echo esc_url( $sa_about_url ); ?>" class="hover:text-petrol transition-colors">درباره ما</a></li>
					<li><a href="<?php echo esc_url( $sa_contact ); ?>" class="hover:text-petrol transition-colors">تماس با تحریریه</a></li>
					<li>
						<a href="<?php echo esc_url( $sa_contrib ); ?>" class="hover:text-petrol transition-colors" data-sa-open-experience>
							ثبت تجربه در بخش شهر من
						</a>
					</li>
				</ul>
			</div>
		</div>

		<div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted">
			<p>© <?php echo esc_html( $sa_year ); ?> <?php echo esc_html( $sa_brand ); ?> — تمامی حقوق محفوظ است.</p>
			<p>ایران را دوباره کشف کن؛ هر گوشه، داستانی تازه.</p>
		</div>
	</div>
</footer>

<?php get_template_part( 'template-parts/2026/experience-modal' ); ?>

<?php wp_footer(); ?>
</body>
</html>
