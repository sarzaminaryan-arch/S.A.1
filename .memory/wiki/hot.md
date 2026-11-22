# وضعیت فعلی و قدم بعدی (Hot Memory)

> **قاعدهٔ شروع نشست:** این فایل همیشه باید اول خوانده شود.

## وضعیت فعلی (۲۰۲۶-۱۱-۲۲ / ۱۴۰۵/۰۹/۰۱)
- **شاخهٔ فعال و آمادهٔ ادغام:** `arena/6347bf14-s-a-1` با عبور کامل از `build-gates.yml` (`45078857776`) شامل نسخهٔ `2.24.16` قالب فرزند و اصلاح `lorestan-478.html` + جاب `tools/wp-agent/jobs/20261122-18-lorestan-478-fix-theme-22416.json`.
- **تغییرات نسخهٔ `2.24.16` (`sarzaminaryan-child`):**
  - بازنویسی `sa_2026_relation_content()` به `sa_2026_unwrap_relation_links()` (`inc/design-2026.php`): جداسازی کامل تگ `<a>` تکراری به‌جای حذف صرف `href` (رفع مشکل متن‌های خط‌زیرین مرده در همهٔ استان‌ها/شهرها/دیدنی‌ها).
  - حذف برچسب «لینک‌سازی افقی» در `single-province.php`، افزودن کلاس `.sa-section-heading` و کوتاه‌سازی تیترهای بخش‌ها (به‌جز `#cta-heading` پس‌زمینهٔ تیره).
  - قواعد CSS با اولویت `body.sa-2026` در `assets/css/child.css`: خط زیرین خوانای فارسی (`text-underline-offset: 6px; text-decoration-skip-ink: none`)، حذف خط زیرین از `#counties-grid`/`#neighbors-section`/غیرلینک‌ها، و تیترهای تک‌خطی موبایل با پس‌زمینهٔ سبز روشن `#E7F5EC`.
  - بازسازی `lorestan-478.html`: حذف ۱۲ باکس تکراری شهرستان از بخش ۴ و جایگزینی با نثر ۳پهنه، `sa-autolink-off` روی استان‌های هم‌مرز (شامل چهارمحال و بختیاری)، و کوتاه‌سازی ۱۰ تیتر `<h2>`.

## قدم بعدی مشخص
1. در شروع نشست بعدی، بند ۳ `AGENTS.md` را اجرا کن (`hot.md` ← `stack.md` ← `bugs.md` ← `patterns.md` / `decisions.md` ← `github-refs.md`) و وضعیت جاب `20261122-18` و صفحهٔ `/province/lorestan/` را بررسی کن.
2. پیش از تغییر در دیگر صفحات استان‌ها (مانند کرمانشاه)، الگوی `sa-autolink-off` برای فهرست استان‌های هم‌مرز و `sa_2026_unwrap_relation_links()` را حفظ کن و هر جاب را فقط پس از سبز شدن `build-gates` روی `main` اجرا کن.
