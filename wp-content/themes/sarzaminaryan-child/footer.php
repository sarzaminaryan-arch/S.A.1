<?php
/**
 * Footer — Sarzamin Aryan Child (v2.7.1, Module 4: slim three-column footer).
 *
 * A short bar at the bottom of the site, three columns on desktop and two on
 * mobile, with every link appearing exactly once:
 *   1) small logo + one-line intro (+ optional social chips);
 *   2) «لینک‌های کاربردی» — home + entity archives;
 *   3) «ارتباط با ما» — the site pages (about/contact/privacy/policy) + e-mail.
 *
 * The old «برگه‌های سایت» column and the duplicated footer nav menu were
 * removed in v2.7.x; one curated, de-duplicated list feeds both link columns.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_logo_url = SA_CHILD_URI . 'assets/img/logo.webp';
if ( has_custom_logo() ) {
	$sa_logo_id = (int) get_theme_mod( 'custom_logo' );
	$sa_logo    = wp_get_attachment_image_src( $sa_logo_id, 'full' );
	if ( $sa_logo ) {
		$sa_logo_url = $sa_logo[0];
	}
}

/**
 * Link to one of the seeded pages (about/contact/privacy/policy/…), falling back
 * to a pretty URL so the link exists even before the page is created.
 *
 * @param string $slug Page slug.
 * @return string URL.
 */
function sa_seeded_page_link( $slug ) {
	$sa_ids = get_option( 'sa_default_pages', array() );
	if ( ! empty( $sa_ids[ $slug ] ) && get_post( $sa_ids[ $slug ] ) ) {
		return get_permalink( (int) $sa_ids[ $slug ] );
	}
	$sa_page = get_page_by_path( $slug );
	if ( $sa_page instanceof WP_Post && 'publish' === $sa_page->post_status ) {
		return get_permalink( $sa_page );
	}
	// v2.3.0 — قبلاً اینجا یک نشانی حدسی برمی‌گشت، حتی وقتی برگه وجود نداشت؛
	// یعنی فوتر هر صفحه‌ی سایت به یک ۴۰۴ لینک می‌داد. حالا لینک ساخته نمی‌شود.
	return '';
}

/**
 * v2.7.1 — یک لینک فقط یک‌بار در فوتر دیده می‌شود.
 *
 * پیش از این، منوی «پابرگ» وردپرس و فهرست ثابت قالب کنار هم چاپ می‌شدند و
 * «سیاست تحریریه»، «درباره ما» و «تماس با ما» دوبار تکرار می‌شد. حالا هر نشانی
 * پس از نرمال‌سازی (حذف پروتکل/اسلش پایانی) فقط یک‌بار اجازه‌ی نمایش دارد.
 *
 * @param array  $list  فهرست مقصد (ارجاع).
 * @param array  $seen  نشانی‌های دیده‌شده (ارجاع).
 * @param string $url   نشانی.
 * @param string $label برچسب.
 */
function sa_footer_add_link( &$list, &$seen, $url, $label ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return;
	}
	$key = strtolower( untrailingslashit( preg_replace( '#^https?://#i', '', $url ) ) );
	if ( isset( $seen[ $key ] ) ) {
		return;
	}
	$seen[ $key ] = true;
	$list[]       = array(
		'url'   => $url,
		'label' => $label,
	);
}

$sa_seen = array();

/* ستون ۲ — لینک‌های کاربردی: خانه + آرشیو موجودیت‌های دارای محتوا. */
$sa_explore_links = array();
sa_footer_add_link( $sa_explore_links, $sa_seen, home_url( '/' ), 'صفحه اصلی' );
foreach ( sa_nav_entity_types() as $sa_type ) {
	sa_footer_add_link( $sa_explore_links, $sa_seen, sa_archive_url( $sa_type ), sa_entity_label( $sa_type, true ) );
}

