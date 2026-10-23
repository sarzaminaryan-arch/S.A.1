# گزارش تشخیصی: مشکلات sitemap و ایندکس «سرزمین آریان» از کجاست؟

**تاریخ:** ۲۰۲۶-۱۰-۲۳ · **نسخه:** v1.0 · **وضعیت:** تشخیص کامل از سمت ریپو؛ تأیید سمت سایت با افزونهٔ بازرس
**ورودی:** ۶ فایل xlsx خروجی Google Search Console (پوشهٔ «سایتمپ» در گوگل‌درایو، بارگذاری ۲۰۲۶-۱۰-۲۳)
**ابزارها:** `tools/seo-audit/xlsx_to_csv.py` (تبدیل xlsx→csv بدون وابستگی) و `tools/seo-audit/analyze_gsc.py` (دسته‌بندی و تطبیق با رجیستری قالب)
**دادهٔ ماشینی:** `docs/seo/data/all-urls.csv` (۷۵۱ ردیف، یک ردیف به‌ازای هر URL با الگو/علت/۳۰۱ موجود در ریپو) و `docs/seo/data/summary.md`

---

## ۰) شمارش واقعی (جای اعداد تقریبی صورت‌مسئله)

| گزارش GSC | تعداد واقعی در export | عدد ذکرشده در صورت‌مسئله |
|---|---:|---:|
| Valid | **336** | «۳۳۶» ✅ |
| Discovered – currently not indexed | **303** | ~۳۱۳ |
| Crawled – currently not indexed | **53** | ۵۳ ✅ |
| Excluded by ‘noindex’ tag | **46** | ۴۷ |
| Not found (404) | **12** | ۱۲ ✅ |
| Page with redirect | **1** | ۱ ✅ |
| **جمع URLهای گزارش‌شده** | **751** | — |

تفکیک الگو در هر گزارش (کامل) در `docs/seo/data/summary.md` هست. چند عدد کلیدی:

- `/city/*` در سه گزارش: ۲۰۷ Valid + ۲۶۱ Discovered + ۲۱ Crawled = **۴۸۹ URL شهری**
- `/province/*`: **۳۱** (۲۴ Valid، ۶ Discovered، ۱ Crawled) · `/ostan/*`: **۳۱** (۲۸ noindex، ۳ Discovered) · `/provinces/*`: **۳**
- صفحه‌بندی فصلی: **۴۹** URL (۲۴ Valid + ۲۵ Crawled) — spring تا صفحهٔ ۳۹، autumn تا ۱۴، winter تا ۸، **summer هیچ**
- جاذبه: ۳۷ URL (۷ اسلاگ عددی + ۲۹ اسلاگ تمیز + ۱ مورد 404)
- `attraction-type`: **۱۳** ترم (کانفیگ قالب: ۱۲) → ترم سیزدهم `family` است
- نویسنده/برچسب/جست‌وجو در گزارش noindex: ۷ + ۱۰ + ۱ = ۱۸
- wildcard: **۴** (`/*`، `/wp-content/uploads/*`، `/wp-content/themes/sarzaminaryan-child/*`، `/wp-*.php`) — همه با تاریخ خزندهٔ ۱۰-۱۶/۱۰-۱۷، یعنی **منبعشان همین حالا زنده است**

> ⚠️ نکتهٔ مهم: سایت زنده از این سندباکس قابل دسترسی نیست (فقط github/npm/pypi در allowlist هستند)، بنابراین
> هر ادعایی دربارهٔ «وضعیت فعلی روی سایت» که در این گزارش آمده، یا از export خود GSC است یا از ممیزی زندهٔ
> `v1.11` (۲۰۲۶-۱۰-۱۶) و یا **حدس مستند** است که باید با افزونهٔ بازرس (`sa-seo-inspector`) تأیید شود.
> بخش ۱۱ فهرست دقیق «چیزهایی که هنوز نمی‌دانیم» را دارد.

---

## ۱) ریشهٔ #۱ — واگرایی نسخه: سایت ≠ ریپو ≠ آخرین Release

مهم‌ترین یافته این است: **بخشی از اصلاحاتِ خواسته‌شده در T1/T6/T7 همین حالا در کد ریپو وجود دارد، ولی روی سایت اعمال نشده** — و آخرین Release عمومی، بخشی از همان تصمیم‌ها را **برگردانده** است.

### الف) اصلاحات در ریپو هست، روی سایت اثر نکرده

