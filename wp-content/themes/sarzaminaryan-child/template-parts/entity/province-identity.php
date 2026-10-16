<?php
/**
 * Cyber-Tech Light-Mode Province Identity Card — «شناسنامه استان» (v2.19.1).
 *
 * Dedicated Cyan-Blue technological tabular card for Iran's 31 provinces.
 * Displays 8 fixed vital slots in a 4x2 (desktop) / 2x4 (mobile) bordered cyber grid,
 * a bordered directional neighbours matrix with Persian + English names, and the
 * bilingual county directory. Any unpopulated slot displays a tiny «نیازمند بازبینی» badge.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_slug = (string) get_post_field( 'post_name', $sa_id );

if ( function_exists( 'sa_toc_register' ) ) {
	sa_toc_register( 'شناسنامه استان', 'province-identity', 5 );
}

$sa_profile = function_exists( 'sa_province_profile' ) ? sa_province_profile( $sa_slug ) : null;
$sa_profile = is_array( $sa_profile ) ? $sa_profile : array();
$sa_stats   = isset( $sa_profile['stats'] ) && is_array( $sa_profile['stats'] ) ? $sa_profile['stats'] : array();
$sa_facts   = function_exists( 'sa_province_profile_facts' ) ? sa_province_profile_facts( $sa_id, $sa_slug, $sa_profile ) : array();
$sa_capital = function_exists( 'sa_province_profile_capital' ) ? sa_province_profile_capital( $sa_id, $sa_slug ) : array( 'name' => '', 'english' => '', 'post_id' => null );

$sa_province_title = function_exists( 'sa_province_name_for_post' )
	? sa_province_name_for_post( $sa_id, true )
	: ( ! empty( $sa_profile['name'] ) ? 'استان ' . (string) $sa_profile['name'] : (string) get_the_title( $sa_id ) );

$sa_en_raw = trim( (string) get_post_meta( $sa_id, 'sa_english_name', true ) );
if ( '' === $sa_en_raw && function_exists( 'sa_cyber_id_province_en' ) ) {
	$sa_en_raw = sa_cyber_id_province_en( $sa_slug );
}
$sa_en_full = '' !== $sa_en_raw
	? ( false === stripos( $sa_en_raw, 'province' ) ? $sa_en_raw . ' Province' : $sa_en_raw )
	: '';

$sa_county_entries = function_exists( 'sa_province_profile_counties' ) ? sa_province_profile_counties( $sa_slug ) : array();
$sa_county_count   = isset( $sa_stats['county_count'] ) ? (int) $sa_stats['county_count'] : count( $sa_county_entries );
$sa_district_count = isset( $sa_stats['district_count'] ) ? (int) $sa_stats['district_count'] : 0;
$sa_city_count     = isset( $sa_stats['city_count'] ) ? (int) $sa_stats['city_count'] : 0;
$sa_rural_count    = isset( $sa_stats['rural_district_count'] ) ? (int) $sa_stats['rural_district_count'] : 0;
$sa_village_count  = isset( $sa_stats['settlement_count'] ) ? (int) $sa_stats['settlement_count'] : 0;

// Coordinates from post meta or data/region-facts.php.
$sa_lat = trim( (string) get_post_meta( $sa_id, 'sa_province_latitude', true ) );
$sa_lng = trim( (string) get_post_meta( $sa_id, 'sa_province_longitude', true ) );
if ( ( '' === $sa_lat || '' === $sa_lng ) && function_exists( 'sa_province_region_fact' ) ) {
	$sa_rf = sa_province_region_fact( $sa_slug );
	if ( is_array( $sa_rf ) ) {
		if ( '' === $sa_lat && ! empty( $sa_rf['lat'] ) ) {
			$sa_lat = (string) $sa_rf['lat'];
		}
		if ( '' === $sa_lng && ! empty( $sa_rf['lng'] ) ) {
			$sa_lng = (string) $sa_rf['lng'];
		}
	}
}
$sa_coords_str = ( '' !== $sa_lat && '' !== $sa_lng ) ? ( $sa_lat . '° N, ' . $sa_lng . '° E' ) : '';
$sa_map_url    = trim( (string) get_post_meta( $sa_id, 'sa_google_map_url', true ) );
if ( '' === $sa_map_url && '' !== $sa_lat && '' !== $sa_lng ) {
	$sa_map_url = 'https://www.google.com/maps?q=' . rawurlencode( $sa_lat . ',' . $sa_lng );
}

// Build the 8 fixed vital slots for the Province Cyber Matrix.
$sa_cap_html = '';
if ( ! empty( $sa_capital['name'] ) ) {
	$sa_cap_label = esc_html( (string) $sa_capital['name'] );
	if ( ! empty( $sa_capital['english'] ) ) {
		$sa_cap_label .= ' <span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( (string) $sa_capital['english'] ) . ')</span>';
	}
	if ( ! empty( $sa_capital['post_id'] ) ) {
		$sa_cap_html = '<a class="sa-cyber-id__cell-link" href="' . esc_url( get_permalink( (int) $sa_capital['post_id'] ) ) . '">' . $sa_cap_label . '</a>';
	} else {
		$sa_cap_html = $sa_cap_label;
	}
}

$sa_pop_val = '';
if ( ! empty( $sa_stats['population'] ) ) {
	$sa_pop_val = sa_fa_digits( number_format( (int) $sa_stats['population'] ) ) . ' نفر';
} elseif ( ! empty( $sa_facts['population_estimate'] ) ) {
	$sa_pop_val = (string) $sa_facts['population_estimate'];
}

$sa_area_val = ! empty( $sa_facts['area'] ) ? (string) $sa_facts['area'] : '';

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

$sa_climate_val = ! empty( $sa_facts['climate'] ) ? (string) $sa_facts['climate'] : '';

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
<section class="sa-cyber-id sa-cyber-id--province sa-province-profile" id="province-identity" aria-labelledby="sa-province-profile-title">
	<div class="sa-cyber-id__hud-bar">
		<div class="sa-cyber-id__badges">
			<span class="sa-cyber-id__badge">شناسنامه</span>
			<span class="sa-cyber-id__type-tag">شناسنامه استان</span>
			<span class="sa-cyber-id__sys-code" dir="ltr">SYS.ID // PRV-<?php echo esc_html( strtoupper( $sa_slug ? $sa_slug : 'IR' ) ); ?></span>
		</div>
		<div class="sa-cyber-id__hud-meta">
			<?php if ( '' !== $sa_map_url ) : ?>
				<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener noreferrer">
					<span>نقشه ماهواره‌ای</span>
					<small dir="ltr">MAP</small>
				</a>
			<?php endif; ?>
			<?php sa_updated_badge( $sa_id ); ?>
		</div>
	</div>

	<div class="sa-cyber-id__header">
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
				<div class="sa-cyber-grid__cell">
					<span class="sa-cyber-grid__code" dir="ltr"><?php echo esc_html( $sa_cell['code'] ); ?></span>
					<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_cell['label'] ); ?></span>
					<strong class="sa-cyber-grid__value">
						<?php echo sa_cyber_id_value_or_review( $sa_cell['value'], ! empty( $sa_cell['html'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</strong>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="sa-cyber-id__subtable">
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
					<div class="sa-cyber-grid__cell sa-cyber-grid__cell--neighbor">
						<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_dir ); ?></span>
						<div class="sa-cyber-id__chips">
							<?php foreach ( $sa_places as $sa_place ) : ?>
								<?php
								$sa_place       = trim( (string) $sa_place );
								$sa_neighbor_id = function_exists( 'sa_province_profile_neighbor_post_id' ) ? sa_province_profile_neighbor_post_id( $sa_place ) : null;
								$sa_place_en    = function_exists( 'sa_province_profile_neighbor_english' ) ? sa_province_profile_neighbor_english( $sa_place ) : '';
								?>
								<?php if ( $sa_neighbor_id ) : ?>
									<a class="sa-cyber-id__chip" href="<?php echo esc_url( get_permalink( $sa_neighbor_id ) ); ?>">
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
				<div class="sa-cyber-grid__cell">
					<span class="sa-cyber-grid__label">استان‌ها و مرزهای همجوار</span>
					<strong class="sa-cyber-grid__value">
						<?php
						$sa_nb_fallback = trim( (string) get_post_meta( $sa_id, 'sa_province_neighbors', true ) );
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
