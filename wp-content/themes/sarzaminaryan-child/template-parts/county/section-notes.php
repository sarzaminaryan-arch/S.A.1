<?php
/**
 * سکشن «نکته‌های کوتاه زیر شناسنامه» — از county-profile.php جدا شد (v2.22.145).
 *
 * گروه: محتوایی. متن از `sa_county_profile_data()` می‌آید (history_now،
 * industry_main، agriculture_main). شرط نمایش در رجیستری: `has_facts`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id           = isset( $sa_id ) ? (int) $sa_id : (int) get_the_ID();
$sa_profile_ext  = isset( $sa_profile_ext ) && is_array( $sa_profile_ext )
	? $sa_profile_ext
	: ( function_exists( 'sa_county_profile_data' ) ? (array) sa_county_profile_data( $sa_id ) : array() );
$sa_cid_notes = array();
if ( ! empty( $sa_profile_ext['history_now'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'HISTORY & TODAY', 'title' => 'قدمت و امروز', 'text' => $sa_profile_ext['history_now'] );
}
if ( ! empty( $sa_profile_ext['industry_main'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'PEOPLE & INDUSTRY', 'title' => 'مردم، صنعت و کارخانه‌ها', 'text' => $sa_profile_ext['industry_main'] );
}
if ( ! empty( $sa_profile_ext['agriculture_main'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'AGRICULTURE', 'title' => 'کشاورزی و دامداری', 'text' => $sa_profile_ext['agriculture_main'] );
}
?>
<?php if ( $sa_cid_notes ) : ?>
	<section class="sa-cid-note" aria-label="توضیح کوتاه شهرستان">
		<?php foreach ( $sa_cid_notes as $sa_note ) : ?>
			<div class="profile-box sa-cid-note__item">
				<h3><span lang="en" dir="ltr"><?php echo esc_html( $sa_note['tag'] ); ?></span><?php echo esc_html( $sa_note['title'] ); ?></h3>
				<p><?php echo esc_html( $sa_note['text'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php