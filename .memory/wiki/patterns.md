# الگوهای تأییدشده کد و طراحی (Patterns)

> **قاعدهٔ استفاده:** پیش از نوشتن کد تازه در قالب فرزند، اول الگوهای زیر را بررسی و از همان ساختار تأییدشده استفاده کن.

## ۱. محافظت قطعه‌های HTML سفارشی در برابر فیلترهای `the_content`
- **کاربرد:** هرگاه در محتوای مقاله یا خروجی بلوکی لینک بیرونی یا ساختار حساسی وجود دارد که نباید توسط `sa_citations_filter()` (اولویت ۱۳) به پانویس `#sa-ref-N` تبدیل شود یا توسط `sa_autolink_content()` بازنویسی شود.
- **الگو (`inc/citations.php`, `inc/template-tags.php`):**
  1. روی کانتینر کلاس `sa-autolink-off` (یا `data-sa-autolink="off"`) بگذار تا `sa_autolink_content()` آن بخش را نادیده بگیرد.
  2. ساختار داخلی المان محافظت‌شده را تک‌لایه (بدون `<div>` تو در تو) طراحی کن و با `sa_protect_source_boxes()` (مشابه `sa_protect_photo_credit_captions()`) موقتاً با کلید placeholder (`<!--SA_SOURCE_BOXES_N-->`) پیش از اجرای فیلتر جایگزین و پس از اتمام بازگردانی کن.
  3. در محتوای بلوک گوتنبرگ از `<!-- wp:html -->...<!-- /wp:html -->` استفاده کن و المان‌های فرزند را تک‌خطی بنویس تا `wpautop` تگ `<p>` و `<br>` ناخواسته اضافه نکند.

## ۲. غلبه بر استایل `.sa-2026-prose a` بدون `!important`
- **کاربرد:** لینک‌ها یا دکمه‌های کارتی درون بدنهٔ `.sa-2026-prose` که نباید خط‌زیر متنی یا رنگ پیش‌فرض لینک مقاله را بگیرند.
- **الگو (`assets/css/child.css`):**
  - برای پشتیبانی هم‌زمان از نمای ۱۴۰۵ و نمای کلاسیک، سلکتور را دوگانه با اختصاصیت حداقل `(0,2,1)` یا `(0,3,1)` بنویس:
```css
.sa-2026-prose .sa-source-boxes a.sa-source-box,
.sa-source-boxes a.sa-source-box {
	text-decoration: none;
}
```

## ۳. چیدمان RTL-Safe با CSS Grid/Flex بومی و Logical Properties
- **کاربرد:** کارت‌های شهرستان‌ها، گرید شناسنامه، آکاردئون پرسش‌ها و منوهای چسبان.
- **الگو:**
  - به‌جای `left`/`right` و `margin-left`/`padding-right` از `inset-inline-start/end`، `padding-inline-start`، `margin-inline-start` و `border-inline-start` استفاده کن.
  - برای کارت‌های هم‌عرض و بدون نیاز به فریم‌ورک، از گرید واکنش‌گرا (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4` در نمای ۱۴۰۵ همراه قوانین fallback با متغیرهای `--sa-*` در `child.css`) استفاده کن تا در صورت خاموش‌بودن کلاس‌های utility هم چیدمان سالم بماند.
  - آکاردئون‌ها بدون JS اضافه با `<details class="sa-faq-item">` (اولین مورد `open`) و پنهان‌سازی نشانگر مرورگر (`::-webkit-details-marker` و `::marker`) همراه چرخش آیکون در `[open]` پیاده شوند.

## ۴. فونت محلی و پیش‌بارگذاری بدون افت CLS
- **کاربرد:** حفظ LCP و CLS در صفحات فارسی.
- **الگو (`functions.php`, `assets/css/child.css`, `tests/test-font-loading.php`):**
  - تنها فایل‌های `woff2` محلی داخل قالب بارگذاری شوند (`font-display: swap`).
  - هر وزن حیاتی بالای صفحه (در اینجا `Bold`) در `wp_head` اولویت پایین (`2`) با `<link rel="preload" as="font" type="font/woff2" crossorigin>` پیش‌بارگذاری شود.
  - فونت جایگزین سیستم با `@font-face` هم‌متریک (`size-adjust`, `ascent-override`, `descent-override`, `line-gap-override`) به ابتدای `--font-family-body` بعد از فونت اصلی متصل شود.

## ۵. بارگذاری شرطی دارایی‌ها و Vanilla JS
- **کاربرد:** جلوگیری از افزایش حجم JS/CSS در صفحاتی که به آن ماژول نیاز ندارند.
- **الگو (`functions.php`, `inc/design-2026.php`):**
  - اسکریپت‌ها در فوتر و با `'strategy' => 'defer'` و بدون وابستگی به `jquery` ثبت شوند.
  - استایل‌ها/اسکریپت‌های تخصصی با شرط‌های دقیق وردپرس (`is_singular( array( 'province', 'city' ) )`, `is_page_template(...)`) enqueue شوند و نسخهٔ کش با `sa_child_asset_version()` (`filemtime`) تنظیم گردد.
  - برای جستجو و فیلتر فارسی در سمت کاربر، از تابع `normalize()` (`ی/ک` عربی و نیم‌فاصله) استفاده شود.

## ۶. تغییر ایمن در قالب فرزند و همگام‌سازی نسخه
- **الگو:**
  1. نسخه در هر دو فایل `wp-content/themes/sarzaminaryan-child/style.css` (`Version: X.Y.Z`) و `functions.php` (`define( 'SA_CHILD_VERSION', 'X.Y.Z' );`) هم‌زمان به‌روز شود.
  2. تست PHP مرتبط در `tests/test-*.php` افزوده یا به‌روزرسانی و همراه با کل مجموعهٔ `build-gates` اجرا شود.
  3. کلید بازگشت فوری (`sa_2026_render_legacy()` یا `rollback_to` در جاب `wp-agent`) برای تغییرات ظاهر/ساختار محفوظ بماند.

## ۷. جداسازی پیوندهای تکراری و استایل‌دهی بر `sa-2026.css`
- **الگو (`inc/design-2026.php`, `assets/css/child.css`):**
  1. در `sa_2026_relation_content()` به‌جای حذف صفت `href`، تگ `<a ...>...</a>` منطبق با `$keys` باید با `sa_2026_unwrap_relation_links()` به‌کلی باز شود و فقط محتوای درونی بماند (با محافظت از بلوک‌های کد/اسکریپت و حفظ `<img>`/`<svg>`).
  2. چون `sa-2026.css` پس از `child.css` بارگذاری می‌شود، هر override روی نمای ۱۴۰۵ در `child.css` با پیشوند `body.sa-2026` نوشته شود.
  3. خط زیرین لینک‌های فارسی درون `.sa-2026-prose` فقط برای `a[href]:not([href=""]):not(.sa-source-box)` همراه با `text-decoration-skip-ink: none; text-underline-offset: 6px; text-decoration-thickness: 1px` و تیترهای بخش‌ها با کلاس `.sa-section-heading` به‌صورت تک‌خطی موبایل و پس‌زمینهٔ سبز روشن `#E7F5EC` (به استثنای CTAهای پس‌زمینهٔ تیره) اعمال شود.
