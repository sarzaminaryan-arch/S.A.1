<?php
/**
 * Sidebar for blog views (entities use template-parts/entity/aside.php).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside id="secondary" class="widget-area" aria-label="نوار کناری">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
