#!/usr/bin/env python3
"""دفتر شهرستان‌ها — یک منبع مرجع و یک ردیف وضعیت برای هر شهرستان.

چرا این فایل (دستور مالک، ۱۴۰۵/۰۸/۲۶): «هر شهرستان یک منبع معتبر» و «اطمینان از
صحت اطلاعات». امروز این دو در هیچ‌جا ثبت نشده‌اند: منبع مرجع شهرستان در جایی
نیست که هنگام اختلاف دو عدد داور باشد، و وضعیت تطبیق بدنه فقط در گزارش‌های
پراکنده بود.

مصرف:

    python3 tools/wp-agent/county_ledger.py build
    python3 tools/wp-agent/county_ledger.py check ramhormoz
    python3 tools/wp-agent/county_ledger.py set ramhormoz --authority "..." --note "..."
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent

QUEUE = ROOT / "docs/live-site/county-research-queue.md"
LEDGER = ROOT / "docs/live-site/county-source-ledger.md"
RAW = ROOT / "tools/county-insights/data/raw"
SEED = ROOT / "wp-content/themes/sarzaminaryan-child/data/county-experiences.php"
SUMMARY = ROOT / "tools/wp-agent/migration-summary.json"
# وضعیت در JSON نگه داشته می‌شود و Markdown از آن ساخته می‌شود: خواندن وضعیت از
# روی جدولِ خودمان (که کاربر هم ممکن است ویرایش کند) یک منبع خطاست.
STATE = ROOT / "tools/wp-agent/county_ledger.json"

SLUG_HINTS = {
    "تهران": "tehran", "مشهد": "mashhad", "اصفهان": "isfahan", "کرج": "karaj",
    "شیراز": "shiraz", "تبریز": "tabriz", "قم": "qom", "اهواز": "ahvaz",
    "کرمانشاه": "kermanshah", "ارومیه": "urmia", "رشت": "rasht", "زاهدان": "zahedan",
    "همدان": "hamedan", "کرمان": "kerman", "یزد": "yazd", "اردبیل": "ardabil-city",
    "بندرعباس": "bandar-e-abbas", "اراک": "arak", "اسلامشهر": "eslamshahr",
    "زنجان": "zanjan", "سنندج": "sanandaj", "قزوین": "qazvin", "خرم‌آباد": "khorramabad",
    "گرگان": "gorgan", "ساری": "sari", "بجنورد": "bojnord",
    # دستهٔ ۹: این ده شهرستان رکورد raw ندارند، پس بدون راهنما ردیفشان «—» می‌شد.
    "نور": "nur", "تالش": "talesh", "ماسال": "masal", "سرعین": "sarein", "جلفا": "jolfa",
    "مرودشت": "marvdasht", "کازرون": "kazerun", "جهرم": "jahrom", "لارستان": "larestan",
    "گناوه": "ganaveh",
}

NOT_AUTHORITY = ("wikipedia", "kojaro", "karnaval", "bartarinha", "google", "neshan",
                 "balad", "aparat", "instagram", "telegram", "blogfa")


def read_queue():
    text = QUEUE.read_text(encoding="utf-8")
    rows = re.findall(r"^\| (\d+) \| ([^|]+) \| ([^|]+) \|$", text, re.M)
    return [(int(n), name.strip(), status.strip()) for n, name, status in rows]


def bare_name(name):
    name = re.sub(r"^شهرستان\s+", "", name)
    return re.sub(r"\s*\([^)]*\)\s*$", "", name).strip()


def slugs_in_seed():
    if not SEED.is_file():
        return set()
    return set(re.findall(r"^\t'([a-z0-9\-]+)' => array\(", SEED.read_text(encoding="utf-8"), re.M))


def raw_slugs():
    return {p.stem for p in RAW.glob("*.json")} if RAW.is_dir() else set()


def read_ledger():
    """{slug: {authority, note}} — از JSON وضعیت، نه از جدول Markdown."""
    if not STATE.is_file():
        return {}
    try:
        data = json.loads(STATE.read_text(encoding="utf-8"))
    except Exception:  # noqa: BLE001
        return {}
    return {k: dict(v) for k, v in (data.get("counties") or {}).items()}


def write_ledger(ledger: dict) -> None:
    STATE.write_text(json.dumps({"counties": ledger}, ensure_ascii=False, indent=1,
                                sort_keys=True) + "\n", encoding="utf-8")


def name_index():
    idx = {}
    for path in RAW.glob("*.json"):
        try:
            rec = json.loads(path.read_text(encoding="utf-8"))
        except Exception:
            continue
        name = str(rec.get("county_name") or "").strip()
        if name:
            idx[name] = path.stem
    return idx


def survey_rows():
    if not SUMMARY.is_file():
        return {}
    try:
        rows = json.loads(SUMMARY.read_text(encoding="utf-8"))
    except Exception:
        return {}
    return {r["slug"]: r for r in rows if isinstance(r, dict) and r.get("slug")}


def build(update=None):
    queue = read_queue()
    seed, raws, mig, names = slugs_in_seed(), raw_slugs(), survey_rows(), name_index()
    ledger = read_ledger()
    for slug, vals in (update or {}).items():
        ledger.setdefault(slug, {}).update(vals)

    lines = [
        "# دفتر شهرستان‌ها: منبع مرجع و وضعیت تطبیق",
        "",
        "این فایل با `python3 tools/wp-agent/county_ledger.py build` ساخته می‌شود؛",
        "دست‌نویس ویرایش نکنید. ستون «منبع مرجع» را با `set` پر کنید.",
        "",
        "**قاعدهٔ مالک:** هر شهرستان یک منبع مرجع دارد که هنگام اختلاف دو عدد داور است.",
        "منبع مرجع در متن مقاله اسم نمی‌آید؛ در `sa_sources` و در همین دفتر ثبت می‌شود.",
        "تجربهٔ مردم (گوگل‌مپ، نشان، بلد) منبع مرجع نیست: آن‌ها به بلوک «از تجربهٔ",
        "مسافران» می‌روند، با نقل‌قول و تاریخ دیده‌شدن (`county_insights.py validate`).",
        "",
        "| # | نامک | منبع مرجع | تجربهٔ مسافران | بدنه | یادداشت |",
        "| --- | --- | --- | --- | --- | --- |",
    ]
    ready, rows = 0, []
    for n, name, _status in queue:
        bare = bare_name(name)
        slug = SLUG_HINTS.get(bare) or names.get(bare) or "—"
        rec = ledger.get(slug, {})
        authority = rec.get("authority") or "— تعیین نشده"
        if rec.get("authority"):
            ready += 1
        exp = "در صف"
        if slug != "—" and slug in seed:
            exp = "در قالب + دادهٔ خام"
        elif slug != "—" and slug in raws:
            exp = "دادهٔ خام آماده، وارد نشده"
        body = "—"
        r = mig.get(slug)
        if r:
            tier = r.get("tier")
            if tier == "already":
                body = "روی قالب مستر"
            elif tier == "ready":
                body = "اسکلت آماده + دو بخش باقی"
            elif tier == "skeleton":
                need = "، ".join(r.get("missing") or []) or "کف واژه"
                body = "اسکلت؛ مانده: " + need
            else:
                body = "بازبینی دستی"
        lines.append("| {} | `{}` | {} | {} | {} | {} |".format(
            n, slug, authority, exp, body, rec.get("note", "")))
        rows.append(slug)

    lines += [
        "",
        "**منبع مرجع ثبت‌شده: {} از {}**".format(ready, len(queue)),
        "",
        "## انتخاب منبع مرجع، به تفکیک موضوع",
        "",
        "1. **تقسیمات کشوری و جمعیت** ← مرکز آمار ایران (سرشماری) یا سالنامهٔ استانداری.",
        "2. **آثار و محوطه‌ها** ← پروندهٔ ثبت ملی در سازمان میراث فرهنگی (شمارهٔ ثبت + تاریخ).",
        "3. **نام‌های محلی و وجه تسمیه** ← دانشگاه/فرهنگستان یا کتاب مرجع محلی، نه وبلاگ.",
        "4. **زیرساخت و راه** ← ادارهٔ راه یا خبر رسمی همان استان، با تاریخ.",
        "",
        "هرگز منبع مرجع نیستند: " + "، ".join(NOT_AUTHORITY) + ".",
        "",
    ]
    write_ledger(ledger)
    LEDGER.write_text("\n".join(lines), encoding="utf-8")
    where = LEDGER.relative_to(ROOT) if str(LEDGER).startswith(str(ROOT)) else LEDGER
    print("ledger → {} · authority known for {}/{}".format(where, ready, len(queue)))
    return 0


def slug_for(name: str) -> str:
    """نام فارسی → نامک: از راهنمای ثابت، بعد از رکوردهای خام تجربه."""
    return SLUG_HINTS.get(name) or name_index().get(name, "")


def cmd_check(args):
    ledger = read_ledger()
    if not args.slugs:
        queue = read_queue()
        known = sum(1 for _, name, _s in queue
                    if (ledger.get(slug_for(bare_name(name)), {}) or {}).get("authority"))
        print("{}/{} counties have an authoritative source on record".format(known, len(queue)))
        return 0
    bad = 0
    for slug in args.slugs:
        auth = (ledger.get(slug) or {}).get("authority", "")
        if not auth:
            print("HOLD {}: no authoritative source on record "
                  "(county_ledger.py set {} --authority \"...\")".format(slug, slug))
            bad += 1
        elif any(x in auth.lower() for x in NOT_AUTHORITY + ("ویکی‌پدیا", "ویکی پدیا", "کجارو", "کارناوال")):
            print("HOLD {}: «{}» is not an authoritative source".format(slug, auth))
            bad += 1
        else:
            print("OK   {}: {}".format(slug, auth))
    return 1 if bad else 0


def cmd_set(args):
    update = {args.slug: {}}
    if args.authority:
        update[args.slug]["authority"] = args.authority
    if getattr(args, "note", ""):
        update[args.slug]["note"] = args.note
    return build(update)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("build").set_defaults(func=lambda a: build())
    c = sub.add_parser("check")
    c.add_argument("slugs", nargs="*")
    c.set_defaults(func=cmd_check)
    s = sub.add_parser("set")
    s.add_argument("slug")
    s.add_argument("--authority", default="")
    s.add_argument("--note", default="")
    s.set_defaults(func=cmd_set)
    args = ap.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
