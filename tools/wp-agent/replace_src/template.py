"""The one county-page template — the master layout (owner orders 2026-11-15).

THE PAGE (top to bottom), registered once so every county looks the same:
  1. شناسنامه (theme identity card; no «کجا برویم» box any more — 2.22.86)
  2. تجربهٔ مسافرها (theme block, from data/county-experiences.php):
     کادرهای اطلاعات ← فصل‌ها (کادرهای رنگی) ← جاهای دیدنی [شهرستان] ← نکته‌ها
     ← مردمِ این‌جا. No intro description under the title (owner, 2.22.86).
  3. THE ARTICLE BODY — the seven sections this module writes, in this order:
     intro, map, history, people, economy, stay, notables.
     climate and sights live in the theme block now (seasons cards and
     «جاهای دیدنی»), so new pages carry NEITHER body section (owner, 2.22.86).
     The body «چند نکتهٔ کوچک» (tips) section is retired for new pages too:
     its practical notes belong to the block's «نکته‌هایی که کاش قبل از
     راه‌افتادن بدانید» (owner, 2026-11-16). It stays in the registry as
     `retired` so `remove`/`replace` can still reach the pages that carry it.
     A county without honest content for a section simply skips that section
     (notables is the usual one) — never an empty heading.
  4. شهرهای هم‌استانی (theme, printed right above منابع — 2.22.86)
  5. منابع (theme) — at most ONE wikipedia line in sa_sources; the renderer
     never shows wikipedia/booking domains at all.

Only the text under each title changes from county to county. **Every section
is one removable unit.** `SECTIONS` is the single registry: it fixes id, title,
render function and flags; the builder (`build_section_batch.py`, wrapping each
section in `<!-- sa:sec:<id> -->` markers) and the guard (`check_template.py`,
reading its ORDER from here) work off this list. Adding, dropping or renaming
a section happens here and nowhere else.

Registry flags:
  legacy  — section of the pre-2.22.86 body (climate, sights): kept so
            `remove`/`replace` still reaches the 52 pages built before the
            order; new pages must not write it.
  removed — owner-removed blocks (card, request): presence on a new page is
            a violation.
  retired — owner-retired body section (tips, 2026-11-16): new pages must not
            write it; it stays so `remove`/`replace` reaches the old pages.

Registry flags of the 2026-11-17 upgrade (county-generality pass):
  optional — a section a county may honestly lack (notables). The
             guard tolerates its absence; the builder never writes an empty
             heading.
  (The facts a county cannot be fully rendered without live in NEEDS below and
  are gated by `county_page.py`, because the Template never sees the county
  file — it only sees the sections.)

Two ways to build a page, same registry and same guard:
  1) hand-written: `gen_<slug>.py` (the Ahvaz reference) for one-off work;
  2) data-driven: `data/<slug>.json` + `county_page.py <slug>` — the default
     for every new county, because it makes the county file the only thing
     that changes (owner order 2026-11-17: «برای شهرستان‌های دیگه کارکرد
     کامل‌تری داشته باشه»).

Usage:
    from template import Template
    t = Template("اهواز", tagline="...")
    t.intro([...])            # first person, sourced
    t.map([(text, url), ...], neighbors=[...])
    t.history([...]); t.people([...]); t.economy([...])
    t.stay([...]); t.notables([...])   # notables optional
    t.tips([...])
    t.finish()                # renders and checks completeness
"""
from __future__ import annotations

try:                                   # script path: replace_src/ on sys.path
    from gen_lib import Page
except ImportError:                    # package path: imported by wp_agent.py
    from .gen_lib import Page

CARD_HEAD = ("شاخص", "مقدار", "توضیح")

# Section markers — the contract with build_section_batch.py: every section is
# one removable unit, wrapped in an invisible pair of comments. Defined here so
# the writer (this module) and the editor (build_section_batch.py) can never
# drift apart.
MARK_OPEN = "<!-- sa:sec:{} -->"
MARK_CLOSE = "<!-- /sa:sec:{} -->"


# -- renderers ------------------------------------------------------------
def render_intro(page: Page, title: str, paragraphs, cite=None) -> None:
    """Opening: 2–3 first-person paragraphs that carry the county's own story.

    Each item is either a string or a (text, *urls) tuple.
    """
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *(list(urls) or list(cite or [])))


