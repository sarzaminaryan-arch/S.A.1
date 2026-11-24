# نشست جاری (۲۰۲۶-۱۱-۲۴ / ۱۴۰۵/۰۹/۰۳)

- **شاخهٔ فعال:** `arena/948ec92c-s-a-1`
- **دستاوردهای تکمیل‌شده در این نشست:**
  1. ممیزی کامل وضعیت پروژه، رفع خطای تست `tools/wp-agent/test_county_run.py` و الزامی‌سازی ثبت پیشرفت پایان نشست در `.memory/` همراه با گارد `tools/wp-agent/test_memory_protocol.py`.
  2. تدوین سند جامع و یکپارچهٔ سیستم خوشه‌ای (`docs/live-site/CLUSTER-SYSTEM-MASTER.md`) شامل پرامپت شهرستان، پرامپت شاخهٔ جاذبه (لحن اول‌شخص)، معماری ۴ لایه لینک‌سازی خوشه‌ای، استاندارد UI/UX، سیاست تصاویر WebP مجوزدار با کریدیت ظریف طوسی و تبصرهٔ سرشماری رسمی ۱۳۹۵.
  3. پیاده‌سازی نسخهٔ `2.24.20` قالب فرزند (`single-city.php`, `single-attraction.php`, `single-province.php`, `inc/design-2026.php`, `inc/county-map.php`, `inc/agent-bridge.php`, `assets/css/child.css`) و افزودن دستور نگهبان‌دار `publish-draft` در `tools/wp-agent/wp_agent.py`.
  4. ثبت ۳ تصمیم ویژه (`#10 کیش`, `#30 بوشهر`, `#41 یاسوج`) و سازمان‌دهی صف اجرایی **۳۱ استان کشور** در `tools/wp-agent/topic_clusters_100.json` و `docs/live-site/topic-cluster-roadmap.md`.
- **گام اجرایی بعدی:** مرج و استقرار `v2.24.20` و آغاز **استان ۱: تهران (`tehran`, `wp_id=43`)** با ساخت پیش‌نویس خوشهٔ **#1 شهرستان تهران (`213`) + ۸ شاخهٔ جاذبه**.
