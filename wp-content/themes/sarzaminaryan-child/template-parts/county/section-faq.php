<?php
/**
 * سکشن «سوالات متداول» — v2.22.145.
 *
 * گروه: محتوایی — فقط پرسش‌های معتبر چاپ می‌شوند؛ بدون پرسش، سکشن اصلاً رندر نمی‌شود.
 *
 * ورودی: $sa_id (int)، $sa_context (array)، $sa_profile_ext (array).
 * خروجی: HTML به‌صورت رشته، از طریق لایهٔ `inc/county-sections-loader.php`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id          = isset( $sa_id ) ? (int) $sa_id : (int) get_the_ID();
$sa_context     = isset( $sa_context ) ? (array) $sa_context : array();
$sa_profile_ext = isset( $sa_profile_ext ) ? (array) $sa_profile_ext : array();
if ( ! function_exists( 'sa_get_valid_faq' ) ) {
	return;
}
$sa_faq = sa_get_valid_faq( $sa_id );
if ( ! $sa_faq ) {
	return;
}
if ( function_exists( 'sa_toc_register' ) ) {
	sa_toc_register( 'سوالات متداول', 'faq', 900 );
}
?>
<section class="sa-faq-section" id="faq">
	<h2>سوالات متداول</h2>
	<?php foreach ( $sa_faq as $sa_i => $sa_row ) : ?>
		<details class="sa-faq-item" <?php echo 0 === $sa_i ? 'open' : ''; ?>>
			<summary><?php echo esc_html( $sa_row['q'] ); ?></summary>
			<div class="sa-faq-item__answer"><?php echo sa_render_markers( wp_kses_post( wpautop( sa_digits( $sa_row['a'] ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</details>
	<?php endforeach; ?>
</section>
<?php

