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

// Safety switch: with the 2026 design off, render the previous template verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'footer' ) ) {
	return;
}

/**
 * Link to one of the seeded pages (about/contact/privacy/policy/…).
 *
 * Locked by tests/test-trust-bundle.php after the 2026-11-15 live audit:
 *   • a draft page (e.g. the «policy» page created by activation) is NEVER linked;
 *   • «policy» resolves the live «editorial-policy» page when it is published;
 *   • when no published page exists, no link is printed at all (no guessed 404).
 *
 * @param string $slug Page slug.
 * @return string URL or empty string.
 */
function sa_seeded_page_link( $slug ) {
	$sa_ids = get_option( 'sa_default_pages', array() );
	if ( ! empty( $sa_ids[ $slug ] ) && 'publish' === get_post_status( (int) $sa_ids[ $slug ] ) ) {
		return get_permalink( (int) $sa_ids[ $slug ] );
	}
	$sa_slugs = 'policy' === $slug ? array( 'editorial-policy', 'policy' ) : array( $slug );
	foreach ( $sa_slugs as $sa_try ) {
		$sa_page = get_page_by_path( $sa_try );
		if ( $sa_page instanceof WP_Post && 'publish' === $sa_page->post_status ) {
			return get_permalink( $sa_page );
		}
	}

	return '';
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
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( $sa_brand . ' — صفحه نخست' ); ?>" class="inline-flex items-center">
						<img src="<?php echo esc_url( sa_brand_logo_url( 'dark', 480 ) ); ?>" alt="<?php echo esc_attr( $sa_brand ); ?>"
							width="72" height="30" class="h-[30px] w-auto object-contain" loading="lazy" decoding="async">
					</a>
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
				<h2 class="text-xs font-bold text-ink uppercase tracking-wider">اعتماد و ارتباط</h2>
				<ul class="space-y-2 text-xs sm:text-sm text-slate-600">
					<?php
					/*
					 * 2.22.73 rule (kept by tests/test-trust-bundle.php): only published pages are
					 * linked; «policy» resolves the live «editorial-policy» page; nothing is guessed.
					 */
					$sa_trust_links = array(
						'about'   => 'درباره ما',
						'contact' => 'تماس با تحریریه',
						'policy'  => 'سیاست تحریریه',
					);
					foreach ( $sa_trust_links as $sa_slug => $sa_label ) :
						$sa_page_url = sa_seeded_page_link( $sa_slug );
						if ( ! $sa_page_url ) {
							continue;
						}
						?>
						<li><a href="<?php echo esc_url( $sa_page_url ); ?>" class="hover:text-petrol transition-colors"><?php echo esc_html( $sa_label ); ?></a></li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( $sa_contrib ); ?>" class="hover:text-petrol transition-colors">ثبت تجربه و مشارکت</a></li>
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
