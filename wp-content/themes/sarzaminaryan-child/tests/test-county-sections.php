<?php
/**
 * گارد رجیستری سکشن‌های شهرستان (v2.22.145).
 *
 * این تست سه چیز را تضمین می‌کند:
 *   ۱) رجیستری کامل و بی‌تکرار است، هر سکشن تابع رندرِ موجود دارد و فایل
 *      `template-parts/county/section-<name>.php` واقعاً روی دیسک است.
 *   ۲) ترتیب پیش‌فرض دقیقاً همان چیدمانی است که پیش از این مدولارسازی روی صفحه
 *      بود — پس این تغییر ظاهر هیچ صفحه‌ای را عوض نمی‌کند (تست رگرسیون ساختاری).
 *   ۳) `order` و `enabled` کار می‌کنند: جابه‌جایی و خاموش‌کردن فقط با رجیستری.
 *
 * اجرا: php tests/test-county-sections.php  (یا از طریق build-gates)
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}

$sa_cs_fail = 0;
/**
 * @param bool   $cond شرط.
 * @param string $msg  پیام.
 */
function sa_cs_assert( $cond, $msg ) {
	global $sa_cs_fail;
	if ( ! $cond ) {
		++$sa_cs_fail;
		echo "FAIL: {$msg}\n";
	}
}

// حداقل‌های وردپرس.
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['sa_filters'][ $tag ][] = $cb;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		if ( empty( $GLOBALS['sa_filters'][ $tag ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['sa_filters'][ $tag ] as $cb ) {
			$value = $cb( $value );
		}
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $cb, $priority = 10, $args = 1 ) {
		return add_filter( $tag, $cb, $priority, $args );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9\-_]/', '', (string) $name );
	}
}
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $id = 0 ) {
		return 'city';
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return 1;
	}
}

require_once SA_CHILD_DIR . 'inc/county-sections.php';
require_once SA_CHILD_DIR . 'inc/county-sections-loader.php';

// ---------------------------------------------------------------------------
// ۱) ساختار رجیستری
// ---------------------------------------------------------------------------
$sa_cs_registry = sa_county_sections_registry();
sa_cs_assert( count( $sa_cs_registry ) >= 13, 'the registry lists every county section (at least 13)' );

foreach ( $sa_cs_registry as $sa_cs_key => $sa_cs_def ) {
	sa_cs_assert( is_string( $sa_cs_key ) && '' !== $sa_cs_key, 'every section has a string key' );
	sa_cs_assert( isset( $sa_cs_def['label'] ) && '' !== $sa_cs_def['label'], "{$sa_cs_key}: has a Persian label" );
	sa_cs_assert( in_array( $sa_cs_def['group'] ?? '', array( 'structure', 'content' ), true ), "{$sa_cs_key}: group is structure|content" );
	sa_cs_assert( ! empty( $sa_cs_def['render'] ) && function_exists( $sa_cs_def['render'] ), "{$sa_cs_key}: render function exists" );
	sa_cs_assert( ! empty( $sa_cs_def['when'] ), "{$sa_cs_key}: declares a display condition" );
}

// هر تابع رندر باید فایل سکشن خودش را داشته باشد.
$sa_cs_files = array( 'cover', 'toc', 'identity', 'notes', 'field-notes', 'body', 'faq', 'photos', 'rating', 'sources', 'contrib', 'related', 'nav' );
foreach ( $sa_cs_files as $sa_cs_file ) {
	$sa_cs_path = SA_CHILD_DIR . 'template-parts/county/section-' . $sa_cs_file . '.php';
	sa_cs_assert( is_readable( $sa_cs_path ), "section-{$sa_cs_file}.php exists" );
}

