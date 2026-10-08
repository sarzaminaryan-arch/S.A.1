# چک‌لیست ممیزی فنی و ساختاری قالب

**آخرین اجرای ثبت‌شده:** 2026-10-08
**دامنه:** قالب والد و فرزند، کد، امنیت، مدل داده، روابط، URL/SEO، دسترس‌پذیری و آمادگی انتشار.
**خارج از دامنه:** بازبینی یا بازنویسی متن مقاله‌ها، صحت ادعاهای مقاله، تصویرهای مقاله و تصمیم‌های تحریری.

> در اجرای ثبت‌شده، بدنه و عنوان نوشته‌ها خوانده نشد. فقط `post_type`، وضعیت، slug، شناسه‌های رابطه، فرادادهٔ ساختاری و taxonomyهای خروجی WXR بررسی شد. هیچ رابطه یا محتوای سایت تغییر نکرد.

## راهنمای وضعیت

- `[x]` بررسی شد و نتیجه ثبت شده است.
- `[ ]` هنوز بررسی نشده است.
- `مسدود` اجرای مورد به ابزار، محیط زنده، منبع معتبر یا تأیید مالک نیاز دارد.

## ۱. دریافت و تطبیق بسته‌ها

- [x] نسخهٔ قالب والد و فرزند را از `style.css` و `functions.php` ثبت کن.
- [x] محتوای ZIP را با شاخهٔ فعال مخزن مقایسه کن؛ فایل مقاله‌ها و assetهای محتوا را جدا از کد نگه دار.
- [x] وجود مسیرهای include، فایل‌های اجباری قالب و وابستگی قالب فرزند به والد را کنترل کن.
- [ ] نسخهٔ ZIP، شمارهٔ مدل داده و منبع schema باید یکسان و قابل‌ردیابی باشند.
- [ ] پیش از ادغام، تغییرات را فایل‌به‌فایل مرور کن؛ ZIP را کورکورانه جایگزین درخت فعال نکن.

**نتیجهٔ این اجرا:** ZIP والد با قالب والد مخزن یکسان است. ZIP فرزند نسخهٔ `2.11.44` و مخزن نسخهٔ `2.11.26` دارد. ZIP کامل ادغام نشد: نسخهٔ مدل در بسته `1.2` است، در حالی که مدل مرجع مخزن `1.1` است؛ بسته همچنین ابزارهای پاک‌سازی، حذف قطعی، ویرایش متن و انتشار بیرونی دارد که هنوز بررسی و تأیید اجرایی نشده‌اند.

## ۲. کیفیت کد و آزمون

- [ ] PHP lint برای همهٔ فایل‌های تغییرکرده، روی PHP حداقلِ اعلام‌شده و نسخهٔ هدف.
- [x] بررسی نحوی JavaScript با `node --check` برای فایل‌های قالب فعلی و ZIP فرزند.
- [ ] آزمون واحد/قراردادی برای توابع جدید؛ فایل آزمون باید همراه تغییر یا در مخزن قابل‌دسترسی باشد.
- [ ] اجرای قالب روی WordPress پشتیبانی‌شده، با `WP_DEBUG` روشن در staging؛ ثبت خطاهای fatal، warning و notice.
- [ ] آزمون مسیرهای REST، فرم‌ها، ذخیرهٔ فراداده، rewrite، sitemap، cron و کش در WordPress واقعی.
- [ ] `git diff --check` و مرور محدودهٔ تغییرات پیش از تحویل.

**نتیجهٔ این اجرا:** `data-model/schema/build_json.py` با PyYAML اجرا شد و JSON نسخهٔ `1.1` را بدون اختلاف بازساخت. اجرای `build_child_config.py` فایل `inc/entities-config.php` را تغییر داد؛ اختلاف بررسی و فایل بازگردانده شد. PHP، WP-CLI و staging در دسترس نیستند. اجراکننده‌های آزمونی که CHANGELOG بستهٔ فرزند نام می‌برد داخل ZIP یا مخزن حاضر نیستند؛ بنابراین ادعای آزمون زمان اجرا مستقلانه بازتولید نشد. بررسی نحوی JS و `git diff --check` موفق بود.

