<?php
/**
 * Category archives of the guides — 2026 design refresh.
 *
 * Owner report (۲۹ آبان ۱۴۰۵): the main categories looked like a bare list of
 * titles. They now use the same hero/chips/card listing as the «آموزش سفر» hub
 * (`template-parts/2026/guide-hub.php`), so every guide category is skinnable with
 * one vocabulary. The previous view is kept in `legacy/category.php` for the
 * rollback switch.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'category' ) ) {
	return;
}

get_header();

if ( function_exists( 'sa_2026_design_enabled' ) && ! sa_2026_design_enabled() ) {
	echo '<main id="primary" class="site-main container sa-archive">';
	if ( have_posts() ) {
		get_template_part( 'template-parts/archive', 'entities' );
	} else {
		get_template_part( 'template-parts/content', 'none' );
	}
	echo '</main>';
} else {
	get_template_part( 'template-parts/2026/guide-hub' );
}

get_footer();