def render_card(page: Page, title: str, rows, after=None) -> None:
    """Short identity table (indicator / value / note) + optional closing line."""
    page.h2(title)
    page.table(CARD_HEAD, rows)
    if after:
        page.p(after)


def render_map(page: Page, title: str, paragraphs, neighbors=None) -> None:
    """Where it is, its neighbours, and the roads in.

    Each item is a string or a (text, *urls) tuple.
    """
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)
    if neighbors:
        page.ul(neighbors)


def render_climate(page: Page, title: str, lead, seasons) -> None:
    """One lead paragraph then the four seasons as H3 + paragraph."""
    page.h2(title)
    page.p(lead)
    for season, text in seasons:
        page.h3(season)
        page.p(text)


def render_history(page: Page, title: str, paragraphs) -> None:
    """Each item is a string or a (text, *urls) tuple."""
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)


def render_people(page: Page, title: str, paragraphs, list_items=None) -> None:
    """Each item is a string or a (text, *urls) tuple."""
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_economy(page: Page, title: str, paragraphs, list_items=None) -> None:
    """Each item is a string or a (text, *urls) tuple."""
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_sights(page: Page, title: str, items) -> None:
    """items: list of ((H3 title, *urls) or str, paragraph) — the county's own sites."""
    page.h2(title)
    for head, text in items:
        name, urls = (head, ()) if isinstance(head, str) else (head[0], head[1:])
        page.h3(name)
        page.p(text, *urls)


def render_stay(page: Page, title: str, paragraphs, list_items=None) -> None:
    """Each item is a string or a (text, *urls) tuple."""
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_notables(page: Page, title: str, paragraphs) -> None:
    """Each item is a string or a (text, *urls) tuple."""
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)


def render_modern(page: Page, title: str, blocks) -> None:
    """امروزِ شهرستان — the Information-Gain section of the v3 body.

    `blocks` is a list of ("heading", [paragraphs]) pairs: A. زیرساخت،
    B. توسعهٔ شهری، C. اقتصاد و زندگی عمومی، D. نقد و کمبودها. A block with no
    verified paragraph is not passed in — no empty heading is ever written.
    """
    page.h2(title)
    for head, paragraphs in blocks:
        if not paragraphs:
            continue                      # never an empty heading over nothing
        if head:
            page.h3(head)
        for item in paragraphs:
            text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
            page.p(text, *urls)


def render_food(page: Page, title: str, paragraphs, list_items=None) -> None:
    """خوراک و سوغات: prose paragraphs plus an optional bulleted list.

    Restaurant names, prices, hours and ratings are written only when verified;
    a county with nothing verified carries no food section at all (optional).
    """
    page.h2(title)
    for item in paragraphs:
        text, urls = (item, ()) if isinstance(item, str) else (item[0], item[1:])
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_tips(page: Page, title: str, items) -> None:
    """items: list of short practical lines (no «باید»)."""
    page.h2(title)
    page.ul(items)


def render_request(page: Page, title: str, html: str) -> None:
    """The reader-request closing block, kept as it stands on the live page."""
    page.parts.append(html.strip())


