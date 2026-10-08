<?php
/**
 * Geography, climate, neighbours and center city inside the province identity card.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_profile            = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$sa_geo                = isset( $args['geo'] ) && is_array( $args['geo'] ) ? $args['geo'] : array();
$sa_geo_base           = isset( $args['geo_baseline'] ) && is_array( $args['geo_baseline'] ) ? $args['geo_baseline'] : array();
$sa_center             = isset( $args['center_name'] ) ? (string) $args['center_name'] : '';
$sa_center_post        = isset( $args['center_post'] ) && $args['center_post'] instanceof WP_Post ? $args['center_post'] : null;
$sa_differences        = isset( $args['neighbor_differences'] ) && is_array( $args['neighbor_differences'] ) ? $args['neighbor_differences'] : array();
$sa_groups             = isset( $sa_profile['neighbor_groups'] ) && is_array( $sa_profile['neighbor_groups'] ) ? $sa_profile['neighbor_groups'] : array();
$sa_base_area          = isset( $sa_geo_base['area'] ) && is_numeric( $sa_geo_base['area'] ) ? $sa_geo_base['area'] : null;
$sa_base_climate       = isset( $sa_geo_base['climate'] ) ? trim( (string) $sa_geo_base['climate'] ) : '';
$sa_page_area          = isset( $args['page_area'] ) && is_numeric( $args['page_area'] ) ? $args['page_area'] : null;
$sa_page_climate       = isset( $args['page_climate'] ) ? trim( (string) $args['page_climate'] ) : '';
$sa_fallback_area      = isset( $sa_geo['area'] ) && is_numeric( $sa_geo['area'] ) ? $sa_geo['area'] : null;
$sa_fallback_climate   = isset( $sa_geo['climate'] ) ? trim( (string) $sa_geo['climate'] ) : '';
$sa_area               = null !== $sa_page_area ? $sa_page_area : $sa_fallback_area;
$sa_climate            = '' !== $sa_page_climate ? $sa_page_climate : $sa_fallback_climate;
$sa_area_conflict      = null !== $sa_page_area && null !== $sa_base_area && (float) $sa_page_area !== (float) $sa_base_area;
$sa_climate_conflict   = '' !== $sa_page_climate && '' !== $sa_base_climate && $sa_page_climate !== $sa_base_climate;
$sa_has_geo            = '' !== $sa_center || null !== $sa_area || '' !== $sa_climate || $sa_groups;

if ( ! $sa_has_geo ) {
	return;
}
?>
<section class="sa-province-profile__module sa-province-profile__module--geography" aria-labelledby="sa-province-profile-geo-title">
	<div class="sa-province-profile__module-heading">
		<div>
			<h3 id="sa-province-profile-geo-title"><?php esc_html_e( 'جغرافیا و همسایگی', 'sarzaminaryan-child' ); ?></h3>
			<p><?php esc_html_e( 'پیوند همسایه‌ها فقط وقتی ساخته می‌شود که صفحهٔ استان منتشر شده باشد.', 'sarzaminaryan-child' ); ?></p>
		</div>
	</div>

	<?php if ( $sa_center || null !== $sa_area || '' !== $sa_climate ) : ?>
		<dl class="sa-province-profile__identity-facts">
			<?php if ( $sa_center ) : ?>
				<div class="sa-province-profile__identity-fact">
					<dt><?php esc_html_e( 'مرکز استان', 'sarzaminaryan-child' ); ?></dt>
					<dd>
						<?php if ( $sa_center_post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $sa_center_post ) ); ?>"><?php echo esc_html( $sa_center ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $sa_center ); ?>
						<?php endif; ?>
						<small><?php esc_html_e( 'بر پایهٔ فهرست ثابت استان‌های قالب؛ پیوند فقط به صفحهٔ منتشرشده.', 'sarzaminaryan-child' ); ?></small>
					</dd>
				</div>
			<?php endif; ?>

			<?php if ( null !== $sa_area ) : ?>
				<div class="sa-province-profile__identity-fact is-review">
					<dt><?php esc_html_e( 'مساحت', 'sarzaminaryan-child' ); ?></dt>
					<dd>
						<strong><?php echo esc_html( sa_number( $sa_area ) ); ?></strong>
						<span><?php esc_html_e( 'کیلومتر مربع', 'sarzaminaryan-child' ); ?></span>
						<small><?php esc_html_e( 'مقدار فعلی قالب؛ منبع مستقیم و سال نامشخص، نیازمند بازبینی.', 'sarzaminaryan-child' ); ?></small>
						<?php if ( $sa_area_conflict ) : ?>
							<details class="sa-province-profile__inline-discrepancy">
								<summary><?php esc_html_e( 'با دادهٔ پایهٔ قالب اختلاف دارد', 'sarzaminaryan-child' ); ?></summary>
								<span><?php echo esc_html( sprintf( __( 'دادهٔ پایهٔ regions.json / Iran Map v0.8.0: %s کیلومتر مربع؛ سال منبع نامعلوم.', 'sarzaminaryan-child' ), sa_number( $sa_base_area ) ) ); ?></span>
							</details>
						<?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $sa_climate ) : ?>
				<div class="sa-province-profile__identity-fact is-review">
					<dt><?php esc_html_e( 'اقلیم', 'sarzaminaryan-child' ); ?></dt>
					<dd>
						<strong><?php echo esc_html( $sa_climate ); ?></strong>
						<small><?php esc_html_e( 'مقدار فعلی قالب؛ منبع دقیق و سال ثبت نشده، نیازمند بازبینی.', 'sarzaminaryan-child' ); ?></small>
						<?php if ( $sa_climate_conflict ) : ?>
							<details class="sa-province-profile__inline-discrepancy">
								<summary><?php esc_html_e( 'با دادهٔ پایهٔ قالب اختلاف دارد', 'sarzaminaryan-child' ); ?></summary>
								<span><?php echo esc_html( sprintf( __( 'دادهٔ پایهٔ regions.json / Iran Map v0.8.0: %s؛ سال/منبع دقیق نامعلوم.', 'sarzaminaryan-child' ), $sa_base_climate ) ); ?></span>
							</details>
						<?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $sa_groups ) : ?>
		<div class="sa-province-profile__neighbors">
			<h4><?php esc_html_e( 'همسایه‌ها و مرزها', 'sarzaminaryan-child' ); ?></h4>
			<ul>
				<?php foreach ( $sa_groups as $sa_group ) : ?>
					<?php
					$sa_direction = isset( $sa_group['direction'] ) ? (string) $sa_group['direction'] : '';
					$sa_places    = isset( $sa_group['places'] ) && is_array( $sa_group['places'] ) ? $sa_group['places'] : array();
					if ( ! $sa_direction || ! $sa_places ) {
						continue;
					}
					?>
					<li>
						<span class="sa-province-profile__direction"><?php echo esc_html( $sa_direction ); ?></span>
						<span class="sa-province-profile__places">
							<?php foreach ( $sa_places as $sa_place_index => $sa_place ) : ?>
								<?php $sa_target_id = sa_province_profile_neighbor_post_id( $sa_place ); ?>
								<?php if ( $sa_place_index > 0 ) : ?><span class="sa-province-profile__separator" aria-hidden="true">، </span><?php endif; ?>
								<?php if ( $sa_target_id ) : ?>
									<a href="<?php echo esc_url( get_permalink( $sa_target_id ) ); ?>"><?php echo esc_html( $sa_place ); ?></a>
								<?php else : ?>
									<span class="sa-province-profile__border"><?php echo esc_html( $sa_place ); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $sa_differences ) : ?>
		<div class="sa-province-profile__review" role="note">
			<strong><?php esc_html_e( 'تفاوت با دادهٔ جغرافیایی قبلی قالب — نیازمند تطبیق', 'sarzaminaryan-child' ); ?></strong>
			<?php if ( ! empty( $sa_differences['source_only'] ) ) : ?>
				<p><span><?php esc_html_e( 'فقط در فایل آماری ورودی:', 'sarzaminaryan-child' ); ?></span> <?php echo esc_html( implode( '، ', $sa_differences['source_only'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $sa_differences['legacy_only'] ) ) : ?>
				<p><span><?php esc_html_e( 'فقط در دادهٔ قبلی قالب:', 'sarzaminaryan-child' ); ?></span> <?php echo esc_html( implode( '، ', $sa_differences['legacy_only'] ) ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'هیچ‌یک به‌عنوان مرجع نهایی انتخاب نشده است؛ هر دو مقدار باید با نقشهٔ تاریخ‌دار تطبیق داده شوند.', 'sarzaminaryan-child' ); ?></p>
		</div>
	<?php endif; ?>
</section>
