#!/usr/bin/env python3
"""One source of truth for «which domains the theme refuses as sources».

`wp-content/themes/sarzaminaryan-child/inc/template-tags.php` holds the owner's
list in `sa_is_forbidden_source_url()`: travel portals, aggregators and wiki
mirrors whose links are never rendered in the sources block. Building a page
with one of them is a silent quality loss — the footnote number exists, the
source line never appears — so the generator and the guard both read the PHP
list here instead of keeping a second copy that drifts.

A theme without that function yields an empty set: the guard then only relies
on its own rules, and the build does not invent restrictions.
"""
from __future__ import annotations

import re
from pathlib import Path

THEME_FILE = (Path(__file__).resolve().parents[3]
              / "wp-content" / "themes" / "sarzaminaryan-child" / "inc" / "template-tags.php")


def _load() -> frozenset[str]:
    if not THEME_FILE.is_file():
        return frozenset()
    src = THEME_FILE.read_text(encoding="utf-8")
    m = re.search(r"function sa_is_forbidden_source_url\(.*?\$forbidden\s*=\s*array\((.*?)\);",
                  src, re.S)
    if not m:
        return frozenset()
    return frozenset(re.findall(r"'([a-z0-9.\-]+)'", m.group(1)))


FORBIDDEN = _load()


def host(url: str) -> str:
    m = re.match(r"\s*https?://([^/]+)", url or "")
    return (m.group(1).lower() if m else "").removeprefix("www.")


# wikipedia is a special case in the site's rules: allowed ONCE per page in
# sa_sources (a definitional reference), but the renderer still drops the line —
# so it must never carry a footnote that promises a visible source.
WIKI = ("wikipedia.org", "wikidata.org", "wikimedia.org")


def is_wiki(url: str) -> bool:
    h = host(url)
    return any(h == d or h.endswith("." + d) for d in WIKI)


def is_forbidden(url: str) -> bool:
    """Same rule as the theme: the host or any parent domain is on the list."""
    h = host(url)
    return any(h == d or h.endswith("." + d) for d in FORBIDDEN)


def blocks_citation(url: str) -> bool:
    """A URL that must not sit behind a numbered footnote (never renders)."""
    return is_wiki(url)


if __name__ == "__main__":  # tiny CLI: check a URL before you cite it
    import sys
    for u in sys.argv[1:]:
        print(("FORBIDDEN " if is_forbidden(u) else "ok        ") + u)
