"""
content_fix.py — conservative cleanup of a county page's HTML (no new text is ever added).

1. Editorial notes: sentences/clauses that are production to-dos left on live pages
   («… پیش از انتشار باید با اسناد رسمی تطبیق داده شود», «در بازبینی نهایی این مقاله …»,
   «این فهرست اولیه است …»). Owner rule (AGENTS.md item 8): no process terms in articles.
   - in <p>: the note clause (after «؛») or the whole note sentence is removed; its citation
     moves to the previous kept sentence;
   - in <li>: a list item containing a note is a placeholder, so the whole item goes.
2. Duplicate sentences: a sentence that already appeared earlier on the page is removed
   (later occurrence). A list item repeated word-for-word in a later list keeps its full
   text there and is shortened to its bold name in the earlier (summary) list.
3. Empty blocks, lists and headings left behind are removed together with their Gutenberg
   comment wrappers.

Every change is reported, so a human-readable diff goes into the job record.
"""

from __future__ import annotations

import re

try:
    from content_audit import norm, plain
except ImportError:  # pragma: no cover
    from .content_audit import norm, plain  # type: ignore

NOTE_RE = re.compile(
    r"پیش از انتشار|نسخهٔ انتشار|نسخه انتشار|بازبینی|نیازمند پژوهش|این فهرست اولیه است|از تعمیم یک رسم"
    r"|تابع مقررات است"
    r"|باید[^.؛]{0,90}?(?:کنترل|بررسی|تطبیق|تکمیل|مستند|به[‌ ]روز|نقل|دریافت|استناد|معرفی)\s*(?:شود|شوند|باشد|باشند|کرد|گردد)"
    r"|باید[^.؛]{0,60}?(?:از منابع|از مرجع|از دستگاه|با منبع|با سال مرجع)"
    r"|(?:منبع|گزارش رسمی|تطبیق|بررسی|کنترل|تأیید|سند)[^.؛]{0,60}?لازم است"
    r"|نیازمند\s+(?:تطبیق|بررسی|دادهٔ|داده|پژوهش|منبع|منابع|سند|به[‌ ]روزرسانی|کنترل|تأیید)"
    r"|به منبع نیاز دار"
    r"|(?:معتبر|منبع|سند|زندگی[‌ ]?نامه)[^.؛]{0,40}ضروری است"
    r"|باید[^.؛]{0,90}?(?:مستند|منابع معتبر|با منبع|از منبع|گزارش رسمی|پرونده|استعلام|کلی[‌ ]?گویی|تعمیم|ادعا)"
)

# «توضیح» / «مقدار» cells of the key-facts table: production notes, not facts.
CELL_NOTE_RE = re.compile(
    NOTE_RE.pattern + r"|لازم است|نیازمند|^کنترل|دادهٔ پایه|داده پایه|رابطهٔ داخلی|رابطه داخلی"
)
TABLE_RE = re.compile(r"<table\b[^>]*>.*?</table>", re.S)
ROW_RE = re.compile(r"<tr\b[^>]*>(.*?)</tr>", re.S)
CELL_RE = re.compile(r"(<t[dh]\b[^>]*>)(.*?)(</t[dh]>)", re.S)


def _clean_cell(inner: str) -> str:
    masked, store = _mask(inner)
    sups = _sups(masked)
    body = re.sub(r"\s*\x02\d+\x03", "", masked).strip()
    kept = [p for p in body.split("؛") if _visible(p) and not CELL_NOTE_RE.search(_visible(p))]
    text = "؛ ".join(x.strip() for x in kept)
    return _unmask((text + (" " if text and sups else "") + sups).strip(), store)


def clean_tables(html: str, log: list[str]) -> str:
    def fix_table(tm):
        table = tm.group(0)
        if "<th" not in table or not re.search(r"<th>\s*(?:شاخص|مقدار|توضیح)\s*</th>", table):
            return table

        def fix_row(rm):
            cells = CELL_RE.findall(rm.group(1))
            if not cells or cells[0][0].startswith("<th"):
                return rm.group(0)
            new_cells = [(a, _clean_cell(b) if k > 0 else b, c) for k, (a, b, c) in enumerate(cells)]
            # placeholder row: generic category whose only «explanation» was a to-do note
            generic = re.match(r"\s*نام‌های تاریخی", norm(plain(cells[0][1])).replace(" ", "‌", 0)) or norm(plain(cells[0][1])).startswith("نام های تاریخی")
            last_emptied = len(cells) > 2 and _visible(_mask(cells[-1][1])[0]) and not _visible(_mask(new_cells[-1][1])[0])
            if generic and last_emptied:
                log.append("table row removed (placeholder): " + norm(plain(cells[0][1])))
                return ""
            if len(new_cells) > 1 and not _visible(_mask(new_cells[1][1])[0]):
                log.append("table row removed (value was a placeholder): " + norm(plain(cells[0][1])))
                return ""
            for (a, b, c), (_, nb, _) in zip(cells, new_cells):
                if b != nb:
                    log.append("table cell: " + norm(plain(b))[:80] + " -> " + (norm(plain(nb))[:60] or "(source only)"))
            row = rm.group(0)
            for (a, b, c), (_, nb, _) in zip(cells, new_cells):
                row = row.replace(a + b + c, a + nb + c, 1)
            return row

        return ROW_RE.sub(fix_row, table)

    return TABLE_RE.sub(fix_table, html)


