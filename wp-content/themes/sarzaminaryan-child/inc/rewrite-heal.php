<?php
/**
 * خودترمیمیِ قاعده‌های بازنویسی (rewrite) — نگهبانِ مسیرهای زیبا.
 *
 * چرا لازم شد (۱۴۰۵/۰۹/۰۷): پس از تغییر دامنه در پنل میزبان، ستون‌های
 * `siteurl` و `home` درست بودند و متغیرهای مسیر (`query_var`) هم کار
 * می‌کردند، اما `rewrite_rules` ذخیره‌شده قاعده‌های قدیمی را داشت. نتیجه:
 *
 *   /?province=tehran   → ۲۰۰، عنوان «استان تهران؛ راهنمای ۱۶ شهرستان»
 *   /province/tehran/   → ۴۰۴ (قالب «صفحه پیدا نشد»)
 *
 * یعنی داده، نوع‌محتوا و قالب سالم بودند و فقط قاعده‌های بازنویسی کهنه.
 * تا امروز رفعش دستی بود (Settings → Permalinks → Save) و همین باعث شد
 * صفحات استان/شهرستان/جاذبه ساعت‌ها برای بازدیدکننده و گوگل ۴۰۴ بدهند.
 *
 * این فایل همان کار را خودکار و امن انجام می‌دهد: نسخهٔ قاعده‌ها را با
 * «اثر انگشت» (نسخهٔ قالب + نامک‌های پایهٔ موجودیت‌ها + ساختار پیوند) مقایسه
 * می‌کند و فقط اگر ناسازگار بود یک بار flush می‌کند. flush در هر درخواست
 * انجام نمی‌شود، پس هزینهٔ کارایی ندارد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * اثر انگشت وضعیت فعلی: اگر این عوض شود، قاعده‌های ذخیره‌شده کهنه‌اند.
 *
 * @return string
 */
function sa_rewrite_fingerprint() {
	$bases = array();
	if ( function_exists( 'sa_entities' ) ) {
		foreach ( sa_entities() as $entity ) {
			if ( ! empty( $entity['status'] ) && 'active' !== $entity['status'] ) {
				continue;
			}
			if ( ! empty( $entity['url_base'] ) ) {
				$bases[] = $entity['url_base'];
			}
		}
	}
	sort( $bases );

	return md5(
		implode(
			'|',
			array(
				SA_CHILD_VERSION,
				(string) get_option( 'permalink_structure' ),
				implode( ',', $bases ),
			)
		)
	);
}

/**
 * آیا قاعده‌های ذخیره‌شده با وضعیت فعلی هم‌خوان‌اند؟
 *
 * @return bool
 */
function sa_rewrite_rules_stale() {
	$rules = get_option( 'rewrite_rules' );

	// خالی‌بودن یعنی وردپرس هنوز نساخته؛ flush لازم است.
	if ( ! is_array( $rules ) || ! $rules ) {
		return true;
	}

	if ( get_option( 'sa_rewrite_fingerprint' ) !== sa_rewrite_fingerprint() ) {
		return true;
	}

	// کمربند ایمنی: قاعدهٔ آرشیو هر موجودیت فعال باید واقعاً وجود داشته باشد.
	if ( function_exists( 'sa_entities' ) ) {
		foreach ( sa_entities() as $entity ) {
			if ( ! empty( $entity['status'] ) && 'active' !== $entity['status'] ) {
				continue;
			}
			if ( empty( $entity['url_base'] ) ) {
				continue;
			}
			// وردپرس کلید قاعده‌ها را بدون «^» ذخیره می‌کند و خودش در زمان
			// تطبیق آن را می‌گذارد؛ پس اینجا هم بدون «^» مقایسه می‌کنیم.
			$needle = preg_quote( $entity['url_base'], '/' ) . '/?$';
			$found  = false;
			foreach ( array_keys( $rules ) as $pattern ) {
				if ( $pattern === $needle ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * اگر بازسازی قاعده‌ها مشکل را حل نکند، هر درخواست دوباره flush می‌کند و
 * صفحه را کند می‌کند. این تابع ترمیم را یک ساعت خاموش نگه می‌دارد تا مدیر
 * فرصت دیدن گزارش را داشته باشد.
 *
 * @return bool
 */
function sa_rewrite_heal_suppressed() {
	return (bool) get_transient( 'sa_rewrite_heal_blocked' );
}

/**
 * اگر قاعده‌ها کهنه‌اند، یک بار بازسازی کن و اثر انگشت را ثبت کن.
 *
 * عمداً روی `init` با اولویت بالا (پس از ثبت نوع‌های محتوا) اجرا می‌شود،
 * چون flush پیش از ثبت CPT قاعده‌های آن‌ها را نمی‌سازد.
 */
function sa_heal_rewrite_rules() {
	// در حین نصب/فعال‌سازی خودکار دخالت نکن.
	if ( wp_installing() ) {
		return;
	}

	if ( sa_rewrite_heal_suppressed() ) {
		return;
	}

	if ( ! sa_rewrite_rules_stale() ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'sa_rewrite_fingerprint', sa_rewrite_fingerprint(), false );

	// کمربند ایمنی: اگر پس از بازسازی هم قاعده‌ها کهنه‌اند، احتمالاً نوع محتوا
	// ثبت نشده یا افزونه‌ای قاعده‌ها را عوض می‌کند؛ دیگر در هر درخواست تلاش نکن.
	if ( sa_rewrite_rules_stale() ) {
		$ttl = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
		set_transient( 'sa_rewrite_heal_blocked', time(), $ttl );
		return;
	}

	// کش صفحه‌های LiteSpeed ممکن است نسخهٔ ۴۰۴ را نگه داشته باشد.
	do_action( 'litespeed_purge_all', 'sarzaminaryan-child rewrite-heal' );
}
add_action( 'init', 'sa_heal_rewrite_rules', 101 );

/**
 * ابزار دستی: در پیشخوان، «تنظیمات → پیوندهای یکتا» همیشه ثبت اثر انگشت
 * می‌کند تا بازدید بعدی دوباره flush نکند.
 */
add_action(
	'update_option_permalink_structure',
	function () {
		update_option( 'sa_rewrite_fingerprint', sa_rewrite_fingerprint(), false );
	}
);
