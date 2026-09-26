<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

get_header();
?>

<div class="container site-content">
<main id="primary" class="site-main">
		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'sarzaminaryan' ); ?></h1>
			</header>

			<div class="page-content">
				<p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try a search?', 'sarzaminaryan' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		</section>
</main><!-- #primary -->

</div><!-- .site-content -->

<?php
get_footer();