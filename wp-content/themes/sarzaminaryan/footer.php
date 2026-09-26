<?php
/**
 * The footer for Sarzamin Aryan theme.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<footer id="colophon" class="site-footer">
	<div class="container">

		<div class="site-footer__top">
			<div class="site-footer__brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Footer Menu', 'sarzaminaryan' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'menu_id'        => 'footer-menu',
							'container'      => false,
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="site-footer__bottom">
			<p class="site-footer__copyright">
				&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<?php echo esc_html( get_theme_mod( 'sarzaminaryan_footer_text', __( 'All rights reserved.', 'sarzaminaryan' ) ) ); ?>
			</p>
		</div>

	</div><!-- .container -->
</footer><!-- #colophon -->

<?php wp_footer(); ?>

</body>
</html>
