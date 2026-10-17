# 🔐 موجودیِ ممیزی امنیتی (فاز صفر — تحلیل ایستا)

> تاریخ: ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) · شاخه: `arena/6b44d15d-s-a-1`
> دامنه: **فقط تحلیل** — هیچ فایل کدی تغییر نکرده است. این سند «نقشهٔ راه» برای شاخهٔ `fix/security-audit` است.
> ⚠️ ** روش‌شناسی:** شمارش‌ها با تحلیلِ الگوی متنی (grep/Regex) روی ۱۳۶ فایل PHP قالب فرزند به دست آمده‌اند.
> PHP و ابزار تحلیل ایستا (PHPCS/PHPStan) در این محیط در دسترس نیستند، بنابراین این‌ها **نشانگر برای بازبینی دستی** هستند،
> نه حکم قطعی. هر مورد باید روی staging با `WP_DEBUG` تأیید شود.

---

## ۱. تصحیحِ عددهای گزارش‌شده در مرحلهٔ ۱

عددهای مرحلهٔ ۱ بر پایهٔ شمارشِ «خطوط» بودند؛ شمارش دقیق‌ترِ این مرحله (بر پایهٔ رخدادها) متفاوت است:

| شاخص | عددِ مرحلهٔ ۱ | **عدد دقیق** | توضیح |
|---|---|---|---|
| نقاط nonce/capability | «۳۷ نقطه» | **۹۸ رخداد در ۲۶ فایل** | ۳۹ رخدادِ nonce/CSRF (۱۳ × `wp_verify_nonce`، ۲۴ × `check_admin_referer`، ۲ × `wp_create_nonce`، ۰ × `check_ajax_referer`) + ۵۹ × `current_user_can` |
| فایل‌های دارای `$wpdb` | «۴۰ فایل» | **۱۰ فایل / ۶۵ فراخوانی** (۱۴ مورد `prepare`) | عدد ۴۰ تعدادِ *خطوط* بود |
| فراخوانی‌های `wp_remote_*` | «۲۵» | **۲۶ فراخوانی در ۷ فایل** | ۶ فایلِ قالب + ۱ فایل تست |
| superglobalها | «۱۱۴» | **۱۸۸ رخداد در ۲۵ فایل** | |

> اعداد قدیمی در `PROJECT_STATUS.md` و `CLAUDE.md` در همین مرحله اصلاح شدند.

---

## ۲. موجودیِ سطحِ حمله

### ۲-۱. مسیرهای نوشتنِ `admin_post_*` — ۲۳ هندلر در ۹ فایل

| فایل | هندلرها | `nopriv` (عمومی) | nonce | capability |
|---|---:|---:|---:|---:|
| `inc/social-publish.php` | ۴ | ۰ | ۵ | ۶ |
| `inc/media-restore.php` | ۳ | ۰ | ۴ | ۴ |
| `inc/site-cleanup.php` | ۳ | ۰ | ۲ | ۵ |
| `inc/telegram-bot.php` | ۳ | ۰ | ۳ | ۴ |
| `inc/city-contrib/includes/class-cc-contrib.php` | ۲ | **۱** | ۱ | ۱ |
| `inc/contact-form.php` | ۲ | **۱** | ۱ | ۰ |
| `inc/content-repair.php` | ۲ | ۰ | ۳ | ۶ |
| `inc/github-updater.php` | ۲ | ۰ | ۲ | ۳ |
| `inc/ready-articles.php` | ۲ | ۰ | ۲ | ۸ |

**دو هندلرِ عمومی (`nopriv`)** — هر دو باید nonce داشته باشند (فرم تماس دارد؛ `cc_review_submission` نیازمند تأیید دستی است).

### ۲-۲. مسیرهای REST — ۱۱ مسیر در ۴ فایل

