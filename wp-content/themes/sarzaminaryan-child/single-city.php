<?php
/**
 * Single template for the "city" entity → generic entity layout.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main sa-main--entity">
	<?php
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/entity/single', 'city' );
	}
	?>
</main>
<?php
get_footer();
