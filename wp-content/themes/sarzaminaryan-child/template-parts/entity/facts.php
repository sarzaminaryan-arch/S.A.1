<?php
/**
 * Key facts card (structured data section — Level 7 "Structured Data").
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id   = get_the_ID();
$sa_rows = sa_entity_facts( $sa_id );
$sa_map  = sa_map_url( $sa_id );
if ( ! $sa_rows && ! $sa_map ) {
	return;
}
?>
<section class="sa-facts" id="facts">
	<h2 class="sa-facts__title">اطلاعات کلیدی</h2>
	<?php if ( $sa_rows ) : ?>
		<dl class="sa-facts__list">
			<?php foreach ( $sa_rows as $sa_row ) : ?>
				<div class="sa-facts__row">
					<dt><?php echo esc_html( $sa_row['label'] ); ?></dt>
					<dd><?php echo $sa_row['html'] ? wp_kses_post( $sa_row['value'] ) : esc_html( $sa_row['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>
	<?php if ( $sa_map ) : ?>
		<a class="sa-btn sa-btn--map" href="<?php echo esc_url( $sa_map ); ?>" target="_blank" rel="noopener nofollow">
			<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6-5.3-6-11a6 6 0 1 1 12 0c0 5.7-6 11-6 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
			مشاهده روی نقشه
		</a>
	<?php endif; ?>
</section>
