<?php
/**
 * گارد «شناسنامهٔ تاشو روی موبایل» (v2.22.147).
 *
 * ادعاها:
 *   ۱) قالب شناسنامه دکمهٔ «نمایش کامل» را با `aria-expanded`/`aria-controls` چاپ
 *      می‌کند و کلاس `sa-cyber-grid--foldable` را فقط وقتی می‌گذارد که خانهٔ
 *      پنجمی وجود داشته باشد — شناسنامهٔ کوتاه دکمهٔ بی‌اثر نمی‌گیرد.
 *   ۲) پنهان‌سازی خانهٔ پنجم به بعد فقط داخل `@media (max-width: 640px)` است،
 *      پس دسکتاپ دست‌نخورده می‌ماند؛ و در چاپ همهٔ خانه‌ها کامل می‌آیند.
 *   ۳) اسکریپت فقط کلاس `is-open` را عوض می‌کند (خرابی نرم: بدون JS همه دیده می‌شوند).
 *
 * اجرا: php tests/test-identity-fold.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$sa_if_dir  = dirname( __DIR__ );
$sa_if_fail = 0;

/**
 * @param bool   $cond شرط.
 * @param string $msg  پیام.
 */
function sa_if_assert( $cond, $msg ) {
	global $sa_if_fail;
	if ( ! $cond ) {
		++$sa_if_fail;
		echo "FAIL: {$msg}\n";
	}
}

$sa_if_tpl = (string) file_get_contents( $sa_if_dir . '/template-parts/county/section-identity.php' );
$sa_if_css = (string) file_get_contents( $sa_if_dir . '/assets/css/child.css' );
$sa_if_js  = (string) file_get_contents( $sa_if_dir . '/assets/js/child.js' );

// ۱) قالب: دکمه و شرط وجود خانهٔ پنجم.
sa_if_assert( false !== strpos( $sa_if_tpl, '$sa_fold_extra = count( $sa_county_cells ) - 4;' ), 'قالب: آستانهٔ تاشو ۴ خانه است' );
sa_if_assert( false !== strpos( $sa_if_tpl, 'sa-cyber-grid--foldable' ), 'قالب: کلاس تاشو روی شبکه می‌نشیند' );
sa_if_assert( false !== strpos( $sa_if_tpl, 'data-sa-fold="county-identity"' ), 'قالب: قلاب دکمه وجود دارد' );
sa_if_assert( false !== strpos( $sa_if_tpl, 'aria-expanded="false"' ) && false !== strpos( $sa_if_tpl, 'aria-controls="county-identity-grid"' ), 'قالب: دکمه وضعیت و مقصد را برای صفحه‌خوان اعلام می‌کند' );
sa_if_assert( false !== strpos( $sa_if_tpl, 'نمایش کامل' ), 'قالب: برچسب دکمه «نمایش کامل» است' );
sa_if_assert( false !== strpos( $sa_if_tpl, 'if ( $sa_fold_extra > 0 ) :' ), 'قالب: دکمه فقط با وجود خانهٔ پنجم چاپ می‌شود' );

// ۲) CSS: پنهان‌سازی فقط موبایل، بازشدن با is-open، چاپ کامل.
$sa_if_pos = strpos( $sa_if_css, 'v2.22.147' );
sa_if_assert( false !== $sa_if_pos, 'css: بلوک v2.22.147 موجود است' );
$sa_if_block = false !== $sa_if_pos ? substr( $sa_if_css, (int) $sa_if_pos, 3000 ) : '';
sa_if_assert( false !== strpos( $sa_if_block, '@media (max-width: 640px)' ), 'css: پنهان‌سازی داخل مدیای موبایل است (دسکتاپ دست‌نخورده)' );
sa_if_assert( false !== strpos( $sa_if_block, 'nth-child(n + 5)' ), 'css: خانهٔ پنجم به بعد پنهان می‌شود' );
sa_if_assert( false !== strpos( $sa_if_block, 'display: none;' ), 'css: پنهان‌سازی واقعاً display است' );
sa_if_assert( false !== strpos( $sa_if_block, '.is-open .sa-cyber-grid--foldable > .sa-cyber-grid__cell:nth-child(n + 5)' ), 'css: حالت باز همهٔ خانه‌ها را برمی‌گرداند' );
sa_if_assert( false !== strpos( $sa_if_block, '@media print' ), 'css: چاپ کاغذی پنهان نمی‌کند' );
sa_if_assert( false !== strpos( $sa_if_block, 'display: flex !important' ), 'css: چاپ، پنهان‌سازی موبایل را override می‌کند' );
sa_if_assert( false === strpos( $sa_if_block, 'display: none !important' ), 'css: پنهان‌سازی با !important نیست' );

// ۳) JS: فقط کلاس و برچسب.
sa_if_assert( false !== strpos( $sa_if_js, "querySelectorAll( '.sa-cyber-id__fold-btn' )" ), 'js: دکمه‌های تاشو را پیدا می‌کند' );
sa_if_assert( false !== strpos( $sa_if_js, "classList.toggle( 'is-open' )" ), 'js: فقط کلاس is-open را عوض می‌کند' );
sa_if_assert( false !== strpos( $sa_if_js, "setAttribute( 'aria-expanded'" ), 'js: aria-expanded را هم‌زمان به‌روز می‌کند' );
sa_if_assert( false !== strpos( $sa_if_js, "'بستن' : 'نمایش کامل'" ), 'js: برچسب دکمه در حالت باز «بستن» می‌شود' );
sa_if_assert( false === strpos( $sa_if_js, '.style.display' ), 'js: نمایش/پنهان‌سازی به CSS سپرده شده، نه استایل درون‌خطی' );

if ( $sa_if_fail > 0 ) {
	echo "\n{$sa_if_fail} assertion(s) failed\n";
	exit( 1 );
}
echo "identity fold (v2.22.147): all assertions passed\n";
