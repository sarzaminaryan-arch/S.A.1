# content/ — خروجی‌های تولیدشده‌ی ایجنت‌های محتوا

هر فایل، خروجی کامل یکی از «Production Prompt»های پوشه‌ی `content-templates/` است (۹ بلوک: ENTITY&SEO · FEATURED IMAGE · ARTICLE · FAQ · SOURCES · SCHEMA DATA · QUALITY CHECK · FACT CHECK · PUBLISH STATUS).

| مسیر | موجودیت | قالب | وضعیت |
|---|---|---|---|
| `provinces/tehran.md` | استان تهران | province.md v1.0 | DRAFT ONLY — منتظر انتشار موجودیت‌های لینک‌شده (پیوست الف) و تصویر شاخص |
| `provinces/east-azerbaijan.md` | استان آذربایجان شرقی | province.md v1.0 | DRAFT ONLY — منتظر انتشار موجودیت‌های لینک‌شده (پیوست الف)، تصویر شاخص و تطبیق داده‌ها با منابع رسمی داخل ایران |

ترتیب تولید استان‌ها: فهرست ثابت `wp-content/themes/sarzaminaryan-child/data/provinces.php` (تهران به‌عنوان نمونه‌ی طلایی زودتر تولید شد؛ از این پس به ترتیب فهرست: آذربایجان شرقی ✅ → آذربایجان غربی → اردبیل → اصفهان → البرز → ایلام → بوشهر → …).

قرارداد نام‌گذاری: `provinces/{slug}.md` · `cities/{slug}.md` · `attractions/{slug}.md` · `foods/{slug}.md` · `souvenirs/{slug}.md` · `routes/{slug}.md` — نامک‌ها همان نامک‌های وردپرس‌اند.

نحوه‌ی انتقال به وردپرس: جدول «نگاشت وردپرس» در بخش ۶ هر Production Prompt.
