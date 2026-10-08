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
// Published county-page links are rendered in the province identity card near the top of the page.
sa_cards_section( sa_get_children( $sa_id, 'attraction' ), 'نمای برتر استان ' . get_the_title(), '', 'attractions' );
sa_cards_section( sa_get_children( $sa_id, 'local_food' ), 'غذاهای محلی', '', 'foods' );
sa_cards_section( sa_get_children( $sa_id, 'souvenir' ), 'سوغات', '', 'souvenirs' );
sa_cards_section( sa_get_children( $sa_id, 'travel_route' ), 'مسیرهای سفر مرتبط', '', 'routes' );