| مسیر | متد | permission_callback | ارزیابی |
|---|---|---|---|
| `cc/v1/auth/request` | POST | `__return_true` | عمومی — **نرخ‌محدودیت دارد** (۳ در ساعت به‌ازای شماره+IP) ✅ |
| `cc/v1/auth/verify` | POST | `__return_true` | عمومی — کد هش‌شده با `wp_hash_password`، اعتبار ۲ دقیقه ✅ |
| `cc/v1/cities/{id}/rating` | GET | `__return_true` | خواندن عمومی — قابل قبول |
| `cc/v1/cities/{id}/rating` | **POST** | `__return_true` | ⚠️ **نوشتنِ عمومی بدون احراز هویت** — نرخ‌محدودیت/جلوگیری از تکرار تأیید نشد |
| `cc/v1/submissions` | POST | Closure (`has_submission_permission`) | ✅ دارای نگهبان |
| `cc/v1/submissions/{id}/resubmit` | POST | Closure (`has_permission`) | ✅ دارای نگهبان |
| `cc/v1/my-submissions` | GET | Closure (`has_permission`) | ✅ دارای نگهبان |
| `cc/v1/leaderboard` | GET | Closure | خواندن — بررسی شود |
| `cc/v1/ping` | GET | `__return_true` | ⚠️ **افشای اطلاعات**: برمی‌گرداند `PHP_VERSION` و وجودِ جدول دیتابیس |
| `sa/v1/ready-articles/cover` | POST | Closure (`edit_posts` + `upload_files`) | ✅ دارای capability |
| `sa/v1/telegram-bot/{secret}` | — | `__return_true` | وب‌هوک تلگرام — راز در URL؛ برای وب‌هوک ضروری است، اما باید روی HTTPS و با قابلیت چرخشِ راز باشد |

### ۲-۳. دسترسی به پایگاه داده — ۶۵ فراخوانی در ۱۰ فایل (۱۴ × `prepare`)

| فایل | فراخوانی | prepare |
|---|---:|---:|
| `inc/city-contrib/includes/class-cc-gamification.php` | ۵ | ۳ |
| `inc/ready-articles.php` | ۴ | ۱ |
| `inc/city-contrib/includes/class-cc-rest.php` | ۴ | ۲ |
| **`inc/city-contrib/includes/class-cc-dashboard.php`** | ۴ | **۰** |
| `inc/internal-links.php` | ۳ | ۱ |
| `inc/content-health.php` | ۳ | ۱ |
| `inc/city-contrib/includes/class-cc-db.php` | ۳ | ۲ |
| `inc/media-restore.php` | ۲ | ۱ |
| `inc/city-contrib/includes/class-cc-rating.php` | ۲ | ۱ |
| `inc/city-contrib/includes/class-cc-moderation.php` | ۱ | ۰ |

**بررسیِ دستیِ کوئری‌های چندخطی:** چهار کوئریِ `content-health.php:72`، `internal-links.php:624`، `media-restore.php:596` و `ready-articles.php:382`
که در شمارشِ خط‌محور «بدون prepare» دیده شده بودند، **در واقع همگی از `$wpdb->prepare()` استفاده می‌کنند** ✅
تنها موردِ باقیمانده، چهار کوئریِ `class-cc-dashboard.php` است که نام جدول از `CC_DB::table()` می‌آید و ورودی کاربر ندارد
(ریسکِ عملی پایین، اما برای یکنواختی باید نرمال‌سازی شود).

---

## ۳. یافته‌های اولویت‌دار

