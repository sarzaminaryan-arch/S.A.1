<?php
/**
 * Single: entities get the entity layout, posts get the article layout with sidebar.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( sa_is_entity() ) {
	echo '<main id="primary" class="site-main sa-main--entity">';
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/entity/single' );
	}
	echo '</main>';
} else {
	echo '<div class="container site-content"><main id="primary" class="site-main">';
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content', 'single' );
	}
	echo '</main>';
	get_sidebar();
	echo '</div>';
}

get_footer();
