<?php
/**
 * Footer — Sarzamin Aryan Child.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<footer id="colophon" class="site-footer sa-footer">
	<div class="container">

		<div class="sa-footer__grid">

			<div class="sa-footer__col">
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php else : ?>
					<div class="sa-footer__brand">
						<?php if ( has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="brand__mark" aria-hidden="true">س</span><span class="site-title"><?php bloginfo( 'name' ); ?></span></a>
						<?php endif; ?>
					</div>
					<p class="sa-footer__about"><?php echo esc_html( get_theme_mod( 'sa_footer_about', 'سرزمین آریان دانشنامه‌ی سفر ایران است؛ اطلاعات دقیق و به‌روز درباره‌ی استان‌ها، شهرها، جاذبه‌ها، مسیرهای سفر، غذاها و سوغات.' ) ); ?></p>
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

			<div class="sa-footer__col">
				<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
					<?php dynamic_sidebar( 'footer-2' ); ?>
				<?php else : ?>
					<h2 class="widget-title">کاوش در ایران</h2>
					<ul class="sa-footer__links">
						<?php foreach ( sa_entity_types() as $sa_type ) : ?>
							<li><a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></a></li>
						<?php endforeach; ?>
						<?php if ( (int) get_option( 'page_for_posts' ) ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ); ?>">وبلاگ</a></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="sa-footer__col">
				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-3' ); ?>
				<?php elseif ( has_nav_menu( 'footer' ) ) : ?>
					<h2 class="widget-title">درباره‌ی سایت</h2>
					<nav class="footer-navigation" aria-label="منوی پابرگ">
						<?php wp_nav_menu( array( 'theme_location' => 'footer', 'menu_id' => 'footer-menu', 'container' => false, 'depth' => 1, 'menu_class' => 'sa-footer__links' ) ); ?>
					</nav>
				<?php endif; ?>
				<?php if ( get_theme_mod( 'sa_contact_email', '' ) ) : ?>
					<p class="sa-footer__contact"><a href="mailto:<?php echo esc_attr( antispambot( get_theme_mod( 'sa_contact_email' ) ) ); ?>"><?php echo esc_html( antispambot( get_theme_mod( 'sa_contact_email' ) ) ); ?></a></p>
				<?php endif; ?>
			</div>

		</div>

		<div class="sa-footer__bottom">
			<p class="sa-footer__copyright">
				<?php
				$sa_copy = get_theme_mod( 'sa_footer_copyright', '' );
				if ( $sa_copy ) {
					echo esc_html( $sa_copy );
				} else {
					printf( '© %s %s — تمامی حقوق محفوظ است.', esc_html( sa_jalali_year() ), esc_html( get_bloginfo( 'name' ) ) );
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
