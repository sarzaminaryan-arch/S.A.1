# وضعیت نشست جاری (Session State)

- **شاخهٔ فعال:** `arena/76314a55-s-a-1` (از `main` در `782cb91`).
- **فایل‌های ایجادشده برای بازسازی استان همدان (`wp_id=628`):**
  - `tools/wp-agent/outbox/20261122-province-hamadan-628/hamadan-628.html` (۱۰ بخش استاندارد هم‌تراز لرستان، نثر ۳بندی برای ۱۰ شهرستان با `sa-autolink-off`، حفاظت `sa-autolink-off` برای ۶ استان هم‌مرز، ۴ لینک داخلی تأییدشده، ۵ آکاردئون FAQ و ۷ دکمهٔ مستقیم منبع)
  - `tools/wp-agent/outbox/20261122-province-hamadan-628/hamadan-628.meta.json` (`sa_seo_title` اصلاح‌شده به ۱۰ شهرستان، `sa_faq` و `sa_sources` هم‌تراز و با تاریخ ۱۴۰۵/۰۹/۰۱)
  - `tools/wp-agent/jobs/20261122-19-province-hamadan-rebuild.json` (`ping` + `update` + `cache-purge` + `probe` با `rollback_on_fail: true`)
- **نتایج آزمون محلی پیش از پوش:**
  - `fa_lint.py`: `0` خطا روی HTML و Meta.
  - `py_compile` و ۱۲ تست مستقل پایتون (`test_fa_lint`, `test_county_template`, `test_relation_html`, `test_changed_counties`, `test_wp_agent_jobs`, `test_ci_coverage`, `test_workflow_safety`, `test_build_gate`, `test_job_exception_recovery`, `test_job_state_recovery`, `test_write_reconciliation`, `test_audit_remediation`, `test_import_photos`): همگی موفق (`render_contract_fixture.py` نیازمند `php` و `pytest` در محیط `ubuntu-22.04` گیت‌هاب اکشنز اجرا می‌شوند).
- **گام بعدی:** کامیت و پوش به `arena/76314a55-s-a-1`، انتظار برای عبور `build-gates.yml`، بازکردن و ادغام PR به `main`، اجرای `wp-agent.yml` برای جاب `20261122-19-province-hamadan-rebuild.json` و بررسی صفحهٔ زندهٔ `/province/hamadan/`.
