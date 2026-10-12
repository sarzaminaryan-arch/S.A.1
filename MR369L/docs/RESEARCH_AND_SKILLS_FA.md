# پژوهش و مهارت‌ها — قالب فروشگاهی فارسی/RTL

| | |
|---|---|
| سند | `docs/RESEARCH_AND_SKILLS_FA.md` |
| نسخه | 0.1 |
| تاریخ بررسی منابع | 2026-10-12 (همهٔ تاریخ‌های «بررسی‌شده» در همین سند به همین روز اشاره دارند مگر ذکر شود) |
| قاعده | «بررسی‌شده» = صفحه/سورس در همین تاریخ واقعاً باز و خوانده شد. هر چیزی که از دانش پیشین یا منبع دست‌دوم است، صریحاً علامت خورده است. |

---

## ۱) منابع بررسی‌شده

### ۱-۱) وردپرس (منابع اصلی)

| # | عنوان | نشانی | ناشر | آخرین به‌روزرسانی منبع | چه آموختیم | اثر بر پروژه | هشدار/قید |
|---|---|---|---|---|---|---|---|
| W1 | Theme Handbook (فهرست) | `developer.wordpress.org/themes/` | WordPress.org | 2026-05-19 | دو مسیر رسمی «قالب بلوکی» و «قالب کلاسیک»؛ توصیه به خواندن هر دو | انتخاب مسیر بلوکی با آگاهی از تفاوت‌ها | صفحهٔ فهرست است؛ جزئیات در زیرصفحه‌ها |
| W2 | Releasing Your Theme | `developer.wordpress.org/themes/releasing-your-theme/` | WordPress.org | 2026-05-19 | قواعد سخت: عدم مجاز بودن افزونه/فرم/CPT/شورت‌کد/بلوک سفارشی در قالب؛ ۳ ایراد مجزا = رد شدن | مرز قالب/افزونه در `ARCHITECTURE_FA.md` §۲ | مربوط به انتشار در مخزن وردپرس است؛ اما به‌عنوان «اصل قابلیت حمل داده» رعایت می‌شود |
| W3 | Accessibility (Theme Handbook) | `developer.wordpress.org/themes/advanced-topics/accessibility/` (هدایت به `…/classic-themes/functionality/accessibility/`) | WordPress.org | — | کنتراست ۴٫۵:۱ برای متن؛ ۳:۱ برای متنِ لینک بدون زیرخط نسبت به متن اطراف؛ بزرگ‌نمایی تا ۲۰۰٪ بدون اسکرول دوبعدی؛ الزامات «accessibility-ready» جدا از WCAG AA کامل است | چک‌لیست A11Y در فهرست ویژگی‌ها و آزمون‌ها | صفحه به بخش قالب‌های کلاسیک منتقل شده؛ اصولش عمومی است |
| W4 | Global Settings and Styles (theme.json) | `developer.wordpress.org/themes/global-settings-and-styles/` | WordPress.org | 2023-12-14 | ساختار فصل theme.json: Settings, Styles, Custom Templates, Template Parts, Patterns, Style Variations | مبنای سیستم طراحی | صفحهٔ فهرست؛ جزئیات هر setting در زیرصفحه‌ها باید هنگام پیاده‌سازی خوانده شود |
| W5 | سورس هستهٔ WordPress 7.1.3 (محلی) | `wp-includes/class-wp-theme-json.php`, `wp-includes/theme.json`, `wp-includes/script-loader.php`, `wp-content/themes/twentytwentyfive/functions.php` | پروژهٔ وردپرس (دانلود از GitHub) | نسخهٔ 7.1.3 (2026) | `LATEST_SCHEMA = 3`؛ قالب پیش‌فرض `theme.json` با `"version": 3`؛ الگوی رسمی enqueue استایل در قالب بلوکی (`wp_enqueue_style` + `wp_style_add_data('path')`)؛ وجود `wp_enqueue_block_style()` در `script-loader.php` | تصمیم فنی دقیق: `theme.json` نسخهٔ ۳ + الگوی استاندارد بارگذاری استایل | سورس هسته «حقیقتِ نسخه‌محور» است؛ روی نسخه‌های دیگر باید بازبینی شود |
| W6 | PHP support clarification, spring 2026 | `make.wordpress.org/core/2026/05/22/php-support-clarification-2026/` | Make WordPress Core | 2026-05-22 | حذف برچسب beta؛ WP 6.9/7.0 رسماً PHP 8.5 را پشتیبانی می‌کنند؛ 6.8+ → 8.4؛ 6.4+ → 8.3؛ **حداقل پشتیبانی‌شده PHP = 7.4 از WP 7.0**؛ حداقل پیشنهادی 8.3 | هدف سازگاری PHP 7.4–8.5 و ماتریس lint | متن اطلاعیه از نتایج جست‌وجو خوانده شد، خود صفحه مستقیماً باز نشد (منبع رسمی make.wordpress.org) |

