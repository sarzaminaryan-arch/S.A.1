<?php
/**
 * سکشن «تجربهٔ مسافرها» — از county-profile.php جدا شد (v2.22.145).
 *
 * گروه: محتوایی. داده از `sa_county_profile_data()` و متاهای تجربه می‌آید؛
 * هر زیربخش فقط با دادهٔ واقعی رندر می‌شود. شرط نمایش در رجیستری: `has_insights`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id          = isset( $sa_id ) ? (int) $sa_id : (int) get_the_ID();
$sa_profile_ext = isset( $sa_profile_ext ) && is_array( $sa_profile_ext )
	? $sa_profile_ext
	: ( function_exists( 'sa_county_profile_data' ) ? (array) sa_county_profile_data( $sa_id ) : array() );
$sa_ins = $sa_profile_ext;
$sa_county_full = function_exists( 'sa_county_name' ) ? sa_county_name( $sa_id, true ) : get_the_title( $sa_id );
if ( function_exists( 'sa_toc_register' ) && ! empty( $sa_ins['has_insights'] ) ) {
	sa_toc_register( 'تجربهٔ مسافرها', 'county-field-notes', 6 );
}
?>
<?php
/* CU-1 (v2.22.49) — «تجربهٔ مسافرها»: لایهٔ زندهٔ صفحهٔ شهرستان.
 * متن ثابت مقاله دست نمی‌خورد؛ این بلوک دادهٔ کاربردی‌ای را نشان می‌دهد که از
 * تجربه‌های تازهٔ مسافرها در سرویس‌های نقشه و دیدگاه‌های اقامتگاه‌ها درآمده است.
 * v2.22.86 (دستور مالک): عنوان بلوک تمیز شد و توضیحِ زیر آن حذف شد؛ ساختار
 * ثابت است: کادرهای اطلاعات، فصل‌ها، جاهای دیدنی شهرستان و نکته‌ها.
 * هر زیربخش فقط وقتی دادهٔ واقعی دارد رندر می‌شود. */
