<?php
/**
 * Template Name: ثبت تجربه (فرم ارسال)
 * Template Post Type: page
 *
 * Wraps the shared 2026 submission form with the site header and footer. Used by the
 * virtual /city-contrib/ route and selectable for any page the owner creates.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
get_template_part( 'template-parts/2026/experience-page' );
get_footer();
