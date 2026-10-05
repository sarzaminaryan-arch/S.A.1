<?php
/**
 * Province/city gallery albums.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'sa_gallery_render_section' ) ) {
	sa_gallery_render_section( get_the_ID(), get_post_type() );
}
