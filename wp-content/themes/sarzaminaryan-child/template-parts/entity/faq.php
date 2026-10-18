<?php
/**
 * Optional, visible FAQ accordion (native <details>); FAQPage schema syncs with visible items.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_faq = function_exists( 'sa_get_valid_faq' ) ? sa_get_valid_faq( get_the_ID() ) : sa_get_faq( get_the_ID() );
if ( ! $sa_faq ) {
	return;
}
// v2.18.17 — اگر قالبی این بخش را پیش از بدنه اعلام نکرده باشد، اینجا اعلام می‌شود.
sa_toc_register( 'سوالات متداول', 'faq', 900 );
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