def apply_rewrites(html: str, rewrites: list[tuple[str, str]], log: list[str]) -> str:
    """Exact, hand-checked replacements (each must match exactly once)."""
    for old, new in rewrites:
        n = html.count(old)
        if n != 1:
            raise ValueError(f"rewrite must match once, matched {n}: {old[:80]}")
        html = html.replace(old, new, 1)
        log.append("rewrite: " + norm(plain(old))[:90] + " -> " + norm(plain(new))[:90])
    return html

SUP_RE = re.compile(r"<sup\b[^>]*>.*?</sup>", re.S | re.I)
TAG_RE = re.compile(r"<[^>]+>")
BLOCK_RE = re.compile(
    r"(?P<pre>(?:<!-- wp:(?:paragraph|list-item)[^>]*-->)?\s*)"
    r"<(?P<tag>p|li)(?P<attrs>\b[^>]*)>(?P<inner>.*?)</(?P=tag)>"
    r"(?P<post>\s*(?:<!-- /wp:(?:paragraph|list-item) -->)?)",
    re.S,
)


def _mask(inner: str):
    """Replace <sup> groups by \x02i\x03 and other tags by \x00i\x01 so '.' in URLs can't split."""
    store: list[str] = []

    def put(m, a, b):
        store.append(m.group(0))
        return f"{a}{len(store) - 1}{b}"

    s = SUP_RE.sub(lambda m: put(m, "\x02", "\x03"), inner)
    s = TAG_RE.sub(lambda m: put(m, "\x00", "\x01"), s)
    return s, store


def _unmask(s: str, store: list[str]) -> str:
    s = re.sub(r"\x02(\d+)\x03", lambda m: store[int(m.group(1))], s)
    return re.sub(r"\x00(\d+)\x01", lambda m: store[int(m.group(1))], s)


def _visible(masked: str) -> str:
    return norm(re.sub(r"\x02\d+\x03|\x00\d+\x01", "", masked))


SENT_RE = re.compile(r".*?[.!?؟](?:\s*\x02\d+\x03)*(?=\s|$)|.+$", re.S)


def _sentences(masked: str) -> list[str]:
    return [m.group(0) for m in SENT_RE.finditer(masked) if m.group(0).strip()]


def _sups(masked: str) -> str:
    return "".join(re.findall(r"\x02\d+\x03", masked))


def _strip_note_clauses(sentence: str, log: list[str]) -> str:
    """Return the sentence without note clauses ('' if the whole sentence is a note)."""
    sups = _sups(sentence)
    body = re.sub(r"\s*\x02\d+\x03", "", sentence).strip()
    parts = body.split("؛")
    if NOTE_RE.search(_visible(parts[0])):
        # «X از آثار … است و اطلاعات ثبت … باید کنترل شود» -> keep the fact before «است و»
        m = re.match(r"(.+?(?:است|هستند|اند|‌اند|دارد))\s+و\s+(.+)$", parts[0], re.S)
        if m and not NOTE_RE.search(_visible(m.group(1))) and NOTE_RE.search(_visible(m.group(2))):
            log.append("note tail: و " + _visible(m.group(2))[:140])
            parts[0] = m.group(1)
            if len(parts) == 1:
                return parts[0].rstrip(" .،") + "." + sups
        else:
            log.append("note sentence: " + _visible(body)[:140])
            return ""
    kept = [parts[0]]
    for part in parts[1:]:
        if NOTE_RE.search(_visible(part)):
            log.append("note clause: ؛" + _visible(part)[:140])
        else:
            kept.append(part)
    if len(kept) == len(parts):
        return sentence
    out = "؛".join(kept).rstrip(" .،")
    return out + "." + sups


def _empty_headings(html: str) -> set[str]:
    found = set()
    heads = list(re.finditer(r"<h([2-6])\b[^>]*>(.*?)</h\1>", html, re.S))
    for j, h in enumerate(heads):
        level = int(h.group(1))
        nxt = next((k for k in heads[j + 1:] if int(k.group(1)) <= level), None)
        seg = html[h.end():nxt.start()] if nxt else html[h.end():]
        if not norm(plain(re.sub(r"<!--.*?-->", "", seg, flags=re.S))):
            found.add(norm(plain(h.group(2))))
    return found


