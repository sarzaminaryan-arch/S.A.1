<?php
/**
 * Explicit post-type/taxonomy to audit-profile mapping.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Profile_Mapper {
	/**
	 * Available contract profiles.
	 *
	 * @return array
	 */
	public static function profiles() {
		$data = IAAE_Rules::all();
		return isset( $data['profiles'] ) && is_array( $data['profiles'] ) ? $data['profiles'] : array();
	}

	/**
	 * Post types whose labels are an exact contract profile name can be mapped safely.
	 * Ambiguous site-specific types intentionally remain unmapped.
	 *
	 * @return array<string,string>
	 */
	public static function map() {
		$stored = IAAE_Database::get_setting( 'profile_map', array() );
		$map    = array();
		$known  = array_keys( self::profiles() );
		$types  = get_post_types( array( 'public' => true ), 'names' );
		foreach ( (array) $types as $type ) {
			if ( in_array( $type, $known, true ) ) {
				$map[ $type ] = $type;
			} elseif ( 'city' === $type && in_array( 'county', $known, true ) ) {
				// API 1.1 compatibility alias only: the site's canonical entity remains City.
				$map[ $type ] = 'county';
			}
		}
		if ( is_array( $stored ) ) {
			foreach ( $stored as $type => $profile ) {
				$type    = sanitize_key( $type );
				$profile = sanitize_key( $profile );
				if ( 'city' === $type && in_array( $type, (array) $types, true ) && in_array( 'county', $known, true ) ) {
					$map[ $type ] = 'county';
					continue;
				}
				if ( in_array( $type, (array) $types, true ) && ( '' === $profile || in_array( $profile, $known, true ) ) ) {
					if ( '' === $profile ) {
						if ( 'city' === $type && in_array( 'county', $known, true ) ) {
							$map[ $type ] = 'county'; // Keep the canonical City CPT addressable through API 1.1.
						} else {
							unset( $map[ $type ] );
						}
					} else {
						$map[ $type ] = $profile;
					}
				}
			}
		}
		return $map;
	}

	/**
	 * Resolve a post's profile. Unmapped types are not guessed.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function for_post( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}
		$map = self::map();
		return isset( $map[ $post->post_type ] ) ? $map[ $post->post_type ] : '';
	}

	/**
	 * Resolve canonical location fields from the Master Data Model's province taxonomy.
	 * The legacy API `county` column carries the City name for the site's `city` CPT.
	 *
	 * @param WP_Post $post    Post.
	 * @param string  $profile Audit profile.
	 * @return array{province:string,county:string}
	 */
	public static function location_fields( $post, $profile = '' ) {
		$location = array( 'province' => '', 'county' => '' );
		if ( ! ( $post instanceof WP_Post ) ) {
			return $location;
		}

		if ( function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( (int) $post->ID, 'province_tax' );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) && 1 === count( $terms ) && isset( $terms[0]->name ) ) {
				$location['province'] = (string) $terms[0]->name;
			}
		}
		if ( 'province' === $profile && '' === $location['province'] ) {
			$location['province'] = (string) $post->post_title;
		}
		if ( 'county' === $profile ) {
			$location['county'] = (string) $post->post_title;
		}
		return $location;
	}

	/**
	 * Mapping status, including public post types requiring an owner decision.
	 *
	 * @return array
	 */
	public static function health() {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$map    = self::map();
		$unmapped = array();
		foreach ( (array) $types as $type => $object ) {
			if ( in_array( $type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			if ( ! isset( $map[ $type ] ) ) {
				$unmapped[] = array(
					'post_type' => (string) $type,
					'label'     => is_object( $object ) && isset( $object->labels->name ) ? (string) $object->labels->name : (string) $type,
				);
			}
		}
		return array(
			'map'      => $map,
			'unmapped' => $unmapped,
			'complete' => empty( $unmapped ),
		);
	}

	/**
	 * Validate and store an explicit map submitted by an administrator.
	 *
	 * @param array $map Submitted post type map.
	 * @return true|WP_Error
	 */
	public static function save( $map ) {
		if ( ! is_array( $map ) ) {
			return new WP_Error( 'iaa_invalid_param', 'نگاشت پروفایل باید آرایه باشد.', array( 'status' => 400 ) );
		}
		$types   = get_post_types( array( 'public' => true ), 'names' );
		$profiles = array_keys( self::profiles() );
		$clean   = array();
		foreach ( $map as $type => $profile ) {
			$type    = sanitize_key( $type );
			$profile = sanitize_key( $profile );
			if ( ! in_array( $type, (array) $types, true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'نوع‌نوشتهٔ نامعتبر در نگاشت: ' . $type, array( 'status' => 400 ) );
			}
			if ( '' !== $profile && ! in_array( $profile, $profiles, true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'پروفایل نامعتبر برای ' . $type, array( 'status' => 400 ) );
			}
			if ( 'city' === $type && 'county' !== $profile ) {
				return new WP_Error( 'iaa_invalid_param', 'نوع‌نوشتهٔ city موجودیت City است و فقط alias سازگاری قواعد API 1.1 را می‌پذیرد.', array( 'status' => 400 ) );
			}
			$clean[ $type ] = $profile;
		}
		return IAAE_Database::set_setting( 'profile_map', $clean )
			? true
			: new WP_Error( 'iaa_engine_error', 'ذخیرهٔ نگاشت در جدول افزونه ناموفق بود.', array( 'status' => 500 ) );
	}
}
