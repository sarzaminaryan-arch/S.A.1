# S.A.1

مخزن ابزارها، قالب فرزند وردپرس و مستندات پروژهٔ سرزمین آریاییان است. تاریخچهٔ تغییرات: [CHANGELOG.md](CHANGELOG.md).

## راهنمای شروع

- پیش از کار، [AGENTS.md](AGENTS.md) و سیاست [نگه‌داری مخزن](docs/repository-retention-policy.md) را بخوانید.
- ارتباط با وردپرس و اجرای کارهای بازبینی‌شده: [راهنمای WP agent](tools/wp-agent/README.md). اجرای production فقط با dispatch دستی روی `main` و پس از گذر از gate همان commit است؛ تا تأیید CI و بررسی ایمنی end-to-end از آن استفاده نکنید.
- پاک‌سازی مقالات و گزینه‌های خط فرمان: [راهنمای ai_cleaner](ai_cleaner/README.md). پیش از اجرای عملیات نوشتن یا انتشار، محدودیت‌ها و وضعیت پیشرفت را بررسی کنید.
- نگه‌داری و بازگردانی پایگاه داده: [راهنمای db-cleanup](tools/db-cleanup/README.md).

## تست‌ها

Python 3.11 یا جدیدتر لازم است. برای نصب وابستگی تست و اجرای آزمون‌های پاک‌کننده:

```sh
python3 -m pip install '.[test]'
python3 -m pytest -q
python3 tools/wp-agent/test_wp_agent_jobs.py
python3 tools/wp-agent/test_workflow_safety.py
python3 tools/wp-agent/test_build_gate.py
python3 tools/wp-agent/test_changed_counties.py
python3 tools/photos/test_import_photos.py
python3 tools/photos/test_release_gate.py
```

تست‌های قالب وردپرس علاوه بر Python به PHP CLI نیاز دارند؛ گردش‌کارهای GitHub Actions مرجع جزئیات اجرای آن‌ها هستند. هرگز اسرار را به مخزن اضافه نکنید و پیش از حذف فایل‌ها یا پشتیبان‌ها سیاست نگه‌داری و تأییدهای لازم را رعایت کنید.