### ۱-۲) ووکامرس (منابع اصلی)

| # | عنوان | نشانی | ناشر | آخرین به‌روزرسانی منبع | چه آموختیم | اثر بر پروژه | هشدار/قید |
|---|---|---|---|---|---|---|---|
| C1 | Template structure & Overriding templates via a theme | `developer.woocommerce.com/docs/theming/theme-development/template-structure` | WooCommerce | 2026-06-08 | قالب‌ها در `plugins/woocommerce/templates/`؛ روش ترجیحی = hooks نه override؛ روش override = کپی به `theme/woocommerce/` بدون پوشهٔ `templates`؛ اگر قالب فایل `woocommerce.php` داشته باشد نمی‌تواند `woocommerce/archive-product.php` را override کند؛ هشدار `@version` در System Status | سیاست «کمینه‌سازی override» و ترجیح hooks/filters | مستند به‌روز و فعال |
| C2 | Theming for Woo blocks | `developer.woocommerce.com/docs/theming/block-theme-development/theming-woo-blocks` | WooCommerce | 2026-06-09 | فهرست قالب‌های قابل override بلوکی: `single-product.html`, `archive-product.html`, `taxonomy-product_cat/tag/attribute.html`, `product-search-results.html`, `page-coming-soon.html`, `page-cart.html`, `page-checkout.html`, `order-confirmation.html`؛ فال‌بک دسته/برچسب/ویژگی به `archive-product.html`؛ **الزام**: `page-cart/checkout.html` باید محتوای صفحهٔ تخصیص‌یافته را با `woocommerce/page-content-wrapper` + `core/post-content` رندر کند (قرار دادن مستقیم بلوک سبد/تسویه در قالب ممنوع)؛ قالب‌بخش‌های `mini-cart.html` و `checkout-header.html`؛ استایل با `styles.blocks["woocommerce/*"]` در `theme.json` | طراحی قالب‌های بلوکی وو و ساختار سبد/تسویه | قاعدهٔ سبد/تسویه در پیاده‌سازی باید حتماً رعایت شود |
| C3 | Cart and checkout blocks theming | `developer.woocommerce.com/docs/theming/block-theme-development/cart-and-checkout/` | WooCommerce | 2026-04-07 | **ممنوعیت صریح** CSS بر پایهٔ کلاس‌های بلوک و سلکتورهای وابسته به ساختار؛ «HTML درونی بلوک‌ها خصوصی است»؛ استایل از طریق global styles؛ نکات ظریف دکمهٔ موبایل و نشان تعداد | قاعدهٔ CSS پروژه: هیچ سلکتور روی کلاس‌های داخلی بلوک‌های وو | این محدودیت، دلیل محکم برای معماری بلوکی/theme.json است |
| C4 | Template File Guidelines for Devs and Theme Authors (wiki) | `github.com/woocommerce/woocommerce/wiki/Template-File-Guidelines-for-Devs-and-Theme-Authors` | WooCommerce (GitHub) | 2017-12-13 | هر template یک `@version` دارد؛ هنگام override نباید هیچ hook ووکامرس حذف شود؛ hooks بر override ترجیح دارند؛ الگوی `woocommerce_locate_template` برای افزونه‌ها | سیاست نگهداری: اگر override لازم شد، hook‌ها حفظ و `@version` ثبت می‌شود | منبع قدیمی (۲۰۱۷) اما به‌عنوان «قاعدهٔ پایدار» همچنان در مستندات معاصر نقل می‌شود |
| C5 | `readme.txt` ووکامرس در tag 11.2.1 | `api.github.com/repos/woocommerce/woocommerce/contents/plugins/woocommerce/readme.txt?ref=11.2.1` | WooCommerce (GitHub API) | tag 11.2.1 | «Requires at least: 7.0»، «Requires PHP: 7.4»، «Tested up to: 7.1»، مجوز GPLv3 | تعیین هدف سازگاری: WP ≥7.0 برای وو 11.2.x | `Stable tag: 11.2.0` در readme با تگ انتشار 11.2.1 اختلاف جزئی دارد (طبیعی) |
| C6 | آخرین انتشار ووکامرس | `api.github.com/repos/woocommerce/woocommerce/releases/latest` | GitHub API | 11.2.1 (2026-10-09) | آخرین انتشار پایدار 11.2.1 | نسخهٔ مرجع آزمون سازگاری | — |

