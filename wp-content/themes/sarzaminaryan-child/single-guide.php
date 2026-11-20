<?php
/**
 * Single «راهنمای سفر» (v2.24.0).
 *
 * The guides used to be ordinary posts, so they were rendered by `single.php` through
 * `template-parts/content-single-post.php`. Moving them to their own post type must not
 * change one pixel of that article: this template prints the very same part, so the
 * section keeps its 2026 design, its byline, its risk badge, its FAQ, its sources, its
 * table of contents, its related-guides block and its comments.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

echo '<main id="primary" class="site-main container sa-main--post">';
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content-single', 'post' );
}
echo '</main>';

get_footer();
