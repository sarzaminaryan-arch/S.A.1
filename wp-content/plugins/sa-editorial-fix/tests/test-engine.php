<?php
/**
 * تست موتور پاک‌سازی افزونهٔ sa-editorial-fix — اجرا با PHP CLI.
 *
 * فیکسچرهای `fixtures.json` با موتور مرجع پایتون (`tools/wp-agent/content_fix.py`)
 * تولید شده‌اند؛ این تست برابری موتور PHP را با همان خروجی‌ها می‌سنجد.
 *
 * اجرا: php tests/test-engine.php
 *
 * @package SA_Editorial_Fix
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ . '/' );

// Stubهای حداقلی وردپرس برای بارگذاری فایل افزونه در CLI.
function add_action() {}
function register_activation_hook() {}

require __DIR__ . '/../sa-editorial-fix.php';

$fixtures = json_decode( file_get_contents( __DIR__ . '/fixtures.json' ), true );
if ( ! is_array( $fixtures ) ) {
	echo "FAIL: fixtures.json خوانده نشد\n";
	exit( 1 );
}

$fail = 0;

foreach ( $fixtures as $f ) {
	list( $out, $log ) = SA_Editorial_Fix::clean( $f['input'] );
	if ( $out !== $f['expected'] ) {
		echo "FAIL clean: {$f['name']}\n--- got ---\n{$out}\n--- want ---\n{$f['expected']}\n";
		$fail++;
	} else {
		echo "PASS clean: {$f['name']}\n";
	}

	$wb = SA_Editorial_Fix::word_count( $f['input'] );
	$wa = SA_Editorial_Fix::word_count( $f['expected'] );
	if ( (int) $f['words_before'] !== $wb || (int) $f['words_after'] !== $wa ) {
		echo "FAIL words: {$f['name']} (got $wb←$wa, want {$f['words_before']}←{$f['words_after']})\n";
		$fail++;
	} else {
		echo "PASS words: {$f['name']}\n";
	}

	if ( count( $log ) < count( $f['log_contains'] ) ) {
		echo "FAIL log: {$f['name']} (got " . count( $log ) . ", want ≥ " . count( $f['log_contains'] ) . ")\n";
		$fail++;
	} else {
		echo "PASS log: {$f['name']}\n";
	}
}

// سنجش‌های سرراست موتور.
$cases = array(
	array( 'این فهرست اولیه است.', true ),
	array( 'پیش از سفر وضعیت راه را بررسی کنید.', false ),
	array( 'باغ ارم شیراز درختان سرو کهنسال دارد.', false ),
	array( 'اطلاعات این بخش نیازمند تطبیق با منابع رسمی است.', true ),
);
foreach ( $cases as $i => $c ) {
	$got = SA_Editorial_Fix::is_note( $c[0] );
	if ( $got !== $c[1] ) {
		echo "FAIL is_note #$i: {$c[0]}\n";
		$fail++;
	} else {
		echo "PASS is_note #$i\n";
	}
}

if ( SA_Editorial_Fix::banned_hits( '<p>این متن سالم است.</p>' ) !== array() ) {
	echo "FAIL banned_hits: false positive\n";
	$fail++;
} else {
	echo "PASS banned_hits clean\n";
}
if ( SA_Editorial_Fix::banned_hits( '<p>بازبینی انسانی لازم است.</p>' ) === array() ) {
	echo "FAIL banned_hits: missed\n";
	$fail++;
} else {
	echo "PASS banned_hits detect\n";
}

// یکسان‌ماندن متن بدون الگو (idempotency).
$clean_twice = SA_Editorial_Fix::clean( '<p>ماسوله روستایی پلکانی در گیلان است.</p>' );
$again      = SA_Editorial_Fix::clean( $clean_twice[0] );
if ( $again[0] !== $clean_twice[0] ) {
	echo "FAIL idempotency\n";
	$fail++;
} else {
	echo "PASS idempotency\n";
}

echo $fail ? "FAILURES: $fail\n" : "ALL PASS\n";
exit( $fail ? 1 : 0 );
