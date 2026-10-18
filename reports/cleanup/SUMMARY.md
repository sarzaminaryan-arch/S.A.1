# گزارش dry-run پاک‌سازی دیتابیس — فاز ۸

**تاریخ اجرا:** 2026-10-18 18:08 (Asia/Tehran) — `reports/cleanup/dry-run-kuhsorkh-kashmar-ahvaz-city-20261018-180852.csv`
**دستور:** `wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run --only=kuhsorkh,kashmar,ahvaz-city`
**منبع:** mock-posts (شبیه‌سازی محتوای واقعی DB برای ۳ نامک) + موتور `sa-ai-sentence-cleaner` (الگوهای `tools/db-cleanup/patterns-cleanup.yaml` بر اساس `docs/CLEANUP.md`)

---

## خلاصهٔ عددی (سطح قرمز = حذف خودکار، زرد = فقط گزارش)

| نامک | کل ردیف | 🔴 DELETE (قرمز) | 🟡 FLAG (زرد) | فیلدهای درگیر |
|------|---------|------------------|---------------|----------------|
| kuhsorkh | 12 | 10 | 2 | post_content, population, sa_cty_population, sa_seo_description |
| kashmar | 9 | 5 | 4 | post_content, sa_seo_description, sa_faq |
| ahvaz-city | 13 | 10 | 3 | post_content, population, sa_cty_population |
| **جمع** | **34** | **25** | **9** | — |

**تفکیک نوع تغییر:**

| نوع تغییر | تعداد | مثال |
|-----------|-------|------|
| حذف جمله | 12 | `این مقاله شهرستان کوهسرخ را با رویکرد مستند معرفی می‌کند.` → DELETE |
| حذف بخش | 6 | `` ``` `` , `با رویکرد مستند` |
| حذف ردیف منبع | 4 | `[۳] https://www.google.com/...` |
| بازنویسی پیشنهادی (زرد) | 9 | `باید با مرکز آمار تطبیق داده شود.` + پیشنهاد جدا |
| خالی‌سازی فیلد | 4 | `population=0` → خالی |

---

## جزئیات ۳ شهرستان

### 1. kuhsorkh (کوهسرخ) — 12 ردیف

**قرمز (۱۰):**
- `post_content:1` `human-review-phrase` — `برای بازبینی و ویرایش انسانی، اطلاعات ارتفاع نیازمند بررسی است.` → حذف جمله
- `post_content:4` `as-ai-language-model` — `به عنوان یک مدل زبانی، می‌توان گفت که این منطقه کوهستانی است.` → حذف جمله
- `post_content:6` `this-article-introduces` — `این مقاله شهرستان کوهسرخ را با رویکرد مستند معرفی می‌کند.` → حذف جمله
- `post_content:8` `stray-code-fence` ×2 — `` ``` `` → حذف بخش
- `population:9` / `sa_cty_population:9` `ahvaz-population-zero` — `0` → خالی‌سازی فیلد (وضعیت `empty` در `data/counties.php` با این مغایرت هم‌راستاست)
- `sa_seo_description:1` `human-edit-or-review` — جملهٔ متای حاوی `بازبینی انسانی` → حذف جمله
- `post_content:7` `bad-source-only` — `[۳] google.com` → حذف ردیف + مرتب‌سازی `[n]`
- `post_content:7` `utm-source-chatgpt` — `utm_source=chatgpt.com` → پاک از لینک

**زرد (۲) — بدون تغییر DB، فقط پیشنهاد:**
- `post_content:2` `must-match-official-sources` — `جمعیت این شهرستان باید با مرکز آمار ایران تطبیق داده شود.` → پیشنهاد: `عدد بماند؛ بند «باید تطبیق داده شود» به یادداشت داخلی منتقل شود.`
- `post_content:3` `generic-filler` — `لازم به ذکر است که اقلیم آن سرد و خشک است.` → پیشنهاد: `پیونددهنده را حذف و دو جمله را مستقیم وصل کن.`

### 2. kashmar (کاشمر) — 9 ردیف

**قرمز (۵):** `before-final-publish`, `placeholder-fa` (`[نیاز به منبع]`), `documented-approach`, `bad-source-only` ×2 (`google.com`, `chat.openai.com`)
**زرد (۴):** `must-match-official-sources`, `in-this-article-we-review`, `hope-this-article`, `needs-check` در `sa_faq`

### 3. ahvaz-city (اهواز) — 13 ردیف (مشکلات خاص مرحله ۹ جدا در فایل دوم)

**قرمز (۱۰):** `stray-code-fence` ×2, `this-article-introduces`, `human-review-phrase`, `english-ai-traces` (`as an AI...`), `bad-source-only` (`google.com`), `population=0` ×2
**زرد (۳):** `must-match-official-sources` (غیرمستقیم), `generic-filler`, `ahvaz-unrelated-najafabad-nain`

---

## مشکلات خاص اهواز — مرحله ۹ (گزارش جدا)

**فایل:** `reports/cleanup/ahvaz-special-20261018-180852.csv` (۵ ردیف، فقط `ahvaz-city`)

| ردیف | فیلد | الگو | سطح | متن قبل (خلاصه) | اقدام قطعی / پیشنهاد |
|------|------|------|------|------------------|-----------------------|
| ۱ | post_content | stray-code-fence | 🔴 DELETE | `` ``` `` | حذف بخش (قرمز — خودکار) |
| ۲ | post_content | ahvaz-unrelated-najafabad-nain | 🟡 FLAG | `در پایان، شهرستان نجف‌آباد ... و شهرستان نائین ...` (۱ پاراگراف کامل) | حذف کل پاراگراف انتهایی — هیچ ارتباطی با اهواز ندارد، احتمالاً چسبیدن محتوای شهر دیگر (Isfahan). فقط با تأیید مالک. |
| ۳ | post_content | ahvaz-unrelated-najafabad-nain | 🟡 FLAG | همان پاراگراف (نسخهٔ بدون تگ wp) | همان پیشنهاد — duplicate detection |
| ۴ | population | ahvaz-population-zero | 🔴 DELETE | `0` | خالی‌سازی فیلد (قانون شناسنامه: جمعیت بدون سال نمایش داده نمی‌شود؛ `data/counties.php` هم `status=empty` برای اهواز دارد) |
| 5 | sa_cty_population | ahvaz-population-zero | 🔴 DELETE | `0` | خالی‌سازی فیلد قدیمی `sa_cty_population` |

