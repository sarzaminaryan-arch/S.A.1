<?php
/**
 * شبیه‌سازِ «دروازهٔ انتشار» برای مقالات آمادهٔ دیدنی‌ها.
 *
 * برای هر مدخلِ `data/ready-articles.php` یک پیش‌نویسِ آزمایشی با همان متاها ساخته
 * می‌شود و سپس همان تابع‌هایی صدا زده می‌شوند که هنگامِ زدنِ دکمهٔ «انتشار» در پیشخوان
 * اجرا می‌شوند (`sa_gate_missing()` + `sa_gate_split()` + `sa_gate_warnings()`).
 * نتیجه نشان می‌دهد کدام مقاله، بلافاصله بعد از درج، اجازهٔ انتشار می‌گیرد و کدام
 * یکی گیر می‌کند — بدونِ نیاز به آزمایشِ دستی روی سایتِ زنده.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-ready-gate-sim.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

define( 'SA_ENABLE_ACCOMMODATION', false );
define( 'SA_CHILD_DIR', '/ws/theme/' );
$GLOBALS['sa_current_test_post_id'] = 0;

function is_singular( $type = '' ) {
	return '' === $type || 'attraction' === $type;
}
function get_the_ID() {
	return (int) $GLOBALS['sa_current_test_post_id'];
}

require '/ws/theme/inc/entities-config.php';

/* ------------------------------------------------- کمینه‌های لازم برای گیت */

function sa_entity( $type ) {
	$entities = sa_entities_config();
	return isset( $entities[ $type ] ) ? $entities[ $type ] : null;
}
function sa_fa_digits( $value ) {
	return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}
// sa_entity_label در wp-stubs.php تعریف شده است.
function sa_count_sources( $raw ) {
	$public = preg_split( '/^\s*-{3,}.*$/mu', (string) $raw, 2 );
	return preg_match_all( '#https?://#i', $public[0] );
}
function sa_count_markers( $content ) {
	$content = (string) $content;
	$n       = 0;
	foreach ( array( 'نیازمند بررسی', 'منبع لازم' ) as $marker ) {
		$n += substr_count( $content, $marker );
	}
	return $n;
}
function sa_get_faq( $post_id ) {
	$rows = json_decode( (string) get_post_meta( $post_id, 'sa_faq', true ), true );
	return is_array( $rows ) ? $rows : array();
}
function has_term( $term = '', $taxonomy = '', $post = null ) {
	return (bool) $GLOBALS['sa_sim_has_term'];
}
$GLOBALS['sa_sim_has_term'] = true;

require '/ws/theme/inc/publish-gate.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/* ------------------------------------------------- دادهٔ مقالات آماده */

$articles = require '/ws/theme/data/ready-articles.php';
sa_ok( 'مقالات آماده بارگذاری شدند', is_array( $articles ) && count( $articles ) >= 20 );
sa_eq( 'شمارِ مقالات آماده', 23, count( $articles ) );

/**
 * جای‌گذاریِ جای‌نگهدارهای گالری با تصویرِ خالی (در شبیه‌ساز، کتابخانهٔ رسانه خالی است).
 *
 * @param string $content متنِ خام.
 * @return string
 */
function sa_sim_content( $content ) {
	return trim( preg_replace( '/\{\{GALLERY:[a-zA-Z0-9_-]+\}\}/', '', (string) $content ) );
}

/**
 * ارزیابیِ گیت برای یک مدخل.
 *
 * @param array<string,mixed> $article مدخل.
 * @return array{blocking:string[],warnings:string[],advisory:string[]}
 */
