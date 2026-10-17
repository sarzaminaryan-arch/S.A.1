<?php
/** Related counties: bounded single-join relation read, random selection in PHP. */
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
function __( $s ) { return $s; }
function sa_county_profile_province_post( $id ) { return $GLOBALS['province']; }
function sa_cards_section( $posts, $heading ) { $GLOBALS['cards'] = array( $posts, $heading ); }
function sa_related_assert( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } }
class WP_Query {
	public $posts = array();
	public function __construct( $args ) {
		$GLOBALS['card_queries'][] = $args;
		sa_related_assert( empty( $args['meta_query'] ), 'No multi-join meta_query in related-card render' );
		sa_related_assert( 'rand' !== ( $args['orderby'] ?? '' ), 'No database RAND sorting in related-card render' );
		$this->posts = array_slice( $args['post__in'], 0, 3 );
	}
}
class SA_Related_DB {
	public $posts = 'IR_posts';
	public $postmeta = 'IR_postmeta';
	public $queries = array();
	public $rows = array();
	public function prepare( $sql, ...$args ) {
		sa_related_assert( array( 'province_id', 'sa_province_id', 'city', 'publish' ) === $args, 'Relation keys, post type and status prepared' );
		return $sql;
	}
	public function get_results( $sql ) {
		$this->queries[] = $sql;
		sa_related_assert( substr_count( strtoupper( $sql ), 'JOIN' ) === 1, 'One bounded join, not a self-join per meta clause' );
		sa_related_assert( strpos( $sql, 'm.meta_key IN' ) !== false, 'Only the two relation keys are read' );
		sa_related_assert( strpos( $sql, 'WHERE p.post_type = %s AND p.post_status = %s' ) !== false, 'Relation read also excludes draft/private/non-county posts' );
		sa_related_assert( ! preg_match( '/RAND|NOT EXISTS|CAST|GROUP BY/i', $sql ), 'No random sort, negative subqueries or cast/group explosion' );
		return $this->rows;
	}
}
function sa_relation_row( $id, $key, $value ) { return (object) array( 'ID' => $id, 'meta_key' => $key, 'meta_value' => $value ); }
$GLOBALS['wpdb'] = new SA_Related_DB();
$GLOBALS['province'] = (object) array( 'ID' => 9 );
$GLOBALS['card_queries'] = array();
$GLOBALS['wpdb']->rows = array(
	sa_relation_row( 42, 'province_id', '9' ),
	sa_relation_row( 43, 'province_id', '9' ),
	sa_relation_row( 44, 'province_id', '8' ),
	sa_relation_row( 45, 'sa_province_id', '8' ),
	sa_relation_row( 46, 'province_id', '' ), sa_relation_row( 46, 'sa_province_id', '8' ),
	sa_relation_row( 47, 'province_id', '9' ), sa_relation_row( 47, 'sa_province_id', '8' ),
	sa_relation_row( 48, 'province_id', '0' ), sa_relation_row( 48, 'sa_province_id', '8' ),
	sa_relation_row( 49, 'province_id', '8' ), sa_relation_row( 49, 'province_id', '9' ),
	sa_relation_row( 50, 'province_id', '-1' ),
	sa_relation_row( 51, 'province_id', 'not-a-province' ),
);
require dirname( __DIR__ ) . '/inc/county-page.php';
sa_county_related_articles( 42 );
sa_related_assert( 1 === count( $GLOBALS['wpdb']->queries ) && 1 === count( $GLOBALS['card_queries'] ), 'Exactly one relation query and one three-card WP_Query, not N+1 per county' );
$args = end( $GLOBALS['card_queries'] );
$ids = $args['post__in']; sort( $ids );
sa_related_assert( array( 44, 45, 46, 49 ) === $ids, 'Canonical precedence, empty-only legacy fallback, same-province/current/unknown exclusion, first meta value wins' );
sa_related_assert( 'post__in' === $args['orderby'] && 3 === $args['posts_per_page'] && true === $args['no_found_rows'], 'Only three cards hydrated, PHP-shuffled order respected, no pagination count' );
sa_related_assert( 'city' === $args['post_type'] && 'publish' === $args['post_status'], 'Final WP query rechecks public county visibility' );
sa_related_assert( 'مقالات مرتبط' === $GLOBALS['cards'][1], 'Heading unchanged' );

$GLOBALS['wpdb']->rows = array( sa_relation_row( 42, 'province_id', '9' ), sa_relation_row( 43, 'province_id', '9' ) );
$count = count( $GLOBALS['card_queries'] );
sa_county_related_articles( 42 );
sa_related_assert( $count === count( $GLOBALS['card_queries'] ), 'Empty candidate list must not turn post__in=[] into every post' );
$GLOBALS['province'] = null;
$count = count( $GLOBALS['wpdb']->queries );
sa_county_related_articles( 42 );
sa_related_assert( $count === count( $GLOBALS['wpdb']->queries ), 'No relation query without a known current province' );

// Large synthetic directory: SQL shape stays fixed and hydration still requests three cards.
$GLOBALS['province'] = (object) array( 'ID' => 9 );
$GLOBALS['wpdb']->rows = array();
for ( $i = 100; $i < 1100; ++$i ) { $GLOBALS['wpdb']->rows[] = sa_relation_row( $i, 'province_id', $i % 2 ? '8' : '9' ); }
sa_county_related_articles( 42 );
$args = end( $GLOBALS['card_queries'] );
sa_related_assert( 500 === count( $args['post__in'] ) && 3 === $args['posts_per_page'], 'No arbitrary ID cutoff; candidate count cannot grow the number of SQL joins' );
echo "PASS: related county SQL query budget, canonical/legacy precedence, visibility and empty-list guard\n";
