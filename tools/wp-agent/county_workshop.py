#!/usr/bin/env python3
"""کارگاه تطبیق — هر صفحهٔ شهرستان را می‌سنجد، تکه‌تکه به قالب مستر می‌برد و می‌سنجد.

چرا این فایل (دستور مالک، ۲۶ آبان ۱۴۰۵): «بهترین راه برای آپدیت مقالات شهرستان‌ها
و تطبیق نوشته‌ها با افزودن دادهٔ معتبر از نظرات مردم در گوگل‌مپ و نشان… هر شهرستان
یک منبع معتبر، یک صفحهٔ مورد نیاز مردم ایران… برای اینکه سریع‌تر مطالب گذشته را به
تمپلیت مستر جدید تطبیق بدیم با اطمینان از صحت اطلاعات و سئو و املا».

قاعدهٔ اصلی: **هیچ چیز بی‌اندازه‌گیری عوض نمی‌شود.** هر صفحه سه حالا دارد:

  ۱) `survey` — صفحهٔ زنده به قطعه‌هایش شکسته می‌شود و هر قطعه برچسب می‌خورد:
     نگه‌داشتنی (fact), یادداشت تحریریه (note), از پیش در بلوک تجربه هست
     (dup-lead/dup-sights/dup-season), تکراری (dup), خالی (empty), بی‌منبع
     (unsourced) یا گم‌شده (missing). گزارش به تفکیک صفحه و در کل نوشته می‌شود.
  ۲) `migrate` — همان قطعه‌های نگه‌داشتنی، بدون یک کلمه بازنویسی، داخل اسکلت
     قالب مستر ریخته می‌شوند (شش بخش اجباری + notables؛ نشانگرهای `sa:sec`).
     یادداشت‌ها با `content_fix` حذف می‌شوند، خالی‌ها تیتر نمی‌گیرند، و اگر
     قطعه‌ای جای مطمئنی نداشت به «صندوق بازبینی» می‌رود، نه به بدنه.
  ۳) `gates` — همان گیت‌هایی که بیلد و مسیر نوشتن اجرا می‌کنند: فیلتر املا،
     رجیستری، `check_template`، منابع قابل‌رندر، مختصات، و قاعدهٔ کف واژه
     (تعداد واژهٔ بدنهٔ زنده، بدون بلوک‌های حذف‌شده).

`migrate --all` فقط صفحه‌های بی‌ریسک را می‌نویسد: صفحه‌ای که پس از مهاجرت
کوتاه‌تر از نسخهٔ زنده می‌شود یا فیلتر املا/نگهبان را رد نمی‌کند، نوشته
نمی‌شود و در گزارش با دلیلش می‌آید. صفر حدس، صفر ادعای تازه.

مصرف (از ریشهٔ مخزن):

    # ۱) گزارش وضعیت همهٔ صفحه‌های شهرستان از نتیجهٔ کار خواندن
    python3 tools/wp-agent/county_workshop.py survey results/<read-all>.json --out docs/live-site/migration-report.md

    # ۲) ساخت پیش‌نویس مهاجرت برای یک یا چند نامک (بدون نوشتن روی سایت)
    python3 tools/wp-agent/county_workshop.py migrate results/<read-all>.json abadan ramhormoz

    # ۳) ساخت همهٔ صفحه‌های ممکن و نوشتن فهرست آماده‌ها
    python3 tools/wp-agent/county_workshop.py migrate --all results/<read-all>.json
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import content_audit as ca                                      # noqa: E402
import content_fix as cf                                        # noqa: E402
from fa_lint import fix_text, scan as fa_scan                   # noqa: E402
from replace_src.template import (BY_ID, MARK_CLOSE, MARK_OPEN,  # noqa: E402
                                   REQUIRED_IDS, SECTIONS)

SEED_FILE = ROOT / "wp-content/themes/sarzaminaryan-child/data/county-experiences.php"

# -- خواندن نتیجهٔ کار خواندن ------------------------------------------------
def load_pages(read_path: Path) -> dict[str, dict]:
    """{slug: page} from a `get`/read job's result (first step with a slug)."""
    data = json.loads(read_path.read_text(encoding="utf-8"))
    if isinstance(data, list):                       # a bare list of page snapshots
        return {p["slug"]: p for p in data if p.get("slug")}
    out = {}
    for step in data.get("steps", []):
        res = step.get("result")
        if isinstance(res, dict) and res.get("slug") and isinstance(res.get("content"), str):
            out[res["slug"]] = res
    return out


# -- ۱) شکستن صفحه به قطعه ---------------------------------------------------
# Both header shapes occur in production: the Gutenberg one with the
# wp-block-heading class and the classic bare <h2>. A class-only pattern sent
# every legacy section to «review», which is exactly what the survey is for.
H2_RE = re.compile(r"<h2\b[^>]*>(.*?)</h2>", re.S)
BLOCK_SPLIT = re.compile(r"\n{2,}")


def h2_titles(html: str) -> list[tuple[int, str]]:
    return [(m.start(), re.sub(r"<[^>]+>", "", m.group(1)).strip()) for m in H2_RE.finditer(html)]


def seed_record(slug: str) -> str:
    """Raw text of one county record in the theme seed ('' when absent)."""
    if not SEED_FILE.is_file():
        return ""
    src = SEED_FILE.read_text(encoding="utf-8")
    m = re.search(r"\n\t'" + re.escape(slug) + r"' => array\(.*?\n\t\),\n", src, re.S)
    return m.group(0) if m else ""


def bigrams(text: str) -> set[str]:
    toks = [t for t in re.split(r"[\s،؛.!؟:«»()\-—]+", ca.norm(text)) if t]
    return {" ".join(toks[i:i + 2]) for i in range(len(toks) - 1)}


def overlap(a: str, b: str) -> float:
    A, B = bigrams(a), bigrams(b)
    return len(A & B) / len(A | B) if A and B else 0.0


