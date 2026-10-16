<?php
/**
 * JSON-LD structured data (Level 5 + reference 04): one @graph per page.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Organization node.
 *
 * @return array
 */
function sa_schema_organization() {
	$node    = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( $logo ) {
			$node['logo'] = array(
				'@type'  => 'ImageObject',
				'url'    => $logo[0],
				'width'  => $logo[1],
				'height' => $logo[2],
			);
		}
	}
	$same_as = array();
	foreach ( array( 'instagram', 'telegram', 'x', 'youtube', 'aparat', 'linkedin' ) as $net ) {
		$url = get_theme_mod( 'sa_social_' . $net, '' );
		if ( $url ) {
			$same_as[] = $url;
		}
	}
	if ( $same_as ) {
		$node['sameAs'] = $same_as;
	}
	$email = get_theme_mod( 'sa_contact_email', '' );
	if ( $email ) {
		$node['email'] = $email;
	}
	return $node;
}

/**
 * WebSite node (+ SearchAction on home).
 *
 * @return array
 */
function sa_schema_website() {
	$node = array(
		'@type'      => 'WebSite',
		'@id'        => home_url( '/#website' ),
		'url'        => home_url( '/' ),
		'name'       => get_bloginfo( 'name' ),
		'inLanguage' => 'fa-IR',
		'publisher'  => array( '@id' => home_url( '/#organization' ) ),
	);
	if ( is_front_page() ) {
		$node['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);
	}
	return $node;
}

/**
 * Image object for a post.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function sa_schema_image( $post_id ) {
	if ( ! has_post_thumbnail( $post_id ) ) {
		return null;
	}
	$sa_thumb_id = (int) get_post_thumbnail_id( $post_id );
	// v2.15.0: تصویرِ بی‌فایل در داده ساخت‌یافته اعلام نمی‌شود.
	if ( $sa_thumb_id && function_exists( 'sa_media_attachment_missing' ) && sa_media_attachment_missing( $sa_thumb_id ) ) {
		return null;
	}
	$img = wp_get_attachment_image_src( $sa_thumb_id, function_exists( 'sa_cover_image_size' ) ? sa_cover_image_size( $post_id ) : 'sa-hero' );
	if ( ! $img ) {
		return null;
	}
	return array(
		'@type'  => 'ImageObject',
		'url'    => $img[0],
		'width'  => $img[1],
		'height' => $img[2],
	);
}

/**
 * Geo node.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function sa_schema_geo( $post_id ) {
	$c = sa_get_coords( $post_id );
	if ( ! $c ) {
		return null;
	}
	return array(
		'@type'     => 'GeoCoordinates',
		'latitude'  => $c[0],
		'longitude' => $c[1],
	);
}

/**
 * Minimal reference node for a related post.
 *
 * @param WP_Post $post  Post.
 * @param string  $type  Schema type.
 * @return array
 */
function sa_schema_ref( $post, $type ) {
	return array(
		'@type' => $type,
		'@id'   => get_permalink( $post ) . '#place',
		'name'  => get_the_title( $post ),
		'url'   => get_permalink( $post ),
	);
}

/**
 * Main node for an entity.
 *
 * @param WP_Post $post Post.
 * @return array
 */
function sa_schema_entity( $post ) {
	$id   = $post->ID;
	$type = $post->post_type;
	$node = array(
		'@id'         => get_permalink( $id ) . '#place',
		'name'        => get_the_title( $id ),
		'url'         => get_permalink( $id ),
		'description' => wp_strip_all_tags( sa_summary( $id, 50 ) ),
	);
	$img  = sa_schema_image( $id );
	if ( $img ) {
		$node['image'] = $img;
	}
	$geo = sa_schema_geo( $id );
	if ( $geo ) {
		$node['geo'] = $geo;
	}
	$city     = sa_get_parent( $id, 'city' );
	$province = sa_get_parent( $id, 'province' );

	switch ( $type ) {
		case 'province':
			$node['@type']            = array( 'AdministrativeArea', 'TouristDestination' );
			$node['containedInPlace'] = array(
				'@type'  => 'Country',
				'name'   => 'ایران',
				'sameAs' => 'https://www.wikidata.org/wiki/Q794',
			);
			$cities                   = array_slice( sa_get_children( $id, 'city' ), 0, 20 );
			if ( $cities ) {
				$node['containsPlace'] = array_map(
					function ( $city ) {
						return sa_schema_ref( $city, 'AdministrativeArea' );
					},
					$cities
				);
			}
			$attractions = array_slice( sa_get_children( $id, 'attraction' ), 0, 20 );
			if ( $attractions ) {
				$node['includesAttraction'] = array_map(
					function ( $attraction ) {
						return sa_schema_ref( $attraction, 'TouristAttraction' );
					},
					$attractions
				);
			}
			break;

		case 'city':
			// `city` is the locked technical CPT for county pages, not a municipal city.
			$node['@type'] = array( 'AdministrativeArea', 'TouristDestination' );
			if ( $province ) {
				$node['containedInPlace'] = sa_schema_ref( $province, 'AdministrativeArea' );
			}
			$attractions = array_slice( sa_get_children( $id, 'attraction' ), 0, 20 );
			if ( $attractions ) {
				$node['includesAttraction'] = array_map(
					function ( $attraction ) {
						return sa_schema_ref( $attraction, 'TouristAttraction' );
					},
					$attractions
				);
			}
			$map = get_post_meta( $id, 'sa_google_map_url', true );
			if ( $map ) {
				$node['hasMap'] = $map;
			}
			break;

		case 'attraction':
			$node['@type'] = 'TouristAttraction';
			if ( $city ) {
				$node['containedInPlace'] = sa_schema_ref( $city, 'AdministrativeArea' );
			}
			$address = get_post_meta( $id, 'sa_address', true );
			if ( $address ) {
				$postal_address = array(
					'@type'          => 'PostalAddress',
					'streetAddress'  => $address,
					'addressCountry' => 'IR',
				);
				$locality       = $city ? trim( (string) get_post_meta( $city->ID, 'sa_cty_center', true ) ) : '';
				if ( $locality ) {
					$postal_address['addressLocality'] = $locality;
				}
				if ( $province ) {
					$postal_address['addressRegion'] = sa_province_name_for_post( $province );
				}
				$node['address'] = $postal_address;
			}
			$hours = get_post_meta( $id, 'sa_opening_hours', true );
			if ( $hours ) {
				$node['openingHours'] = $hours;
			}
			$price = get_post_meta( $id, 'sa_ticket_price', true );
			if ( $price ) {
				if ( preg_match( '/رایگان|free/iu', $price ) ) {
					$node['isAccessibleForFree'] = true;
				} else {
					$node['isAccessibleForFree'] = false;
					$node['publicAccess']        = true;
				}
			}
			$types = wp_get_post_terms( $id, 'attraction_type', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $types ) && $types ) {
				$node['touristType'] = $types;
			}
			$official = get_post_meta( $id, 'sa_official_website', true ); // v1.1.
			if ( $official ) {
				$node['sameAs'] = array( esc_url_raw( $official ) );
			}
			break;

		case 'travel_route':
			$node['@type'] = 'TouristTrip';
			$stops         = array();
			foreach ( sa_get_related( $id, 'sa_city_ids' ) as $c ) {
				$stops[] = sa_schema_ref( $c, 'AdministrativeArea' );
			}
			foreach ( sa_get_related( $id, 'sa_attraction_ids' ) as $a ) {
				$stops[] = sa_schema_ref( $a, 'TouristAttraction' );
			}
			if ( $stops ) {
				$node['itinerary'] = array(
					'@type'           => 'ItemList',
					'numberOfItems'   => count( $stops ),
					'itemListElement' => array_map(
						function ( $s, $i ) {
							return array(
								'@type'    => 'ListItem',
								'position' => $i + 1,
								'item'     => $s,
							);
						},
						$stops,
						array_keys( $stops )
					),
				);
			}
			$durations = wp_get_post_terms( $id, 'travel_duration', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $durations ) && $durations ) {
				$node['touristType'] = $durations;
			}
			break;

		case 'local_food':
			$node['@type']         = 'Recipe';
			$node['recipeCuisine'] = 'Iranian';
			$ingredients           = sa_list( get_post_meta( $id, 'sa_main_ingredients', true ) );
			if ( $ingredients ) {
				$node['recipeIngredient'] = $ingredients;
			}
			$node['author'] = array( '@id' => home_url( '/#organization' ) );
			if ( $city ) {
				$node['spatialCoverage'] = sa_schema_ref( $city, 'AdministrativeArea' );
			}
			unset( $node['geo'] );
			break;

		case 'souvenir':
			$node['@type']           = 'Product';
			$node['countryOfOrigin'] = array(
				'@type' => 'Country',
				'name'  => 'IR',
			);
			$where                   = get_post_meta( $id, 'sa_purchase_location', true );
			if ( $where ) {
				$node['additionalProperty'] = array(
					'@type' => 'PropertyValue',
					'name'  => 'محل خرید',
					'value' => $where,
				);
			}
			unset( $node['geo'] );
			break;

		case 'accommodation':
			$node['@type'] = 'LodgingBusiness';
			if ( $city ) {
				$node['containedInPlace'] = sa_schema_ref( $city, 'AdministrativeArea' );
			}
			break;

		default:
			$node['@type'] = 'Place';
	}
	return $node;
}

