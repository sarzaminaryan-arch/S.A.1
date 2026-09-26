# 🧠 skill-SA-agent — اسکیل عملیاتی پروژه سرزمین آریان

<div dir="rtl">

این پوشه **عصاره‌ی ۹ ریپوی فورک‌شده** در اکانت گیت‌هاب شماست که به یک «اسکیل» استاندارد (Agent Skills) تبدیل شده تا هر عامل هوش مصنوعی (Claude Code، Cursor، Codex، …) بداند روی پروژه‌ی **sarzaminaryan.ir** چطور باید کار کند: وردپرس فارسی، قالب فرزند، مدل داده، سئو و استانداردهای گوگل، امنیت، تولید محتوا و انتشار روی گیت‌هاب.

## قوانین سخت (خلاصه)

1. **تنها راه تحویل فایل = گیت‌هاب.** هر خروجی باید push شود و به‌صورت لینک گیت‌هاب (Release یا raw) برگردد. نصب از طریق cPanel انجام می‌شود.
2. **همه‌چیز در قالب فرزند** (`sarzaminaryan-child`)؛ کاری که افزونه‌ها می‌کنند، در فرزند پیاده می‌شود.
3. **وردپرس فارسی رسمی** (fa.wordpress.org)، RTL، فونت محلی؛ تاریخ‌های ماشینی میلادی می‌مانند.
4. **مدل داده قانون است** (`data-model/MASTER_DATA_MODEL.md`).
5. **امنیت در هر تغییر PHP**: sanitize ورودی، escape خروجی، nonce + capability.
6. هویت قالب: نام `Sarzamin Aryan` (اسلاگ `sarzaminaryan`)، نویسنده **محمدرضا لک**.

## ساختار

</div>

```
skill-SA-agent/
├── SKILL.md                     ← نقطه‌ی ورود عامل: قوانین سخت، مسیردهی کار، چک‌لیست تأیید
├── README.md                    ← این فایل
├── SOURCES.md                   ← از هر فورک چه چیزی استخراج شد و چه چیزی کنار گذاشته شد (و چرا)
├── references/
│   ├── 01-wordpress-theme-standards.md   ← استاندارد قالب/CPT/متا/REST/کارایی/WP-CLI (از WordPress/agent-skills)
│   ├── 02-wordpress-security-hardening.md← جدول حمله→دفاع، SOP بازبینی OWASP، چک‌لیست cPanel
│   ├── 03-persian-rtl-jalali.md          ← RTL، فونت، اعداد فارسی، تاریخ شمسی (از wordpress-fa)
│   ├── 04-seo-schema-google.md           ← قرارداد سئوی هر صفحه، JSON-LD هر موجودیت، بردکرامب، ممیزی فنی
│   ├── 05-content-pipeline.md            ← خط تولید محتوای AI طبق Level 7 + پرامپت‌ها + حذف الگوهای ماشینی
│   ├── 06-agent-workflow.md              ← CLAUDE.md، حلقه‌ی کار، قالب /goal، رویه‌ی انتشار روی گیت‌هاب
│   └── 07-agents-roster.md               ← ۷ کارت نقش (توسعه‌دهنده فرزند، سئو، نویسنده، نگهبان مدل داده، QA، امنیت، انتشار)
└── assets/
    ├── CLAUDE.md.template       ← قالب فایل تنظیمات پروژه برای Claude Code
    └── goal-templates.md        ← سه /goal آماده: انتشار موجودیت، تغییر قالب فرزند، انتشار روی گیت‌هاب
```

<div dir="rtl">

## نصب / استفاده

- **Claude Code / Cursor:** پوشه‌ی `skill-SA-agent` را در `.claude/skills/` (پروژه) یا `~/.claude/skills/` (سراسری) کپی کنید. عامل به‌صورت خودکار با توجه به `description` آن را فعال می‌کند.
- **هر عامل دیگر:** متن `SKILL.md` را در ابتدای گفتگو بدهید و بگویید «طبق skill-SA-agent کار کن».
- فایل `assets/CLAUDE.md.template` را به ریشه‌ی ریپو به نام `CLAUDE.md` کپی کنید.

## نسخه

**1.0** — 2026-09-26 — استخراج اولیه از ۹ فورک + قوانین مالک پروژه.

</div>
