<?php
/**
 * Bilingual county list, linked when a real published county page exists.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_entries = isset( $args['county_entries'] ) && is_array( $args['county_entries'] ) ? $args['county_entries'] : array();
$sa_count   = count( $sa_entries );
?>
<section class="sa-province-profile__module sa-province-profile__module--counties" aria-labelledby="sa-province-profile-counties-title">
	<div class="sa-province-profile__module-heading">
		<div>
			<p class="sa-province-profile__section-kicker" lang="en" dir="ltr">COUNTY LIST</p>
			<h3 class="sa-province-profile__section-title" id="sa-province-profile-counties-title"><?php esc_html_e( 'شهرستان‌های استان', 'sarzaminaryan-child' ); ?></h3>
		</div>
		<span class="sa-province-profile__count-badge"><?php echo esc_html( sa_fa_digits( (string) $sa_count ) ); ?> <?php esc_html_e( 'شهرستان', 'sarzaminaryan-child' ); ?></span>
	</div>

	<?php if ( $sa_entries ) : ?>
		<details class="sa-province-profile__county-details" open>
			<summary><?php esc_html_e( 'نمایش فهرست شهرستان‌ها', 'sarzaminaryan-child' ); ?></summary>
			<nav aria-label="<?php esc_attr_e( 'فهرست دوزبانهٔ شهرستان‌های استان', 'sarzaminaryan-child' ); ?>">
				<ul class="sa-province-profile__county-list">
					<?php foreach ( $sa_entries as $sa_entry ) : ?>
						<?php
						$sa_name    = isset( $sa_entry['name'] ) ? (string) $sa_entry['name'] : '';
						$sa_english = isset( $sa_entry['english'] ) ? (string) $sa_entry['english'] : '';
						$sa_url     = isset( $sa_entry['url'] ) ? (string) $sa_entry['url'] : '';
						if ( '' === $sa_name && '' === $sa_english ) {
							continue;
					}
						?>
						<li>
							<?php if ( $sa_url ) : ?>
								<a class="sa-province-profile__county-label" href="<?php echo esc_url( $sa_url ); ?>">
							<?php else : ?>
								<span class="sa-province-profile__county-label sa-province-profile__county-label--plain">
							<?php endif; ?>
								<span class="sa-province-profile__county-name"><?php echo esc_html( $sa_name ); ?></span>
								<?php if ( $sa_english ) : ?><small class="sa-province-profile__county-en" lang="en" dir="ltr"><?php echo esc_html( $sa_english ); ?></small><?php endif; ?>
							<?php if ( $sa_url ) : ?>
								</a>
							<?php else : ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</details>
	<?php endif; ?>
</section>