/**
 * Article node for blog posts.
 *
 * @param WP_Post $post Post.
 * @return array
 */
function sa_schema_article( $post ) {
	$node = array(
		'@type'            => 'BlogPosting',
		'@id'              => get_permalink( $post ) . '#article',
		'headline'         => wp_trim_words( get_the_title( $post ), 18, '' ),
		'url'              => get_permalink( $post ),
		'datePublished'    => get_the_date( 'c', $post ),
		'dateModified'     => get_the_modified_date( 'c', $post ),
		'inLanguage'       => 'fa-IR',
		'mainEntityOfPage' => get_permalink( $post ),
		'author'           => array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			'url'   => get_author_posts_url( $post->post_author ),
		),
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
	);
	// v2.13.0 — editorial guides carry a team byline (sa_byline): the author is the
	// organisation, never an invented person, and the type is a plain Article.
	$byline = trim( (string) get_post_meta( $post->ID, 'sa_byline', true ) );
	if ( '' !== $byline ) {
		$node['@type']  = 'Article';
		$node['author'] = array(
			'@type' => 'Organization',
			'name'  => $byline,
			'url'   => home_url( '/' ),
		);
	}
	$img = sa_schema_image( $post->ID );
	if ( $img ) {
		$node['image'] = $img;
	}
	$desc = sa_summary( $post, 40 );
	if ( $desc ) {
		$node['description'] = $desc;
	}
	return $node;
}