### ۱-۳) فارسی، RTL، فونت

| # | عنوان | نشانی | ناشر | تاریخ بررسی | چه آموختیم | اثر بر پروژه | هشدار |
|---|---|---|---|---|---|---|---|
| P1 | Vazirmatn | `github.com/rastikerdar/vazirmatn` | صابر راستی‌کردار | 2026-10-12 (API) | مجوز **OFL-1.1**؛ ۹ وزن woff2 + فونت متغیر `Vazirmatn[wght].woff2`؛ آخرین push: 2023-06-14 | فونت پیش‌فرض پلن؛ محلی و مجوزدار | پروژه از ۲۰۲۳ به‌روزرسانی نشده — برای فونت ریسک کم است، اما کاراکترهای جدید یونیکد پوشش داده نمی‌شوند |
| P2 | `skill-SA-agent/references/03-persian-rtl-jalali.md` (داخلی مخزن) | مخزن S.A.1 | مالک پروژه | پیش از 2026-10-12 | قواعد استخراج‌شده از wp-shamsi و تجربهٔ پروژهٔ سرزمین آریان: استفاده از `wp_date` برای تاریخ شمسی، فهرست formatهای ماشینی که نباید تبدیل شوند، تبدیل ارقام فارسی، پیشنهاد Vazirmatn محلی، ممنوعیت CDN | قواعد نمایش فارسی/شمسی/ارقام | منبع داخلی است؛ برای این قالب به‌عنوان «سیاست پروژه» پذیرفته می‌شود، نه مرجع مستقل بیرونی |
| P3 | `skill-SA-agent/references/01-wordpress-theme-standards.md` (داخلی) | مخزن S.A.1 | مالک پروژه | پیش از 2026-10-12 | چک‌لیست اجباری قالب: `title-tag`, `post-thumbnails`, `html5`, `custom-logo`, `responsive-embeds`, `.screen-reader-text`, `:focus-visible`, لینک پرش، یک `h1`؛ قواعد CPT/meta/امنیت/کارایی | ورودی چک‌لیست کیفیت | بخشی از آن مربوط به قالب کلاسیک/CPT است و در معماری جدید فقط بخش نمایشی آن لازم است |

### ۱-۴) دسترس‌پذیری، کارایی، کاوش‌های مفهومی

