<?php
/**
 * Single: entities get the entity layout; ordinary posts (educational guides,
 * v2.13.0) get the article layout; anything else shows the empty state.
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
		sa_public_article_template( 'template-parts/entity/single' );
	}
	echo '</main>';
} elseif ( is_singular( 'post' ) ) {
	echo '<main id="primary" class="site-main container sa-main--post">';
	while ( have_posts() ) {
		the_post();
		sa_public_article_template( 'template-parts/content-single', 'post' );
	}
	echo '</main>';
} else {
	echo '<main id="primary" class="site-main container sa-archive">';
	get_template_part( 'template-parts/content', 'none' );
	echo '</main>';
}

get_footer();