## ۳. استاندارد قالب، RTL و دسترس‌پذیری

- [ ] سربرگ `style.css`، `Template`، نسخه، مجوز، Text Domain، screenshot و readme را بررسی کن.
- [ ] اتصال `wp_head()`, `wp_footer()`, `wp_body_open()`, `language_attributes()`, `body_class()` و `content_width` را در سلسله‌مراتب والد/فرزند بررسی کن.
- [ ] ترتیب و نسخهٔ enqueueها، وابستگی assetها، بارگذاری محلی فونت و نبود CDN را کنترل کن.
- [ ] خروجی‌های پویا را بر اساس context escape کن؛ ورودی‌ها را sanitize و validate کن.
- [ ] skip link، focus قابل‌دیدن، برچسب کنترل‌ها، وضعیت ARIA، ترتیب صفحه‌کلید، کنتراست و هدف‌های لمسی را در مرورگر بیازمای.
- [ ] جهت RTL، ویژگی‌های CSS منطقی و نبودِ وارونگی دوبارهٔ `row-reverse` را در اندازه‌های موبایل و دسکتاپ ببین.
- [ ] تصویر LCP، lazy-load، نسبت ابعاد، font-display، خطای 404 asset و تغییر چیدمان را اندازه بگیر.

**نتیجهٔ این اجرا:** سربرگ‌ها، screenshot/readme و hookهای پایه در فایل‌های قالب پیدا شدند؛ قالب والد `content_width` را تعریف می‌کند و فرزند به آن تکیه دارد. بررسی DOM، صفحه‌کلید، نمایش موبایل، کنتراست و Core Web Vitals به اجرای مرورگر روی سایت نیاز دارد و انجام نشده است.

## ۴. مدل داده و یکپارچگی روابط

- [ ] CPT، taxonomy، meta key، نامک، URL و publish gate را با `MASTER_DATA_MODEL.md` و YAML مقایسه کن.
- [ ] خروجی DB/WXR را بدون خواندن متن مقاله از نظر وضعیت، slug، term assignment و شناسهٔ والد بررسی کن.
- [ ] هر رابطهٔ `City → Province` و `Attraction → City → Province` را با وضعیت انتشار مقصد تطبیق بده.
- [ ] تفاوت slugهای registry، aliasها و redirectها را با مرجع معتبر یا تصمیم مکتوب مالک تعیین تکلیف کن؛ داده را حدس‌زده تغییر نده.
- [ ] فهرست termهای واقعی را با مدل مرجع مقایسه کن؛ termهای اضافه را بدون تأیید حذف یا rename نکن.
- [ ] تغییر مدل را ابتدا در سند مرجع ثبت کن، سپس YAML/JSON و config تولیدشده را هماهنگ و آزمون کن.

**نتیجهٔ WXR مورخ 2026-10-08:**