| # | عنوان | نشانی | ناشر | تاریخ بررسی | چه آموختیم | اثر بر پروژه | هشدار |
|---|---|---|---|---|---|---|---|
| A1 | Core Web Vitals — آستانه‌ها | نتایج جست‌وجو (منشأ اعلامی: `web.dev`) | Google / منابع دست‌دوم | 2026-10-12 | «خوب»: LCP ≤ 2.5s، INP ≤ 200ms، CLS ≤ 0.1 در صدک ۷۵؛ «نیازمند بهبود» تا 4.0s/500ms/0.25؛ INP جایگزین FID از ۲۰۲۴-۰۳-۱۲ | اهداف کارایی فاز ۳/۴ | **منبع دست‌دوم**: وب‌سایت web.dev مستقیماً باز نشد؛ اعداد در چند منبع مستقل یکسان‌اند ولی برای استناد نهایی باید از web.dev تأیید شود |
| A2 | قالب پیش‌فرض TT5 و ساختار بخش‌ها (سورس محلی) | `wp-content/themes/twentytwentyfive/` | پروژهٔ وردپرس | 2026-10-12 | ساختار واقعی یک قالب بلوکی مدرن (templates/parts/patterns/styles) | الگوی ساختار پوشه (مفهومی، بدون کپی) | «الگوگیری ساختاری» است، نه کپی طراحی |

### ۱-۵) منابعی که امتحان شد ولی معتبر نبود/در دسترس نبود

| منبع | وضعیت | اقدام |
|---|---|---|
| `developer.wordpress.org/themes/getting-started/theme-requirements/` | ۴۰۴ (صفحه وجود ندارد) | مسیر صحیح: `…/themes/releasing-your-theme/` و زیرصفحه‌های آن |
| `developer.wordpress.org/themes/release/requirements/` | ۴۰۴ | همان بالا |
| `wordpress.org`, `downloads.wordpress.org` | از داخل سندباکس مسدود (خطای SSL) | دریافت WordPress از نسخهٔ GitHub انجام شد (`codeload.github.com`) |
| GitHub Release Assets (مثل `woocommerce.zip` رسمی) | مسدود (خطای SSL روی `release-assets.githubusercontent.com`) | یکپارچگی وو در سندباکس قابل نصب نیست؛ آزمون واقعی وو به staging مالک یا CI منتقل می‌شود |

### ۱-۶) ابزارهای آزمون که واقعاً به‌کار رفتند (منبع: اجرای مستقیم، ۲۰۲۶-۱۰-۱۲)

| # | ابزار/منبع | نشانی | چه آموختیم | اثر بر پروژه |
|---|---|---|---|---|
| TL-1 | WordPress Playground CLI (`@wp-playground/cli` 3.1.58) | `npmjs.com/package/@wp-playground/cli` (نسخه از registry خوانده شد) | اجرای کامل وردپرس روی PHP-WASM + SQLite؛ `--auto-mount` پوشهٔ وردپرس؛ امکان فعال‌سازی با SQLite | محیط آزمون واقعی L4 بدون نصب PHP/MySQL |
| TL-2 | `@php-wasm/node` 3.1.58 | `npmjs.com/package/@php-wasm/node` | اجرای PHP 7.4 تا 8.5 در Node؛ نیاز به `processId`؛ نبود `popen`/`proc_open` | ابزار lint چندنسخه‌ای بدون PHP سیستمی |
| TL-3 | سورس هستهٔ WordPress 7.1.3 | `codeload.github.com/WordPress/WordPress` | `WP_Theme_JSON::LATEST_SCHEMA = 3`؛ `_add_default_theme_supports()` فهرست پشتیبانی‌های خودکار قالب بلوکی؛ `block-template.php` رندر `title` بدون نیاز به `title-tag`؛ ترتیب `load_default_textdomain()` پیش از `new WP_Locale()` | تصمیم‌های فنی مستند و مبتنی بر سورس نسخهٔ واقعی |
| TL-4 | WPCS 3.4.1 / PHPCS 4.0.4 | `api.github.com/repos/WordPress/WordPress-Coding-Standards/releases/latest` | نسخه‌های جاری برای CI شناسایی شد (نصب محلی ممکن نیست) | برنامهٔ L7 در GitHub Actions |