/* ستون ۳ — ارتباط با ما: برگه‌های سایت (ادغام‌شده) + ایمیل. */
$sa_page_links = array();
foreach ( array(
	'about'   => 'درباره ما',
	'contact' => 'تماس با ما',
	'privacy' => 'حریم خصوصی',
	'policy'  => 'سیاست تحریریه',
) as $sa_slug => $sa_label ) {
	sa_footer_add_link( $sa_page_links, $sa_seen, sa_seeded_page_link( $sa_slug ), $sa_label );
}

$sa_contact_email = get_theme_mod( 'sa_contact_email', '' );
$sa_about_text    = get_theme_mod( 'sa_footer_about', 'دانشنامه‌ی سفر ایران: استان‌ها، شهرها، جاذبه‌ها، مسیرها، غذاها و سوغات.' );
?>

<footer id="colophon" class="site-footer sa-hf-foot sa-hf-foot--mini">
	<div class="container">

		<div class="sa-hf-foot__grid">

			<div class="sa-hf-foot__col sa-hf-foot__brand">
				<a class="sa-hf-brand sa-hf-brand--foot" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="sa-hf-logo"><img src="<?php echo esc_url( $sa_logo_url ); ?>" width="28" height="28" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" loading="lazy" decoding="async"></span>
					<span class="sa-hf-text">
						<span class="site-title"><?php bloginfo( 'name' ); ?></span>
					</span>
				</a>
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php else : ?>
					<p class="sa-footer__about"><?php echo esc_html( $sa_about_text ); ?></p>
					<?php $sa_socials = sa_social_links(); ?>
					<?php if ( $sa_socials ) : ?>
						<ul class="sa-social" aria-label="شبکه‌های اجتماعی">
							<?php foreach ( $sa_socials as $sa_slug => $sa_net ) : ?>
								<li><a class="sa-social__link sa-social__link--<?php echo esc_attr( $sa_slug ); ?>" href="<?php echo esc_url( $sa_net['url'] ); ?>" target="_blank" rel="noopener me"><?php echo esc_html( $sa_net['label'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
				<div class="sa-hf-foot__col sa-hf-foot__links-col"><?php dynamic_sidebar( 'footer-2' ); ?></div>
			<?php elseif ( $sa_explore_links ) : ?>
				<div class="sa-hf-foot__col sa-hf-foot__links-col">
					<h2 class="widget-title">لینک‌های کاربردی</h2>
					<ul class="sa-hf-foot__links">
						<?php foreach ( $sa_explore_links as $sa_link ) : ?>
							<li><a href="<?php echo esc_url( $sa_link['url'] ); ?>"><?php echo esc_html( $sa_link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="sa-hf-foot__col sa-hf-foot__links-col">
				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-3' ); ?>
				<?php else : ?>
				<h2 class="widget-title">ارتباط با ما</h2>
				<?php if ( $sa_page_links ) : ?>
					<ul class="sa-hf-foot__links">
						<?php foreach ( $sa_page_links as $sa_link ) : ?>
							<li><a href="<?php echo esc_url( $sa_link['url'] ); ?>"><?php echo esc_html( $sa_link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $sa_contact_email ) : ?>
					<p class="sa-footer__contact"><a href="mailto:<?php echo esc_attr( antispambot( $sa_contact_email ) ); ?>"><?php echo esc_html( antispambot( $sa_contact_email ) ); ?></a></p>
				<?php endif; ?>
				<?php endif; ?>
			</div>

		</div>

		<div class="sa-hf-foot__bottom">
			<p class="sa-footer__copyright">
				<?php
				$sa_copy = get_theme_mod( 'sa_footer_copyright', '' );
				if ( $sa_copy ) {
					echo esc_html( $sa_copy );
				} else {
					printf( '© %s %s', esc_html( sa_jalali_year() ), esc_html( get_bloginfo( 'name' ) ) );
				}
				?>
			</p>
			<?php if ( get_theme_mod( 'sa_footer_credit', true ) ) : ?>
				<p class="sa-footer__credit">طراحی و توسعه: محمدرضا لک</p>
			<?php endif; ?>
		</div>

	</div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