| # | شدت | مورد | پیشنهاد |
|---|---|---|---|
| **F1** | 🔴 | `inc/site-cleanup.php` — `wp_delete_post( $id, true )` (حذفِ **دائم**، خط ۹۴۲) و بازنویسی محتوا با `str_replace`؛ طبق `docs/technical-audit-checklist.md` کوئریِ نامزدها ممکن است نوشته‌های **منتشرشده** را هم بگیرد | هرگز روی سایت اجرا نشود. پیش از هر استفاده: پشتیبان کامل + dry-run + محدودسازی کوئری به وضعیت‌های مورد نظر + تأیید مالک. حتی با وجود ۲ nonce و ۵ capability، خطرِ «تخریب داده» بالاست |
| **F2** | 🟠 | `cc/v1/ping` — افشای `PHP_VERSION` و وجودِ جدول سفارشی به هر بازدیدکننده | حذفِ مسیر، یا حذفِ فیلدهای `php` و `tables` از پاسخ، یا محدود کردن به `current_user_can('manage_options')` |
| **F3** | 🟠 | `cc/v1/cities/{id}/rating` (POST) — نوشتنِ عمومی بدون احراز هویت | افزودن نرخ‌محدودیت (مانند الگوی OTP با transient)، جلوگیری از رأی تکراری بر پایهٔ IP/کوکی/نشست، و ثبتِ `voter_key` (کلید موجود است) |
| **F4** | 🟡 | `class-cc-dashboard.php` — ۴ کوئری مستقیم بدون `prepare` | نرمال‌سازی با `$wpdb->prepare()` یا انتقال به متدهای `CC_DB` (ورودی کاربر ندارد؛ اولویتِ پایین) |
| **F5** | 🟡 | ۲۳ هندلر `admin_post_*` (۲ تای آن‌ها `nopriv`) | ساخت ماتریسِ تک‌به‌تک: هر هندلر باید `check_admin_referer()` + `current_user_can()` داشته باشد. نمونه‌برداری نشان می‌دهد اکثراً دارند؛ `cc_review_submission` باید تأیید شود |
| **F6** | 🟡 | `sa/v1/telegram-bot/{secret}` — راز در URLِ وب‌هوک | روی HTTPS اجباری؛ امکان چرخشِ راز از پیشخوان؛ عدم ثبتِ راز در لاگ‌ها |

---

## ۴. نقاط قوتِ موجود (نیازمند تغییر نیست)

- ✅ **همهٔ کوئری‌های چندخطی از `prepare` با placeholder استفاده می‌کنند** و نام جدول‌ها از `$wpdb->posts` / `$wpdb->postmeta` می‌آیند (پیشوند ثابت نشده است).
- ✅ **فرم تماس:** هانی‌پات (`$_POST['website']`) + نرخ‌محدودیت با transient (۵ ارسال در ۶ ساعت به‌ازای IP) + nonce + `sanitize_text_field`.
- ✅ **OTP:** نرخ‌محدودیت ۳ در ساعت (شماره + IP) + کدِ ۵ رقمی هش‌شده با `wp_hash_password` و verifier با `wp_check_password` + انقضای ۲ دقیقه‌ای.
- ✅ استفادهٔ گسترده از escape/sanitize: **۱۰۲۲** فراخوانیِ `esc_*`/`wp_kses` و **۲۵۰** فراخوانیِ `sanitize_*`/`absint` در قالب فرزند.
- ✅ `phpcs:ignore` همیشه **با توجیه** به‌کار رفته (مثلاً `-- escaped inside`)، نه برای پنهان‌کردنِ مشکل.

---

## ۵. برنامهٔ پیشنهادی برای شاخهٔ `fix/security-audit`

1. **گام ۱ (کم‌ریسک، قابل بازگشت):** حذف فیلدهای حساس از `cc/v1/ping`؛ افزودن transientِ نرخ‌محدودیت برای رأی‌دهی عمومی.
2. **گام ۲:** نرمال‌سازی کوئری‌های `class-cc-dashboard.php` و تکمیل ماتریسِ nonce/capability برای ۲۳ هندلر (`docs/` یا جدول در همین فایل).
3. **گام ۳ (نیازمند staging):** بررسیِ رفتاریِ هر مسیرِ نوشتن با `WP_DEBUG`، و **تستِ هرگز-اجرا-نشده‌ی `site-cleanup.php` روی یک نسخهٔ کپی**.
4. **ابزار:** نصب PHPCS + WPCS در CI یک workflow تازه می‌خواهد (در این مرحله پیشنهاد می‌شود اما ساخته نشده است).

> هر تغییر در این شاخه باید با `TESTING_CHECKLIST.md` (بخش ۲: امنیت و بهداشت کد) تأیید شود.
