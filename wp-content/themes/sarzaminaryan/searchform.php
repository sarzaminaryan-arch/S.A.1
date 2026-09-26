<?php
/**
 * Custom search form.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php echo esc_html_x( 'Search for:', 'label', 'sarzaminaryan' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder', 'sarzaminaryan' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
	</label>
	<button type="submit" class="search-submit">
		<?php echo esc_html_x( 'Search', 'submit button', 'sarzaminaryan' ); ?>
	</button>
</form>