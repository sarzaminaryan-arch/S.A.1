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
#    From 2.22.116 the spotligh box is gone and the county gallery (slider) is the block
#    that must be present, with a data-sa-zoom frame per slide for the lightbox.
photo_html = """<section class="sa-strip sa-county-gallery" id="county-gallery">
<div class="sa-strip__head"><div class="sa-strip__title-row"><h2 class="sa-strip__title">گالری تصاویر کازرون</h2>
<span class="sa-strip__badge">۴ تصویر</span></div></div>
<div class="sa-slider" data-sa-slider><div class="sa-slider__stage"><div class="sa-slider__track">
<div class="sa-slider__slide sa-strip__slide"><article class="sa-strip__tile">
<div class="sa-strip__frame" data-sa-zoom="https://example.test/a.webp" data-sa-title="بیشاپور">
<img src="a.webp" alt="بیشاپور"><span class="sa-strip__tag">میراث</span></div>
<div class="sa-strip__highlight"><p class="sa-strip__line">یک خط</p>
<p class="sa-strip__credit">عکس: الف · CC BY-SA 3.0</p></div></article></div>
<div class="sa-slider__slide sa-strip__slide"><article class="sa-strip__tile">
<div class="sa-strip__frame"><img src="b.webp" alt="ب"></div></article></div>
</div></div></div></section>"""
pp = wa._SeoParser()
pp.feed(photo_html)
assert pp.county_gallery == 1, pp.county_gallery
assert pp.county_gallery_slides == 2, pp.county_gallery_slides
assert pp.county_zoom_frames == 1, pp.county_zoom_frames
assert pp.strip_tiles == 2 and pp.strip_credits == 1, (pp.strip_tiles, pp.strip_credits)
assert pp.imgs == 2 and pp.imgs_no_alt == 0, (pp.imgs, pp.imgs_no_alt)
assert pp.spotlight == 0 and pp.spotlight_credits == 0, (pp.spotlight, pp.spotlight_credits)
print("PASS: probe counts the county gallery slider, its zoom frames and every credit line")

# 2b) v2.22.116 accordions: the county TOC, the same-province siblings and the
#     sources box are <details> that must start CLOSED (`+`), never pre-expanded.
acc = wa._SeoParser()
acc.feed('<html><body><nav class="sa-toc sa-toc--county" id="sa-toc">'
         '<details class="sa-county-accordion"><summary class="sa-county-accordion__summary">'
         '<span class="sa-county-accordion__title" id="sa-toc-title">فهرست</span>'
         '<span class="sa-county-accordion__icon"></span></summary><ol><li>یک</li></ol></details></nav>'
         '<details class="sa-county-accordion" id="county-siblings">'
         '<summary class="sa-county-accordion__summary">شهرهای هم‌استانی</summary>'
         '<ol><li>دو</li></ol></details>'
         '<details class="sa-county-accordion sa-sources-accordion">'
         '<summary class="sa-county-accordion__summary">منابع</summary></details>'
         '</body></html>')
assert acc.accordions == 3 and acc.accordion_summaries == 3, (acc.accordions, acc.accordion_summaries)
assert acc.accordions_open == 0, acc.accordions_open
opened = wa._SeoParser()
opened.feed('<details class="sa-county-accordion" open><summary class="sa-county-accordion__summary">x</summary></details>')
assert opened.accordions_open == 1
print("PASS: probe counts the closed county accordions and flags a pre-opened one")

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