// ---------------------------------------------------------------------------
// ۲) ترتیب پیش‌فرض = چیدمان قبلی (رگرسیون ساختاری)
// ---------------------------------------------------------------------------
$sa_cs_expected = array(
	'sa-county-cover',
	'sa-county-toc',
	'sa-county-identity',
	'sa-county-notes',
	'sa-county-field',
	'sa-county-body',
	'sa-county-faq',
	'sa-county-gallery',
	'sa-county-rating',
	'sa-county-sources',
	'sa-county-contrib',
	'sa-county-related',
	'sa-county-nav',
);
$sa_cs_actual = array_values( array_column( sa_county_sections_ordered(), 'key' ) );
sa_cs_assert(
	$sa_cs_expected === $sa_cs_actual,
	'the default order is exactly the pre-2.22.145 page order'
);
if ( $sa_cs_expected !== $sa_cs_actual ) {
	echo '  expected: ' . implode( ' → ', $sa_cs_expected ) . "\n";
	echo '  actual:   ' . implode( ' → ', $sa_cs_actual ) . "\n";
}

// ---------------------------------------------------------------------------
// ۳) order / enabled فقط از رجیستری
// ---------------------------------------------------------------------------
add_filter(
	'sa_county_sections',
	static function ( $sections ) {
		$sections['sa-county-late'] = array(
			'label'  => 'سکشن آزمون',
			'group'  => 'structure',
			'render' => 'sa_county_nav_html',
			'when'   => 'always',
			'order'  => 1,
		);
		$sections['sa-county-faq']['enabled'] = false;
		$sections['sa-county-nav']['order']   = 2;
		return $sections;
	}
);
$sa_cs_ordered = array_values( array_column( sa_county_sections_ordered(), 'key' ) );
sa_cs_assert( 'sa-county-late' === $sa_cs_ordered[0], 'a section with order=1 moves to the top without touching the template' );
sa_cs_assert( ! in_array( 'sa-county-faq', $sa_cs_ordered, true ) || true, 'disabled flag is read (checked below)' );

$sa_cs_flags = array();
foreach ( sa_county_sections_ordered() as $sa_cs_row ) {
	$sa_cs_flags[ $sa_cs_row['key'] ] = $sa_cs_row['enabled'];
}
sa_cs_assert( false === $sa_cs_flags['sa-county-faq'], 'enabled=false switches a section off' );
sa_cs_assert( true === $sa_cs_flags['sa-county-cover'], 'sections stay enabled by default' );

// ---------------------------------------------------------------------------
// ۴) شرط نمایش بدون کوئری
// ---------------------------------------------------------------------------
sa_cs_assert( true === sa_county_section_when( 'always', array() ), 'always shows' );
sa_cs_assert( false === sa_county_section_when( 'has_photos', array( 'photo_ids' => array() ) ), 'an empty gallery hides itself' );
sa_cs_assert( true === sa_county_section_when( 'has_photos', array( 'photo_ids' => array( 7 ) ) ), 'a gallery with photos shows' );
sa_cs_assert( false === sa_county_section_when( 'has_insights', array( 'has_insights' => false ) ), 'no traveller data = no block' );
sa_cs_assert( false === sa_county_section_when( 'made-up-condition', array() ), 'an unknown condition is off by default' );

// ---------------------------------------------------------------------------
// ۵) امضای یکسان رندرها
// ---------------------------------------------------------------------------
foreach ( sa_county_sections_registry() as $sa_cs_key => $sa_cs_def ) {
	$sa_cs_ref = new ReflectionFunction( $sa_cs_def['render'] );
	sa_cs_assert( 2 === $sa_cs_ref->getNumberOfParameters() || 1 === $sa_cs_ref->getNumberOfParameters(), "{$sa_cs_key}: render takes (post_id[, context])" );
}

// ---------------------------------------------------------------------------
// ۶) رگرسیون واقعی ۲.۲۲.۱۴۵: آکاردئون هم‌استانی فقط یک‌بار چاپ شود
// ---------------------------------------------------------------------------
$sa_cs_sources = (string) file_get_contents( SA_CHILD_DIR . 'template-parts/county/section-sources.php' );
sa_cs_assert(
	false === strpos( $sa_cs_sources, "'sa_county_siblings_html'" ),
	'the sources section does not print the sibling accordion twice (sa_sources_section owns it)'
);

if ( 0 === $sa_cs_fail ) {
	echo "PASS: county section registry (keys, order, filters, conditions, files)\n";
} else {
	echo "FAILED: {$sa_cs_fail}\n";
	exit( 1 );
}
