<?php
/**
 * Single blog post.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-article' ); ?>>
	<header class="entry-header">
		<?php $sa_cats = get_the_category(); ?>
		<?php if ( $sa_cats ) : ?>
			<p class="sa-article__cats"><?php foreach ( $sa_cats as $sa_cat ) : ?><a href="<?php echo esc_url( get_category_link( $sa_cat ) ); ?>"><?php echo esc_html( $sa_cat->name ); ?></a> <?php endforeach; ?></p>
		<?php endif; ?>
		<h1 class="entry-title"><?php the_title(); ?></h1>
		<div class="entry-meta">
			<?php sa_posted_on(); ?>
			<span class="byline"> · نویسنده: <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a></span>
		</div>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="post-thumbnail"><?php the_post_thumbnail( 'sa-hero', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></figure>
	<?php endif; ?>

	<div class="entry-content">
		<?php
		the_content();
		wp_link_pages( array( 'before' => '<div class="page-links">صفحه‌ها:', 'after' => '</div>' ) );
		?>
	</div>

	<?php get_template_part( 'template-parts/entity/faq' ); ?>

	<footer class="entry-footer">
		<?php the_tags( '<p class="sa-article__tags">برچسب‌ها: ', '، ', '</p>' ); ?>
	</footer>
</article>

<?php
the_post_navigation(
	array(
		'prev_text' => '<span class="nav-subtitle">نوشته‌ی قبلی</span> <span class="nav-title">%title</span>',
		'next_text' => '<span class="nav-subtitle">نوشته‌ی بعدی</span> <span class="nav-title">%title</span>',
	)
);

if ( comments_open() || get_comments_number() ) {
	comments_template();
}
