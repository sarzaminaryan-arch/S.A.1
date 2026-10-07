<?php
/**
 * Empty state.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="no-results not-found sa-empty">
	<h2>چیزی پیدا نشد</h2>
	<?php if ( is_search() ) : ?>
		<p>برای «<?php echo esc_html( get_search_query() ); ?>» نتیجه‌ای نداشتیم. نام استان، شهر یا دیدنی را کوتاه‌تر بنویسید.</p>
	<?php elseif ( is_post_type_archive() && current_user_can( 'edit_posts' ) ) : ?>
		<p>هنوز محتوایی در این بخش منتشر نشده است. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . get_query_var( 'post_type' ) ) ); ?>">اولین مورد را اضافه کنید</a>.</p>
	<?php else : ?>
		<p>هنوز محتوایی در این بخش منتشر نشده است. به‌زودی تکمیل می‌شود.</p>
	<?php endif; ?>
	<?php get_search_form(); ?>
</section>
