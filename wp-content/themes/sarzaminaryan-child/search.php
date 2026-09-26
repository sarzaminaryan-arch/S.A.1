<?php
/**
 * Search results (posts, pages and all entities).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-archive sa-search">
	<header class="sa-archive__head">
		<h1 class="sa-archive__title">نتایج جست‌وجو برای: «<?php echo esc_html( get_search_query() ); ?>»</h1>
		<?php global $wp_query; ?>
		<p class="sa-archive__desc"><?php echo esc_html( sa_number( (int) $wp_query->found_posts ) ); ?> نتیجه</p>
		<div class="sa-search__form"><?php get_search_form(); ?></div>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="sa-grid-cards">
			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content', 'search' );
			}
			?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</main>
<?php
get_footer();
