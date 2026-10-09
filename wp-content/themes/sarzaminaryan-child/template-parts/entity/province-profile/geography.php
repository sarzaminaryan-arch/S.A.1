<?php
/**
 * Province center, climate and neighboring places.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_center       = isset( $args['center_name'] ) ? (string) $args['center_name'] : '';
$sa_center_en    = isset( $args['center_english'] ) ? (string) $args['center_english'] : '';
$sa_center_post  = isset( $args['center_post'] ) && $args['center_post'] instanceof WP_Post ? $args['center_post'] : null;
$sa_climate      = isset( $args['climate'] ) ? trim( (string) $args['climate'] ) : '';
$sa_profile      = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$sa_groups       = isset( $sa_profile['neighbor_groups'] ) && is_array( $sa_profile['neighbor_groups'] ) ? $sa_profile['neighbor_groups'] : array();

if ( ! $sa_center && '' === $sa_climate && ! $sa_groups ) {
	return;
}
?>
<section class="sa-province-profile__module sa-province-profile__module--geography" aria-labelledby="sa-province-profile-geo-title">
	<h3 class="sa-province-profile__section-title" id="sa-province-profile-geo-title"><?php esc_html_e( 'مرکز استان و همسایه‌ها', 'sarzaminaryan-child' ); ?></h3>

	<?php if ( $sa_center || '' !== $sa_climate ) : ?>
		<dl class="sa-province-profile__identity-facts">
			<?php if ( $sa_center ) : ?>
				<div class="sa-province-profile__identity-fact">
					<dt><?php esc_html_e( 'مرکز استان', 'sarzaminaryan-child' ); ?></dt>
					<dd>
						<?php if ( $sa_center_post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $sa_center_post ) ); ?>"><?php echo esc_html( $sa_center ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $sa_center ); ?>
						<?php endif; ?>
						<?php if ( $sa_center_en ) : ?>
							<small class="sa-province-profile__english-inline" lang="en" dir="ltr"><?php echo esc_html( $sa_center_en ); ?></small>
						<?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $sa_climate ) : ?>
				<div class="sa-province-profile__identity-fact">
					<dt><?php esc_html_e( 'آب‌وهوا', 'sarzaminaryan-child' ); ?></dt>
					<dd><?php echo esc_html( $sa_climate ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $sa_groups ) : ?>
		<div class="sa-province-profile__neighbors">
			<h4><?php esc_html_e( 'استان‌ها و مرزهای پیرامونی', 'sarzaminaryan-child' ); ?></h4>
			<ul>
				<?php foreach ( $sa_groups as $sa_group ) : ?>
					<?php
					$sa_direction = isset( $sa_group['direction'] ) ? (string) $sa_group['direction'] : '';
					$sa_places    = isset( $sa_group['places'] ) && is_array( $sa_group['places'] ) ? $sa_group['places'] : array();
					if ( ! $sa_direction || ! $sa_places ) {
						continue;
					}
					?>
					<li>
						<span class="sa-province-profile__direction"><?php echo esc_html( $sa_direction ); ?></span>
						<span class="sa-province-profile__places">
							<?php foreach ( $sa_places as $sa_place_index => $sa_place ) : ?>
								<?php
								$sa_target_id  = sa_province_profile_neighbor_post_id( $sa_place );
								$sa_place_en   = sa_province_profile_place_english( $sa_place );
								$sa_clean_place = sa_province_profile_clean_place( $sa_place );
								?>
								<?php if ( $sa_place_index > 0 ) : ?><span class="sa-province-profile__separator" aria-hidden="true">،</span><?php endif; ?>
								<?php if ( $sa_target_id ) : ?>
									<a class="sa-province-profile__place" href="<?php echo esc_url( get_permalink( $sa_target_id ) ); ?>">
										<span><?php echo esc_html( $sa_clean_place ); ?></span>
										<?php if ( $sa_place_en ) : ?><small lang="en" dir="ltr"><?php echo esc_html( $sa_place_en ); ?></small><?php endif; ?>
									</a>
								<?php else : ?>
									<span class="sa-province-profile__place sa-province-profile__place--border">
										<span><?php echo esc_html( $sa_clean_place ); ?></span>
										<?php if ( $sa_place_en ) : ?><small lang="en" dir="ltr"><?php echo esc_html( $sa_place_en ); ?></small><?php endif; ?>
									</span>
								<?php endif; ?>
							<?php endforeach; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