# Live sections the theme strips or that the master template retires. Their prose
# is NOT lost: it is re-labelled into the master section that owns the topic, but
# it must not be counted as a promise on the finished page.
STRIPPED_SECTIONS = {"request"}
SEEDED_SECTIONS = {"tips"}


# Sentences that instruct the writer rather than tell the reader anything about
# the county. They are exactly what the owner banned («باید» phrasing), so a page
# that ships them fails its own lint — they belong in the brief, not the body.
# A body sentence that tells someone what must be done is editorial scaffolding, not
# a fact: «… ترسیم دقیق آن باید از نقشه‌ها و مصوبه‌های رسمی انجام شود». The owner
# banned «باید» in page text, so such a sentence can never ship — it moves to the
# brief. The one exception is reader advice in the second person («باید بلیت را
# از قبل بخرید»), which is the template's own voice.
# «باید» and «نباید» are both banned in page text; only the *no-space* «باید» is a
# word boundary risk, so plain substring search is the honest test.
DIRECTIVE_RX = re.compile(r"باید")
READER_ADVICE_RX = re.compile(r"باید[^.!؟]{0,40}(?:بخرید|بروید|بگیرید|بپرسید|بیایید|بمانید|بپوشید|بنویسید|ببرید)")
EDITORIAL_RX = (
    r"در (?:بازنویسی|این مقاله|این بخش|نسخهٔ|معرفی)", r"پیش‌?نویس پژوهشی",
    r"این فهرست اولیه", r"نیازمند (?:تطبیق|بازبینی|سند|منبع)", r"پرهیز می‌شود",
    r"منبع معتبر", r"بدون منبع", r"بدون (?:سند|مدرک)", r"دادهٔ پایه", r"فرض نکنیم", r"حدس",
    # «از نوشتن رتبه … بدون سال و منبع رسمی پرهیز می‌شود» — instruction to the writer
    r"پرهیز", r"اکتفا نشود", r"بسنده نکنید", r"قابل (?:اتکا|اعتماد) نیست",
)


def directive_hit(text: str) -> str:
    """Why this sentence instructs the writer instead of informing the reader."""
    if DIRECTIVE_RX.search(text) and not READER_ADVICE_RX.search(text):
        m = DIRECTIVE_RX.search(text)
        ctx = text[max(0, m.start() - 25): m.end() + 30].strip()
        return f"editorial «باید»: «{ctx}»"
    for rx in EDITORIAL_RX:
        m = re.search(rx, text)
        if m:
            return f"editorial note: «{m.group(0)[:30]}»"
    return ""


LINK_RE = re.compile(r'<a\s[^>]*href="([^"]+)"', re.I)


def _is_nav_placeholder(raw: str, title: str) -> bool:
    """«… باقی موارد تا تکمیل مقاله با «به‌زودی» مشخص شده‌اند» + strips of
    «مشهد (به‌زودی)» — the «site is being built» furniture the owner banned."""
    if "به‌زودی" in raw:
        return True
    return bool(re.match(r"^\s*(?:<p>)?\s*<strong>\s*ناوبری", raw)) or title.startswith("ناوبری")


def _is_link_list(raw: str) -> bool:
    """A paragraph that is basically a row of internal links («پیوندهای داخلی …»)."""
    links = LINK_RE.findall(raw)
    if len(links) < 4:
        return False
    text = ca.plain(re.sub(r"<a[^>]*>.*?</a>", "", raw, flags=re.S))
    return len(ca.norm(text).split()) < 25


# The theme silently drops forbidden domains from `sa_sources`, so a body mark
# pointing at one is a promise the page cannot keep. The sentence stays; the
# footnote goes. (`theme_sources.py` is the single source of truth.)
from theme_sources import is_forbidden  # noqa: E402


def drop_unrenderable_cites(raw: str) -> tuple[str, int]:
    n = 0

    def sub(m):
        nonlocal n
        url = re.search(r'href="([^"]+)"', m.group(1))
        if url and is_forbidden(url.group(1)):
            n += 1
            return ""
        return m.group(0)

    return re.sub(r'<sup class="sa-cite">(.*?)</sup>', sub, raw, flags=re.S), n


def dedupe_links(html: str) -> str:
    """One link per URL across the whole body (the shape guard's rule). Later
    repeats keep their words and lose only the anchor."""
    seen: set[str] = set()

    def sub(m):
        href = m.group(1)
        if href in seen:
            return m.group(2)                 # keep the anchor text, drop the link
        seen.add(href)
        return m.group(0)

    return re.sub(r'<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>', sub, html, flags=re.S)


SENT_SPLIT = re.compile(r"(?<=[.!؟])\s+")
TAG_RE = re.compile(r"<[^>]+>")


BLOCK_ATOM = re.compile(r"<(figure|table|ul|ol|blockquote)\b.*?</\1>", re.S)


def split_blocks(chunk: str) -> list[str]:
    """Paragraph pieces, with each table/list/quote kept whole.

    A legacy table can contain blank lines between rows; splitting on them would
    hand half a table to the paragraph normaliser, which strips tags by design.
    """
    atoms: list[str] = []

    def hold(m):
        atoms.append(m.group(0))
        return f"\x03{len(atoms) - 1}\x04"

    masked = BLOCK_ATOM.sub(hold, chunk)
    # `<p>a</p>\n<p>b</p>` is two paragraphs, not one: the legacy joiner separated
    # them with a single newline, and a note in the second must never cost the
    # fact in the first.
    masked = re.sub(r"</p>\s*(?=<p\b)", "</p>\x05<p", masked)
    out = []
    for piece in re.split(r"\x05|\n{2,}", masked):
        piece = piece.strip()
        if not piece:
            continue
        out.append(re.sub(r"\x03(\d+)\x04", lambda m: atoms[int(m.group(1))], piece))
    return out


