<?php
/**
 * Blog index (posts page).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="container site-content">
	<main id="primary" class="site-main">
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<header class="sa-archive__head"><h1 class="sa-archive__title"><?php single_post_title(); ?></h1></header>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="sa-grid-cards sa-grid-cards--2">
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/content', get_post_type() );
				}
				?>
			</div>
			<?php the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
