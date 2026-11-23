# بریف گیت‌ها — شهرستان دشت آزادگان (`dasht-azadegan`، پست 283) — بازسازی v3

تاریخ: ۱۴۰۵/۰۹/۰۲ · بیلد: `python3 tools/wp-agent/replace_src/county_page.py dasht-azadegan`

## نتیجه

| گیت | نتیجه |
|---|---|
| fa_lint (بدنه + متا) | **۰ یافته** (`"fa_lint": []`) |
| check_template (شکل، کف واژه، مرزها، شباهت) | **۱/۱ سازگار** |
| بودجهٔ بخش‌ها (v3، با فعال‌شدن modern) | همه در بازه: intro ~180، map ~300، history ~320، people ~250، economy ~260، stay ~340، modern ~360، food ~180 |
| مجموع واژهٔ بدنه | **۱٬۶۳۸** (بازهٔ v3: 1500–2400) |
| مارکرهای `sa:sec:*` | ۸ بخش، به ترتیب رجیستری: intro,map,history,people,economy,stay,modern,food |
| جدول / خط عمودی در بدنه | ۰ |
| منابع ویکی در مرحله | ۰ (سقف ۱) |
| `<a>` درون‌خطی | ۰ |
| اعرابی ممنوع (ي ك ى ة ﻻ) | ۰ |
| واژه‌های ممنوع (باید، راستش، …) | ۰ («باید/نباید» در بازنویسی حذف شد) |

## خروجی

`tools/wp-agent/outbox/20261117-replace-dasht-azadegan/dasht-azadegan-283.{html,json,meta.json}` + `summary.json` — `ok: true`

## وضعیت انتشار

**محلی؛ بدون هیچ نوشتن زنده.** انتشار فقط با دستور صریح مالک و از مسیر `wp-agent.yml` (کار یک‌باره با `owner_approved_live_edit` + پشتیبان + probe) انجام می‌شود.
