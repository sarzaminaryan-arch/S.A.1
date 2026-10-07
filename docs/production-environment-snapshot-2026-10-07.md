# گزارش snapshot زیرساخت و نسخهٔ تولید — sarzaminaryan.ir

- **وضعیت گزارش‌شده:** Production
- **منبع:** خروجی اطلاعات محیط سایت که مدیر سایت در گفت‌وگو فرستاد؛ این سند از میزبان سایت داده‌برداری زنده نمی‌کند.
- **زمان در خروجی منبع:** `current: 2026-10-07T21:54:59+00:00`؛ `utc-time: Wednesday, 07-Oct-26 21:54:59 UTC`؛ `server-time: 2026-10-08T01:24:58+03:30`
- **طبقه‌بندی:** اطلاعات عملیاتی داخلی؛ شامل نسخه‌ها و مسیرهای سرور است.
- **محرمانگی:** مقدار واقعی refresh token در ورودی ارائه نشده و در این سند نیز ذخیره نشده است؛ فقط وجود آن ثبت شده.

> این سند یک snapshot تاریخ‌دار است، نه تنظیمات زندهٔ سایت. مقادیر زیر همان مقادیری هستند که در گزارش ارسالی آمده‌اند.

## هشدار همگام‌سازی نسخهٔ قالب

گزارش سایت نسخهٔ قالب فرزند فعال را **2.11.43** اعلام می‌کند. هنگام ثبت این یادداشت، نسخهٔ `style.css` در checkout گیتهابِ این مخزن **2.11.26** بود. بنابراین نسخهٔ سورس این checkout با نسخهٔ گزارش‌شدهٔ Production هم‌خوان نیست؛ پیش از انتشار یا عیب‌یابی، اختلاف را با سورس واقعی نسخهٔ 2.11.43 بررسی و همگام کنید. این یادداشت به‌تنهایی اثبات نمی‌کند کد جاری مخزن همان کد نصب‌شده روی سایت است.

## Rank Math SEO

| مورد | مقدار گزارش‌شده |
|---|---|
| نسخه | 1.0.280 |
| نسخهٔ پایگاه‌داده | 1 |
| طرح | Free |
| ماژول‌های فعال | `link-counter`, `analytics`, `seo-analysis`, `sitemap`, `rich-snippet`, `instant-indexing`, `ai-visibility`, `amp`, `llms-txt`, `local-seo`, `content-ai` |
| refresh token | موجود است؛ مقدار توکن ثبت نشده |
| دسترسی Search Console | داده شده |
| `rank_math_404_logs` | پیدا نشد |
| `rank_math_redirections` | پیدا نشد |
| `rank_math_redirections_cache` | پیدا نشد |
| `rank_math_internal_links` | 70 مگابایت |
| `rank_math_internal_meta` | 64 کیلوبایت |
| `rank_math_analytics_gsc` | 80 کیلوبایت |
| `rank_math_analytics_objects` | 192 کیلوبایت |
| `rank_math_analytics_inspections` | 112 کیلوبایت |

## هستهٔ WordPress

| مورد | مقدار گزارش‌شده |
|---|---|
| نسخه | 7.1.3 |
| زبان سایت | `fa_IR` |
| زبان کاربر | `fa_IR` |
| منطقهٔ زمانی | `+03:30` |
| پیوند یکتا | `/%postname%/` |
| HTTPS | فعال (`true`) |
| چندسایتی | غیرفعال (`false`) |
| ثبت‌نام کاربران | غیرفعال (`0`) |
| `blog_public` | `1` |
| وضعیت پیش‌فرض دیدگاه | باز (`open`) |
| نوع محیط در گزارش | `production` |
| تعداد کاربران | 1 |
| ارتباط با WordPress.org | فعال (`true`) |

## قالب فعال و افزونه‌ها

### قالب فرزند فعال

- نام: `Sarzamin Aryan Child` (`sarzaminaryan-child`)
- نسخهٔ گزارش‌شده: `2.11.43`
- نویسنده: محمدرضا لک
- وب‌سایت نویسنده: <https://sarzaminaryan.ir>
- قالب والد: `Sarzamin Aryan` (`sarzaminaryan`)
- مسیر: `/home/haftpair/sarzaminaryan.ir/wp-content/themes/sarzaminaryan-child`
- به‌روزرسانی خودکار: غیرفعال
- ویژگی‌ها: `core-block-patterns`, `widgets-block-editor`, `automatic-feed-links`, `title-tag`, `post-thumbnails`, `customize-selective-refresh-widgets`, `custom-logo`, `html5`, `responsive-embeds`, `align-wide`, `wp-block-styles`, `editor-styles`, `editor-style`, `menus`, `widgets`

### قالب والد