/**
 * Legacy FAQPage helper retained for compatibility; FAQPage output is disabled.
 *
 * The FAQ accordion remains visible to readers, but the theme intentionally emits no
 * FAQPage JSON-LD. Do not restore structured output solely to seek a Google rich result.
 *
 * @deprecated data-model v1.2 — no longer outputs structured data.
 * @param int $post_id Post ID (unused).
 * @return null
 */
function sa_schema_faq( $post_id ) {
	unset( $post_id );
	return null;
}

/**
 * BreadcrumbList node from the breadcrumb trail.
 *
 * @return array|null
 */
function sa_schema_breadcrumbs() {
	$items = function_exists( 'sa_get_breadcrumb_items' ) ? sa_get_breadcrumb_items() : array();
	if ( count( $items ) < 2 ) {
		return null;
	}
	$list = array();
	foreach ( $items as $i => $item ) {
		$entry = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $item['name'],
		);
		if ( ! empty( $item['url'] ) ) {
			$entry['item'] = $item['url'];
		}
		$list[] = $entry;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $list,
	);
}

/**
 * Return the appropriate schema type for a normal static page.
 *
 * @param WP_Post $post Page post.
 * @return string
 */
function sa_schema_static_page_type( $post ) {
	if ( 'page' !== $post->post_type ) {
		return 'WebPage';
	}
	if ( 'about' === $post->post_name ) {
		return 'AboutPage';
	}
	if ( 'contact' === $post->post_name ) {
		return 'ContactPage';
	}
	return 'WebPage';
}

/**
 * Remove repeated top-level JSON-LD nodes sharing the same @id.
 *
 * @param array $nodes JSON-LD nodes.
 * @return array
 */