def close_tags(raw: str) -> str:
    """Give every piece a valid, balanced shape.

    The legacy builder joined strings, so a `<p>` can open in one piece and close in
    the next. Rather than guess, each piece is balanced on its own: a stray `</p>`
    gets its opening tag back, a bare text piece becomes a paragraph, and a `<p>`
    hugging a table/list is unwrapped (a block cannot live inside a paragraph).
    """
    raw = raw.strip()
    leading = ""
    m = re.match(r"\s*(<!--\s*/?\s*wp:[^>]*-->)\s*", raw)
    if m:
        leading, raw = m.group(1), raw[m.end():]

    has_block = re.search(r"<(table|figure|ul|ol|blockquote)\b", raw) is not None
    if has_block:
        raw = re.sub(r"<p\b[^>]*>\s*(?=<(?:figure|table|ul|ol|blockquote)\b)", "", raw)
        raw = re.sub(r"</p>\s*(?=<(?:figure|table|ul|ol|blockquote)\b)", "", raw)

    opens, closes = len(re.findall(r"<p\b", raw)), len(re.findall(r"</p>", raw))
    if closes > opens and not has_block:
        raw = "<p>" * (closes - opens) + raw
    elif opens > closes:
        raw = re.sub(r"<!--\s*/\s*wp:paragraph\s*-->\s*$", "", raw).rstrip()
        raw += "</p>" * (opens - closes)
    elif opens == 0 and closes == 0 and not has_block:
        raw = re.sub(r"<!--\s*/?\s*wp:paragraph\s*-->", "", raw).strip()
        if raw:
            raw = f"<p>{raw}</p>"
    # «… شناخته می‌شود:.» never happens in Persian: a colon ends the clause
    raw = re.sub(r"([:؛])\.(\s|$)", r"\1\2", raw)
    return (leading + "\n" + raw).strip()


def tidy_paragraph(raw: str) -> str:
    """One paragraph, properly closed.

    Legacy bodies were written by a string joiner, so paragraphs arrive as
    `<!-- wp:paragraph --><p>…` with the closing `</p>` on a different piece and
    the next `<p>` opened after the full stop. Normalising here is what makes the
    migrated HTML valid instead of nested.
    """
    has_block = "wp:paragraph" in raw
    raw = re.sub(r"<!--.*?-->", "", raw, flags=re.S)
    if re.search(r"<(table|figure|ul|ol|blockquote)\b", raw):
        # A table/list piece may have been split off in the middle of a <p>; close
        # what is open so the migrated body is valid HTML, not nested paragraphs.
        raw = raw.strip()
        opens = len(re.findall(r"<p\b", raw))
        closes = len(re.findall(r"</p>", raw))
        if opens > closes:
            raw = re.sub(r"<!--\s*/\s*wp:paragraph\s*-->\s*$", "", raw)
            raw += "</p>" * (opens - closes)
        return raw
    # keep the footnote marks: the citation rules run on them later, and stripping
    # them leaves a bare «Maps3.» in the Persian text
    sups: list[str] = []

    def hold(m):
        sups.append(m.group(0))
        return f"\x01{len(sups) - 1}\x02"

    raw = re.sub(r'<sup\b[^>]*>.*?</sup>', hold, raw, flags=re.S)
    text = raw
    # «</p><p>» mid-piece = two paragraphs
    text = re.sub(r"</p>\s*<p[^>]*>", "\x00", text)
    chunks = [c for c in text.split("\x00")]
    out = []
    for c in chunks:
        c = TAG_RE.sub("", c).strip()
        if c:
            out.append(f"<p>{c}</p>")
    if not out:
        return ""
    # a table/list is one block, not a paragraph: wrapping it in <p> would strip its
    # tags and leave «نامکاربردتوضیح…» as text
    is_blocks = any(re.search(r"<(table|ul|ol|figure|blockquote)\b", c) for c in out)
    if is_blocks:
        html = "\n\n".join(out)
    else:
        html = ("<!-- wp:paragraph -->" + "\n\n".join(out)) if has_block else "\n\n".join(out)
    return re.sub(r"\x01(\d+)\x02", lambda m: sups[int(m.group(1))], html)


P_TAG = re.compile(r"(<p\b[^>]*>)(.*?)(</p>)", re.S)


def drop_directive_clauses(html: str) -> tuple[str, list[str]]:
    """Remove instruction sentences from inside a paragraph, keep the paragraph.

    Split-and-rejoin can leave the surviving sentence without its closing tag, so
    the paragraph is rebuilt here; a paragraph that becomes empty is removed.
    """
    dropped: list[str] = []

    def fix_p(m):
        inner = m.group(2)
        kept = []
        for sent in re.split(r"(?<=[.!؟])\s*", inner):
            if not sent.strip():
                continue
            if directive_hit(ca.norm(ca.plain(sent))):
                dropped.append(ca.norm(ca.plain(sent))[:60])
                continue
            kept.append(sent)
        if not kept:
            return ""
        kept = [k.strip() for k in kept if k.strip()]
        tail = kept[-1]
        if not re.search(r"[.!؟:؛…]\s*(?:<[^>]+>\s*)*$", tail):
            tail = tail.rstrip() + "."
        return m.group(1) + " ".join(kept[:-1] + [tail]) + m.group(3)

    html = P_TAG.sub(fix_p, html)
    return html, dropped


def triage(raw: str) -> tuple[str, str, list[str]]:
    """Sentence-level triage: keep the county, drop the instructions about it.

    Legacy bodies mix real facts with research scaffolding in one paragraph
    («… میدان مارون … است. توسعهٔ گردشگری نیز باید با حفاظت … هماهنگ باشد.»).
    Paragraph-level cleaning cannot separate them; a sentence can. Returns
    (clean html, clean text, dropped sentences' labels).
    """
    cleaned, log = cf.clean(raw)
    cleaned = tidy_paragraph(cleaned)
    kept, dropped = [], list(log)
    for chunk in SENT_SPLIT.split(cleaned):
        text = ca.norm(ca.plain(chunk))
        if not text:
            continue
        if cf.find_notes(text) or directive_hit(text):
            dropped.append(f"sentence: {text[:50]}")
            continue
        kept.append(chunk)
    html = " ".join(kept)
    html, more = drop_directive_clauses(html)
    dropped += more
    html = close_tags(html)
    return html, ca.norm(ca.plain(html)), dropped


