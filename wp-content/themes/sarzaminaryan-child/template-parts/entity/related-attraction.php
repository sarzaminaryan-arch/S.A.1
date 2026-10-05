<?php
/**
 * Attraction: similar attractions (explicit related_many, then same city/type).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id   = get_the_ID();
$sa_city = sa_get_parent( $sa_id, 'city' );
sa_cards_section( sa_get_similar_attractions( $sa_id, 6 ), 'نمای برتر مشابه', $sa_city ? get_permalink( $sa_city ) . '#attractions' : '', 'similar' );