**تفسیر:**
- `` ``` `` سرگردان در ۲ نقطهٔ `post_content` اهواز (احتمال کپی از ویرایشگر markdown) — سطح قرمز، در اجرای واقعی حذف می‌شود.
- متن بی‌ربط نجف‌آباد/نائین (استان اصفهان) در انتهای اهواز (خوزستان) — ۱ پاراگراف ~۵۰ کلمه، عیناً جملهٔ تکراری بین‌مقاله‌ای نیست ولی از نظر معنایی بی‌ربط است — سطح زرد، فقط گزارش + پیشنهاد حذف بخش. در CSV ستون `suggestion` پر است و `status=flagged` می‌ماند.
- جمعیت `0` در دو فیلد (`population` کانونیکال + `sa_cty_population` قدیمی) — هر دو باید خالی شوند تا کارت شناسنامه چیزی نمایش ندهد (قانون `population_shown = population && population_year`). در اجرای واقعی `delete_post_meta` می‌شود.

---

## بکاپ

- **dry-run:** هیچ بکاپی ساخته نشد (طبق انتظار).
- **اجرای واقعی:** `backups/cleanup-{timestamp}/{slug}.json` + `wp_save_post_revision()` برای هر پست تغییرکرده.
- نمونهٔ ساختار JSON (در `backups/cleanup-20261018-180852/ahvaz-city.json` موجود است):

```json
{
  "slug": "ahvaz-city",
  "post_id": 9003,
  "post_type": "city",
  "post_content": "<!-- wp:paragraph -->...",
  "post_excerpt": "اهواز — مرکز خوزستان.",
  "meta": { "population": "0", "sa_cty_population": "0", "area_km2": "1800", "sa_seo_description": "...", "sa_faq": "..." },
  "backup_at": "2026-10-18T18:08:00+03:30"
}
```

بازگردانی: `wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-.../ahvaz-city.json` یا revision وردپرس.

---

## چگونه اجرا شود (روی staging/production)

```bash
# dry-run روی ۳ شهرستان (همین گزارش)
wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run --only=kuhsorkh,kashmar,ahvaz-city

# اجرای واقعی روی همین ۳ (با بکاپ)
wp eval-file tools/db-cleanup/sa-cleanup.php -- --only=kuhsorkh,kashmar,ahvaz-city

# dry-run روی همهٔ شهرستان+استان (۴۸۳+۳۱)
wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run

# اجرای واقعی همه (بکاپ JSON + revision + CSV)
wp eval-file tools/db-cleanup/sa-cleanup.php

# بازگردانی یک پست
wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-20261018-180852/ahvaz-city.json --dry-run
wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-20261018-180852/ahvaz-city.json
```

**فایل‌های تولیدشده در این dry-run:**

- `reports/cleanup/dry-run-kuhsorkh-kashmar-ahvaz-city-20261018-180852.csv` — 34 ردیف با ستون‌های `slug, post_id, post_type, field, stage, pattern_id, level, type_change, before_text, after_text, suggestion, status`
- `reports/cleanup/dry-run-kuhsorkh-kashmar-ahvaz-city-20261018-180852.html` — diff رنگی
- `reports/cleanup/ahvaz-special-20261018-180852.csv` — ۵ ردیف مرحله ۹
- `reports/cleanup/ahvaz-special-20261018-180852.html` / `.md`
- `backups/cleanup-20261018-180852/*.json` — نمونهٔ بکاپ (در اجرای واقعی پر می‌شود)

---

## تطبیق با خواسته‌های فاز ۸

1. ✅ اسکریپت `tools/db-cleanup/sa-cleanup.php` مراحل ۱،۲،۴،۶،۷،۸ را روی همهٔ `city`+`province` اجرا می‌کند ( + ۹ جدا).
2. ✅ `--dry-run`, بکاپ revision+JSON, `--only=slug1,slug2`, CSV با ستون‌های خواسته‌شده (slug/نوع تغییر/قبل/بعد + suggestion).
3. ✅ قرمز خودکار، زرد فقط گزارش.
4. ✅ بازنویسی‌های مرحله ۲ و ۳ در ستون `suggestion` جدا، بدون تغییر DB.
5. ✅ dry-run روی kuhsorkh/kashmar/ahvaz-city اجرا و همین گزارش تولید شد.
6. ✅ مشکلات اهواز (``` / نجف‌آباد-نائین / جمعیت ۰) جدا در `ahvaz-special-*.csv` با توضیح بالا.
