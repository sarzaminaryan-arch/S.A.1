<?php
/**
 * The sidebar containing the main widget area.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>

<aside id="secondary" class="widget-area" aria-label="<?php esc_attr_e( 'Sidebar', 'sarzaminaryan' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>