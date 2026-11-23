# وضعیت نشست جاری (Session State)

- **شاخهٔ فعال:** `arena/d4d14c45-s-a-1` (از `main` در `e278249`).
- **تغییرات انجام‌شده برای نسخهٔ `2.24.17` قالب فرزند (`sarzaminaryan-child`):**
  - متمرکزکردن انکر تکست کارت‌های لینک‌دار با الگوی Stretched Link (`.sa-card-linked` + `a.sa-stretched-link::after` با `isolation: isolate; inset: 0; z-index: 2` در `assets/css/child.css`):
    - `single-province.php`: گرید `#counties-grid` (`<article data-sa-county-card>` و `<a data-sa-relation class="sa-stretched-link">` صرفاً روی عنوان `<h3>`).
    - `single-city.php`: گرید `#neighbor-counties-section` (`<article class="sa-card-linked">` و `<a data-sa-relation class="sa-stretched-link">` صرفاً روی عنوان `<h3>`).
    - `front-page.php`: کارت استان‌ها و کارت تازه‌ترین به‌روزرسانی‌ها.
  - به‌روزرسانی نسخه در `style.css` و `functions.php` به `2.24.17`، ثبت در `CHANGELOG.md`، افزودن گارد بخش ۶ به `tests/test-design-2026.php` و ساخت جاب `tools/wp-agent/jobs/20261123-01-theme-22417-card-anchor-text.json`.
- **گام بعدی:** پوش به `arena/d4d14c45-s-a-1`، عبور از `build-gates.yml`، بازکردن و ادغام PR به `main`، صبر برای تکمیل `build-gates.yml` و `release-child-theme.yml` روی `main`، اجرای `wp-agent.yml` برای جاب `20261123-01-theme-22417-card-anchor-text.json` و بررسی صفحهٔ زنده.