> **اصلاح مهم (۲۰۲۶-۱۰-۱۲):** نخستین اجرای lint در این دور شاهد معتبری تولید نکرد (glob هیچ فایلی نمی‌خواند). علت و اصلاح در `ISSUES_AND_SOLUTIONS_FA.md` بند I-008 ثبت شد و شاهد معتبر پس از بازنویسی ابزار در T-014 آمد.

---

## ۲) یادداشت‌های مهارتی (قواعد عملی استخراج‌شده)

1. **حقیقت نسخه‌محور:** همهٔ تصمیم‌های فنی به نسخهٔ WP 7.1.3 و وو 11.2.x مقید می‌شوند؛ هر قاعده باید با «نسخهٔ مرجع» ثبت شود.
2. **`theme.json` تنها مرجع طراحی:** رنگ/تایپ/فاصله/سایه فقط آنجا؛ CSS شخصی صرفاً برای چیزهایی که theme.json پوشش نمی‌دهد.
3. **هیچ CSS روی ساختار داخلی بلوک‌های وو**؛ استایل با `styles.blocks` و کلاس‌های عمومی خودِ وو.
4. **سبد/تسویه را از محتوا جدا نکن:** `page-cart.html`/`page-checkout.html` باید محتوای صفحه را رندر کنند (`woocommerce/page-content-wrapper` + `core/post-content`).
5. **بدون CPT/بلوک سفارشی/شورت‌کد/فرم در قالب.**
6. **override حداقلی:** ابتدا hook/filter؛ override فقط برای تغییر ساختار لازم و با حفظ همهٔ hookها و ثبت `@version`.
7. **RTL با ویژگی‌های منطقی**؛ `dir` از `language_attributes()`؛ آیکون‌های جهت‌دار بازبینی شوند.
8. **نمایش ≠ داده:** اعداد فارسی و تاریخ شمسی فقط در لایهٔ نمایش، با فهرست «formatهای ماشینی ممنوع».
9. **امنیت پیش‌فرض:** escape/sanitize/nonce/capability؛ هیچ درخواست بیرونی و هیچ راز در مخزن.
10. **دسترس‌پذیری از ابتدا:** کنتراست، فوکوس، کیبورد، بزرگ‌نمایی، reduced-motion، ساختار سرویس‌دهنده.
11. **اندازه‌گیری قبل از ادعا:** هر «بهبود کارایی» باید عدد قبل/بعد داشته باشد.
12. **آزمون بدون مرورگر محدود است:** ادعای امتیاز Lighthouse/axe مرورگری ممنوع؛ فقط آزمون‌های اجراشده گزارش شود.

---

## ۳) بازبینی آمادگی (Readiness Review)

