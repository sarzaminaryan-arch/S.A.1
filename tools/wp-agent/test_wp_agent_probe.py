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
# Health rule (2026-11-17): a county without coordinates must not be treated as
# broken for having no map — that false alarm rolled back a healthy edit.
no_coords = {"url": "https://x/city/khorramshahr/", "status": 200, "title": "t", "robots": "index, follow",
             "h1": 1, "map_iframes": 0, "map_frame_buttons": 0, "map_coords": False}
assert wa.unhealthy_pages([no_coords]) == [], wa.unhealthy_pages([no_coords])
promised = dict(no_coords, url="https://x/city/maragheh/", map_coords=True)
assert wa.unhealthy_pages([promised]), "a page that carries coordinates must show its map"
gated = dict(no_coords, map_iframes=1, map_frame_buttons=1)
assert wa.unhealthy_pages([gated]), "the retired «بارگذاری نقشه» button must still fail the probe"

print("PASS: probe parser detects county cover markup, JSON-LD images and the open map iframe (no load button).")

# 2) Licensed place photos: the credit line is part of the design, so the probe counts it.
photo_html = """<section class="sa-spotlight"><figure class="sa-photo__frame sa-spotlight__frame">
<img src="a.jpg" alt="x"><figcaption class="sa-photo__credit">عکس: الف · CC BY-SA 3.0</figcaption></figure></section>
<section class="sa-strip"><article class="sa-strip__tile"><img src="b.jpg" alt="y">
<p class="sa-strip__credit">عکس: ب · CC BY-SA 3.0</p></article>
<article class="sa-strip__tile"><img src="c.jpg" alt="z"></article></section>"""
pp = wa._SeoParser()
pp.feed(photo_html)
assert pp.spotlight == 1 and pp.spotlight_credits == 1, (pp.spotlight, pp.spotlight_credits)
assert pp.strip_tiles == 2 and pp.strip_credits == 1, (pp.strip_tiles, pp.strip_credits)
assert pp.imgs == 3 and pp.imgs_no_alt == 0, (pp.imgs, pp.imgs_no_alt)
print("PASS: probe counts the photo spotlight, the three-tile strip and every credit line")

# 3) «از تجربهٔ مسافرها»: the block is meta-driven, so a probe must be able to
# tell a rendered block (4 facts, 4 seasons, places, tips) from a missing one.
exp_html = """<section class="sa-field-notes" id="county-field-notes">
<div class="sa-field-facts"><div class="sa-field-fact"><span class="sa-field-fact__value">الف</span></div></div>
<div class="sa-field-seasons"><div class="sa-field-season"><span class="sa-field-season__name">بهار</span></div>
<div class="sa-field-season"><span class="sa-field-season__name">تابستان</span></div></div>
<ul class="sa-field-places__list"><li class="sa-field-place"><h4>جا</h4></li><li class="sa-field-place"><h4>جا۲</h4></li></ul>
<div class="sa-field-tips"><div class="sa-field-tip"><span class="sa-field-tip__cat">هشدار</span></div></div></section>"""
ep = wa._SeoParser()
ep.feed(exp_html)
assert ep.experience_block == 1, ep.experience_block
assert ep.experience_facts == 1 and ep.experience_seasons == 2, (ep.experience_facts, ep.experience_seasons)
assert ep.experience_places == 2 and ep.experience_tips == 1, (ep.experience_places, ep.experience_tips)
plain_page = wa._SeoParser()
plain_page.feed('<html><body><main><h2>تاریخ</h2><p>متن</p></main></body></html>')
assert plain_page.experience_block == 0 and plain_page.experience_tips == 0
print("PASS: probe counts the traveller-experience block (facts, seasons, places, tips)")
