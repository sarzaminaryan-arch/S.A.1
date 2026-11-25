# ممیزی تمپلت مستر و لینک‌سازی خوشه‌ای — استان خراسان رضوی (استان ۲)

تاریخ: ۲۰۲۶-۱۱-۲۵ / ۱۴۰۵/۰۹/۰۴ · منبع قواعد: `docs/live-site/CLUSTER-SYSTEM-MASTER.md` §۳/§۴/§۵ و گارد کد `tools/wp-agent/replace_src/check_template.py`.

خوشهٔ `#2 مشهد` (هفت شاخه) + خوشهٔ `#34 نیشابور` (سه شاخه) + هاب استان `50`.

| مجموعه | فایل‌ها | سالم | دارای ایراد | ایرادها | هشدارها |
|---|---:|---:|---:|---:|---:|
| وضعیت پیش از این نشست (baseline) | 13 | 0 | 13 | 45 | 0 |
| خروجی `outbox/20261125-razavi-khorasan-cluster-v6/` | 13 | 13 | 0 | 0 | 0 |

## ایرادهای وضعیت پیش از نشست

- **mashhad-63** (city، 2167 واژه، 9 لینک داخلی)
  - missing cluster links (8): ['/city/chenaran/', '/city/golbahar/', '/city/zebarkhan/', '/city/kalat/', '/city/dargaz/', '/city/sarakhs/']
- **neyshabur-64** (city، 1038 واژه، 5 لینک داخلی)
  - words 1038 outside master/code budget (1200–2400)
  - sa_seo_title 66 chars (> 60)
  - sa_seo_description 118 chars (want 140–155)
  - missing cluster links (2): ['/city/quchan/', '/city/kashmar/']
- **imam-reza-shrine** (attraction، 301 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 301 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (1): ['/attraction/torqabeh/']
- **ferdowsi-tomb** (attraction، 240 واژه، 5 لینک داخلی)
  - duplicate internal links: ['/city/mashhad/']
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 240 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (1): ['/attraction/torqabeh/']
- **koohsangi-park** (attraction، 186 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 186 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (1): ['/attraction/ferdowsi-tomb/']
- **torqabeh** (attraction، 204 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 204 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (3): ['/attraction/imam-reza-shrine/', '/attraction/ferdowsi-tomb/', '/attraction/koohsangi-park/']
- **shandiz** (attraction، 174 واژه، 5 لینک داخلی)
  - duplicate internal links: ['/attraction/torqabeh/']
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 174 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (2): ['/attraction/imam-reza-shrine/', '/attraction/koohsangi-park/']
- **water-waves-land** (attraction، 178 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 178 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (2): ['/attraction/imam-reza-shrine/', '/attraction/koohsangi-park/']
- **haft-hooz-park** (attraction، 182 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 182 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
  - missing cluster links (1): ['/attraction/ferdowsi-tomb/']
- **khayyam-tomb** (attraction، 212 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 212 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
- **attar-tomb** (attraction، 178 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 178 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
- **wooden-village-neyshabur** (attraction، 176 واژه، 4 لینک داخلی)
  - missing attraction body markers: ['lead', 'history', 'sections', 'season', 'access', 'tips', 'nearby', 'sources']
  - words 176 outside attraction budget (800–1500)
  - missing factsheet/seo meta: ['sa_city_id']
- **razavi-khorasan-50** (province_hub، 1926 واژه، 4 لینک داخلی)
  - L1/L2: hub misses 23 links: ['/city/binalud/', '/city/chenaran/', '/city/dargaz/', '/city/fariman/', '/city/golbahar/', '/city/kalat/', '/city/kashmar/', '/city/mashhad/']

## وضعیت خروجی v6

همهٔ 13 فایل بدون ایراد مسدودکننده ممیزی شدند.

## آنچه بررسی شد

- **شهرستان‌ها (۲):** نشانگرهای شش‌گانهٔ گارد کد، بودجهٔ واژهٔ `1500–2400`، متای کامل تمپلیت مستر، تبصرهٔ سرشماری ۱۳۹۵، SEO (عنوان ≤ ۶۰ و توضیح ۱۴۰–۱۵۵ نویسه)، FAQ، منابع دولایه، لینک به هاب استان، همهٔ شاخه‌های همان خوشه و شهرستان‌های همسایه.
- **جاذبه‌ها (۱۰):** نشانگرهای نه‌گانهٔ بدنه، کارت اطلاعات سریع (`sa_city_id` و `sa_province_id`)، بودجهٔ واژه (گارد ۸۰۰–۱۵۰۰ / مستر ۹۰۰–۱۴۰۰)، لینک به شهرستان مادر و استان، ۲ تا ۴ هم‌مسیر، حداکثر یک لینک به هر مقصد و `target="_self"`.
- **هاب استان (۱):** لینک به هر دو شهرستان خوشه و همهٔ ۱۰ جاذبه، بدون تکرار.

## محدودهٔ کار

این خروجی **پیش‌نویس محلی** است؛ هیچ نوشتن زنده‌ای انجام نشد و شمارنده‌های رسمی (`0/31` استان، `6/100` خوشه، `2/100` پیش‌نویس، `18/239` شاخه) دست‌نخورده‌اند. متن‌های گسترش‌یافته از شواهد ازپیش‌تأییدشدهٔ مخزن (پروندهٔ جاذبه‌ها، متای شهرستان، `docs/live-site/briefs/mashhad-evidence.md` و مقالهٔ هاب استان) نوشته شدند؛ پژوهش وب تازه‌ای انجام نشد.
