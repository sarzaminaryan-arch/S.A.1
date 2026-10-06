<?php
/**
 * Travel route: itinerary (cities in order) + attractions on the way.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id     = get_the_ID();
$sa_cities = sa_get_related( $sa_id, 'sa_city_ids' );
if ( $sa_cities ) {
	echo '<section class="sa-section sa-itinerary" id="itinerary"><h2 class="sa-section__title">مسیر سفر (به ترتیب)</h2><ol class="sa-itinerary__list">';
	foreach ( $sa_cities as $sa_i => $sa_c ) {
		printf( '<li><span class="sa-itinerary__num">%s</span><a href="%s">%s</a><span class="sa-itinerary__prov">%s</span></li>', esc_html( sa_number( $sa_i + 1 ) ), esc_url( get_permalink( $sa_c ) ), esc_html( sa_entity_display_name( $sa_c ) ), esc_html( sa_card_meta( $sa_c->ID ) ) );
	}
	echo '</ol></section>';
}
sa_cards_section( sa_get_related( $sa_id, 'sa_attraction_ids' ), 'نمای برتر این مسیر', '', 'attractions' );
