<?php
/**
 * Back-links to published county and province parents on attraction pages.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_attraction_parents = function_exists( 'sa_attraction_parent_links_html' )
	? sa_attraction_parent_links_html( get_the_ID() )
	: '';
if ( '' !== $sa_attraction_parents ) {
	echo $sa_attraction_parents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes every dynamic value.
}
