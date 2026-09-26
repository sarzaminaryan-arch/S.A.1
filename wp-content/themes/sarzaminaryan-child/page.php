<?php
/**
 * Static pages (full width, no sidebar).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-page">
	<?php
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content', 'page' );
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	}
	?>
</main>
<?php
get_footer();
