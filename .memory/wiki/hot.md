# وضعیت فعلی و قدم بعدی (Hot Memory)

> **قاعدهٔ شروع نشست:** این فایل همیشه باید اول خوانده شود.

## وضعیت فعلی (۲۰۲۶-۱۱-۲۳ / ۱۴۰۵/۰۹/۰۲)
- **وضعیت تولیدی مستقر روی `main` (`e278249`):**
  - قالب فرزند `sarzaminaryan-child` نسخهٔ `2.24.16`
  - استان لرستان (`wp_id=478`, `backup_id=5381`, PR `#129`)
  - استان همدان (`wp_id=628`, `backup_id=5383`, PR `#130`)
  - استان زنجان (`wp_id=54`, `backup_id=5385`, PR `#131`)
  - استان کردستان (`wp_id=393`, PR `#132`, run `45092748328`)
  همگی روی سایت زنده منتشر و با HTTP `200` تأیید شده‌اند.
- **کار فعال در شاخهٔ `arena/d4d14c45-s-a-1` (نسخهٔ `2.24.17` قالب فرزند):**
  - اصلاح انکر تکست کارت‌های لینک‌دار در سه قالب ۱۴۰۵ (`single-province.php`، `single-city.php` و `front-page.php`) با الگوی Stretched Link (`.sa-card-linked` و `a.sa-stretched-link::after` در `assets/css/child.css`):
    - تگ `<a href="...">` فقط روی عنوان کارت (`<h3>` یا `<strong>`) می‌نشیند تا گوگل و صفحه‌خوان‌ها انکر تکست کوتاه و متمرکز (مثلاً «شهرستان ابهر») را بخوانند،
    - و کل مساحت کارت از طریق شبه‌عنصر `::after` روی `.sa-card-linked` (`position: relative; isolation: isolate; inset: 0; z-index: 2`) بدون تغییر ظاهر یا شکستن جست‌وجوی `[data-sa-county-card]` کاملاً کلیک‌پذیر می‌ماند.
  - افزودن گارد بخش ۶ به `tests/test-design-2026.php` و ساخت جاب `tools/wp-agent/jobs/20261123-01-theme-22417-card-anchor-text.json`.

## قدم بعدی مشخص
1. پس از ادغام PR و انتشار خودکار ریلیز `sarzaminaryan-child-v2.24.17` از `release-child-theme.yml` و پاس شدن `build-gates.yml` روی `main`، جاب `tools/wp-agent/jobs/20261123-01-theme-22417-card-anchor-text.json` را اجرا و ظاهر/لینک‌های زنده را در `/`, `/province/zanjan/`, `/province/kurdistan/` و `/city/sanandaj/` راستی‌آزمایی کن.
2. بازسازی استان‌های باقی‌مانده را با همان الگوی ۱۰ بخشی ادامه بده.