- 487 شهرستان منتشر، 4 شهرستان پیش‌نویس، 31 استان منتشر و 26 دیدنی منتشر وجود دارد؛ در 491 نوشتهٔ `city` slug تکراری پیدا نشد.
- هر 491 شهرستان دقیقاً یک term از `province_tax` دارند و آن term با `sa_province_id` هماهنگ است. هر 487 شهرستان منتشر به استان منتشر وصل است.
- همهٔ 26 دیدنی یک رابطهٔ شهر و استان سازگار دارند؛ 22 رابطه به شهر منتشر می‌رسد و 4 رابطه به شهر پیش‌نویس: `ali-sadr-cave-hamedan-guide → kabudarahang-city`، `golestan-national-park-guide → galikesh`، `naqsh-e-jahan-square-isfahan → isfahan` و `shahdad-kaluts-lut-desert → shahdad`.
- registry قالب فرزند 483 slug دارد. ZIP در CHANGELOG برای `/city/ijrud/ → /city/ejrud/` تصمیم مالک را ثبت کرده و `bushehr-city` را جدا از `bushehr-county` توصیف می‌کند. وضعیت صفحهٔ منتشرشدهٔ `borazjan` و `khorramdarreh` همچنان نیازمند تصمیم/منبع است؛ تغییر داده انجام نشده است.
- WXR دارای 19 term برای `attraction_type` در برابر 12 term مدل است؛ سه term افزوده (`family`, `recreational`, `waterfall`) به نوشته وصل‌اند و چهار term (`bazaar`, `museum`, `natural`, `sea`) در WXR استفاده نشده‌اند. `travel_budget` پنج term در برابر سه و `travel_duration` هشت term در برابر چهار term مدل دارد؛ termهای اضافی این دو taxonomy در WXR استفاده نشده‌اند. `province_tax` با 31 و `travel_season` با 4 term با مدل تطبیق دارد.
- `inc/entities-config.php` فعلی با خروجی generator نسخهٔ 1.1 round-trip نمی‌شود: config فعلی چند فیلد اختصاصی دیدنی خارج از YAML دارد و حداقل منابع دیدنی را `0` می‌گذارد، در حالی که YAML مرجع `5` می‌گوید. generator این تفاوت‌ها را بازنویسی می‌کند؛ پیش از ساخت مجدد، مدل و رفتار باید آگاهانه هم‌راستا شوند.

## ۵. امنیت و تغییرات عملیاتی

- [ ] هر مسیر نوشتن، AJAX و REST را از نظر capability، nonce، autosave/revision، sanitize و escape بررسی کن.
- [ ] SQL را با `$wpdb->prepare()` و درخواست بیرونی را با allowlist، timeout و پاسخ‌سنجی ایمن کن.
- [ ] token/secret را در UI، log، HTML، URL عمومی یا فایل مخزن افشا نکن.
- [ ] پیش از هر import، حذف، search-replace یا ویرایش انبوه، backup، dry-run، گزارش before/after و تأیید روشن داشته باش.
- [ ] ابزارهای حذف، انتقال رابطه یا بازنویسی متن را جداگانه audit کن؛ حضور آن‌ها در ZIP مجوز اجرای زنده نیست.
- [ ] ارسال خودکار به شبکهٔ اجتماعی/پیام‌رسان را فقط پس از بازبینی حریم خصوصی، رضایت، زمان‌بندی، توقف اضطراری و محیط آزمایشی فعال کن.

**یافتهٔ مسدودکننده در ZIP فرزند:** تابع apply قابل‌بازگشت در `inc/site-cleanup.php` از candidateهایی استفاده می‌کند که query آن‌ها نوشته‌های منتشرشده را هم می‌گیرد؛ مسیر trash خودش guard انتشار/registry ندارد. همان فایل `wp_delete_post(..., true)` برای حذف برگشت‌ناپذیر و جایگزینی متن ذخیره‌شده دارد. این فایل در مخزن ادغام و روی سایت اجرا نشده است؛ پیش از هر استفاده باید مسیرها اصلاح و آزمون شوند.

## ۶. SEO فنی و بررسی سایت زنده

- [ ] عنوان، description، canonical، OG، robots، breadcrumb، sitemap و JSON-LD را روی هر نوع صفحه بررسی کن.
- [ ] URLهای canonical و redirect را از نظر مقصد منتشرشده، chain، loop و 404 آزمایش کن.
- [ ] structured data را با ابزار معتبر و خروجی HTML واقعی بررسی کن؛ property یا rating ساختگی نساز.
- [ ] crawl و بررسی Search Console، خطاهای 404، صفحات orphan، mixed content و sitemap را اجرا کن.
- [ ] اندازه‌گیری performance را پیش/پس با URL، cache و شرایط یکسان ثبت کن.

