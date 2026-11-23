# وضعیت نشست جاری (Session State)

- **شاخهٔ فعال:** `arena/7b589faf-s-a-1` (از `main` در `8c00634`).
- **فایل‌های ایجادشده برای بازسازی استان کردستان (`wp_id=393`):**
  - `tools/wp-agent/outbox/20261122-province-kurdistan-393/kurdistan-393.html`
  - `tools/wp-agent/outbox/20261122-province-kurdistan-393/kurdistan-393.meta.json`
  - `tools/wp-agent/jobs/20261122-21-province-kurdistan-rebuild.json`
- **گام بعدی:** اجرای آزمون‌های محلی و `fa_lint.py`، پوش به `arena/7b589faf-s-a-1`، عبور از `build-gates.yml`، بازکردن و ادغام PR به `main`، اجرای `wp-agent.yml` برای جاب `20261122-21-province-kurdistan-rebuild.json` و بررسی زندهٔ `/province/kurdistan/`.
