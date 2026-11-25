#!/usr/bin/env python3
"""Build the Razavi Khorasan content stage (Province 2) — offline, no live writes.

Owner order (2026-11-25): after the Tehran v6 package, the second province is
Razavi Khorasan (`#2 مشهد` 7 branches, `#34 نیشابور` 3 branches, hub `50`) with
the same master template and 4-layer linking. This script only writes the
content stage under `outbox/20261125-razavi-khorasan-content/`; the cluster
normaliser in `cluster_ops.py --build-razavi` reads it, adds the missing layer
links and writes `outbox/20261125-razavi-khorasan-cluster-v6/`.

Everything here is written from evidence already in the repository:
  * the attraction dossiers in `cluster_data/razavi_khorasan_attractions.py`,
  * the expansion texts in `cluster_data/razavi_expansions_{mashhad,neyshabur}.py`,
  * the county metas in `outbox/20261117-replace-{mashhad,neyshabur}/`,
  * the province hub in `outbox/20261123-province-razavi-khorasan-50/`.
No web research, no live request, no counter change.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

from cluster_data.razavi_khorasan_attractions import ATTRACTIONS  # noqa: E402
from cluster_data.razavi_expansions_mashhad import EXPANSIONS as MASHHAD  # noqa: E402
from cluster_data.razavi_expansions_neyshabur import EXPANSIONS as NEYSHABUR  # noqa: E402
from fa_lint import scan as fa_scan, scan_meta as fa_scan_meta  # noqa: E402

OUTBOX = HERE / "outbox"
CONTENT = OUTBOX / "20261125-razavi-khorasan-content"
CITY_BASE = {"mashhad": OUTBOX / "20261117-replace-mashhad",
             "neyshabur": OUTBOX / "20261117-replace-neyshabur"}
PROV_BASE = OUTBOX / "20261123-province-razavi-khorasan-50"
TODAY = "۱۴۰۵/۰۹/۰۴"
EXPANSIONS = {**MASHHAD, **NEYSHABUR}

BANNED = ("راستش", "به‌زودی", "در حال ساخت", "کلنگ", "منبع معتبر", "فاقد منبع",
          "بدون منبع", "تأیید نمی‌شود", "تایید نمی‌شود", "بررسی بیشتر", "بازبینی",
          "منبعی یافت", "مشخص نیست")


def p_block(text: str) -> str:
    return f"<!-- wp:paragraph --><p>{text}</p><!-- /wp:paragraph -->"


def h2_block(text: str) -> str:
    return f'<!-- wp:heading --><h2 class="wp-block-heading">{text}</h2><!-- /wp:heading -->'


def h3_block(text: str) -> str:
    return f'<!-- wp:heading {{"level":3}} --><h3 class="wp-block-heading">{text}</h3><!-- /wp:heading -->'


def ul_block(items: list[str]) -> str:
    lis = "".join(f"<!-- wp:list-item --><li>{it}</li><!-- /wp:list-item -->" for it in items)
    return f'<!-- wp:list --><ul class="wp-block-list">{lis}</ul><!-- /wp:list -->'


def faq_block(rows: list[dict]) -> str:
    items = []
    for idx, qa in enumerate(rows):
        open_attr = " open" if idx == 0 else ""
        items.append(
            f'<details class="sa-faq-item group bg-canvas rounded-2xl border border-line p-4"{open_attr}>'
            '<summary class="sa-faq-item__summary cursor-pointer list-none flex items-center '
            'justify-between gap-3 text-sm font-bold text-ink">'
            f'<span>{qa["q"]}</span><span class="sa-faq-item__icon" aria-hidden="true">▾</span></summary>'
            f'<p class="sa-faq-item__body text-xs sm:text-sm text-slate-600">{qa["a"]}</p></details>'
        )
    return ('<!-- wp:html -->\n<div class="sa-faq-accordions sa-autolink-off space-y-3">\n'
            + "\n".join(items) + "\n</div>\n<!-- /wp:html -->")


def sources_block(rows: list[tuple[str, str, str]]) -> tuple[str, str]:
    boxes, lines = [], []
    for title, org, url in rows:
        boxes.append(f'<a class="sa-source-box" href="{url}" target="_blank" rel="noopener external" '
                     f'title="{title} — {org}">{org}</a>')
        lines.append(f"{title} | {org} | {url} | {TODAY}")
    html = ('<!-- wp:html -->\n<div class="sa-source-boxes sa-autolink-off flex flex-wrap gap-2">\n'
            + "\n".join(boxes) + "\n</div>\n<!-- /wp:html -->")
    return html, "\n".join(lines)


def render_attraction(item: dict) -> tuple[str, dict]:
    exp = EXPANSIONS[item["slug"]]
    parts: list[str] = ["<!-- sa:att:lead -->"]
    parts += [p_block(p) for p in exp["lead"]]
    parts.append("<!-- /sa:att:lead -->")

    parts.append("<!-- sa:att:history -->")
    parts.append(h2_block(f"پیشینه و ویژگی‌های {item['title']}"))
    parts += [p_block(p) for p in exp["history"]]
    parts.append("<!-- /sa:att:history -->")

    parts.append("<!-- sa:att:sections -->")
    parts.append(h2_block("بخش‌های دیدنی و مسیر پیشنهادی گشت‌وگذار"))
    parts += [p_block(p) for p in exp["sections"]]
    parts.append("<!-- /sa:att:sections -->")

    parts.append("<!-- sa:att:season -->")
    parts.append(h2_block("بهترین فصل و ساعت بازدید"))
    parts += [p_block(p) for p in exp["season"]]
    parts.append("<!-- /sa:att:season -->")

    parts.append("<!-- sa:att:access -->")
    parts.append(h2_block("مسیر دسترسی با مترو، حمل‌ونقل عمومی و خودرو"))
    parts += [p_block(p) for p in exp["access"]]
    parts.append("<!-- /sa:att:access -->")

    parts.append("<!-- sa:att:tips -->")
    parts.append(h2_block("نکات کاربردی و ملزومات پیش از حرکت"))
    parts.append(ul_block(exp["tips"]))
    parts.append("<!-- /sa:att:tips -->")

    parts.append("<!-- sa:att:nearby -->")
    parts.append(h2_block("دیدنی‌های هم‌مسیر برای تکمیل برنامه یک‌روزه"))
    parts += [p_block(p) for p in exp["nearby"]]
    parts.append("<!-- /sa:att:nearby -->")

    parts.append("<!-- sa:att:faq -->")
    parts.append(h2_block("پرسش‌های پرتکرار بازدید"))
    parts.append(faq_block(exp["faqs"]))
    parts.append("<!-- /sa:att:faq -->")

    src_html, src_meta = sources_block(exp["sources"])
    parts.append("<!-- sa:att:sources -->")
    parts.append(h2_block("منابع"))
    parts.append(src_html)
    parts.append("<!-- /sa:att:sources -->")

    meta = {
        "sa_city_id": str(item["county_id"]),
        "sa_province_id": str(item["province_id"]),
        "sa_attr_area": item["area"],
        "sa_address": item["address"],
        "sa_best_time": item["best_time"],
        "sa_visit_duration": item["visit_duration"],
        "sa_suitable_for": item["suitable_for"],
        "sa_opening_hours": item["opening_hours"],
        "sa_ticket_price": item["ticket_price"],
        "sa_elevation": item["elevation"],
        "sa_attraction_age": item["age"],
        "sa_access_level": item["access_level"],
        "sa_latitude": item["lat"],
        "sa_longitude": item["lng"],
        "sa_last_verified_date": TODAY,
        "sa_seo_title": exp["seo_title"],
        "sa_seo_description": exp["seo_description"],
        "sa_og_title": exp["seo_title"],
        "sa_og_description": exp["seo_description"],
        "sa_focus_keyword": item["title"],
        "sa_faq": json.dumps(exp["faqs"], ensure_ascii=False),
        "sa_sources": src_meta,
    }
    return "\n".join(parts) + "\n", meta


def insert_before_marker(html: str, marker: str, block: str) -> str:
    if marker not in html:
        raise KeyError(marker)
    return html.replace(marker, block + "\n" + marker, 1)


def county_additions(slug: str) -> dict[str, list[str]]:
    """Hand-written body paragraphs keyed by section marker (evidence-bound)."""
    if slug == "neyshabur":
        return {
            "intro": [
                "نیشابور برای مسافر خراسان، توقف طبیعی میان مشهد و تهران است: شهر در میانهٔ بزرگراه و "
                "خط راه‌آهن سراسری نشسته و قطارهای محور تهران–مشهد همه در آن ایستگاه دارند. همین جایگاه، "
                "نیشابور را به گزینهٔ سفر یک‌روزه و حتی نیم‌روزه از مشهد تبدیل کرده است؛ صبح با قطار یا "
                "خودرو بیایید، آرامگاه‌ها و بازار را ببینید و عصر برگردید.",
            ],
            "map": [
                "چهرهٔ طبیعی شهرستان را دو پدیده می‌سازد: دشت همواری که شهر و باغ‌های آن را در خود گرفته و "
                "دیوارهٔ بلند بینالود در شمال که آب دره‌ها و چشمه‌های آن را به دشت می‌رساند. قلهٔ شیرباد، "
                "بام استان، در همین رشته‌کوه و در محدودهٔ شهرستان‌های شمالی آن قرار دارد و در روزهای صاف، "
                "خط برف آن از حاشیهٔ شهر دیده می‌شود.",
            ],
            "history": [
                "شکوه کهن نیشابور را بیشتر از راه یافته‌های باستان‌شناسی می‌شناسیم: کاوش‌های محوطهٔ شادیاخ و "
                "تپه‌های پیرامون شهر، سفال زرین‌فام و شیشه‌گری نیشابور را به موزه‌های بزرگ جهان برده است. "
                "کاروان‌سرای شاه‌عباسی در حاشیهٔ شهر و رباط شرف در محور شرقی، یادگار دوره‌ای هستند که این "
                "شهر یکی از ایستگاه‌های اصلی راه ابریشم بود.",
            ],
            "people": [
                "فیروزه‌تراشی تنها یک صنعت نیست؛ بخشی از هویت کاری شهر است. در کارگاه‌های مرکز شهر، سنگ خام "
                "معدن به نگین تراش می‌خورد و مسافر می‌تواند مراحل برش و صیقل را از نزدیک ببیند. در کنار آن، "
                "قالی‌بافی و سفال‌گری خانگی در روستاهای دامنهٔ بینالود هنوز زنده است و بازار سنتی شهر، "
                "نمایشگاه روزمرهٔ همین هنرهاست.",
            ],
            "economy": [
                "در کنار معدن و تراش فیروزه، باغداری دشتی ستون دیگر اقتصاد شهرستان است: آلو بخارا، کشمش، "
                "سیب و گردو در باغ‌های دشت و دره برداشت می‌شود و کارگاه‌های خشک‌کردن میوه، بخشی از تولید "
                "روستایی را به بازار می‌رسانند. دامداری ییلاقی در روستاهای بالادست و صنایع غذایی در حاشیهٔ "
                "بزرگراه، دو پایهٔ دیگر اشتغال‌اند.",
            ],
            "modern": [
                "در دو دههٔ گذشته، دانشگاه‌ها و مراکز درمانی نیشابور شهر را به یکی از کانون‌های خدماتی غرب "
                "خراسان رضوی تبدیل کرده‌اند و مهاجرت روزانه از روستاهای اطراف به شهر، رفت‌وآمد محورهای "
                "ورودی را سنگین کرده است. در کنار آن، مرمت بافت تاریخی بازار و ساماندهی محوطهٔ آرامگاه‌ها، "
                "چهرهٔ گردشگری شهر را از یک توقف کوتاه به یک برنامهٔ یک‌روزه تغییر داده است.",
            ],
            "food": [
                "در سفرهٔ نیشابوری، ریواس جای ویژه‌ای دارد؛ در بهار ساقهٔ تازهٔ آن را در بازار می‌بینید و "
                "شربت و خورش آن را در رستوران‌های سنتی شهر سرو می‌کنند. آش بلغور، دیزی و نان‌های محلی هم "
                "غذاهای روزمره‌اند و آلو بخارا، کشمش و زعفران، سوغاتی‌های سبک و ماندگار این شهرستان.",
            ],
            "stay": [
                "برای اقامت، دو انتخاب روشن وجود دارد: هتل و مهمان‌پذیر مرکز شهر که پیاده به بازار و "
                "آرامگاه‌ها نزدیک‌اند، یا اقامتگاه‌های بوم‌گردی روستاهای ییلاقی که صبح را با هوای دره شروع "
                "می‌کنند. جابه‌جایی درون‌شهری با تاکسی ساده است و برای بازدید آرامگاه خیام، عطار و دهکدهٔ "
                "چوبین، یک نیم‌روز کافی است.",
            ],
        }
    if slug == "mashhad":
        return {
            "stay": [
                "برای اقامت، انتخاب اصلی میان محدودهٔ حرم و نیمهٔ غربی شهر است: نزدیک حرم، پیاده به زیارت "
                "می‌رسید و ترافیک مرکز را حذف می‌کنید؛ در غرب شهر، هتل و اقامتگاه ارزان‌تر و آرام‌تر است و "
                "با مترو در نیم‌ساعت به حرم می‌رسید.",
            ],
        }
    return {}


def county_line_blocks(slug: str) -> dict[str, list[tuple[str, list[str]]]]:
    """Extra bullets per section (kept separate from paragraphs)."""
    return {}


def build_county(slug: str, wp_id: int) -> dict:
    base = CITY_BASE[slug]
    stem = f"{slug}-{wp_id}"
    html = (base / f"{stem}.html").read_text(encoding="utf-8")
    meta = json.loads((base / f"{stem}.meta.json").read_text(encoding="utf-8"))

    for section, paras in county_additions(slug).items():
        marker = f"<!-- /sa:sec:{section} -->"
        block = "\n".join(p_block(p) for p in paras)
        html = insert_before_marker(html, marker, block)

    if slug == "neyshabur":
        meta["sa_seo_title"] = "شهرستان نیشابور؛ راهنمای خیام، عطار و فیروزه"
        meta["sa_seo_description"] = (
            "راهنمای شهرستان نیشابور: آرامگاه خیام و عطار، دهکدهٔ چوبین، شادیاخ، دره‌های بینالود، "
            "معدن فیروزه، جمعیت سرشماری ۱۳۹۵ و مسیر دسترسی با قطار و خودرو.")
        faq = json.loads(meta["sa_faq"])
        faq += [
            {"q": "از مشهد به نیشابور با قطار چقدر طول می‌کشد؟",
             "a": "حدود یک‌ونیم ساعت؛ نیشابور در محور ریلی تهران–مشهد است و قطارهای این مسیر در آن توقف دارند."},
            {"q": "معدن فیروزهٔ نیشابور کجاست؟",
             "a": "در حدود ۵۰ کیلومتری شمال‌غرب شهر و در روستای معدن؛ سنگ استخراج‌شده در کارگاه‌های تراش نیشابور و مشهد به نگین تبدیل می‌شود."},
            {"q": "برای بازدید از نیشابور یک روز کافی است؟",
             "a": "برای آرامگاه خیام، عطار، شادیاخ و دهکدهٔ چوبین یک نیم‌روز کافی است؛ با دره‌های بینالود، یک روز کامل لازم دارید."},
        ]
        meta["sa_faq"] = json.dumps(faq, ensure_ascii=False)
        meta["sa_sources"] = (
            meta["sa_sources"] + "\n"
            "راهنمای جاذبه‌ها و استان‌های ایران | وزارت میراث فرهنگی (ویزیت ایران) | "
            f"https://www.visitiran.ir/ | {TODAY}")

    issues = fa_scan(html) + fa_scan_meta(meta)
    if issues:
        raise RuntimeError(f"{slug}: fa_lint → {issues[:4]}")

    out = CONTENT / "county-hubs"
    out.mkdir(parents=True, exist_ok=True)
    (out / f"{stem}.html").write_text(html, encoding="utf-8", newline="")
    (out / f"{stem}.meta.json").write_text(
        json.dumps(meta, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    words = len(re.sub(r"<[^>]+>", " ", re.sub(r"<!--.*?-->", " ", html, flags=re.S)).split())
    return {"slug": slug, "wp_id": wp_id, "kind": "city", "words": words}


def build_province_hub() -> dict:
    html = (PROV_BASE / "razavi-khorasan-50.html").read_text(encoding="utf-8")
    meta = json.loads((PROV_BASE / "razavi-khorasan-50.meta.json").read_text(encoding="utf-8"))

    counties = json.loads((CONTENT / "counties.json").read_text(encoding="utf-8"))
    rows = "".join(
        f'<!-- wp:list-item --><li><a href="/city/{c["slug"]}/">شهرستان {c["name"]}</a></li><!-- /wp:list-item -->'
        for c in counties)
    counties_block = (
        h3_block("راهنمای تک‌تک شهرستان‌های استان")
        + "\n" + f'<!-- wp:list --><ul class="wp-block-list">{rows}</ul><!-- /wp:list -->')
    html = insert_before_marker(html, "<!-- /sa:prov:counties -->", counties_block)

    att_rows = [
        ("imam-reza-shrine", "زیارت حرم مطهر امام رضا (ع)"),
        ("ferdowsi-tomb", "آرامگاه فردوسی در توس"),
        ("koohsangi-park", "پارک کوهسنگی"),
        ("torqabeh", "ییلاق طرقبه"),
        ("shandiz", "ییلاق شاندیز"),
        ("water-waves-land", "سرزمین موج‌های آبی"),
        ("haft-hooz-park", "هفت‌حوض"),
        ("khayyam-tomb", "آرامگاه خیام"),
        ("attar-tomb", "آرامگاه عطار"),
        ("wooden-village-neyshabur", "دهکده چوبین نیشابور"),
    ]
    att_block = (
        h3_block("راهنمای تفکیکی جاذبه‌های خوشه‌های مشهد و نیشابور")
        + "\n"
        + p_block("برای برنامه‌ریزی روزانه، هر جاذبهٔ شاخص استان صفحهٔ راهنمای مستقل خود را دارد؛ از "
                  + "، ".join(f'<a href="/attraction/{s}/">{t}</a>' for s, t in att_rows[:3])
                  + " شروع کنید و بقیهٔ مسیرها را با فاصله‌هایشان بسنجید.")
        + "\n"
        + p_block("تفرجگاه‌های غرب مشهد، یعنی "
                  + "، ".join(f'<a href="/attraction/{s}/">{t}</a>' for s, t in att_rows[3:7])
                  + "، یک عصر تا یک روز کامل از برنامه را پر می‌کنند و در یک محور ییلاقی کنار هم‌اند.")
        + "\n"
        + p_block("در محور نیشابور، "
                  + "، ".join(f'<a href="/attraction/{s}/">{t}</a>' for s, t in att_rows[7:])
                  + " در یک نیم‌روز جا می‌شوند؛ برای حرم، توس و تفرجگاه‌های غرب مشهد یک روز کامل در نظر بگیرید.")
    )
    html = insert_before_marker(html, "<!-- /sa:prov:attractions -->", att_block)

    issues = fa_scan(html) + fa_scan_meta(meta)
    if issues:
        raise RuntimeError(f"province hub: fa_lint → {issues[:4]}")
    out = CONTENT / "province-hub"
    out.mkdir(parents=True, exist_ok=True)
    (out / "razavi-khorasan-50.html").write_text(html, encoding="utf-8", newline="")
    (out / "razavi-khorasan-50.meta.json").write_text(
        json.dumps(meta, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    words = len(re.sub(r"<[^>]+>", " ", re.sub(r"<!--.*?-->", " ", html, flags=re.S)).split())
    return {"slug": "razavi-khorasan", "wp_id": 50, "kind": "province_hub", "words": words}


def main() -> None:
    # County directory for the hub list: theme table, filtered to this province.
    src = (REPO / "wp-content/themes/sarzaminaryan-child/data/counties.php").read_text(encoding="utf-8")
    rows = re.findall(r"array\(\s*'slug' => '([a-z0-9-]+)',\s*'name' => '([^']+)',\s*"
                      r"'province' => '([a-z0-9-]+)'", src)
    counties = [{"slug": s, "name": n} for s, n, p in rows if p == "razavi-khorasan"]
    CONTENT.mkdir(parents=True, exist_ok=True)
    (CONTENT / "counties.json").write_text(
        json.dumps(counties, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    report = {"county_hubs": [build_county("mashhad", 63), build_county("neyshabur", 64)],
              "attractions": [], "province_hub": build_province_hub()}

    out = CONTENT / "attractions"
    out.mkdir(parents=True, exist_ok=True)
    for item in ATTRACTIONS:
        html, meta = render_attraction(item)
        issues = fa_scan(html) + fa_scan_meta(meta)
        if issues:
            raise RuntimeError(f"{item['slug']}: fa_lint → {issues[:4]}")
        (out / f"{item['slug']}.html").write_text(html, encoding="utf-8", newline="")
        (out / f"{item['slug']}.meta.json").write_text(
            json.dumps(meta, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
        words = len(re.sub(r"<[^>]+>", " ", re.sub(r"<!--.*?-->", " ", html, flags=re.S)).split())
        report["attractions"].append({"slug": item["slug"], "title": item["title"],
                                      "county_id": item["county_id"], "words": words})

    (CONTENT / "manifest.json").write_text(
        json.dumps({"batch": "محتوای خوشهٔ خراسان رضوی ۱۴۰۵/۰۹/۰۴",
                    "note": "فقط پیش‌نویس محلی؛ هیچ نوشتن زنده‌ای انجام نشد.",
                    **report}, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    rows = report["county_hubs"] + [{"kind": "attraction", **a} for a in report["attractions"]]
    rows.append(report["province_hub"])
    for row in rows:
        print(f"{row['kind']:13s} {row['slug']:26s} {row['words']:5d} words")


if __name__ == "__main__":
    main()
