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
	$node = array(
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
	$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'sa-hero' );
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
	$img = sa_schema_image( $id );
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
			$node['@type']          = array( 'AdministrativeArea', 'TouristDestination' );
			$node['containedInPlace'] = array( '@type' => 'Country', 'name' => 'ایران', 'sameAs' => 'https://www.wikidata.org/wiki/Q794' );
			$cities = array_slice( sa_get_children( $id, 'city' ), 0, 20 );
			if ( $cities ) {
				$node['containsPlace'] = array_map( function ( $c ) { return sa_schema_ref( $c, 'City' ); }, $cities );
			}
			$attractions = array_slice( sa_get_children( $id, 'attraction' ), 0, 20 );
			if ( $attractions ) {
				$node['includesAttraction'] = array_map( function ( $a ) { return sa_schema_ref( $a, 'TouristAttraction' ); }, $attractions );
			}
			break;

		case 'city':
			$node['@type'] = array( 'City', 'TouristDestination' );
			if ( $province ) {
				$node['containedInPlace'] = sa_schema_ref( $province, 'AdministrativeArea' );
			}
			$attractions = array_slice( sa_get_children( $id, 'attraction' ), 0, 20 );
			if ( $attractions ) {
				$node['includesAttraction'] = array_map( function ( $a ) { return sa_schema_ref( $a, 'TouristAttraction' ); }, $attractions );
			}
			$map = get_post_meta( $id, 'sa_google_map_url', true );
			if ( $map ) {
				$node['hasMap'] = $map;
			}
			break;

		case 'attraction':
			$node['@type'] = 'TouristAttraction';
			if ( $city ) {
				$node['containedInPlace'] = sa_schema_ref( $city, 'City' );
			}
			$address = get_post_meta( $id, 'sa_address', true );
			if ( $address ) {
				$node['address'] = array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $address,
					'addressLocality' => $city ? get_the_title( $city ) : '',
					'addressRegion'   => $province ? get_the_title( $province ) : '',
					'addressCountry'  => 'IR',
				);
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
			$official = get_post_meta( $id, 'sa_official_website', true ); // v1.1
			if ( $official ) {
				$node['sameAs'] = array( esc_url_raw( $official ) );
			}
			break;

		case 'travel_route':
			$node['@type'] = 'TouristTrip';
			$stops         = array();
			foreach ( sa_get_related( $id, 'sa_city_ids' ) as $c ) {
				$stops[] = sa_schema_ref( $c, 'City' );
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
				$node['spatialCoverage'] = sa_schema_ref( $city, 'City' );
			}
			unset( $node['geo'] );
			break;

		case 'souvenir':
			$node['@type']           = 'Product';
			$node['countryOfOrigin'] = array( '@type' => 'Country', 'name' => 'IR' );
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
				$node['containedInPlace'] = sa_schema_ref( $city, 'City' );
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
 * FAQPage node from sa_faq meta.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function sa_schema_faq( $post_id ) {
	$faq = sa_get_faq( $post_id );
	if ( count( $faq ) < 1 ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => get_permalink( $post_id ) . '#faq',
		'mainEntity' => array_map(
			function ( $row ) {
				return array(
					'@type'          => 'Question',
					'name'           => $row['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $row['a'],
					),
				);
			},
			$faq
		),
	);
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
 * Build and print the graph.
 */
function sa_schema_output() {
	if ( sa_seo_plugin_active() || is_admin() || is_feed() || is_404() ) {
		return;
	}
	$graph = array( sa_schema_organization(), sa_schema_website() );

	$bc = sa_schema_breadcrumbs();
	if ( $bc ) {
		$graph[] = $bc;
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( sa_is_entity( $post ) ) {
			$graph[] = sa_schema_entity( $post );
		} elseif ( 'post' === $post->post_type ) {
			$graph[] = sa_schema_article( $post );
		} else {
			$graph[] = array(
				'@type'       => 'WebPage',
				'@id'         => get_permalink( $post ) . '#webpage',
				'url'         => get_permalink( $post ),
				'name'        => get_the_title( $post ),
				'description' => sa_summary( $post, 30 ),
				'inLanguage'  => 'fa-IR',
				'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			);
		}
		$faq = sa_schema_faq( $post->ID );
		if ( $faq ) {
			$graph[] = $faq;
		}
	} elseif ( is_post_type_archive() || is_tax() || is_category() ) {
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'url'        => sa_seo_canonical(),
			'name'       => wp_strip_all_tags( sa_seo_title() ),
			'inLanguage' => 'fa-IR',
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		);
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => apply_filters( 'sa_schema_graph', $graph ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sa_schema_output', 30 );
