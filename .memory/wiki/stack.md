# پشتهٔ فنی فعلی سایت (Stack)

> **آخرین راستی‌آزمایی:** ۲۰۲۶-۱۱-۲۳ (`arena/a2d1e561-s-a-1`، آماده برای انتشار `2.24.18`). پیش از انتخاب هر کتابخانه، ابزار یا الگوی جدید این فایل را بررسی کن.

## ۱. هستهٔ وردپرس و قالب‌ها
- **وردپرس هدف:** Requires at least `6.4`, Tested up to `7.1`, Requires PHP `7.4+`.
- **قالب والد:** `sarzaminaryan` نسخهٔ `1.1.0` (`wp-content/themes/sarzaminaryan/`) — **ممنوعیت تغییر قطعی** (محافظت‌شده در `.github/workflows/protect.yml`). تمام توسعه فقط در قالب فرزند انجام می‌شود.
- **قالب فرزند فعال:** `sarzaminaryan-child` نسخهٔ `2.24.17` روی تولید (`2.24.18` در `arena/a2d1e561-s-a-1`، با `SA_CHILD_VERSION` در `functions.php` و `Version` در `style.css`).
- **مدل داده:** `SA_MODEL_VERSION = '1.3'`.
- **نوع‌های محتوای اختصاصی (CPT) و طبقه‌بندی‌ها (`inc/post-types.php`, `inc/taxonomies.php`, `inc/guide-cpt.php`):**
  - فعال: `province`, `city`, `attraction`, `guide` (`/travel-guide/`), `travel_route`, `local_food`, `souvenir`.
  - رزرو/غیرفعال: `accommodation` (`SA_ENABLE_ACCOMMODATION = false`).
  - پیشخوان و مشارکت مردمی «شهر من» (`inc/city-contrib.php`, `inc/experience-form.php`, `inc/contact-form.php`) داخلی قالب و بدون افزونه پیاده‌سازی شده‌اند.

## ۲. فونت و تایپوگرافی فارسی (RTL)
- **فونت اصلی:** `Vazirmatn` کاملاً **self-hosted** درون قالب والد:
  - مسیر فایل‌ها: `wp-content/themes/sarzaminaryan/assets/fonts/fa/Vazirmatn-{Regular,Medium,Bold}.woff2` (وزن‌های `400`, `500`, `700`, فرمت `woff2`, مجوز `SIL OFL 1.1`).
  - تعریف `@font-face` در `wp-content/themes/sarzaminaryan/assets/css/fonts.css` با `font-display: swap` و **صفر درخواست خارجی**.
  - `assets/fonts/og/Vazirmatn-Bold.ttf` در قالب فرزند صرفاً برای رندر سرور-ساید تصاویر `og:image` (`inc/og-card.php`).
- **پیش‌بارگذاری و کنترل CLS:**
  - والد `Regular` را پیش‌بارگذاری می‌کند؛ فرزند `Vazirmatn-Bold.woff2` را در `wp_head` اولویت ۲ با `sa_child_preload_bold_font()` (`functions.php`) پیش‌بارگذاری می‌کند.
  - فونت جایگزین هم‌متریک `Vazirmatn Fallback` در `assets/css/child.css`: `local('Tahoma')`, `local('Noto Naskh Arabic')`, `size-adjust: 108%`, `ascent-override: 102.54%`, `descent-override: 53.71%`, `line-gap-override: 0%` (نگه‌داری‌شده با تست `tests/test-font-loading.php`).

## ۳. استایل‌دهی و سیستم طراحی (CSS)
- **طراحی ۱۴۰۵ (`inc/design-2026.php`):**
  - استایل‌شیت کامپایل‌شدهٔ استاتیک: `assets/css/sa-2026.css` (خروجی Tailwind v3.4.17 — بدون بارگذاری Tailwind CDN یا بیلد زمان اجرا در وردپرس).
  - پالت‌های متغیر CSS: `sa-palette--teal` (پیش‌فرض)، `sa-palette--home` (صفحهٔ اصلی)، `sa-palette--treasure` (دیدنی‌ها).
  - کلید بازگشت اضطراری سه‌لایه به قالب‌های `legacy/`: ثابت `SA_2026_DESIGN`, گزینهٔ `sa_2026_design` و فیلتر `sa_2026_design_enabled`.
- **استایل مکمل قالب فرزند:** `assets/css/child.css` با CSS Grid/Flex بومی، CSS Custom Properties و Logical Properties (`inset-inline-*`, `padding-inline-*`, `border-inline-*`). شامل استایل کارت‌های `.sa-county-boxes`, آکاردئون `.sa-faq-accordions`, باکس‌های منابع `.sa-source-boxes` و قوانین `.sa-2026-prose`.
- **بارگذاری شرطی CSS:**
  - `assets/css/landing.css` فقط برای `template-home.php` یا حالت طراحی قدیمی صفحهٔ اول.
  - `assets/css/gallery.css` فقط در `is_singular( array( 'province', 'city' ) )`.
  - cache-busting با `filemtime()` در `sa_child_asset_version()`.

