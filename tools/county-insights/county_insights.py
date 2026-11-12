#!/usr/bin/env python3
"""اعتبارسنجی و ادغام خروجی‌های استخراج «از تجربهٔ مسافرها» (CU-1/CU-2).

Reads data/raw/<slug>.json (one extraction per county, produced with
extract_prompt.md), validates them against the locked schema, merges them
into data/counties-insights.json and emits, per county:

  out/<slug>.meta.json   canonical county-profile keys ready to enter in the
                         «شناسنامهٔ شهرستان» meta box (tips/places serialized
                         to the theme's line format)
  out/<slug>.preview.md  human-readable preview for the owner's review

Rules mirrored from the theme (inc/county-profile.php): empty renders
nothing, tip categories are warning/culture/logistics only, a place needs a
name, and nothing here publishes anything by itself — the owner's approval
gate stays the only publish condition.

Usage:
    python3 county_insights.py validate [file.json ...]
    python3 county_insights.py build
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
RAW_DIR = ROOT / "data" / "raw"
MERGED = ROOT / "data" / "counties-insights.json"
OUT_DIR = ROOT / "out"

CATEGORIES = {"warning", "culture", "logistics"}
SEASON_KEYS = ("spring", "summer", "autumn", "winter")
QUICK_KEYS = ("road_quality", "mobile_internet", "access_difficulty", "parking_status")
SEASON_FA = {"spring": "بهار", "summer": "تابستان", "autumn": "پاییز", "winter": "زمستان"}
MAX_TIP_WORDS = 45
MAX_PLACES = 14
MAX_TIPS = 10


def _words(text: str) -> int:
    return len(str(text).split())


def validate_record(rec: dict, fname: str) -> tuple[list[str], list[str]]:
    errors: list[str] = []
    warns: list[str] = []

    if not isinstance(rec, dict):
        return [f"{fname}: رکورد باید آبجکت JSON باشد"], warns
    for key in ("county_slug", "county_name"):
        if not str(rec.get(key) or "").strip():
            errors.append(f"{fname}: {key} خالی است")
    if rec.get("status") not in (None, "draft_needs_review", "verified"):
        errors.append(f"{fname}: status نامعتبر: {rec.get('status')!r}")
    if rec.get("status") in (None, "draft_needs_review"):
        warns.append(f"{fname}: هنوز راستی‌آزمایی انسانی نشده (status=draft_needs_review)")

    qf = rec.get("quick_facts") or {}
    if not isinstance(qf, dict):
        errors.append(f"{fname}: quick_facts باید آبجکت باشد")
    else:
        for key in qf:
            if key not in QUICK_KEYS:
                errors.append(f"{fname}: کلید ناشناخته در quick_facts: {key}")

    seasons = rec.get("seasons") or {}
    if not isinstance(seasons, dict):
        errors.append(f"{fname}: seasons باید آبجکت باشد")
    else:
        for key in seasons:
            if key not in SEASON_KEYS:
                errors.append(f"{fname}: فصل ناشناخته: {key}")

    tips = rec.get("local_tips") or []
    if len(tips) > MAX_TIPS:
        warns.append(f"{fname}: نکته‌ها {len(tips)} تاست (سقف پیشنهادی {MAX_TIPS})")
    for i, tip in enumerate(tips):
        if tip.get("category") not in CATEGORIES:
            errors.append(f"{fname}: نکتهٔ {i + 1} دستهٔ نامعتبر دارد: {tip.get('category')!r}")
        if not str(tip.get("title") or "").strip() or not str(tip.get("text") or "").strip():
            errors.append(f"{fname}: نکتهٔ {i + 1} عنوان یا متن ندارد")
        if _words(tip.get("text") or "") > MAX_TIP_WORDS:
            warns.append(f"{fname}: متن نکتهٔ {i + 1} بلند است ({_words(tip.get('text'))} کلمه)")

    places = rec.get("extra_places") or []
    if len(places) > MAX_PLACES:
        warns.append(f"{fname}: جاها {len(places)} تاست (سقف پیشنهادی {MAX_PLACES})")
    for i, place in enumerate(places):
        if not str(place.get("name") or "").strip():
            errors.append(f"{fname}: جای {i + 1} نام ندارد")
        url = str(place.get("url") or "").strip()
        if url and not url.startswith(("http://", "https://")):
            errors.append(f"{fname}: پیوند جای {i + 1} معتبر نیست: {url!r}")

    if _words(rec.get("people_character") or "") > 130:
        warns.append(f"{fname}: «ویژگی مردم» بلند است؛ ۲ تا ۴ جمله کافی است")
    return errors, warns


def to_meta(rec: dict) -> dict:
    """Map one extraction record to canonical county-profile meta keys."""
    meta: dict[str, str] = {}
    qf = rec.get("quick_facts") or {}
    for key in QUICK_KEYS:
        if qf.get(key):
            meta[key] = str(qf[key]).strip()
    seasons = rec.get("seasons") or {}
    for key in SEASON_KEYS:
        if seasons.get(key):
            meta[f"season_{key}"] = str(seasons[key]).strip()
    tips = rec.get("local_tips") or []
    if tips:
        meta["local_tips"] = "\n".join(
            f"{t['category']} | {str(t.get('title', '')).strip()} | {str(t.get('text', '')).strip()}"
            for t in tips
        )
    places = rec.get("extra_places") or []
    if places:
        meta["extra_places"] = "\n".join(
            " | ".join(
                [
                    str(p.get("name", "")).strip(),
                    str(p.get("kind", "")).strip(),
                    str(p.get("access", "")).strip(),
                    str(p.get("note", "")).strip(),
                    str(p.get("url", "")).strip(),
                ]
            ).rstrip(" |")
            for p in places
        )
    if rec.get("people_character"):
        meta["people_character"] = str(rec["people_character"]).strip()
    if rec.get("field_update_note"):
        meta["field_update_note"] = str(rec["field_update_note"]).strip()
    return meta


def preview(rec: dict, meta: dict) -> str:
    lines = [f"# {rec.get('county_name')} ({rec.get('county_slug')})", ""]
    lines.append(f"وضعیت: {rec.get('status') or 'draft_needs_review'}")
    if meta.get("field_update_note"):
        lines.append(f"آخرین به‌روزرسانی: {meta['field_update_note']}")
    lines += ["", "## دسترسی در یک نگاه", ""]
    for key, label in (
        ("road_quality", "وضعیت جاده"),
        ("mobile_internet", "آنتن و اینترنت"),
        ("access_difficulty", "سختی دسترسی"),
        ("parking_status", "پارکینگ"),
    ):
        if meta.get(key):
            lines.append(f"- **{label}:** {meta[key]}")
    if any(meta.get(f"season_{k}") for k in SEASON_KEYS):
        lines += ["", "## چهار فصل", ""]
        for key in SEASON_KEYS:
            if meta.get(f"season_{key}"):
                lines.append(f"- **{SEASON_FA[key]}:** {meta[f'season_{key}']}")
    if rec.get("extra_places"):
        lines += ["", "## جاهای دیگری که مردم می‌روند", ""]
        for p in rec["extra_places"]:
            lines.append(f"- **{p.get('name')}** ({p.get('kind', '')}) — {p.get('access', '')} · {p.get('note', '')}")
    if rec.get("local_tips"):
        lines += ["", "## نکات بومی", ""]
        for t in rec["local_tips"]:
            lines.append(f"- [{t.get('category')}] **{t.get('title')}:** {t.get('text')}")
    if rec.get("people_character"):
        lines += ["", "## مردم از نگاه مسافرها", "", str(rec["people_character"])]
    lines.append("")
    return "\n".join(lines)


def _load(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def cmd_validate(files: list[str]) -> int:
    paths = [Path(f) for f in files] if files else sorted(RAW_DIR.glob("*.json"))
    if not paths:
        print("فایلی برای اعتبارسنجی نیست (data/raw/*.json)")
        return 1
    bad = 0
    for path in paths:
        try:
            rec = _load(path)
        except Exception as exc:  # noqa: BLE001
            print(f"ERROR {path.name}: JSON نامعتبر: {exc}")
            bad += 1
            continue
        errors, warns = validate_record(rec, path.name)
        for w in warns:
            print(f"WARN  {w}")
        for e in errors:
            print(f"ERROR {e}")
        if errors:
            bad += 1
        else:
            print(f"OK    {path.name} ({rec.get('county_name')})")
    return 1 if bad else 0


def cmd_build() -> int:
    paths = sorted(RAW_DIR.glob("*.json"))
    if not paths:
        print("data/raw خالی است؛ اول خروجی استخراج هر شهرستان را آن‌جا بگذارید")
        return 1
    merged: dict[str, dict] = {}
    bad = 0
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for path in paths:
        try:
            rec = _load(path)
        except Exception as exc:  # noqa: BLE001
            print(f"ERROR {path.name}: JSON نامعتبر: {exc}")
            bad += 1
            continue
        errors, warns = validate_record(rec, path.name)
        for w in warns:
            print(f"WARN  {w}")
        for e in errors:
            print(f"ERROR {e}")
        if errors:
            bad += 1
            continue
        slug = str(rec["county_slug"]).strip()
        merged[slug] = rec
        meta = to_meta(rec)
        (OUT_DIR / f"{slug}.meta.json").write_text(
            json.dumps(meta, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        (OUT_DIR / f"{slug}.preview.md").write_text(preview(rec, meta), encoding="utf-8")
        print(f"OK    {slug}: {len(meta)} کلید، {len(rec.get('local_tips') or [])} نکته، {len(rec.get('extra_places') or [])} جا")
    MERGED.write_text(json.dumps(merged, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"ادغام شد: {MERGED.relative_to(ROOT)} ({len(merged)} شهرستان)")
    return 1 if bad else 0


def main() -> int:
    parser = argparse.ArgumentParser(description="ابزار دادهٔ «از تجربهٔ مسافرها» شهرستان‌ها")
    sub = parser.add_subparsers(dest="cmd", required=True)
    p_val = sub.add_parser("validate", help="اعتبارسنجی فایل‌های استخراج")
    p_val.add_argument("files", nargs="*")
    sub.add_parser("build", help="اعتبارسنجی + ادغام + خروجی متا و پیش‌نمایش")
    args = parser.parse_args()
    if args.cmd == "validate":
        return cmd_validate(args.files)
    return cmd_build()


if __name__ == "__main__":
    sys.exit(main())
