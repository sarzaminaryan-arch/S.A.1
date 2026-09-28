#!/usr/bin/env python3
"""Rank Math-style on-page SEO audit for a 9-block article file (BLOCK 3 body).

Usage:
    python3 content-templates/tools/seo_audit.py content/provinces/<slug>.md [focus keyword] [secondary keyword ...]

If no keyword is given, focus_keyword and secondary_keywords are read from BLOCK 1.
Reported: machine word count, exact focus-keyword count and density, keyword in the
first 10%, headings that contain the keyword, paragraphs longer than 120 words,
per-secondary-keyword counts inside the body, internal/external links, and whether
every inline citation <sup>[n](URL)</sup> points to the URL of row n in BLOCK 5
(the theme prints BLOCK 5 as an <ol>, so n is what readers see). Exit code 1 if a
hard check fails.
Prompt v1.2 (2026-09-28) adds WARN-only lines (they never change PASS/FAIL): prose
length of the history section and presence of its dynasty table, the fixed fifth H3 of
the attractions section, hedge words without a citation or marker in the same sentence
(the "no guessing" rule), and how many BLOCK 5 rows use the [نقشه]/[غیررسمی] prefixes.
See content-templates/seo-checklist-rankmath.md for the thresholds and
content-templates/province.md §2-ج for the v1.2 rules.
"""
import re
import sys

MAX_PARA_WORDS = 120
DENSITY_MIN, DENSITY_MAX = 0.8, 1.5
# Domains the theme links dofollow (mirror of sa_source_rel() in inc/template-tags.php).
OFFICIAL = (".gov.ir", "unesco.org", "amar.org.ir", "mcth.ir", "doe.ir", "moi.ir", "ichto.ir", "un.org", "britannica.com", "iranicaonline.org")
FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")
# --- prompt v1.2 (province.md §2-ج) — WARN-only thresholds -------------------------
HISTORY_H2 = "## تاریخچه استان"
HISTORY_MIN, HISTORY_MAX = 700, 1200
DYNASTY_TABLE_KEY = "سلسله"
FIFTH_H3 = "### بازارها، مراکز خرید و بناهای شاخص امروزی"
HEDGES = ("حدود", "تقریباً", "احتمالاً", "شاید", "گفته می‌شود", "گفته می شود", "به نظر می‌رسد", "به نظر می رسد", "ظاهراً")
SOURCE_PREFIXES = ("[میراث]", "[محلی]", "[سفرنامه]", "[نقشه]", "[غیررسمی]")
_FA_WORD = r"[\w\u0600-\u06FF\u200c]"
HEDGE_RE = re.compile(r"(?<!%s)(?:%s)(?!%s)" % (_FA_WORD, "|".join(re.escape(h) for h in HEDGES), _FA_WORD))


def read_blocks(path):
    text = open(path, encoding="utf-8").read()
    try:
        head, rest = text.split("=== BLOCK 3: ARTICLE (Markdown) ===", 1)
        body = rest.split("=== BLOCK 4", 1)[0]
    except ValueError:
        sys.exit("BLOCK 3 marker not found in %s" % path)
    return head, body, text


def sources_by_row(text):
    """Row number -> URL (or None) for the public part of BLOCK 5, as the theme's <ol> numbers them."""
    m = re.search(r"=== BLOCK 5: SOURCES ===\s*```\s*\n(.*?)(?:\n---|```)", text, flags=re.S)
    if not m:
        return {}
    rows = {}
    for i, line in enumerate([l for l in m.group(1).split("\n") if l.strip()], start=1):
        u = re.search(r"https?://\S+", line)
        rows[i] = u.group(0) if u else None
    return rows


def source_lines(text):
    """Raw public BLOCK 5 lines (same slice as sources_by_row)."""
    m = re.search(r"=== BLOCK 5: SOURCES ===\s*```\s*\n(.*?)(?:\n---|```)", text, flags=re.S)
    return [l for l in m.group(1).split("\n") if l.strip()] if m else []


def section(body, h2):
    """Text of one H2 section of BLOCK 3 (without the heading), or ''."""
    m = re.search(r"^%s[^\n]*\n(.*?)(?=^## |\Z)" % re.escape(h2), body, flags=re.S | re.M)
    return m.group(1) if m else ""


def prose_words(text):
    """Word count the way the templates mean it: no tables, headings, citations or markers."""
    t = re.sub(r"<sup>.*?</sup>", "", text)
    t = re.sub(r"\[[^\]]*\]", "", t)
    lines = [l for l in t.split("\n") if l.strip() and not l.lstrip().startswith(("|", "#"))]
    return len(" ".join(lines).split())


