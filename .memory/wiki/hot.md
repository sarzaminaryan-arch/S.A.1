# وضعیت فعلی و قدم بعدی (Hot Memory)

> **قاعدهٔ شروع نشست:** این فایل همیشه باید اول خوانده شود.

## وضعیت فعلی (۲۰۲۶-۱۱-۲۲ / ۱۴۰۵/۰۹/۰۱)
- **شاخهٔ پایهٔ انتشاریافته (`main`):** `1073146` (پس از ادغام PR `#128` و اجرای موفق گردش‌کار `wp-agent.yml` شمارهٔ `45074604844`).
- **قالب فرزند زنده و منتشرشده (`sarzaminaryan-child`):** نسخهٔ `2.24.15` (ریلیز گیت‌هاب `sarzaminaryan-child-v2.24.15`، آزمایش‌شده و سالم روی سایت زنده). قالب والد `sarzaminaryan` نسخهٔ `1.1.0` کاملاً دست‌نخورده و قفل‌شده با `protect.yml` است.
- **آخرین عملیات زنده:** صفحهٔ استان لرستان (`wp_id=478`, `/province/lorestan/`) با موفقیت بازسازی و منتشر شد (`backup_id: 5379`, `modified_gmt: "2026-11-22 21:54:25"`): شامل ۱۲ باکس شهرستان (`.sa-county-box` در گرید ۳ ستونه دسکتاپ)، ۵ آکاردئون پرسش‌وپاسخ (`<details class="sa-faq-item">`, اولی باز) و ۷ باکس یکتا و قابل‌کلیک منابع (`.sa-source-boxes`, محافظت‌شده با `sa_protect_source_boxes()`).
- **زیرساخت حافظهٔ پایدار (`.memory/`):** در ریشهٔ مخزن ایجاد و با `AGENTS.md` همگام‌سازی شد؛ ۵ منبع کلیدی گیت‌هاب در `github-refs.md` ثبت شده‌اند.
- **عملیات بازِ پیشین مخزن (در `.agent/ROADMAP.md` و `.agent/STATUS.md`):**
  - ردیف ۱ صف شهرستان‌ها (`tehran`، شناسهٔ ۲۱۳) و ردیف ۲ (`mashhad`، شناسهٔ ۶۳): پیش‌نویس‌ها در `tools/wp-agent/replace_src/data/` بازسازی شده‌اند، اما انتشار دستی/نصب زنده تابع دستورات `.agent/RULES.md` و تأیید مسیر اجرایی ایمن است.
  - بخش `sa_2026` و قالب‌های فردی (`front-page.php`, `single-province.php`, `single-city.php`, `single-attraction.php`, `archive-guide.php`) فعال‌اند و همهٔ گاردهای آفلاین CI (`build-gates.yml`) و تست‌های PHP قالب پوشش داده شده‌اند.

## قدم بعدی مشخص
1. در شروع نشست بعدی، طبق بند ۳ `AGENTS.md` فایل‌های `hot.md` ← `stack.md` ← `bugs.md` ← `patterns.md` / `decisions.md` ← `github-refs.md` خوانده و در `.memory/session/current.md` خلاصه شوند.
2. بر اساس دستور بعدی مالک (یا طراحی/بهینه‌سازی ظاهر، RTL، CWV، سئوی فنی قالب فرزند، یا ادامهٔ صف مقالات/استان‌ها):
   - پیش از هر تغییر قالب: بررسی الگوهای `patterns.md` و `bugs.md`، به‌روزرسانی هم‌زمان نسخه در `style.css` و `SA_CHILD_VERSION` (`functions.php`)، و اجرای کامل تست‌های PHP در `wp-content/themes/sarzaminaryan-child/tests/` و `build-gates`.
   - هر جاب زندهٔ وردپرس فقط پس از ادغام به `main` و با اجرای صریح `wp-agent.yml` (`input: job=...`) همراه پشتیبان خودکار و `cache-purge` + `probe` انجام شود.