def keep_fact(raw: str) -> tuple[str, str, list[str]]:
    """(html_without_note_clauses, plain_text, notes) — the fact survives, the note goes."""
    cleaned, log = cf.clean(raw)
    # the same deterministic fixes the site's own linter applies (half-spaces,
    # Arabic look-alike letters, digits, known phrases) — this is the spelling
    # guarantee the owner asked for, applied to text that is kept as it is.
    cleaned, spelling = fix_text(cleaned)
    text = ca.norm(ca.plain(cleaned))
    return cleaned, text, log + spelling


def classify_title(title: str) -> str | None:
    """Registry id for a heading, by the longest matching pattern — «از مردم … یک
    درخواست داریم» is the *request* block, not «مردم»."""
    best, best_len = None, 0
    for sec in SECTIONS:
        m = re.search(sec["pattern"], title)
        if m and len(m.group(0)) > best_len:
            best, best_len = sec["id"], len(m.group(0))
    return best


def classify_blocks(page: dict) -> dict:
    """Split the live body into labelled pieces — the heart of the survey."""
    html = page.get("content") or ""
    name = ca.county_name(page.get("title", ""))
    heads = h2_titles(html)
    seed = seed_record(page.get("slug") or "")
    def seed_field(key: str) -> str:
        m = re.search(r"'" + key + r"'\s*=>\s*'((?:[^'\\]|\\.)*)'", seed, re.S)
        return m.group(1) if m else ""

    seed_places = seed_field("extra_places")
    seed_tips = seed_field("local_tips")
    seed_people = seed_field("people_character")
    seed_seasons = " ".join(seed_field("season_" + k)
                            for k in ("spring", "summer", "autumn", "winter"))

    spans = []
    for i, (start, title) in enumerate(heads):
        end = heads[i + 1][0] if i + 1 < len(heads) else len(html)
        ids = [classify_title(title)] if classify_title(title) else []
        spans.append((title, start, end, ids))

    pieces = []
    for title, start, end, ids in spans:
        chunk = html[start:end]
        chunk = re.sub(r"<!--\s*/?\s*wp:heading[^>]*-->", "", chunk)
        chunk = re.sub(r"<!--\s*/?\s*sa:sec:[a-z]+\s*-->", "", chunk)
        chunk = re.sub(r"<h2[^>]*>.*?</h2>", "", chunk, flags=re.S)
        for raw in split_blocks(chunk):
            raw = raw.strip()
            if not raw:
                continue
            # escaped citation markup (legacy bug: `</sup>&lt;sup&gt;<a …>N</a></sup>`) is
            # a footnote nobody can see; unescape it so the citation rules apply.
            raw = (raw.replace("&lt;sup class=&quot;sa-cite&quot;&gt;", '<sup class="sa-cite">')
                      .replace("&lt;sup&gt;", "<sup>").replace("&lt;/sup&gt;", "</sup>"))
            text = ca.norm(ca.plain(raw))
            if not text:
                continue
            words = len(text.split())
            section = ids[0] if ids else "review"
            if not ids:
                for legacy, target in LEGACY_SECTION_MAP.items():
                    if title.startswith(legacy):
                        section = target
                        break
            if any(title.startswith(x) for x in LEGACY_DATA_SECTIONS):
                pieces.append({"section": "identity", "title": title, "html": raw,
                               "text": text, "words": words, "label": "identity-data",
                               "why": "data the identity card already prints",
                               "trimmed": False, "cites": 0})
                continue
            if _is_nav_placeholder(raw, title):
                pieces.append({"section": section, "title": title, "html": "", "text": text,
                               "words": words, "label": "link-list",
                               "why": "«به‌زودی» navigation strip", "trimmed": False, "cites": 0})
                continue
            if _is_link_list(raw):
                pieces.append({"section": section, "title": title, "html": "", "text": text,
                               "words": words, "label": "link-list",
                               "why": "internal-link furniture", "trimmed": False, "cites": 0})
                continue
            # «نام یک مکان به تنهایی به معنای آماده بودن آن برای بازدید نیست» — a
            # caveat about the list, not a place; it never ships as a fact.
            for lead in ("نام یک مکان", "تا زمان تأیید", "از نوشتن رتبه"):
                raw = re.sub(r"<p>\s*(?:<!--[^>]*-->\s*)?" + lead + r"(?:(?!</p>).)*</p>",
                             "", raw, flags=re.S).strip()
            # «… آمده‌اند. .» — the lost half of a citation leaves a double stop
            raw = re.sub(r"\.\s*</p>", "</p>", re.sub(r"(?<=[.؟!])\.\s*(?=</p>)", "", raw))
            tri_html, tri_text, dropped = triage(raw)
            if not tri_text:
                pieces.append({"section": section, "title": title, "html": "", "text": text,
                               "words": words,
                               "label": "note" if dropped else "empty",
                               "why": (dropped or ["nothing but markup"])[0][:70],
                               "trimmed": False, "cites": 0})
                continue
            raw, text, words = tri_html, tri_text, len(tri_text.split())
            label, why = "fact", ""
            if dropped:
                why = f"trimmed {len(dropped)} note sentence(s)"
            if words < 8:
                label, why = "empty", f"{words} words"
            elif section == "sights" and seed_places and overlap(text, ca.norm(seed_places)) >= 0.18:
                label, why = "dup-lead", "already in «جاهای دیدنی» (seed)"
            elif section == "tips" and seed_tips and overlap(text, ca.norm(seed_tips)) >= 0.18:
                label, why = "dup-lead", "already in «نکته‌ها» (seed)"
            elif section == "people" and seed_people and overlap(text, ca.norm(seed_people)) >= 0.25:
                label, why = "dup-lead", "already in «مردمِ این‌جا» (seed)"
            elif section == "climate" and seed_seasons and (
                    overlap(text, ca.norm(seed_seasons)) >= 0.12
                    or re.search(r"<th[^>]*>\s*فصل", raw)):
                label, why = "dup-lead", "the seasons block already prints this (seed)"
            pieces.append({"section": section, "title": title, "html": raw, "text": text,
                           "words": words, "label": label, "why": why,
                           "trimmed": bool(dropped),
                           "cites": len(re.findall(r'class="sa-cite"', raw))})
    # Notables: a paragraph whose whole job is to say the list is not ready is a
    # rubric. The owner's rule is that a section without real data is not printed.
    for p in pieces:
        if p["section"] == "notables" and p["label"] == "fact" and not re.search(
                r"(?:نام|آرامگاه|زادهٔ|زاده|متولد|شاعر|نویسنده|دانشمند|هنرمند|باستان‌شناس)\s*[«:].{2,}|"
                r"\b(?:او|وی|ایشان)\b", p["text"]):
            p["label"], p["why"] = "no-names", "notables paragraph names nobody"

    # cross-piece duplicate
    seen = set()
    for p in pieces:
        key = ca.norm(p["text"])[:160]
        if key in seen and p["label"] == "fact":
            p["label"], p["why"] = "dup", "same sentence earlier on the page"
        seen.add(key)
    # required sections that do not exist on the page at all
    present = {p["section"] for p in pieces}
    for key in ("intro", "map", "history", "people", "economy", "stay"):
        if key not in present and not any(p["section"] == key for p in pieces):
            pieces.append({"section": key, "title": "", "html": "", "text": "", "words": 0,
                           "label": "missing", "why": "section absent on the live page", "cites": 0})
    return {"name": name, "pieces": pieces}


