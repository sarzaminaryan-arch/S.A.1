<?php
/**
 * Single: entities only. Non-entity posts redirect to home.
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
	echo '<main id="primary" class="site-main container sa-archive">';
	get_template_part( 'template-parts/content', 'none' );
	echo '</main>';
}

get_footer();