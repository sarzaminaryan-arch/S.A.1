"""The one county-page template (owner rule 2026-11-15).

Every rebuilt county page uses exactly these twelve H2 sections, in this
order, with these titles. Only the text under each title changes from county
to county — that is where the unique, sourced material lives. The traveller
block (از تجربهٔ مسافرها) is rendered by the theme above the article body from
data/county-experiences.php, so it is not part of this body.

Usage:
    from template import Template
    t = Template("اهواز", tagline="...")
    t.intro(page, [...])
    t.card(page, ["شاخص", "مقدار", "توضیح"], rows)
    ...
    page = t.render()   # Page with all twelve sections in order
"""
from __future__ import annotations

from gen_lib import Page

SECTIONS = (
    "intro",
    "card",
    "map",
    "climate",
    "history",
    "people",
    "economy",
    "sights",
    "stay",
    "notables",
    "tips",
    "request",
)

TITLES = {
    "intro": "{name}؛ {tagline}",
    "card": "شناسنامهٔ کوتاه {name}",
    "map": "کجای نقشه است و همسایه‌هایش کیست‌اند؟",
    "climate": "آب‌وهوا و بهترین زمان سفر",
    "history": "تاریخ و پیشینه",
    "people": "مردم، زبان و هنرها",
    "economy": "اقتصاد و معیشت",
    "sights": "جاهای دیدنی {name}",
    "stay": "راه رسیدن و جای ماندن",
    "notables": "چهره‌های نام‌آور {name}",
    "tips": "چند نکتهٔ کوچک از {name}",
}

CARD_HEAD = ("شاخص", "مقدار", "توضیح")


class Template:
    """Collects one page's sections and renders them in the locked order."""

    def __init__(self, name: str, tagline: str, page: Page | None = None):
        self.name = name
        self.tagline = tagline
        self.page = page or Page()
        self.done: list[str] = []

    # -- helpers ---------------------------------------------------------
    def _title(self, key: str) -> str:
        return TITLES[key].format(name=self.name, tagline=self.tagline)

    def _h2(self, key: str) -> None:
        if key in self.done:
            raise ValueError(f"section repeated: {key}")
        self.done.append(key)
        self.page.h2(self._title(key))

    # -- sections --------------------------------------------------------
    def intro(self, paragraphs, cite=None):
        """Opening: 2–3 first-person paragraphs that carry the county's own story."""
        self._h2("intro")
        for text in paragraphs:
            self.page.p(text, *(cite or []))

    def card(self, rows, after=None):
        """Short identity table (indicator / value / note) + optional closing line."""
        self._h2("card")
        self.page.table(CARD_HEAD, rows)
        if after:
            self.page.p(after)

    def map(self, paragraphs, neighbors=None):
        """Where it is, its neighbours, and the roads in."""
        self._h2("map")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)
        if neighbors:
            self.page.ul(neighbors)

    def climate(self, lead, seasons):
        """One lead paragraph then the four seasons as H3 + paragraph."""
        self._h2("climate")
        self.page.p(lead)
        for season, text in seasons:
            self.page.h3(season)
            self.page.p(text)

    def history(self, paragraphs):
        self._h2("history")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)

    def people(self, paragraphs, list_items=None):
        self._h2("people")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)
        if list_items:
            self.page.ul(list_items)

    def economy(self, paragraphs, list_items=None):
        self._h2("economy")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)
        if list_items:
            self.page.ul(list_items)

    def sights(self, items):
        """items: list of (H3 title, paragraph) — the county's own sites."""
        self._h2("sights")
        for title, text in items:
            self.page.h3(title)
            self.page.p(text)

    def stay(self, paragraphs, list_items=None):
        self._h2("stay")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)
        if list_items:
            self.page.ul(list_items)

    def notables(self, paragraphs):
        self._h2("notables")
        for text, *urls in paragraphs:
            self.page.p(text, *urls)

    def tips(self, items):
        """items: list of short practical lines (no «باید»)."""
        self._h2("tips")
        self.page.ul(items)

    def finish(self) -> Page:
        missing = [s for s in SECTIONS if s not in self.done and s != "request"]
        if missing:
            raise ValueError(f"{self.name}: sections missing before finish: {missing}")
        return self.page
