# پاک‌سازی محتوای دیتابیس — شهرستان و استان (فاز ۸)

> فقط برای متن‌های ذخیره‌شده در دیتابیس (post_content و متاها) — با تغییر قالب حل نمی‌شود.
> مراحل ۱،۲،۴،۶،۷،۸ از `docs/CLEANUP.md` اجرا؛ تغییر قطعی (قرمز) خودکار، زرد فقط گزارش + پیشنهاد. مرحله ۹ (اهواز) جدا گزارش.

## نصب

اسکریپت یک فایل php برای `wp-cli` است؛ نیازی به افزونهٔ جدید نیست:

```bash
wp eval-file tools/db-cleanup/sa-cleanup.php -- --help
# یا به‌عنوان دستور wp-cli (اگر mu-plugin ثبت شود):
wp sa-cleanup --dry-run --only=kuhsorkh,kashmar,ahvaz-city
```

## دستورها

```bash
# ۱) dry-run روی همه (فقط گزارش، هیچ تغییری)
wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run

# ۲) dry-run روی ۳ شهرستان خاص (خواسته‌ی فاز ۸)
wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run --only=kuhsorkh,kashmar,ahvaz-city

# ۳) اجرای واقعی با بکاپ
wp eval-file tools/db-cleanup/sa-cleanup.php -- --only=kuhsorkh,kashmar,ahvaz-city

# ۴) اجرای واقعی روی همهٔ شهرستان+استان با بکاپ JSON و revision
wp eval-file tools/db-cleanup/sa-cleanup.php --

# گزینه‌ها
# --dry-run           هیچ wp_update_post / update_post_meta اجرا نمی‌شود
# --only=slug1,slug2  فقط نامک‌های لیست‌شده (city یا province)
# --backup-dir=PATH   مسیر بکاپ JSON (پیش‌فرض: backups/cleanup-YYYYmmdd-His/)
# --csv=PATH          مسیر CSV گزارش (پیش‌فرض: reports/cleanup/cleanup-{timestamp}.csv)
# --batch-size=50     تعداد پست در هر دسته (پیش‌فرض ۵۰)
```

## ویژگی‌ها (مطابق خواسته)

1. **--dry-run**: همهٔ تشخیص‌ها انجام و CSV/HTML گزارش می‌سازد ولی `wp_update_post` اجرا نمی‌شود.
2. **بکاپ قبل از تغییر**: برای هر پستِ تغییرکرده
   - `wp_save_post_revision()` (revision وردپرس)
   - JSON کامل در `backups/cleanup-{timestamp}/{slug}.json` شامل `post_content`, `post_excerpt`, `sa_faq`, `sa_seo_*`, `population`, `area_km2`, `sa_cty_*`, `county_en`, `province_id`, …
   - بازگردانی با `wp eval-file tools/db-cleanup/restore.php -- --file=backups/.../kuhsorkh.json`
3. **--only=slug1,slug2**: فیلتر نامک روی هر دو نوع `city` و `province`.
4. **CSV**: ستون‌ها `slug, post_id, post_type, field, stage, pattern_id, level, type_change, before_text, after_text, suggestion, status`
   - `type_change`: `حذف جمله` / `حذف بخش` / `حذف ردیف منبع` / `بازنویسی پیشنهادی` / `خالی‌سازی فیلد`
   - `suggestion`: فقط برای سطح زرد (مرحله ۲ و ۳) پر می‌شود
   - `status`: `would-delete` (dry-run) یا `deleted`/`flagged`/`manual-review`
5. **سطح قرمز خودکار**: همهٔ `level=DELETE` در مرحله‌های ۱،۴،۶،۷،۸ مستقیماً با `SA_AI_Engine::clean_content` حذف می‌شود.
6. **سطح زرد فقط گزارش**: `level=FLAG` (مرحله ۲ و ۳) هیچ تغییری نمی‌دهد؛ فقط در CSV با `suggestion` می‌آید.
7. **پیشنهاد بازنویسی**: مرحله ۲ و ۳ در ستون `suggestion` جملهٔ پیشنهادی و در HTML diff با رنگ زرد نمایش داده می‌شود.
8. **اهواز خاص (مرحله ۹)**: سه مورد جدا در گزارش `reports/cleanup/ahvaz-special-*.csv`:
   - `` ``` `` سرگردان
   - متن بی‌ربط نجف‌آباد/نائین در انتهای اهواز
   - `population=0` (باید خالی شود؛ در dry-run به‌صورت `خالی‌سازی فیلد` گزارش)

## الگوها

`tools/db-cleanup/patterns-cleanup.yaml` — هر مرحله از `docs/CLEANUP.md` یک یا چند regex با `level` و `type_change`. مالک می‌تواند الگو اضافه/کم کند بدون تغییر کد.

## خروجی نمونه (dry-run روی ۳ شهرستان)

```
reports/cleanup/dry-run-20261018-kuhsorkh-kashmar-ahvaz-city.csv
reports/cleanup/ahvaz-special-20261018.csv
reports/cleanup/dry-run-20261018-kuhsorkh-kashmar-ahvaz-city.html  (diff رنگی راست‌به‌چپ)
backups/cleanup-20261018-.../kuhsorkh.json  (فقط در اجرای واقعی)
```

## تست موتور

موتور همان `wp-content/plugins/sa-ai-sentence-cleaner/includes/class-sa-ai-engine.php` است:

```bash
php wp-content/plugins/sa-ai-sentence-cleaner/tests/test-engine.php
```

## بازگردانی

```bash
wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-20261018-142000/ahvaz-city.json
# یا از revision وردپرس: پیشخوان → نوشته → بازبینی‌ها → بازگردانی
```

