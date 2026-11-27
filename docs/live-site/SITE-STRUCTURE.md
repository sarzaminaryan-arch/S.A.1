# ساختار فعلی سایت — تصویر ماشینی از دامپ پایگاه‌داده

> منبع: `haftpair_SA.sql.gz` · ساخته‌شده با `tools/ops/site_structure.py` (فقط‌خواندنی).
> اعداد این فایل از **دادهٔ خود سایت** است، نه برآورد. برای بازسازی: 
> `python3 tools/ops/site_structure.py --sql <dump.sql.gz>`

---

## ۱. شمارش نوع‌محتوا (منتشرشده)

| نوع | منتشرشده | پیش‌نویس | سایر |
|---|---:|---:|---:|
| `province` | 31 | 1 | 0 |
| `city` | 487 | 8 | 2 |
| `attraction` | 22 | 2 | 0 |
| `region` | 59 | 3 | 0 |
| `page` | 5 | 2 | 2 |
| `cc_submission` | 3 | 2 | 3 |
| `attachment` | 0 | 0 | 612 |

## ۲. بودجهٔ واژه و پوشش متا

| نوع | تعداد | میانگین واژه | میانه | کمینه | زیر ۷۰۰ واژه | SEO/منبع/FAQ | تصویر شاخص |
|---|---:|---:|---:|---:|---:|---:|---:|
| `province` | 31 | 5568 | 5475 | 3841 | 0 | 31/31/31 | 18 |
| `city` | 487 | 935 | 994 | 589 | 164 | 487/487/487 | 487 |
| `attraction` | 22 | 1436 | 1396 | 1088 | 0 | 22/22/22 | 2 |

## ۳. توزیع شهرها در استان‌ها (پایهٔ قانون خوشه)

| استان | slug | شهرستان منتشرشده |
|---|---|---:|
| فارس | `fars` | 37 |
| خراسان رضوی | `razavi-khorasan` | 34 |
| اصفهان | `isfahan` | 29 |
| خوزستان | `khuzestan` | 29 |
| سیستان و بلوچستان | `sistan-baluchestan` | 26 |
| کرمان | `kerman` | 25 |
| آذربایجان شرقی | `east-azerbaijan` | 22 |
| مازندران | `mazandaran` | 22 |
| آذربایجان غربی | `west-azerbaijan` | 20 |
| گیلان | `gilan` | 17 |
| تهران | `tehran` | 16 |
| کرمانشاه | `kermanshah` | 14 |
| گلستان | `golestan` | 14 |
| هرمزگان | `hormozgan` | 13 |
| اردبیل | `ardabil` | 12 |
| ایلام | `ilam` | 12 |
| بوشهر | `bushehr` | 12 |
| خراسان جنوبی | `south-khorasan` | 12 |
| لرستان | `lorestan` | 12 |
| مرکزی | `markazi` | 12 |
| چهارمحال و بختیاری | `chaharmahal-bakhtiari` | 12 |
| یزد | `yazd` | 12 |
| خراسان شمالی | `north-khorasan` | 10 |
| زنجان | `zanjan` | 10 |
| همدان | `hamadan` | 10 |
| کردستان | `kurdistan` | 10 |
| کهگیلویه و بویراحمد | `kohgiluyeh-boyer-ahmad` | 9 |
| سمنان | `semnan` | 8 |
| البرز | `alborz` | 7 |
| قزوین | `qazvin` | 6 |
| قم | `qom` | 3 |

**جمع:** 487 شهرستان در 31 استان.

## ۴. تاکسونومی‌ها

| تاکسونومی | تعداد ترم | تعداد شیء |
|---|---:|---:|
| `sa_gallery_album` | 518 | 6 |
| `province_tax` | 31 | 540 |
| `attraction_type` | 19 | 73 |
| `accommodation_type` | 9 | 0 |
| `travel_duration` | 8 | 0 |
| `travel_budget` | 5 | 0 |
| `category` | 5 | 0 |
| `travel_season` | 4 | 1151 |
| `wp_theme` | 3 | 3 |
| `nav_menu` | 2 | 10 |

