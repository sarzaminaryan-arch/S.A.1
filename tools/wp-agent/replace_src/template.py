"""The one county-page template — the master layout (owner orders 2026-11-15).

THE PAGE (top to bottom), registered once so every county looks the same:
  1. شناسنامه (theme identity card; no «کجا برویم» box any more — 2.22.86)
  2. تجربهٔ مسافرها (theme block, from data/county-experiences.php):
     کادرهای اطلاعات ← فصل‌ها (کادرهای رنگی) ← جاهای دیدنی [شهرستان] ← نکته‌ها
     ← مردمِ این‌جا. No intro description under the title (owner, 2.22.86).
  3. THE ARTICLE BODY — the eight sections this module writes, in this order:
     intro, map, history, people, economy, stay, notables, tips.
     climate and sights live in the theme block now (seasons cards and
     «جاهای دیدنی»), so new pages carry NEITHER body section (owner, 2.22.86).
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

from gen_lib import Page

CARD_HEAD = ("شاخص", "مقدار", "توضیح")


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
    {"id": "map", "title": "شهرستان {name} کجاست؟ موقعیت روی نقشه و همسایه‌ها",
     "pattern": r"کجاست|نقشه|همسایه", "render": render_map},
    {"id": "climate", "title": "آب‌وهوا و بهترین زمان سفر",
     "pattern": r"آب‌وهوا|آب و هوا|بهترین زمان سفر", "render": render_climate,
     "legacy": True},
    {"id": "history", "title": "تاریخ و پیشینه", "pattern": r"تاریخ|پیشینه",
     "render": render_history},
    {"id": "people", "title": "مردم، زبان و هنرها", "pattern": r"مردم",
     "render": render_people},
    {"id": "economy", "title": "اقتصاد و معیشت",
     "pattern": r"اقتصاد|معیشت|کشاورزی|صنعت|کارِ مردم", "render": render_economy},
    {"id": "sights", "title": "جاهای دیدنی {name}", "pattern": r"جاهای دیدنی|جاذبه",
     "render": render_sights, "legacy": True},
    {"id": "stay", "title": "راه رسیدن و جای ماندن",
     "pattern": r"راه رسیدن|جای ماندن|چطور برسیم|راه و ارتباط", "render": render_stay},
    {"id": "notables", "title": "چهره‌های نام‌آور {name}", "pattern": r"چهره‌ها|نام‌آور|مشاهیر",
     "render": render_notables, "optional": True},
    {"id": "tips", "title": "چند نکتهٔ کوچک از {name}", "pattern": r"چند نکته|اگر فقط یک روز",
     "render": render_tips},
    {"id": "request", "title": "از مردم عزیز شهرستان {name}، یک درخواست داریم",
     "pattern": r"یک درخواست داریم", "render": render_request, "removed": True},
)

REQUIRED_IDS = ("intro", "map", "history", "people", "economy", "stay", "tips")

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

    def tips(self, items):
        self.section("tips", items)

    def request(self, html):
        self.section("request", html)

    def finish(self) -> Page:
        """Master shape (owner orders 2026-11-15 evening, 2.22.86):
        required = the eight-section body minus optional notables;
        legacy (climate/sights) and removed (card/request) must not be written."""
        banned = [s["id"] for s in SECTIONS if (s.get("legacy") or s.get("removed")) and s["id"] in self.done]
        if banned:
            raise ValueError(f"{self.name}: legacy/owner-removed sections written: {banned}")
        missing = [k for k in REQUIRED_IDS if k not in self.done]
        if missing:
            raise ValueError(f"{self.name}: sections missing before finish: {missing}")
        order = [s["id"] for s in SECTIONS]
        idx = [order.index(k) for k in self.done]
        if idx != sorted(idx):
            raise ValueError(f"{self.name}: sections out of registry order: {self.done}")
        return self.page
