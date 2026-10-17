# 🛡️ تنظیمات امنیتی مخزن گیت‌هاب (اقدام توسط مالک)

لطفاً وارد سایت گیت‌هاب شده و در مسیر `Settings > Branches > Branch protection rules` قوانین زیر را اضافه کنید:

## برای Branch `main`:

1. تیک **Require a pull request before merging** زده شود.

2. تیک **Require status checks to pass before merging** زده شود (تا اکشن Protect ما اجرا شود).

3. تیک **Do not allow force pushes** زده شود.

4. تیک **Do not allow deletions** زده شود.

## برای Branch `develop` (پس از ساخته شدن):

1. تیک **Do not allow force pushes** زده شود.

*این کار باعث می‌شود هیچ Agentای نتواند با یک اشتباه کل پروژه را پاک کرده یا مستقیماً روی نسخه نهایی تغییرات مخرب اعمال کند.*

---

## 📌 نکات تکمیلی (مهم)

- **نامِ چک اجباری:** پس از اولین اجرای workflow بالا، در فهرست status checkها نام
  **`check-protected-files`** ظاهر می‌شود؛ آن را در بخش «Required status checks» تیک بزنید
  (تا پیش از اولین اجرا در فهرست نخواهد بود).
- **چرا Agentها این کار را نمی‌توانند انجام دهند؟** تغییر تنظیمات مخزن نیازمند دسترسی `Admin` است؛
  توکن‌های مورد استفادهٔ ایجنت‌ها معمولاً فقط `contents: write` دارند.
- **مخزن خصوصی است:** به‌روزرسان قالب روی سایت با یک Fine-grained token فقط‌خواندنی کار می‌کند
  (ترجیحاً ثابت `SA_GITHUB_TOKEN` در `wp-config.php` هاست).
- **پیشنهاد:** Rulesetها (در `Settings > Rules > Rulesets`) جایگزین مدرن‌تری برای Branch protection هستند
  و در صورت تمایل می‌توان همان چهار قانون را آن‌جا تعریف کرد.

## 🔐 محافظتِ تکمیلیِ پیشنهادی

| مورد | مسیر در گیت‌هاب | پیشنهاد |
|---|---|---|
| محدودیت توکن | `Settings > Actions > General` | Workflow permissions روی **Read repository contents** |
| تأیید دوم | Branch protection روی `main` | در صورت امکان: **Require approvals = 1** (تأیید مالک) |
| تاریخچه | Branch protection روی `main` | **Do not allow deletions** + غیرفعال‌بودن force push (بالا) |
| اسرار | `Settings > Secrets and variables` | هیچ توکنی در فایل‌های مخزن ذخیره نشود (اسکن توکن در workflow ریلیز فعال است) |
| لاگ‌ها | `Settings > Actions` | برای workflowهای دارای `contents: write`، دسترسی را در سطح job محدود نگه دارید |

## ⚠️ موردِ بازِ مرتبط

فایل `imagetool (23).zip` با وجود قرار داشتنِ `*.zip` در `.gitignore` **در تاریخچه و درخت مخزن کامیت شده است**
(قانون `.gitignore` روی فایل‌هایی که قبلاً track شده‌اند اثر نمی‌کند). برای حذف آن، پس از تأیید شما
دستور زیر اجرا می‌شود (فایل از گیت خارج می‌شود اما نسخهٔ محلی باقی می‌ماند):

```bash
git rm --cached "imagetool (23).zip"
```

> این تغییر را ایجنت به‌تنهایی انجام نمی‌دهد؛ نیازمند تأیید صریح مالک است.
