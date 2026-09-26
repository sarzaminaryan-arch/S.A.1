<?php
/**
 * Archive template for the "travel_route" entity → shared entity archive.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-archive sa-archive--travel_route">
	<?php get_template_part( 'template-parts/archive', 'entities' ); ?>
</main>
<?php
get_footer();
