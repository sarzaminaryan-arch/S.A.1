#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Build & lint all 18 attraction sub-branch articles for Tehran Province (Province #1)."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

from cluster_data.tehran_city_attractions import TEHRAN_CITY_ATTRACTIONS  # noqa: E402
from cluster_data.tehran_province_attractions import TEHRAN_PROVINCE_ATTRACTIONS  # noqa: E402
from fa_lint import scan as fa_scan, scan_meta as fa_scan_meta  # noqa: E402

OUT_DIR = HERE / "outbox" / "20261124-tehran-attractions"
TODAY = "۱۴۰۵/۰۹/۰۳"

EXTRA_BANNED = (
    "راستش", "به‌زودی", "در حال ساخت", "کلنگ", "اسرائیل", "اسراییل",
    "منبع معتبر", "فاقد منبع", "بدون منبع", "تأیید نمی‌شود", "تایید نمی‌شود",
    "بررسی بیشتر", "بازبینی", "منبعی یافت", "مشخص نیست",
)


def p_block(text: str) -> str:
    return f"<!-- wp:paragraph --><p>{text}</p><!-- /wp:paragraph -->"


def h2_block(text: str) -> str:
    return f'<!-- wp:heading --><h2 class="wp-block-heading">{text}</h2><!-- /wp:heading -->'


def ul_block(items: list[str]) -> str:
    lis = "".join(f"<!-- wp:list-item --><li>{it}</li><!-- /wp:list-item -->" for it in items)
    return f'<!-- wp:list --><ul class="wp-block-list">{lis}</ul><!-- /wp:list -->'


