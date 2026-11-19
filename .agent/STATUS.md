# وضعیت فعلی پروژه

- آخرین commit ادغام‌شده در main: `50373e5` (PR #76).
- تست‌های محلی سبز قبلی (دوباره اجرا نشوند): `tests/test_ai_cleaner.py`، `tools/photos/test_import_photos.py`، `tools/wp-agent/test_job_state_recovery.py` و `tools/wp-agent/test_job_exception_recovery.py`.
- مورد ۳ هنوز باز است: journal محلی روی runner موقت پایدار نیست؛ پیامد نوشتن نامعلوم به بررسی دستی نیاز دارد.
- CI برای آخرین merge به دلیل skip اجرا نشده و وضعیت سبز ندارد؛ CI در GitHub باید به‌طور کامل اجرا شود.
- LAK-15 و LAK-24 برای فاز بعدی باز هستند؛ production آماده اعلام نمی‌شود.

- تست اختصاصی پوشش CI افزوده شد؛ CI خودِ تست‌های بازیابی و محافظت production را نیز اجرا خواهد کرد. وضعیت CI تا اجرای راه دور نامشخص است.

- آزمون تازهٔ `tools/wp-agent/test_write_reconciliation.py`: پنج تست محلی سبز (hash، مغایرت، قطع و خطای استعلام). hash برابر به‌تنهایی اثبات اجرای همان درخواست یا شناسهٔ backup نیست؛ قفل تا بررسی دستی باقی می‌ماند.
