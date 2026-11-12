<?php
/**
 * Header — Sarzamin Aryan Child (v2 dark design).
 *
 * Dark navy bar matching the unified v2.4 palette (navy #0b2a4a, blue #18558f),
 * official logo, site name and the patriotic slogan under it.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_logo_480 = function_exists( 'sa_brand_logo_url' ) ? sa_brand_logo_url( 'light', 480 ) : SA_CHILD_URI . 'assets/img/logo-light-480.webp';
$sa_logo_960 = function_exists( 'sa_brand_logo_url' ) ? sa_brand_logo_url( 'light', 960 ) : SA_CHILD_URI . 'assets/img/logo-light-960.webp';
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

			<?php $sa_brand = function_exists( 'sa_brand_name' ) ? sa_brand_name() : get_bloginfo( 'name' ); ?>
			<a class="sa-hf-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="sa-hf-logo"><img src="<?php echo esc_url( $sa_logo_480 ); ?>" srcset="<?php echo esc_url( $sa_logo_480 ); ?> 480w, <?php echo esc_url( $sa_logo_960 ); ?> 960w" sizes="(max-width: 480px) 82px, 106px" width="106" height="44" alt="" decoding="async" fetchpriority="high"></span>
				<span class="sa-hf-text">
					<span class="site-title"><?php echo esc_html( $sa_brand ); ?></span>
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