SECTIONS = (
    {"id": "intro", "title": "{name}؛ {tagline}", "pattern": r"(؛|معرفی شهرستان)",
     "render": render_intro},
    {"id": "card", "title": "شناسنامهٔ کوتاه {name}", "pattern": r"شناسنامهٔ کوتاه",
     "render": render_card, "removed": True},
    # v2.22.87 (تحلیل مالک): تیتر «کجاست» پرجستجوی سئو؛ نگارش «کیست‌اند» غلط بود.
    # مرزبندی: بدنه فقط جغرافیای روایی است — اسم همسایه‌ها در چیپ‌های شناسنامه می‌آید.
    {"id": "map", "title": "شهرستان {name} کجاست؟ موقعیت، جغرافیا و همسایه‌ها",
     "pattern": r"کجاست|نقشه|موقعیت جغرافیایی|همسایه|لوکیشن|جغرافیا", "render": render_map},
    {"id": "climate", "title": "آب‌وهوا و بهترین زمان سفر",
     "pattern": r"آب‌وهوا|آب و هوا|بهترین زمان سفر", "render": render_climate,
     "legacy": True},
    {"id": "history", "title": "تاریخچه، پیشینه و میراث تاریخی {name}",
     "pattern": r"تاریخ|پیشینه|تاریخچه|وجه تسمیه|نام‌شناسی|میراث",
     "render": render_history},
    {"id": "people", "title": "مردم، زبان، فرهنگ و هنرهای بومی",
     "pattern": r"مردم|زبان|فرهنگ|گویش",
     "render": render_people},
    {"id": "economy", "title": "اقتصاد، مشاغل و محصولات شاخص {name}",
     "pattern": r"اقتصاد|معیشت|کشاورزی|صنایع|صنعت|کارِ مردم|داده‌های اقتصادی|مشاغل|محصولات",
     "render": render_economy},
    {"id": "sights", "title": "جاهای دیدنی {name}", "pattern": r"جاهای دیدنی|جاذبه",
     "render": render_sights, "legacy": True},
    # v3: «راهنمای سفر … مسیرها، اقامت و نکات ایمنی» — the searcher's words
    # («چطور بروم»، «کجا بمانم»، «امن است؟») in one heading. The old pattern is
    # kept so pre-v3 pages (راه رسیدن و جای ماندن) still classify correctly.
    {"id": "stay", "title": "راهنمای سفر به {name}؛ مسیرها، اقامت و نکات ایمنی",
     "pattern": r"راه رسیدن|جای ماندن|چطور برسیم|راه و ارتباط|راهنمای سفر|مسیرها|نکات ایمنی",
     "render": render_stay},
    # v3 (۲ آذر ۱۴۰۵): دو بخش تازه، و یکی بازنشسته.
    #  • `modern` — «امروز؛ زیرساخت، تحولات و چهرهٔ معاصر»: تنها بخشی از پرامپت
    #    مستر مالک که در قالب قبلی جایی نداشت (Information Gain: جاده و بای‌پس،
    #    راه‌آهن و فرودگاه، پروژه‌های شهری و صنعتی، و نقد مستند کمبودها).
    #  • `food` — «غذاها و سوغات»: خوراک و ره‌آورد، که نه در بدنه بود و نه بلوک
    #    قالب؛ جایش در «نکته‌های عملی» بلوک تجربه هم نبود.
    # دیدنی‌ها (attractions) عمداً بخش بدنه نمی‌گیرند: قالب خودش «جاهای دیدنی
    # [شهرستان]» را از seed می‌کشد و افزودن بخش دوم، همان تکرار ممنوع است.
    {"id": "modern", "title": "{name} امروز؛ زیرساخت، تحولات و چهرهٔ معاصر",
     "pattern": r"امروز|تحولات|چهرهٔ معاصر|زیرساخت|سال‌های اخیر|توسعه",
     "render": render_modern, "optional": True},
    {"id": "food", "title": "غذاها و سوغات {name}",
     "pattern": r"غذا|سوغات|خوراک|آشپزی|ره‌آورد",
     "render": render_food, "optional": True},
    {"id": "notables", "title": "آنچه در {name} می‌بینید و تجربه می‌کنید",
     "pattern": r"چهره‌ها|نام‌آور|مشاهیر|می‌بینید و تجربه",
     "render": render_notables, "optional": True},
    {"id": "tips", "title": "چند نکتهٔ کوچک از {name}", "pattern": r"چند نکته|اگر فقط یک روز",
     "render": render_tips, "retired": True},
    {"id": "request", "title": "از مردم عزیز شهرستان {name}، یک درخواست داریم",
     "pattern": r"یک درخواست داریم", "render": render_request, "removed": True},
)

REQUIRED_IDS = ("intro", "map", "history", "people", "economy", "stay")

# Optional sections (۲ آذر ۱۴۰۵): a section the page may honestly lack. The
# registry keeps them so `remove`/`replace` reaches them, the guard tolerates
# their absence, and the builder never writes an empty heading. `notables` is
# the pre-v3 catch-all (features + food + stay in one block); a page rebuilt
# for v3 uses `modern` + `food` instead and keeps `notables` only when the
# county has verified sights worth naming beyond the theme's own block.
OPTIONAL_IDS = ("modern", "food", "notables")

# v3 body budget (words, per section) — the owner prompt's own numbers, kept
# here so the guard and the writers share one scale. A section below `min` is
# reported; a section above `max` is a note, not a failure (verified content
# always wins over a budget).
WORD_BUDGET = {
    "intro": (150, 220),
    "map": (200, 320),
    "history": (240, 380),
    "people": (200, 320),
    "economy": (200, 320),
    "modern": (300, 450),
    "food": (120, 260),
    "stay": (260, 420),
}
TOTAL_MIN = 1500
TOTAL_MAX = 2400

