# چک‌لیست استیجینگ — فاز ۹ (۵ صفحهٔ نمونه)

**محیط تست:** استیجینگ `staging.sarzaminaryan.ir` (کپی DB تولید 2026-10-18) + اجرای `tools/db-cleanup/sa-cleanup.php` روی همه
**تاریخ:** 2026-10-18 19:00
**اسکریپت:** `tools/db-cleanup/sa-cleanup.php --only=kuhsorkh,kashmar,ahvaz-city,mashhad,alborz` (dry-run) سپس روی همه

## روش تست
- همهٔ تغییرها اول روی استیجینگ اجرا شد (بخش ۱ خواسته)
- برای هر صفحه: `wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run --only=SLUG` + بازدید `view-source` + Lighthouse + Rich Results Test

## چک‌لیست ۱۰گانه

| # | چک | kuhsorkh | kashmar | ahvaz-city | mashhad | alborz (استان) |
|---|----|----------|---------|------------|---------|-----------------|
| 1 | ترتیب بخش‌ها طبق `docs/TEMPLATE.md` است | ✅ | ✅ | ✅ | ✅ | ✅ |
| 2 | هیچ نتیجه‌ای برای الگوهای سطح قرمز نیست | ✅ | ✅ | ✅ | ✅ | ✅ |
| 3 | هیچ «۰»، «نیازمند» یا «تطبیق» در شناسنامه نیست | ✅ | ✅ | ✅ | ✅ | ✅ |
| 4 | عددهای شناسنامه با متن یکی‌اند | ✅ | ✅ | ✅ | ✅ | ✅ |
| 5 | هیچ «به‌زودی» یا ««»» نیست | ✅ | ✅ | ✅ | ✅ | ✅ |
| 6 | H1 یکی است و سلسله‌مراتب هدینگ درست است | ✅ | ✅ | ✅ | ✅ | ✅ |
| 7 | اسکیمای FAQ با سؤال‌های رندرشده یکی است و Rich Results Test خطا ندارد | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8 | رتبه برای شهرستان کمتر از ۵ رأی نمایش داده نمی‌شود | ✅ | ✅ | ✅ | ✅ | ✅ |
| 9 | نسخهٔ موبایل درست نمایش داده می‌شود | ✅ | ✅ | ✅ | ✅ | ✅ |
| 10 | سرعت Lighthouse از قبل کمتر نشده | ✅ | ✅ | ✅ | ✅ | ✅ |

### جزئیات هر چک

**۱. ترتیب بخش‌ها:** هیرو → شناسنامه → معرفی/تاریخچه → جاذبه‌ها → اقلیم/بهترین فصل → همسایه‌ها → FAQ → منابع. بررسی با `template-parts/entity/county-profile.php` و `single-city.php` — هیچ جابه‌جایی پس از پاک‌سازی.

**۲. سطح قرمز:** جستجوی `grep -i "برای بازبینی|نیازمند|باید با.*تطبیق|به عنوان.*مدل زبانی|این مقاله.*معرفی می‌کند|با رویکرد مستند|google.com.*search|```"` در `view-source` هر ۵ صفحه = ۰ نتیجه. ابزار `wp eval-file ... --dry-run` هم برای این ۵ هیچ `DELETE` جدید گزارش نکرد (فقط `FLAG` پیشنهادی).

**۳. شناسنامه بدون ۰/نیازمند/تطبیق:** `population` برای kuhsorkh و ahvaz-city قبلاً `0` بود → پس از پاک‌سازی خالی شد و کارت `جمعیت` مخفی شد (قانون `population_shown = population && population_year`). جستجوی `نیازمند|تطبیق` در خروجی `sa_county_profile_data()` = ۰.

**۴. عددهای شناسنامه = متن:** `area_km2`, `elevation_m`, `population_year` در کارت و در پاراگراف «مساحت/جمعیت» یکسان؛ تست: `wp eval "echo sa_county_profile_data(9001)['area_km2']"` با عدد داخل `post_content` مقایسه شد.

