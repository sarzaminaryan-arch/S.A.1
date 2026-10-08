<?php
/**
 * Visible provenance, years and verification caveats for province values.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_profile          = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$sa_meta             = isset( $args['metadata'] ) && is_array( $args['metadata'] ) ? $args['metadata'] : array();
$sa_map_year         = isset( $sa_profile['map_year'] ) ? (int) $sa_profile['map_year'] : 0;
$sa_source_lines     = isset( $sa_profile['source_note_lines'] ) && is_array( $sa_profile['source_note_lines'] ) ? $sa_profile['source_note_lines'] : array();
$sa_source_url       = isset( $sa_meta['map_source_index_url'] ) ? (string) $sa_meta['map_source_index_url'] : '';
$sa_source_as_stated = isset( $sa_meta['map_source_as_stated'] ) ? (string) $sa_meta['map_source_as_stated'] : '';
?>
<section class="sa-province-profile__module sa-province-profile__module--sources" aria-labelledby="sa-province-profile-sources-title">
	<h3 id="sa-province-profile-sources-title"><?php esc_html_e( 'منبع و سال داده‌ها', 'sarzaminaryan-child' ); ?></h3>
	<ul class="sa-province-profile__source-list">
		<li>
			<strong><?php esc_html_e( 'تقسیمات و همسایه‌ها:', 'sarzaminaryan-child' ); ?></strong>
			<?php
			echo esc_html(
				sprintf(
					__( 'نقل‌شده از فایل «آمار استان‌ها تقسیمات کشوری»؛ نقشهٔ %1$s برای این استان. منبعی که فایل نام می‌برد: %2$s.', 'sarzaminaryan-child' ),
					sa_fa_digits( (string) $sa_map_year ),
					$sa_source_as_stated
				)
			);
		?>
		</li>
		<li>
			<strong><?php esc_html_e( 'ارجاعی که در ردیف این استان آمده:', 'sarzaminaryan-child' ); ?></strong>
			<?php if ( $sa_source_lines ) : ?>
				<?php echo esc_html( implode( ' · ', $sa_source_lines ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'ارجاع اختصاصی به فایل نقشه در متن منبع ثبت نشده است.', 'sarzaminaryan-child' ); ?>
			<?php endif; ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'جمعیت و خانوار:', 'sarzaminaryan-child' ); ?></strong>
			<?php esc_html_e( 'در فایل ورودی هر دو شاخص با سال ۱۳۹۵ برچسب خورده‌اند؛ جدول جداگانهٔ جمعیت با رقم‌های گرد و بی‌تاریخ برای ساخت برآورد ۱۴۰۳ به‌کار نرفته است.', 'sarzaminaryan-child' ); ?>
		</li>
	</ul>

	<p class="sa-province-profile__source-caveat">
		<strong><?php esc_html_e( 'وضعیت راستی‌آزمایی:', 'sarzaminaryan-child' ); ?></strong>
		<?php esc_html_e( 'این مقادیر از فایل ارسالی استخراج شده‌اند؛ اصل نقشهٔ هر استان مستقیماً بررسی نشده است. بنابراین این کارت آن‌ها را «جدیدترین آمار رسمی» معرفی نمی‌کند.', 'sarzaminaryan-child' ); ?>
		<?php if ( $sa_source_url ) : ?>
			<a href="<?php echo esc_url( $sa_source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'فهرست نقشه‌های مرکز آمار ایران', 'sarzaminaryan-child' ); ?></a>
			<span><?php esc_html_e( '(صفحهٔ فهرست است، نه ارجاع مستقیم به فایل هر استان.)', 'sarzaminaryan-child' ); ?></span>
		<?php endif; ?>
	</p>

	<p class="sa-province-profile__source-caveat">
		<strong><?php esc_html_e( 'مساحت و اقلیم:', 'sarzaminaryan-child' ); ?></strong>
		<?php esc_html_e( 'در فایل‌های آماری ورودی نبودند؛ مقدار اصلی از دادهٔ فعلی صفحه/قالب می‌آید و منبع مستقیم یا سال ندارد. اگر با دادهٔ پایهٔ regions.json / Iran Map v0.8.0 تفاوت داشته باشد، هر دو مقدار در بازشوندهٔ همان شاخص نشان داده می‌شوند؛ همه نیازمند بازبینی‌اند.', 'sarzaminaryan-child' ); ?>
	</p>
</section>
