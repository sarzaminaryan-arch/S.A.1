<?php
/**
 * Souvenir: other souvenirs + foods of the same city.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id   = get_the_ID();
$sa_city = sa_get_parent( $sa_id, 'city' );
if ( $sa_city ) {
	$sa_others = array_filter( sa_get_children( $sa_city->ID, 'souvenir' ), function ( $p ) use ( $sa_id ) { return $p->ID !== $sa_id; } );
	sa_cards_section( array_slice( $sa_others, 0, 4 ), 'سوغات دیگر ' . sa_entity_display_name( $sa_city ), get_permalink( $sa_city ) . '#souvenirs', 'souvenirs' );
	sa_cards_section( array_slice( sa_get_children( $sa_city->ID, 'local_food' ), 0, 4 ), 'غذاهای محلی ' . sa_entity_display_name( $sa_city ), get_permalink( $sa_city ) . '#foods', 'foods' );
}
