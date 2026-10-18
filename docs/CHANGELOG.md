# 📝 تاریخچه تغییرات

> ثبت دقیق تغییرات برای آگاهی Agentهای آینده
> این فایل فقط «تاریخچهٔ جلساتِ نگه‌داری و مستندسازی» است؛ تاریخچهٔ نسخه‌های قالب در
> `wp-content/themes/sarzaminaryan-child/CHANGELOG.md` و تاریخچهٔ مدل داده در
> `data-model/CHANGELOG.md` نگه داشته می‌شود.

---

### ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) — Session مستندسازی و زیرساخت (Session #0)

- **Agent:** ایجاد ساختار هوشمند مستندات

- **Branch:** `arena/6b44d15d-s-a-1`

- **تغییرات:**

  - ایجاد و تکمیل فایل‌های پایه (`CLAUDE.md`, `PROJECT_STATUS.md`, `ARCHITECTURE.md`, `AGENT_RULES.md`)

  - استخراج آمار دقیق مخزن (۶۳۹ تابع فرزند، ۶ CPT، شناسایی ۱۵ مشکل ساختاری)

  - حذف فایل‌های موازی (مثل `01-ARCHITECTURE.md`)

- **نتیجه:** ✅ موفق و پایدار

**جزئیات اجرا**

| مرحله | خروجی | کامیت |
|---|---|---|
| ۱ — اسکن کامل مخزن | نقشهٔ درختی (۶۸۶ فایل / ۷۴ پوشه)، ۳۰ کامیت آخر، بررسی والد/فرزند، فهرست پلاگین‌ها و ۱۵ مشکل | — (فقط خواندن) |
| ۲ — ساختار مستندات | `docs/project/` شامل ۱۳ سند + ایندکس | `2405272` |
| ۳ — فایل هوش پروژه | `CLAUDE.md` بازنویسی‌شده (۱۲ بخش با دادهٔ واقعی) | `8e188fa` |
| ۴ — گزارش وضعیت | `PROJECT_STATUS.md` (ارزیابی 🟡 + جدول ۱۵ مشکل + ۲۰ فایل کلیدی) | `551c0f8` |
| ۵ — معماری و قوانین | `ARCHITECTURE.md` و `AGENT_RULES.md` | `29a6bb8` و `2cd3e30` |
| ۵ — حذف افزونگی | حذف `docs/project/01-ARCHITECTURE.md` و ارجاع ایندکس به فایل ریشه | `f629e66` |
| ۶ — رهگیری و سنجش | `docs/CHANGELOG.md`، `docs/KNOWN_ISSUES.md`، `docs/DECISIONS.md`، `docs/AGENT_TEST.md` | یک کامیت یکجا |

---

### ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) — Session #0 · بخش پایانی (ادغام و ممیزی اتصالات)

- **Agent:** تکمیل حلقهٔ مستندسازی و آماده‌سازی زیرساخت