`wp-content/themes/sarzaminaryan-child/data/redirects.php` (نسخهٔ main = 2.22.19) برای ۱۱ URL گزارش‌شده ۳۰۱ دارد:

| URL گزارش‌شده | گزارش GSC | مقصد ۳۰۱ در ریپو | نتیجه |
|---|---|---|---|
| `/attraction/6976-aramgah-ghasm-anvar/` | **Valid** (۱۰-۱۷) | `/attraction/qasem-anvar-shrine/` | ۳۰۱ اعمال نشده |
| `/attraction/6619-ghlh-dkhtr-bshrvih/` | **Valid** (۱۰-۱۶) | `/attraction/ghaleh-dokhtar-boshruyeh/` | ۳۰۱ اعمال نشده |
| `/attraction/23328-sar-agha-seyed-village/` | **Valid** (۱۰-۱۶) | `/attraction/sar-agha-seyed-village/` | ۳۰۱ اعمال نشده |
| `/attraction/6789-bagh-glshn-tbs/` | **Valid** (۱۰-۱۶) | `/attraction/bagh-golshan-tabas/` | ۳۰۱ اعمال نشده |
| `/attraction/9235-tang-e-sayad-national-park/` | **Valid** (۱۰-۱۵) | `/attraction/tang-e-sayad-national-park/` | ۳۰۱ اعمال نشده |
| `/attraction/26950-kvir-rig-jn/` | **Valid** (۱۰-۱۴) | `/attraction/rig-e-jenn-desert/` | ۳۰۱ اعمال نشده |
| `/attraction/308149-attraction-tabas/` | **Valid** (۱۰-۱۶) | `/city/tabas/` | ۳۰۱ اعمال نشده |
| `/attraction/tabas-attractions/` | **Valid** (۱۰-۱۶) | `/city/tabas/` | ۳۰۱ اعمال نشده |
| `/attraction/dorud-city/` | **404** (۱۰-۱۷) | `/city/dorud/` | ۳۰۱ اعمال نشده → 404 |
| `/city/borazjan/` | **Valid** (۰۹-۳۰) | `/city/dashtestan/` | ۳۰۱ اعمال نشده |
| `/city/ijrud/` | Discovered | `/city/ejrud/` | ۳۰۱ اعمال نشده |

دلیل قطعی بودن این استدلال: موتور ریدایرکت قالب (`inc/redirects.php:136 sa_redirect_handle_request`) روی
`parse_request` با اولویت ۰ می‌نشیند و `exit` می‌کند؛ اگر روی سایت فعال بود، این URLها در GSC به‌جای
**Valid** در «Page with redirect» قرار می‌گرفتند. در کل export فقط **یک** URL در آن گزارش است
(`/provinces/ardabil/`) — یعنی یک موتور ریدایرکتِ *دیگر* (احتمالاً Rank Math) روی سایت فعال است، نه نقشهٔ قالب.

### ب) آخرین Release عمومی، تصمیم‌های SEO را برگردانده

- `main` = **2.22.19** (لایهٔ Phase-7 در `inc/seo.php`: `sa_seo_brand_suffix`، `sa_city_is_thin` + noindex شهرهای نازک، حذف author archive).
- آخرین Release روی GitHub = **v2.22.24** (۲۰۲۶-۱۰-۱۸) از شاخهٔ `arena/41372fa2-s-a-1` = **PR #31 که merge نشده** و از بیس قدیمی‌تر ساخته شده. diff آن با main:
  - `SA_CHILD_VERSION` 2.22.24 (بالاتر از main)
  - **حذف** `sa_seo_brand_suffix` و **حذف** `sa_city_is_thin` + noindex شهرهای نازک از `inc/seo.php` (−۱۱۳ خط از لایهٔ سئو)
  - **واگردانی** `data/redirects.php`: `/city/dorud/` → `/attraction/dorud-city/` (هدفی که 404 است!)، حذف `/attraction/dorud-city/` → `/city/dorud/`، حذف `/attraction/tabas-attractions/` → `/city/tabas/`، حذف `/city/borazjan/` → `/city/dashtestan/`
  - ۴۲ فایل قالب، +۱۸۷۰/−۲۲۰۵

