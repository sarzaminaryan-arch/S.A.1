<?php
/**
 * Administrative and census metrics inside the province identity card.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_profile = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$sa_stats   = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$sa_map_year = isset( $sa_profile['map_year'] ) ? (int) $sa_profile['map_year'] : 0;
$sa_pop_year = isset( $sa_profile['population_year'] ) ? (int) $sa_profile['population_year'] : 0;
$sa_hh_year  = isset( $sa_profile['household_year'] ) ? (int) $sa_profile['household_year'] : 0;
$sa_old_population = isset( $args['legacy_population'] ) ? $args['legacy_population'] : null;

$sa_metrics = array(
	'population'           => array( 'label' => 'جمعیت', 'unit' => 'نفر', 'year' => $sa_pop_year, 'year_label' => 'سرشماری' ),
	'county_count'          => array( 'label' => 'تعداد شهرستان', 'unit' => 'شهرستان', 'year' => $sa_map_year, 'year_label' => 'نقشه' ),
	'city_count'            => array( 'label' => 'تعداد شهر', 'unit' => 'شهر', 'year' => $sa_map_year, 'year_label' => 'نقشه' ),
	'district_count'        => array( 'label' => 'تعداد بخش', 'unit' => 'بخش', 'year' => $sa_map_year, 'year_label' => 'نقشه' ),
	'rural_district_count'  => array( 'label' => 'تعداد دهستان', 'unit' => 'دهستان', 'year' => $sa_map_year, 'year_label' => 'نقشه' ),
	'settlement_count'      => array( 'label' => 'تعداد آبادی', 'unit' => 'آبادی', 'year' => $sa_map_year, 'year_label' => 'نقشه' ),
	'household_count'       => array( 'label' => 'تعداد خانوار', 'unit' => 'خانوار', 'year' => $sa_hh_year, 'year_label' => 'سرشماری' ),
);
?>
<section class="sa-province-profile__module sa-province-profile__module--stats" aria-labelledby="sa-province-profile-stats-title">
	<div class="sa-province-profile__module-heading">
		<div>
			<h3 id="sa-province-profile-stats-title"><?php esc_html_e( 'آمار تقسیماتی و سرشماری', 'sarzaminaryan-child' ); ?></h3>
			<p><?php esc_html_e( 'سال مرجع کنار هر شاخص درج شده است.', 'sarzaminaryan-child' ); ?></p>
		</div>
		<span class="sa-province-profile__year-tag"><?php echo esc_html( sprintf( __( 'نقشهٔ %s', 'sarzaminaryan-child' ), sa_fa_digits( (string) $sa_map_year ) ) ); ?></span>
	</div>

	<dl class="sa-province-profile__metrics">
		<?php foreach ( $sa_metrics as $sa_key => $sa_metric ) : ?>
			<?php if ( ! isset( $sa_stats[ $sa_key ] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php $sa_conflict = ( 'population' === $sa_key && null !== $sa_old_population ); ?>
			<div class="sa-province-profile__metric<?php echo $sa_conflict ? ' is-conflict' : ''; ?>">
				<dt><?php echo esc_html( $sa_metric['label'] ); ?></dt>
				<dd>
					<strong><?php echo esc_html( sa_number( $sa_stats[ $sa_key ] ) ); ?></strong>
					<span><?php echo esc_html( $sa_metric['unit'] ); ?></span>
					<small><?php echo esc_html( sprintf( __( '%1$s %2$s', 'sarzaminaryan-child' ), $sa_metric['year_label'], sa_fa_digits( (string) $sa_metric['year'] ) ) ); ?></small>
					<?php if ( $sa_conflict ) : ?>
						<small class="sa-province-profile__metric-warning">
							<?php
							echo esc_html(
								sprintf(
									__( 'دادهٔ قبلی قالب: %1$s؛ اختلاف نیازمند بررسی', 'sarzaminaryan-child' ),
									sa_number( $sa_old_population )
								)
							);
							?>
						</small>
					<?php endif; ?>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
