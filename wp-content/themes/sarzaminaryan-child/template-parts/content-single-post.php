<?php
/**
 * Single blog post (educational guides, e.g. «آموزش‌های ضروری کمپینگ و بقا»).
 *
 * Order: header (category chips, title, byline) → featured image → lead (excerpt)
 * → body → FAQ accordion (sa_faq meta, no FAQPage JSON-LD) → sources (sa_sources)
 * → related guides from the same category.
 *
 * @since 2.13.0
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id     = get_the_ID();
$sa_cats   = get_the_category( $sa_id );
$sa_tags   = get_the_tags( $sa_id );
$sa_byline = trim( (string) get_post_meta( $sa_id, 'sa_byline', true ) );
$sa_risk   = trim( (string) get_post_meta( $sa_id, 'sa_risk_level', true ) );
$sa_policy = get_page_by_path( 'editorial-policy' );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-article sa-guide' ); ?>>
	<header class="entry-header">
		<?php if ( $sa_cats && ! is_wp_error( $sa_cats ) ) : ?>
			<p class="sa-article__cats">
				<?php foreach ( $sa_cats as $sa_cat ) : ?>
					<a class="sa-chip" href="<?php echo esc_url( get_category_link( $sa_cat ) ); ?>"><?php echo esc_html( $sa_cat->name ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
		<h1 class="entry-title"><?php the_title(); ?></h1>
		<p class="sa-article__byline">
			<?php if ( '' !== $sa_byline ) : ?>
				<span class="sa-article__author">نویسنده: <?php echo esc_html( $sa_byline ); ?></span>
			<?php endif; ?>
			<span class="sa-article__date">آخرین به‌روزرسانی: <time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>"><?php echo esc_html( sa_digits( get_the_modified_date() ) ); ?></time></span>
			<?php if ( '' !== $sa_risk ) : ?>
				<span class="sa-article__risk">سطح ریسک: <?php echo esc_html( $sa_risk ); ?></span>
			<?php endif; ?>
			<?php if ( $sa_policy && 'publish' === $sa_policy->post_status ) : ?>
				<a class="sa-article__policy" href="<?php echo esc_url( get_permalink( $sa_policy ) ); ?>">روش تهیهٔ مقالات</a>
			<?php endif; ?>
		</p>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="post-thumbnail"><?php the_post_thumbnail( 'sa-guide-cover', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></figure>
	<?php endif; ?>

	<div class="sa-entity__layout sa-guide__layout">
		<?php if ( has_excerpt() ) : ?>
			<p class="sa-entity__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages( array( 'before' => '<div class="page-links">صفحه‌ها:', 'after' => '</div>' ) );
			?>
		</div>

		<?php get_template_part( 'template-parts/entity/faq' ); ?>

		<?php sa_guide_sources_section( $sa_id ); ?>

		<?php if ( $sa_tags && ! is_wp_error( $sa_tags ) ) : ?>
			<p class="sa-article__tags">
				<span class="sa-article__tags-label">برچسب‌ها:</span>
				<?php foreach ( $sa_tags as $sa_tag ) : ?>
					<a class="sa-chip sa-chip--soft" href="<?php echo esc_url( get_tag_link( $sa_tag ) ); ?>"><?php echo esc_html( $sa_tag->name ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php sa_guide_related( $sa_id ); ?>
	</div>
</article>
