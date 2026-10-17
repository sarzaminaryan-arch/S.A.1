# 📗 پاسخ‌نامهٔ تست ایجنت (ویژهٔ مالک و ارزیاب)

> این فایلِ پاسخ‌نامهٔ [`docs/AGENT_TEST.md`](AGENT_TEST.md) است و **نباید در اختیار ایجنتِ آزمون‌شونده قرار گیرد**.
> ایجنت باید نخست از روی دانش خود (و خواندن سه فایل مرجع ریشه: `CLAUDE.md`، `PROJECT_STATUS.md`، `ARCHITECTURE.md`) پاسخ دهد
> و ارزیاب پس از آن پاسخ‌ها را با این فایل تطبیق می‌دهد. نمرهٔ قبولی: **حداقل ۵ پاسخِ کامل از ۷**.
> آخرین به‌روزرسانی: ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) — منتقل‌شده از `AGENT_TEST.md` در مرحلهٔ ۷.

## پاسخ سطح ۱

**۱ — CPTها:** **شش CPT فعال** در `inc/entities-config.php` و `inc/post-types.php` ثبت شده است:
`province` (استان)، `city` (شهرستان)، `attraction` (دیدنی/نمای برتر)، `travel_route` (مسیر سفر)،
`local_food` (غذای محلی)، `souvenir` (سوغات). سه موردِ مهم‌تر که ستونِ سایت هستند:
**`province`، `city`، `attraction`** (دو مورد آخر در عمل از منو/پیشخوان پنهان‌اند و ساختار فعلی «۴ ستون» است).
یک هفتم هم وجود دارد: **`accommodation`** که **رزرو** است و فقط با ثابت `SA_ENABLE_ACCOMMODATION` فعال می‌شود.

**۲ — پیشوند:** پیشوند استاندارد قالب فرزند **`sa_`** است (مثلاً `sa_breadcrumbs()`, `sa_archive_url()`).
تعداد: **۶۳۹ تابع یکتا** با این پیشوند — از مجموع **۷۳۰** تابع تعریف‌شده در قالب فرزند.
قالب والد از پیشوند جداگانهٔ **`sarzaminaryan_`** با **۱۴** تابع استفاده می‌کند.
الگوهای مکمل: ثابت‌ها `SA_*`، کلیدهای متا `sa_*`، کلاس‌های ماژول مشارکت مردمی `SA_CC_*`.

**۳ — فایل‌های غایب:** **`wp-config.php`** و **`.htaccess`** در مخزن **وجود ندارند** و هرگز نباید ساخته یا پوش شوند.
همچنین هستهٔ وردپرس (`wp-admin/`، `wp-includes/` و فایل‌های ریشهٔ WP) و پوشهٔ `wp-content/uploads` در مخزن نیستند.
دلیل: سیاست «مخزن فقط سورس» در `docs/repository-retention-policy.md` و بند ۷ `AGENTS.md`.
تنها چیزی که کدِ قالب از `wp-config.php` انتظار دارد، ثابت اختیاری `SA_GITHUB_TOKEN` برای به‌روزرسان است.

**۴ — وضعیت PRها:**
| PR | عنوان | وضعیت |
|---|---|---|
| #21 | کارت آبرنگی ۲۸ دیدنی + فهرست مطالب (۲.۱۸.۱۷/۱۸) | **OPEN** (از ۲۰۲۶-۱۰-۱۵) |
| #22 | Theme 2.21.0 SEO and navigation fixes | **DRAFT** (از ۲۰۲۶-۱۰-۱۶) |
| #23 | Clean editorial notes in ready articles | **OPEN** (از ۲۰۲۶-۱۰-۱۷) |

## پاسخ سطح ۲

**۱ — زنجیرهٔ ارجاع (مدل تا نمایش):**
```
data-model/schema/data-model.yaml  (منبع دستی، v1.3)
        │  data-model/schema/build_child_config.py
        ▼
inc/entities-config.php   →  sa_entities_config()  (تولیدی؛ ⚠️ سرآیند آن هنوز v1.2 است)
        ▼
inc/post-types.php · inc/taxonomies.php · inc/meta-fields.php · inc/relations.php · inc/publish-gate.php
        ▼
template-parts/entity/*  ←  inc/schema.php (JSON-LD)  ←  inc/seo.php
```
یعنی هیچ CPT/طبقه‌بندی/متایی به‌صورت دستی ثبت نمی‌شود: همه چیز از **config تولیدی** خوانده می‌شود
(`sa_entities_config()`, `sa_taxonomies_config()`, `sa_entity_types()`).

برای **شناسنامهٔ شهرستان**، منطقِ ارجاع سه‌لایه است (در `inc/county-profile.php` و
`template-parts/entity/county-profile.php`):
1. ابتدا **کلید کانونی** خوانده می‌شود (۲۸ کلید استانداردِ تعریف‌شده در `sa_county_profile_fields()`)؛
2. اگر خالی بود، **fallback قدیمی** `sa_cty_*` / `sa_city_*`؛
3. آخرین چاره، دادهٔ **رجیستری** (`data/counties.php`).
دسترسی یکپارچه از راه `sa_county_profile_data()` انجام می‌شود و متاها با
`register_post_meta('city', $key, array('show_in_rest' => true))` ثبت شده‌اند تا ابزار
`tools/county-sync` بتواند از راه REST بنویسد. قواعد خروجی: خانهٔ خالی حذف می‌شود،
جمعیت بدون سالِ سرشماری چاپ نمی‌شود، و مختصات پیش از ساخت لینک نقشه اعتبارسنجی می‌شود.

