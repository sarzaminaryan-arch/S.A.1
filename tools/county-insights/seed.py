#!/usr/bin/env python3
"""Turn vetted «از تجربهٔ مسافرها» raw records into theme seed entries.

Replaces the bundled seed file (data/county-experiences.php data array) from
data/raw/*.json — the single source the owner reviews — so every county that
has a vetted record shows the same traveller block on the live page.

Guarantees before anything is written:
  * the record passes county_insights.validate (same schema/limits);
  * every field is non-empty (the block must not look half-built);
  * the whole file passes the site's banned-word rules: «باید», «راستش»,
    AI/process wording, not-yet-open projects, «اسرائیل», raw http links.
The banned-word hits inherited from older copy (e.g. «اهواز را باید شب شناخت»)
are reported per file so they can be re-worded, never silently shipped.

Usage (from the repo root):
    python3 tools/county-insights/seed.py check          # verify only
    python3 tools/county-insights/seed.py write          # rewrite the seed file
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import county_insights as ci  # noqa: E402

ROOT = Path(__file__).resolve().parent
THEME = ROOT.parent.parent / "wp-content" / "themes" / "sarzaminaryan-child"
SEED = THEME / "data" / "county-experiences.php"

BANNED = (
    "باید",
    "راستش",
    "به‌زودی",
    "در حال ساخت",
    "در حال احداث",
    "در حال تکمیل",
    "در حال نهایی",
    "در حال اجرا",
    "کلنگ",
    "آمادهٔ افتتاح",
    "هنوز کامل نیست",
    "اسرائیل",
    "اسراییل",
    "بازبینی انسانی",
    "هوش مصنوعی",
    "متادیتا",
    "http",
)
REQUIRED = (
    "road_quality",
    "mobile_internet",
    "access_difficulty",
    "parking_status",
    "season_spring",
    "season_summer",
    "season_autumn",
    "season_winter",
    "local_tips",
    "extra_places",
    "people_character",
    "field_update_note",
)


def _php(value: str) -> str:
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def entry(slug: str, rec: dict) -> str:
    meta = ci.to_meta(rec)
    meta.setdefault("updated_at", str(rec.get("updated_at") or ""))
    lines = [f"\t{_php(slug)} => array("]
    for key in ("road_quality", "mobile_internet", "access_difficulty", "parking_status",
                "season_spring", "season_summer", "season_autumn", "season_winter",
                "local_tips", "extra_places", "people_character", "plate_code",
                "field_update_note", "updated_at"):
        value = meta.get(key, "")
        if key == "plate_code" and not str(value).strip():
            continue  # county-plates.php still fills tile 12 for counties without a record value
        lines.append(f"\t\t{_php(key)} => {_php(value)},")
    lines.append("\t),")
    return "\n".join(lines)


def collect() -> tuple[dict[str, dict], list[str]]:
    problems: list[str] = []
    out: dict[str, dict] = {}
    for path in sorted(ci.RAW_DIR.glob("*.json")):
        rec = json.loads(path.read_text(encoding="utf-8"))
        errors, _ = ci.validate_record(rec, path.name)
        if errors:
            problems += errors
            continue
        slug = str(rec["county_slug"]).strip()
        meta = ci.to_meta(rec)
        missing = [k for k in REQUIRED if not str(meta.get(k) or "").strip()]
        if missing:
            problems.append(f"{path.name}: فیلد خالی برای بلوک تجربه: {', '.join(missing)}")
            continue
        blob = json.dumps(meta, ensure_ascii=False)
        hits = sorted({b for b in BANNED if b in blob})
        if hits:
            problems.append(f"{path.name}: واژهٔ ممنوع {hits}")
            continue
        out[slug] = rec
    return out, problems


def check() -> int:
    records, problems = collect()
    for p in problems:
        print("ERROR", p)
    print(f"records: {len(records)}")
    total_tips = sum(len(r["local_tips"]) for r in records.values())
    total_places = sum(len(r["extra_places"]) for r in records.values())
    print(f"tips: {total_tips} · places: {total_places}")
    live = SEED.read_text(encoding="utf-8") if SEED.exists() else ""
    known = sorted(set(__import__("re").findall(r"^\t'([a-z0-9-]+)' => array", live, __import__("re").M)))
    print("in seed now:", len(known))
    print("would add:", len(set(records) - set(known)), sorted(set(records) - set(known)))
    print("in seed but no raw record:", len(set(known) - set(records)), sorted(set(known) - set(records)))
    return 1 if problems else 0


def write() -> int:
    records, problems = collect()
    if problems:
        for p in problems:
            print("ERROR", p)
        print("seed not written: fix the records first")
        return 1
    src = SEED.read_text(encoding="utf-8")
    start = src.index("return array(")
    body = "".join(entry(slug, records[slug]) + "\n" for slug in sorted(records))
    head = src[:start]
    SEED.write_text(head + "return array(\n" + body + ");\n", encoding="utf-8")
    print(f"seed written: {len(records)} counties")
    return 0


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "check"
    sys.exit(check() if cmd == "check" else write())
