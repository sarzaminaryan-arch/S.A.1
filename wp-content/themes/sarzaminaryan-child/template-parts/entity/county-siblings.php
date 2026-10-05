<?php
/**
 * «همهٔ شهرستان‌های این استان» — fixed internal-linking block built from the
 * official registry, so the grid is complete even before every article exists.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id  = get_the_ID();
$sa_reg = sa_county_of_post( $sa_id );
if ( ! $sa_reg ) {
	return;
}
$sa_group = sa_counties_by_province();
if ( empty( $sa_group[ $sa_reg['province'] ] ) ) {
	return;
}
$sa_rows  = $sa_group[ $sa_reg['province'] ];
$sa_pname = sa_province_name( $sa_reg['province'] );
?>
<nav class="sa-county-siblings" aria-label="<?php echo esc_attr( 'شهرستان‌های استان ' . $sa_pname ); ?>">
	<h2 class="sa-county__title">
		<?php
		printf(
			/* translators: 1: province name, 2: county count */
			esc_html( '%1$s شهرستان استان %2$s' ),
			esc_html( sa_fa_digits( count( $sa_rows ) ) ),
			esc_html( $sa_pname )
		);
		?>
	</h2>
	<ul class="sa-county__chips sa-county__chips--siblings">
		<?php
		foreach ( $sa_rows as $sa_row ) :
			$sa_url     = sa_county_permalink( $sa_row['slug'] );
			$sa_current = ( $sa_row['slug'] === $sa_reg['slug'] );
			?>
			<li class="sa-county__chip<?php echo $sa_current ? ' is-current' : ''; ?>">
				<?php if ( $sa_current ) : ?>
					<span aria-current="page"><?php echo esc_html( $sa_row['name'] ); ?></span>
				<?php elseif ( $sa_url ) : ?>
					<a href="<?php echo esc_url( $sa_url ); ?>"><?php echo esc_html( $sa_row['name'] ); ?></a>
				<?php else : ?>
					<span class="is-pending"><?php echo esc_html( $sa_row['name'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
