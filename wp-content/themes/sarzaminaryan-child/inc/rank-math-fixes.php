<?php
/**
 * Rank Math fallbacks and empty-archive SEO rules.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read Rank Math metadata, accepting both the plugin key and legacy importer key.
 *
 * @param int    $post_id Post ID.
 * @param string $field   Meta field without the Rank Math prefix.
 * @return string
 */
function sa_rank_math_meta_value( $post_id, $field ) {
	foreach ( array( '_rank_math_' . $field, 'rank_math_' . $field ) as $meta_key ) {
		$value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return '';
}

/**
 * Read Rank Math term metadata, accepting both common key forms.
 *
 * @param int    $term_id Term ID.
 * @param string $field   Meta field without the Rank Math prefix.
 * @return string
 */
function sa_rank_math_term_meta_value( $term_id, $field ) {
	foreach ( array( 'rank_math_' . $field, '_rank_math_' . $field ) as $meta_key ) {
		$value = trim( (string) get_term_meta( $term_id, $meta_key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return '';
}

/**
 * Whether a saved description is blank or a known editorial placeholder.
 *
 * @param string $description Saved description.
 * @return bool
 */
function sa_rank_math_description_is_placeholder( $description ) {
	$description = trim( wp_strip_all_tags( (string) $description ) );
	if ( '' === $description ) {
		return true;
	}
	$patterns = array(
		'/lorem ipsum/i',
		'/add (?:a )?(?:meta )?description/i',
		'/description (?:goes )?here/i',
		'/توضیحات? (?:متا|سئو) را اینجا بنویسید/u',
		'/ایمیل و راه‌های تماس را اینجا بنویسید/u',
		'/این متن را با معرفی واقعی تیم/u',
		'/جایگزین کنید/u',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $description ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Identify the static page context for the approved description fallbacks.
 *
 * @return array{0:int,1:string}|array{0:int,1:''}
 */
function sa_rank_math_static_page_context() {
	if ( is_home() && ! is_front_page() ) {
		$post_id = (int) get_option( 'page_for_posts' );
		if ( $post_id && 'blog' === get_post_field( 'post_name', $post_id ) ) {
			return array( $post_id, 'blog' );
		}
		return array( $post_id, '' );
	}
	if ( is_page( 'about' ) ) {
		return array( get_queried_object_id(), 'about' );
	}
	if ( is_page( 'contact' ) ) {
		return array( get_queried_object_id(), 'contact' );
	}
	return array( 0, '' );
}

/**
 * Approved static-page descriptions, based on the theme's page roles/content templates.
 *
 * @param string $key Page key.
 * @return string
 */
function sa_rank_math_static_page_description( $key ) {
	$descriptions = array(
		'blog'    => 'راهنماها و نوشته‌های سفر برای آشنایی با استان‌ها، شهرستان‌ها و دیدنی‌های ایران؛ ایده‌هایی برای انتخاب مقصد و برنامه‌ریزی بازدید.',
		'about'   => 'سرزمین آریان دانشنامهٔ سفر ایران است؛ راهنماهای استان‌ها، شهرستان‌ها و دیدنی‌ها برای شناخت مقصد و برنامه‌ریزی آگاهانهٔ سفر.',
		'contact' => 'برای پرسش، پیشنهاد یا گزارش خطا دربارهٔ راهنماهای سفر، از راه‌های ارتباطی سرزمین آریان استفاده کنید.',
	);
	return isset( $descriptions[ $key ] ) ? $descriptions[ $key ] : '';
}

/**
 * Return a published-object count for a public taxonomy term.
 *
 * @param WP_Term $term Term object.
 * @return int
 */
function sa_taxonomy_published_count( $term ) {
	static $counts = array();
	if ( ! ( $term instanceof WP_Term ) ) {
		return 0;
	}
	$key = $term->taxonomy . ':' . (int) $term->term_id;
	if ( isset( $counts[ $key ] ) ) {
		return $counts[ $key ];
	}
	$taxonomy = get_taxonomy( $term->taxonomy );
	$types    = $taxonomy ? array_values( (array) $taxonomy->object_type ) : array();
	$types    = array_values(
		array_filter(
			$types,
			function ( $type ) {
				$object = get_post_type_object( $type );
				return $object && $object->public;
			}
		)
	);
	if ( ! $types ) {
		$counts[ $key ] = 0;
		return 0;
	}

	$query          = new WP_Query(
		array(
			'post_type'              => $types,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => true,
				),
			),
		)
	);
	$counts[ $key ] = max( 0, (int) $query->found_posts );
	return $counts[ $key ];
}

/**
 * Build a generic archive description from its term name, optional term text and published count.
 *
 * @param WP_Term $term Term.
 * @return string
 */
function sa_taxonomy_auto_description( $term ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$name        = trim( wp_strip_all_tags( (string) $term->name ) );
	$description = trim( wp_strip_all_tags( (string) $term->description ) );
	$count       = sa_taxonomy_published_count( $term );
	$lead        = '' !== $description
		? wp_trim_words( $description, 20, '…' )
		: sprintf( 'محتوای مرتبط با «%s» را در این آرشیو مرور کنید.', $name );
	$count_text  = number_format_i18n( $count ) . ' مورد منتشرشده';
	return trim( $lead . ' در حال حاضر ' . $count_text . ' در دسترس است.' );
}

/**
 * Check whether a public post type has at least one published item.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function sa_post_type_has_published_items( $post_type ) {
	$counts = wp_count_posts( $post_type );
	return $counts && isset( $counts->publish ) && (int) $counts->publish > 0;
}

/**
 * Whether the current queried archive has any published records.
 *
 * @return bool|null True when populated, false when empty, null outside supported archives.
 */
function sa_current_archive_has_published_items() {
	if ( is_post_type_archive() ) {
		$post_types = get_query_var( 'post_type' );
		$post_types = is_array( $post_types ) ? $post_types : array( $post_types );
		$post_types = array_filter( array_map( 'sanitize_key', $post_types ) );
		if ( ! $post_types ) {
			$queried = get_queried_object();
			if ( is_object( $queried ) && isset( $queried->name ) ) {
				$post_types = array( sanitize_key( $queried->name ) );
			}
		}
		if ( ! $post_types ) {
			return null;
		}
		foreach ( $post_types as $post_type ) {
			if ( sa_post_type_has_published_items( $post_type ) ) {
				return true;
			}
		}
		return false;
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		return $term instanceof WP_Term ? sa_taxonomy_published_count( $term ) > 0 : null;
	}
	return null;
}

/**
 * Rank Math title source precedence: saved Rank Math title first, then sa_seo_title.
 *
 * @param string $title Rank Math's current title.
 * @return string
 */
function sa_rank_math_title_fallback( $title ) {
	if ( is_admin() || ! is_singular() ) {
		return $title;
	}
	$post_id     = get_queried_object_id();
	$rank_math   = sa_rank_math_meta_value( $post_id, 'title' );
	$theme_title = trim( (string) get_post_meta( $post_id, 'sa_seo_title', true ) );
	if ( '' !== $rank_math ) {
		return '' !== trim( (string) $title ) ? $title : $rank_math;
	}
	return '' !== $theme_title ? $theme_title : $title;
}
add_filter( 'rank_math/frontend/title', 'sa_rank_math_title_fallback', 99 );

/**
 * Rank Math description fallback for approved pages and terms, only when saved text is empty/placeholder.
 *
 * @param string $description Rank Math's current description.
 * @return string
 */
function sa_rank_math_description_fallback( $description ) {
	if ( is_admin() ) {
		return $description;
	}
	list( $post_id, $page_key ) = sa_rank_math_static_page_context();
	if ( $post_id && '' !== $page_key ) {
		$saved = sa_rank_math_meta_value( $post_id, 'description' );
		if ( sa_rank_math_description_is_placeholder( $saved ) ) {
			$fallback = sa_rank_math_static_page_description( $page_key );
			return '' !== $fallback ? $fallback : $description;
		}
		return $description;
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && sa_rank_math_description_is_placeholder( sa_rank_math_term_meta_value( $term->term_id, 'description' ) ) ) {
			return sa_taxonomy_auto_description( $term );
		}
	}
	return $description;
}
add_filter( 'rank_math/frontend/description', 'sa_rank_math_description_fallback', 99 );

/**
 * Apply noindex,follow to empty post-type and taxonomy archives in Rank Math.
 *
 * @param array $robots Rank Math robots directives.
 * @return array
 */
function sa_rank_math_empty_archive_robots( $robots ) {
	if ( false === sa_current_archive_has_published_items() ) {
		unset( $robots['index'], $robots['nofollow'] );
		$robots['noindex'] = 'noindex';
		$robots['follow']  = 'follow';
	}
	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'sa_rank_math_empty_archive_robots', 99 );

/**
 * Apply WordPress core noindex,follow directives to empty archives when Rank Math is inactive.
 *
 * @param array $robots Core robots directives.
 * @return array
 */
function sa_empty_archive_wp_robots( $robots ) {
	if ( defined( 'RANK_MATH_VERSION' ) || function_exists( 'rank_math' ) ) {
		return $robots;
	}
	if ( false === sa_current_archive_has_published_items() ) {
		unset( $robots['index'], $robots['nofollow'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'sa_empty_archive_wp_robots', 30 );

/**
 * Omit empty public post-type archives from Rank Math sitemaps. First publish restores them automatically.
 *
 * @param string[] $excluded Excluded post type names.
 * @return string[]
 */
function sa_rank_math_exclude_empty_post_type_sitemaps( $excluded ) {
	$excluded = (array) $excluded;
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $object ) {
		if ( ! empty( $object->has_archive ) && ! sa_post_type_has_published_items( $name ) ) {
			$excluded[] = $name;
		}
	}
	return array_values( array_unique( $excluded ) );
}
add_filter( 'rank_math/sitemap/exclude_post_type', 'sa_rank_math_exclude_empty_post_type_sitemaps', 10 );

/**
 * Omit empty taxonomy-term URLs from Rank Math sitemaps.
 *
 * @param mixed           $url    Sitemap entry.
 * @param string          $type   Entry type.
 * @param WP_Post|WP_Term $entry Entry object.
 * @return mixed
 */
function sa_rank_math_exclude_empty_term_sitemap_entry( $url, $type, $entry ) {
	if ( $entry instanceof WP_Term && 0 === sa_taxonomy_published_count( $entry ) ) {
		return false;
	}
	return $url;
}
add_filter( 'rank_math/sitemap/entry', 'sa_rank_math_exclude_empty_term_sitemap_entry', 10, 3 );

/**
 * Taxonomy archive intro for attraction types and travel seasons, with links to published matching content.
 *
 * @return string Safe HTML or empty string.
 */
function sa_taxonomy_intro_html() {
	if ( ! is_tax( array( 'attraction_type', 'travel_season' ) ) ) {
		return '';
	}
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$taxonomy = get_taxonomy( $term->taxonomy );
	$types    = $taxonomy ? array_values( (array) $taxonomy->object_type ) : array();
	$types    = array_values(
		array_filter(
			$types,
			function ( $type ) {
				$object = get_post_type_object( $type );
				return $object && $object->public;
			}
		)
	);
	$posts    = $types ? get_posts(
		array(
			'post_type'              => $types,
			'post_status'            => 'publish',
			'posts_per_page'         => 5,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => true,
				),
			),
		)
	) : array();

	$intro = '';
	if ( '' === trim( wp_strip_all_tags( (string) $term->description ) ) ) {
		$intro = 'attraction_type' === $term->taxonomy
			? sprintf( 'جاذبه‌های «%s» را مرور کنید و برای اطلاعات مقصد و بازدید، صفحهٔ هر مکان را ببینید.', $term->name )
			: sprintf( 'مقصدهای مرتبط با فصل «%s» را ببینید و جزئیات هر مکان را برای برنامه‌ریزی سفر بخوانید.', $term->name );
	}
	$label    = 'attraction_type' === $term->taxonomy ? 'نوع جاذبه' : 'فصل سفر';
	$title_id = 'sa-taxonomy-intro-' . sanitize_html_class( $term->taxonomy . '-' . $term->term_id );
	$html     = '<section class="sa-taxonomy-intro" aria-labelledby="' . esc_attr( $title_id ) . '">'
		. '<h2 id="' . esc_attr( $title_id ) . '">' . esc_html( ( '' !== $intro ? $label : 'محتوای مرتبط با ' . $label ) . ': ' . $term->name ) . '</h2>';
	if ( '' !== $intro ) {
		$html .= '<p>' . esc_html( $intro ) . '</p>';
	}
	if ( $posts ) {
		$html .= '<nav aria-label="محتوای مرتبط"><h3>محتوای مرتبط</h3><ul>';
		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );
			if ( $permalink ) {
				$html .= '<li><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
			}
		}
		$html .= '</ul></nav>';
	}
	return $html . '</section>';
}
