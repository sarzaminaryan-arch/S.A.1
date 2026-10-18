<?php
/**
 * Cyber-Tech Light-Mode Province Identity Card — «شناسنامه استان» (v2.19.2).
 *
 * Dedicated Cyan-Blue technological tabular card for Iran's 31 provinces.
 * Uses only canonical helpers from `inc/province-profile.php` and `inc/template-tags.php`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_slug = sanitize_key( (string) get_post_field( 'post_name', $sa_id ) );

if ( function_exists( 'sa_toc_register' ) ) {
	sa_toc_register( 'شناسنامه استان', 'province-identity', 5 );
}

$sa_profile = function_exists( 'sa_province_profile' ) ? sa_province_profile( $sa_slug ) : null;
$sa_profile = is_array( $sa_profile ) ? $sa_profile : array();
$sa_stats   = isset( $sa_profile['stats'] ) && is_array( $sa_profile['stats'] ) ? $sa_profile['stats'] : array();

$sa_catalog         = function_exists( 'sa_province_profile_catalog' ) ? sa_province_profile_catalog() : array();
$sa_province_record = isset( $sa_catalog['by_slug'][ $sa_slug ] ) && is_array( $sa_catalog['by_slug'][ $sa_slug ] ) ? $sa_catalog['by_slug'][ $sa_slug ] : array();
$sa_geo             = function_exists( 'sa_region_province' ) ? sa_region_province( $sa_slug ) : array();
$sa_geo             = is_array( $sa_geo ) ? $sa_geo : array();

$sa_province_title = function_exists( 'sa_province_name_for_post' )
	? sa_province_name_for_post( $sa_id, true )
	: ( ! empty( $sa_profile['name'] ) ? 'استان ' . (string) $sa_profile['name'] : (string) get_the_title( $sa_id ) );

// 1) English Name.
$sa_en_raw = trim( (string) get_post_meta( $sa_id, 'sa_english_name', true ) );
if ( '' === $sa_en_raw && ! empty( $sa_province_record['en'] ) ) {
	$sa_en_raw = (string) $sa_province_record['en'];
}
if ( '' === $sa_en_raw && function_exists( 'sa_cyber_id_province_en' ) ) {
	$sa_en_raw = sa_cyber_id_province_en( $sa_slug );
}
$sa_en_full = '' !== $sa_en_raw
	? ( false === stripos( $sa_en_raw, 'province' ) ? $sa_en_raw . ' Province' : $sa_en_raw )
	: '';

// 2) Capital City & County Entries.
$sa_county_posts   = function_exists( 'sa_province_profile_county_posts' ) ? sa_province_profile_county_posts( $sa_id ) : array();
$sa_county_entries = function_exists( 'sa_province_profile_county_entries' ) ? sa_province_profile_county_entries( $sa_slug, $sa_id, $sa_county_posts ) : array();
$sa_center_name    = ! empty( $sa_province_record['center'] )
	? (string) $sa_province_record['center']
	: ( ! empty( $sa_geo['capital'] ) ? (string) $sa_geo['capital'] : '' );
$sa_center_post    = ( '' !== $sa_center_name && function_exists( 'sa_province_profile_center_post' ) )
	? sa_province_profile_center_post( $sa_slug, $sa_county_posts )
	: null;
$sa_center_en      = ( '' !== $sa_center_name && function_exists( 'sa_province_profile_center_english' ) )
	? sa_province_profile_center_english( $sa_slug, $sa_center_name, $sa_center_post )
	: '';

$sa_cap_html = '';
if ( '' !== $sa_center_name ) {
	$sa_cap_label = esc_html( $sa_center_name );
	if ( '' !== $sa_center_en ) {
		$sa_cap_label .= ' <span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( $sa_center_en ) . ')</span>';
	}
	if ( $sa_center_post instanceof WP_Post ) {
		$sa_cap_html = '<a class="sa-cyber-id__cell-link" href="' . esc_url( get_permalink( $sa_center_post ) ) . '">' . $sa_cap_label . '</a>';
	} else {
		$sa_cap_html = $sa_cap_label;
	}
}

// 3) Population.
$sa_pop_val = '';
if ( ! empty( $sa_stats['population'] ) ) {
	$sa_pop_val = sa_fa_digits( number_format( (int) $sa_stats['population'] ) ) . ' نفر';
} elseif ( ! empty( $sa_geo['population'] ) ) {
	$sa_pop_val = sa_fa_digits( number_format( (int) $sa_geo['population'] ) ) . ' نفر';
} else {
	$sa_meta_pop = trim( (string) get_post_meta( $sa_id, 'sa_province_population', true ) );
	if ( '' !== $sa_meta_pop ) {
		$sa_pop_val = sa_fa_digits( $sa_meta_pop );
	}
}

// 4) Area.
$sa_page_area = get_post_meta( $sa_id, 'sa_province_area', true );
$sa_area_num  = is_numeric( $sa_page_area ) && (float) $sa_page_area > 0
	? (float) $sa_page_area
	: ( isset( $sa_geo['area'] ) && is_numeric( $sa_geo['area'] ) ? (float) $sa_geo['area'] : 0 );
$sa_area_val  = $sa_area_num > 0 ? ( sa_fa_digits( number_format( (int) round( $sa_area_num ) ) ) . ' کیلومتر مربع' ) : '';

// 5 & 6) Divisions.
$sa_county_count   = isset( $sa_stats['county_count'] ) ? (int) $sa_stats['county_count'] : count( $sa_county_entries );
$sa_district_count = isset( $sa_stats['district_count'] ) ? (int) $sa_stats['district_count'] : 0;
$sa_city_count     = isset( $sa_stats['city_count'] ) ? (int) $sa_stats['city_count'] : 0;
$sa_rural_count    = isset( $sa_stats['rural_district_count'] ) ? (int) $sa_stats['rural_district_count'] : 0;
$sa_village_count  = isset( $sa_stats['settlement_count'] ) ? (int) $sa_stats['settlement_count'] : 0;

$sa_div_parts = array();
if ( $sa_county_count > 0 ) {
	$sa_div_parts[] = sa_fa_digits( (string) $sa_county_count ) . ' شهرستان';
}
if ( $sa_district_count > 0 ) {
	$sa_div_parts[] = sa_fa_digits( (string) $sa_district_count ) . ' بخش';
}
$sa_div_val = implode( ' · ', $sa_div_parts );

$sa_settle_parts = array();
if ( $sa_city_count > 0 ) {
	$sa_settle_parts[] = sa_fa_digits( (string) $sa_city_count ) . ' شهر';
}
if ( $sa_rural_count > 0 ) {
	$sa_settle_parts[] = sa_fa_digits( (string) $sa_rural_count ) . ' دهستان';
}
if ( $sa_village_count > 0 ) {
	$sa_settle_parts[] = sa_fa_digits( number_format( $sa_village_count ) ) . ' آبادی';
}
$sa_settle_val = implode( ' · ', $sa_settle_parts );

// 7) Climate.
$sa_page_climate = trim( (string) get_post_meta( $sa_id, 'sa_province_climate', true ) );
$sa_climate_val  = '' !== $sa_page_climate
	? $sa_page_climate
	: ( ! empty( $sa_geo['climate'] ) ? trim( (string) $sa_geo['climate'] ) : '' );

// 8) Coordinates.
$sa_lat = trim( (string) get_post_meta( $sa_id, 'sa_province_latitude', true ) );
$sa_lng = trim( (string) get_post_meta( $sa_id, 'sa_province_longitude', true ) );
if ( '' === $sa_lat && ! empty( $sa_geo['lat'] ) ) {
	$sa_lat = (string) $sa_geo['lat'];
}
if ( '' === $sa_lng && ! empty( $sa_geo['lng'] ) ) {
	$sa_lng = (string) $sa_geo['lng'];
}
$sa_coords_str = ( '' !== $sa_lat && '' !== $sa_lng ) ? ( $sa_lat . '° N, ' . $sa_lng . '° E' ) : '';
$sa_map_url    = function_exists( 'sa_map_url' ) ? sa_map_url( $sa_id ) : '';
if ( '' === $sa_map_url && '' !== $sa_lat && '' !== $sa_lng ) {
	$sa_map_url = 'https://www.google.com/maps?q=' . rawurlencode( $sa_lat . ',' . $sa_lng );
}

$sa_province_cells = array(
	array(
		'code'  => '01 // EN_NAME',
		'label' => 'نام انگلیسی استان',
		'value' => '' !== $sa_en_full ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_en_full ) . '</span>' : '',
		'html'  => true,
	),
	array(
		'code'  => '02 // CAPITAL',
		'label' => 'مرکز استان',
		'value' => $sa_cap_html,
		'html'  => true,
	),
	array(
		'code'  => '03 // POPULATION',
		'label' => 'جمعیت استان',
		'value' => $sa_pop_val,
		'html'  => false,
	),
	array(
		'code'  => '04 // AREA_KM2',
		'label' => 'مساحت کل',
		'value' => $sa_area_val,
		'html'  => false,
	),
	array(
		'code'  => '05 // DIVISIONS',
		'label' => 'شهرستان‌ها و بخش‌ها',
		'value' => $sa_div_val,
		'html'  => false,
	),
	array(
		'code'  => '06 // CITIES_RURAL',
		'label' => 'شهرها، دهستان‌ها و آبادی‌ها',
		'value' => $sa_settle_val,
		'html'  => false,
	),
	array(
		'code'  => '07 // CLIMATE',
		'label' => 'اقلیم و آب‌وهوای غالب',
		'value' => $sa_climate_val,
		'html'  => false,
	),
	array(
		'code'  => '08 // GEO_COORDS',
		'label' => 'مختصات جغرافیایی مرکز',
		'value' => '' !== $sa_coords_str ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_coords_str ) . '</span>' : '',
		'html'  => true,
	),
);

$sa_neighbor_groups = isset( $sa_profile['neighbor_groups'] ) && is_array( $sa_profile['neighbor_groups'] ) ? $sa_profile['neighbor_groups'] : array();
?>
<section class="profile-box sa-cyber-id sa-cyber-id--province sa-province-profile" id="province-identity" aria-labelledby="sa-province-profile-title">
	<div class="sa-cyber-id__hud-bar">
		<div class="sa-cyber-id__badges">
			<span class="sa-cyber-id__badge">شناسنامه</span>
			<span class="sa-cyber-id__type-tag">شناسنامه استان</span>
			<span class="sa-cyber-id__sys-code" dir="ltr">SYS.ID // PRV-<?php echo esc_html( strtoupper( $sa_slug ? $sa_slug : 'IR' ) ); ?></span>
		</div>
		<div class="sa-cyber-id__hud-meta">
			<?php if ( '' !== $sa_map_url ) : ?>
				<a class="profile-link sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener noreferrer">
					<span>نقشه ماهواره‌ای</span>
					<small dir="ltr">MAP</small>
				</a>
			<?php endif; ?>
			<?php
			if ( function_exists( 'sa_updated_badge' ) ) {
				sa_updated_badge( $sa_id );
			}
			?>
		</div>
	</div>

	<div class="profile-box sa-cyber-id__header">
		<div class="sa-cyber-id__title-group">
			<h1 class="sa-cyber-id__title" id="sa-province-profile-title"><?php echo esc_html( $sa_province_title ); ?></h1>
			<?php if ( '' !== $sa_en_full ) : ?>
				<span class="sa-cyber-id__en-pill" lang="en" dir="ltr"><?php echo esc_html( strtoupper( $sa_en_full ) ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( has_excerpt( $sa_id ) ) : ?>
			<p class="sa-cyber-id__summary"><?php echo esc_html( get_the_excerpt( $sa_id ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="sa-cyber-id__table-wrap" role="region" aria-label="جدول شناسنامه استان">
		<div class="sa-cyber-grid sa-cyber-grid--province">
			<?php foreach ( $sa_province_cells as $sa_cell ) : ?>
				<div class="profile-box sa-cyber-grid__cell">
					<span class="sa-cyber-grid__code" dir="ltr"><?php echo esc_html( $sa_cell['code'] ); ?></span>
					<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_cell['label'] ); ?></span>
					<strong class="sa-cyber-grid__value">
						<?php echo sa_cyber_id_value_or_review( $sa_cell['value'], ! empty( $sa_cell['html'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</strong>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="profile-box sa-cyber-id__subtable">
		<div class="sa-cyber-id__subtable-head">
			<span class="sa-cyber-id__subtable-title">همسایه‌ها و مرزهای پیرامونی استان</span>
			<span class="sa-cyber-id__subtable-code" dir="ltr">BORDER &amp; NEIGHBOR MATRIX</span>
		</div>
		<?php if ( ! empty( $sa_neighbor_groups ) ) : ?>
			<div class="sa-cyber-grid sa-cyber-grid--neighbors">
				<?php foreach ( $sa_neighbor_groups as $sa_group ) : ?>
					<?php
					$sa_dir    = isset( $sa_group['direction'] ) ? trim( (string) $sa_group['direction'] ) : '';
					$sa_places = isset( $sa_group['places'] ) && is_array( $sa_group['places'] ) ? $sa_group['places'] : array();
					if ( '' === $sa_dir && empty( $sa_places ) ) {
						continue;
					}
					?>
					<div class="profile-box sa-cyber-grid__cell sa-cyber-grid__cell--neighbor">
						<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_dir ); ?></span>
						<div class="sa-cyber-id__chips">
							<?php foreach ( $sa_places as $sa_place ) : ?>
								<?php
								$sa_place       = trim( (string) $sa_place );
								$sa_neighbor_id = function_exists( 'sa_province_profile_neighbor_post_id' ) ? sa_province_profile_neighbor_post_id( $sa_place ) : null;
								$sa_place_en    = function_exists( 'sa_province_profile_place_english' ) ? sa_province_profile_place_english( $sa_place ) : '';
								?>
								<?php if ( $sa_neighbor_id ) : ?>
									<a class="profile-link sa-cyber-id__chip" href="<?php echo esc_url( get_permalink( $sa_neighbor_id ) ); ?>">
										<span><?php echo esc_html( $sa_place ); ?></span>
										<?php if ( '' !== $sa_place_en ) : ?><small dir="ltr"><?php echo esc_html( $sa_place_en ); ?></small><?php endif; ?>
									</a>
								<?php else : ?>
									<span class="sa-cyber-id__chip sa-cyber-id__chip--plain">
										<span><?php echo esc_html( $sa_place ); ?></span>
										<?php if ( '' !== $sa_place_en ) : ?><small dir="ltr"><?php echo esc_html( $sa_place_en ); ?></small><?php endif; ?>
									</span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sa-cyber-grid sa-cyber-grid--single">
				<div class="profile-box sa-cyber-grid__cell">
					<span class="sa-cyber-grid__label">استان‌ها و مرزهای همجوار</span>
					<strong class="sa-cyber-grid__value">
						<?php
						$sa_nb_fallback = trim( (string) get_post_meta( $sa_id, 'sa_province_neighbors', true ) );
						if ( '' === $sa_nb_fallback && ! empty( $sa_geo['neighbors'] ) ) {
							$sa_nb_fallback = (string) $sa_geo['neighbors'];
						}
						echo sa_cyber_id_value_or_review( $sa_nb_fallback, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</strong>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $sa_county_entries ) ) : ?>
		<div class="sa-cyber-id__counties">
			<?php
			get_template_part(
				'template-parts/entity/province-profile/county-links',
				null,
				array(
					'county_entries' => $sa_county_entries,
				)
			);
			?>
		</div>
	<?php endif; ?>
</section>