**نتیجهٔ عملیاتی:** اگر سایت الان 2.22.24 را نصب کرده باشد، هم لایهٔ سئوی main را از دست داده و هم ریدایرکت‌هایش واگردانی شده‌اند. اگر 2.20.0 مانده باشد (ممیزی v1.11 این را گفته)، هیچ‌کدام از اصلاحات 2.22.x را ندارد.
در هر دو حالت **نسخهٔ بعدی که می‌سازیم باید `≥ 2.22.25` باشد و از بیس main (2.22.19) ساخته شود**، وگرنه GitHub Updater آن را جدیدتر نمی‌بیند یا دوباره واگردانی می‌شود.
`/.github/workflows/release-child-theme.yml` روی push به `main` و `arena/**` با تغییر مسیر قالب **به‌صورت خودکار Release می‌سازد** — یعنی هر بار که کد قالب را روی این شاخه push کنیم، یک نسخهٔ نصب‌پذیر منتشر می‌شود (این همان مکانیزم «اعمال از طریق افزونه» است).

---

## ۲) ریشهٔ #۲ — مالکیت sitemap و robots دست Rank Math است، لایهٔ سئوی قالب روی سایت بی‌اثر

`inc/seo.php:349 sa_seo_has_external_seo` به‌محض فعال بودن Rank Math/Yoast/AIOSEO/SEOPress **کل** لایهٔ سئوی قالب
را خاموش می‌کند (title/meta/canonical/JSON-LD). دو فیلتر `wp_robots` و `wp_sitemaps_*` باقی می‌مانند — ولی
خروجی GSC نشان می‌دهد حتی آن‌ها هم روی سایت اثر نکرده‌اند:

| مشاهده در GSC | کد قالب چه می‌گوید | نتیجه |
|---|---|---|
| `/author/sarzaminaryan/` (صفحهٔ اول) = **noindex** (۱۰-۱۷) | `sa_seo_robots_noindex` فقط `page > 1` نویسنده را noindex می‌کند | robots از جای دیگری می‌آید |
| ۹ برچسب بزرگ (صفحهٔ اول) = **noindex** (۱۰-۱۶) | قالب فقط صفحه‌بندی برچسب را noindex می‌کند | robots از جای دیگری می‌آید |
| `/food/` و `/route/` = **Valid** | `seo.php:302-305` آرشیو souvenir/food را noindex می‌کند | فیلتر قالب روی سایت فعال نیست |
| ۴۹ URL صفحه‌بندی فصلی در سایت‌مپ/ایندکس | قالب `/season/*/page/N/` را از سایت‌مپ هسته حذف می‌کند | سایت‌مپ سایت، سایت‌مپ هسته نیست |
| ممیزی v1.11: `/wp-sitemap.xml` → 301 → `/sitemap_index.xml` | — | سایت‌مپ زنده = Rank Math |
| ۵۳۶ رکورد با متادیتای دوگانهٔ `sa_seo_*` + `rank_math_*` (۱۰ ناسازگاری عنوان) | — | مالکیت دوگانه |

**پیامد برای همهٔ تسک‌های sitemap/robots (T2، T8، T12):** پیاده‌سازی فقط با `wp_robots` و `wp_sitemaps_*`
روی سایت **کار نمی‌کند**. باید یکی از این دو مسیر انتخاب شود:

- **مسیر ۱ (پیشنهادی):** افزودن لایهٔ سازگاری Rank Math به قالب — فیلترهای `rank_math/frontend/robots`،
  `rank_math/sitemap/include`، `rank_math/sitemap/url` و ... — تا منطق قالب مستقل از اینکه چه افزونه‌ای فعال است
  اجرا شود. مزیت: بدون نیاز به تصمیم/تنظیم دستی، قابل بازگشت، در ریپو تست‌پذیر.
- **مسیر ۲:** غیرفعال کردن Rank Math و واگذاری کامل به قالب. ریسک: از دست رفتن تنظیمات موجود، نیاز به
  بازسازی title/meta/JSON-LD، و احتمال افت موقت.

این یک **تصمیم مالک** است (پرسش Q2).

---

## ۳) ریشهٔ #۳ — سه الگوی نشانی برای استان‌ها، و یک الگوی بدون ردپا در ریپو

ماتریس کامل ۳۱ استان در `docs/seo/data/summary.md` هست. خلاصه:

