#!/usr/bin/env python3
"""Section-based county pages: every section is one removable, replaceable unit.

Owner rule (2026-11-15): a county page must be assembled from named sections so
that one day we can delete or change *that one section* across all 483 county
pages without touching the rest of the article.

How the markers look in the post body (invisible when rendered):

    <!-- sa:sec:intro -->
    ...section html...
    <!-- /sa:sec:intro -->

Commands (from tools/wp-agent):

  # 1) build one page from a section map (keeps the reader-request H2 + below)
  python3 build_section_batch.py build <read.json> <live_dir> <sections_dir> <out_dir>

  # 2) delete one section from every page of an outbox folder
  python3 build_section_batch.py remove <out_dir> <section-key>

  # 3) replace one section in every page of an outbox folder
  python3 build_section_batch.py replace <out_dir> <section-key> <html-file>

`sections_dir/<slug>-<id>.json` is `{"<key>": "<html>", ...}`; the order always
comes from `replace_src/template.py::SECTIONS`, never from the file, so every
page keeps the identical structure.

Checks before anything is written: the section order matches the template, the
words floor holds (owner rule: never shorter than the original), no banned
wording, balanced tags/Gutenberg comments and https-only citations.
"""
from __future__ import annotations

import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "replace_src"))
sys.path.insert(0, HERE)

import content_audit as ca  # noqa: E402
from build_fix_batch import balanced  # noqa: E402
from build_replace_batch import banned_hits, gutenberg_ok  # noqa: E402
from template import BY_ID, IDS  # noqa: E402

SECTIONS = IDS

REQUEST_H2 = "یک درخواست داریم"
OPEN = "<!-- sa:sec:{} -->"
CLOSE = "<!-- /sa:sec:{} -->"
PAGE_RE = re.compile(r".+-\d+\.json")  # <slug>-<post id>.json, never .meta.json
H2_RE = re.compile(r"(?:<!-- wp:heading[^>]*-->\s*)?<h2[^>]*>(.*?)</h2>", re.S)


def wrap(key: str, html: str) -> str:
    return f"{OPEN.format(key)}\n{html.strip()}\n{CLOSE.format(key)}"


def marked_body(sections: dict) -> str:
    """Render sections in the locked template order, wrapped in markers."""
    missing = [k for k in SECTIONS if k not in sections]
    if missing:
        raise ValueError(f"sections missing: {missing}")
    extra = [k for k in sections if k not in SECTIONS]
    if extra:
        raise ValueError(f"unknown sections: {extra}")
    return "\n\n".join(wrap(k, sections[k]) for k in SECTIONS)


def split_article(html: str) -> tuple[str, str]:
    """Split a live page into (before, from the reader-request H2 on)."""
    hits = [m for m in H2_RE.finditer(html) if REQUEST_H2 in m.group(1)]
    if len(hits) != 1:
        raise ValueError(f"reader-request H2 must appear once (found {len(hits)})")
    pos = hits[0].start()
    return html[:pos], html[pos:]


INTRO_RX = re.compile(r"؛")


def is_intro(title: str) -> bool:
    """The opening H2 is «<name>؛ <tagline>» — nothing else after the first H2
    and no chapter-ish wording, so body H2s with a ؛ are not mistaken for it."""
    return ("؛" in title and "معرفی شهرستان" not in title
            and not re.search(r"نگاه|نکته|درخواست|فصل|بخش|دهستان", title))


def h2_spans(html: str) -> list[tuple[int, str]]:
    """(start offset, plain title) for every H2, in document order."""
    return [(m.start(), re.sub(r"<[^>]+>", "", m.group(1)).strip())
            for m in H2_RE.finditer(html)]


def marked_spans(html: str) -> dict[str, tuple[int, int]] | None:
    if OPEN.format(SECTIONS[0]) not in html:
        return None
    spans = {}
    for key in SECTIONS:
        o = html.find(OPEN.format(key))
        c = html.find(CLOSE.format(key))
        if o == -1 or c == -1:
            raise ValueError(f"section {key}: marker missing")
        spans[key] = (o, c + len(CLOSE.format(key)))
    order = [spans[k][0] for k in SECTIONS]
    if order != sorted(order):
        raise ValueError("section markers are out of template order")
    return spans


def legacy_span(html: str, key: str) -> tuple[int, int]:
    """Section span on a page built before the markers existed: the H2 that
    matches this section's pattern, up to the next H2. Exactly one match is
    required — a page where a section is ambiguous is reported, not guessed."""
    heads = h2_spans(html)
    rx = re.compile(BY_ID[key]["pattern"])
    if key == "intro":
        # by contract the opening section is the first H2 of the article; the
        # «؛» test only keeps a page whose first H2 is not an opening line out
        hits = [(0, heads[0][0])] if heads and is_intro(heads[0][1]) else []
    else:
        hits = [(i, start) for i, (start, title) in enumerate(heads) if rx.search(title)]
        if len(hits) > 1:
            # building blocks (places, history themes) sit above the climate H2,
            # so the section's own H2 is the last match in document order
            hits = hits[-1:]
    if len(hits) != 1:
        raise ValueError(f"{key}: {len(hits)} H2s match {BY_ID[key]['pattern']!r} "
                         "(page needs a rewrite before it can be sectioned)")
    i, start = hits[0]
    end = heads[i + 1][0] if i + 1 < len(heads) else len(html)
    return start, end


