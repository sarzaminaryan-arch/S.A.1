#!/usr/bin/env python3
"""Offline parser checks: county-cover placement, JSON-LD images, open map iframe (2.22.91)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import wp_agent as wa  # noqa: E402

page = wa._SeoParser()
page.feed('''<html><head><meta property="og:image" content="https://example.test/cover.webp">
<script type="application/ld+json">{"@type":"AdministrativeArea","image":"https://example.test/cover.webp"}</script></head>
<body><figure class="sa-county-cover"><img class="sa-county-cover__img" src="https://example.test/cover.webp"></figure></body></html>''')
assert page.county_cover_figures == 1
assert page.county_cover_images == 1
assert page.county_cover_image_srcs == ["https://example.test/cover.webp"]
assert wa._jsonld_images(page.jsonld) == ["https://example.test/cover.webp"]
assert "https://example.test/cover.webp" in page.meta["og:image"]

# The county map is an OPEN iframe (2.22.91) — parsed, and the retired load button
# is counted so a regression can never pass the health gate silently.
map_page = wa._SeoParser()
map_page.feed('<html><body><main><figure class="sa-map" id="sa-map">'
              '<div class="sa-map__embed"><iframe class="sa-map__frame" src="https://www.openstreetmap.org/export/embed.html"></iframe></div>'
              '<noscript><p>موقعیت</p></noscript></figure><p class="t">متن مقاله</p></main></body></html>')
assert map_page.map_iframes == 1 and map_page.map_frame_buttons == 0, (map_page.map_iframes, map_page.map_frame_buttons)
assert map_page.text_parts and "متن مقاله" in " ".join(map_page.text_parts)
assert "iframe" not in " ".join(map_page.text_parts)  # interface text is not article text

old_facade = wa._SeoParser()
old_facade.feed('<html><body><main><div class="sa-map__facade"><span class="sa-map__load">بارگذاری نقشهٔ تعاملی</span></div></main></body></html>')
assert old_facade.map_iframes == 0 and old_facade.map_frame_buttons == 2

archive = wa._SeoParser()
archive.feed('<html><body><article><img src="x.webp"></article></body></html>')
assert archive.county_cover_figures == archive.county_cover_images == 0
print("PASS: probe parser detects county cover markup, JSON-LD images and the open map iframe (no load button).")
