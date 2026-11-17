#!/usr/bin/env python3
"""فهرست کار تمپلیت — هر ۴۸۵ شهرستان، به ترتیب بزرگی و معروفیت، در دسته‌های ده‌تایی.

دستور مالک (۲۶ آبان ۱۴۰۵): «به ترتیب شهرستان‌های بزرگ و معروف ایران به پایین…
هر بار ده شهرستان را چک کن، اختلافات با تمپلیت را تصحیح کن و اطلاعات تازه اضافه کن.»
پس دو چیز لازم است که هر دو اندازه‌پذیر باشند، نه حدسی:

  ۱) **ترتیب**: صف مالک (۱۱۷ ردیف، تا ردیف ۳۸ نوشته شده) پایه است؛ بعد از آن
     شهرستان‌هایی که مرکزشان در فهرست شهرهای بزرگ ایران است (فهرست زیر، تقریبی
     از جمعیت و شهرت)، بعد مراکز استان‌هایی که در صف نبودند، و آخر بقیه به
     ترتیب استان و نام.
  ۲) **وضعیت**: از فهرست زندهٔ ۴۸۸ صفحهٔ شهرستان (`20261119-02-inventory-city.json`):
     تعداد واژه روی سایت، تاریخ ویرایش، و این‌که آیا صفحه پیش‌تر با تمپلیت مستر
     بازنویسی شده یا نه (فهرست قطعی از `replace_src/data/*.json` و پوشه‌های
     `outbox/2026*-replace-*`).

خروجی: `docs/live-site/county-template-worklist.md` و همان داده در JSON.

    python3 tools/wp-agent/template_worklist.py
"""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
INVENTORY = ROOT / "tools/wp-agent/results/20261119-02-inventory-city.json"
COUNTIES = ROOT / "wp-content/themes/sarzaminaryan-child/data/counties.php"
OUT_MD = ROOT / "docs/live-site/county-template-worklist.md"
OUT_JSON = ROOT / "tools/wp-agent/template_worklist.json"

# شهرهای بزرگ و شناخته‌شدهٔ ایران، تقریباً به ترتیب جمعیت و شهرت. هر شهرستان که
# مرکزش در این فهرست باشد، پیش از بقیه می‌آید. (شهرهای زیر ۵۰ هزار نفر و
# شهرستان‌های تازه‌تأسیس عمداً نیامده‌اند.)
BIG_CITIES = """
تهران مشهد اصفهان کرج شیراز تبریز قم اهواز کرمانشاه ارومیه رشت زاهدان همدان کرمان یزد
اردبیل بندرعباس اراک اسلامشهر زنجان سنندج قزوین خرم‌آباد گرگان ساری شهریار قدس کاشان
ملارد دزفول نیشابور بابل خمینی‌شهر سبزوار بجنورد بوشهر بیرجند ایلام شهرکرد سمنان یاسوج
بندرلنگه قشم چابهار رامسر آمل قائم‌شهر لاهیجان بندر انزلی آستارا نوشهر چالوس تنکابن
بابلسر بهشهر گنبد کاووس گرگان علی‌آباد کتول شاهرود دامغان گرمسار ورامین رباط‌کریم
پاکدشت قرچک پردیس دماوند فیروزکوه ساوجبلاغ نظرآباد فردیس هشتگرد بروجرد ملایر نهاوند
تویسرکان اسدآباد کبودرآهنگ بهار ساوه خمین محلات دلیجان شازند سقز بانه مریوان مهاباد
بوکان میاندوآب خوی مرند مراغه میانه اهر سراب بناب سلماس ماکو نقده پیرانشهر اشنویه
تکاب شاهیندژ شاهین‌شهر نجف‌آباد خمینی‌شهر (اصفهان) شهرضا فلاورجان لنجان خوراسگان
کاشان آران و بیدگل نطنز گلپایگان خوانسار سمیرم فریدن چادگان برخوار

"""  # noqa: E501  (فهرست عمداً یک‌خطی-چندخطی است)

PROVINCE_CAPITALS = """
تهران مشهد اصفهان کرج شیراز تبریز قم اهواز کرمانشاه ارومیه رشت زاهدان همدان کرمان یزد
اردبیل بندرعباس اراک زنجان سنندج قزوین خرم‌آباد گرگان ساری بجنورد بوشهر بیرجند ایلام
شهرکرد سمنان یاسوج
"""


def parse_counties() -> list[dict]:
    src = COUNTIES.read_text(encoding="utf-8")
    rows = []
    for m in re.finditer(
        r"array\(\s*'slug' => '([^']+)',\s*'name' => '([^']+)',\s*'province' => '([^']+)'", src
    ):
        rows.append({"slug": m.group(1), "name": m.group(2), "province": m.group(3)})
    return rows


def inventory() -> dict:
    data = json.loads(INVENTORY.read_text(encoding="utf-8"))
    items = data["steps"][0]["result"]["city"]["items"]
    return {i["slug"]: i for i in items}


