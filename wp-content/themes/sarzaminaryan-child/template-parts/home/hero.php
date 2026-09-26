<?php
/**
 * Home hero with search.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_hero_img = get_theme_mod( 'sa_hero_image', '' );
?>
<section class="sa-hero<?php echo $sa_hero_img ? ' sa-hero--image' : ''; ?>" <?php echo $sa_hero_img ? 'style="background-image:url(' . esc_url( $sa_hero_img ) . ')"' : ''; ?>>
	<div class="container sa-hero__inner">
		<h1 class="sa-hero__title"><?php echo esc_html( get_theme_mod( 'sa_hero_title', 'ایران را استان به استان بشناسید' ) ); ?></h1>
		<p class="sa-hero__subtitle"><?php echo esc_html( get_theme_mod( 'sa_hero_subtitle', '۳۱ استان، صدها شهر و هزاران جاذبه — با اطلاعات دقیق، مسیر سفر، غذاها و سوغات هر منطقه.' ) ); ?></p>
		<div class="sa-hero__search"><?php get_search_form(); ?></div>
		<ul class="sa-hero__quick" aria-label="دسترسی سریع">
			<?php foreach ( sa_entity_types() as $sa_type ) : ?>
				<li><a href="<?php echo esc_url( sa_archive_url( $sa_type ) ); ?>"><?php echo esc_html( sa_entity_label( $sa_type, true ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
