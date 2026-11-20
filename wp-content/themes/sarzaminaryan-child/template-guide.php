<?php
/**
 * «آموزش سفر» hub — template of `/travel-guide/`.
 *
 * Since v2.24.0 this address is the archive of the `guide` post type, so
 * `archive-guide.php` requires this file and `inc/guide-hub.php` serves it for the
 * hub's own search as well. The listing itself lives in
 * `template-parts/2026/guide-hub.php` so the category archives share it.
 *
 * With the 2026 design switched off, the same list is printed with the classic
 * classes (`sa-archive`), so the page never loses its content.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( function_exists( 'sa_2026_design_enabled' ) && ! sa_2026_design_enabled() ) {
	echo '<main id="primary" class="site-main container sa-archive">';
	echo '<header class="sa-archive__head"><h1 class="sa-archive__title">آموزش سفر</h1></header>';
	if ( have_posts() ) {
		echo '<ul class="sa-archive__list">';
		while ( have_posts() ) {
			the_post();
			printf(
				'<li><a href="%s">%s</a> <span class="sa-archive__date">%s</span></li>',
				esc_url( (string) get_permalink() ),
				esc_html( get_the_title() ),
				esc_html( (string) get_the_date() )
			);
		}
		echo '</ul>';
		the_posts_pagination(
			array(
				'prev_text' => 'قبلی',
				'next_text' => 'بعدی',
			)
		);
	} else {
		get_template_part( 'template-parts/content', 'none' );
	}
	echo '</main>';
} else {
	get_template_part( 'template-parts/2026/guide-hub' );
}

get_footer();