- نام: `Sarzamin Aryan` (`sarzaminaryan`)
- نسخه: `1.1.0`
- نویسنده: محمدرضا لک
- وب‌سایت نویسنده: <https://sarzaminaryan.ir>
- مسیر: `/home/haftpair/sarzaminaryan.ir/wp-content/themes/sarzaminaryan`

### افزونه‌های فعال

- **LiteSpeed Cache** — نسخهٔ `7.9.1`؛ به‌روزرسانی خودکار غیرفعال
- **Rank Math SEO** — نسخهٔ `1.0.280`؛ به‌روزرسانی خودکار فعال

### افزونهٔ Must-Use

- **Mizbanfa One-Click Login** — نسخهٔ `1.0.0`

## سرور و PHP

| مورد | مقدار گزارش‌شده |
|---|---|
| معماری | Linux `4.18.0-553.121.1.lve.el8.x86_64`, `x86_64` |
| وب‌سرور | LiteSpeed |
| نسخهٔ PHP | 8.4.26، 64-bit |
| PHP SAPI | `litespeed` |
| `max_input_variables` | 10000 |
| `time_limit` | 300 ثانیه |
| `memory_limit` | 512M |
| `max_input_time` | 600 ثانیه |
| `upload_max_filesize` | 1024M |
| `php_post_max_size` | 1124M |
| cURL | 7.61.1، OpenSSL/1.1.1k |
| Suhosin | غیرفعال (`false`) |
| Imagick | در دسترس (`true`) |
| OPcache | فعال (`true`) |
| مصرف حافظهٔ OPcache | 92,437,520 از 266,542,624 بایت |
| استفاده از interned strings | 74.03% از 16,777,216 بایت؛ 4,356,920 بایت آزاد |
| نرخ hit در OPcache | 90.32% |
| OPcache پر است؟ | خیر (`false`) |
| پیوندهای یکتای زیبا | فعال (`true`) |
| قوانین اضافی `.htaccess` | فعال (`true`) |
| فایل ایستای `robots.txt` | وجود ندارد (`false`) |

## پایگاه‌داده

| مورد | مقدار گزارش‌شده |
|---|---|
| افزونهٔ PHP | `mysqli` |
| نسخهٔ سرور | `11.4.13-MariaDB-cll-lve-log` |
| نسخهٔ کلاینت | `mysqlnd 8.4.26` |
| `max_allowed_packet` | 268435456 بایت |
| `max_connections` | 720 |

## ثابت‌ها و تنظیمات WordPress

| مورد | مقدار گزارش‌شده |
|---|---|
| `WP_HOME` | تعریف نشده |
| `WP_SITEURL` | تعریف نشده |
| `WP_CONTENT_DIR` | `/home/haftpair/sarzaminaryan.ir/wp-content` |
| `WP_PLUGIN_DIR` | `/home/haftpair/sarzaminaryan.ir/wp-content/plugins` |
| `WP_MEMORY_LIMIT` | 40M |
| `WP_MAX_MEMORY_LIMIT` | 512M |
| `WP_DEBUG` | `false` |
| `WP_DEBUG_DISPLAY` | `true` |
| `WP_DEBUG_LOG` | `false` |
| `SCRIPT_DEBUG` | `false` |
| `WP_CACHE` | `true` |
| `CONCATENATE_SCRIPTS` | تعریف نشده |
| `COMPRESS_SCRIPTS` | تعریف نشده |
| `COMPRESS_CSS` | تعریف نشده |
| `WP_ENVIRONMENT_TYPE` | تعریف نشده |
| `WP_DEVELOPMENT_MODE` | تعریف نشده |
| `DB_CHARSET` | `utf8` |
| `DB_COLLATE` | تعریف نشده |
| `EMPTY_TRASH_DAYS` | 30 روز |

## دسترسی‌پذیری فایل‌ها

| مسیر/مورد | وضعیت گزارش‌شده |
|---|---|
| WordPress | قابل‌نوشتن |
| `wp-content` | قابل‌نوشتن |
| `uploads` | قابل‌نوشتن |
| `plugins` | قابل‌نوشتن |
| `themes` | قابل‌نوشتن |
| `mu-plugins` | قابل‌نوشتن |
| `fonts` | وجود ندارد |

## یادداشت‌های پیگیری

1. اختلاف نسخهٔ قالب فعال در Production (`2.11.43`) با checkout فعلی مخزن (`2.11.26`) باید پیش از تصمیم‌گیری دربارهٔ رفع خطا یا انتشار بررسی شود.
2. خروجی، هم‌زمان `WP_DEBUG=false` و `WP_DEBUG_DISPLAY=true` را گزارش می‌کند؛ این ترکیب طبق همان خروجی ثبت شده و در بازبینی تنظیمات محیط تولید بررسی شود.
3. مسیرهای مطلق سرور و جزئیات نسخه‌ها اطلاعات داخلی‌اند. مقدار محرمانهٔ refresh token در هیچ نسخه‌ای از این سند ذخیره نشده است.
