# وضعیت نشست جاری (Session State)

- **شاخهٔ فعال:** `arena/948ec92c-s-a-1` (انشعاب از `04f6fbd` روی `main`).
- **هدف نشست (۲۰۲۶-۱۱-۲۴ / ۱۴۰۵/۰۹/۰۳):**
  1. همگام‌سازی کامل حافظهٔ `.memory/` با آخرین وضعیت واقعی پروژه تا `PR #151` و افزودن فایل مرجع `.memory/wiki/roadmap.md`.
  2. الزامی‌کردن قانون «ثبت پیشرفت کار در `.memory/` در پایان هر نشست» در تمامی اسناد ورودی ایجنت (`AGENTS.md`, `.agent/RULES.md`, `CLAUDE.md`, `AGENT_RULES.md`) همراه با گارد CI (`tools/wp-agent/test_memory_protocol.py`).
  3. ثبت کامل **استراتژی انتشار خوشه‌ای (Topic Cluster Strategy — ۱۰۰ شهرستان پرجستجو + ۲۳۹ شاخهٔ جاذبه)** در `docs/live-site/topic-cluster-roadmap.md` و `tools/wp-agent/topic_clusters_100.json`.
  4. رفع باگ ایزوله‌نبودن `test_county_run.py` که باعث قرمزی `Build gates` روی `main` پس از `PR #151` شده بود.
- **گام بعدی پس از این نشست:** آغاز اجرای **خوشهٔ ۱ — تهران (`/city/tehran/` + ۸ شاخهٔ جاذبه)** پس از تعیین روش انتشار مقالات جدید `/attraction/`.
