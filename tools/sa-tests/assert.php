<?php
/**
 * ابزارهای سادهٔ ادعا (assert) برای تست‌های قالب.
 *
 * @package sa-tests
 */

$GLOBALS['sa_assert_ok']   = 0;
$GLOBALS['sa_assert_fail'] = 0;

/**
 * ثبت نتیجهٔ یک ادعا.
 *
 * @param string $label عنوان.
 * @param bool   $cond  درستی.
 * @param string $extra توضیحِ بیشتر در صورت شکست.
 */
function sa_ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['sa_assert_ok']++;
		echo "  ✓ " . $label . "\n";
		return;
	}
	$GLOBALS['sa_assert_fail']++;
	echo "  ✗ " . $label . ( '' === $extra ? '' : ' — ' . $extra ) . "\n";
}

/**
 * برابریِ دقیق.
 *
 * @param string $label    عنوان.
 * @param mixed  $expected مقدارِ مورد انتظار.
 * @param mixed  $actual   مقدارِ واقعی.
 */
function sa_eq( $label, $expected, $actual ) {
	$same = $expected === $actual;
	$extra = $same ? '' : 'expected: ' . var_export( $expected, true ) . ' | actual: ' . var_export( $actual, true );
	sa_ok( $label, $same, $extra );
}

/**
 * وجودِ یک رشته در رشته‌ای دیگر.
 *
 * @param string $label    عنوان.
 * @param string $needle   عبارتِ جستجوشده.
 * @param string $haystack متن.
 */
function sa_has( $label, $needle, $haystack ) {
	$found = false !== strpos( (string) $haystack, (string) $needle );
	sa_ok( $label, $found, $found ? '' : 'not found: ' . $needle . ' in: ' . substr( (string) $haystack, 0, 400 ) );
}

/**
 * نبودِ یک رشته در رشته‌ای دیگر.
 *
 * @param string $label    عنوان.
 * @param string $needle   عبارت.
 * @param string $haystack متن.
 */
function sa_lacks( $label, $needle, $haystack ) {
	sa_ok( $label, false === strpos( (string) $haystack, (string) $needle ), 'unexpected: ' . $needle );
}

/**
 * چاپِ خلاصه و پایان.
 */
function sa_done() {
	$ok   = (int) $GLOBALS['sa_assert_ok'];
	$fail = (int) $GLOBALS['sa_assert_fail'];
	echo "\n" . str_repeat( '-', 60 ) . "\n";
	echo 'PASS: ' . $ok . '   FAIL: ' . $fail . "\n";
	exit( $fail > 0 ? 1 : 0 );
}