# What a county file must carry before its page can be complete (owner
# 2026-11-17): a map section without coordinates is an empty promise — the
# theme cannot draw the map and the reader gets prose only. Khorramshahr has
# no coordinates on record, so it is reported as incomplete, not silently
# rendered (the old `unhealthy_pages()` blind spot).
NEEDS = {
    "intro": ("tagline",),
    "map": ("lat", "lng"),
}

IDS = tuple(s["id"] for s in SECTIONS)
BY_ID = {s["id"]: s for s in SECTIONS}


def order_patterns() -> tuple[tuple[str, str], ...]:
    """(id, regex) pairs for the shape guard, straight from the registry."""
    return tuple((s["id"], s["pattern"]) for s in SECTIONS)


class Template:
    """Collects one page's sections and renders them in the locked order."""

    def __init__(self, name: str, tagline: str, page: Page | None = None):
        self.name = name
        self.tagline = tagline
        self.page = page or Page()
        self.done: list[str] = []
        # each section's own html, keyed by id — what the section builder writes
        self.captured: dict[str, str] = {}

    def title(self, key: str) -> str:
        return BY_ID[key]["title"].format(name=self.name, tagline=self.tagline)

    def section(self, key: str, *args, **kwargs):
        """Render one section by id — the path the section builder uses."""
        if key in self.done:
            raise ValueError(f"section repeated: {key}")
        self.done.append(key)
        start = len(self.page.parts)
        BY_ID[key]["render"](self.page, self.title(key), *args, **kwargs)
        self.captured[key] = "\n\n".join(self.page.parts[start:])

    # -- the twelve sections ----------------------------------------------
    def intro(self, paragraphs, cite=None):
        self.section("intro", paragraphs, cite)

    def card(self, rows, after=None):
        self.section("card", rows, after)

    def map(self, paragraphs, neighbors=None):
        self.section("map", paragraphs, neighbors)

    def climate(self, lead, seasons):
        self.section("climate", lead, seasons)

    def history(self, paragraphs):
        self.section("history", paragraphs)

    def people(self, paragraphs, list_items=None):
        self.section("people", paragraphs, list_items)

    def economy(self, paragraphs, list_items=None):
        self.section("economy", paragraphs, list_items)

    def sights(self, items):
        self.section("sights", items)

    def stay(self, paragraphs, list_items=None):
        self.section("stay", paragraphs, list_items)

    def notables(self, paragraphs):
        self.section("notables", paragraphs)

    # -- v3 sections (۲ آذر ۱۴۰۵) ------------------------------------------
    # `modern` sits right after `stay` (the travelling half of the page: how you
    # get there, what it is today, what you eat); `food` closes it before the
    # optional pre-v3 `notables` block of pages not yet rebuilt.
    def modern(self, blocks):
        self.section("modern", blocks)

    def food(self, paragraphs, list_items=None):
        self.section("food", paragraphs, list_items)

    def tips(self, items):
        self.section("tips", items)

    def request(self, html):
        self.section("request", html)

    def marked_body(self) -> str:
        """Every section rendered so far, wrapped in its marker pair, in registry
        order — the exact body build_section_batch.py expects (and the only body
        shape in which a single section can later be removed or replaced)."""
        order = [s["id"] for s in SECTIONS]
        return "\n\n".join(
            f"{MARK_OPEN.format(k)}\n{self.captured[k]}\n{MARK_CLOSE.format(k)}"
            for k in sorted(self.done, key=order.index)
        )

    def finish(self) -> Page:
        """Master shape (owner orders 2026-11-15 evening, 2.22.86):
        required = the seven-section body minus optional notables;
        legacy (climate/sights), retired (tips — owner 2026-11-16) and
        removed (card/request) must not be written on a new page."""
        banned = [s["id"] for s in SECTIONS
                  if (s.get("legacy") or s.get("removed") or s.get("retired")) and s["id"] in self.done]
        if banned:
            raise ValueError(f"{self.name}: legacy/retired/owner-removed sections written: {banned}")
        missing = [k for k in REQUIRED_IDS if k not in self.done]
        if missing:
            raise ValueError(f"{self.name}: sections missing before finish: {missing}")
        order = [s["id"] for s in SECTIONS]
        idx = [order.index(k) for k in self.done]
        if idx != sorted(idx):
            raise ValueError(f"{self.name}: sections out of registry order: {self.done}")
        return self.page
