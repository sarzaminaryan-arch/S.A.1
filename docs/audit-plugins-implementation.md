# برنامهٔ ساخت افزونه‌های ممیزی — Path A

**وضعیت:** سورس و گزارش روی شاخهٔ `arena/95d36f25-s-a-1` پوش شده‌اند. بسته‌های نصب در [GitHub Release نسخهٔ Preview 2](https://github.com/sarzaminaryan-arch/S.A.1/releases/tag/iran-audit-plugins-v0.1.0-preview.2) منتشر شدند؛ GitHub Actions run [37852871074](https://github.com/sarzaminaryan-arch/S.A.1/actions/runs/37852871074) با موفقیت ZIPها را ساخت و بارگذاری کرد.
**دستور مالک (2026-10-09):** بستهٔ نصب آماده شود و آزمون تازه لازم نیست؛ workflow فقط بسته‌بندی و انتشار می‌کند و آزمون افزونه اجرا نمی‌کند.
**مرجع API:** سند قراردادی ارائه‌شده از مالک، نسخهٔ 1.1.0 / API 1.1؛ فایل منبع بیرونی در ریپو کپی نشده است.
**مرجع قوانین:** `data/rules.json` نسخهٔ `2026.10.08-2` با 41 قانون؛ snapshot مورد استفاده در بستهٔ Engine بدون تغییر نگهداری می‌شود.

**گزارش جامع پوشش، شکاف‌ها و راهنمای استفاده:** [audit-plugins-status-and-usage.md](audit-plugins-status-and-usage.md).

## آنچه در workspace ساخته شده

- `iran-audit-engine`: bootstrap، schema اختصاصی، اعتبارسنجی قواعد، نگاشت صریح پروفایل، ارزیاب‌ها، ذخیرهٔ گزارش و نتیجه‌ها، REST API، صف قابل‌بازیابی و صفحهٔ تنظیمات.
- `iran-audit-dashboard`: افزونهٔ مستقل RTL که فقط از REST Engine می‌خواند/درخواست‌های مجاز را به آن می‌فرستد؛ نمای کلی، نوشته‌ها، گزارش، صف، ادعاها، قواعد و CSV.
- انتخاب جزئی ماژول‌ها، قوانین قابل‌اجرا در دسته‌های انتخاب‌نشده را `insufficient` ثبت می‌کند و وضعیت گزارش را کامل/تأییدشده نشان نمی‌دهد؛ قرارداد API 1.1 فیلد scope جدیدی دریافت نمی‌کند.
- دیتامدل فعال repository نسخهٔ 1.1، موجودیت `Accommodation` را به‌صورت `RESERVED` از قبل نگه می‌دارد؛ ساخت CPT یا فعال‌سازی این موجودیت جزو این کار نیست.
- نگهداری پیش‌فرض ۱۰ گزارش آخر هر نوشته با امکان تنظیم ۱ تا ۱۰۰؛ پاک‌سازی به جدول‌های اختصاصی موتور محدود است.
- CPT موجود `city` برای همهٔ نوشته‌های City به‌طور ثابت انتخاب می‌شود؛ پروفایل `county` فقط alias داخلی قواعد/API 1.1 است و هیچ CPT، جدول یا موجودیت County ساخته نشده است.
- `province_tax` به فیلد استانِ موجود گزارش وصل شده و کلیدهای متای جغرافیایی City از export فعلی خوانده می‌شوند؛ مختصات فقط با جفت عددیِ داخل بازه معتبر است.
- `tests/test_contract_smoke.py`: نه smoke test بدون وابستگی برای یکپارچگی snapshot قواعد، پوشش evaluatorها، routeهای REST، مرز نوشتن، جدایی Dashboard، فیلدهای قرارداد، City/taxonomy/meta، retention و تعداد formatهای درج Job.
- امتیازدهی موقت از طریق harness PHP-WASM بررسی شده؛ این آزمون، WordPress یا دیتابیس واقعی را اجرا نمی‌کند.

## مرزهای داده و ایمنی

- `iran-audit-engine`: فقط خواندن محتوای WordPress؛ نگهداری گزارش، issue، job، claim و تنظیمات در جدول‌های اختصاصی `{prefix}iaa_*`. تنها option هسته‌ای برای نگهداری نسخهٔ schema، مطابق قرارداد است.
- `iran-audit-dashboard`: مصرف‌کنندهٔ REST API موتور؛ دسترسی مستقیم به دیتابیس یا جدول‌های Engine ندارد.
- افزونه‌ها متن نوشته، post meta، taxonomy، رابطه‌ها، تنظیمات SEO/کش و جدول‌های اصلی WordPress را تغییر نمی‌دهند.
- بررسی PageSpeed و AI، job شبانه و درخواست به URLهای بیرونی در این نسخه اجرا نمی‌شوند. بررسی HTML رندرشده خاموش و opt-in مدیر است؛ فقط پس از آزمون staging فعال شود.
- API و قوانین نسخه‌دارند؛ فایل‌های Drive تغییر نمی‌کنند. هیچ تغییر/نصب/فعال‌سازی روی WordPress زنده انجام نشده است.

## آزمون‌های انجام‌شده در این مرحله

- lint نحوی تمام 12 فایل PHP افزونه با PHP-WASM 8.5: موفق.
- `node --check` برای فایل JavaScript داشبورد: موفق.
- `python3 wp-content/plugins/iran-audit-engine/tests/test_contract_smoke.py`: نه تست موفق.
- آزمون scorer با PHP-WASM: وزن `insufficient` در مخرج coverage، پاس کامل، انتخاب ماژول جزئی، critical و major بررسی شد؛ موفق.
- `git diff --check` برای فایل‌های پیگیری‌شده، به‌همراه اسکن مستقل فاصلهٔ انتهایی در فایل‌های تغییرکرده/جدید افزونه‌ها و سند برنامه: موفق.

### شبیه‌سازی آفلاین تاکسونومی/متا (نه Staging واقعی)

- نمونه از WXR مورخ 2026-10-08: post `154`، «شهرستان کاشان»، `post_type=city`؛ `province_tax=isfahan` با نام نمایشی «اصفهان» و metaهای `sa_city_latitude`، `sa_city_longitude` و `sa_google_map_url` در export وجود دارند.
- طبق دستور قطعی مالک، همهٔ موارد CPT `city` موجود City محسوب می‌شوند. alias داخلی `county` صرفاً برای سازگاری با پروفایل‌های API 1.1 است؛ County entity/CPT/جدول جدیدی وجود ندارد.
- اجرای PHP-WASM روی fixture: پروفایل سازگار resolve شد؛ `province_tax` به استان «اصفهان» و نام نوشته به فیلد City قدیمی API رسید؛ map URL و جفت مختصات معتبر `verified` شدند؛ مختصهٔ ناقص و مقادیر خارج از بازه رد شدند. HTML embed نیز مسیر مستقلی برای تأیید دارد.
- این شبیه‌سازی با export و stubهای WordPress انجام شد؛ WordPress، دیتابیس و محیط Staging واقعی در دسترس/اجرا نبودند. هیچ رکوردی import یا ویرایش نشد.

آزمون REST واقعی، WordPress/PHP-FPM، دیتابیس، capability و nonce در WP، race/cron، export در مرورگر، تست نفوذ، staging و Golden Set هنوز اجرا نشده‌اند.

## ورودی‌های مسدودکنندهٔ پذیرش کامل

1. نگاشت/پذیرش term `historical`، دستهٔ `travel-guide`، `travel_route`، `local_food`، `souvenir` و `post` به پروفایل‌های موجود هنوز کامل نیست؛ Cityهای CPT `city` طبق دستور مالک از این ابهام مستثنا و City محسوب می‌شوند.
2. فایل نسخه‌دار و معتبر تقسیمات کشوری برای `GEO-NAMES-01`؛ تا آن زمان این قانون باید `insufficient` باشد.
3. ده نمونهٔ Golden Set با برچسب انسانی، نسخه/هش محتوا و نتایج موردانتظار؛ بدون آن TP/FP/FN اعلام نمی‌شود.
4. staging، خروجی redacted نسخه‌های PHP/WordPress/افزونه‌ها، HTML رندرشده و امکان آزمون REST؛ هیچ credential یا token در گفتگو/ریپو لازم نیست.
5. تأیید جداگانه برای آزمون لینک بیرونی، PageSpeed، AI یا زمان‌بندی خودکار؛ پیش‌فرض خاموش.

## راه‌حل انتشار و نصب

- **اشتباه من در تحویل قبلی:** بسته را محلی ساختم و مسیر آپلود مستقیم از Arena را انتخاب کردم؛ این sandbox به میزبان `uploads.github.com` دسترسی نداشت. این مشکل از انتقال فایل بود، نه از کد افزونه یا کاری که مالک انجام داده بود.
- **راه‌حل اجراشده:** workflowِ [`.github/workflows/release-audit-plugins.yml`](../.github/workflows/release-audit-plugins.yml) بسته‌ها را روی runner خود GitHub ساخت و بارگذاری کرد. run اول به‌علت شرط اجرای job بدون job شکست خورد؛ شرط برداشته شد و run بعدی موفق شد.
- **نسخهٔ منتشرشده:** [`iran-audit-plugins-v0.1.0-preview.2`](https://github.com/sarzaminaryan-arch/S.A.1/releases/tag/iran-audit-plugins-v0.1.0-preview.2). دارایی‌ها: [Engine ZIP](https://github.com/sarzaminaryan-arch/S.A.1/releases/download/iran-audit-plugins-v0.1.0-preview.2/iran-audit-engine-0.1.0-preview.zip)، [Dashboard ZIP](https://github.com/sarzaminaryan-arch/S.A.1/releases/download/iran-audit-plugins-v0.1.0-preview.2/iran-audit-dashboard-0.1.0-preview.zip)، [راهنمای نصب](https://github.com/sarzaminaryan-arch/S.A.1/releases/download/iran-audit-plugins-v0.1.0-preview.2/INSTALL-PREVIEW.txt)، [SHA-256](https://github.com/sarzaminaryan-arch/S.A.1/releases/download/iran-audit-plugins-v0.1.0-preview.2/SHA256SUMS.txt).
- **نصب دستی:** در WordPress → افزونه‌ها → افزودن افزونه → بارگذاری افزونه، ZIP موتور را اول نصب و فعال کنید؛ سپس ZIP داشبورد را نصب و فعال کنید. یا در cPanel هر ZIP را در `wp-content/plugins/` استخراج و بعد در صفحهٔ افزونه‌ها فعال کنید. نیازمندی‌ها WordPress 6.4+ و PHP 7.4+ است. فعال‌سازی موتور فقط جدول‌های اختصاصی `{prefix}iaa_*` را می‌سازد/به‌روزرسانی می‌کند؛ import محتوا انجام نمی‌شود.

## اعتبار و محدودیت نسخه

این یک Preview قابل نصب برای محیط آزمایشی است، نه تأیید تولیدی. به‌درخواست مالک در این نوبت آزمون تازه‌ای اجرا نمی‌شود. آزمون‌های محلی و شبیه‌سازی‌های ثبت‌شده در بخش‌های بالا مربوط به اجرای قبلی‌اند؛ WordPress واقعی، REST/capability/nonce، پایگاه‌داده، Cron، آزمون نفوذ، Staging واقعی و Golden Set هنوز تأیید نشده‌اند. هیچ اتصال یا فعال‌سازی روی سایت زنده و هیچ import واقعی انجام نشده است.
