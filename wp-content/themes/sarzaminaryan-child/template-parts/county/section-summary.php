<?php
/**
 * Compact county excerpt and key facts.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();
get_template_part( 'template-parts/entity/facts', null, array( 'post_id' => $sa_id ) );
$sa_summary_facts = trim( (string) ob_get_clean() );
$sa_summary_lead = has_excerpt( $sa_id ) ? get_the_excerpt( $sa_id ) : '';
if ( '' === $sa_summary_lead && '' === $sa_summary_facts ) {
	return;
}
?>
<div class="sa-county-summary">
	<?php if ( '' !== $sa_summary_lead ) : ?>
		<p class="sa-entity__lead"><?php echo esc_html( $sa_summary_lead ); ?></p>
	<?php endif; ?>
	<?php echo $sa_summary_facts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by facts.php. ?>
</div>
