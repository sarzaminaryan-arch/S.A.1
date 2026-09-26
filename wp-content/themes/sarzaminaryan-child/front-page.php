<?php
/**
 * Front page: hero + search, stats, province grid, featured entities, latest posts.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main sa-home">

	<?php get_template_part( 'template-parts/home/hero' ); ?>

	<div class="container">
		<?php
		if ( get_theme_mod( 'sa_show_stats', true ) ) {
			get_template_part( 'template-parts/home/stats' );
		}

		$sa_sections = array_map( 'trim', explode( ',', (string) get_theme_mod( 'sa_home_sections', 'provinces,attractions,routes,foods,souvenirs,posts' ) ) );
		foreach ( $sa_sections as $sa_section ) {
			switch ( $sa_section ) {
				case 'provinces':
					get_template_part( 'template-parts/home/provinces' );
					break;
				case 'cities':
					sa_cards_section( get_posts( array( 'post_type' => 'city', 'posts_per_page' => 8, 'no_found_rows' => true ) ), 'شهرهای ایران', sa_archive_url( 'city' ), 'home-cities' );
					break;
				case 'attractions':
					sa_cards_section( get_posts( array( 'post_type' => 'attraction', 'posts_per_page' => 8, 'no_found_rows' => true ) ), 'جاذبه‌های دیدنی', sa_archive_url( 'attraction' ), 'home-attractions' );
					break;
				case 'routes':
					sa_cards_section( get_posts( array( 'post_type' => 'travel_route', 'posts_per_page' => 4, 'no_found_rows' => true ) ), 'مسیرهای سفر پیشنهادی', sa_archive_url( 'travel_route' ), 'home-routes' );
					break;
				case 'foods':
					sa_cards_section( get_posts( array( 'post_type' => 'local_food', 'posts_per_page' => 4, 'no_found_rows' => true ) ), 'غذاهای محلی', sa_archive_url( 'local_food' ), 'home-foods' );
					break;
				case 'souvenirs':
					sa_cards_section( get_posts( array( 'post_type' => 'souvenir', 'posts_per_page' => 4, 'no_found_rows' => true ) ), 'سوغات ایران', sa_archive_url( 'souvenir' ), 'home-souvenirs' );
					break;
				case 'posts':
					$sa_blog = (int) get_option( 'page_for_posts' );
					sa_cards_section( get_posts( array( 'post_type' => 'post', 'posts_per_page' => 3, 'no_found_rows' => true ) ), 'تازه‌های وبلاگ', $sa_blog ? get_permalink( $sa_blog ) : '', 'home-posts' );
					break;
			}
		}

		// Page content set as front page (optional editorial block under the sections).
		if ( have_posts() && 'page' === get_option( 'show_on_front' ) ) {
			while ( have_posts() ) {
				the_post();
				if ( '' !== trim( get_the_content() ) ) {
					echo '<section class="sa-section sa-home__content entry-content">';
					the_content();
					echo '</section>';
				}
			}
		}
		?>
	</div>

</main>

<?php
get_footer();
