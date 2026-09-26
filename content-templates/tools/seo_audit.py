#!/usr/bin/env python3
"""Rank Math-style on-page SEO audit for a 9-block article file (BLOCK 3 body).

Usage:
    python3 content-templates/tools/seo_audit.py content/provinces/<slug>.md [focus keyword] [secondary keyword ...]

If no keyword is given, focus_keyword and secondary_keywords are read from BLOCK 1.
Reported: machine word count, exact focus-keyword count and density, keyword in the
first 10%, headings that contain the keyword, paragraphs longer than 120 words,
per-secondary-keyword counts inside the body. Exit code 1 if a hard check fails.
See content-templates/seo-checklist-rankmath.md for the thresholds.
"""
import re
import sys

MAX_PARA_WORDS = 120
DENSITY_MIN, DENSITY_MAX = 0.8, 1.5


def read_blocks(path):
    text = open(path, encoding="utf-8").read()
    try:
        head, rest = text.split("=== BLOCK 3: ARTICLE (Markdown) ===", 1)
        body = rest.split("=== BLOCK 4", 1)[0]
    except ValueError:
        sys.exit("BLOCK 3 marker not found in %s" % path)
    return head, body


def keywords_from_header(head):
    m = re.search(r"^focus_keyword:\s*(.+?)\s*(#.*)?$", head, flags=re.M)
    focus = m.group(1).strip() if m else None
    secs = []
    m = re.search(r"^secondary_keywords:\s*\n((?:\s+-\s.*\n)+)", head, flags=re.M)
    if m:
        secs = [re.sub(r"\s+#.*$", "", l.strip()[2:]).strip() for l in m.group(1).splitlines()]
    return focus, secs


def is_prose(block):
    return not (block.startswith(("|", "#", "- ", "<!--", ">", "**شهرستان"))
                or re.match(r"^\d+\. ", block))


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    head, body = read_blocks(sys.argv[1])
    focus, secs = keywords_from_header(head)
    if len(sys.argv) >= 3:
        focus = sys.argv[2]
        secs = sys.argv[3:] or secs
    if not focus:
        sys.exit("no focus keyword")

    words = body.split()
    n_words = len(words)
    n_kw = len(re.findall(re.escape(focus), body))
    density = n_kw / n_words * 100 if n_words else 0
    fails = []

    print("file             :", sys.argv[1])
    print("focus keyword    :", focus)
    print("words (machine)  :", n_words)
    print("keyword count    : %d  -> density %.2f%%  (target %.1f-%.1f%%)" % (n_kw, density, DENSITY_MIN, DENSITY_MAX))
    if not (DENSITY_MIN <= density <= DENSITY_MAX):
        fails.append("density")
    first = " ".join(words[: max(1, n_words // 10)])
    ok_first = focus in first
    print("in first 10%%     : %s" % ok_first)
    if not ok_first:
        fails.append("first10")
    heads = re.findall(r"^(#{2,4}) (.*)$", body, flags=re.M)
    with_kw = [h for _, h in heads if focus in h]
    print("headings with kw : %d" % len(with_kw))
    for h in with_kw:
        print("   -", h)
    if not with_kw:
        fails.append("subheading")
    h4 = [h for lvl, h in heads if lvl == "####"]
    if h4:
        print("H4 present (forbidden by template):", len(h4))
        fails.append("h4")
    paras = [p.strip() for p in re.split(r"\n\s*\n", body) if p.strip()]
    long_paras = [(len(p.split()), p[:70]) for p in paras if is_prose(p) and "\n" not in p and len(p.split()) > MAX_PARA_WORDS]
    print("paragraphs >%d   : %d" % (MAX_PARA_WORDS, len(long_paras)))
    for n, p in long_paras:
        print("   %4d  %s" % (n, p))
    if long_paras:
        fails.append("long_paragraphs")
    links = re.findall(r"\]\((/[^)\s]*)\)", body)
    ext = re.findall(r"\]\((https?://[^)\s]*)\)", body)
    print("internal links   : %d   external links in body: %d (must be 0 - sources live in BLOCK 5)" % (len(links), len(ext)))
    if ext:
        fails.append("external_links_in_body")
    print("secondary keywords in body:")
    for s in secs:
        c = len(re.findall(re.escape(s), body))
        print("   %3d  %s" % (c, s))
        if c == 0:
            fails.append("secondary:" + s)
    print("markers          : [نیازمند بررسی]=%d  [منبع لازم]=%d  [URL لازم]=%d  (به‌زودی)=%d" % (
        body.count("[نیازمند بررسی"), body.count("[منبع لازم"), body.count("[URL لازم"), body.count("(به‌زودی)")))
    print("RESULT           :", "PASS" if not fails else "FAIL " + ", ".join(fails))
    sys.exit(1 if fails else 0)


if __name__ == "__main__":
    main()