def clean(html: str, rewrites: list[tuple[str, str]] | None = None) -> tuple[str, list[str]]:
    log: list[str] = []
    empty_before = _empty_headings(html)
    if rewrites:
        html = apply_rewrites(html, rewrites, log)
    html = clean_tables(html, log)
    seen_sent: set[str] = set()
    seen_clause: set[str] = set()
    li_texts: dict[str, int] = {}
    blocks = list(BLOCK_RE.finditer(html))

    # pass 0: list items repeated later -> shorten the earlier one to its bold name
    shorten: set[int] = set()
    for i, m in enumerate(blocks):
        if m.group("tag") != "li":
            continue
        key = norm(plain(m.group("inner")))
        if key in li_texts and "؛" in key:
            shorten.add(li_texts[key])
        else:
            li_texts.setdefault(key, i)

    out, pos = [], 0
    for i, m in enumerate(blocks):
        out.append(html[pos:m.start()])
        pos = m.end()
        tag, inner = m.group("tag"), m.group("inner")
        masked, store = _mask(inner)
        if tag == "li":
            vis = _visible(masked)
            if NOTE_RE.search(vis):
                log.append("note item: " + vis[:140])
                continue
            if vis.rstrip(" .") + "." in seen_sent or vis in seen_sent or vis.rstrip(" .") in seen_clause:
                log.append("list item repeats an earlier sentence: " + vis[:120])
                continue
            if i in shorten:
                strong = re.match(r"\s*(\x00\d+\x01[^\x00]*\x00\d+\x01)", masked)
                if strong:
                    new = strong.group(1) + _sups(masked)
                    log.append("summary item shortened (full text kept below): " + vis[:100])
                    out.append(m.group("pre") + f"<li{m.group('attrs')}>" + _unmask(new, store) + "</li>" + m.group("post"))
                    continue
            for s in re.split(r"(?<=[.!?؟])\s+", vis):
                if len(s.split()) >= 6:
                    seen_sent.add(s)
                    seen_clause.update(c.strip().rstrip(".") for c in s.split("؛"))
            out.append(m.group(0))
            continue
        # paragraph: sentence-level
        kept: list[str] = []
        changed = False
        for s in _sentences(masked):
            s2 = _strip_note_clauses(s, log)
            if s2 != s:
                changed = True
            if not s2:
                if kept and _sups(s):
                    kept[-1] = kept[-1].rstrip() + _sups(s)
                continue
            key = _visible(s2)
            if len(key.split()) >= 6 and key in seen_sent:
                log.append("duplicate sentence: " + key[:140])
                changed = True
                if kept and _sups(s2):
                    kept[-1] = kept[-1].rstrip() + _sups(s2)
                continue
            if len(key.split()) >= 6:
                seen_sent.add(key)
                seen_clause.update(c.strip().rstrip(".") for c in key.split("؛") if len(c.split()) >= 5)
            kept.append(s2.strip())
        if not changed:
            out.append(m.group(0))
            continue
        new_inner = " ".join(kept).strip()
        if not _visible(new_inner).strip(" .،:"):
            log.append("empty paragraph removed")
            continue
        out.append(m.group("pre") + f"<p{m.group('attrs')}>" + _unmask(new_inner, store) + "</p>" + m.group("post"))
    out.append(html[pos:])
    html2 = "".join(out)

    # empty lists, then headings with no content before the next heading of the same or higher level
    html2 = re.sub(r"(?:<!-- wp:list[^>]*-->\s*)?<(ul|ol)\b[^>]*>\s*</\1>(?:\s*<!-- /wp:list -->)?", "", html2)
    while True:
        heads = list(re.finditer(r"(?:<!-- wp:heading[^>]*-->\s*)?<h([2-6])\b[^>]*>(.*?)</h\1>(?:\s*<!-- /wp:heading -->)?", html2, re.S))
        removed = False
        for j, h in enumerate(heads):
            level = int(h.group(1))
            nxt = None
            for k in heads[j + 1:]:
                if int(k.group(1)) <= level:
                    nxt = k
                    break
            end = nxt.start() if nxt else None
            seg = html2[h.end():end] if end is not None else html2[h.end():]
            if end is None:
                continue  # last section: leave alone
            if norm(plain(h.group(2))) in empty_before:
                continue  # was already text-less (e.g. a title over an image): not ours to touch
            if not norm(plain(re.sub(r"<!--.*?-->", "", seg, flags=re.S))):
                log.append("empty heading removed: " + norm(plain(h.group(2)))[:60])
                html2 = html2[:h.start()] + html2[h.end():]
                removed = True
                break
        if not removed:
            break
    # a paragraph left ending in «؛» (its last clause was a note) ends with a full stop
    html2 = re.sub(r"؛\s*((?:<sup\b[^>]*>.*?</sup>)*)\s*</p>", r".\1</p>", html2, flags=re.S)
    html2 = re.sub(r"\n{3,}", "\n\n", html2)
    return html2, log
