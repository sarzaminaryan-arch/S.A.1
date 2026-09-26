<?php
/**
 * Custom post types for the 6 active entities (+ reserved Accommodation) — Level 1 & 4.
 *
 * show_in_rest is false on purpose: entities use the classic editor with structured meta boxes
 * so that every field of the data model is a real form field (no block soup).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register entity CPTs.
 */
function sa_register_post_types() {
	foreach ( sa_entity_types() as $type ) {
		$e = sa_entities_config()[ $type ];

		$labels = array(
			'name'                  => $e['plural'],
			'singular_name'         => $e['singular'],
			'menu_name'             => $e['plural'],
			'name_admin_bar'        => $e['singular'],
			'add_new'               => 'افزودن ' . $e['singular'],
			'add_new_item'          => 'افزودن ' . $e['singular'] . ' جدید',
			'edit_item'             => 'ویرایش ' . $e['singular'],
			'new_item'              => $e['singular'] . ' جدید',
			'view_item'             => 'مشاهده ' . $e['singular'],
			'view_items'            => 'مشاهده ' . $e['plural'],
			'search_items'          => 'جست‌وجوی ' . $e['plural'],
			'not_found'             => 'موردی یافت نشد.',
			'not_found_in_trash'    => 'موردی در زباله‌دان نیست.',
			'all_items'             => 'همه‌ی ' . $e['plural'],
			'archives'              => 'آرشیو ' . $e['plural'],
			'attributes'            => 'ویژگی‌های ' . $e['singular'],
			'insert_into_item'      => 'درج در ' . $e['singular'],
			'uploaded_to_this_item' => 'بارگذاری‌شده برای این ' . $e['singular'],
			'featured_image'        => 'تصویر شاخص ' . $e['singular'],
			'set_featured_image'    => 'انتخاب تصویر شاخص',
			'remove_featured_image' => 'حذف تصویر شاخص',
			'use_featured_image'    => 'استفاده به‌عنوان تصویر شاخص',
			'item_published'        => $e['singular'] . ' منتشر شد.',
			'item_updated'          => $e['singular'] . ' به‌روزرسانی شد.',
		);

		register_post_type(
			$type,
			array(
				'labels'              => $labels,
				'description'         => sprintf( 'موجودیت «%s» طبق مدل داده‌ی سرزمین آریان v%s', $e['label'], SA_MODEL_VERSION ),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => 'sarzaminaryan',
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => false,
				'exclude_from_search' => false,
				'has_archive'         => $e['url_base'],
				'hierarchical'        => false,
				'menu_icon'           => $e['icon'],
				'menu_position'       => 5 + (int) $e['menu_pos'],
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
				'taxonomies'          => $e['taxonomies'],
				'rewrite'             => array(
					'slug'       => $e['url_base'],
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				),
				'query_var'           => true,
				'delete_with_user'    => false,
			)
		);
	}
}
add_action( 'init', 'sa_register_post_types', 5 );

/**
 * Persian placeholders for the title field.
 *
 * @param string  $title Placeholder.
 * @param WP_Post $post  Post.
 * @return string
 */
function sa_title_placeholder( $title, $post ) {
	$e = sa_entity( $post->post_type );
	if ( $e ) {
		return 'نام ' . $e['singular'] . ' (مثلاً …)';
	}
	return $title;
}
add_filter( 'enter_title_here', 'sa_title_placeholder', 10, 2 );

/**
 * Basic Persian → Latin transliteration for slugs (owner should still write proper English slugs).
 *
 * @param string $text Persian text.
 * @return string ASCII text.
 */
function sa_transliterate_fa( $text ) {
	$map = array(
		'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's', 'ج' => 'j', 'چ' => 'ch',
		'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
		'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => '', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g',
		'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v', 'ؤ' => 'o', 'ه' => 'h', 'ة' => 'h', 'ی' => 'i', 'ي' => 'i', 'ئ' => 'y', 'ء' => '',
		'ً' => '', 'ٌ' => '', 'ٍ' => '', 'َ' => 'a', 'ُ' => 'o', 'ِ' => 'e', 'ّ' => '', 'ْ' => '', 'ـ' => '',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'‌' => '-', '‍' => '', '،' => '-', '؛' => '-', '؟' => '', ' ' => '-',
	);
	$text = strtr( (string) $text, $map );
	$text = remove_accents( $text );
	$text = strtolower( preg_replace( '/[^A-Za-z0-9\-]+/', '-', $text ) );
	return trim( preg_replace( '/-+/', '-', $text ), '-' );
}

/**
 * Level 4 slug rules: lowercase ASCII, hyphens, unique per CPT.
 * A Persian/encoded slug (or an empty one) is transliterated on publish instead of becoming %d8%a7… noise.
 *
 * @param array $data    Slashed post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function sa_enforce_ascii_slug( $data, $postarr ) {
	if ( ! sa_is_entity( $data['post_type'] ) || 'publish' !== $data['post_status'] ) {
		return $data;
	}
	$decoded = urldecode( (string) $data['post_name'] );
	if ( '' !== $decoded && ! preg_match( '/[^a-z0-9\-]/', $decoded ) ) {
		return $data; // already a clean ASCII slug.
	}
	$source = '' !== $decoded ? $decoded : wp_unslash( $data['post_title'] );
	$ascii  = sa_transliterate_fa( $source );
	if ( '' === $ascii ) {
		$ascii = $data['post_type'] . '-' . wp_generate_password( 6, false, false );
	}
	$post_id           = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	$data['post_name'] = wp_unique_post_slug( strtolower( $ascii ), $post_id, $data['post_status'], $data['post_type'], (int) $data['post_parent'] );
	return $data;
}
add_filter( 'wp_insert_post_data', 'sa_enforce_ascii_slug', 5, 2 );