- **Branch:** `arena/6b44d15d-s-a-1` · **PR:** [#26](https://github.com/sarzaminaryan-arch/S.A.1/pull/26) (OPEN · MERGEABLE)

- **تغییرات:**

  - خروجِ `imagetool (23).zip` از ردیابی گیت با `git rm --cached` (فایل فیزیکی باقی ماند) — کامیت `ed1ccb6`

  - ایجاد `TESTING_CHECKLIST.md` در ریشه و `docs/START_PROMPT.txt` برای ایجنت‌های آینده

  - **ممیزی اتصالات خارجی:** بررسی ۶۸۶ فایل برای `notion` · `linear` · `drive.google` · `docs.google` · `webhook` · `api_key`
    → نتیجه: **هیچ اتصال کدی به Notion / Linear / Google Drive / Google Docs وجود ندارد**؛
    گزارش کامل در `docs/CONNECTORS_AUDIT.md`، و دو موردِ کم‌اهمیت با شماره‌های ۱۶ و ۱۷ به `KNOWN_ISSUES.md` افزوده شد

  - به‌روزرسانی `PROJECT_STATUS.md`، `CLAUDE.md` و `KNOWN_ISSUES.md` با وضعیتِ پایانِ جلسه

- **نتیجه:** ✅ موفق — ۹ کامیت پوش‌شده روی شاخه؛ ادغام در `main` توسط مالک (یک کلیک روی PR #26)

**نکتهٔ مهم برای جلسهٔ بعد:** ایجنت در این محیط **اجازهٔ push روی `main` و `develop` ندارد**؛
بنابراین ادغام و ساخت شاخهٔ `develop` باید توسط مالک انجام شود (دستورات در `docs/GITHUB_SETUP.md`).

---

**نکتهٔ فنی جلسه:** کلون اولیه shallow (عمق ۱) بود و فقط یک کامیت دیده می‌شد؛ با
`git fetch --deepen=60` تاریخچه تا ۲۶۷ کامیت بازیابی شد و گزارشِ مرحلهٔ ۱ روی آن بنا شد.
همچنین شمارش دوبارهٔ توابع نشان داد عدد واقعی **۶۳۹** تابع با پیشوند `sa_` است (از ۷۳۰ تابع کل فرزند).

---

### ۱۴۰۵/۰۷/۲۸ (۲۰۲۶-۱۰-۱۸) — فاز ۷-۹: SEO/FAQ، پاک‌سازی دیتابیس، تست استیجینگ

- **Agent:** Arena Agent — شاخه `arena/55424780-s-a-1` → `main` (مرج `2e3c987`)
- **نسخهٔ قالب:** **2.22.19** (همگام در `style.css` / `functions.php` / `readme.txt` — بامپ 2.22.16 → 2.22.17 → 2.22.19)
- **فاز ۷ (۶ شرط):** فیلتر `sa_is_valid_faq_answer`/`sa_get_valid_faq` (خالی/نیازمند/تطبیق/در بخش+آمده/باید+بررسی شود)، FAQPage دقیقاً رندرشده، عنوان `شهرستان [نام]؛ راهنمای سفر، جاذبه‌ها و تاریخ [استان] | سرزمین آریان` + توضیح ۱۵۵ از لید، Breadcrumb خانه→استان→شهرستان، `TouristDestination/Place` با `geo/hasMap`، `noindex,follow` + خروج از sitemap برای <۳۰۰ کلمه/بدون جاذبه، برند واحد «سرزمین آریان»
- **فاز ۸ (مراحل 1,2,4,6,7,8 و ۹ اهواز):** اسکریپت `tools/db-cleanup/sa-cleanup.php` با `--dry-run`/`--only`/`--backup-dir`/`--csv` + `patterns-cleanup.yaml` (قرمز خودکار/زرد فقط گزارش + ستون `suggestion` برای مرحله ۲و۳)؛ dry-run روی `kuhsorkh,kashmar,ahvaz-city` = ۳۴ ردیف (۲۵ DELETE/۹ FLAG) و گزارش جدا اهواز (5 ردیف ```/نجف‌آباد-نائین/جمعیت۰)؛ `docs/CLEANUP.md` ۹ مرحله، `backups/` نمونه JSON، `restore.php`
- **فاز ۹ (تست استیجینگ):** چک‌لیست ۱۰گانه برای ۵ نمونه (kuhsorkh/kashmar/ahvaz-city/mashhad/alborz) — ترتیب بخش‌ها، بدون قرمز، بدون ۰/نیازمند/تطبیق در شناسنامه، اعداد یکسان، بدون «به‌زودی»/«»»»، H1 واحد، FAQPage همگام (Rich Results Test OK)، رتبه <۵ مخفی، موبایل، Lighthouse بدون افت؛ اسکرین‌شات قبل/بعد در `reports/cleanup/screenshots/`؛ اجرای کامل روی همه (۵۱۴ پست → ۴۱۲ ردیف، ۲۳ noindex) + `sitemap-update.md` و `noindex-list.csv`; نسخه 2.22.19 + changelog دقیق در همه ثبت‌ها
- **فایل‌های کلیدی:** `inc/helpers.php` (sa_is_valid_faq_answer/sa_get_valid_faq/sa_city_is_thin…), `inc/seo.php` (sa_brand_name/sa_seo_title برای شهرستان/155/robots/sitemap), `inc/schema.php` (Organization/WebSite با برند + FAQPage فیلترشده + BreadcrumbList شهرستان), `inc/breadcrumbs.php` (خانه→استان→شهرستان), `template-parts/entity/faq.php`, `tools/db-cleanup/*`, `reports/cleanup/*`, `docs/CLEANUP.md`, `docs/phase9-checklist.md`
- **نتیجه:** ✅ همهٔ تغییرها روی استیجینگ تست، هیچ رگرسیون، sitemap به ۵۸۹ URL به‌روز، ZIP `sarzaminaryan-child-v2.22.19.zip` در گوگل‌درایو + Release v2.22.19

---
