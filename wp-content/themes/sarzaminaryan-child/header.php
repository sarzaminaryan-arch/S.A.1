<?php
/**
 * Header — Sarzamin Aryan Child.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

<header id="masthead" class="site-header sa-header">
	<div class="container site-header__inner">

		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="brand__mark" aria-hidden="true">س</span>
					<span class="brand__text">
						<span class="site-title"><?php bloginfo( 'name' ); ?></span>
						<?php $sa_desc = get_bloginfo( 'description', 'display' ); ?>
						<?php if ( $sa_desc ) : ?>
							<span class="site-description"><?php echo esc_html( $sa_desc ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			<?php endif; ?>
		</div>

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