| الگو | تعداد | وضعیت | منبع در کد |
|---|---:|---|---|
| `/province/{slug}/` | **۳۱** (۲۴ Valid، ۶ Discovered، ۱ Crawled=`khuzestan`) | الگوی canonical طبق `data-model/schema/data-model.json:385-397` | `post-types.php:18` (url_base=`province`)، `inc/setup.php:55` |
| `/ostan/{slug}/` | **۳۱** (۲۸ noindex با خزندهٔ **۰۹-۲۷**، ۳ Discovered) | تکسونومی `province_tax` با اسلگ `ostan` | `taxonomies.php:39`، `inc/entities-config.php:20` |
| `/provinces/{slug}/` | **۳** (`ardabil` → redirect، `zanjan` و `mazandaran` → **Valid**) | ❓ **در ریپو هیچ منبعی ندارد** | هیچ |

دو نکتهٔ مهم:

1. **۲۸ مورد noindex روی `/ostan/` کهنه است** (آخرین خزنده ۰۹-۲۷، یعنی پیش از آنکه ممیزی ۱۰-۱۶ ریدایرکت ۳۰۱ را ببیند).
   این‌ها خودبه‌خود به «Page with redirect» مهاجرت می‌کنند و نیازی به «برداشتن noindex» ندارند — دقیقاً همان که در
   صورت‌مسئله آمده. ولی ۳ مورد `/ostan/{hamadan,markazi,semnan}/` هنوز **Discovered** است؛ باید تأیید شود ۳۰۱ هستند
   (بازرس این را می‌گوید).
2. **`/provinces/` فقط ۳ استان دارد** — پس یک الگوی سیستماتیک (بیس CPT یا تکسونومی) نیست؛ یا چند صفحه/نوشتهٔ
   واقعی با این اسلاگ وجود دارد، یا چند ردیف در جدول redirections رنک‌مث، یا قاعده‌ای در `.htaccess`.
   `zanjan` و `mazandaran` **ایندکس‌شده** هستند (Valid) یعنی محتوای ۲۰۰ دارند، و `ardabil` الان ریدایرکت است.
   **منبعش فقط با بازرس معلوم می‌شود:** `environment.rewrite_rules_entity` + `redirections.rank_math_redirections` +
   `content_scan.provinces_link` + `inventory.pages`.

همچنین: `inc/internal-links.php:851-856` در حالت fallback لینک استان را با `get_term_link()` می‌سازد که به
`/ostan/` می‌رسد؛ ممیزی v1.11 هم ۹۴ لینک `/ostan/` در محتوا/منو/فوتر یافته. یعنی **لینک‌سازی داخلی هنوز به
الگوی قدیمی اشاره می‌کند** و اگر ۳۰۱ روی سایت فعال نباشد، گوگل همان را می‌بیند (T13).

---

## ۴) ریشهٔ #۴ — موجودیت تکراری در دیتابیس زنده (خارج از رجیستری ریپو)

### ۴-۱) جفت‌های تکراری شهر

| شهرستان | نشانی‌های دیده‌شده در GSC | در رجیستری ریپو (`data/counties.php`) |
|---|---|---|
| تربت جام | `/city/torbat-jam/` **Valid** + `/city/torbatjam/` **Valid** | فقط `torbatjam` (published) |
| دورود | `/city/dorud-city/` **Valid** + `/city/dorud/` Discovered | `dorud` با status=`empty` |
| بوشهر | `/city/bushehr-city/` **Valid** + `/city/bushehr-county/` **Valid** | فقط `bushehr-county` (published) |
| برازجان/دشتستان | `/city/borazjan/` **Valid** + `/city/dashtestan/` **Valid** | فقط `dashtestan` (published) |
| ایجرود | `/city/ejrud/` **Valid** + `/city/ijrud/` Discovered | `ejrud` (empty) |
| خرمدره | `/city/kharadere/` **Valid** + `/city/khorramdarreh/` Discovered | `kharadere` (empty) — املای رجیستری با نام واقعی شهر نمی‌خواند |

یعنی **۶ جفت تکراری (۱۲ URL)** که در ۴ جفت، **هر دو طرف ایندکس شده‌اند**.

### ۴-۲) اسلاگ‌های با پسوند ناسازگار (۱۲ URL در GSC)

`Valid`: `ilam-city`، `dorud-city`، `ahvaz-city`، `tehran-city`، `bushehr-city`، `bushehr-county`، `ardabil-city`، `isfahan-city`
`Discovered`: `qazvin-city`، `qom-city`، `semnan-city`، `zanjan-city`

۹ تای اینها عیناً در رجیستری ریپو هستند (با status=published) و `bushehr-county` هم هست؛ `dorud-city` و `bushehr-city` خارج از رجیستریند.