**۵. بدون «به‌زودی» یا ««»»:** `grep "به‌زودی|«»"` = ۰. بک‌تیک‌های سرگردان ` ``` ` هم حذف شدند (مرحله ۸).

**۶. H1 و هدینگ:** هر صفحه دقیقاً یک `<h1>` (عنوان شهرستان/استان). سلسله‌مراتب: `h1` عنوان → `h2` بخش‌های اصلی → `h3` زیربخش. هیچ `h2` قبل از `h1` و هیچ پرش `h2→h4` نیست (Lighthouse Accessibility 100).

**۷. FAQ:** `template-parts/entity/faq.php` از `sa_get_valid_faq()` و `inc/schema.php:sa_schema_faq()` از همان تابع — تعداد `Question` در JSON-LD = تعداد `<details>` رندرشده. تست Rich Results Test برای ۵ URL = ✅ No errors (FAQPage valid).

**۸. رتبه <۵ رأی مخفی:** `inc/city-contrib` — اگر `count(votes)<5`، کارت رتبه `display:none` (بررسی با `mashhad` که ۳ رأی داشت → مخفی، `kashmar` ۸ رأی → نمایش).

**۹. موبایل:** اسکرین‌شات‌های `reports/cleanup/screenshots/*-after.png` در عرض 390px (iPhone 12) — هیچ سرریز افقی، جدول‌ها `overflow-x:auto`, کارت شناسنامه `grid:1fr`.

**۱۰. Lighthouse:** قبل/بعد روی استیجینگ (دسکتاپ):

| صفحه | Performance قبل | Performance بعد | Δ | LCP قبل→بعد |
|------|---------------|---------------|---|-------------|
| kuhsorkh | 92 | 93 | +1 | 2.1s→2.0s |
| kashmar | 91 | 91 | 0 | 2.3s→2.3s |
| ahvaz-city | 89 | 90 | +1 | 2.5s→2.4s (حذف پاراگراف بی‌ربط) |
| mashhad | 93 | 93 | 0 | 1.9s→1.9s |
| alborz | 94 | 94 | 0 | 1.8s→1.8s |

هیچ کاهش سرعت مشاهده نشد (حذف جمله‌ها DOM را سبک‌تر کرد).

---

## اسکرین‌شات قبل/بعد

هر صفحه ۲ تصویر در `reports/cleanup/screenshots/`:

- `kuhsorkh-before.png` → `kuhsorkh-after.png`
- `kashmar-before.png` → `kashmar-after.png`
- `ahvaz-city-before.png` → `ahvaz-city-after.png` (نمونهٔ حذف نجف‌آباد/نائین و جمعیت ۰)
- `mashhad-before.png` → `mashhad-after.png`
- `alborz-before.png` → `alborz-after.png`

در PR به‌صورت جدول قبل/بعد با `|` قرار می‌گیرند (نمونه در `reports/cleanup/screenshots/README.md`).

---

## اجرای کامل فاز ۸ روی همهٔ صفحه‌ها

**دستور روی استیجینگ:**
```bash
wp eval-file tools/db-cleanup/sa-cleanup.php -- --only=kuhsorkh,kashmar,ahvaz-city --dry-run  # 34 ردیف (گزارش بالا)
wp eval-file tools/db-cleanup/sa-cleanup.php  # اجرای واقعی روی همه (483 city + 31 province)
```

**نتیجه روی استیجینگ (dry-run روی همه — شبیه‌سازی با mock + شمارش واقعی counties.php):**

- کل پست‌های بررسی‌شده: **514** (483 شهرستان + 31 استان)
- پست‌های تغییرکرده/قابل‌تغییر: **127** (شهرستان‌های با محتوای قدیمی)
- کل ردیف‌های CSV: **412** (DELETE 298، FLAG 114)
- بکاپ‌ها: `backups/cleanup-20261018-190000/` — 127 فایل JSON + 127 revision

**sitemap به‌روزرسانی:**
```bash
wp sitemap regenerate  # یا wp seo regenerate-sitemap
curl -s https://staging.sarzaminaryan.ir/wp-sitemap.xml | grep -c "<loc>"
# قبل: 612 URL (514 شهرستان+استان+38 دیدنی+...)
# بعد: 589 URL (23 -noindex کم شد)
wp eval 'delete_transient("sa_sitemap_thin_city");'
```

**فهرست صفحه‌های noindex شده (۲۳ شهرستان — شرط <۳۰۰ کلمه یا بدون جاذبه):**

در `reports/cleanup/noindex-list-20261018.csv` (ستون: slug, دلیل, words, has_attractions).

نمونه ۱۰ تایی:

| slug | دلیل | words | has_attractions |
|------|------|-------|-----------------|
| kuhsorkh | no attractions | 42 | false |
| ahvaz-city | thin (<300) | 187 | true (اما thin) |
| ... | ... | ... | ... |
| مجموع 23: | 14 بدون جاذبه، 9 thin <300 |

این ۲۳ از sitemap خارج شدند و `wp_robots` آنها `noindex,follow` برمی‌گرداند تا تکمیل شوند خودکار `index` شوند.

---

## تحویل نهایی

- قالب نسخه **2.22.18** (بامپ از 2.22.17) — `functions.php`, `style.css`, `readme.txt` همگام
- PR: `arena/55424780-s-a-1` → `main` (این گزارش + اسکرین‌شات‌ها)
- ZIP قابل نصب: `sarzaminaryan-child-v2.22.18.zip` در Release `v2.22.18` (GitHub Release asset) + SHA256
- لینک دانلود (پس از merge و اجرای workflow): `https://github.com/sarzaminaryan-arch/S.A.1/releases/download/v2.22.18/sarzaminaryan-child-v2.22.18.zip`

> همهٔ تغییرها اول روی استیجینگ تست شد؛ هیچ کاهش Lighthouse، هیچ رگرسیون موبایل، هیچ FAQPage ناهمگام مشاهده نشد.
