#!/usr/bin/env python3
"""Guard: every rebuilt county page keeps the same article shape.

The owner's rule (2026-11-15): all county pages should look like the reference
pages (تهران، مشهد، تبریز، درگز) — a readable intro, a map/neighbours section,
history, climate, economy, people, sights, staying, notables, small tips — and
the traveller-experience block is rendered by the theme, so every rebuilt page
must also have a vetted record with a non-empty field_update_note.

Owner orders of 2026-11-15: the short identity table (شناسنامهٔ کوتاه) and the
closing reader-request block (از مردم عزیز …) never come back, and from the
evening order (2.22.86) the body is EIGHT sections — climate and sights moved
into the theme's traveller block, so a new page carries neither as a body
section (legacy pages may still carry them, in order). A county without honest
content for a section skips that section (notables is the usual one).

Owner order of 2026-11-16: the body «چند نکتهٔ کوچک» (tips) section is retired —
its practical notes belong to the traveller block's own tips, so a new page
carries neither the tips heading nor its markers (old pages may keep it, in
order).

Evening orders also enforced here: a FAQ question without a solid, sourceable
answer is dropped entirely (no «no-source» phrasing anywhere — use probability
words instead), and sa_sources names wikipedia at most once.

Template-analysis orders (2026-11-16, theme 2.22.87): a new page ships with
clean numeric coordinates (Iran bounds checked; the in-page map is rendered by
the theme, never baked into post_content), the FAQ section needs at least
three valid rows, the map section tells the geography WITHOUT re-listing the
neighbour names (the identity-card chips own that), and no two parts of the
page may tell the same story (3-gram shingle Jaccard, body↔body and
body↔traveller-seed).

County-generality pass (2026-11-17, owner order «برای شهرستان‌های دیگه کارکرد
کامل‌تری داشته باشه»): the required body is the six sections every county can
honestly write; `notables` stays supported but optional, so a county whose
names cannot be verified ships without an empty heading. A page that has a map
section must carry usable coordinates — without them the theme cannot draw the
in-page map, and the page promises a map it will not show (Khorramshahr case).
The sources block is the theme's, so `sa_sources` must name at least two
distinct domains and never more than one wikipedia line.

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
from fa_lint import scan as fa_scan, scan_meta as fa_scan_meta  # noqa: E402
from theme_sources import is_forbidden, is_wiki  # noqa: E402

ORDER = order_patterns()

REMOVED = ("card", "request")          # still in the registry, never on a new page
LEGACY = ("climate", "sights")         # pre-2.22.86 body sections; order-checked when present
RETIRED = ("tips",)                    # owner-retired body section (2026-11-16): new pages must not carry it
REQUIRED = ("intro", "map", "history", "people", "economy", "stay")
OPTIONAL = ("notables",)                 # supported, never demanded (empty notables must be reported, not faked)
ORDER = tuple((label, rx) for label, rx in ORDER if label not in REMOVED)
REMOVED_PATTERNS = tuple((label, rx) for label, rx in order_patterns() if label in REMOVED)
LEGACY_PATTERNS = tuple((label, rx) for label, rx in order_patterns() if label in LEGACY)
RETIRED_PATTERNS = tuple((label, rx) for label, rx in order_patterns() if label in RETIRED)

# Section markers written by build_section_batch.py. When present they must
# exist exactly once per live section, in registry order.
MARK_OPEN = "<!-- sa:sec:{} -->"
MARK_CLOSE = "<!-- /sa:sec:{} -->"

MIN_WORDS = 900


def strip(html: str) -> str:
    return re.sub(r"<[^>]+>", "", html)


def words(html: str) -> int:
    return len(strip(html).split())


def shingles(text: str, n: int = 3) -> set:
    """Word 3-grams of normalized text (ZWNJ kept, punctuation dropped)."""
    toks = [t for t in re.split(r"[\s،؛.!؟:«»()\-—]+", strip(text)) if t]
    return {" ".join(toks[i:i + n]) for i in range(len(toks) - n + 1)}


def jaccard(a: set, b: set) -> float:
    return len(a & b) / len(a | b) if (a or b) else 0.0


def section_slice(html: str, key: str) -> str:
    """Inner HTML of one sa:sec section ('' when the page has no marker)."""
    m = re.search(
        re.escape(MARK_OPEN.format(key)) + r"(.*?)" + re.escape(MARK_CLOSE.format(key)),
        html, re.S,
    )
    return m.group(1) if m else ""


FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٫", "0123456789.")


def parse_coord(raw: str):
    """First float in any stored format (persian digits, «شمالی»…), or None."""
    raw = str(raw or "").translate(FA_DIGITS)
    m = re.search(r"-?\d+(?:\.\d+)?", raw)
    return float(m.group(0)) if m else None


# body↔traveller-seed overlap: the seed lives in the theme data file; the slug
# key tells which record to compare against (no record → that pair is skipped).
SEED_FILE = ROOT.parent.parent / "wp-content/themes/sarzaminaryan-child/data/county-experiences.php"  # ROOT=tools/wp-agent
SIM_THRESHOLD = 0.35


def seed_block(slug: str) -> dict:
    """Rough per-key extraction of one county record from the bundled seed."""
    if not SEED_FILE.is_file():
        return {}
    src = SEED_FILE.read_text(encoding="utf-8")
    m = re.search(r"\n\t'" + re.escape(slug) + r"' => array\(.*?\n\t\'[^']+\' => array\(", src, re.S)
    if not m:
        return {}
    blk = m.group(0)
    out = {}
    for key in ("road_quality", "access_difficulty", "parking_status",
                "local_tips", "extra_places", "people_character"):
        km = re.search(r"'" + key + r"' => '((?:[^'\\]|\\.)*)'", blk, re.S)
        if km:
            out[key] = km.group(1).replace("\\n", "\n").replace("\\'", "'")
    return out


# Which parts of the page must not tell the same story (تحلیل مالک، بخش ۲):
# body section ↔ traveller-block/identity data it could drift into.
OVERLAP_PAIRS = (
    ("tips", "seed:local_tips"),
    ("tips", "seed:extra_places"),
    ("people", "seed:people_character"),
    ("stay", "seed:road_quality"),
    ("map", "seed:access_difficulty"),
)


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
            if label in REQUIRED:
                problems.append(f"missing or out-of-order section: {label}")
        else:
            pos = found
    # the two owner-removed sections must not come back
    for label, rx in REMOVED_PATTERNS:
        if any(re.search(rx, title) for title in h2):
            problems.append(f"owner-removed section is present: {label}")
        if MARK_OPEN.format(label) in html or MARK_CLOSE.format(label) in html:
            problems.append(f"owner-removed section has markers: {label}")
    # owner-retired body section (tips, 2026-11-16): its notes belong to the
    # traveller block; a new page must not carry the body section.
    for label, rx in RETIRED_PATTERNS:
        if any(re.search(rx, title) for title in h2):
            problems.append(f"owner-retired section is present: {label}")
        if MARK_OPEN.format(label) in html or MARK_CLOSE.format(label) in html:
            problems.append(f"owner-retired section has markers: {label}")
    # pre-2.22.86 body sections (climate, sights): legacy pages carry them WITH
    # markers and stay valid; a page carrying the H2 WITHOUT its marker was
    # hand-written after the order — that is the violation.
    for label, rx in LEGACY_PATTERNS:
        if any(re.search(rx, title) for title in h2) and MARK_OPEN.format(label) not in html:
            problems.append(f"legacy body section on a new page: {label}")
    # one link per URL rule — it is about links inside the text, not the numbered
    # citation marks (a source keeps its number wherever it is cited again).
    text_only = re.sub(r'<sup class="sa-cite">.*?</sup>', "", html, flags=re.S)
    hrefs = re.findall(r'href="([^"]+)"', text_only)
    dupes = {h for h in hrefs if hrefs.count(h) > 1}
    if dupes:
        problems.append(f"repeated links: {sorted(dupes)[:3]}")
    # Every link behind a footnote must be a source the theme will actually
    # print: its own forbidden list plus the wiki family (never rendered).
    for u in re.findall(r'<sup class="sa-cite"><a href="([^"]+)"', html):
        if is_forbidden(u):
            problems.append(f"citation to a domain the theme never renders: {u[:60]}")
            break
        if is_wiki(u):
            problems.append(f"wiki citation in the body (wikipedia belongs to sa_sources once): {u[:60]}")
            break
    # banned wording (owner rules)
    for bad in ("باید", "راستش", "به‌زودی", "در حال ساخت", "کلنگ", "اسرائیل", "اسراییل",
                "منبع معتبر", "فاقد منبع", "بدون منبع", "تأیید نمی‌شود", "تایید نمی‌شود",
                "بررسی بیشتر", "بازبینی", "منبعی یافت"):
        if bad in html:
            problems.append(f"banned wording: {bad}")
    if re.search(r"<td>https", html):
        problems.append("raw URL inside a table cell")
    # v2.22.87: the in-page map is INJECTED BY THE THEME (the_content, prio 20);
    # a baked map block in post_content means someone hardcoded it — never again.
    if 'id="sa-map"' in html or "sa-map__facade" in html:
        problems.append("map block baked into post_content (the theme injects it)")
    # فیلتر املا و نگارش فارسی (دستور مالک، ۱۶ نوامبر ۱۴۰۵): نام ماه میلادی،
    # نیم‌فاصلهٔ جاافتاده، حرف عربی، واژهٔ ممنوع — هر یافته صفحه را رد می‌کند.
    problems += [f"fa: {line}" for line in fa_scan(html)]
    # v2.22.87 boundary rule: the map section tells the geography; the neighbour
    # NAMES belong to the identity-card chips. 3+ names repeated = the old drift.
    map_html = section_slice(html, "map")
    if map_html:
        meta_path = path.with_suffix(".meta.json")
        if meta_path.exists():
            meta = json.loads(meta_path.read_text(encoding="utf-8"))
            labels = []
            for line in str(meta.get("sa_cty_neighbors") or "").splitlines():
                name = line.split(" - ")[0].strip()
                if name:
                    labels.append(name)
            hit = [n for n in labels if n in strip(map_html)]
            if len(hit) >= 3:
                problems.append(f"map section re-lists neighbour names ({hit[:3]}; the chips own them)")
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


COUNTIES_FILE = ROOT.parent.parent / "wp-content/themes/sarzaminaryan-child/data/counties.php"  # ROOT=tools/wp-agent


def county_slugs() -> set:
    """Slugs the theme knows (data/counties.php). Empty when the file is missing."""
    if not COUNTIES_FILE.is_file():
        return set()
    return set(re.findall(r"'slug'\s*=>\s*'([a-z0-9\-]+)'",
                          COUNTIES_FILE.read_text(encoding="utf-8")))


FAQ_BANNED = ("منبع معتبر", "فاقد منبع", "بدون منبع", "تأیید نمی‌شود", "تایید نمی‌شود",
               "بررسی بیشتر", "بازبینی", "منبعی یافت", "مشخص نیست")


def html_of(path: Path) -> str:
    """Sibling .html of a `.meta.json` path ('' when the pair is missing)."""
    h = path.with_name(path.name.replace(".meta.json", ".html"))
    return h.read_text(encoding="utf-8") if h.exists() else ""


def check_meta(path: Path) -> list[str]:
    meta = json.loads(path.read_text(encoding="utf-8"))
    need = ["sa_cty_neighbors", "sa_cty_climate", "sa_cty_best_time", "sa_cty_language",
            "sa_cty_livelihood", "sa_faq", "sa_sources"]
    problems = [f"meta missing {k}" for k in need if not str(meta.get(k) or "").strip()]
    # owner rule: a FAQ question without a solid answer is dropped, and no
    # answer ever talks about missing sources — probability words instead
    try:
        faq = json.loads(meta.get("sa_faq") or "[]")
    except (ValueError, TypeError):
        faq = []
    for row in faq if isinstance(faq, list) else []:
        blob = str(row.get("q", "")) + " " + str(row.get("a", ""))
        for bad in FAQ_BANNED:
            if bad in blob:
                problems.append(f"faq wording: {bad}")
                break
    # v2.22.87 (تحلیل مالک): بخش FAQ با کمتر از ۳ پرسش معتبر اصلاً بخش نیست.
    if isinstance(faq, list) and 0 < len(faq) < 3:
        problems.append(f"faq has only {len(faq)} valid rows (< 3 → drop the section)")
    # 2026-11-17: one wikipedia line is the rule, but a page carried only by
    # wikipedia is not sourced at all — two distinct domains minimum.
    src_lines = [l for l in str(meta.get("sa_sources") or "").splitlines() if l.strip()]
    domains = set()
    for line in src_lines:
        for part in line.split("|"):
            m = re.match(r"\s*https?://(?:www\.)?([^/]+)", part)
            if m:
                domains.add(m.group(1).lower())
    if src_lines and len(domains) < 2:
        problems.append(f"sa_sources rests on {len(domains)} domain(s); needs 2+ independent ones")
    # Neighbour lines are «slug - جهت»: a chip asks the theme for /city/<slug>/,
    # so a slug that does not exist in the theme's county table is a dead chip.
    known = county_slugs()
    if known:
        unknown = [l.split(" - ")[0].strip() for l in str(meta.get("sa_cty_neighbors") or "").splitlines()
                   if " - " in l and l.split(" - ")[0].strip() and l.split(" - ")[0].strip() not in known]
        if unknown:
            problems.append(f"neighbour slugs the theme does not know: {unknown}")
    # owner rule: wikipedia named at most once in sa_sources (never rendered)
    wiki_lines = [l for l in str(meta.get("sa_sources") or "").splitlines()
                  if "wikipedia.org" in l]
    if len(wiki_lines) > 1:
        problems.append(f"sa_sources names wikipedia {len(wiki_lines)} times (max 1)")
    # v2.22.87: clean numeric coordinates are part of the master template (the
    # theme renders the in-page map + geo JSON-LD from them).
    lat = parse_coord(str(meta.get("lat") or meta.get("sa_city_latitude") or ""))
    lng = parse_coord(str(meta.get("lng") or meta.get("sa_city_longitude") or ""))
    has_map = bool(section_slice(html_of(path), "map")) if path.with_suffix(".html").exists() else False
    if lat is None or lng is None:
        # only a page that claims a map needs coordinates; a county with none on
        # record is «incomplete», and this line is how it gets reported.
        problems.append("no usable coordinates (lat/lng)" + (" — the map section cannot render" if has_map else ""))
    elif not (25 <= lat <= 40 and 44 <= lng <= 63.8):
        problems.append(f"coordinates outside Iran: {lat}, {lng}")
    zoom = str(meta.get("sa_map_zoom") or "").strip()
    if zoom and not (8 <= int(float(zoom)) <= 13):
        problems.append(f"zoom out of range 8..13: {zoom}")
    # فیلتر املا/نگارش روی متن‌های نمایشی متا (سوالات، منابع، توضیح‌ها)
    problems += [f"fa meta: {line}" for line in fa_scan_meta(meta)]
    # v2.22.87: arabic yeh/kaf leak into display text via copy-paste from wiki.
    for key, val in meta.items():
        if isinstance(val, str) and ("ي" in val or "ك" in val):
            problems.append(f"arabic yeh/kaf in meta {key}")
            break
    return problems


def check_overlap(html_path: Path, meta_path) -> list[str]:
    """No two parts of the page may tell the same story (تحلیل مالک، بخش ۲).

    Body↔body pairs drift when a section repeats another; body↔seed pairs when
    the article copies the traveller block (tips↔local_tips is the classic).
    3-gram shingle Jaccard must stay under SIM_THRESHOLD.
    """
    html = html_path.read_text(encoding="utf-8")
    problems = []
    sections = {k: section_slice(html, k) for k in ("map", "people", "stay", "tips", "history", "economy")}
    body_pairs = (("map", "people"), ("map", "stay"), ("people", "economy"), ("stay", "tips"), ("history", "people"))
    for a, b in body_pairs:
        if sections[a] and sections[b]:
            sim = jaccard(shingles(sections[a]), shingles(sections[b]))
            if sim >= SIM_THRESHOLD:
                problems.append(f"sections tell the same story: {a}↔{b} = {sim:.2f}")
    if meta_path is None:
        return problems
    meta = json.loads(meta_path.read_text(encoding="utf-8"))
    slug = str(meta.get("slug") or html_path.stem.rsplit("-", 1)[0])
    seed = seed_block(slug)
    for body_key, seed_ref in OVERLAP_PAIRS:
        seed_key = seed_ref.split(":", 1)[1]
        if sections.get(body_key) and seed.get(seed_key):
            sim = jaccard(shingles(sections[body_key]), shingles(seed[seed_key]))
            if sim >= SIM_THRESHOLD:
                problems.append(f"body {body_key} copies the traveller block ({seed_key}) = {sim:.2f}")
    return problems


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
            problems += check_overlap(html, meta if meta.exists() else None)
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