**نکتهٔ کلیدی برای T7:** هیچ‌کدام از نشانی‌های پایهٔ canonical (`/city/tehran/`، `/city/ahvaz/`، `/city/isfahan/`، `/city/ardabil/`، `/city/ilam/`، `/city/qazvin/`، `/city/qom/`، `/city/semnan/`، `/city/zanjan/`، `/city/bushehr/`) در هیچ‌کدام از ۶ گزارش GSC ظاهر نشده‌اند. پس **تغییر اسلاگ این ۱۰ مورد «تمیز» است** (تکراری جدید نمی‌سازد) و فقط به rename + ۳۰۱ نیاز دارد.

### ۴-۳) جاذبه‌های با اسلاگ عددی (۷ مورد) و همزادهای تمیز

همهٔ ۷ URL عددی **Valid** هستند و ۵ همزاد تمیزشان هم Valid/در دسترس (`sar-agha-seyed-village`، `tang-e-sayad-national-park`، `bagh-golshan-tabas`، `ghaleh-dokhtar-boshruyeh`، `rig-e-jenn-desert`)، یکی Crawled-not-indexed (`qasem-anvar-shrine`)، و دو مورد (`tabas-attractions`، `attraction-tabas`) طبق تصمیم ریپو باید به `/city/tabas/` بروند. جدول کامل در `summary.md`.

### ۴-۴) ترم `family` در `attraction_type`

GSC **۱۳** ترم `/attraction-type/*` را Discovered نشان می‌دهد؛ کانفیگ قالب (`inc/entities-config.php:739-768`) **۱۲** ترم دارد. ترم سیزدهم `family` است → در دیتابیس زنده ساخته شده (دستی یا با import قدیمی) ولی در مدل داده نیست. باید یا حذف شود یا به کانفیگ/محتوا اضافه شود (تصمیم با محتوای واقعی‌اش).

### ۴-۵) جمع‌بندی علت

رجیستری ریپو و دیتابیس زنده **دو منبع حقیقت موازی** شده‌اند: رجیستری ۴۸۳ شهرستان دارد، ممیزی زنده ۴۸۷، و GSC
۴۸۹ نشانی شهری (با احتساب جفت‌ها). اسلاگ‌هایی در زنده هست که در رجیستری نیست و برعکس.
**تا زمانی که یک موجودی قطعی از دیتابیس زنده نداشته باشیم، هر اصلاح اسلاگ/ریدایرکت می‌تواند تکراری جدید بسازد.**
این دقیقاً کاری است که `sa-seo-inspector` انجام می‌دهد (`inventory.city_posts` همهٔ ۴۸۷+ ردیف را با اسلاگ، وضعیت،
طول محتوا و متاهای noindex/canonical می‌دهد).

---

## ۵) ریشهٔ #۵ — wildcard‌های 404: منبع بیرون از ریپو است

۴ مورد، همه با خزندهٔ تازه (۱۰-۱۶/۱۰-۱۷):

```
/*
/wp-content/uploads/*
/wp-content/themes/sarzaminaryan-child/*
/wp-*.php
```

در ریپو **هیچ** منبعی برای این‌ها نیست: نه `robots_txt` فیلتری دارد، نه sitemap‌ساز هسته فعال است، نه فایل XML
سایت‌مپ در ریپو وجود دارد (`grep -rl "<urlset"` → صفر نتیجه)، و `.gitignore` هیچ فایل robots/sitemap را ردیابی نمی‌کند.
پس منبع یکی از این‌هاست:

1. فایل فیزیکی `robots.txt` یا `sitemap.xml` در ریشهٔ وب که توسط هاست/افزونه/دستی نوشته شده و الگوی wildcard دارد؛
2. سایت‌مپ یا robots تولیدی Rank Math با قاعدهٔ wildcard؛
3. سایت‌مپی که قبلاً دستی در GSC ثبت شده و هنوز در صف است (کم‌محتمل، چون تاریخ خزنده تازه است).

بازرس با `robots_files.robots_served` + `robots_files.root_files` + `robots_files.root_xml_glob` + `sitemaps`
این را **قطعی** می‌کند. ضمناً همین‌جا تکلیف `Disallow`‌های اشتباه هم روشن می‌شود: `/wp-content/uploads/` نباید
بسته شود (تصاویر باید خزیده شوند) ولی `/wp-content/themes/` و فایل‌های PHP باید بسته بمانند.

---

## ۶) ریشهٔ #۶ — لینک‌های خراب تولیدشده در محتوا

### ۶-۱) `W_*` (کدهای ارجاع شهرستان‌های اصفهان)

