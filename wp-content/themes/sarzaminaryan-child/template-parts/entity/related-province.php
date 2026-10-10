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
// The modular province identity renders the published county links.
sa_cards_section( sa_get_children( $sa_id, 'attraction' ), 'دیدنی‌های استان ' . sa_province_name_for_post( $sa_id ), '', 'attractions' );
sa_cards_section( sa_get_children( $sa_id, 'local_food' ), 'غذاهای محلی', '', 'foods' );
sa_cards_section( sa_get_children( $sa_id, 'souvenir' ), 'سوغات', '', 'souvenirs' );
sa_cards_section( sa_get_children( $sa_id, 'travel_route' ), 'مسیرهای سفر مرتبط', '', 'routes' );