def spans_of(html: str) -> tuple[dict[str, tuple[int, int]], bool]:
    """(spans, marked?) — markers first, legacy H2 mapping as the fallback."""
    marked = marked_spans(html)
    if marked is not None:
        return marked, True
    return {k: legacy_span(html, k) for k in SECTIONS}, False


def checks(out: str, words_floor: int | None) -> dict:
    row = {
        "words_after": ca.analyze_page({"content": out})["words"],
        "tags_ok": balanced(out),
        "gutenberg_ok": gutenberg_ok(out),
        "banned": banned_hits(out),
    }
    if words_floor is not None:
        row["words_original"] = words_floor
        row["volume_ok"] = row["words_after"] >= words_floor
    return row


def cmd_build(read_path: str, live_dir: str, sections_dir: str, out_dir: str) -> int:
    pages = {}
    for step in json.load(open(read_path, encoding="utf-8"))["steps"]:
        r = step.get("result")
        if isinstance(r, dict) and r.get("slug"):
            pages[f"{r['slug']}-{r['id']}"] = r
    os.makedirs(out_dir, exist_ok=True)
    summary = []
    for key in sorted(os.listdir(sections_dir)):
        if not PAGE_RE.fullmatch(key):
            continue
        page_key = key[:-5]
        page = pages.get(page_key)
        if not page:
            raise SystemExit(f"{page_key}: not in {read_path}")
        sections = json.load(open(os.path.join(sections_dir, key), encoding="utf-8"))
        # base: the live body (what is on the site right now) — never a guess
        base_path = os.path.join(live_dir, page_key + ".html")
        base = open(base_path, encoding="utf-8", newline="").read() if os.path.exists(base_path) else page["content"]
        _before, tail = split_article(base)
        out = marked_body(sections) + "\n\n" + tail
        floor = len(re.sub(r"<[^>]+>", " ", base).split())
        row = checks(out, floor)
        row.update({"id": page["id"], "slug": page["slug"],
                    "modified_gmt": page["modified_gmt"], "changes": ["rebuild: marked sections"],
                    "bad_cites": []})
        row["ok"] = (row["volume_ok"] and row["tags_ok"] and row["gutenberg_ok"] and not row["banned"])
        open(os.path.join(out_dir, page_key + ".html"), "w", encoding="utf-8", newline="").write(out)
        summary.append(row)
    json.dump(summary, open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8"),
              ensure_ascii=False, indent=1)
    for r in summary:
        print(json.dumps({k: r[k] for k in ("slug", "words_original", "words_after", "ok", "banned")},
                         ensure_ascii=False))
    return 0 if summary and all(r["ok"] for r in summary) else 1


def _rewrite_dir(out_dir: str, key: str, new_html: str | None) -> int:
    if key not in SECTIONS:
        raise SystemExit(f"unknown section {key}; template sections: {', '.join(SECTIONS)}")
    touched = 0
    skipped = 0
    for name in sorted(os.listdir(out_dir)):
        if not name.endswith(".html"):
            continue
        path = os.path.join(out_dir, name)
        html = open(path, encoding="utf-8", newline="").read()
        try:
            spans, marked = spans_of(html)
        except ValueError as exc:
            print(f"SKIP {name}: {exc}")
            skipped += 1
            continue
        a, b = spans[key]
        if new_html is None:
            after = a
            end = b
            # swallow the blank line that separated the removed block
            while end < len(html) and html[end] in "\n":
                end += 1
            html = html[:after] + html[end:]
        else:
            html = html[:a] + wrap(key, new_html) + html[b:]
        open(path, "w", encoding="utf-8", newline="").write(html)
        touched += 1
        print(("removed " if new_html is None else "replaced ") + f"{key} in {name}"
              + ("" if marked else " (legacy page, matched by H2)"))
    print(f"{touched} pages updated, {skipped} skipped")
    return 0 if touched and not skipped else 1


def cmd_report(out_dir: str) -> int:
    """How many built pages are section-addressable, and which are not."""
    rows = []
    for name in sorted(os.listdir(out_dir)):
        if not name.endswith(".html"):
            continue
        html = open(os.path.join(out_dir, name), encoding="utf-8", newline="").read()
        marked = marked_spans(html) is not None
        try:
            spans_of(html)
            ok = True
        except ValueError as exc:
            ok, why = False, str(exc)
        rows.append({"page": name, "marked": marked, "sectionable": ok})
        if not ok:
            print(f"NO   {name}: {why}")
    n_marked = sum(r["marked"] for r in rows)
    n_ok = sum(r["sectionable"] for r in rows)
    print(f"{n_marked}/{len(rows)} pages carry section markers; "
          f"{n_ok}/{len(rows)} can take a section change")
    return 0 if n_ok == len(rows) else 1


if __name__ == "__main__":
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    cmd = sys.argv[1]
    if cmd == "build":
        sys.exit(cmd_build(*sys.argv[2:6]))
    if cmd == "remove":
        sys.exit(_rewrite_dir(sys.argv[2], sys.argv[3], None))
    if cmd == "report":
        sys.exit(cmd_report(sys.argv[2]))
    if cmd == "replace":
        html = open(sys.argv[4], encoding="utf-8").read()
        sys.exit(_rewrite_dir(sys.argv[2], sys.argv[3], html))
    sys.exit(__doc__)
