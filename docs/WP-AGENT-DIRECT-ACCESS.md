# دسترسی مستقیم ایجنت به وردپرس (پل ایجنت)

> **سند مرجع.** هر نشست تازه پیش از هر کاری روی سایت زنده این فایل را بخواند.
> وضعیت: **فعال و آزموده** از ۱۵ نوامبر ۲۰۲۶ (۲۵ آبان ۱۴۰۵)، قالب 2.22.72.
> راهنمای فنی دستورها: `tools/wp-agent/README.md`.

## ۱. خلاصه در سه خط

- ایجنت سایت **sarzaminaryan.ir** را مستقیم می‌خواند: همهٔ نوع‌ها، همهٔ وضعیت‌ها، متا و برچسب‌ها.
- ایجنت مقاله را مستقیم در وردپرس می‌گذارد، اما **فقط به‌صورت پیش‌نویس**. انتشار فقط کار مالک است.
- پیش از هر تغییر در ظاهر، ساختار یا محتوای موجود، **اول پشتیبان** گرفته می‌شود و بعد تغییر انجام می‌شود. بازگشت با یک فرمان ممکن است.

## ۲. قواعد مالک (دستور ۱۴ نوامبر ۲۰۲۶؛ نقض نکن)

| موضوع | قاعده |
|---|---|
| خواندن | آزاد است. |
| مقالهٔ تازه | فقط `draft`. هیچ فرمان انتشار یا حذفی وجود ندارد. |
| تغییر ظاهر یا ساختار | اول پشتیبان از وضع قبلی، بعد تغییر. پیش از هر تغییر ظاهری به مالک بگو چه چیزی از چه مقداری به چه مقداری می‌رود. |
| متن مقالهٔ منتشرشده | مستقیم عوض نمی‌شود. `draft-copy` (پیش‌نویس بازنویسی کنار اصل) بساز. `owner_approved_live_edit` فقط با دستور صریح و مکتوب مالک در همان نشست. |
| کد قالب | فقط از راه نسخهٔ تازه و Release. نسخهٔ قبلی روی GitHub پشتیبان است. |
| دورهٔ Core Update (تا حدود ۴ آذر ۱۴۰۵) | تغییر ظاهری، تغییر نشانی و حذف انبوه ممنوع است. |
| رمزها | فقط در GitHub Secrets. هرگز در چت نخواه و هرگز در مخزن ننویس. |
| متن | قواعد `AGENTS.md` (بندهای ۸ تا ۱۲) برقرار است. پل خودش واژه‌های ممنوع را رد می‌کند. |

## ۳. چطور کار می‌کند

```
نشست Arena (sandbox بدون دسترسی شبکه به سایت)
  │  ۱) فایل کار: tools/wp-agent/jobs/<YYYYMMDD-NN-نام>.json  (+ متن در tools/wp-agent/outbox/)
  │  ۲) git push روی شاخهٔ نشست
  ▼
GitHub Actions — workflow «WP agent» (.github/workflows/wp-agent.yml)
  │  رمزها از Secrets: SA_SITE_URL, SA_USER, SA_APP_PASSWORD
  │  اجرا: python3 tools/wp-agent/wp_agent.py run-job <job>
  ▼
sarzaminaryan.ir/wp-json/sa-agent/v1   (inc/agent-bridge.php در قالب فرزند)
  │  احراز هویت: Application Password کاربر sa-agent (مدیر کل)
  │  پشتیبان سمت سایت: ابزارها ← «پشتیبان‌های ایجنت» (نوع خصوصی sa_agent_backup)
  ▼
نتیجه: tools/wp-agent/results/<همان نام>.json  (workflow روی همان شاخه commit می‌کند)
پشتیبان سمت runner: artifact خصوصی wp-agent-backups-<run> (۹۰ روز)
```

## ۴. دستور کار ایجنت (گام‌به‌گام)

1. اگر متن لازم است: `tools/wp-agent/outbox/<slug>.html` (و متا در `<slug>.meta.json`).
2. فایل کار با **نام یکتا** بساز. هر فایل فقط یک بار اجرا می‌شود.

   ```json
   {"note": "پیش‌نویس بجنورد", "steps": [
     {"cmd": "ping"},
     {"cmd": "draft", "type": "post", "title": "…", "content_file": "tools/wp-agent/outbox/bojnord.html"}
   ]}
   ```

3. `git add` و `commit` کن، بعد `git push origin <شاخهٔ نشست>`.
4. اجرا را دنبال کن و نتیجه را بخوان:

   ```bash
   gh run list --workflow wp-agent.yml --limit 3
   gh run watch <id> --exit-status
   git pull --ff-only origin <شاخه>
   cat tools/wp-agent/results/<نام>.json
   ```

