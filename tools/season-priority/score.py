#!/usr/bin/env python3
"""رتبه‌بندی ارزش پرداختن به شهرستان‌ها در بخش «فصل‌های سفر» (/season/...).

داده‌ها همه از منابع منتشرشده آمده‌اند (فهرست منابع در
docs/live-site/season-priority-1405.md). این اسکریپت فقط امتیازها را با یک
قاعدهٔ ثابت جمع می‌زند تا رتبه‌بندی قابل بازتولید باشد:

    python3 tools/season-priority/score.py            # جدول کلی + جدول هر فصل
    python3 tools/season-priority/score.py --json     # خروجی ماشینی

قاعدهٔ امتیاز (سقف نظری حدود ۱۰۰):
  A) تقاضای سنجیده (داده‌های رزرو و آمار رسمی)            ۰ تا ~۵۵
  B) سیگنال جستجوی گوگل ترندز (خلاصهٔ مالک)                ۰ تا ۱۲
  C) آمادگی سایت (ردیف صف پژوهش)                          ۰ تا ۱۰
  D) روند ۱۴۰۴–۱۴۰۵ (جنگ، قطع اینترنت، افت جنوب)          −۵ تا +۳
امتیاز فصلی = امتیاز کل + ۸ اگر آن فصل «فصل اوج» شهرستان باشد.
"""
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
QUEUE = ROOT / "docs/live-site/county-research-queue.md"

# --- منبع ۱: ستاد مرکزی خدمات سفر، نوروز ۱۴۰۴ (خبرآنلاین ۲۰۴۶۰۷۹)
# ۴۰ شهر به ترتیب بیشترین مسافر. نام شهر → شهرستان (درگهان→قشم،
# مرزن‌آباد→چالوس، لوندویل→آستارا، چمستان→نور).
HQ_NOWRUZ_1404 = [
    "مشهد", "شیراز", "تهران", "اصفهان", "بندرعباس", "قم", "کرج", "رشت", "اهواز",
    "خرم‌آباد", "بوشهر", "قشم", "یزد", "گناوه", "کرمانشاه", "بندر انزلی", "تبریز",
    "همدان", "نوشهر", "گرگان", "ساری", "قشم:درگهان", "بندر لنگه (کیش)", "دزفول",
    "زنجان", "رامسر", "چالوس", "آمل", "بابلسر", "اردبیل", "کاشان", "سمنان",
    "محمودآباد", "لاهیجان", "شاهرود", "چالوس:مرزن‌آباد", "آستارا:لوندویل", "بابل",
    "نور:چمستان", "شوشتر",
]

# --- منبع ۲: گزارش سالانهٔ علی‌بابا ۱۴۰۴ (دیجیاتو، مهر ۱۴۰۵): ۵ مقصد اول هتل داخلی
ALIBABA_YEAR_HOTEL = {"مشهد": 10, "تهران": 8, "بندر لنگه (کیش)": 6, "شیراز": 4, "اصفهان": 2}
# مسیرهای پروازی پرطرفدار همان گزارش (پس از تهران–مشهد): تهران–شیراز، تهران–اهواز
ALIBABA_YEAR_ROUTE = {"شیراز": 2, "اهواز": 2}

# --- منبع ۳: علی‌بابا، نوروز ۱۴۰۴ (۲۵ اسفند تا ۱۵ فروردین؛ دیجیاتو)
ALIBABA_NOWRUZ = {
    "پرواز": ["مشهد", "شیراز", "بندرعباس"],
    "هتل": ["مشهد", "تهران", "اصفهان"],
    "تور": ["بندر لنگه (کیش)", "مشهد"],
    "اتوبوس": ["اصفهان", "شیراز", "مشهد"],
    "قطار": ["تهران", "مشهد", "قم"],
}

# --- منبع ۴: پایش رزرو اقامتگاه پیش از نوروز (ستاد سفر، به نقل ایسنا در مجلهٔ علی‌بابا)
HQ_BOOKING = ["مشهد", "بندر لنگه (کیش)", "یزد", "قشم", "شیراز", "اصفهان", "بندرعباس"]

# --- منبع ۵: جاباما
JABAMA = {
    # تابستان ۱۴۰۴ (جنگ ۱۲روزه): کردان ۱، رامسر ۲، تهران ۳
    "ساوجبلاغ (هشتگرد)": 6, "رامسر": 5, "تهران": 4,
    # همان گزارش: رشد محسوس
    "رشت": 3, "چالوس": 3, "نوشهر": 3, "دماوند": 3,
}
JABAMA_NOWRUZ_1405_RISERS = ["چالوس", "نوشهر"]  # +۳
JABAMA_POPULAR = ["مشهد", "رامسر", "تهران", "رشت", "بندر لنگه (کیش)", "قشم", "شیراز", "اصفهان"]  # +۲

