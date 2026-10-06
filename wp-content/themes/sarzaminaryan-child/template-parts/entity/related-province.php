<?php
/**
 * Province hub: cities, attractions, foods, souvenirs, routes.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id = get_the_ID();
// City links are rendered as compact navigation pills directly below the province featured image.
sa_cards_section( sa_get_children( $sa_id, 'attraction' ), 'نمای برتر ' . sa_entity_display_name( $sa_id, 'استان' ), '', 'attractions' );
sa_cards_section( sa_get_children( $sa_id, 'local_food' ), 'غذاهای محلی', '', 'foods' );
sa_cards_section( sa_get_children( $sa_id, 'souvenir' ), 'سوغات', '', 'souvenirs' );
sa_cards_section( sa_get_children( $sa_id, 'travel_route' ), 'مسیرهای سفر مرتبط', '', 'routes' );
