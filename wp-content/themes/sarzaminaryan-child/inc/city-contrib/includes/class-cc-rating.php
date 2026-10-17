<?php
/** Five-star presentation with reversible encoding in the existing seven-point table. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class CC_Rating {
	public static function init() {}

	/** Legacy votes remain untouched. Every stored vote is normalized before aggregation. */
	public static function display_stars( $stored ) {
		return max( 1, min( 5, (int) round( (float) $stored * 5 / 7 ) ) );
	}

	/** 1..5 -> 1,3,4,6,7; round-trip exact, no schema change or bulk migration. */
	public static function storage_stars( $stars ) {
		return (int) round( (int) $stars * 7 / 5 );
	}

	public static function get( $city, $voter_key = '' ) {
		global $wpdb;
		$table = CC_DB::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT stars,COUNT(*) n FROM $table WHERE city_id=%d GROUP BY stars", $city ) );
		$dist = array_fill( 1, 5, 0 );
		foreach ( (array) $rows as $row ) {
			$dist[ self::display_stars( $row->stars ) ] += (int) $row->n;
		}
		$count = array_sum( $dist );
		$sum = 0;
		foreach ( $dist as $stars => $votes ) {
			$sum += $stars * $votes;
		}
		$user_rating = 0;
		$changeable_at = 0;
		if ( $voter_key ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT stars,updated_at FROM $table WHERE city_id=%d AND voter_key=%s", $city, $voter_key ) );
			if ( $row ) {
				$user_rating = self::display_stars( $row->stars );
				$changeable_at = strtotime( $row->updated_at ) + DAY_IN_SECONDS;
			}
		}
		return array( 'average' => $count ? round( $sum / $count, 1 ) : 0, 'sum' => $sum, 'count' => $count,
			'distribution' => array_values( $dist ), 'user_rating' => $user_rating,
			'locked' => $user_rating > 0 && time() < $changeable_at, 'changeable_at' => $changeable_at,
			'rank' => self::rank_of( $city ), 'total' => self::total_cities(), 'scale' => 5 );
	}

	public static function ranked_ids() {
		$cached = get_site_transient( 'cc_ranking_5' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = CC_DB::table();
		$rows = $wpdb->get_results( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN $table r ON r.city_id=p.ID WHERE p.post_type='city' AND p.post_status='publish' GROUP BY p.ID ORDER BY SUM(ROUND(r.stars*5.0/7)) DESC, COUNT(r.id) ASC, p.ID ASC LIMIT 1000" );
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = (int) $row->ID;
		}
		set_site_transient( 'cc_ranking_5', $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}
	public static function rank_of( $city ) {
		$position = array_search( (int) $city, self::ranked_ids(), true );
		return false === $position ? 0 : $position + 1;
	}
	public static function total_cities() {
		$count = wp_count_posts( 'city' );
		return isset( $count->publish ) ? (int) $count->publish : 0;
	}
	public static function province_of( $id ) {
		$terms = get_the_terms( $id, 'province_tax' );
		return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
	}
	public static function sibling_count( $id ) {
		$terms = get_the_terms( $id, 'province_tax' );
		return $terms && ! is_wp_error( $terms ) ? (int) $terms[0]->count : 0;
	}
	public static function top( $limit = 7 ) {
		$out = array();
		foreach ( array_slice( self::ranked_ids(), 0, (int) $limit ) as $index => $id ) {
			$data = self::get( $id );
			$out[] = array( 'id' => $id, 'rank' => $index + 1, 'title' => get_the_title( $id ), 'url' => get_permalink( $id ),
				'avg' => $data['average'], 'total' => $data['sum'], 'count' => $data['count'],
				'province' => self::province_of( $id ), 'siblings' => self::sibling_count( $id ) );
		}
		return $out;
	}
}
