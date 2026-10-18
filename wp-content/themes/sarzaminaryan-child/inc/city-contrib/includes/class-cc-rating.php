<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Rating {

	const BAYESIAN_MIN_VOTES    = 5;
	const BAYESIAN_DEFAULT_MEAN = 4.0;
	const RANKING_TRANSIENT     = 'cc_ranking_v2';

	public static function init() {}

	/**
	 * Retired/alias slugs that must not appear as separate counties in rankings.
	 *
	 * @return string[]
	 */
	public static function excluded_slugs() {
		$slugs = array(
			'borazjan',
			'ijrud',
			'isfahan',
			'galikesh',
			'kabudarahang-city',
			'shahdad',
		);
		if ( function_exists( 'sa_redirect_map' ) ) {
			foreach ( array_keys( (array) sa_redirect_map() ) as $from ) {
				if ( 0 === strpos( (string) $from, '/city/' ) ) {
					$s = trim( substr( (string) $from, strlen( '/city/' ) ), '/' );
					if ( '' !== $s ) {
						$slugs[] = $s;
					}
				}
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Normalize any county post title to the canonical «شهرستان X» display form.
	 *
	 * @param int    $city_id   Post ID.
	 * @param string $raw_title Optional fallback title.
	 * @return string
	 */
	public static function format_county_name( $city_id, $raw_title = '' ) {
		$city_id = (int) $city_id;
		if ( $city_id > 0 && function_exists( 'sa_county_name' ) ) {
			$name = trim( (string) sa_county_name( $city_id, true ) );
			if ( '' !== $name ) {
				if ( 'شهرستان برازجان' === $name || 'برازجان' === $name ) {
					return 'شهرستان دشتستان';
				}
				return $name;
			}
		}
		$bare = '' !== trim( (string) $raw_title ) ? trim( (string) $raw_title ) : ( $city_id > 0 ? trim( (string) get_the_title( $city_id ) ) : '' );
		$bare = trim( (string) preg_replace( '/^(?:(?:شهرستان|شهر)\s+)+/u', '', $bare ) );
		if ( 'برازجان' === $bare ) {
			$bare = 'دشتستان';
		}
		return '' !== $bare ? 'شهرستان ' . $bare : 'شهرستان';
	}

	/**
	 * Compute Bayesian weighted rating score W = (v/(v+m))*R + (m/(v+m))*C.
	 *
	 * @param int   $votes      Number of votes for this county (v).
	 * @param float $avg_stars  Average rating of this county (R).
	 * @param float $global_avg Global prior mean across all ratings (C).
	 * @param int   $min_votes  Minimum confidence vote threshold (m).
	 * @return float
	 */
	public static function compute_bayesian_score( $votes, $avg_stars, $global_avg = self::BAYESIAN_DEFAULT_MEAN, $min_votes = self::BAYESIAN_MIN_VOTES ) {
		$v = max( 0, (int) $votes );
		if ( 0 === $v ) {
			return 0.0;
		}
		$m = max( 1, (int) $min_votes );
		$r = (float) $avg_stars;
		$c = $global_avg > 0 ? (float) $global_avg : self::BAYESIAN_DEFAULT_MEAN;
		return ( ( $v / ( $v + $m ) ) * $r ) + ( ( $m / ( $v + $m ) ) * $c );
	}

	/**
	 * Global average rating across all recorded votes (prior C).
	 *
	 * @return float
	 */
	public static function global_mean() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return self::BAYESIAN_DEFAULT_MEAN;
		}
		$t   = CC_DB::table();
		$avg = (float) $wpdb->get_var( "SELECT AVG(stars) FROM {$t}" );
		return $avg > 0 ? $avg : self::BAYESIAN_DEFAULT_MEAN;
	}

	/**
	 * Get rating summary + Bayesian rank for a single county.
	 *
	 * @param int    $city      County post ID.
	 * @param string $voter_key Viewer key.
	 * @return array<string,mixed>
	 */
	public static function get( $city, $voter_key = '' ) {
		global $wpdb;
		$city  = (int) $city;
		$t     = CC_DB::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT stars, COUNT(*) n FROM {$t} WHERE city_id = %d GROUP BY stars", $city ) );
		$dist  = array_fill( 1, 7, 0 );
		$sum   = 0;
		$count = 0;
		foreach ( (array) $rows as $r ) {
			$s = (int) $r->stars;
			$n = (int) $r->n;
			if ( $s >= 1 && $s <= 7 ) {
				$dist[ $s ] = $n;
				$sum       += $s * $n;
				$count     += $n;
			}
		}
		if ( 0 === $count ) {
			$count = (int) get_post_meta( $city, 'cc_rating_count', true );
			$avg   = (float) get_post_meta( $city, 'cc_rating_avg', true );
		} else {
			$avg = round( $sum / $count, 1 );
		}
		$weighted      = round( self::compute_bayesian_score( $count, $avg, self::global_mean(), self::BAYESIAN_MIN_VOTES ), 1 );
		$user_rating   = 0;
		$changeable_at = 0;
		if ( $voter_key ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT stars, updated_at FROM {$t} WHERE city_id = %d AND voter_key = %s", $city, $voter_key ) );
			if ( $row ) {
				$user_rating   = (int) $row->stars;
				$changeable_at = strtotime( $row->updated_at ) + DAY_IN_SECONDS;
			}
		}
		$rank = self::rank_of( $city );
		return array(
			'average'       => $avg,
			'weighted'      => $weighted,
			'count'         => $count,
			'distribution'  => array_values( $dist ),
			'user_rating'   => $user_rating,
			'locked'        => ( $user_rating > 0 && time() < $changeable_at ),
			'changeable_at' => $changeable_at,
			'rank'          => $rank,
			'total'         => self::total_cities(),
		);
	}

	/**
	 * Ordered list of rated county post IDs using Bayesian weighted average (m = 5).
	 *
	 * @return int[]
	 */
	public static function ranked_ids() {
		$cached = get_site_transient( self::RANKING_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$t        = CC_DB::table();
		$m        = (float) self::BAYESIAN_MIN_VOTES;
		$c        = self::global_mean();
		$excluded = self::excluded_slugs();
		$not_in   = '';
		if ( ! empty( $excluded ) ) {
			$esc = array();
			foreach ( $excluded as $slug ) {
				$esc[] = $wpdb->prepare( '%s', $slug );
			}
			$not_in = ' AND p.post_name NOT IN (' . implode( ',', $esc ) . ')';
		}

		$sql = $wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$t} r ON r.city_id = p.ID
			 WHERE p.post_type = 'city' AND p.post_status = 'publish'{$not_in}
			 GROUP BY p.ID
			 ORDER BY (((COUNT(r.id) / (COUNT(r.id) + %f)) * AVG(r.stars)) + ((%f / (COUNT(r.id) + %f)) * %f)) DESC,
			          AVG(r.stars) DESC,
			          COUNT(r.id) DESC,
			          p.post_title ASC
			 LIMIT 1000",
			$m,
			$m,
			$m,
			$c
		);
		$rows = $wpdb->get_results( $sql );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = (int) $r->ID;
		}
		set_site_transient( self::RANKING_TRANSIENT, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	public static function rank_of( $city ) {
		$pos = array_search( (int) $city, self::ranked_ids(), true );
		return false === $pos ? 0 : $pos + 1;
	}

	public static function total_cities() {
		if ( function_exists( 'sa_counties' ) ) {
			$cnt = count( (array) sa_counties() );
			if ( $cnt > 0 ) {
				return $cnt;
			}
		}
		$n = wp_count_posts( 'city' );
		return isset( $n->publish ) ? (int) $n->publish : 0;
	}

	public static function province_of( $id ) {
		if ( function_exists( 'sa_province_name_for_post' ) ) {
			$p = (string) sa_province_name_for_post( $id );
			if ( '' !== $p ) {
				return $p;
			}
		}
		$terms = get_the_terms( $id, 'province_tax' );
		if ( $terms && ! is_wp_error( $terms ) && isset( $terms[0] ) ) {
			return $terms[0]->name;
		}
		return '';
	}

	public static function sibling_count( $id ) {
		$terms = get_the_terms( $id, 'province_tax' );
		if ( $terms && ! is_wp_error( $terms ) && isset( $terms[0] ) ) {
			return (int) $terms[0]->count;
		}
		return 0;
	}

	/**
	 * Top N counties with Bayesian weighted score and normalized «شهرستان X» title.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top( $limit = 7 ) {
		global $wpdb;
		$ids  = array_slice( self::ranked_ids(), 0, (int) $limit );
		$c    = self::global_mean();
		$t    = CC_DB::table();
		$out  = array();
		foreach ( $ids as $i => $id ) {
			$row = isset( $wpdb ) && is_object( $wpdb )
				? $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(stars), 0) s, COALESCE(AVG(stars), 0) a, COUNT(*) cnt FROM {$t} WHERE city_id = %d", $id ) )
				: null;
			$cnt      = $row && (int) $row->cnt > 0 ? (int) $row->cnt : (int) get_post_meta( $id, 'cc_rating_count', true );
			$avg      = $row && (int) $row->cnt > 0 ? round( (float) $row->a, 1 ) : (float) get_post_meta( $id, 'cc_rating_avg', true );
			$sum      = $row && (int) $row->cnt > 0 ? (int) $row->s : (int) get_post_meta( $id, 'cc_rating_sum', true );
			$weighted = round( self::compute_bayesian_score( $cnt, $avg, $c, self::BAYESIAN_MIN_VOTES ), 1 );
			$out[]    = array(
				'id'       => $id,
				'rank'     => $i + 1,
				'title'    => self::format_county_name( $id ),
				'url'      => get_permalink( $id ),
				'avg'      => $avg,
				'weighted' => $weighted,
				'total'    => $sum,
				'count'    => $cnt,
				'province' => self::province_of( $id ),
				'siblings' => self::sibling_count( $id ),
			);
		}
		return $out;
	}
}
