<?php
/**
 * Shared archive body for entity CPTs and taxonomies: title, filters, card grid, pagination.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_type = is_post_type_archive() ? get_query_var( 'post_type' ) : ( get_post_type() ? get_post_type() : 'attraction' );
if ( is_array( $sa_type ) ) {
	$sa_type = reset( $sa_type );
}
$sa_obj = get_post_type_object( $sa_type );
$sa_top_views = is_post_type_archive( 'attraction' ) || is_tax( 'attraction_type' );
/* v2.13.3 — آرشیو استان‌ها و شهرستان‌ها به‌جای کارت‌های تصویردار، نوارهای مینیمال سفید را نشان می‌دهند (طرحی که تا ۲٫۱۳٫۲ در صفحهٔ اول بود). */
$sa_bars = '';
if ( is_post_type_archive( 'province' ) && function_exists( 'sa_province_bars_html' ) ) {
	$sa_bars = 'province';
} elseif ( is_post_type_archive( 'city' ) && function_exists( 'sa_city_bars_html' ) ) {
	$sa_bars = 'city';
}
?>
<header class="sa-archive__head<?php echo $sa_top_views ? ' sa-archive__head--topviews' : ''; ?>">
	<h1 class="sa-archive__title">
		<?php
		if ( $sa_top_views ) {
			echo esc_html( 'این شما و این دیدنی‌ها پاره‌های تن ایران عزیز' );
		} elseif ( 'province' === $sa_bars ) {
			echo esc_html( get_theme_mod( 'sa_sec_prov_title', 'استان‌های ایران' ) );
		} elseif ( 'city' === $sa_bars ) {
			echo esc_html( get_theme_mod( 'sa_sec_city_title', 'شهرستان‌های ایران' ) );
		} elseif ( is_post_type_archive() ) {
			// «دیدنی‌ها» + « ایران» → «دیدنی‌های ایران» (v2.13.3: اضافهٔ «ی» برای جمع‌های «‌ها»).
			$sa_plural = $sa_obj ? (string) $sa_obj->labels->name : post_type_archive_title( '', false );
			echo esc_html( 'ها' === mb_substr( $sa_plural, -2 ) ? $sa_plural . 'ی ایران' : $sa_plural . ' ایران' );
		} else {
			echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
		}
		?>
	</h1>
	<?php
	$sa_desc = is_post_type_archive() ? sa_seo_description() : term_description();
	if ( $sa_desc ) {
		echo '<div class="sa-archive__desc">' . wp_kses_post( wpautop( $sa_desc ) ) . '</div>';
	}
	?>
	<?php if ( is_post_type_archive( 'attraction' ) || is_tax( 'attraction_type' ) ) : ?>
		<?php $sa_types = get_terms( array( 'taxonomy' => 'attraction_type', 'hide_empty' => true ) ); ?>
		<?php if ( $sa_types && ! is_wp_error( $sa_types ) ) : ?>
			<nav class="sa-filters" aria-label="فیلتر بر اساس نوع دیدنی">
				<a class="sa-chip<?php echo is_post_type_archive( 'attraction' ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( sa_archive_url( 'attraction' ) ); ?>">همه</a>
				<?php foreach ( $sa_types as $sa_t ) : ?>
					<a class="sa-chip<?php echo is_tax( 'attraction_type', $sa_t->slug ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $sa_t ) ); ?>"><?php echo esc_html( $sa_t->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	<?php elseif ( ( is_category() || is_tag() ) && function_exists( 'sa_is_education_context' ) && sa_is_education_context() ) : ?>
		<?php
		/* v2.13.1 — شاخه‌های آموزش (برچسب‌ها) به‌عنوان فیلتر روی آرشیو دستهٔ کمپینگ و بقا و برچسب‌هایش. */
		$sa_edu_tags = sa_education_filter_tags();
		$sa_edu_url  = sa_education_url();
		?>
		<?php if ( $sa_edu_tags && $sa_edu_url ) : ?>
			<nav class="sa-filters" aria-label="شاخه‌های آموزش">
				<a class="sa-chip<?php echo is_category() ? ' is-active' : ''; ?>" href="<?php echo esc_url( $sa_edu_url ); ?>">همه</a>
				<?php foreach ( $sa_edu_tags as $sa_t ) : ?>
					<a class="sa-chip<?php echo is_tag( $sa_t->slug ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $sa_t ) ); ?>"><?php echo esc_html( $sa_t->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</header>

<?php if ( 'province' === $sa_bars ) : ?>
	<?php echo sa_province_bars_html( $GLOBALS['wp_query']->posts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
<?php elseif ( 'city' === $sa_bars && have_posts() ) : ?>
	<?php echo sa_city_bars_html( $GLOBALS['wp_query']->posts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
<?php elseif ( have_posts() ) : ?>
	<div class="sa-grid-cards">
		<?php
		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/card', 'entity' );
		}
		?>
	</div>
	<?php the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی', 'mid_size' => 2 ) ); ?>
<?php else : ?>
	<?php get_template_part( 'template-parts/content', 'none' ); ?>
<?php endif; ?>
