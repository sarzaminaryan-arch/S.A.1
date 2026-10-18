<?php
/**
 * Read-only phase-3/5 source export. Never updates posts, meta, or theme options.
 * wp --user=ADMIN_LOGIN eval-file tools/article-review/export.php -- --output=/private/path/articles.jsonl
 * The destination must already have a private parent directory outside ABSPATH.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'Run through WP-CLI on the actual WordPress installation; no mock fallback.' );
}
if ( ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'An administrator WP-CLI context is required to export article source.' );
}
$sa_review_args = isset( $args ) && is_array( $args ) ? $args : ( isset( $argv ) ? $argv : array() );
$sa_review_output = '';
$sa_review_expected = 480;
foreach ( $sa_review_args as $sa_review_arg ) {
	if ( 0 === strpos( $sa_review_arg, '--output=' ) ) {
		$sa_review_output = substr( $sa_review_arg, 9 );
	} elseif ( 0 === strpos( $sa_review_arg, '--expected=' ) ) {
		$sa_review_value = substr( $sa_review_arg, 11 );
		if ( ! ctype_digit( $sa_review_value ) ) {
			throw new InvalidArgumentException( 'Expected count must be a nonnegative integer.' );
		}
		$sa_review_expected = (int) $sa_review_value;
	} elseif ( 0 === strpos( $sa_review_arg, '--' ) && '--' !== $sa_review_arg ) {
		throw new InvalidArgumentException( 'Unknown export option: ' . $sa_review_arg );
	}
}
$sa_review_parent = realpath( dirname( $sa_review_output ) );
$sa_review_root   = realpath( ABSPATH );
if ( '' === $sa_review_output || false === $sa_review_parent || false === $sa_review_root ) {
	throw new RuntimeException( 'Specify --output in an existing private directory outside the WordPress directory.' );
}
if ( $sa_review_parent === $sa_review_root || 0 === strpos( $sa_review_parent . DIRECTORY_SEPARATOR, $sa_review_root . DIRECTORY_SEPARATOR ) ) {
	throw new RuntimeException( 'Refusing to put article source inside the WordPress directory.' );
}
$sa_review_path = $sa_review_parent . DIRECTORY_SEPARATOR . basename( $sa_review_output );
$sa_review_umask = umask( 0077 );
$sa_review_handle = fopen( $sa_review_path, 'x' ); // Exclusive: never overwrite an earlier export.
umask( $sa_review_umask );
if ( false === $sa_review_handle ) {
	throw new RuntimeException( 'Cannot create export; existing exports are never overwritten.' );
}
if ( ! chmod( $sa_review_path, 0600 ) ) {
	fclose( $sa_review_handle );
	throw new RuntimeException( 'Cannot restrict export permissions; no article content was exported.' );
}
clearstatcache( true, $sa_review_path );
if ( 0 !== ( fileperms( $sa_review_path ) & 0077 ) ) {
	fclose( $sa_review_handle );
	throw new RuntimeException( 'Export permissions are not private; no article content was exported.' );
}

function sa_review_export_line( $handle, $record ) {
	$line = wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( false === $line ) {
		throw new RuntimeException( 'Cannot encode export record.' );
	}
	$line .= "\n";
	$offset = 0;
	while ( $offset < strlen( $line ) ) {
		$written = fwrite( $handle, substr( $line, $offset ) );
		if ( false === $written || 0 === $written ) {
			throw new RuntimeException( 'Export write failed; a file without a complete footer must not be used.' );
		}
		$offset += $written;
	}
}

$sa_review_types = array_values( array_filter( array( 'post', 'city', 'province', 'attraction', 'travel_route', 'local_food', 'souvenir' ), 'post_type_exists' ) );
$sa_review_count = 0;
try {
	sa_review_export_line( $sa_review_handle, array(
		'kind' => 'header', 'schema' => 'sa-article-review-v1', 'provenance' => 'wordpress_export',
		'site' => home_url( '/' ), 'exported_at' => gmdate( 'c' ), 'post_types' => $sa_review_types,
		'expected_articles' => $sa_review_expected, 'scope' => 'published', 'read_only' => true,
		'theme_version' => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : null,
	) );
	for ( $sa_review_page = 1; ; ++$sa_review_page ) {
		$sa_review_ids = get_posts( array(
			'post_type' => $sa_review_types, 'post_status' => 'publish', 'posts_per_page' => 50,
			'paged' => $sa_review_page, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids',
			'suppress_filters' => true, 'no_found_rows' => true,
		) );
		if ( empty( $sa_review_ids ) ) {
			break;
		}
		foreach ( $sa_review_ids as $sa_review_id ) {
			$sa_review_post = get_post( $sa_review_id );
			if ( ! $sa_review_post || 'publish' !== $sa_review_post->post_status ) {
				throw new RuntimeException( 'A post changed during export; export again from a stable snapshot.' );
			}
			$sa_review_identity = array();
			if ( 'city' === $sa_review_post->post_type && function_exists( 'sa_county_profile_fields' ) ) {
				foreach ( sa_county_profile_fields() as $sa_review_key => $sa_review_field ) {
					$sa_review_identity[ $sa_review_key ] = array(
						'label' => $sa_review_field['label'],
						'canonical' => get_post_meta( $sa_review_id, $sa_review_key, true ),
						'legacy' => ! empty( $sa_review_field['legacy'] ) ? get_post_meta( $sa_review_id, $sa_review_field['legacy'], true ) : '',
					);
				}
			}
			sa_review_export_line( $sa_review_handle, array(
				'kind' => 'article', 'id' => (int) $sa_review_id, 'slug' => $sa_review_post->post_name,
				'post_type' => $sa_review_post->post_type, 'title' => $sa_review_post->post_title,
				'url' => get_permalink( $sa_review_id ), 'modified_gmt' => $sa_review_post->post_modified_gmt,
				'content' => $sa_review_post->post_content, 'content_sha256' => hash( 'sha256', $sa_review_post->post_content ),
				'excerpt' => $sa_review_post->post_excerpt, 'sources' => get_post_meta( $sa_review_id, 'sa_sources', true ),
				// Preserve raw FAQ, including malformed/empty values, for editorial review.
				// Do not call sa_get_faq(): it silently drops malformed/incomplete rows.
				'faq_raw' => get_post_meta( $sa_review_id, 'sa_faq', true ),
				'facts_checked' => get_post_meta( $sa_review_id, 'sa_facts_checked', true ),
				'identity_fields' => $sa_review_identity,
				'identity_coverage' => empty( $sa_review_identity ) ? 'unavailable' : 'county_canonical_and_legacy_meta_only',
			) );
			++$sa_review_count;
		}
	}
	sa_review_export_line( $sa_review_handle, array(
		'kind' => 'summary', 'complete' => true, 'articles' => $sa_review_count,
		'expected_articles' => $sa_review_expected, 'count_matches_expected' => $sa_review_count === $sa_review_expected,
		'consistent_database_snapshot' => false, 'database_writes' => 0,
	) );
} finally {
	fclose( $sa_review_handle );
}
echo 'Read-only export complete: ' . $sa_review_count . ' published articles; database writes=0.' . PHP_EOL;
if ( $sa_review_count !== $sa_review_expected ) {
	echo 'WARNING: Actual count differs from the expected count; do not claim coverage of 480 articles.' . PHP_EOL;
}
