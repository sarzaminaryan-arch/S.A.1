<?php
/**
 * County identity card (شناسنامهٔ شهرستان) — compact sibling of the province
 * profile, shown first in the article. Every empty field omits its tile/row.
 *
 * Data comes only from post meta via sa_county_profile_data(); this template
 * never reads numbers from the article body.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id = get_the_ID();
if ( 'city' !== get_post_type( $sa_id ) || ! function_exists( 'sa_county_profile_data' ) ) {
	return;
}
$sa_d = sa_county_profile_data( $sa_id );

$sa_fmt = function ( $value, $decimals = 0 ) {
	return function_exists( 'sa_number' ) ? sa_number( $value, $decimals ) : number_format_i18n( (float) $value, $decimals );
};

$sa_metrics = array();
if ( '' !== $sa_d['population'] && '' !== $sa_d['population_year'] ) {
	$sa_metrics[] = array(
		'label' => 'جمعیت (سرشماری ' . $sa_fmt( $sa_d['population_year'] ) . ')',
		'value' => $sa_fmt( $sa_d['population'] ),
		'unit'  => 'نفر',
	);
}
if ( '' !== $sa_d['area_km2'] ) {
	$sa_metrics[] = array( 'label' => 'مساحت', 'value' => $sa_fmt( $sa_d['area_km2'] ), 'unit' => 'کیلومتر مربع' );
}
if ( '' !== $sa_d['elevation_m'] ) {
	$sa_metrics[] = array( 'label' => 'ارتفاع مرکز', 'value' => $sa_fmt( $sa_d['elevation_m'] ), 'unit' => 'متر' );
}
if ( ! empty( $sa_d['divisions'] ) ) {
	$sa_div_parts = array();
	foreach ( $sa_d['divisions'] as $sa_label => $sa_value ) {
		$sa_div_parts[] = $sa_fmt( $sa_value ) . ' ' . $sa_label;
	}
	$sa_metrics[] = array( 'label' => 'تقسیمات کشوری', 'value' => implode( ' · ', $sa_div_parts ), 'unit' => '' );
}

$sa_info = array();
if ( '' !== $sa_d['center_fa'] ) {
	$sa_info[] = array( 'label' => 'مرکز شهرستان', 'value' => $sa_d['center_fa'], 'en' => $sa_d['center_en'] );
}
if ( '' !== $sa_d['climate'] ) {
	$sa_info[] = array( 'label' => 'اقلیم', 'value' => $sa_d['climate'], 'en' => '' );
}
if ( '' !== $sa_d['best_season'] ) {
	$sa_info[] = array( 'label' => 'بهترین فصل سفر', 'value' => $sa_d['best_season'], 'en' => '' );
}

$sa_footer = array();
if ( '' !== $sa_d['language'] ) {
	$sa_footer[] = array( 'label' => 'زبان/گویش غالب', 'value' => $sa_d['language'] );
}
if ( '' !== $sa_d['economy'] ) {
	$sa_footer[] = array( 'label' => 'محور اقتصاد', 'value' => $sa_d['economy'] );
}
if ( '' !== $sa_d['distance_km'] ) {
	$sa_footer[] = array( 'label' => 'فاصله تا مرکز استان', 'value' => $sa_fmt( $sa_d['distance_km'] ) . ' کیلومتر' );
}
?>
<section class="sa-county-id" id="county-identity" aria-labelledby="sa-county-id-title-<?php echo esc_attr( (string) $sa_id ); ?>">
	<header class="sa-county-id__header">
		<div class="sa-county-id__heading">
			<p class="sa-county-id__eyebrow">شناسنامهٔ شهرستان <span lang="en" dir="ltr">COUNTY PROFILE</span></p>
			<h2 class="sa-county-id__title" id="sa-county-id-title-<?php echo esc_attr( (string) $sa_id ); ?>"><?php echo esc_html( $sa_d['name_fa'] ); ?></h2>
			<?php if ( '' !== $sa_d['name_en'] ) : ?>
				<p class="sa-county-id__english" lang="en" dir="ltr"><?php echo esc_html( $sa_d['name_en'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $sa_d['province_post'] ) : ?>
			<a class="sa-county-id__province" href="<?php echo esc_url( $sa_d['province_url'] ); ?>"><?php echo esc_html( $sa_d['province_name'] ); ?></a>
		<?php endif; ?>
	</header>

	<?php if ( $sa_metrics ) : ?>
		<dl class="sa-county-id__metrics">
			<?php foreach ( $sa_metrics as $sa_metric ) : ?>
				<div class="sa-county-id__metric">
					<dt><?php echo esc_html( $sa_metric['label'] ); ?></dt>
					<dd><strong><?php echo esc_html( $sa_metric['value'] ); ?></strong><?php if ( '' !== $sa_metric['unit'] ) : ?> <span><?php echo esc_html( $sa_metric['unit'] ); ?></span><?php endif; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $sa_info ) : ?>
		<dl class="sa-county-id__info">
			<?php foreach ( $sa_info as $sa_row ) : ?>
				<div class="sa-county-id__info-row">
					<dt><?php echo esc_html( $sa_row['label'] ); ?></dt>
					<dd><?php echo esc_html( $sa_row['value'] ); ?><?php if ( '' !== $sa_row['en'] ) : ?> <small lang="en" dir="ltr"><?php echo esc_html( $sa_row['en'] ); ?></small><?php endif; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( ! empty( $sa_d['neighbors'] ) ) : ?>
		<div class="sa-county-id__neighbors">
			<h3>همسایه‌ها</h3>
			<ul>
				<?php foreach ( $sa_d['neighbors'] as $sa_neighbor ) : ?>
					<li>
						<span class="sa-county-id__direction"><?php echo esc_html( $sa_neighbor['direction'] ); ?></span>
						<?php if ( ! empty( $sa_neighbor['url'] ) ) : ?>
							<a class="sa-county-id__chip" href="<?php echo esc_url( $sa_neighbor['url'] ); ?>"><?php echo esc_html( $sa_neighbor['label'] ); ?></a>
						<?php else : ?>
							<span class="sa-county-id__chip sa-county-id__chip--plain"><?php echo esc_html( $sa_neighbor['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $sa_footer || null !== $sa_d['lat'] ) : ?>
		<ul class="sa-county-id__footer">
			<?php foreach ( $sa_footer as $sa_row ) : ?>
				<li><span><?php echo esc_html( $sa_row['label'] ); ?>:</span> <?php echo esc_html( $sa_row['value'] ); ?></li>
			<?php endforeach; ?>
			<?php if ( null !== $sa_d['lat'] ) : ?>
				<li>
					<span>مختصات مرکز:</span>
					<span dir="ltr"><?php echo esc_html( number_format( $sa_d['lat'], 4 ) . ', ' . number_format( $sa_d['lng'], 4 ) ); ?></span>
					<a href="<?php echo esc_url( $sa_d['map_url'] ); ?>" target="_blank" rel="noopener nofollow">مشاهده در نقشه</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>
</section>
