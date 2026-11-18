<?php
/**
 * Generic entity page (province/city/attraction/route/food/souvenir).
 *
 * v2.19.2:
 * - Attraction featured images are hidden by default.
 * - Each of the 3 primary entities (`province`, `city`, `attraction`) displays its own
 *   Cyber-Tech Light-Mode Tabular Identity Card (`شناسنامه`) at the top of the page.
 * - Duplicate lead paragraphs and duplicate `اطلاعات کلیدی` (`facts.php`) boxes are
 *   omitted on `province`, `city`, and `attraction` so information inside the `شناسنامه`
 *   is never repeated outside it.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_type = get_post_type( $sa_id );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-entity-page sa-entity sa-entity--' . esc_attr( $sa_type ) ); ?>>

	<?php get_template_part( 'template-parts/entity/hero' ); ?>

	<div class="container sa-entity__layout">
		<?php
		if ( 'province' === $sa_type ) {
			// v2.22.70: تصویر شاخص استان فقط همین‌جا، اول صفحه و بالای شناسنامه.
			if ( function_exists( 'sa_province_featured_figure' ) ) {
				echo sa_province_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			get_template_part( 'template-parts/entity/province-identity' );
		} elseif ( 'city' === $sa_type ) {
			// v2.22.90: تصویر شاخص شهرستان فقط همین‌جا، اول صفحه و بالای شناسنامه (به‌غیر از کارت‌های فهرست).
			if ( function_exists( 'sa_county_featured_figure' ) ) {
				echo sa_county_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			get_template_part( 'template-parts/entity/county-profile' );
		} elseif ( 'attraction' !== $sa_type ) {
			if ( has_excerpt() ) {
				echo '<p class="sa-entity__lead">' . esc_html( get_the_excerpt() ) . '</p>';
			}
			get_template_part( 'template-parts/entity/facts' );
		}
		?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::rating_block(); ?>
		<?php endif; ?>

		<?php if ( 'attraction' === $sa_type && function_exists( 'sa_attraction_featured_figure' ) && function_exists( 'sa_attraction_images_visible' ) && sa_attraction_images_visible() ) : ?>
			<?php echo sa_attraction_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>

		<?php
		if ( function_exists( 'sa_toc_register_tail' ) ) {
			sa_toc_register_tail( $sa_id );
		}
		?>

		<div class="sa-entity__content entry-content">
			<?php the_content(); ?>
		</div>

		<?php get_template_part( 'template-parts/entity/faq' ); ?>

		<?php
		// v2.22.52: منابع آخرین بخشِ خودِ مقاله است (نه آخرین بخش صفحه)؛
		// بعد از آن فقط بخش‌های ثابتِ صفحه می‌آیند و ناوبریِ مقالهٔ قبلی/بعدی
		// آخرین بخش صفحه است.
		if ( function_exists( 'sa_sources_section' ) ) {
			sa_sources_section( $sa_id );
		}
		if ( function_exists( 'sa_facts_checked_note' ) ) {
			sa_facts_checked_note( $sa_id );
		}
		?>

		<?php
		// v2.22.53: بلوک «شهرستان‌های استان» از زیر مقاله حذف شد؛ همان فهرست
		// (شهرهای هم‌استانی) داخل شناسنامه هست و تکرار بود (تصمیم مالک).
		?>

		<?php
		// v2.22.98 — تصاویر مجاز مکان‌ها؛ v2.22.114 — کادر «مکان در یک نگاه» اسلایدر
		// نماهای همان مکان شد (همان شکل و اندازهٔ پیشین) و بلوک پایین «گالری تصاویر
		// شهرستان» است: اسلایدر تا دوازده قاب. اگر عکسی با مجوز و نام عکاس ثبت نشده
		// باشد، هیچ‌کدام چاپ نمی‌شوند (بدون جای خالی).
		if ( 'city' === $sa_type && function_exists( 'sa_photo_spotlight' ) ) {
			sa_photo_spotlight( $sa_id );
		}
		if ( 'city' === $sa_type && function_exists( 'sa_photo_strip' ) ) {
			sa_photo_strip( $sa_id );
		}
		?>

		<?php get_template_part( 'template-parts/entity/gallery' ); ?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::contrib_block(); ?>
		<?php endif; ?>

		<?php
		if ( function_exists( 'sa_related_articles' ) ) {
			sa_related_articles( $sa_id, $sa_type );
		}
		// v2.22.52: ناوبری مقالهٔ قبلی/بعد؛ آخرین بخش صفحه است.
		if ( function_exists( 'sa_entity_navigation' ) ) {
			sa_entity_navigation( $sa_id, $sa_type );
		}
		?>
	</div>

</article>
