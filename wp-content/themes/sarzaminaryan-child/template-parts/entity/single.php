<?php
/**
 * Generic entity page (province/city/attraction/route/food/souvenir).
 *
 * v2.19.2:
 * - Attraction featured images are hidden by default.
 * - Each of the 3 primary entities (`province`, `city`, `attraction`) displays its own
 *   Cyber-Tech Light-Mode Tabular Identity Card (`شناسنامه`) at the top of the page.
 * - Duplicate lead paragraphs and duplicate `اطلاعات کلیدی` (`facts.php`) boxes are
 *   omitted here on `province`, `city`, and `attraction`; counties render
 *   their compact summary through the section registry.
 *
 * v2.22.145 — the county branch is no longer written here. `city` reads
 * `inc/county-sections.php` (one registry: key, order, enabled, condition) and
 * renders each section from `template-parts/county/`. Reordering, disabling or
 * adding a county section is a one-line change in that registry and touches no
 * post in the database. The block below stays for the other post types.
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
		} elseif ( ! in_array( $sa_type, array( 'city', 'attraction' ), true ) ) {
			if ( has_excerpt() ) {
				echo '<p class="sa-entity__lead">' . esc_html( get_the_excerpt() ) . '</p>';
			}
			get_template_part( 'template-parts/entity/facts' );
		}
		?>

		<?php
		// v2.22.144: کارت جایگزین موقت برای نوشته‌های بی‌عکس (فقط post/attraction).
		// بالای محتوا و بیرون از بدنهٔ مقاله، پس هیچ سکشن یا ترتیبی عوض نمی‌شود.
		if ( ! in_array( $sa_type, array( 'province', 'city' ), true ) && function_exists( 'sa_fallback_card_html' ) ) {
			echo sa_fallback_card_html( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* inside.
		}
		?>
		<?php
		// v2.22.145 — صفحهٔ شهرستان حالا کامل از رجیستری ساخته می‌شود:
		// `inc/county-sections.php` می‌گوید کدام سکشن، با چه ترتیبی و با چه شرطی.
		// تغییر ترتیب/خاموش‌کردن هر بخش =
		// یک سطر در همان رجیستری، بدون دست‌زدن به هیچ نوشته‌ای در دیتابیس.
		if ( 'city' === $sa_type && function_exists( 'sa_county_render_sections' ) ) {
			echo sa_county_render_sections( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- هر سکشن با escape داخلی ساخته می‌شود.
		}
		?>

		<?php if ( 'city' !== $sa_type ) : ?>
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
			// v2.22.116 (دستور مالک): بخش «مکان در یک نگاه» برداشته شد؛ برای
			// شهرستان‌ها گالری جایش را گرفته و در رجیستری سکشن‌ها ثبت است.
			?>

			<?php
			// v2.22.52: منابع آخرین بخشِ خودِ مقاله است؛ بعد از آن فقط بخش‌های
			// ثابتِ صفحه می‌آیند و ناوبریِ مقالهٔ قبلی/بعدی آخرین بخش صفحه است.
			if ( function_exists( 'sa_sources_section' ) ) {
				sa_sources_section( $sa_id );
			}
			if ( function_exists( 'sa_facts_checked_note' ) ) {
				sa_facts_checked_note( $sa_id );
			}
			?>

			<?php get_template_part( 'template-parts/entity/gallery' ); ?>

			<?php
			if ( function_exists( 'sa_related_articles' ) ) {
				sa_related_articles( $sa_id, $sa_type );
			}
			if ( function_exists( 'sa_entity_navigation' ) ) {
				sa_entity_navigation( $sa_id, $sa_type );
			}
			?>
		<?php endif; ?>
	</div>

</article>
