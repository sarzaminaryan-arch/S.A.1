<?php
/**
 * City: attractions, foods, souvenirs, routes, sibling cities.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id = get_the_ID();
sa_cards_section( sa_get_children( $sa_id, 'attraction' ), 'جاذبه‌های ' . get_the_title(), '', 'attractions' );
sa_cards_section( sa_get_children( $sa_id, 'local_food' ), 'غذاهای محلی ' . get_the_title(), '', 'foods' );
sa_cards_section( sa_get_children( $sa_id, 'souvenir' ), 'سوغات ' . get_the_title(), '', 'souvenirs' );
sa_cards_section( sa_get_children( $sa_id, 'travel_route' ), 'مسیرهای سفری که از این شهر می‌گذرند', '', 'routes' );
$sa_province = sa_get_parent( $sa_id, 'province' );
if ( $sa_province ) {
	$sa_siblings = array_filter(
		sa_get_children( $sa_province->ID, 'city' ),
		function ( $c ) use ( $sa_id ) {
			return $c->ID !== $sa_id;
		}
	);
	sa_cards_section( array_slice( $sa_siblings, 0, 8 ), 'شهرهای دیگر استان ' . get_the_title( $sa_province ), get_permalink( $sa_province ), 'related-cities' );
}