def rebuilt_slugs() -> set[str]:
    """صفحه‌هایی که با مسیر داده‌محور یا پوشهٔ replace بازنویسی شده‌اند."""
    out = set()
    for p in (ROOT / "tools/wp-agent/replace_src/data").glob("*.json"):
        out.add(p.stem)
    for d in (ROOT / "tools/wp-agent/outbox").glob("*replace*"):
        for f in d.glob("*.migration.json"):
            try:
                out.add(json.loads(f.read_text(encoding="utf-8"))["slug"])
            except Exception:                                  # noqa: BLE001
                pass
        for f in d.glob("*.html"):
            m = re.match(r"(.+)-(\d+)\.html$", f.name)
            if m:
                out.add(m.group(1))
    return out


def queue_positions() -> dict[str, int]:
    """ردیف صف مالک از `county-research-queue.md` (۱ تا ۱۱۷)."""
    path = ROOT / "docs/live-site/county-research-queue.md"
    pos = {}
    for m in re.finditer(r"^\|\s*(\d+)\s*\|\s*شهرستان\s+([^|]+?)\s*\|", path.read_text(encoding="utf-8"), re.M):
        name = m.group(2).split("(")[0].strip()
        pos.setdefault(name, int(m.group(1)))
    return pos


def main() -> int:
    counties = parse_counties()
    inv = inventory()
    alias = {}
    for slug, item in inv.items():
        alias.setdefault(slug.replace("-city", ""), item)
        alias.setdefault(slug + "-city", item)
    rebuilt = rebuilt_slugs()
    queue = queue_positions()
    big = [c.strip() for c in BIG_CITIES.split() if c.strip()]
    big_index = {name: i for i, name in enumerate(dict.fromkeys(big))}
    caps = {c.strip() for c in PROVINCE_CAPITALS.split() if c.strip()}

    rows = []
    for c in counties:
        live = inv.get(c["slug"]) or alias.get(c["slug"]) or {}
        if live and live.get("slug") != c["slug"]:
            c = dict(c, slug=live["slug"]) if live.get("slug") else c
        name = c["name"]
        is_big = big_index.get(name)
        is_cap = name in caps
        q = queue.get(name)
        # امتیاز: صف مالک اول، بعد شهرهای بزرگ، بعد مراکز استان.
        score = 0
        if q:
            score += 10_000 - q
        if is_big is not None:
            score += 5_000 - is_big
        if is_cap:
            score += 1_000
        rows.append({
            "slug": c["slug"], "name": name, "province": c["province"],
            "id": live.get("id"), "status": live.get("status"),
            "words": live.get("words"), "modified": live.get("modified_gmt"),
            "on_template": c["slug"] in rebuilt or c["slug"].replace("-city", "") in rebuilt,
            "queue": q, "big_rank": is_big, "capital": is_cap,
            "score": score,
        })
    rows.sort(key=lambda r: (-r["score"], r["province"], r["name"]))
    for i, r in enumerate(rows, 1):
        r["rank"] = i
        r["batch"] = (i - 1) // 10 + 1

    OUT_JSON.write_text(json.dumps(rows, ensure_ascii=False, indent=1), encoding="utf-8")

    done = sum(1 for r in rows if r["on_template"])
    solid = sum(1 for r in rows if (r["words"] or 0) >= 900)
    lines = [
        "# فهرست کار تمپلیت شهرستان‌ها — ۴۸۵ صفحه به ترتیب بزرگی و معروفیت",
        "",
        f"ساخته‌شده از فهرست زندهٔ {len(inv)} صفحهٔ شهرستان (کار ۱۲۰، ۲۶ آبان ۱۴۰۵) و "
        f"ردیف‌های صف مالک. هر دسته ۱۰ شهرستان است.",
        "",
        f"- **کل صفحه‌ها:** {len(rows)}",
        f"- **با بدنهٔ ≥ ۹۰۰ واژه:** {solid}",
        f"- **بازنویسی‌شده با مسیر تمپلیت (فایل داده یا پوشهٔ replace):** {done}",
        "",
        "**ترتیب:** صف مالک (۱ تا ۱۱۷) ← شهرهای بزرگ و شناخته‌شده ← مراکز استان ← بقیه "
        "به ترتیب استان و نام. «تمپلیت» یعنی صفحه بدنهٔ شش‌بخشی نشانه‌دار دارد؛ "
        "«—» یعنی هنوز نه.",
        "",
        "| # | دسته | شهرستان | استان | واژه | آخرین ویرایش | تمپلیت |",
        "| --- | --- | --- | --- | --- | --- | --- |",
    ]
    for r in rows:
        lines.append(
            f"| {r['rank']} | {r['batch']} | {r['name']} (`{r['slug']}`) | {r['province']} | "
            f"{r['words']} | {(r['modified'] or '')[:16]} | {'بله' if r['on_template'] else '—'} |"
        )
    OUT_MD.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(json.dumps({"pages": len(rows), "batches": (len(rows) - 1) // 10 + 1,
                      "rebuilt": done, "solid": solid}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