**۲ — پیاده‌سازی صحیح nonce در AJAX:**
```php
// ۱) تحویل nonce و نشانی endpoint به جاوااسکریپت
function sa_example_assets() {
	wp_enqueue_script( 'sa-example', SA_CHILD_URI . 'assets/js/child.js', array( 'jquery' ), SA_CHILD_VERSION, true );
	wp_localize_script(
		'sa-example',
		'SA_EXAMPLE',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sa_example_action' ), // ویژهٔ همین عملیات
		)
	);
}
add_action( 'wp_enqueue_scripts', 'sa_example_assets' );

// ۲) سمت سرور: nonce → capability → پاک‌سازی → پاسخ
function sa_example_ajax_handler() {
	// الف) اعتبارسنجی nonce (در صورت نامعتبر بودن، ۴۰۳ برمی‌گرداند)
	check_ajax_referer( 'sa_example_action', 'nonce' );

	// ب) بررسی دسترسی — هرگز به nonce اکتفا نکن
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
	}

	// ج) پاک‌سازی و اعتبارسنجی ورودی
	$post_id = absint( $_POST['post_id'] ?? 0 ); // ترجیحاً filter_input
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => 'شناسه نامعتبر' ), 400 );
	}

	// د) انجام کار با API وردپرس (و در صورت نیاز به کوئری، prepare)
	$title = get_the_title( $post_id );

	// هـ) پاسخ استاندارد
	wp_send_json_success( array( 'title' => $title ) );
}
add_action( 'wp_ajax_sa_example_action', 'sa_example_ajax_handler' );        // کاربرِ واردشده
add_action( 'wp_ajax_nopriv_sa_example_action', 'sa_example_ajax_handler' ); // فقط اگر واقعاً عمومی است
```
```js
// سمت کلاینت
fetch( SA_EXAMPLE.ajaxUrl, {
	method: 'POST',
	credentials: 'same-origin',
	body: new URLSearchParams( {
		action:  'sa_example_action',
		nonce:   SA_EXAMPLE.nonce,
		post_id: 123,
	} ),
} );
```
نکات کلیدی: nonce باید **ویژهٔ عملیات** باشد، همراه با **بررسی capability** بیاید، برای کاربرِ
واردنشده **روترِ `nopriv` فقط در صورت نیاز واقعی** ثبت شود، و خروجی با `wp_send_json_*` برگردد.
در این پروژه ۳۷ نقطهٔ بررسی nonce/capability وجود دارد که هنوز **ممیزی نشده**اند.

## پاسخ سطح ۳

مشکلات کد:
1. **عدم پاک‌سازی و اعتبارسنجی ورودی** — `$_GET['id']` مستقیماً و بدون `absint()`/`sanitize_text_field()` استفاده شده است.
2. **تزریق SQL (SQL Injection)** — مقدار درون رشتهٔ کوئری الحاق شده؛ کوئری باید با `$wpdb->prepare()` و `%d` بسته شود.
3. **پیشوند جدول به‌صورت ثابت (`wp_users`)** — با تغییر پیشوند دیتابیس (در این پروژه احتمالاً `IR_`) کوئری می‌شکند؛ باید از `$wpdb->users` یا `$wpdb->prefix . 'users'` استفاده شود.
4. **نبود بررسی nonce و capability** — افشای اطلاعات کاربران (IDOR): هر بازدیدکننده‌ای می‌تواند هر کاربری را بخواند.
5. **دور زدن API وردپرس و کش** — برای گرفتن یک کاربر باید از `get_userdata()` استفاده کرد، نه کوئری دستی؛ ضمن این‌که فیلترها و کشِ آبجکت نادیده گرفته می‌شوند.
6. **چاپ مستقیم نتیجه بدون escape** — هر خروجی باید با `esc_html()` / `esc_attr()` چاپ شود.

نسخهٔ اصلاح‌شده:
```php
// الف) مسیر ترجیحی: استفاده از API خود وردپرس (بدون کوئری دستی)
$user_id = absint( $_GET['id'] ?? 0 );

if ( ! current_user_can( 'edit_users' ) ) {
	wp_die( 'دسترسی غیرمجاز', 403 );
}

$user = $user_id ? get_userdata( $user_id ) : false;

if ( ! $user ) {
	wp_send_json_error( array( 'message' => 'کاربر یافت نشد' ), 404 );
}

echo esc_html( $user->display_name );

// ب) اگر واقعاً به کوئری دستی نیاز است:
global $wpdb;
$user_id = absint( $_GET['id'] ?? 0 );

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, display_name FROM {$wpdb->users} WHERE ID = %d", $user_id )
);
```
قانون طلایی پروژه (بند ۶ `AGENTS.md`): **پاک‌سازی ورودی، escape خروجی، nonce + capability برای هر نوشتن.**
