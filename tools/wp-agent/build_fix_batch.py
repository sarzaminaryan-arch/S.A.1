#!/usr/bin/env python3
"""Build a dedup/editorial-note fix batch from a read-only `get` job result.

Usage: build_fix_batch.py <results/...-read.json> <outbox_dir> [rewrites.json]

Writes <slug>-<id>.html (cleaned content), <slug>-<id>.changes.txt and
summary.json with before/after word, duplicate and note counts plus HTML
sanity checks (tag balance, Gutenberg comment pairs). Never touches the site.
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import content_audit as ca  # noqa: E402
import content_fix as cf  # noqa: E402

TAGS = ["p", "ul", "ol", "li", "table", "tr", "td", "th", "h2", "h3", "h4", "strong", "a"]


def balanced(html: str) -> bool:
    import re
    return all(len(re.findall(rf"<{t}[\s>]", html)) == html.count(f"</{t}>") for t in TAGS)


def main(src: str, out_dir: str, rewrites_path: str | None = None) -> list[dict]:
    rewrites = json.load(open(rewrites_path, encoding="utf-8")) if rewrites_path else {}
    result = json.load(open(src, encoding="utf-8"))
    os.makedirs(out_dir, exist_ok=True)
    summary = []
    for step in result["steps"]:
        page = step["result"]
        html = page["content"]
        rw = [tuple(x) for x in rewrites.get(page["slug"], [])]
        out, log = cf.clean(html, rw or None)
        before = ca.analyze_page(page)
        after = ca.analyze_page({**page, "content": out})
        base = f"{out_dir}/{page['slug']}-{page['id']}"
        open(base + ".html", "w", encoding="utf-8").write(out)
        open(base + ".changes.txt", "w", encoding="utf-8").write("\n".join(log) + "\n")
        summary.append({
            "id": page["id"], "slug": page["slug"], "modified_gmt": page["modified_gmt"],
            "words_before": before["words"], "words_after": after["words"],
            "dup_before": before["dup_sentences"], "dup_after": after["dup_sentences"],
            "notes_before": before["editorial_notes"], "notes_after": after["editorial_notes"],
            "notes_left": after["note_examples"], "dup_left": after["dup_examples"],
            "changes": len(log), "idempotent": cf.clean(out)[0] == out,
            "tags_ok": balanced(out) == balanced(html) or balanced(out),
            "gutenberg_ok": out.count("<!-- wp:") - out.count("<!-- /wp:") == html.count("<!-- wp:") - html.count("<!-- /wp:"),
        })
    json.dump(summary, open(f"{out_dir}/summary.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    return summary


if __name__ == "__main__":
    for row in main(*sys.argv[1:4]):
        print(json.dumps(row, ensure_ascii=False))
