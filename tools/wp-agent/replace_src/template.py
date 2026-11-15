"""The one county-page template (owner rule 2026-11-15).

Every rebuilt county page uses exactly these twelve sections, in this order,
with these titles. Only the text under each title changes from county to
county — that is where the unique, sourced material lives.

**Every section is one removable unit.** `SECTIONS` is the single registry: it
fixes the id, the title and the render function of each section, and both the
builder (`build_section_batch.py`, which wraps each section in
`<!-- sa:sec:<id> -->` markers so it can later be deleted or replaced across
every page) and the guard (`check_template.py`, which reads its ORDER from
here) work off this list. Adding, dropping or renaming a section happens here
and nowhere else, plus one `remove`/`replace` pass over the built pages.

The traveller block (از تجربهٔ مسافرها) is rendered by the theme above the
article body from data/county-experiences.php, so it is not part of this body;
`request` is the only section that already exists on the old pages and is kept
verbatim by the builder.

Usage:
    from template import Template
    t = Template("اهواز", tagline="...")
    t.intro([...])            # first person, sourced
    t.card(rows)
    t.map([(text, url), ...], neighbors=[...])
    t.climate(lead, [("بهار", text), ...])
    t.history([...]); t.people([...]); t.economy([...])
    t.sights([(h3_title, text), ...])
    t.stay([...]); t.notables([...]); t.tips([...])
    t.finish()                # renders `request` last and checks completeness
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
    """Where it is, its neighbours, and the roads in."""
    page.h2(title)
    for text, *urls in paragraphs:
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
    page.h2(title)
    for text, *urls in paragraphs:
        page.p(text, *urls)


def render_people(page: Page, title: str, paragraphs, list_items=None) -> None:
    page.h2(title)
    for text, *urls in paragraphs:
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_economy(page: Page, title: str, paragraphs, list_items=None) -> None:
    page.h2(title)
    for text, *urls in paragraphs:
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
    page.h2(title)
    for text, *urls in paragraphs:
        page.p(text, *urls)
    if list_items:
        page.ul(list_items)


def render_notables(page: Page, title: str, paragraphs) -> None:
    page.h2(title)
    for text, *urls in paragraphs:
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
     "render": render_card},
    {"id": "map", "title": "کجای نقشه است و همسایه‌هایش کیست‌اند؟", "pattern": r"نقشه|همسایه",
     "render": render_map},
    {"id": "climate", "title": "آب‌وهوا و بهترین زمان سفر",
     "pattern": r"آب‌وهوا|آب و هوا|بهترین زمان سفر", "render": render_climate},
    {"id": "history", "title": "تاریخ و پیشینه", "pattern": r"تاریخ|پیشینه",
     "render": render_history},
    {"id": "people", "title": "مردم، زبان و هنرها", "pattern": r"مردم",
     "render": render_people},
    {"id": "economy", "title": "اقتصاد و معیشت",
     "pattern": r"اقتصاد|معیشت|کشاورزی|صنعت|کارِ مردم", "render": render_economy},
    {"id": "sights", "title": "جاهای دیدنی {name}", "pattern": r"جاهای دیدنی|جاذبه",
     "render": render_sights},
    {"id": "stay", "title": "راه رسیدن و جای ماندن",
     "pattern": r"راه رسیدن|جای ماندن|چطور برسیم|راه و ارتباط", "render": render_stay},
    {"id": "notables", "title": "چهره‌های نام‌آور {name}", "pattern": r"چهره‌ها|نام‌آور|مشاهیر",
     "render": render_notables},
    {"id": "tips", "title": "چند نکتهٔ کوچک از {name}", "pattern": r"چند نکته|اگر فقط یک روز",
     "render": render_tips},
    {"id": "request", "title": "از مردم عزیز شهرستان {name}، یک درخواست داریم",
     "pattern": r"یک درخواست داریم", "render": render_request},
)

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
        missing = [s for s in IDS if s not in self.done and s != "request"]
        if missing:
            raise ValueError(f"{self.name}: sections missing before finish: {missing}")
        return self.page
