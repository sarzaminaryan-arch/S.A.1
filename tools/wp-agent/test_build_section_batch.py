#!/usr/bin/env python3
"""Self-test for build_section_batch.py (the «one section, all counties» tool).

Builds a two-section page in a temp dir and checks the three owner-facing
operations: a section can be deleted from every page, put back in its registry
position, and swapped — while the rest of the page stays byte-identical.
"""
import os
import shutil
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
sys.path.insert(0, os.path.join(HERE, "replace_src"))

import build_section_batch as b  # noqa: E402
from template import IDS  # noqa: E402

HTML = """<!-- sa:sec:intro -->
<!-- wp:heading --><h2 class="wp-block-heading">آزمون؛ شهر تست</h2><!-- /wp:heading -->

<!-- wp:paragraph --><p>متن معرفی.</p><!-- /wp:paragraph -->
<!-- /sa:sec:intro -->

<!-- sa:sec:card -->
<!-- wp:heading --><h2 class="wp-block-heading">شناسنامهٔ کوتاه آزمون</h2><!-- /wp:heading -->

<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>شاخص</th></tr></thead><tbody><tr><td>۱</td></tr></tbody></table></figure><!-- /wp:table -->
<!-- /sa:sec:card -->

<!-- sa:sec:tips -->
<!-- wp:heading --><h2 class="wp-block-heading">چند نکتهٔ کوچک از آزمون</h2><!-- /wp:heading -->

<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>یک</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- /sa:sec:tips -->

<!-- sa:sec:request -->
<!-- wp:heading --><h2 class="wp-block-heading">از مردم عزیز شهرستان آزمون، یک درخواست داریم</h2><!-- /wp:heading -->

<!-- wp:paragraph --><p>درخواست.</p><!-- /wp:paragraph -->
<!-- /sa:sec:request -->"""


def run(html, op, key, new=None):
    d = tempfile.mkdtemp()
    try:
        path = os.path.join(d, "x-1.html")
        with open(path, "w", encoding="utf-8", newline="") as fh:
            fh.write(html)
        if op == "remove":
            b._rewrite_dir(d, key, None)
        else:
            b._rewrite_dir(d, key, new)
        return open(path, encoding="utf-8", newline="").read()
    finally:
        shutil.rmtree(d)


def main() -> int:
    assert "request" in IDS and "intro" in IDS, "registry must expose section ids"

    # 1) remove one section: it disappears, everything else stays verbatim
    out = run(HTML, "remove", "card")
    assert "sa:sec:card" not in out, "removed section still has markers"
    assert "شناسنامهٔ کوتاه" not in out, "removed section text survived"
    for keep in ("متن معرفی.", "چند نکتهٔ کوچک", "یک درخواست داریم"):
        assert keep in out, f"remove damaged another section: {keep}"

    # 2) replace one section: only that block changes
    new = ("<!-- wp:heading --><h2 class=\"wp-block-heading\">چند نکتهٔ کوچک از آزمون</h2>"
           "<!-- /wp:heading -->\n\n<!-- wp:paragraph --><p>متن تازه.</p><!-- /wp:paragraph -->")
    out = run(HTML, "replace", "tips", new)
    assert "متن تازه." in out and "<li>یک</li>" not in out, "replace did not swap the block"
    assert "متن معرفی." in out and "یک درخواست داریم" in out, "replace damaged other sections"

    # 3) a section that was removed can be put back in its registry position
    without = run(HTML, "remove", "card")
    out = run(without, "replace", "card", "<!-- wp:paragraph --><p>برگشت.</p><!-- /wp:paragraph -->")
    order = [k for k in IDS if b.OPEN.format(k) in out]
    assert order == ["intro", "card", "tips", "request"], f"re-added section landed out of order: {order}"
    assert "برگشت." in out, "re-added section text missing"

    # 4) the owner-removed blocks are dropped from the build, not kept
    base = HTML  # carries card + request sections
    cleaned = b.cleaned_base(base)
    assert "یک درخواست داریم" not in cleaned, "cleaned_base still carries the request block"
    assert "شناسنامهٔ کوتاه" not in cleaned, "cleaned_base still carries the card table"
    for keep in ("متن معرفی.", "چند نکتهٔ کوچک"):
        assert keep in cleaned, f"cleaned_base damaged a kept section: {keep}"
    # a base without the request H2 is accepted as it is
    assert b.cleaned_base(HTML.split("<!-- sa:sec:request -->")[0]) == cleaned

    # 5) marked_body accepts the owner-ordered subset (no card, no request)
    sections = {"intro": "<p>متن معرفی تازه و کامل برای صفحهٔ آزمون این نوبت.</p>",
                "tips": "<ul><li>یک</li><li>دو</li><li>سه</li></ul>"}
    body = b.marked_body(sections)
    order = [k for k in IDS if b.OPEN.format(k) in body]
    assert order == ["intro", "tips"], f"subset rendered out of order: {order}"
    assert "sa:sec:card" not in body and "sa:sec:request" not in body
    try:
        b.marked_body({"nope": "<p>x</p>"})
        raise AssertionError("unknown section id was accepted")
    except ValueError:
        pass

    # 6) build: registry order, request tail dropped, floor is the cleaned base
    import json
    d = tempfile.mkdtemp()
    try:
        read = os.path.join(d, "read.json")
        json.dump({"steps": [{"result": {"id": 1, "slug": "testcity", "content": HTML,
                                         "modified_gmt": "2026-11-15 10:00:00"}}]},
                  open(read, "w", encoding="utf-8"))
        sdir = os.path.join(d, "sections")
        os.makedirs(sdir)
        json.dump(sections, open(os.path.join(sdir, "testcity-1.json"), "w", encoding="utf-8"))
        outdir = os.path.join(d, "out")
        code = b.cmd_build(read, d, sdir, outdir)
        assert code == 0, "build failed"
        page = open(os.path.join(outdir, "testcity-1.html"), encoding="utf-8", newline="").read()
        assert "یک درخواست داریم" not in page, "build kept the request tail"
        assert "شناسنامهٔ کوتاه" not in page, "build let the card section through"
        assert [k for k in IDS if b.OPEN.format(k) in page] == ["intro", "tips"]
        summary = json.load(open(os.path.join(outdir, "summary.json"), encoding="utf-8"))[0]
        floor = len(b.re.sub(r"<[^>]+>", " ", cleaned).split())
        assert summary["words_original"] == floor, (summary["words_original"], floor)
        assert summary["ok"] is True, summary
    finally:
        shutil.rmtree(d)

    # 7) a page where a section cannot be identified is skipped, never guessed
    legacy = HTML.replace("<!-- sa:sec:card -->", "").replace("<!-- /sa:sec:card -->", "")
    d = tempfile.mkdtemp()
    try:
        p = os.path.join(d, "y-2.html")
        open(p, "w", encoding="utf-8", newline="").write(legacy)
        code = b._rewrite_dir(d, "card", None)
        assert code == 1, "a half-marked page must be reported, not silently edited"
        assert open(p, encoding="utf-8", newline="").read() == legacy, "a skipped page was modified"
    finally:
        shutil.rmtree(d)

    print("OK: section tool (remove/replace/re-add, registry order, skip-half-marked)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