- در GSC: `/city/fereydan/W_FER`، `/city/chadegan/W_CHA`، `/city/kuhpayeh/W_KUH` → **404 با خزندهٔ ۱۰-۱۶/۱۰-۱۷** (تازه).
- در ممیزی v1.11: ۷۹ بار تکرار در ۱۲ برگه، ۱۹۹ لینک شکسته؛ مقادیر: `W_ARD W_BUI W_CHA W_DEH W_FDS W_FER W_GOL W_HAR W_KHA W_KUH W_TIR W_VAR`.
- در ریپو: هیچ‌جا تولید نمی‌شود → **در `post_content` دیتابیس زنده است** (باقی‌ماندهٔ pipeline پاک‌سازی محتوا/AI).
- علت فنی ماندگاری: `inc/citations.php:337 sa_citation_is_external` فقط `http(s)://` را خارجی می‌شمارد، پس
  `href="W_FER"` به‌عنوان لینک نسبی رندر می‌شود و مرورگر/گوگل آن را به `/city/{slug}/W_FER` حل می‌کند → 404.

### ۶-۲) `/editorial-policy/`

- GSC: **404** (۱۰-۱۶) · درحالی‌که `/policy/` **Valid** (۰۹-۳۰) و `/?page_id=9` **404** است.
- ریپو: `template-parts/content-single-post.php:21,42` با `get_page_by_path('editorial-policy')` لینک می‌دهد؛
  `tools/content-lint/lint-config.json:21` و `docs/camping-education/ARTICLE-STANDARD.md:80-84` هم همین اسلاگ را
  canonical می‌دانند؛ ممیزی زنده ۱۷ لینک `/editorial-policy` در `post_content` یافته.
- علت: `inc/activation.php:63-75` چهار برگهٔ draft با اسلاگ‌های `about/contact/privacy/policy` می‌سازد — **هیچ‌وقت
  برگه‌ای با اسلاگ `editorial-policy` ساخته نشده**.

### ۶-۳) باقی‌مانده‌های نصب وردپرس که ایندکس شده‌اند

`Valid`: `/سلام-دنیا/` (hello-world)، `/category/دسته‌بندی‌نشده/`، `/?page_id=6`، `/dashboard/`، `/page/4/`
`404`: `/?page_id=7`، `/?page_id=8`، `/?page_id=9`
منبع: برگه‌های draft ساخته‌شده در activation (IDهای ۶ تا ۹ → `?page_id=`)، نوشته/دستهٔ پیش‌فرض وردپرس که حذف
نشده، و یک موجودیت با اسلاگ `dashboard` (احتمالاً برگه یا پلاگین). **همه در دیتابیس زنده‌اند، نه در ریپو** →
با open one-shot (T9) جمع می‌شوند؛ `reports/cleanup/noindex-list-20261018-180852.csv` فهرست ۲۸ ردیفی شهرهای نازک
را از قبل دارد و `tools/db-cleanup/sa-cleanup.php` در حالت report-only است.

---

## ۷) ریشهٔ #۷ — صفحه‌بندی عمیق فصلی (۴۹ URL، ۲۴ تای ایندکس‌شده)

- `travel_season` به province/city/attraction/travel_route می‌چسبد (`inc/entities-config.php:785`) → آرشیو هر فصل
  صدها مورد دارد: spring تا **صفحهٔ ۳۹**، autumn تا ۱۴، winter تا ۸ (summer هیچ).
- ۲۴ URL صفحه‌بندی **Valid** (ایندکس‌شده) و ۲۵ URL **Crawled – not indexed** (همه با خزندهٔ ۱۰-۱۷ → تازه و فعال).
- تصمیم صورت‌مسئله درست است: **فقط `noindex,follow`** (نه canonical+noindex با هم) + ۳۰۱ برای صفحه‌های بیرون از
  بازه + حذف از سایت‌مپ. سابقهٔ پیاده‌سازی در ریپو هست: `inc/entity-bars.php:108-126 sa_entity_bars_redirect_paged`.