5. هر نوشتن را در `docs/live-site/CHANGELOG.md` یک سطر ثبت کن.

**فرمان‌ها:**

| فرمان | کار |
|---|---|
| `ping` | آزمون اتصال |
| `list` | فهرست با فیلتر `type` / `status` / `search` / `slug` / `modified_after` |
| `inventory` | همهٔ محتوا، همهٔ وضعیت‌ها |
| `get` | یک نوشته کامل، همراه متا |
| `draft` | ساخت پیش‌نویس تازه |
| `draft-copy` | پیش‌نویس بازنویسی کنار یک نوشتهٔ منتشرشده |
| `update` | متا یا برچسب، و متنِ پیش‌نویس‌ها؛ با پشتیبان |
| `backups` | فهرست پشتیبان‌ها |
| `restore` | بازگشت از پشتیبان |
| `theme-mods` | خواندن یا تغییر تنظیمات ظاهر؛ با پشتیبان |
| `run-job` | اجرای یک فایل کار |

**خواندن عمومی سریع، بدون این مراحل:** `fetch_page` روی صفحه‌ها یا `/wp-json/wp/v2/...`.

## ۵. اجزا

| جزء | مسیر |
|---|---|
| ماژول سمت سایت | `wp-content/themes/sarzaminaryan-child/inc/agent-bridge.php` (در `functions.php` بعد از `security.php` بارگذاری می‌شود) |
| تست | `wp-content/themes/sarzaminaryan-child/tests/test-agent-bridge.php` (در CI انتشار قالب) |
| Application Password | در `inc/security.php` دیگر خاموش نیست. فقط کاربرانی که `edit_others_posts` دارند (ویرایشگر و مدیر) آن را دارند. سرآیند جایگزین `X-SA-Authorization` هم پذیرفته می‌شود. |
| کلاینت | `tools/wp-agent/wp_agent.py` (فقط کتابخانهٔ استاندارد پایتون؛ رمز را با `***` می‌پوشاند) |
| اجراکننده | `.github/workflows/wp-agent.yml` (روی push در `tools/wp-agent/jobs/**.json`؛ هم‌زمانی سریالی) |
| نتیجه‌ها | `tools/wp-agent/results/` |

**مسیرهای REST** (`/wp-json/sa-agent/v1`):

| مسیر | مجوز |
|---|---|
| `GET ping`، `GET posts`، `GET post/{id}` | `edit_posts` |
| `POST post/{id}`، `POST post/{id}/draft-copy`، `POST draft`، `GET backups`، `GET backup/{id}`، `POST restore/{id}` | `edit_others_posts` |
| `GET/POST theme-mods` | `edit_theme_options` |

- هیچ مسیر DELETE وجود ندارد.
- نامک، وضعیت و تاریخ انتشار با پل عوض نمی‌شوند.
- برچسب یا دستهٔ تازه ساخته نمی‌شود.
- متای محافظت‌شده (`_…`، به‌جز `_thumbnail_id`) رد می‌شود.
- هم‌زمانی با `expected_modified_gmt` (پاسخ 409) و `expected_hash` بررسی می‌شود.

## ۶. وضعیت و تاریخچه

| تاریخ | رویداد |
|---|---|
| ۲۰۲۶-۱۱-۱۴ | دستور مالک. قالب 2.22.72 ساخته و منتشر شد (کامیت `64e357f`). |
| ۲۰۲۶-۱۱-۱۵ | مالک قالب را نصب کرد، کاربر `sa-agent` با نقش مدیر کل ساخت و سه Secret را در GitHub گذاشت. |
| ۲۰۲۶-۱۱-۱۵ | ping موفق: نسخه 2.22.72، `can_write` و `can_theme` برقرار، ۸ نوع محتوا (`results/20261115-01-ping.json`). |

## ۷. عیب‌یابی

| نشانه | علت و راه‌حل |
|---|---|
| `Missing repository secrets` در workflow | Secrets ساخته نشده است. مالک: Settings ← Secrets and variables ← Actions. |
| `HTTP 401` | Application Password لغو شده یا نام کاربری اشتباه است. مالک یک رمز تازه می‌سازد و `SA_APP_PASSWORD` را به‌روز می‌کند. |
| `HTTP 404 rest_no_route` | قالب 2.22.72 یا بالاتر نصب نیست. |
| `HTTP 409 sa_agent_live_content` | تلاش برای تغییر متن زنده. `draft-copy` بساز. |
| `HTTP 409 sa_agent_stale` | نوشته از زمان خواندن عوض شده. دوباره بخوان. |
| فایل کار اجرا نشد | نامش تکراری است و فایل نتیجه دارد. نام تازه بگذار. |

**قطع دسترسی توسط مالک:** کاربران ← `sa-agent` ← ویرایش ← «رمزهای عبور برنامه» ← «لغو».
