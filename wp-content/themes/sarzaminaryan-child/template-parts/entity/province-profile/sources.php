<?php
/**
 * Short source reference for optional use in the province profile.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_meta       = sa_province_profile_metadata();
$sa_source_url = isset( $sa_meta['map_source_index_url'] ) ? (string) $sa_meta['map_source_index_url'] : '';
?>
<section class="sa-province-profile__module sa-province-profile__module--sources" aria-label="<?php esc_attr_e( 'منبع اطلاعات', 'sarzaminaryan-child' ); ?>">
	<p><?php esc_html_e( 'اطلاعات تقسیمات استان بر پایهٔ نقشه‌ها و فهرست‌های استانی گردآوری شده است.', 'sarzaminaryan-child' ); ?>
		<?php if ( $sa_source_url ) : ?>
			<a href="<?php echo esc_url( $sa_source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مرکز آمار ایران', 'sarzaminaryan-child' ); ?></a>
		<?php endif; ?>
	</p>
</section>
