<?php
/**
 * 31-province grid (links to hub post when it exists, else to the term archive).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_terms = get_terms(
	array(
		'taxonomy'   => 'province_tax',
		'hide_empty' => false,
		'orderby'    => 'name',
	)
);
if ( is_wp_error( $sa_terms ) || ! $sa_terms ) {
	return;
}
?>
<section class="sa-section" id="home-provinces">
	<div class="sa-section__head">
		<h2 class="sa-section__title">استان‌های ایران</h2>
		<a class="sa-section__more" href="<?php echo esc_url( sa_archive_url( 'province' ) ); ?>">صفحه‌ی استان‌ها ←</a>
	</div>
	<ul class="sa-provinces">
		<?php
		foreach ( $sa_terms as $sa_term ) {
			$sa_post = sa_province_post_for_term( $sa_term );
			$sa_url  = $sa_post ? get_permalink( $sa_post ) : get_term_link( $sa_term );
			$sa_cnt  = (int) $sa_term->count;
			printf(
				'<li><a href="%s"%s><span class="sa-provinces__name">%s</span>%s</a></li>',
				esc_url( $sa_url ),
				$sa_post ? '' : ' class="is-empty"',
				esc_html( $sa_term->name ),
				$sa_cnt ? '<span class="sa-provinces__count">' . esc_html( sa_number( $sa_cnt ) ) . ' مطلب</span>' : ''
			);
		}
		?>
	</ul>
</section>
