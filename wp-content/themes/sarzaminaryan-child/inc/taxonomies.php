<?php
/**
 * Taxonomies (Level 2) + idempotent term seeding.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register taxonomies from config.
 */
function sa_register_taxonomies() {
	$active_types = sa_entity_types();

	foreach ( sa_taxonomies_config() as $tax => $t ) {
		if ( 'reserved' === $t['status'] && ! SA_ENABLE_ACCOMMODATION ) {
			continue;
		}
		$objects = array_values( array_intersect( $t['applies_to'], $active_types ) );
		if ( empty( $objects ) ) {
			continue;
		}

		$labels = array(
			'name'              => $t['plural'],
			'singular_name'     => $t['singular'],
			'menu_name'         => $t['plural'],
			'search_items'      => 'جست‌وجوی ' . $t['plural'],
			'all_items'         => 'همه‌ی ' . $t['plural'],
			'parent_item'       => $t['singular'] . ' مادر',
			'parent_item_colon' => $t['singular'] . ' مادر:',
			'edit_item'         => 'ویرایش ' . $t['singular'],
			'update_item'       => 'به‌روزرسانی ' . $t['singular'],
			'add_new_item'      => 'افزودن ' . $t['singular'],
			'new_item_name'     => 'نام ' . $t['singular'] . ' جدید',
			'not_found'         => 'موردی یافت نشد.',
			'no_terms'          => 'بدون ' . $t['singular'],
			'back_to_items'     => '→ بازگشت به ' . $t['plural'],
		);

		register_taxonomy(
			$tax,
			$objects,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => (bool) $t['hierarchical'],
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_admin_column'  => true,
				'show_in_rest'       => false,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'         => $t['slug'],
					'with_front'   => false,
					'hierarchical' => false,
				),
				// Editors may not create provinces (fixed list of 31) — only admins.
				'capabilities'       => 'province_tax' === $tax ? array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				) : array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}
}
add_action( 'init', 'sa_register_taxonomies', 4 );

/**
 * Provinces dataset.
 *
 * @return array[]
 */
function sa_provinces_data() {
	static $data = null;
	if ( null === $data ) {
		$data = include SA_CHILD_DIR . 'data/provinces.php';
	}
	return $data;
}

/**
 * Seed all fixed terms (safe to run repeatedly).
 *
 * @return int Number of terms created.
 */
function sa_seed_terms() {
	$created = 0;

	if ( taxonomy_exists( 'province_tax' ) ) {
		foreach ( sa_provinces_data() as $p ) {
			if ( term_exists( $p['slug'], 'province_tax' ) ) {
				continue;
			}
			$term = wp_insert_term(
				$p['name'],
				'province_tax',
				array(
					'slug'        => $p['slug'],
					'description' => sprintf( 'استان %s — مرکز: %s', $p['name'], $p['center'] ),
				)
			);
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], 'sa_name_en', $p['en'] );
				update_term_meta( $term['term_id'], 'sa_center', $p['center'] );
				$created++;
			}
		}
	}

	foreach ( sa_taxonomies_config() as $tax => $t ) {
		if ( ! taxonomy_exists( $tax ) || empty( $t['terms'] ) ) {
			continue;
		}
		foreach ( $t['terms'] as $term ) {
			if ( term_exists( $term['slug'], $tax ) ) {
				continue;
			}
			$res = wp_insert_term( $term['name'], $tax, array( 'slug' => $term['slug'] ) );
			if ( ! is_wp_error( $res ) ) {
				$created++;
			}
		}
	}

	// A few editorial categories for blog posts.
	foreach ( array(
		'travel-guide'  => 'راهنمای سفر',
		'tourism-news'  => 'اخبار گردشگری',
		'culture'       => 'فرهنگ و آداب',
		'travel-tips'   => 'نکات سفر',
	) as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			$res = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $res ) ) {
				$created++;
			}
		}
	}

	return $created;
}

/**
 * Get the province_tax term matching a Province post (by slug), creating the link if needed.
 *
 * @param int $province_post_id Province post ID.
 * @return WP_Term|null
 */
function sa_province_term_for_post( $province_post_id ) {
	$post = get_post( $province_post_id );
	if ( ! $post || 'province' !== $post->post_type ) {
		return null;
	}
	$term_id = (int) get_post_meta( $post->ID, 'sa_province_term_id', true );
	$term    = $term_id ? get_term( $term_id, 'province_tax' ) : null;
	if ( $term && ! is_wp_error( $term ) ) {
		return $term;
	}
	$term = get_term_by( 'slug', $post->post_name, 'province_tax' );
	if ( ! $term ) {
		$terms = wp_get_post_terms( $post->ID, 'province_tax' );
		$term  = ! is_wp_error( $terms ) && $terms ? $terms[0] : null;
	}
	if ( $term ) {
		update_post_meta( $post->ID, 'sa_province_term_id', $term->term_id );
		update_term_meta( $term->term_id, 'sa_province_post_id', $post->ID );
	}
	return $term ? $term : null;
}

/**
 * Province post for a province_tax term (if the hub page exists).
 *
 * @param int|WP_Term $term Term.
 * @return WP_Post|null
 */
function sa_province_post_for_term( $term ) {
	$term = get_term( $term, 'province_tax' );
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	$post_id = (int) get_term_meta( $term->term_id, 'sa_province_post_id', true );
	if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
		return get_post( $post_id );
	}
	$found = get_posts(
		array(
			'post_type'      => 'province',
			'name'           => $term->slug,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);
	if ( $found ) {
		update_term_meta( $term->term_id, 'sa_province_post_id', $found[0]->ID );
		return $found[0];
	}
	return null;
}

/**
 * Nice Persian term names for the fixed vocabularies when only a slug is known.
 *
 * @param string $tax  Taxonomy.
 * @param string $slug Term slug.
 * @return string
 */
function sa_term_label( $tax, $slug ) {
	$config = sa_taxonomies_config();
	if ( isset( $config[ $tax ]['terms'] ) ) {
		foreach ( $config[ $tax ]['terms'] as $t ) {
			if ( $t['slug'] === $slug ) {
				return $t['name'];
			}
		}
	}
	$term = get_term_by( 'slug', $slug, $tax );
	return $term ? $term->name : $slug;
}
