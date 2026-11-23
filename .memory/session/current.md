# وضعیت نشست جاری (Session State)

- **شاخهٔ فعال:** `arena/a2d1e561-s-a-1` (از `main` در `6a66322`).
- **فاز ۱ (اولویت اول کاربر):** رفع دو باگ گزارش‌شده روی سایت زنده:
  1. پیوند نادرست «شهرستان کوهپایه‌ای جم» در `/province/bushehr/` به `/city/kuhpayeh/` ← اصلاح در `inc/internal-links.php` (`sa_autolink_fragment`) + تست در `tests/test-internal-links.php`.
  2. نمایش «۲۸ شهرستان (۲۹ راهنمای فعال)» در `/province/isfahan/` ← اصلاح `county_count` اصفهان در `data/province-profiles.json` به `29` + افزودن `meymeh-vazvan` در `data/region-facts.php` + تست در `tests/test-province-county-links.php`.
- **نسخه و جاب:** بامپ قالب فرزند به `2.24.18` و ساخت `tools/wp-agent/jobs/20261123-04-theme-22418-zwnj-isfahan-count.json`.
- **گام بعدی:** پوش به `arena/a2d1e561-s-a-1`، اجرای `build-gates.yml`، باز کردن و ادغام PR به `main`، صبر برای تکمیل `build-gates.yml` و `release-child-theme.yml` روی `main`، اجرای `wp-agent.yml` روی `20261123-04-theme-22418-zwnj-isfahan-count.json` و تأیید زندهٔ `/province/bushehr/` و `/province/isfahan/` پیش از آغاز فاز ۲ (۱۴ استان باقی‌مانده).
