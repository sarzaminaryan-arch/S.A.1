<?php
/**
 * The template for displaying search results pages.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

get_header();
?>

<div class="container site-content">
<main id="primary" class="site-main">
		<?php if ( have_posts() ) : ?>
			<header class="page-header">
				<h1 class="page-title">
					<?php
					printf(
						/* translators: %s: search query. */
						esc_html__( 'Search Results for: %s', 'sarzaminaryan' ),
						'<span>' . get_search_query() . '</span>'
					);
					?>
				</h1>
			</header>

			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'search' );
			endwhile;

			the_posts_pagination(
				array(
					'prev_text' => esc_html__( 'Previous', 'sarzaminaryan' ),
					'next_text' => esc_html__( 'Next', 'sarzaminaryan' ),
				)
			);
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
</main><!-- #primary -->

<?php get_sidebar(); ?>
</div><!-- .site-content -->

<?php
get_footer();