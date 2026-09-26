<?php
/**
 * Local food: other foods of the same city + souvenirs.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id   = get_the_ID();
$sa_city = sa_get_parent( $sa_id, 'city' );
if ( $sa_city ) {
	$sa_others = array_filter( sa_get_children( $sa_city->ID, 'local_food' ), function ( $p ) use ( $sa_id ) { return $p->ID !== $sa_id; } );
	sa_cards_section( array_slice( $sa_others, 0, 4 ), 'غذاهای دیگر ' . get_the_title( $sa_city ), get_permalink( $sa_city ) . '#foods', 'foods' );
	sa_cards_section( array_slice( sa_get_children( $sa_city->ID, 'attraction' ), 0, 4 ), 'دیدنی‌های ' . get_the_title( $sa_city ), get_permalink( $sa_city ) . '#attractions', 'attractions' );
}
