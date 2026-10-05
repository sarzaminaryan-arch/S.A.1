<?php
/**
 * Sarzamin Aryan Child — bootstrap.
 *
 * Everything project-specific lives in this child theme (no plugins):
 * entity CPTs from the Master Data Model, taxonomies, meta boxes, publish gate,
 * SEO + JSON-LD, breadcrumbs, Jalali dates, security & performance hardening.
 *
 * @package Sarzaminaryan_Child
 * @author  محمدرضا لک
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_CHILD_VERSION', '2.11.9' );
define( 'SA_CHILD_DIR', trailingslashit( get_stylesheet_directory() ) );
define( 'SA_CHILD_URI', trailingslashit( get_stylesheet_directory_uri() ) );
define( 'SA_MODEL_VERSION', '1.1' );

// Accommodation entity is RESERVED in the data model (v1.0). Flip to true when v1.1 activates it.
if ( ! defined( 'SA_ENABLE_ACCOMMODATION' ) ) {
	define( 'SA_ENABLE_ACCOMMODATION', false );
}

$sa_child_includes = array(
	'inc/entities-config.php',   // generated from data-model.yaml
	'inc/helpers.php',
	'inc/setup.php',
	'inc/post-types.php',
	'inc/taxonomies.php',
	'inc/meta-fields.php',
	'inc/relations.php',
	'inc/publish-gate.php',
	'inc/jalali.php',
	'inc/seo.php',
	'inc/citations.php',
	'inc/region-map.php',
	'inc/schema.php',
	'inc/breadcrumbs.php',
	'inc/template-tags.php',
	'inc/gallery.php', // گالری آلبومی استان/شهرستان + تبدیل خودکار WebP
	'inc/geo-counties.php',
	'inc/geo-import.php',
	'inc/security.php',
	'inc/performance.php',
	'inc/customizer.php',
	'inc/admin.php',
	'inc/content-health.php',
	'inc/city-contrib.php', // مشارکت مردمی «شهر من» — داخلی قالب، بدون نیاز به افزونه
	'inc/contact-form.php', // فرم تماس داخلی قالب (ارسال به ایمیل تماس سایت)
	'inc/github-updater.php',
	'inc/activation.php',
);

foreach ( $sa_child_includes as $sa_child_file ) {
	require_once SA_CHILD_DIR . $sa_child_file;
}
unset( $sa_child_includes, $sa_child_file );
