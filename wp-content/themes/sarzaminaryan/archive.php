<?php
/**
 * The template for displaying archive pages.
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
				<?php
				the_archive_title( '<h1 class="page-title">', '</h1>' );
				the_archive_description( '<div class="archive-description">', '</div>' );
				?>
			</header>

			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', get_post_type() );
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