**نتیجهٔ این اجرا:** هیچ سایت زنده، staging، Search Console یا HTML رندرشده در دسترس نبود؛ بنابراین نتیجهٔ SEO یا ظاهری نهایی صادر نمی‌شود.

## ۷. افزونه‌ها و دادهٔ SQL

- [ ] وضعیت واقعی افزونه‌ها را از `wp plugin list` یا `active_plugins` در dump کامل تأیید کن.
- [ ] وجود جدول اختصاصی افزونه را فقط نشانهٔ نصب/استفادهٔ قبلی بدان، نه مدرک فعال‌بودن.
- [ ] اگر حذف افزونه مطرح شد: پشتیبان داده، اسکریپت انتقال، محیط تست و مقایسهٔ خروجی قبل/بعد الزامی است.

**نتیجهٔ این اجرا:** در `haftpair_SA.sql.gz` هفت ردیف قابل‌خواندن از `IR_options` پیدا شد (cron و transientها) و گزینهٔ `active_plugins` در این جدول dump وجود نداشت. جدول‌های Rank Math به‌تنهایی وضعیت فعال افزونه را ثابت نمی‌کنند؛ موجودی افزونه‌ها نامشخص می‌ماند.

## ۸. فیلتر تحویل و دروازهٔ مرحله

- [ ] **فنی:** lint، آزمون کد و رفتار WordPress، امنیت، assetها و نسخه‌ها سبز باشند.
- [ ] **مدل/ساختار:** اختلاف relation، taxonomy، slug، redirect و schema تعیین تکلیف شده باشد.
- [ ] **SEO:** فقط با crawl/render واقعی و بدون ادعای فراتر از شواهد تأیید شود.
- [ ] **ظاهر/دسترس‌پذیری:** مرور موبایل/دسکتاپ و صفحه‌کلید ثبت شده باشد.
- [ ] **محتوا:** فقط پس از اتمام کامل بررسی فنی/ساختاری و تأیید جداگانهٔ مالک آغاز شود.

**وضعیت این اجرا:** فنی و ساختاری هنوز کامل نیست؛ PHP/WordPress زنده در دسترس نیست، دو slug شهرستانی و termهای خارج از مدل تعیین تکلیف نشده‌اند و مدل `1.2` منبع مرجع ندارد. مرحلهٔ محتوا شروع نشده است.

## منابع رسمی و داخلی

- [WordPress Theme Review: Required](https://developer.wordpress.org/themes/review/required/)
- [WordPress Child Themes](https://developer.wordpress.org/themes/advanced-topics/child-themes/)
- [WordPress Template Hierarchy](https://developer.wordpress.org/themes/templates/template-hierarchy/)
- [WordPress sanitizing](https://developer.wordpress.org/apis/security/sanitizing/)، [escaping](https://developer.wordpress.org/apis/security/escaping/) و [nonces](https://developer.wordpress.org/apis/security/nonces/)
- [WordPress `register_post_type()`](https://developer.wordpress.org/reference/functions/register_post_type/) و [`register_taxonomy()`](https://developer.wordpress.org/reference/functions/register_taxonomy/)
- [Google Search Central: consolidate duplicate URLs](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Google Search Central: structured data policies](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
- [Google Search Central: Core Web Vitals](https://developers.google.com/search/docs/appearance/core-web-vitals)
- [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- قواعد پروژه: [`AGENTS.md`](../AGENTS.md)، [`skill-SA-agent/SKILL.md`](../skill-SA-agent/SKILL.md)، [`data-model/MASTER_DATA_MODEL.md`](../data-model/MASTER_DATA_MODEL.md)، [`data-model/CHANGELOG.md`](../data-model/CHANGELOG.md) و [workflow انتشار](../.github/workflows/release-child-theme.yml).
