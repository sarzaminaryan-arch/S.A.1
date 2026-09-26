<?php
/**
 * Sarzamin Aryan Customizer options.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

if ( ! function_exists( 'sarzaminaryan_customize_register' ) ) {
	/**
	 * Add postMessage support for site title and description.
	 */
	function sarzaminaryan_customize_register( $wp_customize ) {
		$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
		$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
		$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

		if ( isset( $wp_customize->selective_refresh ) ) {
			$wp_customize->selective_refresh->add_partial(
				'blogname',
				array(
					'selector'        => '.site-title a',
					'render_callback' => 'sarzaminaryan_customize_partial_blogname',
				)
			);
			$wp_customize->selective_refresh->add_partial(
				'blogdescription',
				array(
					'selector'        => '.site-description',
					'render_callback' => 'sarzaminaryan_customize_partial_blogdescription',
				)
			);
		}

		// Add section for footer text.
		$wp_customize->add_section(
			'sarzaminaryan_options',
			array(
				'title'    => esc_html__( 'Theme Options', 'sarzaminaryan' ),
				'priority' => 30,
			)
		);

		// Footer copyright text.
		$wp_customize->add_setting(
			'sarzaminaryan_footer_text',
			array(
				'default'           => esc_html__( 'All rights reserved.', 'sarzaminaryan' ),
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'sarzaminaryan_footer_text',
			array(
				'label'   => esc_html__( 'Footer Text', 'sarzaminaryan' ),
				'section' => 'sarzaminaryan_options',
				'type'    => 'text',
			)
		);
	}
}
add_action( 'customize_register', 'sarzaminaryan_customize_register' );

if ( ! function_exists( 'sarzaminaryan_customize_partial_blogname' ) ) {
	function sarzaminaryan_customize_partial_blogname() {
		bloginfo( 'name' );
	}
}

if ( ! function_exists( 'sarzaminaryan_customize_partial_blogdescription' ) ) {
	function sarzaminaryan_customize_partial_blogdescription() {
		bloginfo( 'description' );
	}
}