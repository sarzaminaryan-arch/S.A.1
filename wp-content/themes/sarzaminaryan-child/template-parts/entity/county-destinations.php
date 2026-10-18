<?php
/** Compact destination links below (never restyling) the county identity card. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_destination_id = get_the_ID();
if ( 'city' !== get_post_type( $sa_destination_id ) ) {
	return;
}
$sa_destination_key = static function ( $name ) {
	return trim( preg_replace( '/[\s\x{200c}]+/u', ' ', strtr( $name, array( 'ي' => 'ی', 'ك' => 'ک' ) ) ) );
};
$sa_destinations = array();
$sa_destination_rows = function_exists( 'sa_county_profile_attractions' ) ? sa_county_profile_attractions( $sa_destination_id, PHP_INT_MAX ) : array();
foreach ( $sa_destination_rows as $sa_destination ) {
	$sa_key = $sa_destination_key( $sa_destination['name'] );
	if ( '' === $sa_key ) {
		continue;
	}
	if ( ! isset( $sa_destinations[ $sa_key ] ) ) {
		$sa_destinations[ $sa_key ] = array( 'name' => $sa_destination['name'], 'en' => '', 'url' => '', 'details' => array() );
	}
	// A published relation takes precedence over a legacy unlinked POI.
	if ( '' !== $sa_destination['url'] && '' !== esc_url( $sa_destination['url'] ) ) {
		$sa_destinations[ $sa_key ]['url'] = $sa_destination['url'];
		$sa_destinations[ $sa_key ]['en'] = $sa_destination['en'];
	}
}
// Merge all four legacy buckets, keeping existing notes/distances, not their type labels.
if ( function_exists( 'sa_county_lines' ) && function_exists( 'sa_county_parse_poi' ) ) {
	foreach ( array( 'sa_cty_poi_nature', 'sa_cty_poi_offbeat', 'sa_cty_poi_recreation', 'sa_cty_poi_heritage' ) as $sa_meta_key ) {
		foreach ( sa_county_lines( get_post_meta( $sa_destination_id, $sa_meta_key, true ) ) as $sa_line ) {
			$sa_poi = sa_county_parse_poi( $sa_line );
			$sa_key = $sa_destination_key( $sa_poi['name'] );
			if ( '' === $sa_key ) {
				continue;
			}
			if ( ! isset( $sa_destinations[ $sa_key ] ) ) {
				$sa_destinations[ $sa_key ] = array( 'name' => $sa_poi['name'], 'en' => '', 'url' => '', 'details' => array() );
			}
			foreach ( array( $sa_poi['distance'], $sa_poi['note'] ) as $sa_detail ) {
				if ( '' !== $sa_detail && ! in_array( $sa_detail, $sa_destinations[ $sa_key ]['details'], true ) ) {
					$sa_destinations[ $sa_key ]['details'][] = $sa_detail;
				}
			}
		}
	}
}
$sa_linked_destinations = array_filter( $sa_destinations, static function ( $item ) { return '' !== $item['url']; } );
$sa_unlinked_destinations = array_filter( $sa_destinations, static function ( $item ) { return '' === $item['url']; } );
$sa_destination_siblings = function_exists( 'sa_county_profile_sibling_cities' ) ? sa_county_profile_sibling_cities( $sa_destination_id, PHP_INT_MAX ) : array();
$sa_destination_siblings = array_filter( $sa_destination_siblings, static function ( $item ) use ( $sa_destination_id ) {
	return '' !== esc_url( $item['url'] ) && $item['url'] !== get_permalink( $sa_destination_id );
} );
if ( function_exists( 'sa_toc_register' ) ) {
	if ( $sa_destinations ) { sa_toc_register( 'جاذبه‌های شهرستان', 'county-attractions', 6 ); }
	if ( $sa_destination_siblings ) { sa_toc_register( 'شهرهای هم‌استانی', 'county-siblings', 7 ); }
}
?>
<?php if ( $sa_destinations ) : ?>
	<section class="sa-destination-section" id="county-attractions" aria-labelledby="sa-destinations-title">
		<span id="county-visit" class="sa-destination-section__anchor" aria-hidden="true"></span>
		<h2 id="sa-destinations-title">جاذبه‌های شهرستان</h2>
		<?php if ( $sa_linked_destinations ) : ?>
			<ul class="sa-destination-grid">
				<?php foreach ( $sa_linked_destinations as $sa_destination ) : ?>
					<li><a class="sa-destination-card" href="<?php echo esc_url( $sa_destination['url'] ); ?>">
						<strong><?php echo esc_html( $sa_destination['name'] ); ?></strong>
						<?php if ( '' !== $sa_destination['en'] ) : ?><small lang="en" dir="ltr"><?php echo esc_html( $sa_destination['en'] ); ?></small><?php endif; ?>
						<?php if ( $sa_destination['details'] ) : ?><span class="sa-destination-card__detail"><?php echo esc_html( implode( ' — ', $sa_destination['details'] ) ); ?></span><?php endif; ?>
					</a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $sa_unlinked_destinations ) : ?>
			<?php // No fabricated destination URLs or link-like glow for places without a published page. ?>
			<ul class="sa-destination-notes" aria-label="دیگر جاذبه‌های ثبت‌شده">
				<?php foreach ( $sa_unlinked_destinations as $sa_destination ) : ?>
					<li><strong><?php echo esc_html( $sa_destination['name'] ); ?></strong><?php if ( $sa_destination['details'] ) : ?> — <?php echo esc_html( implode( ' — ', $sa_destination['details'] ) ); ?><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endif; ?>
<?php if ( $sa_destination_siblings ) : ?>
	<section class="sa-destination-section" id="county-siblings" aria-labelledby="sa-destination-siblings-title">
		<h2 id="sa-destination-siblings-title">شهرهای هم‌استانی</h2>
		<ul class="sa-destination-grid">
			<?php foreach ( $sa_destination_siblings as $sa_sibling ) : ?>
				<li><a class="sa-destination-card" href="<?php echo esc_url( $sa_sibling['url'] ); ?>"><?php echo esc_html( $sa_sibling['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