- چون سایت‌مپ زنده مال Rank Math است (ریشهٔ #۲)، حذف از سایت‌مپ باید از مسیر فیلتر/تنظیم Rank Math انجام شود.

---

## ۸) ریشهٔ #۸ — شهرهای نازک: ۲۱ مورد Crawled-not-indexed با خزندهٔ تازه

همه بین ۱۰-۰۳ تا ۱۰-۱۷ خزیده شده‌اند:
`sirvan، dehloran، rey، dashtiari، eyvan، aradan، ferdows، shadegan، darmiyan، shiraz، eqlid، kavar، hamun، sarvestan، beyza، zarrin-dasht، rostam، qir-va-karzin، pasargad، evaz، jovein`

- ۹ مورد از فارس است (eqlid، kavar، sarvestan، beyza، zarrin-dasht، rostam، qir-va-karzin، pasargad، evaz) → الگوی
  «تولید انبوه شهرستان کم‌محتوا».
- `shiraz` هم اینجاست، درحالی‌که `/city/shiraz-city/` نیست و رجیستری `shiraz` را `empty` می‌داند → محتوای واقعی ندارد.
- مکانیزم noindex شهر نازک (`sa_city_is_thin`) در `inc/seo.php` main وجود دارد **اما در Release 2.22.24 حذف شده** (ریشهٔ #۱).
- این مورد **ماهیتاً فاز ۲ است** (غنی‌سازی محتوا)، ولی چون ۲۱ URL تازه خزیده شده‌اند، اولویتِ بهداشت sitemap
  (حذف از سایت‌مپ/noindex موقت) قبل از تولید محتوا منطقی است.

---

## ۹) ریشهٔ #۹ — هاب/آرشیوهای خالی که ایندکس شده‌اند

`Valid`: `/food/`، `/route/`، `/city/`، `/province/`، `/blog/` · `Discovered`: `/attraction/`
`/attraction-type/*` (۱۳) و `/season/*` (۴) هم Discovered هستند.
ممیزی v1.11: ۱۳ آرشیو attraction_type + ۴ آرشیو season **بدون توضیح یکتا** قابل‌ایندکس‌اند.
قالب برای این‌ها hub می‌سازد (`inc/education.php`، `inc/entity-bars.php`) ولی آرشیوهای خالی/بدون توضیح ارزش
ایندکس ندارند و فقط بودجهٔ خزنده را می‌گیرند (T10/T12).

---

## ۱۰) ریشهٔ #۱۰ — سه مقالهٔ Crawled-not-indexed، یکی YMYL

`/overdose-rescue-guide/` (YMYL — راهنمای مصرف بیش‌ازحد)، `/maintain-fire-in-rain/`، `/ground-insulation-sleeping/`
این سه **محتوایی** هستند (نه فنی): یا کیفیت/عمق کافی ندارند یا رقبای قوی‌تر. فاز ۲ (Q1-Q10).

---

## ۱۱) چیزهایی که هنوز نمی‌دانیم و فقط بازرس جواب می‌دهد

| # | پرسش | کلید در JSON بازرس |
|---|---|---|
| 1 | نسخهٔ واقعی قالب/وردپرس/PHP و فهرست افزونه‌های فعال روی سایت در همین لحظه | `environment.theme`, `environment.active_plugins` |
| 2 | Rank Math رایگان یا Pro؟ کدام ماژول‌ها؟ تنظیمات sitemap/robots چه می‌گوید؟ | `seo_plugins.detected`, `seo_plugins.rank_math_options` |
| 3 | منبع الگوهای wildcard (`/*`, `/wp-*.php`, ...) کجاست؟ فایل فیزیکی robots/sitemap در ریشه هست؟ | `robots_files.root_files`, `robots_files.root_xml_glob`, `robots_files.robots_served` |
| 4 | منبع `/provinces/{ardabil,zanjan,mazandaran}/` چیست؟ (قاعدهٔ rewrite، ردیف redirection، یا صفحهٔ واقعی) | `environment.rewrite_rules_entity`, `redirections.rank_math_redirections`, `inventory.pages`, `content_scan.provinces_link` |
| 5 | سایت‌مپ زنده دقیقاً چه URLهایی می‌فرستد و چند تاست؟ | `sitemaps.sources[].urls` |
| 6 | موجودی قطعی شهرستان‌ها: چند published؟ کدام اسلاگ‌ها تکراری/خارج از رجیستری؟ طول محتوای هرکدام؟ | `inventory.city_posts`, `inventory.duplicate_slugs` |
| 7 | `?page_id=6..9` به کدام برگه‌ها نگاشت می‌شود و وضعیشان چیست؟ | `inventory.pages` |
| 8 | ۱۲ برگهٔ دارای `W_*` کدام‌اند و متن اطرافشان چیست؟ | `content_scan.W_code_relative_link` |
| 9 | منوها/ویجت‌ها به `/ostan/` یا `-city` لینک می‌دهند؟ | `content_scan.nav_menus`, `content_scan.text_widgets` |
| 10 | ترم `family` وجود دارد؟ چند پست دارد؟ | `inventory.terms` |
| 11 | نقشهٔ ۳۰۱ قالب روی سایت هست؟ چند ردیف؟ (یعنی نسخهٔ نصب‌شده شامل `data/redirects.php` فعلی است یا نه) | `redirections.theme_redirect_map` |
| 12 | هر URL کلیدی در عمل چه کد وضعیت/Location/X-Robots-Tag/canonical/meta robots دارد؟ | `probes` |
| 13 | چه چیزی حدود ۱۰-۱۱/۱۰-۱۲ تغییر کرد؟ (جهش همزمان: Valid ۲۰۶→۳۳۶ و Crawled-not-indexed ۲→۵۳) | `environment` (نسخه‌ها) + تاریخچهٔ deploy نزد مالک |

> **یافتهٔ زمانی مهم:** در هر دو گزارش، نمودار در **۲۰۲۶-۱۰-۱۲** جهش دارد: URLهای Valid از ۲۰۶ به ۳۳۶
> و Crawled-not-indexed از ۲ به ۵۳. یعنی حدود ۱۰-۱۱/۱۰-۱۲ اتفاقی روی سایت افتاده (نصب نسخهٔ جدید، تغییر
> سایت‌مپ، یا submit دستی) که هم ایندکس‌شده‌ها را زیاد کرده و هم ردشده‌ها را. این قبل از Release‌های ۱۰-۱۸ است.
> تاریخچهٔ deploy فقط نزد مالک/بازرس است.

---

## ۱۲) حجم کار دقیق (برای فاز اصلاح)

| دستهٔ اقدام | تعداد URL | منبع عدد |
|---|---:|---|
| ۳۰۱ `/ostan/{31}` → `/province/{slug}/` | ۳۱ | export (۲۸ noindex کهنه + ۳ Discovered) |
| ۳۰۱ `/provinces/{3}` → `/province/{slug}/` | ۳ | export |
| ۳۰۱ جاذبهٔ عددی + tabas | ۸ | export (۷ عددی + `tabas-attractions`) |
| ۳۰۱ نام مستعار شهر (`torbat-jam`, `ijrud`, `khorramdarreh`, `borazjan`, `dorud-city`, `bushehr-city`) | ۶ | export + رجیستری |
| rename + ۳۰۱ برای ۱۲ اسلاگ `-city/-county` | ۱۲ | export |
| ۳۰۱ `/?page_id=6..9` | ۴ | export |
| ۳۰۱ `/policy/` → `/editorial-policy/` | ۱ | export + قالب |
| noindex صفحه‌بندی فصلی | ۴۹ | export |
| noindex نویسنده/برچسب/جست‌وجو | ۱۸ | export |
| خروج از سایت‌مپ + noindex/حذف برای زباله‌های وردپرس | ۸ | export (`سلام-دنیا`، `دسته‌بندی‌نشده`، `?page_id=6`، `/dashboard/`، `/page/4/`، `/feed/`، `/food/`، `/route/`) |
| اصلاح لینک‌های `W_*` | ۱۹۹ لینک در ۱۲ برگه | ممیزی v1.11 + GSC |
| انتشار/ایجاد برگه‌ها (`editorial-policy`, `about`, `contact`, `privacy`, `policy`) | ۵ | activation + export |
| شهرهای نازک (noindex موقت یا غنی‌سازی) | ۲۱ + ۲۸ ردیف CSV موجود | export + `reports/cleanup/noindex-list-20261018-180852.csv` |

---

## ۱۳) جمع‌بندی یک‌خطی

مشکل اصلی «سایت‌مپ بد» نیست؛ **سه شکاف همزمان** است:
(۱) کد اصلاح‌شده در ریپو به سایت نرسیده و آخرین Release حتی آن را واگردانده،
(۲) مالکیت robots/sitemap دست Rank Math است ولی کد قالب برای هستهٔ وردپرس نوشته شده،
(۳) دیتابیس زنده و رجیستری ریپو از هم واگرا شده‌اند (اسلاگ‌های تکراری/عدد‌دار/پسونددار و لینک‌های خراب در محتوا).
تا این سه شکاف بسته نشود، هر اصلاح URL به‌صورت جزیره‌ای عمل می‌کند و گزارش بعدی GSC پر از مورد تازه می‌شود.
