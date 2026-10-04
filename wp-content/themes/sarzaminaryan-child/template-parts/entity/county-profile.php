<?php
/**
 * Fixed county profile: divisions · neighbours · where to go.
 * Identical slot order on all 483 county pages; empty slots simply disappear.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id = get_the_ID();
if ( 'city' !== get_post_type( $sa_id ) || ! sa_county_has_profile( $sa_id ) ) {
	return;
}

/* ---- تقسیمات کشوری ---- */
$sa_div = array(
	'sa_cty_districts'       => 'بخش',
	'sa_cty_rural_districts' => 'دهستان',
	'sa_cty_cities'          => 'شهر',
	'sa_cty_villages'        => 'روستا',
);
$sa_div_rows = array();
foreach ( $sa_div as $sa_key => $sa_label ) {
	$sa_val = get_post_meta( $sa_id, $sa_key, true );
	if ( '' !== (string) $sa_val ) {
		$sa_div_rows[ $sa_label ] = sa_number( $sa_val, 0 );
	}
}

/* ---- همسایه‌ها ---- */
$sa_neighbors = array();
foreach ( sa_county_lines( get_post_meta( $sa_id, 'sa_cty_neighbors', true ) ) as $sa_line ) {
	$sa_neighbors[] = sa_county_parse_neighbor( $sa_line );
}

/* ---- کجا برویم ---- */
$sa_poi_groups = array(
	'sa_cty_poi_nature'     => array( 'title' => 'طبیعت‌گردی', 'icon' => '🏞' ),
	'sa_cty_poi_offbeat'    => array( 'title' => 'نقاط بکر و کمتر دیده‌شده', 'icon' => '🧭' ),
	'sa_cty_poi_recreation' => array( 'title' => 'تفریحی و خانوادگی', 'icon' => '🎡' ),
	'sa_cty_poi_heritage'   => array( 'title' => 'تاریخی و زیارتی', 'icon' => '🏛' ),
);
$sa_pois = array();
foreach ( $sa_poi_groups as $sa_key => $sa_meta ) {
	$sa_items = array();
	foreach ( sa_county_lines( get_post_meta( $sa_id, $sa_key, true ) ) as $sa_line ) {
		$sa_items[] = sa_county_parse_poi( $sa_line );
	}
	if ( $sa_items ) {
		$sa_pois[ $sa_key ] = array_merge( $sa_meta, array( 'items' => $sa_items ) );
	}
}

$sa_life = array(
	'sa_cty_language'   => 'زبان و گویش',
	'sa_cty_livelihood' => 'معیشت اصلی',
	'sa_cty_best_time'  => 'بهترین زمان سفر',
);
$sa_life_rows = array();
foreach ( $sa_life as $sa_key => $sa_label ) {
	$sa_val = get_post_meta( $sa_id, $sa_key, true );
	if ( '' !== (string) $sa_val ) {
		$sa_life_rows[ $sa_label ] = $sa_val;
	}
}
?>
<section class="sa-county" id="county-profile">

	<?php if ( $sa_div_rows ) : ?>
		<h2 class="sa-county__title" id="divisions">تقسیمات شهرستان</h2>
		<ul class="sa-county__stats">
			<?php foreach ( $sa_div_rows as $sa_label => $sa_value ) : ?>
				<li class="sa-county__stat">
					<span class="sa-county__num"><?php echo esc_html( $sa_value ); ?></span>
					<span class="sa-county__cap"><?php echo esc_html( $sa_label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $sa_neighbors ) : ?>
		<h2 class="sa-county__title" id="neighbors">همسایه‌های شهرستان</h2>
		<ul class="sa-county__chips">
			<?php foreach ( $sa_neighbors as $sa_n ) : ?>
				<li class="sa-county__chip">
					<?php if ( $sa_n['url'] ) : ?>
						<a href="<?php echo esc_url( $sa_n['url'] ); ?>"><?php echo esc_html( $sa_n['label'] ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( $sa_n['label'] ); ?></span>
					<?php endif; ?>
					<?php if ( $sa_n['dir'] ) : ?>
						<small><?php echo esc_html( $sa_n['dir'] ); ?></small>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $sa_pois ) : ?>
		<h2 class="sa-county__title" id="where-to-go">کجا برویم؟</h2>
		<?php foreach ( $sa_pois as $sa_group ) : ?>
			<h3 class="sa-county__sub"><span aria-hidden="true"><?php echo esc_html( $sa_group['icon'] ); ?></span> <?php echo esc_html( $sa_group['title'] ); ?></h3>
			<ul class="sa-county__poi">
				<?php foreach ( $sa_group['items'] as $sa_poi ) : ?>
					<li>
						<strong><?php echo esc_html( $sa_poi['name'] ); ?></strong>
						<?php if ( $sa_poi['distance'] ) : ?>
							<span class="sa-county__dist"><?php echo esc_html( sa_fa_digits( $sa_poi['distance'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( $sa_poi['note'] ) : ?>
							<p><?php echo esc_html( $sa_poi['note'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( $sa_life_rows ) : ?>
		<h2 class="sa-county__title" id="people">مردم، معیشت و زمان سفر</h2>
		<dl class="sa-county__list">
			<?php foreach ( $sa_life_rows as $sa_label => $sa_value ) : ?>
				<div class="sa-county__row">
					<dt><?php echo esc_html( $sa_label ); ?></dt>
					<dd><?php echo esc_html( $sa_value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

</section>