# --- منبع ۶: فلای‌تودی، نیمهٔ نخست ۱۴۰۴
FLYTODAY = {"مشهد": 4, "بندر لنگه (کیش)": 3, "قشم": 3}
# --- منبع ۷: مهر، آبان ۱۴۰۴: رشد ۴۰٪ رزرو تور قشم و کیش در پاییز
AUTUMN_TOURS = {"بندر لنگه (کیش)": 2, "قشم": 2}
# --- منبع ۸: رویدادهای سنجیده
EVENTS = {"کاشان": 3,   # گلاب‌گیری نیاسر، بیش از ۱۰۰ هزار گردشگر در اردیبهشت ۱۴۰۵ (صداوسیما)
          "چابهار": 2}  # بیش از ۴۵۰ هزار مسافر نوروزی (صداوسیما، تاریخ خبر نامشخص)

# --- سیگنال گوگل ترندز (خلاصهٔ مالک): فصل‌هایی که شهرستان در صدر جستجوست
GT_SEASONS = {
    "مشهد": "spring summer autumn winter", "تهران": "autumn winter spring summer",
    "اصفهان": "spring", "شیراز": "spring", "یزد": "spring autumn",
    "کاشان": "spring autumn", "رامسر": "summer spring", "چالوس": "summer spring",
    "نوشهر": "summer", "رشت": "summer autumn", "بندر انزلی": "summer",
    "لاهیجان": "summer", "ماسال": "summer", "تبریز": "summer", "اردبیل": "summer",
    "سرعین": "summer", "همدان": "summer", "سنندج": "summer spring", "مریوان": "summer",
    "بندر لنگه (کیش)": "winter spring", "قشم": "winter", "چابهار": "winter",
    "بندرعباس": "winter", "اهواز": "winter", "آبادان": "winter", "کرمان": "autumn",
}

# فصل اوج (برای امتیاز فصلی) و فصل‌هایی که شهرستان در صفحهٔ آن فصل می‌آید
CITIES = {
    #  نام شهرستان           : (فصل‌های اوج, فصل‌های حضور در صفحه, روند ۱۴۰۴–۱۴۰۵)
    "مشهد":               ("spring summer", "spring summer autumn winter", 0),
    "تهران":              ("autumn winter", "spring summer autumn winter", 0),
    "اصفهان":             ("spring", "spring autumn", -3),
    "شیراز":              ("spring", "spring autumn", -3),
    "یزد":                ("spring autumn", "spring autumn winter", 0),
    "کاشان":              ("spring autumn", "spring autumn", 0),
    "قم":                 ("spring", "spring", 0),
    "کرج":                ("spring", "spring summer", 0),
    "رامسر":              ("summer", "summer spring autumn", 3),
    "چالوس":              ("summer", "summer spring autumn", 3),
    "نوشهر":              ("summer", "summer spring", 3),
    "رشت":                ("summer", "summer spring autumn", 3),
    "بندر انزلی":         ("summer", "summer spring", 3),
    "لاهیجان":            ("summer", "summer spring", 3),
    "ماسال":              ("summer", "summer autumn", 3),
    "ساوجبلاغ (هشتگرد)": ("summer", "summer", 3),
    "دماوند":             ("summer", "summer", 3),
    "آمل":                ("summer", "summer spring", 3),
    "بابلسر":             ("summer", "summer spring", 3),
    "محمودآباد":          ("summer", "summer spring", 3),
    "تبریز":              ("summer", "summer spring", 0),
    "اردبیل":             ("summer", "summer winter", 0),
    "سرعین":              ("summer", "summer winter", 0),
    "همدان":              ("summer", "summer spring", 0),
    "سنندج":              ("summer", "summer spring", 0),
    "مریوان":             ("summer", "summer", 0),
    "کرمانشاه":           ("summer", "summer spring", 0),
    "گرگان":              ("autumn", "autumn spring", 0),
    "بندر لنگه (کیش)":    ("winter autumn", "winter autumn spring", -5),
    "قشم":                ("winter autumn", "winter autumn spring", -5),
    "چابهار":             ("winter", "winter spring", -5),
    "بندرعباس":           ("winter", "winter spring", -5),
    "بوشهر":              ("winter", "winter spring", -5),
    "گناوه":              ("spring", "spring winter", -5),
    "اهواز":              ("winter", "winter spring", 0),
    "آبادان":             ("winter", "winter", 0),
    "خرم‌آباد":           ("spring", "spring", 0),
    "کرمان":              ("autumn", "autumn winter", 0),
}

