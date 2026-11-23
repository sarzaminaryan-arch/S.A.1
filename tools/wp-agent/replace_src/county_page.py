#!/usr/bin/env python3
"""County page generator — the reusable base for every county (template 2.22.92).

Why this file exists (owner order, 2026-11-17): «تمپلیت رو آپدیت کن که برای
شهرستان‌های دیگه کارکرد کامل‌تری داشته باشه». Until now every county was a
hand-written `gen_<slug>.py` copied from `gen_ahvaz.py`; 480 pages later that
pattern is where the mistakes come from (a retired section creeping back, a
Gregorian month, a missing citation, a 600-word page). This module turns the
whole thing into one data file per county and one command:

    python3 replace_src/county_page.py maragheh     # build + guard, no writes

What one county file holds (`replace_src/data/<slug>.json`):

    {
      "id": 101, "slug": "maragheh", "name": "مراغه",
      "tagline": "پایتخت علمی ایران در روزگار ایلخانی",
      "intro":  [{"p": "…", "sources": ["irib_observatory"]}, "…"],
      "map":    [...], "history": [...], "people": [...], "economy": [...],
      "stay":   [...], "notables": [...],
      "people_list": ["…"], "economy_list": ["…"],
      "sources": {"irib_observatory": ["عنوان | سازمان", "https://…"]},
      "meta": { ... }            # every sa_* / lat / lng / season_* key
    }

A paragraph is a string (no citation) or `{"p": text, "sources": [key, …]}`.
Every source key used in the body must exist in `sources`; a citation whose
number the theme would drop (wikipedia, booking…) is refused here, because a
page that promises six footnotes and renders three is a quality bug.

The command runs the same gates the publishing path runs — `fa_lint` on the
body and on the meta, `template.Template.finish()` (the registry) and
`check_template.py` (shape, words floor, bounds, similarity) — and then writes
the three outbox files that `build_section_batch.py`/the agent job need. One
failure anywhere means nothing is written.

Adding a county: copy an existing file, change the facts. Nothing else.
"""
from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent.parent
sys.path.insert(0, str(HERE))

from fa_lint import scan as fa_scan, scan_meta as fa_scan_meta          # noqa: E402
from gen_lib import Page                                                # noqa: E402
from template import NEEDS, REQUIRED_IDS, Template                             # noqa: E402
from theme_sources import blocks_citation, is_forbidden                        # noqa: E402

DATA_DIR = HERE / "data"
OUTBOX = REPO / "tools" / "wp-agent" / "outbox"

# The renderers take the same argument shape, so the section table below is all
# the county file has to know. Order comes from template.SECTIONS — never here.
SECTION_ARGS = {
    "intro": ("intro", "cite"),
    "map": ("map", "neighbors"),
    "history": ("history",),
    "people": ("people", "people_list"),
    "economy": ("economy", "economy_list"),
    "stay": ("stay", "stay_list"),
    "modern": ("modern",),            # list of [heading, [paragraphs]] blocks
    "food": ("food", "food_list"),
    "notables": ("notables",),
}

# Domains behind a footnote must render; the theme's own list (inc/template-tags.php)
# plus the wiki family are read from theme_sources.py. Booking/aggregator links
# are never a source for a travel page in the first place.
BANNED_SOURCE = ("booking.com", "tripadvisor", "alibaba.ir")

TODAY = "۱۴۰۵/۰۸/۲۶"


def load(spec_path: Path) -> dict:
    data = json.loads(spec_path.read_text(encoding="utf-8"))
    for key in ("id", "slug", "name", "tagline", "meta"):
        if key not in data:
            raise SystemExit(f"{spec_path.name}: missing «{key}»")
    for key in REQUIRED_IDS:
        if key not in data:
            raise SystemExit(f"{spec_path.name}: required section «{key}» is missing")
    # facts without which the page would be incomplete: no coordinates means
    # the theme cannot draw the map the map section promises (Khorramshahr).
    have = set(data) | set(data["meta"])
    for section, needed in NEEDS.items():
        if section in data:
            gone = [n for n in needed if n not in have or not str(data["meta"].get(n) or have and data.get(n) or "").strip()]
            # tagline is a top-level key; lat/lng are meta keys
            gone = [n for n in needed
                    if not str(data.get(n) or data["meta"].get(n) or "").strip()]
            if gone:
                raise SystemExit(f"{spec_path.name}: «{section}» needs {gone} — the page "
                                 "would be incomplete without them")
    return data


def para(item):
    """(text, urls) from a string or {"p": text, "sources": [...]}."""
    if isinstance(item, str):
        return item, ()
    return item["p"], tuple(item.get("sources") or ())


def resolve(item, sources: dict, where: str):
    """A county paragraph with its source keys turned into real URLs."""
    text, keys = para(item)
    urls = []
    for key in keys:
        if key not in sources:
            raise SystemExit(f"{where}: unknown source key «{key}» (add it to sources)")
        url = sources[key][1] if isinstance(sources[key], list) else sources[key]["url"]
        if blocks_citation(url):
            raise SystemExit(f"{where}: source «{key}» is a wiki link the theme never renders "
                             f"behind a footnote ({url}) — cite a news/academic source here "
                             "and keep wikipedia for sa_sources only")
        if any(bad in url for bad in BANNED_SOURCE):
            raise SystemExit(f"{where}: source «{key}» is not an acceptable source ({url})")
        urls.append(url)
    return (text, *urls) if urls else text


