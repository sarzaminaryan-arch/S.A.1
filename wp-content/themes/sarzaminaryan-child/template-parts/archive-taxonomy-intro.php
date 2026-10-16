<?php
/**
 * Fallback intro and related content links for attraction_type/travel_season archives.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_taxonomy_intro = function_exists( 'sa_taxonomy_intro_html' ) ? sa_taxonomy_intro_html() : '';
if ( '' !== $sa_taxonomy_intro ) {
	echo $sa_taxonomy_intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes every dynamic value.
}
