<?php
/**
 * 404 with helpful navigation.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-404">
	<section class="error-404 not-found">
		<p class="sa-404__code" aria-hidden="true">۴۰۴</p>
		<h1 class="page-title">این صفحه پیدا نشد</h1>
		<p>شاید نشانی تغییر کرده باشد. جست‌وجو کنید یا از بخش‌های اصلی شروع کنید:</p>
		<?php get_search_form(); ?>
		<ul class="sa-404__links">
			<?php foreach ( sa_entity_types() as $sa_type ) : ?>
				<li><a class="sa-chip" href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php sa_cards_section( get_posts( array( 'post_type' => 'attraction', 'posts_per_page' => 4, 'orderby' => 'rand', 'no_found_rows' => true ) ), 'شاید این‌ها را دوست داشته باشید', sa_archive_url( 'attraction' ) ); ?>
</main>
<?php
get_footer();
