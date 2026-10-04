<?php
/**
 * فهرست رسمی شهرستان‌های استان، زیر مقالهٔ هر استان.
 *
 * چرا خودکار: بررسی خروجی ۱۴۰۵/۰۷/۱۰ نشان داد هر ۳۱ مقالهٔ استان نام شهرستان‌ها را
 * در متن دارند ولی عملاً **هیچ لینکی** به صفحهٔ شهرستان‌ها نمی‌دهند (۲ لینک در کل سایت).
 * این بلوک همان پل استان↔شهرستان را بدون دست‌زدن به متن مقاله‌ها می‌سازد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id = get_the_ID();
if ( 'province' !== get_post_type( $sa_id ) ) {
	return;
}
$sa_pslug  = get_post_field( 'post_name', $sa_id );
$sa_groups = sa_counties_by_province();
if ( empty( $sa_groups[ $sa_pslug ] ) ) {
	return;
}
$sa_rows      = $sa_groups[ $sa_pslug ];
$sa_pname     = sa_province_name( $sa_pslug );
$sa_published = 0;
foreach ( $sa_rows as $sa_row ) {
	if ( sa_county_permalink( $sa_row['slug'] ) ) {
		++$sa_published;
	}
}
?>
<nav class="sa-county-siblings sa-province-counties" id="counties" aria-label="<?php echo esc_attr( 'شهرستان‌های استان ' . $sa_pname ); ?>">
	<h2 class="sa-county__title">
		<?php
		printf(
			esc_html( 'شهرستان‌های استان %1$s (%2$s شهرستان)' ),
			esc_html( $sa_pname ),
			esc_html( sa_fa_digits( count( $sa_rows ) ) )
		);
		?>
	</h2>
	<?php if ( $sa_published ) : ?>
		<p class="sa-county__hint">
			<?php
			printf(
				esc_html( 'راهنمای کامل %1$s شهرستان از این %2$s شهرستان منتشر شده است؛ روی نام هر کدام بزنید.' ),
				esc_html( sa_fa_digits( $sa_published ) ),
				esc_html( sa_fa_digits( count( $sa_rows ) ) )
			);
			?>
		</p>
	<?php endif; ?>
	<ul class="sa-county__chips">
		<?php
		foreach ( $sa_rows as $sa_row ) :
			$sa_url = sa_county_permalink( $sa_row['slug'] );
			?>
			<li class="sa-county__chip">
				<?php if ( $sa_url ) : ?>
					<a href="<?php echo esc_url( $sa_url ); ?>"><?php echo esc_html( $sa_row['name'] ); ?></a>
				<?php else : ?>
					<span class="is-pending"><?php echo esc_html( $sa_row['name'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
