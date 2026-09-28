=== سرزمین آریان — درون‌ریز ۵ استان ویژه (کردستان، کرمانشاه، کهگیلویه و بویراحمد، گلستان، گیلان) ===
Contributors: sarzaminaryan
Tags: provinces, iran, importer, kurdistan, kermanshah, kohgiluyeh, golestan, gilan
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

درون‌ریز اختصاصی ۵ استان منتخب (کردستان، کرمانشاه، کهگیلویه و بویراحمد، گلستان، گیلان) همراه با ۶۴ شهرستان رسمی و اسلاگ‌های انگلیسی.

== توضیحات ==

این افزونه امکان درون‌ریزی ۵ استان کامل را با ساختار شیوه‌نامه نگارش نسخه ۱.۲ فراهم می‌سازد:
- استان کردستان (kurdistan) با ۱۰ شهرستان
- استان کرمانشاه (kermanshah) با ۱۴ شهرستان
- استان کهگیلویه و بویراحمد (kohgiluyeh-boyer-ahmad) با ۹ شهرستان
- استان گلستان (golestan) با ۱۴ شهرستان
- استان گیلان (gilan) با ۱۷ شهرستان

== نصب ==

۱. پوشه sa-province-importer-5provinces را در wp-content/plugins بارگذاری و در پیشخوان وردپرس فعال کنید.
۲. به مسیر «ابزارها ← درون‌ریزی استان‌ها» بروید یا با WP-CLI دستور زیر را اجرا کنید:
   `wp sa-province import --batch=pack5`
۳. برای ساخت پیش‌نویس ۶۴ شهرستان:
   `wp sa-province counties --batch=pack5`
