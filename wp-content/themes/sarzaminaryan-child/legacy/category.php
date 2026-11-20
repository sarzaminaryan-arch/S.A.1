<?php
/**
 * Category archive — pre-refresh view (kept for the 2026 rollback switch).
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
