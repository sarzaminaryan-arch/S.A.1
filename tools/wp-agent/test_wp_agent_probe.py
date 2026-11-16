#!/usr/bin/env python3
"""Offline parser checks for county-cover placement and JSON-LD images."""
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

archive = wa._SeoParser()
archive.feed('<html><body><article><img src="x.webp"></article></body></html>')
assert archive.county_cover_figures == archive.county_cover_images == 0
print("PASS: probe parser detects county single cover markup and JSON-LD image without matching archive thumbnails.")