# -- ۲) مهاجرت: همان متن، اسکلت تازه ----------------------------------------
# Legacy H2s that the registry does not own. Anything here is data the theme
# already prints in the identity card (population, area, coordinates) or a
# «حقایق جالب» list that content_audit proves is a numeric paraphrase of the
# same card — neither may enter a master-template body (duplicate rule).
LEGACY_DATA_SECTIONS = {"مشخصات کلی", "حقایق جالب", "نکته‌های کلیدی", "در یک نگاه"}

# A legacy heading the registry cannot own, mapped to where its facts belong.
LEGACY_SECTION_MAP = {
    # «تقسیمات کشوری»، «جمعیت»، «مراکز آموزشی» — administrative context, not prose
    # about a place; counted as identity data so they never enter the body.
    "تقسیمات کشوری": "identity", "جمعیت": "identity", "شهرهای شاخص": "identity",
    "معرفی": "intro", "جغرافیا": "map", "اقلیم": "climate",
    "موقعیت": "map", "حکومت": "history", "دورهٔ": "history", "آثار": "sights", "دژ ": "sights",
    "جایگاه اداری": "history", "شهر شدن": "history", "مراکز آموزشی": "people",
    "صنایع": "economy", "کشاورزی": "economy", "معیشت": "economy",
    "زبان": "people", "فرهنگ": "people", "آداب": "people",
    "مشاهیر": "notables", "چهره": "notables",
    "راه": "stay", "اقامت": "stay", "سفر": "stay", "آب و هوا": "climate",
}

SECTION_LABEL = {
    "intro": "افتتاحیه", "map": "جغرافیا", "history": "تاریخ", "people": "مردم",
    "economy": "اقتصاد", "sights": "دیدنی‌ها (بازنشسته)", "stay": "راه رسیدن",
    "notables": "چهره‌ها", "climate": "اقلیم (تفصیل)", "tips": "نکته‌ها (بازنشسته)",
}

# Where a retired/legacy section's fact can live without repeating the theme.
REDIRECT = {"sights": "stay", "climate": "map", "tips": "stay"}


def migrate(page: dict, survey: dict, cite_paragraphs: int = 6) -> dict:
    """Rebuild the body on the master skeleton from the page's own facts.

    Every fact keeps its words; every section keeps its H2 from the registry, so
    the output is a real master-template page, not a pile of paragraphs. A
    required section with no usable legacy prose is written **empty and listed**
    — the drafting stage fills it from verified data. Nothing is padded, and no
    sentence is invented here.
    """
    by_section: dict[str, list[dict]] = {}
    park: list[dict] = []
    for p in survey["pieces"]:
        if p["label"] != "fact":
            continue
        target = p["section"]
        if target in ("review", "identity"):
            park.append(p)
            continue
        if target in REDIRECT:
            target = REDIRECT[target]
        by_section.setdefault(target, []).append(p)

    cite_budget = cite_paragraphs
    rendered: dict[str, str] = {}
    for s in SECTIONS:
        key = s["id"]
        if key in ("card", "request", "tips", "sights", "climate"):
            continue                      # retired/removed/legacy body sections
        items = by_section.get(key) or []
        if not items:
            continue
        parts = []
        for it in items:
            html, _dropped = drop_unrenderable_cites(it["html"])
            if cite_budget <= 0:          # over budget: keep the sentence, drop the mark
                html = re.sub(r'<sup class="sa-cite">.*?</sup>', "", html, flags=re.S)
            elif it["cites"]:
                cite_budget -= 1
            parts.append(html)
        title = s["title"].replace("{name}", survey["name"]).replace(
            "{tagline}", ca.norm(page.get("title", "")))
        rendered[key] = f"<h2>{title}</h2>\n" + "\n\n".join(parts)

    body = "\n\n".join(
        f"{MARK_OPEN.format(k)}\n{rendered[k]}\n{MARK_CLOSE.format(k)}"
        for k in [s["id"] for s in SECTIONS] if k in rendered)
    body = dedupe_links(body)
    words = len(ca.WORD_RE.findall(ca.plain(body)))
    landing = {"intro", "map", "history", "people", "economy", "stay", "notables"}
    kept = sum(p["words"] for p in survey["pieces"]
               if p["label"] == "fact" and (p["section"] in landing or p["section"] in REDIRECT))
    return {"body": body, "sections": sorted(rendered), "words": words, "kept_words": kept,
            "missing": [k for k in REQUIRED_IDS if k not in rendered],
            "review": park}


