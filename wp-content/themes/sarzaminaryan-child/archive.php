<?php
/**
 * Generic archive: entity CPT archives & taxonomies → card grid; blog archives → list with sidebar.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sa_entity_archive = is_post_type_archive( sa_entity_types() ) || is_tax( array_keys( sa_taxonomies_config() ) );

if ( $sa_entity_archive ) {
	echo '<main id="primary" class="site-main container sa-archive">';
	get_template_part( 'template-parts/archive', 'entities' );
	echo '</main>';
} else {
	echo '<div class="container site-content"><main id="primary" class="site-main">';
	echo '<header class="sa-archive__head"><h1 class="sa-archive__title">' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</h1>';
	the_archive_description( '<div class="sa-archive__desc">', '</div>' );
	echo '</header>';
	if ( have_posts() ) {
		echo '<div class="sa-grid-cards sa-grid-cards--2">';
		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/content', get_post_type() );
		}
		echo '</div>';
		the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) );
	} else {
		get_template_part( 'template-parts/content', 'none' );
	}
	echo '</main>';
	get_sidebar();
	echo '</div>';
}

get_footer();