## ۴. جاوااسکریپت و تعاملات
- **پرهیز از jQuery:** تعاملات بخش ۱۴۰۵ در `assets/js/sa-2026.js` با **Vanilla JS** خالص (بدون وابستگی و بدون jQuery) و با گزینهٔ `['in_footer' => true, 'strategy' => 'defer']` بارگذاری می‌شوند.
- **قابلیت‌های `sa-2026.js`:** منوی موبایل، جستجوی هدر با کلید `/`, مودال ثبت تجربه، جستجوی فوری صفحهٔ اول و شهرستان‌ها با نرمال‌سازی ی/ک/نیم‌فاصله، تب‌های دسته‌بندی جاذبه‌ها، scroll-spy و بوکمارک سفر.
- **اسکریپت‌های شرطی دیگر:** `assets/js/gallery.js` (فقط استان/شهرستان)، `assets/js/iran-provinces-map.js` / `iran-counties-map.js`، `assets/js/dashboard.js`، `assets/js/city-contrib.js`.

## ۵. سئوی فنی، اسکیما و بهینه‌سازی عملکرد (CWV)
- **سئوی داخلی (`inc/seo.php`, `inc/breadcrumbs.php`):** عنوان و توضیح یکتای متا، `canonical`, `robots` (`inc/agent-ops.php` به عنوان دروازهٔ ایندکس), کارت‌های OG/Twitter (`inc/og-card.php` ۱۲۰۰×۶۳۰).
- **دادهٔ ساخت‌یافته (`inc/schema.php`):** خروجی یکپارچهٔ `@graph` شامل `Organization` (با لوگوی رسمی داخل قالب در `sa_brand_logo()`), `WebSite` + `SearchAction`, `BreadcrumbList`, `Place`/`AdministrativeArea`/`TouristDestination`/`TouristAttraction`, `GeoCoordinates`, `Article`, و `FAQPage` هماهنگ با `sa_faq`.
- **بهینه‌سازی عملکرد (`inc/performance.php`):**
  - حذف اسکریپت/استایل ایموجی، حذف `dashicons` برای کاربران مهمان، حذف `wp-block-library`/`global-styles` روی صفحات موجودیت‌ها و آرشیوها، حذف `wp-embed` خارج از `post`/`page`، حذف `heartbeat` در فرانت‌اند مهمان، حذف `dns-prefetch` اضافی `s.w.org`.
  - تصاویر: `decoding="async"`, کیفیت JPEG `82`، تبدیل خودکار اندازهٔ تصاویر به `WebP` در صورت پشتیبانی GD/Imagick، سقف `big_image_size_threshold = 2000`.
  - کش موقت: `sa_entity_counts` (۱ ساعت)، واژه‌نامهٔ `sa_autolink` (۱۲ ساعت).

## ۶. اتوماسیون، کیفیت و زیرساخت استقرار (CI/CD & Agent)
- **گیت‌ها و تست‌های آفلاین (`.github/workflows/build-gates.yml`):** `pytest`, `py_compile`, `fa_lint.py`, `test_county_template.py`, `test_wp_agent_jobs.py` و ده‌ها تست مستقل PHP در `wp-content/themes/sarzaminaryan-child/tests/` (از جمله `test-design-2026.php`, `test-font-loading.php`, `test-ai-visibility.php`, `test-responsive-tables.php`, `test-sources-last.php`, `test-sources-sanitization.php`, `test-internal-links.php`, `test-toc.php`).
- **محافظت شاخه و فایل‌ها (`.github/workflows/protect.yml`):** مسدودسازی تغییر در `wp-config.php`, `.htaccess` و کل پوشهٔ قالب والد `wp-content/themes/sarzaminaryan/`.
- **انتشار قالب (`.github/workflows/release-child-theme.yml`):** اعتبارسنجی انطباق `Version` در `style.css` با `SA_CHILD_VERSION`, تست‌های PHP و تولید فایل ZIP + `.sha256` روی GitHub Releases در شاخهٔ `main`.
- **انتشار روی سایت زنده (`.github/workflows/wp-agent.yml` + `tools/wp-agent/` + `inc/agent-bridge.php` + `inc/agent-ops.php`):** اجرای دستی روی `main` با جاب JSON مشخص (`ping`, `theme-update` با `rollback_to`, `update` با `backup_id` قبل از نوشتن، `cache-purge`, `probe`).
- **حافظهٔ داده و محتوا (Supabase):** پروژهٔ `yesseutgyatmxsaswghb` (PostgreSQL 17 + `pgvector` و جدول‌های `articles`, `sources`, `embeddings`).