function sa_schema_dedupe_nodes( $nodes ) {
	$seen = array();
	foreach ( $nodes as $key => $node ) {
		if ( '@graph' === $key && is_array( $node ) ) {
			$nodes[ $key ] = sa_schema_dedupe_nodes( $node );
			continue;
		}
		if ( ! is_array( $node ) || empty( $node['@id'] ) ) {
			continue;
		}
		$id = (string) $node['@id'];
		if ( isset( $seen[ $id ] ) ) {
			unset( $nodes[ $key ] );
			continue;
		}
		$seen[ $id ] = true;
	}
	return $nodes;
}

/**
 * Correct Rank Math static page types without changing post/article schema.
 *
 * @param array $data Rank Math JSON-LD nodes.
 * @return array
 */
function sa_rank_math_static_page_schema( $data ) {
	if ( ! is_page() ) {
		return $data;
	}
	$type      = sa_schema_static_page_type( get_queried_object() );
	$container = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? '@graph' : '';
	$node_list = '' !== $container ? $data[ $container ] : $data;
	$has_page  = false;
	foreach ( $node_list as $key => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		if ( false !== stripos( (string) $key, 'webpage' ) || array_intersect( $types, array( 'WebPage', 'AboutPage', 'ContactPage' ) ) ) {
			$node_list[ $key ]['@type'] = $type;

			$has_page = true;
		}
	}
	if ( ! $has_page ) {
		$post = get_queried_object();
		$url  = get_permalink( $post );

		$node_list['sa_static_page'] = array(
			'@type'      => $type,
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => get_the_title( $post ),
			'inLanguage' => 'fa-IR',
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		);
	}
	if ( '' !== $container ) {
		$data[ $container ] = $node_list;
	} else {
		$data = $node_list;
	}
	return sa_schema_dedupe_nodes( $data );
}
add_filter( 'rank_math/json_ld', 'sa_rank_math_static_page_schema', 99 );

/**
 * Build and print the graph.
 *
 * With an SEO plugin active (Rank Math, Yoast, AIOSEO, SEOPress, TSF) the plugin owns the
 * site-level nodes — Organization, WebSite, WebPage/Article, BreadcrumbList — so the theme
 * prints only what no plugin can build from its meta boxes: the entity node
 * (AdministrativeArea/TouristDestination/…). Visible FAQ content remains in the page;
 * the theme intentionally emits no FAQPage JSON-LD. Node @ids follow the same
 * `home_url( '/#organization' )` convention as the plugins, so references resolve.
 * `add_filter( 'sa_schema_with_plugin', '__return_false' )` switches the theme graph off
 * completely when a plugin is active (v1.0.1 behaviour).
 */
function sa_schema_output() {
	if ( is_admin() || is_feed() || is_404() ) {
		return;
	}
	$plugin = sa_seo_plugin_active();
	if ( $plugin && ! apply_filters( 'sa_schema_with_plugin', true ) ) {
		return;
	}
	$graph = array();
	if ( ! $plugin ) {
		$graph[] = sa_schema_organization();
		$graph[] = sa_schema_website();
		$bc      = sa_schema_breadcrumbs();
		if ( $bc ) {
			$graph[] = $bc;
		}
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( sa_is_entity( $post ) ) {
			$graph[] = sa_schema_entity( $post );
		} elseif ( ! $plugin && 'post' === $post->post_type ) {
			$graph[] = sa_schema_article( $post );
		} elseif ( ! $plugin ) {
			$graph[] = array(
				'@type'       => sa_schema_static_page_type( $post ),
				'@id'         => get_permalink( $post ) . '#webpage',
				'url'         => get_permalink( $post ),
				'name'        => get_the_title( $post ),
				'description' => sa_summary( $post, 30 ),
				'inLanguage'  => 'fa-IR',
				'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			);
		}
	} elseif ( ! $plugin && ( is_post_type_archive() || is_tax() || is_category() ) ) {
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'url'        => sa_seo_canonical(),
			'name'       => wp_strip_all_tags( sa_seo_title() ),
			'inLanguage' => 'fa-IR',
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		);
	}

	$graph = apply_filters( 'sa_schema_graph', $graph );
	$graph = sa_schema_dedupe_nodes( (array) $graph );
	if ( ! $graph ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sa_schema_output', 30 );