SEASONS = [("spring", "بهار"), ("summer", "تابستان"), ("autumn", "پاییز"), ("winter", "زمستان")]


def queue_rows():
    rows = {}
    for line in QUEUE.read_text(encoding="utf-8").splitlines():
        m = re.match(r"^\| (\d+) \| شهرستان (.+?) \|", line)
        if m:
            rows[m.group(2).strip()] = int(m.group(1))
    return rows


def score():
    rows = queue_rows()
    hq_rank = {}
    for i, name in enumerate(HQ_NOWRUZ_1404, start=1):
        county = name.split(":")[0]
        hq_rank.setdefault(county, i)  # بهترین رتبهٔ شهرستان
    out = []
    for city, (peak, present, trend) in CITIES.items():
        a = 0.0
        notes = []
        if city in hq_rank:
            r = hq_rank[city]
            a += round(15 * (41 - r) / 40, 1)
            notes.append(f"ستاد سفر نوروز#{r}")
        a += ALIBABA_YEAR_HOTEL.get(city, 0) + ALIBABA_YEAR_ROUTE.get(city, 0)
        n = sum(city in v for v in ALIBABA_NOWRUZ.values())
        a += 2 * n
        if city in HQ_BOOKING:
            a += 4
        a += JABAMA.get(city, 0)
        if city in JABAMA_NOWRUZ_1405_RISERS:
            a += 3
        if city in JABAMA_POPULAR:
            a += 2
        a += FLYTODAY.get(city, 0) + AUTUMN_TOURS.get(city, 0) + EVENTS.get(city, 0)
        b = min(12, 3 * len(GT_SEASONS.get(city, "").split()))
        row = rows.get(city)
        if row is None and city == "بندر لنگه (کیش)":
            row = rows.get("بندر لنگه (کیش)")
        c = 10 if row and row <= 37 else 5 if row and row <= 60 else 2 if row else 0
        total = round(a + b + c + trend, 1)
        out.append({
            "city": city, "row": row, "demand": round(a, 1), "trends": b,
            "ready": c, "trend": trend, "total": total,
            "peak": peak.split(), "present": present.split(),
        })
    out.sort(key=lambda x: -x["total"])
    return out


def main():
    data = score()
    if "--json" in sys.argv:
        print(json.dumps(data, ensure_ascii=False, indent=1))
        return
    print("| رتبه | شهرستان | ردیف صف | تقاضا (A) | ترندز (B) | آمادگی (C) | روند (D) | جمع |")
    print("|---|---|---|---|---|---|---|---|")
    for i, d in enumerate(data, 1):
        print(f"| {i} | {d['city']} | {d['row'] or '—'} | {d['demand']} | {d['trends']} | {d['ready']} | {d['trend']:+d} | **{d['total']}** |")
    print("\n### ارزش هر صفحهٔ فصل (جمع امتیاز ۱۰ شهرستان اول آن فصل)\n")
    print("| فصل | جمع ۱۰ اول | از ۱۰ اول چند شهرستان نوشته شده (ردیف ۱–۳۷) |")
    print("|---|---|---|")
    for key, fa in SEASONS:
        items = sorted(((d["total"] + (8 if key in d["peak"] else 0), d) for d in data if key in d["present"]), key=lambda x: -x[0])[:10]
        done = sum(1 for _, d in items if d["row"] and d["row"] <= 37)
        print(f"| {fa} | {round(sum(s for s, _ in items), 1)} | {done} از ۱۰ |")
    for key, fa in SEASONS:
        items = [(d["total"] + (8 if key in d["peak"] else 0), d) for d in data if key in d["present"]]
        items.sort(key=lambda x: -x[0])
        print(f"\n### {fa}\n")
        print("| رتبه | شهرستان | امتیاز فصلی | اوجِ همین فصل؟ |")
        print("|---|---|---|---|")
        for i, (s, d) in enumerate(items, 1):
            print(f"| {i} | {d['city']} | {round(s, 1)} | {'بله' if key in d['peak'] else '—'} |")


if __name__ == "__main__":
    main()
