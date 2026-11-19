# وضعیت فعلی پروژه

- آخرین commit شناخته‌شدهٔ `main` هنگام ثبت این وضعیت: `50373e5` (پیش از ادغام PR #77).
- CI در GitHub برای commit `6eea22f` در PR #77 سبز است؛ برای هر commit جدید CI باید جداگانه سبز شود.
- تست‌های محلی سبز قبلی (دوباره اجرا نشوند): `tests/test_ai_cleaner.py`، `tools/photos/test_import_photos.py`، `tools/wp-agent/test_job_state_recovery.py`، `tools/wp-agent/test_job_exception_recovery.py` و `tools/wp-agent/test_write_reconciliation.py`.
- گام بعدی: LAK-15.
- سیاست production فقط اجرای دستی از main است؛ runner میزبانی‌شدهٔ فعلی به دلیل نبود state پایدار اجرای کار، حتی دستی، را نیز فعلاً مسدود می‌کند. نوشتن با نتیجهٔ نامعلوم نیازمند بررسی دستی است.