def unsourced_hedges(body):
    """Sentences of BLOCK 3 prose containing a hedge word but no citation/marker (v1.2 'no guessing')."""
    hits = []
    for line in body.split("\n"):
        if not line.strip() or line.lstrip().startswith(("|", "#")):
            continue
        # a list item is one unit (its citation usually sits at the end of the item); prose is split into sentences
        units = [line] if line.lstrip().startswith(("- ", "* ")) or re.match(r"^\s*\d+\. ", line) else re.split(r"(?<=[.؟!])\s+", line)
        for sent in units:
            if HEDGE_RE.search(sent) and "<sup>" not in sent and "[نیازمند بررسی" not in sent and "[منبع لازم" not in sent:
                hits.append(sent.strip())
    return hits


def v12_report(body, text):
    """Prompt v1.2 checks — informational WARN lines only."""
    print("--- prompt v1.2 (WARN only) ---")
    hist = section(body, HISTORY_H2)
    hw = prose_words(hist) if hist else 0
    has_table = any(l.lstrip().startswith("|") and DYNASTY_TABLE_KEY in l for l in hist.split("\n"))
    status = "OK" if HISTORY_MIN <= hw <= HISTORY_MAX else "WARN"
    print("history words    : %d  (target %d-%d)  %s" % (hw, HISTORY_MIN, HISTORY_MAX, status))
    print("dynasty table    : %s" % ("present" if has_table else "WARN missing (جدول سلسله‌ها و دولت‌های حاکم بر پهنه‌ی استان)"))
    print("fifth H3 (§23)   : %s" % ("present" if FIFTH_H3 in body else "WARN missing (%s)" % FIFTH_H3[4:]))
    hedges = unsourced_hedges(body)
    print("unsourced hedges : %d  %s" % (len(hedges), "" if not hedges else "WARN — حدس بی‌ارجاع؟ (حدود/احتمالاً/شاید/گفته می‌شود …)"))
    for h in hedges[:5]:
        print("   -", h[:110])
    counts = {p: 0 for p in SOURCE_PREFIXES}
    for l in source_lines(text):
        for p in SOURCE_PREFIXES:
            if l.lstrip().startswith(p):
                counts[p] += 1
    print("source prefixes  :", "  ".join("%s=%d" % (p, n) for p, n in counts.items()))


def is_official(url):
    host = re.sub(r"^https?://", "", url).split("/")[0].lower()
    return any(host == d.lstrip(".") or host.endswith(d) for d in OFFICIAL)


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
    head, body, text = read_blocks(sys.argv[1])
    rows = sources_by_row(text)
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
    official = [u for u in ext if is_official(u)]
    print("internal links   : %d   external links: %d (dofollow-eligible official: %d)" % (len(links), len(ext), len(official)))
    if not ext or not official:
        fails.append("external_links")
    cites = re.findall(r"<sup>\[([۰-۹0-9]+)\]\((https?://[^)\s]*)\)</sup>", body)
    bad_cites = []
    for n, u in cites:
        k = int(n.translate(FA_DIGITS))
        if rows.get(k) != u:
            bad_cites.append((k, u, rows.get(k)))
    print("citations <sup>  : %d  (BLOCK 5 rows: %d, mismatched: %d)" % (len(cites), len(rows), len(bad_cites)))
    for k, u, want in bad_cites:
        print("   row %d cites %s but BLOCK 5 has %s" % (k, u, want))
    if bad_cites:
        fails.append("citation_numbers")
    loose = [u for u in ext if u not in [c[1] for c in cites]]
    if loose:
        print("   external links outside <sup> citations:", len(loose))
    print("secondary keywords in body:")
    for s in secs:
        c = len(re.findall(re.escape(s), body))
        print("   %3d  %s" % (c, s))
        if c == 0:
            fails.append("secondary:" + s)
    print("markers          : [نیازمند بررسی]=%d  [منبع لازم]=%d  [URL لازم]=%d  (به‌زودی)=%d" % (
        body.count("[نیازمند بررسی"), body.count("[منبع لازم"), body.count("[URL لازم"), body.count("(به‌زودی)")))
    v12_report(body, text)
    print("RESULT           :", "PASS" if not fails else "FAIL " + ", ".join(fails))
    sys.exit(1 if fails else 0)


if __name__ == "__main__":
    main()
