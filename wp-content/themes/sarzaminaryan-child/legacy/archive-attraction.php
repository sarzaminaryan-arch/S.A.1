<?php
/**
 * Archive template for the "attraction" entity → shared entity archive.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-archive sa-archive--attraction">
	<?php get_template_part( 'template-parts/archive', 'entities' ); ?>
</main>
<?php
get_footer();
