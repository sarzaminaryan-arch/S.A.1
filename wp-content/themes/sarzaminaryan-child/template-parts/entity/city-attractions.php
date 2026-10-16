<?php
/**
 * Published attraction links on county pages.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_city_attractions = function_exists( 'sa_city_attractions_html' )
	? sa_city_attractions_html( get_the_ID() )
	: '';
if ( '' !== $sa_city_attractions ) {
	echo $sa_city_attractions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes every dynamic value.
}
