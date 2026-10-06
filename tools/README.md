# ابزارهای توسعه و تست (بخشی از قالب نیست)

این پوشه فقط برای توسعه و کنترلِ کیفیت است و در بستهٔ نصبیِ قالب (زیپِ منتشرشده) قرار نمی‌گیرد.

## ۱. اجرای PHP بدون نیاز به نصبِ PHP روی سیستم

`tools/phpwasm` یک اجراکنندهٔ سبک بر پایهٔ [php-wasm](https://www.npmjs.com/package/@php-wasm/node) است که PHP 8.1 و 7.4 را داخل Node اجرا می‌کند.

```bash
cd tools/phpwasm
sh setup.sh                 # فقط بار اول: نصب node_modules (در مخزن نگه‌داری نمی‌شود)
```

بررسیِ نحو (lint) همهٔ فایل‌های PHP قالب روی هر دو نسخه:

```bash
cd /ریشهٔ-مخزن
node tools/phpwasm/lint.js $(find wp-content/themes/sarzaminaryan-child -name '*.php' | sort)
PHP_VER=7.4 node tools/phpwasm/lint.js $(find wp-content/themes/sarzaminaryan-child -name '*.php' | sort)
```

اجرای یک اسکریپتِ PHP دلخواه با شبیه‌سازِ وردپرس:

```bash
node tools/phpwasm/exec.js tools/sa-tests/run-bot.php
```

`exec.js` هنگام اجرا:

- پوشه‌های `data/` و `inc/` قالب را به شکل只读 در فایل‌سیستمِ مجازیِ PHP کپی می‌کند،
- اسکریپت و همهٔ فایل‌های کناری‌اش (مثل `wp-stubs.php` و `assert.php`) را هم کپی می‌کند،
- متغیر محیطی `SA_CHILD_DIR` را برای اسکریپت تنظیم می‌کند.

متغیرهای محیطیِ مفید: `PHP_VER` (پیش‌فرض `8.1`) و `SA_THEME_DIR` (مسیر قالب؛ پیش‌فرض نسبت به ریشهٔ مخزن).

## ۲. تست‌ها

| فایل | چه چیزی را تست می‌کند |
| --- | --- |
| `tools/sa-tests/run-bot.php` | ربات راهنمای تلگرام: تنظیمات، ارتباط با Bot API، خلاصه‌ها، صفحه‌بندی، مسیرِ استان←شهر←دیدنی، جستجو، وب‌هوک (۴۰۳/۵۰۳/۲۰۰)، تنظیم وب‌هوک، پاک‌سازیِ ورودی و رندرِ صفحهٔ مدیریت |
| `tools/sa-tests/run-social.php` | انتشار خودکار: استخراج تصویر، ساخت متن، ارسال تلگرام/اینستاگرام، شرط‌ها و زمان‌بندی، **ارسالِ انبوه** و رندرِ صفحهٔ مدیریت |
| `tools/sa-tests/wp-stubs.php` | شبیه‌سازِ وردپرس (گزینه‌ها، متا، نوشته‌ها، ترنزینت‌ها، رویدادهای زمان‌بندی‌شده، درخواست‌های HTTP، REST، قلاب‌ها، توابعِ پیشخوان) |
| `tools/sa-tests/assert.php` | توابعِ سادهٔ ادعا و چاپِ خلاصه |

اجرا:

```bash
node     tools/phpwasm/exec.js tools/sa-tests/run-bot.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-bot.php
node     tools/phpwasm/exec.js tools/sa-tests/run-social.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-social.php
```

خروجیِ پایانی به‌شکل `PASS: 113   FAIL: 0` است و در صورت شکست، کدِ خروج `۱` برمی‌گردد.

نکته: در تست‌ها **هر اخطار یا نوتیسِ PHP به خطا تبدیل می‌شود** (`set_error_handler` در ابتدای هر فایل تست)، بنابراین «بدون خطا اجرا شدن» واقعاً بررسی می‌شود.
