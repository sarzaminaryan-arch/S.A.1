<?php
/**
 * Header — Sarzamin Aryan, 2026 design refresh.
 *
 * Reproduces the sticky top bar of the approved mockups (home / province /
 * county / attraction). Brand colours come from the active palette that
 * `inc/design-2026.php` puts on <body>, so the same markup serves all four
 * page designs.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safety switch: with the 2026 design off, render the previous header verbatim.
if ( function_exists( 'sa_2026_render_legacy' ) && sa_2026_render_legacy( 'header' ) ) {
	return;
}

$sa_brand   = function_exists( 'sa_brand_name' ) ? sa_brand_name() : get_bloginfo( 'name' );
$sa_tagline = 'دانشنامه سفر و شناخت ایران';
$sa_nav     = function_exists( 'sa_2026_nav_items' ) ? sa_2026_nav_items() : array();
$sa_cta     = function_exists( 'sa_2026_experience_cta' ) ? sa_2026_experience_cta() : array( 'label' => 'ثبت تجربه', 'url' => '#' );
?><!doctype html>
<html <?php language_attributes(); ?> class="scroll-smooth">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'bg-canvas text-ink font-sans antialiased selection:bg-petrol selection:text-white overflow-x-hidden' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary">پرش به محتوا</a>

<header class="sa-site-header sticky top-0 z-50 bg-surface/95 backdrop-blur-md border-b border-line">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">

		<a class="sa-hf-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( $sa_brand . ' — ' . $sa_tagline ); ?>">
			<?php
			/*
			 * Owner 2026-11-22: «لوگوی ناقص سبز را از تمام صفحات بردار، لوگوی فوتر کافی است.»
			 * The teal 40×40 tile was already gone from the front page; the cropped map mark
			 * inside it read as a broken logo on every other page, so the header now carries
			 * the wordmark only. The official lockup still ships in the footer and in the
			 * Organization schema — never a redrawn logo.
			 */
			?>
			<span class="sa-hf-brand__text block">
				<strong><?php echo esc_html( $sa_brand ); ?></strong>
				<span><?php echo esc_html( $sa_tagline ); ?></span>
			</span>
		</a>

		<nav aria-label="منوی اصلی" class="hidden lg:flex items-center gap-6 text-sm font-semibold text-slate-700">
			<?php foreach ( $sa_nav as $sa_item ) : ?>
				<a href="<?php echo esc_url( $sa_item['url'] ); ?>"
					class="<?php echo ! empty( $sa_item['active'] ) ? 'text-petrol font-bold border-b-2 border-ochre py-2' : 'hover:text-petrol transition-colors py-2'; ?>">
					<?php echo esc_html( $sa_item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="flex items-center gap-2">
			<button type="button"
				class="hidden sm:inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold bg-ochreSoft text-ochre hover:bg-ochre hover:text-white border border-ochre/30 px-3.5 py-2 rounded-xl transition-colors"
				data-sa-open-experience
				aria-haspopup="dialog">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
				</svg>
				<span><?php echo esc_html( $sa_cta['label'] ); ?></span>
			</button>

			<button type="button"
				class="lg:hidden w-10 h-10 rounded-xl border border-line bg-surface text-petrol flex items-center justify-center"
				data-sa-toggle-nav
				aria-controls="sa-mobile-nav"
				aria-expanded="false">
				<span class="screen-reader-text">منو</span>
				<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
				</svg>
			</button>
		</div>
	</div>

	<div id="sa-mobile-nav" class="lg:hidden hidden border-t border-line bg-surface">
		<nav aria-label="منوی موبایل" class="max-w-7xl mx-auto px-4 sm:px-6 py-3 grid gap-1 text-sm font-semibold text-slate-700">
			<?php foreach ( $sa_nav as $sa_item ) : ?>
				<a href="<?php echo esc_url( $sa_item['url'] ); ?>"
					class="px-3 py-2.5 rounded-xl <?php echo ! empty( $sa_item['active'] ) ? 'bg-petrolSoft text-petrol font-bold' : 'hover:bg-sand'; ?>">
					<?php echo esc_html( $sa_item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</header>
