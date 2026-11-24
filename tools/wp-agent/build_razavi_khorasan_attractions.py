#!/usr/bin/env python3
"""Build and lint all 10 Razavi Khorasan Province attraction sub-articles."""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "replace_src"))
sys.path.insert(0, str(ROOT / "cluster_data"))

import fa_lint  # noqa: E402
from razavi_khorasan_attractions import ATTRACTIONS  # noqa: E402

OUT_DIR = ROOT / "outbox" / "20261124-razavi-khorasan-attractions"
OUT_DIR.mkdir(parents=True, exist_ok=True)


def build_one(item: dict) -> dict:
    parts = [f"<p>{item['intro']}</p>", f"<h2>پیشینه و جایگاه تاریخی</h2>\n<p>{item['history']}</p>"]
    for heading, body in item["sections"]:
        parts.append(f"<h2>{heading}</h2>\n<p>{body}</p>")
    parts.append(f"<h2>دیدنی‌های نزدیک و هم‌مسیر</h2>\n<p>{item['nearby']}</p>")
    html = "\n\n".join(parts) + "\n"

    meta = {
        "sa_province_id": str(item["province_id"]),
        "sa_attr_area": item["area"],
        "sa_address": item["address"],
        "sa_best_time": item["best_time"],
        "sa_visit_duration": item["visit_duration"],
        "sa_suitable_for": item["suitable_for"],
        "sa_opening_hours": item["opening_hours"],
        "sa_ticket_price": item["ticket_price"],
        "sa_elevation": item["elevation"],
        "sa_attraction_age": item["age"],
        "sa_access_level": item["access_level"],
        "sa_latitude": item["lat"],
        "sa_longitude": item["lng"],
        "sa_last_verified_date": "۱۴۰۵/۰۹/۰۴",
        "sa_seo_title": f"{item['title']}؛ راهنمای بازدید، دسترسی و نکات سفر",
        "sa_seo_description": f"راهنمای کامل بازدید از {item['title']} در {item['county_name']}: پیشینه، مسیر دسترسی، ساعات بازدید، هزینه ورود و دیدنی‌های نزدیک.",
        "sa_og_title": f"{item['title']}؛ راهنمای بازدید، دسترسی و نکات سفر",
        "sa_og_description": f"راهنمای کامل بازدید از {item['title']} در {item['county_name']}: پیشینه، مسیر دسترسی، ساعات بازدید و دیدنی‌های نزدیک.",
        "sa_focus_keyword": item["title"],
        "sa_faq": json.dumps(item["faq"], ensure_ascii=False),
        "sa_sources": "\n".join(item["sources"]),
    }

    issues = fa_lint.scan(html) + fa_lint.scan_meta(meta)
    if issues:
        raise RuntimeError(f"{item['slug']}: {issues[:5]}")

    (OUT_DIR / f"{item['slug']}.html").write_text(html, encoding="utf-8")
    (OUT_DIR / f"{item['slug']}.meta.json").write_text(
        json.dumps(meta, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    words = len(re.sub(r"<[^>]+>", "", html).split())
    return {"slug": item["slug"], "title": item["title"], "county_id": item["county_id"], "words": words}


def main() -> None:
    manifest = [build_one(it) for it in ATTRACTIONS]
    (OUT_DIR / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    for m in manifest:
        print(f"OK  {m['slug']:28s} ({m['words']} words)")
    print(f"BUILT {len(manifest)}/10 RAZAVI KHORASAN ATTRACTIONS.")


if __name__ == "__main__":
    main()