def render_attraction(spec: dict) -> tuple[str, dict]:
    parts: list[str] = []

    parts.append("<!-- sa:att:lead -->")
    for p in spec["lead"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:lead -->")

    parts.append("<!-- sa:att:history -->")
    parts.append(h2_block(f"پیشینه، داستان شکل‌گیری و ویژگی‌های {spec['title']}"))
    for p in spec["history_arch"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:history -->")

    parts.append("<!-- sa:att:sections -->")
    parts.append(h2_block("بخش‌های دیدنی و مسیر پیشنهادی گشت‌وگذار"))
    for p in spec["sections"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:sections -->")

    parts.append("<!-- sa:att:season -->")
    parts.append(h2_block("بهترین فصل و ساعت بازدید"))
    for p in spec["best_season"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:season -->")

    parts.append("<!-- sa:att:access -->")
    parts.append(h2_block("مسیر دسترسی دقیق با مترو، حمل‌ونقل عمومی و خودرو"))
    for p in spec["access"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:access -->")

    parts.append("<!-- sa:att:tips -->")
    parts.append(h2_block("نکات کاربردی و ملزومات پیش از حرکت"))
    parts.append(ul_block(spec["tips"]))
    parts.append("<!-- /sa:att:tips -->")

    parts.append("<!-- sa:att:nearby -->")
    parts.append(h2_block("دیدنی‌های هم‌مسیر برای تکمیل برنامه یک‌روزه"))
    for p in spec["nearby_narrative"]:
        parts.append(p_block(p))
    parts.append("<!-- /sa:att:nearby -->")

    parts.append("<!-- sa:att:faq -->")
    parts.append(h2_block("پرسش‌های پرتکرار بازدید"))
    faq_items = []
    for idx, qa in enumerate(spec["faqs"]):
        open_attr = " open" if idx == 0 else ""
        faq_items.append(
            f'<details class="sa-faq-item group bg-canvas rounded-2xl border border-line p-4"{open_attr}>'
            f'<summary class="sa-faq-item__summary cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-bold text-ink">'
            f'<span>{qa["q"]}</span><span class="sa-faq-item__icon" aria-hidden="true">▾</span></summary>'
            f'<p class="sa-faq-item__body text-xs sm:text-sm text-slate-600">{qa["a"]}</p></details>'
        )
    parts.append(
        "<!-- wp:html -->\n"
        '<div class="sa-faq-accordions sa-autolink-off space-y-3">\n'
        + "\n".join(faq_items)
        + "\n</div>\n<!-- /wp:html -->"
    )
    parts.append("<!-- /sa:att:faq -->")

    parts.append("<!-- sa:att:sources -->")
    parts.append(h2_block("منابع"))
    src_boxes = []
    src_meta_lines = []
    for title, org, url in spec["sources"]:
        src_boxes.append(
            f'<a class="sa-source-box" href="{url}" target="_blank" rel="noopener external" title="{title} — {org}">{org}</a>'
        )
        src_meta_lines.append(f"{title} | {org} | {url} | {TODAY}")
    parts.append(
        "<!-- wp:html -->\n"
        '<div class="sa-source-boxes sa-autolink-off flex flex-wrap gap-2">\n'
        + "\n".join(src_boxes)
        + "\n</div>\n<!-- /wp:html -->"
    )
    parts.append("<!-- /sa:att:sources -->")

    html = "\n\n".join(parts) + "\n"

    meta = {
        "sa_city_id": str(spec["city_id"]),
        "sa_province_id": "43",
        "sa_attr_area": spec["attr_area"],
        "sa_address": spec["address"],
        "sa_best_time": spec["best_time"],
        "sa_visit_duration": spec["visit_duration"],
        "sa_suitable_for": spec["suitable_for"],
        "sa_opening_hours": spec["opening_hours"],
        "sa_ticket_price": spec["ticket_price"],
        "sa_elevation": spec["elevation"],
        "sa_attraction_age": spec["attraction_age"],
        "sa_access_level": spec["access_level"],
        "sa_latitude": spec["lat"],
        "sa_longitude": spec["lng"],
        "sa_last_verified_date": TODAY,
        "sa_seo_title": spec["seo_title"],
        "sa_seo_description": spec["seo_description"],
        "sa_og_title": spec["seo_title"],
        "sa_og_description": spec["seo_description"],
        "sa_focus_keyword": spec["focus_keyword"],
        "sa_faq": json.dumps(spec["faqs"], ensure_ascii=False),
        "sa_sources": "\n".join(src_meta_lines),
    }
    return html, meta


def validate_one(slug: str, html: str, meta: dict) -> list[str]:
    problems: list[str] = []
    plain = re.sub(r"<[^>]+>", " ", html)
    wc = len(plain.split())
    if wc < 750:
        problems.append(f"word count too low: {wc} (< 750)")
    for line in fa_scan(html):
        problems.append(f"fa_lint html: {line}")
    for line in fa_scan_meta(meta):
        problems.append(f"fa_lint meta: {line}")
    for bad in EXTRA_BANNED:
        if bad in html:
            problems.append(f"banned phrase in html: {bad}")
        for k, v in meta.items():
            if isinstance(v, str) and bad in v:
                problems.append(f"banned phrase in meta[{k}]: {bad}")
    # Internal links: relative paths only, never target="_blank", never duplicated
    for m in re.finditer(r"<a\b([^>]+)>", html):
        attrs = m.group(1)
        hm = re.search(r'href="([^"]+)"', attrs)
        if not hm:
            continue
        href = hm.group(1)
        if href.startswith("/"):
            if 'target="_blank"' in attrs:
                problems.append(f"internal link carries target=_blank: {href}")
    internal_hrefs = [h for h in re.findall(r'href="(/[^"]+)"', html)]
    dupes = sorted({h for h in internal_hrefs if internal_hrefs.count(h) > 1})
    if dupes:
        problems.append(f"duplicate internal links in body: {dupes}")
    if f"/city/{meta_city_slug(meta['sa_city_id'])}/" not in internal_hrefs:
        problems.append("missing contextual link to parent city")
    if "/province/tehran/" not in internal_hrefs:
        problems.append("missing contextual link to /province/tehran/")
    att_links = [h for h in internal_hrefs if h.startswith("/attraction/")]
    if len(att_links) < 2:
        problems.append(f"fewer than 2 sibling/nearby attraction links: {att_links}")
    return problems


def meta_city_slug(cid: str) -> str:
    return {
        "213": "tehran",
        "215": "shemiranat",
        "216": "damavand",
        "217": "firuzkuh",
        "218": "varamin",
        "225": "shahriar",
    }[str(cid)]


def main() -> int:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    all_specs = TEHRAN_CITY_ATTRACTIONS + TEHRAN_PROVINCE_ATTRACTIONS
    assert len(all_specs) == 18, f"expected 18 attractions, got {len(all_specs)}"
    seen_slugs = set()
    manifest = []
    any_err = False
    for spec in all_specs:
        slug = spec["slug"]
        assert slug not in seen_slugs, f"duplicate slug {slug}"
        seen_slugs.add(slug)
        html, meta = render_attraction(spec)
        errs = validate_one(slug, html, meta)
        wc = len(re.sub(r"<[^>]+>", " ", html).split())
        if errs:
            any_err = True
            print(f"FAIL {slug} ({wc} words):")
            for e in errs:
                print(f"   - {e}")
        else:
            (OUT_DIR / f"{slug}.html").write_text(html, encoding="utf-8")
            (OUT_DIR / f"{slug}.meta.json").write_text(
                json.dumps(meta, ensure_ascii=False, indent=2) + "\n",
                encoding="utf-8",
            )
            manifest.append({
                "slug": slug,
                "title": spec["title"],
                "city_id": spec["city_id"],
                "city_slug": spec["city_slug"],
                "excerpt": spec["excerpt"],
                "words": wc,
                "content_file": f"tools/wp-agent/outbox/20261124-tehran-attractions/{slug}.html",
                "meta_file": f"tools/wp-agent/outbox/20261124-tehran-attractions/{slug}.meta.json",
            })
            print(f"OK   {slug:28s} city={spec['city_slug']:11s} words={wc}")
    if any_err:
        return 1
    (OUT_DIR / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(f"\nBuilt and verified {len(manifest)}/18 Tehran Province attraction articles.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
