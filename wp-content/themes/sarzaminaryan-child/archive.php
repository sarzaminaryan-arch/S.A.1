<?php
/**
 * Archive: entity CPT archives & taxonomies only.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

echo '<main id="primary" class="site-main container sa-archive">';

if ( have_posts() ) {
	get_template_part( 'template-parts/archive', 'entities' );
} else {
	get_template_part( 'template-parts/content', 'none' );
}

echo '</main>';

get_footer();