<?php
/**
 * The template for displaying all single posts.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

get_header();
?>

<div class="container site-content">
<main id="primary" class="site-main">
		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/content', 'single' );

			the_post_navigation(
				array(
					'prev_text' => esc_html__( 'Previous post', 'sarzaminaryan' ),
					'next_text' => esc_html__( 'Next post', 'sarzaminaryan' ),
				)
			);

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
</main><!-- #primary -->

<?php get_sidebar(); ?>
</div><!-- .site-content -->

<?php
get_footer();