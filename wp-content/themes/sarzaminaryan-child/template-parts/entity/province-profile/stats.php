<?php
/**
 * At-a-glance province facts for travelers.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_stats       = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$sa_entries     = isset( $args['county_entries'] ) && is_array( $args['county_entries'] ) ? $args['county_entries'] : array();
$sa_area        = isset( $args['area'] ) && is_numeric( $args['area'] ) ? (float) $args['area'] : null;
$sa_population  = isset( $sa_stats['population_estimate'] ) ? $sa_stats['population_estimate'] : ( isset( $sa_stats['population'] ) ? $sa_stats['population'] : 0 );
$sa_city_count  = isset( $sa_stats['city_count'] ) ? (int) $sa_stats['city_count'] : 0;
$sa_area_thousands = null !== $sa_area ? max( 1, (int) round( $sa_area / 1000 ) ) : null;

$sa_metrics = array(
	array(
		'label' => 'جمعیت تقریبی',
		'value' => sa_province_profile_population_label( $sa_population ),
		'unit'  => 'نفر',
		'class' => 'population',
	),
	array(
		'label' => 'شهرستان',
		'value' => sa_fa_digits( (string) count( $sa_entries ) ),
		'unit'  => 'شهرستان',
		'class' => 'counties',
	),
	array(
		'label' => 'شهر',
		'value' => sa_fa_digits( (string) $sa_city_count ),
		'unit'  => 'شهر',
		'class' => 'cities',
	),
	array(
		'label' => 'مساحت حدودی',
		'value' => null !== $sa_area_thousands ? sa_fa_digits( (string) $sa_area_thousands ) : '',
		'unit'  => 'هزار کیلومتر مربع',
		'class' => 'area',
	),
);
?>
<section class="sa-province-profile__module sa-province-profile__module--stats" aria-label="<?php esc_attr_e( 'اطلاعات کلی استان', 'sarzaminaryan-child' ); ?>">
	<dl class="sa-province-profile__metrics">
		<?php foreach ( $sa_metrics as $sa_metric ) : ?>
			<?php if ( '' === $sa_metric['value'] ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div class="sa-province-profile__metric sa-province-profile__metric--<?php echo esc_attr( $sa_metric['class'] ); ?>">
				<dt><?php echo esc_html( $sa_metric['label'] ); ?></dt>
				<dd>
					<strong><?php echo esc_html( $sa_metric['value'] ); ?></strong>
					<span><?php echo esc_html( $sa_metric['unit'] ); ?></span>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