$sa_ins = $sa_profile_ext;
?>
<?php if ( ! empty( $sa_ins['has_insights'] ) ) : ?>
	<section class="sa-field-notes" id="county-field-notes" aria-label="تجربهٔ مسافرها">
		<div class="profile-box sa-field-notes__head">
			<h2>تجربهٔ مسافرها</h2>
		</div>

		<?php if ( ! empty( $sa_ins['access_facts'] ) ) : ?>
			<div class="sa-field-facts" role="list" aria-label="دسترسی در یک نگاه">
				<?php foreach ( (array) $sa_ins['access_facts'] as $sa_fact_label => $sa_fact_value ) : ?>
					<div class="sa-field-fact profile-box" role="listitem">
						<span class="sa-field-fact__label"><?php echo esc_html( (string) $sa_fact_label ); ?></span>
						<span class="sa-field-fact__value"><?php echo esc_html( (string) $sa_fact_value ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sa_ins['seasons'] ) ) : ?>
			<div class="profile-box sa-field-seasons">
				<h3>اقلیم و آب‌وهوا، فصل‌به‌فصل</h3>
				<div class="sa-field-seasons__grid">
					<?php $sa_season_slugs = array( 'بهار' => 'spring', 'تابستان' => 'summer', 'پاییز' => 'autumn', 'زمستان' => 'winter' ); ?>
					<?php $sa_season_en = array( 'بهار' => 'Spring', 'تابستان' => 'Summer', 'پاییز' => 'Autumn', 'زمستان' => 'Winter' ); ?>
					<?php foreach ( (array) $sa_ins['seasons'] as $sa_season_name => $sa_season_text ) : ?>
						<?php
						// «بهترین فصل» یک عبارت آزاد است («آذر تا فروردین»، «آبان تا اسفند»)؛
						// مقایسهٔ مستقیم نام فصل با کل عبارت، نشان را به فصل اشتباه می‌چسباند.
						// یک فصل تنها وقتی پیشنهادی است که خودش در عبارت آمده باشد و عبارت
						// یک بازه نباشد؛ در بازه، هیچ فصل تنها نشان نمی‌گیرد.
						$sa_best_text = trim( (string) ( $sa_ins['best_season'] ?? '' ) );
						$sa_is_range  = (bool) preg_match( '/\b(تا|الی|لغایت)\b/u', $sa_best_text );
						$sa_is_best   = ( ! $sa_is_range && '' !== $sa_best_text && false !== mb_strpos( $sa_best_text, (string) $sa_season_name ) );
						?>
						<?php $sa_season_slug = isset( $sa_season_slugs[ (string) $sa_season_name ] ) ? $sa_season_slugs[ (string) $sa_season_name ] : ''; ?>
						<div class="sa-field-season<?php echo $sa_season_slug ? ' sa-field-season--' . esc_attr( $sa_season_slug ) : ''; ?><?php echo $sa_is_best ? ' sa-field-season--best' : ''; ?>">
							<div class="sa-field-season__bar">
								<span class="sa-field-season__name"><?php echo esc_html( (string) $sa_season_name ); ?><?php if ( $sa_is_best ) : ?> <span class="sa-field-season__badge">فصل پیشنهادی</span><?php endif; ?></span>
								<?php if ( isset( $sa_season_en[ (string) $sa_season_name ] ) ) : ?>
									<span class="sa-field-season__en" lang="en" dir="ltr"><?php echo esc_html( $sa_season_en[ (string) $sa_season_name ] ); ?></span>
								<?php endif; ?>
							</div>
							<p><?php echo esc_html( (string) $sa_season_text ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sa_ins['extra_places'] ) ) : ?>
			<div class="profile-box sa-field-places">
				<h3>جاهای دیدنی <?php echo esc_html( $sa_county_full ); ?></h3>
				<ul class="sa-field-places__list">
					<?php foreach ( (array) $sa_ins['extra_places'] as $sa_place ) : ?>
						<li class="sa-field-place">
							<div class="sa-field-place__head">
								<strong><?php echo esc_html( (string) $sa_place['name'] ); ?></strong>
								<?php if ( ! empty( $sa_place['kind'] ) ) : ?><span class="sa-field-place__kind"><?php echo esc_html( (string) $sa_place['kind'] ); ?></span><?php endif; ?>
							</div>
							<?php if ( ! empty( $sa_place['access'] ) ) : ?><p class="sa-field-place__access"><span>مسیر و موقعیت:</span> <?php echo esc_html( (string) $sa_place['access'] ); ?></p><?php endif; ?>
							<?php if ( ! empty( $sa_place['note'] ) ) : ?><p class="sa-field-place__note"><?php echo esc_html( (string) $sa_place['note'] ); ?></p><?php endif; ?>
							<?php if ( ! empty( $sa_place['url'] ) ) : ?><p class="sa-field-place__link"><a href="<?php echo esc_url( (string) $sa_place['url'] ); ?>" rel="noopener" target="_blank">وب‌سایت یا صفحهٔ این مکان ←</a></p><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sa_ins['local_tips'] ) ) : ?>
			<div class="profile-box sa-field-tips">
				<h3>نکته‌هایی که کاش قبل از راه‌افتادن بدانید</h3>
				<div class="sa-field-tips__grid">
					<?php foreach ( (array) $sa_ins['local_tips'] as $sa_tip ) : ?>
						<div class="sa-field-tip sa-field-tip--<?php echo esc_attr( (string) $sa_tip['category'] ); ?>">
							<span class="sa-field-tip__cat"><?php echo esc_html( (string) $sa_tip['cat_label'] ); ?></span>
							<strong><?php echo esc_html( (string) $sa_tip['title'] ); ?></strong>
							<p><?php echo esc_html( (string) $sa_tip['text'] ); ?></p>
							<?php if ( ! empty( $sa_tip['evidence'] ) ) : ?>
								<p class="sa-field-tip__evidence"><?php echo esc_html( (string) $sa_tip['evidence'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sa_ins['people_character'] ) ) : ?>
			<div class="profile-box sa-field-people">
				<h3>مردمِ این‌جا، از نگاه مسافرها</h3>
				<p><?php echo esc_html( (string) $sa_ins['people_character'] ); ?></p>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