# -- ۳) گیت‌ها ---------------------------------------------------------------
def _strip_sections(html: str, keys: set[str]) -> str:
    """Drop whole H2 sections whose registry id is in `keys` (their H2 to the next)."""
    heads = h2_titles(html)
    spans = []
    for i, (start, title) in enumerate(heads):
        ids = [s["id"] for s in SECTIONS if re.search(s["pattern"], title)]
        if ids and ids[0] in keys:
            spans.append((start, heads[i + 1][0] if i + 1 < len(heads) else len(html)))
    for start, end in reversed(spans):
        html = html[:start] + html[end:]
    return html


def rendered_before(page: dict) -> str:
    """The live body as the theme serves it: no «یک درخواست داریم», no retired
    tips/sights/climate body sections (their content lives in the seed block)."""
    html = page.get("content") or ""
    try:
        import importlib
        html = importlib.import_module("build_section_batch").cleaned_base(html)
    except Exception:  # noqa: BLE001
        pass
    return _strip_sections(html, {"request", "tips", "sights", "climate", "card"})


def live_words(page: dict, survey: dict | None = None) -> int:
    """Words the theme renders today — the only honest «before» for the floor.

    The identity table («مشخصات کلی»: population, area, coordinates) is printed by
    the theme card on the finished page, so it stays visible when it leaves the
    body; it is subtracted from the floor instead of blocking the migration.
    """
    html = rendered_before(page)
    if survey is None:
        survey = classify_blocks(page)
    for p in survey["pieces"]:
        if p["label"] == "identity-data" and p["text"]:
            html = html.replace(p["html"], "", 1)
    return len(ca.WORD_RE.findall(ca.plain(html)))


def on_template(page: dict) -> bool:
    """Is the live body already the master skeleton (all required sections marked)?"""
    html = page.get("content") or ""
    return all(
        f"<!-- sa:sec:{k} -->" in html and f"<!-- /sa:sec:{k} -->" in html
        for k in ("intro", "map", "history", "people", "economy", "stay"))


def gates(page: dict, migrated: dict, survey: dict | None = None) -> dict:
    """Every check the publishing path runs, split into **hard** and **gap**.

    hard = a page that would break the site or the owner's rules: lint, marker
    pairing, section order, forbidden source, coordinates. Gap = the page is
    structurally correct but still short of a section or of the word floor —
    that is work for the drafting stage, not a reason to refuse the skeleton.
    """
    from replace_src import check_template as ct
    import tempfile

    out = {"words_after": migrated["words"], "kept_words": migrated["kept_words"],
           "words_before": live_words(page, survey), "fa_lint": fa_scan(migrated["body"]),
           "hard": [], "gaps": []}
    with tempfile.TemporaryDirectory() as td:
        d = Path(td)
        key = f"{page.get('slug')}-{page.get('id')}"
        (d / f"{key}.html").write_text(migrated["body"], encoding="utf-8", newline="")
        problems = ct.check_html(d / f"{key}.html")
    for line in problems:
        if "words (<" in line or "missing or out-of-order section" in line:
            out["gaps"].append(line)
        else:
            out["hard"].append(line)
    out["hard"] += [f"lint: {x}" for x in out["fa_lint"][:5]]
    out["ok"] = not out["hard"]
    out["tier"] = "ready" if out["ok"] and not out["gaps"] else (
        "skeleton" if out["ok"] else "hold")
    return out


# -- ۴) بریفِ هر شهرستان ------------------------------------------------------
INSIGHT_DIR = ROOT / "tools/county-insights/data/raw"

REVIEW_CHECKLIST = [
    "کوچک‌ترین چیزِ به‌یادماندنی: یک خوراک، یک صدا، یک بو، یک کارِ روزمره که فقط مال همین‌جاست.",
    "یک عددِ دقیق که به کار می‌آید: فاصله از مرکز استان، ارتفاع، بلندی یک اثر، ساعت کاری — با منبع.",
    "یک «اشتباهِ رایج» که مسافر می‌کند و می‌شود در دو جمله جلوی آن را گرفت.",
    "یک «چرا این‌جا؟» تاریخی که در دو جملهٔ روشن گفته شود، نه فهرستِ نام و سال.",
    "یک جمله که آدم بعد از بستن صفحه برای دوستش تعریف می‌کند («رامهرمز جایی است که …»).",
]