def build(data: dict) -> tuple[str, dict, list[str]]:
    """Renders the body + meta for one county; returns (html, meta, problems)."""
    name = data["name"]
    sources = data.get("sources") or {}
    t = Template(name, tagline=data["tagline"], page=Page(start=1))

    for key in ("intro", "map", "history", "people", "economy", "stay",
                "modern", "food", "notables"):
        if key not in data:
            continue
        if key == "modern":
            # blocks: [{"h": "زیرساخت", "p": ["…", {"p": "…", "sources": [...]}]}]
            blocks = [(b.get("h", ""), [resolve(r, sources, f"{data['slug']}.modern")
                                        for r in b.get("p") or []])
                      for b in data["modern"] if b.get("p")]
            if not blocks:
                continue
            t.section("modern", blocks)
            continue
        rows = [resolve(r, sources, f"{data['slug']}.{key}") for r in data[key]]
        args = [rows]
        if key == "map" and "neighbors" in data:
            args.append(data["neighbors"])
        elif key == "people" and data.get("people_list"):
            args.append(data["people_list"])
        elif key == "economy" and data.get("economy_list"):
            args.append(data["economy_list"])
        elif key == "stay" and data.get("stay_list"):
            args.append(data["stay_list"])
        elif key == "food" and data.get("food_list"):
            args.append(data["food_list"])
        t.section(key, *args)

    t.finish()                       # registry: order, no legacy/retired, nothing missing
    body = t.marked_body()           # every section one removable unit

    meta = {"sa_sources": source_lines(sources)}
    meta.update(data["meta"])
    meta.setdefault("sa_cty_neighbors", data.get("neighbors_text", ""))
    problems = fa_scan(body, "body: ") + [f"meta: {x}" for x in fa_scan_meta(meta)]
    return body, meta, problems


def source_lines(sources: dict) -> str:
    """«عنوان | سازمان | نشانی | تاریخ» per line — the shape the theme renders."""
    if not sources:
        return ""
    lines = []
    for key, val in sources.items():
        if isinstance(val, dict):
            title, org, url = val["title"], val["org"], val["url"]
        else:
            title_org, url = val
            title, _, org = title_org.partition(" | ")
        # Keep old batches reproducible; new research records its actual visit date.
        seen = val.get("accessed_on", TODAY) if isinstance(val, dict) else TODAY
        lines.append(f"{title.strip()} | {org.strip()} | {url.strip()} | {seen}")
    return "\n".join(lines)


def write(slug: str) -> Path:
    data = load(DATA_DIR / f"{slug}.json")
    body, meta, problems = build(data)
    if problems:
        for line in problems:
            print(f"      - {line}")
        raise SystemExit(f"{slug}: {len(problems)} spelling/style problem(s); nothing written")

    out = OUTBOX / f"20261117-replace-{slug}"
    out.mkdir(parents=True, exist_ok=True)
    key = f"{slug}-{data['id']}"
    sections = {}
    for m in re.finditer(r"<!-- sa:sec:([a-z]+) -->(.*?)<!-- /sa:sec:\1 -->", body, re.S):
        sections[m.group(1)] = m.group(2).strip()
    missing = [k for k in REQUIRED_IDS if k not in sections]
    if missing:                      # belt and braces: never write an unmarked body
        raise SystemExit(f"{slug}: sections missing from the marked body: {missing}")

    (out / f"{key}.json").write_text(json.dumps(sections, ensure_ascii=False, indent=1), encoding="utf-8")
    words = len(re.sub(r"<[^>]+>", " ", body).split())
    summary = out / "summary.json"
    rows = json.loads(summary.read_text(encoding="utf-8")) if summary.exists() else []
    rows = [r for r in rows if r.get("slug") != data["slug"]]
    rows.append({"id": data["id"], "slug": data["slug"], "words_after": words,
                 "sections": list(sections), "ok": True, "fa_lint": []})
    summary.write_text(json.dumps(rows, ensure_ascii=False, indent=1), encoding="utf-8")
    (out / f"{key}.meta.json").write_text(json.dumps(meta, ensure_ascii=False, indent=1), encoding="utf-8")
    (out / f"{key}.html").write_text(body, encoding="utf-8", newline="")
    return out / f"{key}.html"


def main(argv: list[str]) -> int:
    args = [a for a in argv[1:] if not a.startswith("--")]
    if not args:
        print(__doc__)
        return 2
    rc = 0
    for slug in args:
        html = write(slug)
        words = len(re.sub(r"<[^>]+>", " ", html.read_text(encoding="utf-8")).split())
        print(f"OK   {slug} → {html.relative_to(REPO)} ({words} words)")
    return rc


if __name__ == "__main__":
    sys.exit(main(sys.argv))
