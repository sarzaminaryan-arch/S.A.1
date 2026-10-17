<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SA_PC_Public {
	public static function register() {
		add_filter( 'the_content', array( __CLASS__, 'content' ), 9 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'guard' ), PHP_INT_MAX, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// Force the full, uncropped poster for head consumers including the child's SEO.
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'head_image_size' ), 99, 4 );
		foreach ( array( 'rank_math/opengraph/facebook/image', 'rank_math/opengraph/twitter/image', 'wpseo_opengraph_image', 'wpseo_twitter_image', 'seopress_social_og_thumb', 'seopress_social_twitter_card_thumb' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'social_url' ), 99 );
		}
		foreach ( array( 'rank_math/opengraph/facebook/image_array', 'rank_math/opengraph/twitter/image_array' ) as $hook ) { add_filter( $hook, array( __CLASS__, 'social_array' ), 99 ); }
		add_filter( 'wpseo_opengraph_image_width', array( __CLASS__, 'social_width' ), 99 );
		add_filter( 'wpseo_opengraph_image_height', array( __CLASS__, 'social_height' ), 99 );
		foreach ( array( 'wpseo_opengraph_image_alt', 'wpseo_twitter_image_alt' ) as $hook ) { add_filter( $hook, array( __CLASS__, 'social_alt' ), 99 ); }
		add_filter( 'aioseo_facebook_tags', array( __CLASS__, 'facebook_tags' ), 99 );
		add_filter( 'aioseo_twitter_tags', array( __CLASS__, 'twitter_tags' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
	}

	private static function context() {
		return ! is_admin() && ! is_feed() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && is_singular( 'province' );
	}

	/** No hashing, remote calls or writes during a public page request. */
	public static function image() {
		if ( ! self::context() ) { return false; }
		$id = (int) get_queried_object_id();
		if ( 'publish' !== get_post_status( $id ) || post_password_required( $id ) ) { return false; }
		$journal = get_option( 'sa_pc_journal_' . $id, array() );
		if ( 'applied' !== ( $journal['state'] ?? '' ) || empty( $journal['new_id'] ) || (int) get_post_thumbnail_id( $id ) !== (int) $journal['new_id'] ) { return false; }
		$catalog = sa_pc_catalog();
		if ( ! isset( $catalog[ $journal['slug'] ?? '' ] ) ) { return false; }
		$file = get_attached_file( $journal['new_id'] );
		if ( ! $file || ! is_readable( $file ) ) { return false; }
		$url = wp_get_attachment_url( $journal['new_id'] );
		$meta = wp_get_attachment_metadata( $journal['new_id'] );
		if ( ! $url || ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) { return false; }
		return array( 'id' => (int) $journal['new_id'], 'post_id' => $id, 'url' => $url,
			'alt' => $catalog[ $journal['slug'] ]['alt'], 'width' => (int) $meta['width'], 'height' => (int) $meta['height'] );
	}

	public static function assets() {
		if ( self::context() ) { wp_enqueue_style( 'sa-province-covers', SA_PC_URL . 'assets/public.css', array(), SA_PC_VERSION ); }
	}

	public static function content( $content ) {
		if ( ! self::context() || ! in_the_loop() || ! is_main_query() || (int) get_the_ID() !== (int) get_queried_object_id()
			|| ! get_option( 'sa_pc_show_in_article', true ) || strpos( $content, 'data-sa-province-cover=' ) !== false ) { return $content; }
		$image = self::image();
		if ( ! $image ) { return $content; }
		// wp_get_attachment_image deliberately avoids global the_post_thumbnail output:
		// this one explicitly scoped article figure is the only exception to the ban.
		$html = wp_get_attachment_image( $image['id'], 'full', false, array(
			'class' => 'sa-province-cover__image', 'alt' => $image['alt'], 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async',
		) );
		if ( ! $html ) { return $content; }
		return '<figure class="sa-province-cover" data-sa-province-cover="' . (int) $image['id'] . '">' . $html . '</figure>' . $content;
	}

	/** Even if the old global switch is enabled, province cards/heroes stay text-only. */
	public static function guard( $html, $post_id ) {
		if ( ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && 'province' === get_post_type( $post_id ) ) { return ''; }
		return $html;
	}

	public static function head_image_size( $original, $id, $size, $icon = false ) {
		if ( ! doing_action( 'wp_head' ) ) { return $original; }
		$image = self::image();
		return $image && (int) $id === $image['id'] ? array( $image['url'], $image['width'], $image['height'], false ) : $original;
	}
	public static function social_url( $url ) { $image = self::image(); return $image ? $image['url'] : $url; }
	public static function social_array( $original ) {
		$image = self::image();
		return $image ? array( 'id' => $image['id'], 'url' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'alt' => $image['alt'], 'type' => 'image/webp' ) : $original;
	}
	public static function social_width( $original ) { $image = self::image(); return $image ? $image['width'] : $original; }
	public static function social_height( $original ) { $image = self::image(); return $image ? $image['height'] : $original; }
	public static function social_alt( $alt ) { $image = self::image(); return $image ? $image['alt'] : $alt; }
	public static function facebook_tags( $tags ) {
		$image = self::image();
		if ( $image ) {
			$tags['og:image'] = $image['url']; $tags['og:image:secure_url'] = $image['url']; $tags['og:image:type'] = 'image/webp'; $tags['og:image:width'] = $image['width']; $tags['og:image:height'] = $image['height']; $tags['og:image:alt'] = $image['alt'];
		}
		return $tags;
	}
	public static function twitter_tags( $tags ) {
		$image = self::image();
		if ( $image ) { $tags['twitter:image'] = $image['url']; $tags['twitter:image:alt'] = $image['alt']; $tags['twitter:card'] = 'summary_large_image'; }
		return $tags;
	}

	public static function head() {
		$image = self::image();
		if ( ! $image ) { return; }
		// Known SEO providers own their tags; use their URL filters, not duplicate tags.
		if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\\Load' ) ) { return; }
		$theme_og = function_exists( 'sa_seo_head' );
		if ( ! $theme_og ) { echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '">' . "\n"; }
		echo '<meta property="og:image:width" content="' . (int) $image['width'] . '">' . "\n";
		echo '<meta property="og:image:height" content="' . (int) $image['height'] . '">' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( $image['alt'] ) . '">' . "\n";
		echo '<meta property="og:image:type" content="image/webp">' . "\n";
		echo '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '">' . "\n";
		echo '<meta name="twitter:image:alt" content="' . esc_attr( $image['alt'] ) . '">' . "\n";
		if ( ! $theme_og ) { echo '<meta name="twitter:card" content="summary_large_image">' . "\n"; }
	}
}
