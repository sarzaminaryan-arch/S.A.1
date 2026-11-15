<?php
/**
 * Posts page (/blog/) — v2.22.78.
 *
 * Same markup as the parent theme's index.php, plus exactly one H1 (the posts
 * page's own title). The live scan of 2026-11-15 found /blog/ was the only
 * page of 627 without an H1 (Google: one clear main heading per page).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sa_blog_page  = (int) get_option( 'page_for_posts' );
$sa_blog_title = $sa_blog_page ? get_the_title( $sa_blog_page ) : '';
if ( '' === trim( (string) $sa_blog_title ) ) {
	$sa_blog_title = 'وبلاگ';
}
?>

<div class="container site-content">
<main id="primary" class="site-main">

	<header class="sa-archive__head">
		<h1 class="sa-archive__title"><?php echo esc_html( $sa_blog_title ); ?></h1>
	</header>

		<?php if ( have_posts() ) : ?>

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
