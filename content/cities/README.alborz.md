# سرزمین آریان — دستهٔ شهرستان‌های استان البرز (استان ۵)

> منبع فهرست: `wp-content/plugins/sa-province-importer-b01/data/counties.json` (بستهٔ ۱، استان ۵ از ۱۰)
> قالب حاکم: `content-templates/city-county-structure.md` (۱۴۰۵/۰۷/۰۶) + اصلاحیهٔ کارفرما (حداقل ۸۰۰ واژه· بخش بی‌منبع نوشته نمی‌شود)
> این فایل «لیست شروع» استان جدید است؛ پس از تولید هر دسته، جدول گیت همان دسته به آن افزوده می‌شود.

## وضعیت

- **۷ از ۷ شهرستان نوشته شده — همه DRAFT ONLY (۱۴۰۵/۰۷/۰۸)، آمادهٔ بازبینی v1.3** (پیش از آن: استان‌های ۱، ۲، ۳ و ۴ کامل شده‌اند: ۲۱ + ۲۰ + ۱۲ + ۲۹ = ۸۲ شهرستان).
- بازبینی خودکار: هفت فایل با `seo_audit.py` و `review_pass.py` سنجیده و PASS شده‌اند (۸۱۸ تا ۱٬۱۷۴ واژه؛ FAQ ۱۱ پرسش؛ ۵ تا ۷ ردیف منبع با تاریخ دسترسی؛ بدون نشان کارگاهی).
- تصاویر شاخص: `assets/featured/counties/alborz/` — **۷ از ۷** (۷ فایل webp، ۱۲۰۰×۶۷۴ از بستهٔ «alborz images.zip» کارفرما؛ ALT/زیرنویس/عنوان/توضیح در `assets/featured/counties/alborz/manifest.json`).
- افزونهٔ درون‌ریز: **`sa-city-importer-alborz` v1.0.0 ساخته و آماده است** — `wp-content/plugins/sa-city-importer-alborz/` و بستهٔ نصب `downloads/sa-city-importer-alborz-v1.0.0.zip` (۷/۷ مقاله + ۷/۷ تصویر شاخص با ALT).
- بستهٔ داده: `data/alborz.json` + `data/manifest.json` + `data/counties.json` (نسخهٔ داده 1.0.0، ساختهٔ 2026-09-30)؛ نامک‌ها فعلاً همان مقادیر ثبت‌شده (تصمیم نامک در بخش تأییدها).
- نقشهٔ تولید: دستهٔ اول ۵ مقاله → دستهٔ دوم ۲ مقاله (چرخهٔ ۵تایی، بازبینی v1.3 و ۴۰۴-چک پیش از هر تحویل).

## فهرست ۷ شهرستان استان البرز

| # | شهرستان | مرکز | نامک (`slug`) | صفحهٔ آماده | تصویر شاخص مورد انتظار | وضعیت |
|---|---|---|---|---|---|---|
| ۱ | کرج | کرج | `karaj` | `/city/karaj/` | `assets/featured/counties/alborz/karaj.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۲ | فردیس | فردیس | `ferdows` ⚠️ | `/city/ferdows/` | `alborz/ferdows.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۳ | ساوجبلاغ | هشتگرد | `sojablogh` ⚠️ | `/city/sojablogh/` | `alborz/sojablogh.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۴ | نظرآباد | نظرآباد | `nazarabad` | `/city/nazarabad/` | `alborz/nazarabad.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۵ | چهارباغ | چهارباغ | `chaharbagh` | `/city/chaharbagh/` | `alborz/chaharbagh.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۶ | اشتهارد | اشتهارد | `eshtehard` | `/city/eshtehard/` | `alborz/eshtehard.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |
| ۷ | طالقان | طالقان | `talqan` ⚠️ | `/city/talqan/` | `alborz/talqan.webp` | ✅ DRAFT ONLY (۱۴۰۵/۰۷/۰۸) |

نامک‌ها همان چیزی است که امروز در `counties.json` بستهٔ ۱ و در `docs/site-url-structure.md` ثبت شده است (۷ ردیف، همه بدون صفحهٔ موجود در وردپرس).

## ⚠️ سه نامک نیازمند تأیید پیش از شروع

نامک سه شهرستان با قاعدهٔ لاتین‌نویسی بقیهٔ استان‌ها ناهمخوان است و **اگر پیش از ساخت صفحه اصلاح نشود، آدرس منتشرشده بعداً قابل تغییر نیست** (قاعدهٔ نامک ثابت سطح ۴):

| شهرستان | نامک امروز | پیشنهاد استاندارد |
|---|---|---|
| فردیس | `ferdows` | `fardis` |
| ساوجبلاغ | `sojablogh` | `savojbolagh` |
| طالقان | `talqan` | `taleghan` |

در صورت تأیید، هر سه نامک در `counties.json` بستهٔ ۱ + `docs/site-url-structure.md` یک‌جا اصلاح می‌شود (بدون هیچ صفحهٔ ساخته‌شده‌ای که بشکند؛ استان البرز هنوز صفحهٔ شهرستان ندارد) و نام فایل تصاویر شاخص هم بر همان اساس خوانده می‌شود.

## تصاویر شاخص و افزونهٔ درون‌ریز

| مورد | وضعیت |
|---|---|
| تصاویر شاخص البرز | ✅ ۷ از ۷ (`assets/featured/counties/alborz/*.webp` + `manifest.json`) |
| افزونهٔ درون‌ریز | ✅ `sa-city-importer-alborz` v1.0.0 (نسخهٔ هستهٔ مشترک 1.1.0) |
| بستهٔ نصب | `downloads/sa-city-importer-alborz-v1.0.0.zip` (۱۸ فایل · ۷ تصویر webp) |
| بستهٔ داده | `data/alborz.json` (۷ بستهٔ DRAFT ONLY) + `data/manifest.json` + `data/counties.json` |
| نام فایل‌های تصویر | `karaj.webp، ferdows.webp، sojablogh.webp، nazarabad.webp، chaharbagh.webp، eshtehard.webp، talqan.webp` |
| ALT و زیرنویس | از بلوک ۲ هر مقاله (مثلاً «نمایی از کلان‌شهر کرج با درهٔ رودخانه و سد امیرکبیر در دامنهٔ البرز؛ تصویر شاخص شهرستان کرج») |
| WP-CLI | `wp sa-city-import alborz` |

> نکته: نام فایل «fardis.webp» و «savobolagh.webp» در بستهٔ کارفرما به نامک‌های ثبت‌شده (`ferdows`, `sojablogh`) نگاشت شد تا افزونه بدون تغییر داده تصویر را بردارد؛ در صورت تأیید نامک‌های جدید، هر دو یک‌جا اصلاح و زیپ دوباره ساخته می‌شود.

## برنامهٔ دسته‌ها (چرخهٔ ۵تایی)

| دسته | شهرستان‌ها |
|---|---|
| دستهٔ ۱ (۵ مقاله) | کرج، فردیس، ساوجبلاغ، نظرآباد، چهارباغ |
| دستهٔ ۲ (۲ مقاله) | اشتهارد، طالقان |

## صف استان‌های بستهٔ ۱ (پس از البرز)

ایلام (۱۲) · بوشهر (۱۲) · تهران (۱۶) · چهارمحال و بختیاری (۱۲) · خراسان جنوبی (۱۲)
