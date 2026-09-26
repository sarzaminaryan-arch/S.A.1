<?php
/**
 * Search form.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_search_id = 'search-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $sa_search_id ); ?>">
		<span class="screen-reader-text">جست‌وجو برای:</span>
		<input type="search" id="<?php echo esc_attr( $sa_search_id ); ?>" class="search-field" placeholder="نام استان، شهر، جاذبه، غذا یا سوغات…" value="<?php echo get_search_query(); ?>" name="s" autocomplete="off">
	</label>
	<button type="submit" class="search-submit">جست‌وجو</button>
</form>