### مهارت‌های مورد نیاز پروژه
| مهارت | سطح لازم | وضعیت ما | مبنا |
|---|---|---|---|
| ساختار قالب وردپرس + سلسله‌مراتب قالب | زیاد | ✅ تأمین‌شده | منابع W1–W5 + دانش پیشین |
| `theme.json` نسخهٔ ۳ و قالب بلوکی (FSE) | زیاد | 🟡 تأمین نسبتاً | W4/W5؛ جزئیات هر setting هنگام پیاده‌سازی خوانده می‌شود |
| قالب‌های بلوکی ووکامرس | زیاد | 🟡 نسبی | C1–C3؛ **بدون آزمون اجرایی با وو** (ریسک R-01) |
| PHP و استانداردهای کدنویسی | زیاد | ✅ | تجربهٔ مخزن + lint اجراشده |
| امنیت وردپرس (nonce/escape/sanitize) | زیاد | ✅ | منبع داخلی ۰۲ + قواعد استاندارد |
| CSS راست‌چین/منطقی | زیاد | ✅ | P2/P3 + استاندارد MDN (دانش پیشین) |
| دسترس‌پذیری (WCAG/A11Y وردپرس) | متوسط‌به‌زیاد | 🟡 | W3؛ آزمون مرورگری در دسترس نیست |
| کارایی و CWV | متوسط | 🟡 | A1 (دست‌دوم)؛ اندازه‌گیری سرور‌محور ممکن است |
| ابزارسازی CI (PHPCS/WPCS/Actions) | متوسط | 🟡 | WPCS 3.4.1 و PHPCS 4.0.4 شناسایی شد؛ اجرای محلی ممکن نیست → CI |

### شواهد گردآوری‌شده تا این لحظه
- محیط واقعی وردپرس در سندباکس بالا آمد: **WordPress 7.1.3 / PHP 8.3 / SQLite** روی `127.0.0.1:9400`، پاسخ `HTTP 200`، حجم ۶۹٫۷۲۴ بایت، زمان ~۱٫۰۶s (شاهد: خروجی curl ثبت‌شده در `MR369L/docs/TEST_AND_QUALITY_FA.md`).
- امکان اجرای PHP 7.4 تا 8.5 در سندباکس فراهم شد (`@php-wasm/node` 3.1.58) → مبنای lint چندنسخه‌ای.
- ظرفیت دریافت از GitHub (`codeload`, `api`) تأیید شد؛ WordPress 7.1.3 دانلود و باز شد.
- قواعد کلیدی معماری از منابع اصلی استخراج و ثبت شد (بالا).

### شکاف‌های باقی‌مانده (پیش از فاز ۳ قابل تحمل، پیش از انتشار باید بسته شوند)
| شناسه | شکاف | اثر | برنامهٔ بستن |
|---|---|---|---|
| G-01 | نبود اجرای واقعی WooCommerce در سندباکس | ریسک ناسازگاری قالب‌های بلوکی وو | آزمون در GitHub Actions با نصب ووکامرس + آزمون پذیرش روی staging مالک |
| G-02 | نبود مرورگر | نبود آزمون بصری/دسترس‌پذیری مرورگری | چک‌لیست دستی مالک + ابزارهای ساختاری (axe در jsdom با محدودیت) |
| G-03 | نبود MySQL/MariaDB | تفاوت رفتاری با SQLite | قالب بدون کوئری سنگین؛ آزمون نهایی روی staging |
| G-04 | نبود اجرای PHPCS/WPCS محلی | نبود بازبینی استاندارد کدنویسی در سندباکس | اجرا در CI؛ **جایگزین موقت:** ابزار ایستای پروژه (`theme-audit.mjs`) با ۱۳ قاعده که اجرا و سبز شد |
| G-05 | تأیید نشدن آستانه‌های CWV از منبع اصلی | استناد نهایی ضعیف | باز کردن مستقیم `web.dev/articles/vitals` در فرصت بعدی |
| G-06 | نبود بازبینی سورس قالب‌های تجاری برای الگوگیری مفهومی | پوشش ویژگی ممکن است ناقص بماند | بررسی README/ویژگی‌های Storefront/Astra/Blocksy در فاز ۲ (بند TF-01) |

### حکم آمادگی
**آمادهٔ شروع فاز ۳ با سه شرط:** (۱) تأیید سه تصمیم کلیدی توسط مالک، (۲) ثبت صریح محدودیت‌های آزمون در هر گزارش، (۳) انجام آزمون واقعی ووکامرس در CI یا staging پیش از اعلام «آمادهٔ تولید».
این سند ادعای تسلط کامل نمی‌کند؛ سطوح بالا **خودارزیابی مبتنی بر شواهد** است.