def insight_record(slug: str) -> dict:
    path = INSIGHT_DIR / f"{slug}.json"
    if path.is_file():
        try:
            return json.loads(path.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return {}
    return {}


def brief(page: dict, survey: dict, migrated: dict, gates_: dict) -> str:
    slug, name = page.get("slug"), survey["name"]
    meta = page.get("meta") or {}
    rec = insight_record(slug)
    lines = [f"# بریف شهرستان {name} (`{slug}`، پست {page.get('id')})", ""]

    # 1) وضعیت
    lines += ["## ۱) وضعیت صفحه", "",
              f"- واژهٔ بدنهٔ زنده (همان که قالب نشان می‌دهد): **{gates_['words_before']}**",
              f"- واژه‌ای که با اطمینان منتقل شد: **{gates_['kept_words']}**",
              f"- بخش‌های حاضر پس از مهاجرت: {'، '.join(migrated['sections']) or '—'}",
              f"- بخش‌های اجباریِ گم‌شده: {'، '.join(migrated['missing']) or '—'}",
              f"- قطعه‌های پارک‌شده برای بازبینی: {len(migrated['review'])}",
              f"- وضعیت شهود و امتیاز: `{gates_['tier']}`", ""]

    # 2) نگه‌داشتنی‌ها
    lines += ["## ۲) آنچه از متن فعلی نگه داشته می‌شود (بی‌یک کلمه تغییر)", ""]
    by_sec: dict[str, list[dict]] = {}
    for p_ in survey["pieces"]:
        if p_["label"] == "fact":
            by_sec.setdefault(p_["section"], []).append(p_)
    for sec, items in by_sec.items():
        lines.append(f"**{SECTION_LABEL.get(sec, sec)}** — {sum(i['words'] for i in items)} واژه")
        for i in items:
            lines.append(f"- ({i['words']} و) {i['text'][:110]}…")
        lines.append("")

    # 3) حذف‌ها
    dropped: dict[str, list[str]] = {}
    for p_ in survey["pieces"]:
        if p_["label"] not in ("fact",):
            dropped.setdefault(p_["label"], []).append(p_["why"] or p_["text"][:60])
    lines += ["## ۳) چه چیزی حذف شد و چرا", ""]
    for label, whys in sorted(dropped.items(), key=lambda kv: -len(kv[1])):
        lines.append(f"- **{label}** ×{len(whys)} — نمونه: {whys[0]}")
    lines.append("")

    # 4) دادهٔ تجربه
    lines += ["## ۴) دادهٔ تجربهٔ مردم (منبع: نظرات، با شاهد)", ""]
    if not rec:
        lines += ["- رکورد تجربه (data/raw) ندارد؛ استخراج لازم است (extract_prompt.md).", ""]
    else:
        qf = rec.get("quick_facts") or {}
        lines.append(f"- وضعیت: `{rec.get('status') or 'draft_needs_review'}`"
                     f" · بازبین: {rec.get('verified_by') or '—'}"
                     f" · تاریخ: {rec.get('reviewed_at') or '—'}")
        for k, v in qf.items():
            if v:
                lines.append(f"- {k}: {v}")
        for t in rec.get("local_tips") or []:
            q = t.get("quote")
            ev = f" — شاهد: «{q}» ({t.get('source')}، {t.get('seen')})" if q else " — **بی‌شاهد**"
            lines.append(f"- [{t.get('category')}] {t.get('title')}: {t.get('text')}{ev}")
        for pl in rec.get("extra_places") or []:
            lines.append(f"- جای دیگر: {pl.get('name')} ({pl.get('kind')}) — {pl.get('access')}")
        lines.append("")

    # 5) منبع مرجع
    src = str(meta.get("sa_sources") or "")
    domains = sorted({re.sub(r"^https?://(www\.)?", "", u).split("/")[0]
                      for u in re.findall(r"https?://[^\s|]+", src)})
    lines += ["## ۵) منابع", "",
              f"- منبع مرجع شهرستان (authoritative_source): **{rec.get('authoritative_source') or '— تعیین نشده'}**",
              f"- دامنه‌های فعلی `sa_sources`: {'، '.join(domains) or '—'}",
              "- قاعده: حداکثر یک ویکی‌پدیا در `sa_sources` (صفر بهتر)، هیچ ویکی‌پدیا در بدنه،",
              "  حداقل دو دامنهٔ متفاوت. دادهٔ نظرات منبع نمی‌شود؛ به بلوک تجربه می‌رود.", ""]

    # 6) کارهای باقی‌مانده
    lines += ["## ۶) کارهای باقی‌مانده، به‌ترتیب", ""]
    n = 1
    for sec in migrated["missing"]:
        lines.append(f"{n}. نوشتن بخش «{SECTION_LABEL.get(sec, sec)}» از دادهٔ راستی‌آزمایی‌شده (نه از حدس).")
        n += 1
    gap = max(0, gates_["words_before"] - gates_["kept_words"])
    if gap:
        lines.append(f"{n}. کف واژه: دست‌کم {gap} واژهٔ واقعی دیگر لازم است تا صفحه کوتاه‌تر از نسخهٔ زنده نشود.")
        n += 1
    if not rec or (rec.get("status") not in ("verified",)):
        lines.append(f"{n}. استخراج/راستی‌آزمایی نظرها: هر نکته بی‌شاهد، شاهد لازم دارد.")
        n += 1
    if not rec.get("authoritative_source"):
        lines.append(f"{n}. انتخاب منبع مرجع شهرستان (فرمانداری/سازمان میراث/مرکز آمار/دانشگاه…).")
        n += 1
    if migrated["review"]:
        lines.append(f"{n}. تعیین تکلیف {len(migrated['review'])} قطعهٔ پارک‌شده (پایین).")
        n += 1
    lines.append("")

    # 7) سنجهٔ کیفیت
    lines += ["## ۷) سنجهٔ «ارزش دوباره آمدن و به‌خاطر سپردن نام»", "",
              "پیش از تأیید، صفحه باید به این پنج پرسش جواب بدهد:", ""]
    lines += [f"- [ ] {q}" for q in REVIEW_CHECKLIST]
    lines.append("")

    if migrated["review"]:
        lines += ["## ۸) قطعه‌های پارک‌شده (جای مطمئنی نداشتند)", ""]
        lines += [f"- {p_['text'][:150]}" for p_ in migrated["review"]]
        lines.append("")
    return "\n".join(lines)


def cmd_brief(args) -> int:
    pages = load_pages(Path(args.read))
    targets = list(pages) if args.all else args.slugs
    unknown = [s for s in targets if s not in pages]
    if unknown:
        raise SystemExit(f"not in the read file: {unknown}")
    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    for slug in targets:
        page = pages[slug]
        sv = classify_blocks(page)
        mig = migrate(page, sv)
        g = gates(page, mig, sv)
        if on_template(page) and not args.force:
            # already a master-template page: the workshop's job is done here
            summary.append({"slug": slug, "id": page.get("id"), "name": sv["name"],
                            "words_before": g["words_before"], "kept_words": g["kept_words"],
                            "words_after": g["words_after"], "sections": mig["sections"],
                            "missing": [], "parked": 0, "hard": [], "gaps": [],
                            "ok": True, "tier": "already"})
            continue
        text = brief(page, sv, mig, g)
        (out_dir / f"{slug}.md").write_text(text, encoding="utf-8")
    print(f"{len(targets)} brief(s) → {out_dir}")
    return 0


# -- commands ----------------------------------------------------------------
def cmd_survey(args) -> int:
    pages = load_pages(Path(args.read))
    rows = []
    for slug, page in sorted(pages.items()):
        sv = classify_blocks(page)
        labels: dict[str, int] = {}
        for p in sv["pieces"]:
            labels[p["label"]] = labels.get(p["label"], 0) + 1
        rows.append({"slug": slug, "id": page.get("id"), "name": sv["name"],
                     "words": live_words(page, sv), "labels": labels,
                     "missing": [p["section"] for p in sv["pieces"] if p["label"] == "missing"],
                     "notes": sum(p["words"] for p in sv["pieces"] if p["label"] == "note")})
    total: dict[str, int] = {}
    for r in rows:
        for k, v in r["labels"].items():
            total[k] = total.get(k, 0) + v
    report = {"pages": len(rows), "labels": total, "rows": rows}
    if args.out:
        Path(args.out).parent.mkdir(parents=True, exist_ok=True)
        Path(args.out).write_text(render_report(report), encoding="utf-8")
    print(json.dumps({"pages": report["pages"], "labels": total}, ensure_ascii=False))
    return 0


def render_report(rep: dict) -> str:
    lines = ["# گزارش وضعیت صفحه‌های شهرستان (کارگاه تطبیق)", "",
             f"صفحه‌ها: **{rep['pages']}**", "",
             "| برچسب | تعداد قطعه |", "| --- | --- |"]
    for k, v in sorted(rep["labels"].items(), key=lambda x: -x[1]):
        lines.append(f"| {k} | {v} |")
    lines += ["", "| صفحه | واژه | fact | note | dup-lead | dup | empty | review | بخش گم‌شده |",
              "| --- | --- | --- | --- | --- | --- | --- | --- | --- |"]
    for r in sorted(rep["rows"], key=lambda x: -x["words"]):
        L = r["labels"]
        lines.append("| {name} (`{slug}`) | {w} | {fact} | {note} | {lead} | {dup} | {empty} | {rev} | {miss} |".format(
            name=r["name"], slug=r["slug"], w=r["words"], fact=L.get("fact", 0), note=L.get("note", 0),
            lead=L.get("dup-lead", 0), dup=L.get("dup", 0), empty=L.get("empty", 0),
            rev=L.get("review", 0), miss="، ".join(r["missing"]) or "—"))
    lines += ["", "**راهنما:** `fact` = نگه‌داشتنی · `note` = یادداشت تحریریه (حذف) · "
              "`dup-lead` = از پیش در بلوک تجربه هست · `dup` = تکراری در همان صفحه · "
              "`empty` = کمتر از ۸ واژه · `missing` = بخش اجباری که در صفحه نیست.", ""]
    return "\n".join(lines)


def cmd_migrate(args) -> int:
    pages = load_pages(Path(args.read))
    targets = list(pages) if args.all else args.slugs
    unknown = [s for s in targets if s not in pages]
    if unknown:
        raise SystemExit(f"not in the read file: {unknown}")
    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    summary = []
    for slug in targets:
        page = pages[slug]
        sv = classify_blocks(page)
        mig = migrate(page, sv)
        g = gates(page, mig, sv)
        if on_template(page) and not args.force:
            # already a master-template page: the workshop's job is done here
            summary.append({"slug": slug, "id": page.get("id"), "name": sv["name"],
                            "words_before": g["words_before"], "kept_words": g["kept_words"],
                            "words_after": g["words_after"], "sections": mig["sections"],
                            "missing": [], "parked": 0, "hard": [], "gaps": [],
                            "ok": True, "tier": "already"})
            continue
        row = {"slug": slug, "id": page.get("id"), "name": sv["name"],
               "words_before": g["words_before"], "kept_words": g["kept_words"],
               "words_after": g["words_after"], "sections": mig["sections"],
               "missing": mig["missing"], "parked": len(mig["review"]),
               "hard": g["hard"][:5], "gaps": g["gaps"][:5],
               "ok": g["ok"], "tier": g["tier"]}
        summary.append(row)
        if g["ok"] and args.write:
            key = f"{slug}-{page.get('id')}"
            (out_dir / f"{key}.html").write_text(mig["body"], encoding="utf-8", newline="")
            json.dump({"slug": slug, "id": page.get("id"), "tier": g["tier"],
                       "sections": mig["sections"], "missing": mig["missing"],
                       "parked": [p["text"] for p in mig["review"]],
                       "words_before": g["words_before"], "kept_words": g["kept_words"]},
                      open(out_dir / f"{key}.migration.json", "w", encoding="utf-8"),
                      ensure_ascii=False, indent=1)
    (out_dir / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=1), encoding="utf-8")
    tiers = {t: sum(1 for r in summary if r["tier"] == t)
             for t in ("ready", "skeleton", "hold", "already")}
    print(f"ready={tiers['ready']}  skeleton={tiers['skeleton']}  hold={tiers['hold']}  "
          f"already={tiers['already']}  (of {len(summary)})")
    for r in summary:
        reason = "" if r["ok"] else (r["hard"][0] if r["hard"] else "")
        need = ("، ".join(r["missing"]) or "—") if r["ok"] else ""
        print(f"{r['tier'].upper():8} {r['slug']:18} kept {r['kept_words']:4}/"
              f"{r['words_before']:4} w  needs: {need:40} {reason[:60]}")
    return 0


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    s = sub.add_parser("survey")
    s.add_argument("read")
    s.add_argument("--out", default="docs/live-site/migration-report.md")
    s.set_defaults(func=cmd_survey)
    m = sub.add_parser("migrate")
    m.add_argument("read")
    m.add_argument("slugs", nargs="*")
    m.add_argument("--all", action="store_true")
    m.add_argument("--out-dir", default="tools/wp-agent/outbox/20261118-migrate")
    m.add_argument("--write", action="store_true", help="write the .html of pages that pass every gate")
    m.add_argument("--force", action="store_true", help="also rebuild pages already on the template")
    m.set_defaults(func=cmd_migrate)
    b = sub.add_parser("brief")
    b.add_argument("read")
    b.add_argument("slugs", nargs="*")
    b.add_argument("--all", action="store_true")
    b.add_argument("--out-dir", default="docs/live-site/briefs")
    b.set_defaults(func=cmd_brief)
    args = ap.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
