<?php
/**
 * Header — Sarzamin Aryan Child (v2 dark design).
 *
 * Dark navy bar matching the v2 home design (ink #0b1424, blue #2f6bff),
 * official logo, site name and the patriotic slogan under it.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_logo_url = SA_CHILD_URI . 'assets/img/logo.webp';
if ( has_custom_logo() ) {
	$sa_logo_id = (int) get_theme_mod( 'custom_logo' );
	$sa_logo    = wp_get_attachment_image_src( $sa_logo_id, 'full' );
	if ( $sa_logo ) {
		$sa_logo_url = $sa_logo[0];
	}
}
$sa_slogan = get_theme_mod( 'sa_home_slogan', 'چو ایران نباشد، تن من مباد' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary">پرش به محتوا</a>

<header id="masthead" class="site-header sa-hf-head">
	<div class="sa-hf-bar">
		<div class="container sa-hf-in">

			<a class="sa-hf-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="sa-hf-logo"><img src="<?php echo esc_url( $sa_logo_url ); ?>" width="96" height="96" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" decoding="async"></span>
				<span class="sa-hf-text">
					<span class="site-title"><?php bloginfo( 'name' ); ?></span>
					<span class="sa-hf-slogan"><?php echo esc_html( $sa_slogan ); ?></span>
				</span>
			</a>

			<nav id="site-navigation" class="main-navigation" aria-label="منوی اصلی">
				<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
					<span class="screen-reader-text">منو</span>
					<span class="menu-toggle__icon" aria-hidden="true"><span></span><span></span><span></span></span>
				</button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'container'      => false,
						'fallback_cb'    => 'sa_fallback_menu',
						'depth'          => 2,
					)
				);
				?>
			</nav>

			<div class="site-header__actions">
				<button class="search-toggle" aria-expanded="false" aria-controls="header-search" aria-label="باز کردن جست‌وجو">
					<svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				</button>
			</div>

		</div>
	</div>

	<div id="header-search" class="header-search" hidden>
		<div class="container">
			<?php get_search_form(); ?>
		</div>
	</div>

	<?php if ( has_nav_menu( 'secondary' ) ) : ?>
		<nav class="sa-subnav" aria-label="دسترسی سریع">
			<div class="container">
				<?php wp_nav_menu( array( 'theme_location' => 'secondary', 'container' => false, 'depth' => 1, 'menu_class' => 'sa-subnav__list' ) ); ?>
			</div>
		</nav>
	<?php endif; ?>
</header>

<?php if ( ! is_front_page() ) : ?>
	<div class="container"><?php sa_breadcrumbs(); ?></div>
<?php endif; ?>
