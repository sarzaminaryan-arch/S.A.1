<?php
/**
 * Entity counters.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_counts = sa_entity_counts();
$sa_total  = array_sum( $sa_counts );
if ( $sa_total < 1 ) {
	return;
}
?>
<section class="sa-stats" aria-label="آمار محتوا">
	<ul>
		<?php foreach ( $sa_counts as $sa_type => $sa_count ) : ?>
			<?php if ( $sa_count > 0 ) : ?>
				<li><a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><strong><?php echo esc_html( sa_number( $sa_count ) ); ?></strong><span><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></span></a></li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</section>
