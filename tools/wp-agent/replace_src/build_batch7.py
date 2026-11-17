#!/usr/bin/env python3
"""دستهٔ ۷ — ساخت فایل دادهٔ شهرستان از متن نویسنده + متای زندهٔ خوانده‌شده.

هر شهرستان دو تکه دارد:
  ۱) `replace_src/batch7/<slug>.content.json` — فقط چیزهایی که نویسنده می‌گوید:
     شعار، پاراگراف‌های شش بخش، فهرست‌ها، منابع، پرسش‌ها و اصلاح‌های متا.
  ۲) متای زندهٔ همان صفحه از نتیجهٔ کار خواندن (`results/20261119-01-…json`).

این اسکریپت آن دو را جوش می‌دهد و `replace_src/data/<slug>.json` را می‌سازد؛
بعد `county_page.py <slug>` همان مسیر همیشگی را می‌رود (فیلتر املا، رجیستری
قالب، سه فایل outbox).

چرا جدا: متای زنده سی تا چهل کلید دارد و بازنویسی دستی‌اش جای خطاست؛ ولی
هر کلیدی که در `meta_overrides` بیاید، مقدار تازه می‌گیرد و در گزارش چاپ
می‌شود تا معلوم باشد چه چیزی از چه به چه رفت.

    python3 tools/wp-agent/replace_src/build_batch7.py --all
    python3 tools/wp-agent/replace_src/build_batch7.py bushehr-county
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent.parent
# خواندنِ زنده و پوشهٔ متن هر دسته با --read/--content عوض می‌شود؛ پیش‌فرض همان
# دستهٔ ۷ است تا فرمان‌های پیشین بی‌تغییر کار کنند.
READ = REPO / "tools/wp-agent/results/20261119-01-read-batch7-ten.json"
CONTENT = HERE / "batch7"
DATA = HERE / "data"

# کلیدهایی که هر صفحهٔ تازه باید داشته باشد (قالب مستر، ۲۲ آبان).
REQUIRED_META = (
    "sa_city_slug", "lat", "lng", "sa_cty_center", "sa_cty_population",
    "sa_cty_census_year", "sa_cty_neighbors", "sa_access_road",
    "sa_access_rail", "sa_access_air", "sa_seo_title", "sa_seo_description",
    "sa_focus_keyword", "sa_og_title", "sa_og_description", "sa_faq",
    "sa_map_zoom", "sa_schema_contained_in", "sa_schema_sameas",
)


def live_pages(read_path: Path | None = None) -> dict:
    data = json.loads((read_path or READ).read_text(encoding="utf-8"))
    out = {}
    for step in data.get("steps", []):
        res = step.get("result")
        if isinstance(res, dict) and res.get("slug"):
            out[res["slug"]] = res
    return out


def build(slug: str, pages: dict) -> Path:
    page = pages[slug]
    spec = json.loads((CONTENT / f"{slug}.json").read_text(encoding="utf-8"))

    meta = {}
    for key, value in (page.get("meta") or {}).items():
        if key.startswith("cc_rating") or key == "_thumbnail_id":
            meta[key] = value            # امتیاز کاربران و تصویر شاخص دست نمی‌خورد
            continue
        meta[key] = value
    for junk in ("sa_sources", "sa_content_scope", "sa_featured_image_importer"):
        meta.pop(junk, None)          # منابع را county_page می‌سازد؛ این دو کلیدِ دفتری‌اند
      # منابع را county_page از فایل sources می‌سازد
    for key, value in spec["meta_overrides"].items():
        if meta.get(key) != value:
            print(f"  ~ {slug}: {key}: {str(meta.get(key))[:60]!r} → {str(value)[:60]!r}")
        meta[key] = value

    data = {
        "id": page["id"],
        "slug": slug,
        "name": spec["name"],
        "tagline": spec["tagline"],
        "lat": spec["lat"],
        "lng": spec["lng"],
        "neighbors_text": spec["neighbors_text"],
        "neighbors": [],
        "sources": spec["sources"],
        "meta": meta,
    }
    for key in ("intro", "map", "history", "people", "economy", "stay", "notables"):
        if key in spec["sections"]:
            data[key] = spec["sections"][key]
    for key in ("people_list", "economy_list", "stay_list"):
        if key in spec.get("lists", {}):
            data[key] = spec["lists"][key]

    meta["sa_faq"] = json.dumps(spec["faq"], ensure_ascii=False)

    missing = [k for k in REQUIRED_META if not str(meta.get(k) or data.get(k) or "").strip()]
    if missing:
        raise SystemExit(f"{slug}: required meta missing: {missing}")

    out = DATA / f"{slug}.json"
    out.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    return out


def main(argv=None) -> int:
    global CONTENT
    ap = argparse.ArgumentParser()
    ap.add_argument("slugs", nargs="*")
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--read", help="نتیجهٔ کار خواندن (پیش‌فرض: دستهٔ ۷)")
    ap.add_argument("--content", help="پوشهٔ متن دسته (پیش‌فرض: replace_src/batch7)")
    args = ap.parse_args(argv)
    if args.content:
        CONTENT = (HERE / args.content) if not Path(args.content).is_absolute() else Path(args.content)
    read_path = None
    if args.read:
        read_path = Path(args.read) if Path(args.read).is_absolute() else REPO / args.read
    pages = live_pages(read_path)
    slugs = sorted(p.stem for p in CONTENT.glob("*.json")) if args.all else args.slugs
    for slug in slugs:
        if slug not in pages:
            raise SystemExit(f"{slug}: not in the read result")
        print(f"{slug}: {build(slug, pages).relative_to(REPO)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
