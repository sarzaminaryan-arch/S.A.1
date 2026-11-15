#!/usr/bin/env python3
"""Guard: every rebuilt county page keeps the same article shape.

The owner's rule (2026-11-15): all county pages should look like the reference
pages (تهران، مشهد، تبریز، درگز) — a readable intro, a map/neighbours section,
history, climate, economy, people, sights, staying, notables, small tips — and
the traveller-experience block is rendered by the theme, so every rebuilt page
must also have a vetted record with a non-empty field_update_note.

Owner orders of 2026-11-15 (page 51 onwards): the short identity table
(شناسنامهٔ کوتاه) duplicates the county identity card above the body and the
closing reader-request block (از مردم عزیز …) leaves the site, so a rebuilt
page carries NEITHER: their presence here is a violation, their absence is the
correct shape.

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
sys.path.insert(0, str(Path(__file__).resolve().parent))

# The locked order comes from the single registry in template.py: adding,
# dropping or renaming a section is one edit there and nowhere else.
from template import order_patterns  # noqa: E402

ORDER = order_patterns()

REMOVED = ("card", "request")  # still in the registry, never on a new page
ORDER = tuple((label, rx) for label, rx in ORDER if label not in REMOVED)
REMOVED_PATTERNS = tuple((label, rx) for label, rx in order_patterns() if label in REMOVED)

# Section markers written by build_section_batch.py. When present they must
# exist exactly once per live section, in registry order.
MARK_OPEN = "<!-- sa:sec:{} -->"
MARK_CLOSE = "<!-- /sa:sec:{} -->"

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
    problems += check_markers(html)
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
    # the two owner-removed sections must not come back
    for label, rx in REMOVED_PATTERNS:
        if any(re.search(rx, title) for title in h2):
            problems.append(f"owner-removed section is present: {label}")
        if MARK_OPEN.format(label) in html or MARK_CLOSE.format(label) in html:
            problems.append(f"owner-removed section has markers: {label}")
    # one link per URL rule — it is about links inside the text, not the numbered
    # citation marks (a source keeps its number wherever it is cited again).
    text_only = re.sub(r'<sup class="sa-cite">.*?</sup>', "", html, flags=re.S)
    hrefs = re.findall(r'href="([^"]+)"', text_only)
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


def check_markers(html: str) -> list[str]:
    """Section markers must be balanced and in registry order (unmarked pages
    are the older shape and are covered by the H2 order check alone)."""
    opens = re.findall(r"<!-- sa:sec:([a-z]+) -->", html)
    if not opens:
        return []
    ids = [label for label, _ in ORDER]
    problems = []
    for key in opens:
        if html.count(MARK_OPEN.format(key)) != 1 or html.count(MARK_CLOSE.format(key)) != 1:
            problems.append(f"section marker not paired once: {key}")
    if opens != [k for k in ids if k in opens]:
        problems.append(f"section markers out of order: {opens}")
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
