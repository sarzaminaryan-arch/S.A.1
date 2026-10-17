<?php
/**
 * Cyber county identity card (شناسنامهٔ سایبری شهرستان).
 *
 * Compact dark HUD-style card that holds every county number in one place.
 * Green monospaced English micro-labels (tags, order numbers, translations)
 * mark each slot; Persian values carry the data. Empty field = slot is
 * omitted entirely. Below the card a short prose block carries قدمت و
 * امروز، صنعت و مردم، کشاورزی — only as long as their meta exists.
 *
 * Data: sa_county_profile_data() (post meta only). Attractions come from
 * the attraction → county relation, so a published دیدنی appears in the
 * table automatically and links county ↔ attraction ↔ province.
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
	$sa_metrics[] = array( 'tag' => 'POPULATION', 'label' => 'جمعیت · سرشماری ' . $sa_fmt( $sa_d['population_year'] ), 'value' => $sa_fmt( $sa_d['population'] ), 'unit' => 'نفر' );
}
if ( '' !== $sa_d['area_km2'] ) {
	$sa_metrics[] = array( 'tag' => 'AREA', 'label' => 'مساحت', 'value' => $sa_fmt( $sa_d['area_km2'] ), 'unit' => 'km²' );
}
if ( '' !== $sa_d['elevation_m'] ) {
	$sa_metrics[] = array( 'tag' => 'ELEVATION', 'label' => 'ارتفاع مرکز', 'value' => $sa_fmt( $sa_d['elevation_m'] ), 'unit' => 'm' );
}
if ( ! empty( $sa_d['divisions'] ) ) {
	$sa_parts = array();
	foreach ( $sa_d['divisions'] as $sa_label => $sa_value ) {
		$sa_parts[] = $sa_fmt( $sa_value ) . ' ' . $sa_label;
	}
	$sa_metrics[] = array( 'tag' => 'DIVISIONS', 'label' => 'تقسیمات کشوری', 'value' => implode( ' · ', $sa_parts ), 'unit' => '' );
}

$sa_info = array();
if ( '' !== $sa_d['center_fa'] ) {
	$sa_info[] = array( 'tag' => 'CENTER', 'label' => 'مرکز شهرستان', 'value' => $sa_d['center_fa'], 'en' => $sa_d['center_en'] );
}
if ( '' !== $sa_d['climate'] ) {
	$sa_info[] = array( 'tag' => 'CLIMATE', 'label' => 'اقلیم', 'value' => $sa_d['climate'], 'en' => '' );
}
if ( '' !== $sa_d['best_season'] ) {
	$sa_info[] = array( 'tag' => 'BEST SEASON', 'label' => 'بهترین فصل سفر', 'value' => $sa_d['best_season'], 'en' => '' );
}
if ( '' !== $sa_d['language'] ) {
	$sa_info[] = array( 'tag' => 'LANGUAGE', 'label' => 'زبان/گویش غالب', 'value' => $sa_d['language'], 'en' => '' );
}
if ( '' !== $sa_d['economy'] ) {
	$sa_info[] = array( 'tag' => 'ECONOMY', 'label' => 'محور اقتصاد و کار مردم', 'value' => $sa_d['economy'], 'en' => '' );
}
if ( '' !== $sa_d['distance_km'] ) {
	$sa_info[] = array( 'tag' => 'DIST. TO CAPITAL', 'label' => 'فاصله تا مرکز استان', 'value' => $sa_fmt( $sa_d['distance_km'] ) . ' کیلومتر', 'en' => '' );
}

$sa_attractions = function_exists( 'sa_county_profile_attractions' ) ? sa_county_profile_attractions( $sa_id ) : array();
$sa_siblings    = function_exists( 'sa_county_profile_sibling_cities' ) ? sa_county_profile_sibling_cities( $sa_id ) : array();
?>
<section class="sa-cid" id="county-identity" aria-labelledby="sa-cid-title-<?php echo esc_attr( (string) $sa_id ); ?>">
	<header class="sa-cid__head">
		<div class="sa-cid__titles">
			<p class="sa-cid__tag"><span class="sa-cid__dot" aria-hidden="true"></span>شناسنامهٔ شهرستان <span lang="en" dir="ltr">// COUNTY PROFILE</span></p>
			<h2 class="sa-cid__title" id="sa-cid-title-<?php echo esc_attr( (string) $sa_id ); ?>"><?php echo esc_html( $sa_d['name_fa'] ); ?></h2>
			<?php if ( '' !== $sa_d['name_en'] ) : ?>
				<p class="sa-cid__en" lang="en" dir="ltr"><?php echo esc_html( $sa_d['name_en'] ); ?> County</p>
			<?php endif; ?>
		</div>
		<?php if ( $sa_d['province_post'] ) : ?>
			<a class="sa-cid__province" href="<?php echo esc_url( $sa_d['province_url'] ); ?>"><span lang="en" dir="ltr">PROVINCE</span><?php echo esc_html( $sa_d['province_name'] ); ?></a>
		<?php endif; ?>
	</header>

	<?php if ( $sa_metrics ) : ?>
		<dl class="sa-cid__metrics">
			<?php foreach ( $sa_metrics as $sa_i => $sa_m ) : ?>
				<div class="sa-cid__metric">
					<dt><span class="sa-cid__num" lang="en" dir="ltr"><?php echo esc_html( str_pad( (string) ( $sa_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span> <span lang="en" dir="ltr"><?php echo esc_html( $sa_m['tag'] ); ?></span><br><?php echo esc_html( $sa_m['label'] ); ?></dt>
					<dd><strong><?php echo esc_html( $sa_m['value'] ); ?></strong><?php if ( '' !== $sa_m['unit'] ) : ?> <span lang="en" dir="ltr"><?php echo esc_html( $sa_m['unit'] ); ?></span><?php endif; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $sa_info ) : ?>
		<dl class="sa-cid__info">
			<?php foreach ( $sa_info as $sa_row ) : ?>
				<div class="sa-cid__row">
					<dt><span lang="en" dir="ltr"><?php echo esc_html( $sa_row['tag'] ); ?></span><?php echo esc_html( $sa_row['label'] ); ?></dt>
					<dd><?php echo esc_html( $sa_row['value'] ); ?><?php if ( '' !== $sa_row['en'] ) : ?> <small lang="en" dir="ltr"><?php echo esc_html( $sa_row['en'] ); ?></small><?php endif; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $sa_attractions ) : ?>
		<div class="sa-cid__table-wrap">
			<h3 class="sa-cid__sub"><span lang="en" dir="ltr">TOP ATTRACTIONS</span> جاذبه‌های مهم شهرستان</h3>
			<table class="sa-cid__table">
				<thead><tr>
					<th scope="col"><span lang="en" dir="ltr">#</span></th>
					<th scope="col">جاذبه <span lang="en" dir="ltr">ATTRACTION</span></th>
					<th scope="col">نوع <span lang="en" dir="ltr">TYPE</span></th>
					<th scope="col"><span lang="en" dir="ltr">LINK</span></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $sa_attractions as $sa_i => $sa_a ) : ?>
						<tr>
							<td class="sa-cid__num" lang="en" dir="ltr"><?php echo esc_html( str_pad( (string) ( $sa_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></td>
							<td><?php if ( '' !== $sa_a['url'] ) : ?><a href="<?php echo esc_url( $sa_a['url'] ); ?>"><?php echo esc_html( $sa_a['name'] ); ?></a><?php else : ?><?php echo esc_html( $sa_a['name'] ); ?><?php endif; ?><?php if ( '' !== $sa_a['en'] ) : ?> <small lang="en" dir="ltr"><?php echo esc_html( $sa_a['en'] ); ?></small><?php endif; ?></td>
							<td><?php echo esc_html( $sa_a['type'] ); ?></td>
							<td><?php if ( '' !== $sa_a['url'] ) : ?><a class="sa-cid__go" href="<?php echo esc_url( $sa_a['url'] ); ?>">مشاهده ←</a><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $sa_d['neighbors'] ) ) : ?>
		<div class="sa-cid__links">
			<h3 class="sa-cid__sub"><span lang="en" dir="ltr">NEIGHBORS</span> همسایه‌ها</h3>
			<ul class="sa-cid__chips">
				<?php foreach ( $sa_d['neighbors'] as $sa_n ) : ?>
					<li><span class="sa-cid__dir"><?php echo esc_html( $sa_n['direction'] ); ?></span><?php if ( ! empty( $sa_n['url'] ) ) : ?><a href="<?php echo esc_url( $sa_n['url'] ); ?>"><?php echo esc_html( $sa_n['label'] ); ?></a><?php else : ?><span class="sa-cid__plain"><?php echo esc_html( $sa_n['label'] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $sa_siblings ) : ?>
		<div class="sa-cid__links">
			<h3 class="sa-cid__sub"><span lang="en" dir="ltr">SAME PROVINCE</span> شهرهای هم‌استانی</h3>
			<ul class="sa-cid__chips">
				<?php foreach ( $sa_siblings as $sa_s ) : ?>
					<li><a href="<?php echo esc_url( $sa_s['url'] ); ?>"><?php echo esc_html( $sa_s['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( null !== $sa_d['lat'] ) : ?>
		<p class="sa-cid__coords"><span lang="en" dir="ltr">GEO</span> مختصات مرکز: <span dir="ltr"><?php echo esc_html( number_format( $sa_d['lat'], 4 ) . ', ' . number_format( $sa_d['lng'], 4 ) ); ?></span> · <a href="<?php echo esc_url( $sa_d['map_url'] ); ?>" target="_blank" rel="noopener nofollow">مشاهده در نقشه</a></p>
	<?php endif; ?>
</section>

<?php
$sa_notes = array();
if ( '' !== $sa_d['history_now'] ) {
	$sa_notes[] = array( 'tag' => 'HISTORY & TODAY', 'title' => 'قدمت و امروز', 'text' => $sa_d['history_now'] );
}
if ( '' !== $sa_d['industry_main'] ) {
	$sa_notes[] = array( 'tag' => 'PEOPLE & INDUSTRY', 'title' => 'مردم، صنعت و کارخانه‌ها', 'text' => $sa_d['industry_main'] );
}
if ( '' !== $sa_d['agriculture_main'] ) {
	$sa_notes[] = array( 'tag' => 'AGRICULTURE', 'title' => 'کشاورزی و دامداری', 'text' => $sa_d['agriculture_main'] );
}
?>
<?php if ( $sa_notes ) : ?>
	<section class="sa-cid-note" aria-label="توضیح کوتاه شهرستان">
		<?php foreach ( $sa_notes as $sa_note ) : ?>
			<div class="sa-cid-note__item">
				<h3><span lang="en" dir="ltr"><?php echo esc_html( $sa_note['tag'] ); ?></span><?php echo esc_html( $sa_note['title'] ); ?></h3>
				<p><?php echo esc_html( $sa_note['text'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>
