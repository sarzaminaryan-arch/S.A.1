<?php
/**
 * Internal county links, resolved only from published WordPress pages.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_posts          = isset( $args['county_posts'] ) && is_array( $args['county_posts'] ) ? $args['county_posts'] : array();
$sa_profile        = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$sa_stats          = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$sa_slug           = isset( $args['slug'] ) ? (string) $args['slug'] : '';
$sa_map_year       = isset( $sa_profile['map_year'] ) ? (int) $sa_profile['map_year'] : 0;
$sa_page_count     = count( $sa_posts );
$sa_map_count      = isset( $sa_stats['county_count'] ) ? (int) $sa_stats['county_count'] : null;
$sa_province_title = isset( $args['post_id'] ) ? get_the_title( (int) $args['post_id'] ) : '';
?>
<section class="sa-province-profile__module sa-province-profile__module--counties" aria-labelledby="sa-province-profile-counties-title">
	<div class="sa-province-profile__module-heading">
		<div>
			<h3 id="sa-province-profile-counties-title"><?php esc_html_e( 'فهرست پیونددار شهرستان‌ها', 'sarzaminaryan-child' ); ?></h3>
			<p><?php esc_html_e( 'هر پیوند از صفحهٔ منتشرشدهٔ همین سایت گرفته می‌شود؛ برای مقصد ناموجود، لینک حدسی ساخته نمی‌شود.', 'sarzaminaryan-child' ); ?></p>
		</div>
		<span class="sa-province-profile__count-badge"><?php echo esc_html( sa_fa_digits( (string) $sa_page_count ) ); ?> <?php esc_html_e( 'صفحهٔ منتشرشده', 'sarzaminaryan-child' ); ?></span>
	</div>

	<?php if ( $sa_posts ) : ?>
		<details class="sa-province-profile__county-details" open>
			<summary><?php echo esc_html( sprintf( __( 'مشاهدهٔ شهرستان‌های دارای صفحه (%s)', 'sarzaminaryan-child' ), sa_fa_digits( (string) $sa_page_count ) ) ); ?></summary>
			<nav aria-label="<?php echo esc_attr( sprintf( __( 'صفحه‌های شهرستان استان %s', 'sarzaminaryan-child' ), $sa_province_title ) ); ?>">
				<ul class="sa-province-profile__county-list">
					<?php foreach ( $sa_posts as $sa_post ) : ?>
						<?php
						$sa_label = sa_province_profile_county_label( $sa_post, $sa_slug );
						if ( '' === $sa_label ) {
							continue;
					}
						?>
						<li><a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>"><?php echo esc_html( $sa_label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</details>
	<?php else : ?>
		<p class="sa-province-profile__empty"><?php esc_html_e( 'در حال حاضر صفحهٔ شهرستانِ منتشرشده‌ای برای پیوند دادن در این استان پیدا نشد.', 'sarzaminaryan-child' ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $sa_map_count && $sa_page_count !== $sa_map_count ) : ?>
		<p class="sa-province-profile__review" role="note">
			<?php
			echo esc_html(
				sprintf(
					__( 'عددِ فایل آماری برای نقشهٔ %1$s: %2$s شهرستان؛ تعداد صفحه‌های منتشرشدهٔ سایت: %3$s. اختلاف این دو شمارش هنوز تطبیق داده نشده است.', 'sarzaminaryan-child' ),
					sa_fa_digits( (string) $sa_map_year ),
					sa_fa_digits( (string) $sa_map_count ),
					sa_fa_digits( (string) $sa_page_count )
				)
			);
			?>
		</p>
	<?php endif; ?>
</section>
