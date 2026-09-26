<?php
/**
 * Other taxonomies (attraction_type, travel_season, travel_budget, travel_duration) → card grid.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-archive">
	<?php get_template_part( 'template-parts/archive', 'entities' ); ?>
</main>
<?php
get_footer();
