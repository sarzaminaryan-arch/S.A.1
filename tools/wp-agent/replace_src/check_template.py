#!/usr/bin/env python3
"""Guard: every rebuilt county page keeps the same article shape.

The owner's rule (2026-11-15): all county pages should look like the reference
pages (تهران، مشهد، تبریز، درگز) — a readable intro, a short identity table,
a map/neighbours section, history, climate, economy, people, sights, staying,
notables, small tips — and the traveller-experience block is rendered by the
theme, so every rebuilt page must also have a vetted record with a non-empty
field_update_note.

This checks the built outbox files (not the live site) and prints one line per
problem. Exit code 1 on any violation so the batch cannot ship half-templated.

Usage: python3 replace_src/check_template.py outbox/<batch-dir> [...]
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# The locked order of the county template (tools/wp-agent/replace_src/template.py).
# Titles may be worded for the county, but every family must appear once, in order.
ORDER = (
    ("intro",          r"(؛|معرفی شهرستان)"),
    ("card",           r"شناسنامهٔ کوتاه"),
    ("map",            r"نقشه|همسایه"),
    ("climate",        r"آب‌وهوا|آب و هوا|بهترین زمان سفر"),
    ("history",        r"تاریخ|پیشینه"),
    ("people",         r"مردم"),
    ("economy",        r"اقتصاد|معیشت|کشاورزی|صنعت|کارِ مردم"),
    ("sights",         r"جاهای دیدنی|جاذبه"),
    ("stay",           r"راه رسیدن|جای ماندن|چطور برسیم|راه و ارتباط"),
    ("notables",       r"چهره‌ها|نام‌آور|مشاهیر"),
    ("tips",           r"چند نکته|اگر فقط یک روز"),
    ("request",        r"یک درخواست داریم"),
)

MIN_WORDS = 900


def strip(html: str) -> str:
    return re.sub(r"<[^>]+>", "", html)


def words(html: str) -> int:
    return len(strip(html).split())


def check_html(path: Path) -> list[str]:
    html = path.read_text(encoding="utf-8")
    problems: list[str] = []
    h2 = [strip(x).strip() for x in re.findall(r"<h2[^>]*>(.*?)</h2>", html, re.S)]
    joined = "\n".join(h2)
    if words(html) < MIN_WORDS:
        problems.append(f"only {words(html)} words (< {MIN_WORDS})")
    pos = -1
    for label, rx in ORDER:
        found = None
        for i, title in enumerate(h2):
            if i > pos and re.search(rx, title):
                found = i
                break
        if found is None:
            problems.append(f"missing or out-of-order section: {label}")
        else:
            pos = found
    if not re.search(r"<table>", html):
        problems.append("no identity table")
    # one link per URL rule
    hrefs = re.findall(r'href="([^"]+)"', html)
    dupes = {h for h in hrefs if hrefs.count(h) > 1}
    if dupes:
        problems.append(f"repeated links: {sorted(dupes)[:3]}")
    # banned wording (owner rules)
    for bad in ("باید", "راستش", "به‌زودی", "در حال ساخت", "کلنگ", "اسرائیل", "اسراییل"):
        if bad in html:
            problems.append(f"banned wording: {bad}")
    if re.search(r"<td>https", html):
        problems.append("raw URL inside a table cell")
    return problems


def check_meta(path: Path) -> list[str]:
    meta = json.loads(path.read_text(encoding="utf-8"))
    need = ["sa_cty_neighbors", "sa_cty_climate", "sa_cty_best_time", "sa_cty_language",
            "sa_cty_livelihood", "sa_faq", "sa_sources"]
    return [f"meta missing {k}" for k in need if not str(meta.get(k) or "").strip()]


def main(argv: list[str]) -> int:
    dirs = [Path(a).resolve() for a in argv[1:]] or sorted((ROOT / "outbox").glob("*-replace-*"))
    total = 0
    bad = 0
    for d in dirs:
        for html in sorted(d.glob("*.html")):
            total += 1
            problems = check_html(html)
            meta = html.with_suffix(".meta.json")
            if meta.exists():
                problems += check_meta(meta)
            else:
                problems.append("no .meta.json")
            if problems:
                bad += 1
                print(f"FAIL {html.relative_to(ROOT)}")
                for p in problems:
                    print(f"      - {p}")
            else:
                print(f"OK   {html.relative_to(ROOT)}")
    print(f"\n{total - bad}/{total} pages conform to the county template")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
