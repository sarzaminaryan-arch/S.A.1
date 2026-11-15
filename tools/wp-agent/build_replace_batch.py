#!/usr/bin/env python3
"""Build a "delete + replace" batch: cleaned page HTML plus new verified sections.

Usage:
  build_replace_batch.py <read.json[,read2.json]> <base_dir> <additions.json> <out_dir>

  read.json      read-only `get` job result(s) (page meta/title; comma-join several)
  base_dir       directory with the current <slug>-<id>.html (live or cleaned version)
  additions.json {"<slug>-<id>": {
                    "words_original": int,      # words before ANY cleanup (volume floor)
                    "modified_gmt": str,        # expected_modified_gmt for the write job
                    "rewrites": [[old, new]],   # each old must match exactly once
                    "insert_before_h2": str,    # fragment of the H2 to insert before
                    "blocks": str}}             # Gutenberg HTML to insert

Owner rule (2026-11-15): removed duplicate text must be replaced, and the page
volume must not drop. A page fails (exit 1) when words_after < words_original,
a duplicate sentence or editorial note remains, tags/Gutenberg comments are
unbalanced, or a citation link is not https. Never touches the site.
"""
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import content_audit as ca  # noqa: E402
from build_fix_batch import balanced  # noqa: E402

H2_RE = re.compile(r"<!-- wp:heading[^>]*-->\s*<h2[^>]*>(.*?)</h2>", re.S)
CITE_RE = re.compile(r'class="sa-cite"><a href="([^"]+)"')


def apply(html: str, spec: dict) -> tuple[str, list[str]]:
    log = []
    for old, new in spec.get("rewrites", []):
        n = html.count(old)
        if n != 1:
            raise ValueError(f"rewrite must match exactly once (found {n}): {old[:80]}")
        html = html.replace(old, new)
        log.append(f"rewrite: {old[:60]} -> {new[:60]}")
    frag = spec.get("insert_before_h2")
    if frag:
        hits = [m for m in H2_RE.finditer(html) if frag in m.group(1)]
        if len(hits) != 1:
            raise ValueError(f"insert_before_h2 must match one H2 (found {len(hits)}): {frag}")
        pos = hits[0].start()
        html = html[:pos] + spec["blocks"].strip() + "\n\n" + html[pos:]
        log.append(f"insert before H2: {frag}")
    return html, log


def check_cites(html: str) -> list[str]:
    return [h for h in CITE_RE.findall(html) if not h.startswith("https://")]


def gutenberg_ok(html: str) -> bool:
    opens = len(re.findall(r"<!-- wp:[a-z]", html))
    closes = len(re.findall(r"<!-- /wp:[a-z]", html))
    return opens == closes


def load_pages(read_paths: str) -> dict:
    pages = {}
    for rp in read_paths.split(","):
        for step in json.load(open(rp, encoding="utf-8"))["steps"]:
            r = step.get("result")
            if isinstance(r, dict) and r.get("slug") and r.get("id") is not None:
                pages[f"{r['slug']}-{r['id']}"] = r
    return pages


def main(read_paths: str, base_dir: str, additions_path: str, out_dir: str) -> list[dict]:
    pages = load_pages(read_paths)
    additions = json.load(open(additions_path, encoding="utf-8"))
    os.makedirs(out_dir, exist_ok=True)
    summary = []
    for key, spec in additions.items():
        page = pages[key]
        base = open(os.path.join(base_dir, key + ".html"), encoding="utf-8", newline="").read()
        out, log = apply(base, spec)
        live = ca.analyze_page({**page, "content": base})
        after = ca.analyze_page({**page, "content": out})
        row = {
            "id": page["id"], "slug": page["slug"], "modified_gmt": spec["modified_gmt"],
            "words_original": spec["words_original"], "words_live": live["words"],
            "words_after": after["words"],
            "volume_ok": after["words"] >= spec["words_original"],
            "dup_after": after["dup_sentences"], "dup_left": after["dup_examples"],
            "notes_after": after["editorial_notes"], "notes_left": after["note_examples"],
            "tags_ok": balanced(out), "gutenberg_ok": gutenberg_ok(out),
            "bad_cites": check_cites(out), "changes": log,
        }
        row["ok"] = (row["volume_ok"] and not row["dup_after"] and not row["notes_after"]
                     and row["tags_ok"] and row["gutenberg_ok"] and not row["bad_cites"])
        open(os.path.join(out_dir, key + ".html"), "w", encoding="utf-8", newline="").write(out)
        summary.append(row)
    json.dump(summary, open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8"),
              ensure_ascii=False, indent=1)
    return summary


if __name__ == "__main__":
    if len(sys.argv) != 5:
        sys.exit(__doc__)
    rows = main(*sys.argv[1:5])
    print("pages", len(rows))
    for r in rows:
        print(json.dumps({k: r[k] for k in ("slug", "words_original", "words_live", "words_after", "ok")},
                         ensure_ascii=False))
    if not all(r["ok"] for r in rows):
        sys.exit("one or more pages failed the checks; see summary.json")