function sa_sim_gate( $article ) {
	$type = (string) $article['type'];
	$meta = isset( $article['meta'] ) && is_array( $article['meta'] ) ? $article['meta'] : array();
	$meta['sa_province_id'] = 1;
	if ( ! empty( $article['city'] ) ) {
		$meta['sa_city_id'] = 2;
	}

	$post_id = sa_add_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_name'    => (string) $article['slug'],
			'post_title'   => (string) $article['title'],
			'post_excerpt' => (string) $article['excerpt'],
			'post_content' => sa_sim_content( $article['content'] ),
		)
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, (string) $key, $value );
	}
	if ( ! empty( $article['faq'] ) ) {
		update_post_meta( $post_id, 'sa_faq', wp_json_encode( array_values( $article['faq'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	$missing = sa_gate_missing( $post_id, $type, null );
	list( $blocking, $advisory ) = sa_gate_split( $missing );
	$warnings = sa_gate_warnings( $post_id, $type, null );

	return array( 'blocking' => $blocking, 'warnings' => $warnings, 'advisory' => $advisory );
}

echo "== شبیه‌سازیِ دروازهٔ انتشار برای مقالات آماده ==\n\n";

$blocked      = array();
$city_blocked = array();
$rows         = array();

foreach ( $articles as $key => $article ) {
	$result = sa_sim_gate( $article );
	$rows[ $key ] = $result;
	$type         = (string) $article['type'];
	if ( $result['blocking'] ) {
		$blocked[ $key ] = $result['blocking'];
		if ( 'city' === $type ) {
			$city_blocked[ $key ] = $result['blocking'];
		}
	}
	printf(
		"%-42s %-11s مانع: %d | هشدار: %d\n",
		$key,
		$type,
		count( $result['blocking'] ),
		count( $result['warnings'] )
	);
	foreach ( $result['blocking'] as $item ) {
		echo '    ✗ ' . $item . "\n";
	}
	foreach ( $result['warnings'] as $item ) {
		echo '    • ' . $item . "\n";
	}
}

echo "\n";

/* ------------------------------------------------- داوری */

// مقالات جاذبه (دیدنی) باید بدونِ مانع درج شوند: کافی است مالک دکمهٔ «انتشار» را بزند.
$attraction_blocked = array();
foreach ( $rows as $key => $result ) {
	if ( isset( $articles[ $key ] ) && 'attraction' === $articles[ $key ]['type'] && $result['blocking'] ) {
		$attraction_blocked[ $key ] = $result['blocking'];
	}
}
sa_eq( 'هیچ مقالهٔ دیدنی مانع انتشار ندارد', array(), $attraction_blocked );

// مقالات شهرستانی (که خودشان صفحهٔ شهرستان‌اند) به تصویر شاخص نیاز دارند.
$city_expected = array();
foreach ( $rows as $key => $result ) {
	if ( isset( $articles[ $key ] ) && 'city' === $articles[ $key ]['type'] ) {
		$city_expected[ $key ] = $result['blocking'];
	}
}
$city_offenders = array();
foreach ( $city_expected as $key => $items ) {
	$unexpected = array_values( array_diff( $items, array( 'تصویر شاخص' ) ) );
	if ( $unexpected ) {
		$city_offenders[ $key ] = $unexpected;
	}
}
sa_eq( 'مقالهٔ شهرستانی فقط به تصویر شاخص نیاز دارد', array(), $city_offenders );

// هیچ مقاله‌ای نباید یادداشتِ تحریریِ جامانده داشته باشد (علتِ قفلِ «آبشار بیشه»).
$phrase_left = array();
foreach ( $articles as $key => $article ) {
	if ( false !== strpos( (string) $article['content'], 'برای انتشار نهایی' ) ) {
		$phrase_left[] = $key;
	}
}
sa_eq( 'هیچ مقالهٔ آماده‌ای عبارت تحریریِ جامانده ندارد', array(), $phrase_left );

// مقالهٔ «آبشار بیشه» — نمونهٔ گزارش‌شدهٔ مالک.
sa_ok( 'مقالهٔ «آبشار بیشه» در فهرست است', isset( $rows['bisheh-waterfall-dorud'] ) );
sa_eq( 'مقالهٔ «آبشار بیشه» مانع انتشار ندارد', array(), isset( $rows['bisheh-waterfall-dorud'] ) ? $rows['bisheh-waterfall-dorud']['blocking'] : array( 'یافت نشد' ) );
echo "\nهشدارهای «آبشار بیشه»:\n";
foreach ( isset( $rows['bisheh-waterfall-dorud'] ) ? $rows['bisheh-waterfall-dorud']['warnings'] : array() as $item ) {
	echo '  • ' . $item . "\n";
}

$total_blocking = 0;
foreach ( $rows as $result ) {
	$total_blocking += count( $result['blocking'] );
}
printf( "\nجمعِ مانع‌ها در ۲۳ مقاله: %d — مقاله‌های دارای مانع: %s\n", $total_blocking, $blocked ? implode( '، ', array_keys( $blocked ) ) : 'هیچ' );

sa_done();
