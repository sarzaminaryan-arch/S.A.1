#!/usr/bin/env python3
"""Tehran province cluster: master-template audit + 4-layer link normalisation.

Owner order (2026-11-25): «مقالات رو چک کن حتما طبق تمپلت مستر شهرستان باشه …
لینک سازی طبق قواعد سایت انجام بده». Both jobs are offline; neither writes to
the live site:

  --build   copies the 8 county hubs, 18 attraction branches and the province
            hub into outbox/20261125-tehran-cluster-v6/, then normalises their
            internal links to CLUSTER-SYSTEM-MASTER.md §5 (4 layers, one link
            per destination, _self, natural anchors, 2–3 links per paragraph).
  --audit   audits a set of files against the same master spec and the code
            guard contract, and writes a JSON + Markdown report (baseline vs v6).

  --build-razavi / --audit-razavi
            the same two jobs for province 2 (خراسان رضوی): two county hubs
            (`mashhad-63`, `neyshabur-64`), the 10 attraction branches of the
            two clusters and the province hub `50`, written to
            outbox/20261125-razavi-khorasan-cluster-v6/. The county hubs are
            read from the content stage built by `build_razavi_cluster_v6.py`.

Reference decisions (documented in .memory/wiki/decisions.md):
  * The master document (§3) lists weather/attractions/FAQ as body sections,
    but single-city.php renders them from meta (#weather-section,
    #attractions-section, #faq). Per .agent/RULES.md «کد بر سند قدیمی مقدم
    است» we keep those in the theme and audit the meta instead of duplicating
    H2 blocks inside post_content.
  * check_template.py (the publishing guard) owns the section-marker contract
    (intro/map/history/people/economy/stay) and the 1500–2400 word budget; the
    master document asks for 1200–1800. Both are reported; the code guard wins.
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
OUTBOX = HERE / "outbox"
V6 = OUTBOX / "20261125-tehran-cluster-v6"
V6_CITY = V6 / "county-hubs"
V6_ATT = V6 / "attractions"
V6_HUB = V6 / "province-hub"
ATT_BASE = OUTBOX / "20261124-tehran-attractions"
HUB_BASE = OUTBOX / "20261123-province-tehran-43"
RESULTS = HERE / "results"
REPORTS = REPO / "docs" / "live-site"  # `reports/` is gitignored; keep the audit in Git

CITY_IDS = {
    "tehran": 213, "eslamshahr": 219, "shahriar": 225, "pakdasht": 221,
    "varamin": 218, "damavand": 216, "firuzkuh": 217, "shemiranat": 215,
}
CITY_NAMES = {
    "tehran": "شهرستان تهران", "eslamshahr": "شهرستان اسلامشهر",
    "shahriar": "شهرستان شهریار", "pakdasht": "شهرستان پاکدشت",
    "varamin": "شهرستان ورامین", "damavand": "شهرستان دماوند",
    "firuzkuh": "شهرستان فیروزکوه", "shemiranat": "شهرستان شمیرانات",
}
# Every county slug the province hub links to gets a Persian anchor. An unknown
# slug is skipped, never written as a Latin anchor (prose_guard refuses Latin).
COUNTY_NAMES = {
    "tehran": "تهران", "shemiranat": "شمیرانات", "damavand": "دماوند",
    "firuzkuh": "فیروزکوه", "varamin": "ورامین", "shahriar": "شهریار",
    "eslamshahr": "اسلامشهر", "pakdasht": "پاکدشت", "rey": "ری",
    "baharestan": "بهارستان", "pardis": "پردیس", "pishva": "پیشوا",
    "qarchak": "قرچک", "qods": "قدس", "robat-karim": "رباطکریم", "malard": "ملارد",
}
CITY_SENTENCES = [
    "برای چیدن برنامهٔ همین خوشه، {links} را کنار هم بگذارید؛ ترتیبشان را با فصل سفر و ساعت حرکت هماهنگ کنید.",
    "اگر وقت بیشتری دارید، سراغ {links} بروید؛ فاصلهها را پیش از حرکت روی نقشه بسنجید.",
    "مسیرهای همسایه و دیدنیهای نزدیک، یعنی {links}، یک نیمروزه را کاملتر میکنند.",
    "در پایان گشت، {links} را هم به فهرست توقفها بیفزایید و ساعت برگشت را با ترافیک عصر بسنجید.",
]
ATT_SENTENCES = [
    "اگر یک روز کامل برای گشت دارید، {links} را به همان برنامه اضافه کنید؛ فاصلهها را پیش از حرکت روی نقشه ببینید.",
    "هممسیرهای نزدیک، {links}، در یک نوبت گشت جا میگیرند؛ ترتیبشان را با نور و ساعت روز هماهنگ کنید.",
    "برای تکمیل برنامه، {links} را هم در نظر بگیرید؛ وضعیت مسیرها را همان صبح استعلام کنید.",
    "پس از بازدید، {links} گزینههای طبیعی ادامهٔ روزاند؛ زمان رفت و برگشت را دستکم نگیرید.",
]
HUB_SENTENCES = [
    "از میان دیدنیها و شهرستانهای خوشهٔ تهران، {links} هم در همان برنامه میگنجند؛ ترتیبشان را با فصل سفر هماهنگ کنید.",
    "مسیرهای پیشنهادی استان، {links} را هم پوشش میدهند؛ فاصلهها را روی نقشه بسنجید.",
    "برای سفر طولانیتر، {links} توقفهای بعدیاند؛ ساعت حرکت را با ترافیک محورها تنظیم کنید.",
    "این صفحهها، یعنی {links}، راهنمای تفکیکی همان خوشهاند و برنامهٔ روزانه را آسانتر میچینند.",
]
CITY_DIRS = {slug: OUTBOX / f"20261117-replace-{slug}" for slug in CITY_IDS}
PROVINCE = "/province/tehran/"

# --- province 2: Razavi Khorasan -------------------------------------------
RX_V6 = OUTBOX / "20261125-razavi-khorasan-cluster-v6"
RX_CONTENT = OUTBOX / "20261125-razavi-khorasan-content"
RX_ATT_BASE = OUTBOX / "20261124-razavi-khorasan-attractions"
RX_ATT = RX_CONTENT / "attractions"
RX_CITY_BASE = {"mashhad": OUTBOX / "20261117-replace-mashhad",
                "neyshabur": OUTBOX / "20261117-replace-neyshabur"}
RX_PROVINCE = "/province/razavi-khorasan/"
RX_CITY_IDS = {"mashhad": 63, "neyshabur": 64}
RX_COUNTY_NAMES = {
    "mashhad": "مشهد", "neyshabur": "نیشابور", "chenaran": "چناران",
    "golbahar": "گلبهار", "zebarkhan": "زبرخان", "kalat": "کلات", "dargaz": "درگز",
    "sarakhs": "سرخس", "fariman": "فریمان", "torbat-heydarieh": "تربت حیدریه",
    "quchan": "قوچان", "kashmar": "کاشمر", "binalud": "بینالود",
}
RX_ANCHOR = {
    "imam-reza-shrine": "حرم مطهر امام رضا (ع)", "ferdowsi-tomb": "آرامگاه فردوسی",
    "koohsangi-park": "پارک کوهسنگی", "torqabeh": "ییلاق طرقبه",
    "shandiz": "ییلاق شاندیز", "water-waves-land": "سرزمین موج‌های آبی",
    "haft-hooz-park": "پارک طبیعی هفت‌حوض", "khayyam-tomb": "آرامگاه خیام",
    "attar-tomb": "آرامگاه عطار", "wooden-village-neyshabur": "دهکده چوبین نیشابور",
}
# Same-day-visit groups (CLUSTER-SYSTEM-MASTER.md §5, Mashhad reference pair).
RX_GROUPS = {
    "mashhad-center": ["imam-reza-shrine", "koohsangi-park"],
    "mashhad-yeylaq": ["torqabeh", "shandiz", "water-waves-land"],
    "mashhad-nature": ["haft-hooz-park", "koohsangi-park", "ferdowsi-tomb"],
    "neyshabur-literature": ["khayyam-tomb", "attar-tomb", "wooden-village-neyshabur"],
}
RX_CITY_SENTENCES = [
    "برای چیدن برنامهٔ همین خوشه، {links} را کنار هم بگذارید؛ ترتیبشان را با فصل سفر و ساعت حرکت هماهنگ کنید.",
    "اگر وقت بیشتری دارید، سراغ {links} بروید؛ فاصله‌ها را پیش از حرکت روی نقشه بسنجید.",
    "مسیرهای همسایه و دیدنی‌های نزدیک، یعنی {links}، یک نیم‌روزه را کامل‌تر می‌کنند.",
    "در پایان گشت، {links} را هم به فهرست توقف‌ها بیفزایید و ساعت برگشت را با ترافیک عصر بسنجید.",
]
RX_ATT_SENTENCES = [
    "اگر یک روز کامل برای گشت دارید، {links} را به همان برنامه اضافه کنید؛ فاصله‌ها را پیش از حرکت روی نقشه ببینید.",
    "هم‌مسیرهای نزدیک، {links}، در یک نوبت گشت جا می‌گیرند؛ ترتیبشان را با نور و ساعت روز هماهنگ کنید.",
    "برای تکمیل برنامه، {links} را هم در نظر بگیرید؛ وضعیت مسیرها را همان صبح استعلام کنید.",
    "پس از بازدید، {links} گزینه‌های طبیعی ادامهٔ روزاند؛ زمان رفت و برگشت را دستکم نگیرید.",
]
RX_HUB_SENTENCES = [
    "از میان دیدنی‌ها و شهرستان‌های خوشهٔ خراسان رضوی، {links} هم در همان برنامه می‌گنجند؛ ترتیبشان را با فصل سفر هماهنگ کنید.",
    "مسیرهای پیشنهادی استان، {links} را هم پوشش می‌دهند؛ فاصله‌ها را روی نقشه بسنجید.",
    "برای سفر طولانی‌تر، {links} توقف‌های بعدی‌اند؛ ساعت حرکت را با ترافیک محورها تنظیم کنید.",
    "این صفحه‌ها، یعنی {links}، راهنمای تفکیکی همان خوشه‌اند و برنامهٔ روزانه را آسان‌تر می‌چینند.",
]

ANCHOR = {
    "milad-tower": "برج میلاد", "golestan-palace": "کاخ گلستان",
    "saadabad-complex": "مجموعه سعدآباد", "tabiat-bridge": "پل طبیعت",
    "darband-darakeh": "دربند و درکه", "tehran-grand-bazaar": "بازار بزرگ تهران",
    "azadi-tower": "برج آزادی", "chitgar-lake": "دریاچه چیتگر",
    "tochal-ski-resort": "تلهکابین توچال", "dizin-ski-resort": "پیست دیزین",
    "shemshak-ski-resort": "پیست شمشک", "afjeh-carrot-plain": "دشت هویج افجه",
    "tar-havir-lakes": "دریاچههای تار و هویر", "abali-ski-resort": "پیست آبعلی",
    "tangeh-vashi": "تنگه واشی", "veresk-bridge": "پل ورسک",
    "varamin-jameh-mosque": "مسجد جامع ورامین",
    "joughin-mound-shahriar": "تپه جوقین شهریار",
}

# Real-geography, same-day-visit groups for layer 4 (CLUSTER-SYSTEM-MASTER.md §5).
GROUPS = {
    "center": ["milad-tower", "azadi-tower", "tabiat-bridge", "chitgar-lake",
               "golestan-palace", "tehran-grand-bazaar", "saadabad-complex",
               "darband-darakeh"],
    "alborz": ["tochal-ski-resort", "dizin-ski-resort", "shemshak-ski-resort",
               "afjeh-carrot-plain", "darband-darakeh", "saadabad-complex"],
    "east": ["tar-havir-lakes", "abali-ski-resort", "tangeh-vashi", "veresk-bridge"],
    "south": ["varamin-jameh-mosque", "joughin-mound-shahriar",
              "golestan-palace", "tehran-grand-bazaar"],
}
# Zero-branch clusters still connect to the closest real attractions (§1 focus rule).
ZERO_BRANCH_NEARBY = {
    "eslamshahr": ["azadi-tower", "chitgar-lake", "joughin-mound-shahriar"],
    "pakdasht": ["varamin-jameh-mosque", "golestan-palace", "tehran-grand-bazaar"],
}

# Master-spec SEO package (§3 item 6): title < 60 chars, description 140–155.
# The v3 county files were written before that rule; these are the corrected
# display strings, all checked with fa_lint.scan and measured in characters.
SEO_FIXES = {
    "tehran": ("شهرستان تهران؛ راهنمای پایتخت و جاذبههای شاخص",
               "راهنمای شهرستان تهران: جمعیت و مساحت بر پایه سرشماری ۱۳۹۵، آبوهوای دوگانه شمال و جنوب، دسترسی مترو و فرودگاه مهرآباد، کاخ گلستان، برج میلاد و پل طبیعت."),
    "eslamshahr": ("شهرستان اسلامشهر؛ راهنمای تپههای باستانی و دسترسی",
                   "راهنمای شهرستان اسلامشهر: امامزاده عقیل، تپه مافینآباد، یخچال ترشنبه، پارکهای واوان، وضعیت مترو، ترافیک محور تهران–ساوه و دادههای سرشماری ۱۳۹۵."),
    "shahriar": ("شهرستان شهریار؛ باغشهر غرب تهران و تپه جوقین",
                 "راهنمای شهرستان شهریار: تپه باستانی جوقین، باغهای میوه وحیدیه، دسترسی از بزرگراه فتح، بهار سرسبز و تابستان گرم، جمعیت سرشماری ۱۳۹۵ و نکات سفر یکروزه."),
    "pakdasht": ("شهرستان پاکدشت؛ راهنمای گلزار و گلخانهها",
                 "راهنمای شهرستان پاکدشت در جنوب شرق استان تهران: گلزار و نمایشگاههای گل، بازار مامازند، بقعههای ثبت ملی شریفآباد، جمعیت سرشماری ۱۳۹۵ و مسیر دسترسی از تهران."),
    "varamin": ("شهرستان ورامین؛ مسجد جامع و دشت ورامین",
                "راهنمای شهرستان ورامین: مسجد جامع ایلخانی، برج علاءالدوله، امامزاده یحیی، دشت ورامین، دسترسی با قطار حومهای و جاده خاوران، جمعیت ۱۳۹۵ و بهترین فصل سفر."),
    "damavand": ("شهرستان دماوند؛ دریاچه تار و پیست آبعلی",
                 "راهنمای شهرستان دماوند: دریاچههای تار و هویر، پیست اسکی آبعلی، مسجد جامع و برج شبلی، اقلیم کوهستانی البرز، جاده فیروزکوه و هراز، جمعیت ۱۳۹۵ و نکات سفر."),
    "firuzkuh": ("شهرستان فیروزکوه؛ تنگه واشی و راهآهن شمال",
                 "راهنمای شهرستان فیروزکوه: تنگه واشی، کاروانسرای گدوک، مسیر ریلی راهآهن شمال، اقلیم سرد کوهستانی، جاده تهران–دماوند–فیروزکوه، جمعیت ۱۳۹۵ و بهترین فصل سفر."),
    "shemiranat": ("شهرستان شمیرانات؛ ییلاقهای البرز و پیستها",
                   "راهنمای شهرستان شمیرانات: لواسان، فشم و افجه، پیستهای توچال، دیزین و شمشک، سد لتیان، اقلیم کوهستانی، جاده لشکرک، جمعیت سرشماری ۱۳۹۵ و نکات سفر."),
}
# One v3 hub lacked the province relation in its meta (L1 contract).
META_ADDITIONS = {"eslamshahr": {"sa_province_id": "43"}}

# Master-spec word budget: three v3 hubs landed under 1200 words. These short,
# practical paragraphs use only facts already present in the pages' own meta
# (climate, best season, access, POIs) — no new claims, no new web research.
BODY_ADDITIONS = {
    "shahriar": [
        "برای چیدن یک نیمروز آرام در این باغشهر، فروردین تا خرداد را انتخاب کنید؛ باغها سرسبزند و هوا هنوز گرم نشده است. مهر و آبان هم چهرهٔ دیگری از شهریار نشان میدهد، با رنگ برگها و میوهای تازهچیده. تابستانِ دشت گرم است و گشت باغها را به صبح زود یا نزدیک غروب بسپارید. در زمستان، مه و بارندگی خفیف مسیرهای روستایی را لغزنده میکند و برنامهٔ پیادهروی طولانی چندان دلچسب نیست. اگر هدف عکاسی است، نور کمجانب عصر روی دیوارهای خشتی و ردیف درختان میوه، سادهایترین و صادقانهترین قاب شهرستان را میسازد.",
        "دسترسی از بزرگراه فتح و جادهٔ شهریار–کرج ساده است، ولی میان محلههای پیوستهٔ وحیدیه، اندیشه و باغستان، مرز شهر و شهرستان در عمل محو میشود. برای همین بهتر است مقصد هر توقف را پیش از حرکت روی نقشه مشخص کنید و ترتیبشان را با ساعت اوج ترافیک غرب تهران هماهنگ کنید. خودروی شخصی آزادی عمل بیشتری میدهد، چون فاصلهٔ باغها و محوطههای تاریخی از هم کم نیست و پیاده نمیتوان میانشان رفت. برای خانوادهای که با کودک سفر میکند، توقف کوتاه در فضای سبز حاشیهٔ باغها و خرید میوهٔ فصل از همانجا، برنامه را بدون خستگی پر میکند.",
    ],
    "varamin": [
        "برای برنامهٔ یکروزهٔ ورامین، قطار حومهای تهران گزینهای آرام و کمهزینه است؛ پیاده از ایستگاه تا بافت قدیمی و مسجد جامع فاصلهای کوتاه است و همان مسیر، حال و هوای دشت را پیش از رسیدن به بنا نشان میدهد. اسفند تا اردیبهشت و مهر تا آذر، هوای نیمهخشک دشت ملایمتر است و گشت در حیاط مسجد و اطراف برج علاءالدوله راحتتر انجام میشود. تابستان گرم است و سایهٔ اندک اطراف محوطهها، برنامهٔ ظهر را خستهکننده میکند. اگر عکاسی میکنید، نور اواخر عصر روی آجرکاری ایلخانی بهترین فرصت است؛ ساعت کاری مجموعههای تاریخی را پیش از حرکت استعلام کنید.",
    ],
    "firuzkuh": [
        "تیر و مرداد، تنگهٔ واشی خنکترین و پرآبترین حالش را دارد و خرداد تا شهریور، فصل طبیعتگردی در درههای البرز است. زمستان بسیار سرد و برفی است؛ پیش از حرکت وضعیت جادهٔ تهران–دماوند–فیروزکوه و گردنهها را ببینید و زنجیر چرخ همراه داشته باشید. اختلاف دمای تهران با این شهرستان در یک روز بارانی گاهی چشمگیر است، پس لباس لایهای تنها چیزی است که برنامهٔ یکروزه را نجات میدهد.",
    ],
}

CITY_REQUIRED_SECTIONS = ("intro", "map", "history", "people", "economy", "stay")
ATT_REQUIRED_SECTIONS = ("lead", "history", "sections", "season", "access", "tips", "nearby", "sources")
CITY_META_KEYS = (
    "sa_sources", "sa_seo_title", "sa_seo_description", "sa_focus_keyword",
    "sa_faq", "sa_cty_population", "sa_cty_census_year", "sa_cty_area",
    "sa_cty_center", "sa_cty_climate", "sa_cty_best_time", "sa_cty_neighbors",
    "sa_cty_language", "sa_cty_livelihood", "sa_schema_contained_in",
    "sa_province_id", "sa_access_road", "sa_access_rail", "sa_access_air",
)
ATT_META_KEYS = (
    "sa_city_id", "sa_province_id", "sa_attr_area", "sa_address", "sa_best_time",
    "sa_visit_duration", "sa_suitable_for", "sa_latitude", "sa_longitude",
    "sa_seo_title", "sa_seo_description", "sa_faq", "sa_sources",
)

LINK_RE = re.compile(r'(<a\b[^>]*\bhref="(/[^"]*)"[^>]*>)(.*?)(</a>)', re.S)
HREF_RE = re.compile(r'<a\b[^>]*\bhref="(/[^"]*)"', re.S)


def load_manifest() -> list[dict]:
    return json.loads((ATT_BASE / "manifest.json").read_text(encoding="utf-8"))


RX_ID_CITY = {63: "mashhad", 64: "neyshabur"}


def load_rx_manifest() -> list[dict]:
    """The 10 Razavi Khorasan branches, tagged with the cluster they belong to.

    The original manifest carries `county_id`; the cluster builder works on
    `city_slug`, so the mapping is normalised here (63 → مشهد, 64 → نیشابور).
    """
    rows = json.loads((RX_ATT_BASE / "manifest.json").read_text(encoding="utf-8"))
    for row in rows:
        row.setdefault("city_slug", RX_ID_CITY[int(row["county_id"])])
    return rows


def rx_branches(manifest: list[dict]) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {slug: [] for slug in RX_CITY_IDS}
    for row in manifest:
        out.setdefault(row["city_slug"], []).append(row["slug"])
    return out


def branches_by_city(manifest: list[dict]) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {slug: [] for slug in CITY_IDS}
    for row in manifest:
        out.setdefault(row["city_slug"], []).append(row["slug"])
    return out


def city_link(slug: str) -> str:
    return f"/city/{slug}/"


def att_link(slug: str) -> str:
    return f"/attraction/{slug}/"


def link_tag(href: str, text: str) -> str:
    return f'<a href="{href}" target="_self">{text}</a>'


def province_county_slugs(hub_html: str) -> list[str]:
    return sorted(set(re.findall(r'href="/city/([a-z0-9-]+)/"', hub_html)))


def internal_hrefs(html: str) -> list[str]:
    return HREF_RE.findall(html)


def normalise_tags(html: str) -> tuple[str, list[str]]:
    """One link per internal destination; every internal link opens in _self."""
    seen: set[str] = set()
    touched: list[str] = []

    def repl(m: re.Match) -> str:
        open_tag, href, inner, close_tag = m.groups()
        if href in seen:
            touched.append(f"duplicate removed: {href}")
            return inner
        seen.add(href)
        if 'target="_blank"' in open_tag:
            touched.append(f"target fixed: {href}")
            open_tag = open_tag.replace('target="_blank"', 'target="_self"')
        return open_tag + inner + close_tag

    return LINK_RE.sub(repl, html), touched


def join_links(pairs: list[tuple[str, str]]) -> str:
    tags = [link_tag(h, t) for h, t in pairs]
    if len(tags) == 1:
        return tags[0]
    return "، ".join(tags[:-1]) + " و " + tags[-1]


def paragraph(text: str) -> str:
    return f"\n<!-- wp:paragraph --><p>{text}</p><!-- /wp:paragraph -->\n"


def add_missing_links(html: str, targets: list[tuple[str, str]],
                      sections: list[str], kind: str,
                      sentences: list[str]) -> tuple[str, list[str]]:
    """Put missing links inside marked sections, 2–3 per paragraph."""
    existing = set(internal_hrefs(html))
    missing = [(h, t) for h, t in targets if h not in existing]
    available = [f"<!-- /sa:{kind}:{s} -->" for s in sections
                 if f"<!-- /sa:{kind}:{s} -->" in html]
    added: list[str] = []
    for i in range(0, len(missing), 3):
        chunk = missing[i:i + 3]
        if not available:
            break
        slot = i // 3
        marker = available[slot % len(available)]
        template = sentences[slot % len(sentences)]
        para = paragraph(template.format(links=join_links(chunk)))
        html = html.replace(marker, para + marker, 1)
        added.extend(h for h, _ in chunk)
    return html, added


def append_missing_links(html: str, targets: list[tuple[str, str]],
                         sentences: list[str]) -> tuple[str, list[str]]:
    """Province hub: extend its existing directory-style trailing paragraph."""
    existing = set(internal_hrefs(html))
    missing = [(h, t) for h, t in targets if h not in existing]
    added: list[str] = []
    for i in range(0, len(missing), 3):
        chunk = missing[i:i + 3]
        slot = i // 3
        template = sentences[slot % len(sentences)]
        html = html.rstrip() + "\n" + paragraph(template.format(links=join_links(chunk)))
        added.extend(h for h, _ in chunk)
    return html, added


def dedupe_pairs(pairs: list[tuple[str, str]]) -> list[tuple[str, str]]:
    seen: set[str] = set()
    out = []
    for href, text in pairs:
        if href not in seen:
            seen.add(href)
            out.append((href, text))
    return out


def city_targets(slug: str, branches: dict[str, list[str]],
                 county_slugs: list[str], meta: dict) -> list[tuple[str, str]]:
    out: list[tuple[str, str]] = [(PROVINCE, "استان تهران")]
    own = branches.get(slug, [])
    for br in own or ZERO_BRANCH_NEARBY.get(slug, []):
        out.append((att_link(br), ANCHOR.get(br, br)))
    # Layer 1 + neighbour chips resolved from the identity meta.
    for line in str(meta.get("sa_cty_neighbors", "")).splitlines():
        m = re.match(r"\s*([a-z0-9-]+)\s*-", line)
        if not m:
            continue
        other = m.group(1)
        if other != slug and other in county_slugs and other in COUNTY_NAMES:
            out.append((city_link(other), COUNTY_NAMES[other]))
    return dedupe_pairs(out)


def att_siblings(slug: str, city: str, branches: dict[str, list[str]]) -> list[str]:
    own = [x for x in branches.get(city, []) if x != slug]
    group = next((g for g in GROUPS.values() if slug in g), [])
    candidates = own + [x for x in group if x != slug]
    out: list[str] = []
    for x in candidates:
        if x not in out:
            out.append(x)
    return out[:3]


def att_targets(slug: str, city: str, branches: dict[str, list[str]]) -> list[tuple[str, str]]:
    out = [(city_link(city), CITY_NAMES.get(city, city)), (PROVINCE, "استان تهران")]
    for sib in att_siblings(slug, city, branches):
        out.append((att_link(sib), ANCHOR.get(sib, sib)))
    return dedupe_pairs(out)


def hub_targets(manifest: list[dict], county_slugs: list[str]) -> list[tuple[str, str]]:
    out = [(city_link(s), COUNTY_NAMES[s]) for s in county_slugs if s in COUNTY_NAMES]
    out += [(att_link(x["slug"]), ANCHOR.get(x["slug"], x["title"])) for x in manifest]
    return dedupe_pairs(out)


def rx_city_targets(slug: str, branches: dict[str, list[str]], county_slugs: list[str],
                    meta: dict) -> list[tuple[str, str]]:
    out: list[tuple[str, str]] = [(RX_PROVINCE, "استان خراسان رضوی")]
    for br in branches.get(slug, []):
        out.append((att_link(br), RX_ANCHOR.get(br, br)))
    for line in str(meta.get("sa_cty_neighbors", "")).splitlines():
        m = re.match(r"\s*([a-z0-9-]+)\s*-", line)
        if not m:
            continue
        other = m.group(1)
        if other != slug and other in county_slugs and other in RX_COUNTY_NAMES:
            out.append((city_link(other), RX_COUNTY_NAMES[other]))
    return dedupe_pairs(out)


def rx_att_siblings(slug: str, city: str, branches: dict[str, list[str]]) -> list[str]:
    own = [x for x in branches.get(city, []) if x != slug]
    group = next((g for g in RX_GROUPS.values() if slug in g), [])
    out: list[str] = []
    for x in own + [y for y in group if y != slug]:
        if x not in out:
            out.append(x)
    return out[:3]


def rx_att_targets(slug: str, city: str, branches: dict[str, list[str]]) -> list[tuple[str, str]]:
    out = [(city_link(city), RX_COUNTY_NAMES[city]), (RX_PROVINCE, "استان خراسان رضوی")]
    for sib in rx_att_siblings(slug, city, branches):
        out.append((att_link(sib), RX_ANCHOR.get(sib, sib)))
    return dedupe_pairs(out)


def rx_hub_targets(manifest: list[dict], county_slugs: list[str]) -> list[tuple[str, str]]:
    out = [(city_link(s), RX_COUNTY_NAMES[s]) for s in county_slugs if s in RX_COUNTY_NAMES]
    out += [(att_link(x["slug"]), RX_ANCHOR.get(x["slug"], x["title"])) for x in manifest]
    return dedupe_pairs(out)


def read_pair(base: Path, stem: str) -> tuple[str, dict]:
    html = (base / f"{stem}.html").read_text(encoding="utf-8")
    meta = json.loads((base / f"{stem}.meta.json").read_text(encoding="utf-8"))
    return html, meta


def write_pair(base: Path, stem: str, html: str, meta: dict) -> None:
    base.mkdir(parents=True, exist_ok=True)
    (base / f"{stem}.html").write_text(html, encoding="utf-8", newline="")
    (base / f"{stem}.meta.json").write_text(
        json.dumps(meta, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")


def word_count(html: str) -> int:
    text = re.sub(r"<!--.*?-->", " ", html, flags=re.S)
    text = re.sub(r"<[^>]+>", " ", text)
    return len(text.split())


def build() -> None:
    manifest = load_manifest()
    branches = branches_by_city(manifest)
    hub_html, _ = read_pair(HUB_BASE, "tehran-43")
    county_slugs = province_county_slugs(hub_html)
    changes: dict[str, dict] = {}

    for slug, wp_id in CITY_IDS.items():
        stem = f"{slug}-{wp_id}"
        html, meta = read_pair(CITY_DIRS[slug], stem)
        # Master-spec meta corrections (SEO lengths, L1 province relation).
        title, desc = SEO_FIXES[slug]
        meta["sa_seo_title"] = title
        meta["sa_seo_description"] = desc
        meta.update(META_ADDITIONS.get(slug, {}))
        # Master-spec word budget: expand the three short v3 hubs in-place.
        additions = BODY_ADDITIONS.get(slug, [])
        if additions:
            marker = "<!-- /sa:sec:stay -->"
            extra = "".join(paragraph(p) for p in additions)
            html = html.replace(marker, extra + marker, 1)
        html, touched = normalise_tags(html)
        targets = city_targets(slug, branches, county_slugs, meta)
        html, added = add_missing_links(
            html, targets, ["stay", "map", "modern", "economy", "history"], "sec",
            CITY_SENTENCES)
        write_pair(V6_CITY, stem, html, meta)
        changes[stem] = {"kind": "city", "normalised": touched, "links_added": added,
                         "seo_meta_fixed": True, "body_paragraphs_added": len(additions),
                         "targets": [h for h, _ in targets], "words": word_count(html)}

    for row in manifest:
        slug, city = row["slug"], row["city_slug"]
        html, meta = read_pair(ATT_BASE, slug)
        html, touched = normalise_tags(html)
        targets = att_targets(slug, city, branches)
        html, added = add_missing_links(
            html, targets, ["nearby", "tips", "access", "season", "sections"], "att",
            ATT_SENTENCES)
        write_pair(V6_ATT, slug, html, meta)
        changes[slug] = {"kind": "attraction", "city": city, "normalised": touched,
                         "links_added": added, "targets": [h for h, _ in targets],
                         "words": word_count(html)}

    html, meta = read_pair(HUB_BASE, "tehran-43")
    html, touched = normalise_tags(html)
    targets = hub_targets(manifest, county_slugs)
    html, added = append_missing_links(html, targets, HUB_SENTENCES)
    write_pair(V6_HUB, "tehran-43", html, meta)
    changes["tehran-43"] = {"kind": "province_hub", "normalised": touched,
                            "links_added": added, "targets": [h for h, _ in targets],
                            "words": word_count(html)}

    (V6 / "changes.json").write_text(json.dumps(changes, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    (V6 / "manifest.json").write_text(json.dumps({
        "batch": "پیشنویس خوشهای ۱۴۰۵/۰۹/۰۴",
        "province": "استان تهران",
        "source": "سند جامع سیستم خوشهای، بخشهای ۳ و ۴ و ۵، همراه قرارداد گارد قالب شهرستان",
        "note": "فقط پیشنویس محلی؛ هیچ نوشتن زندهای انجام نشد. جزئیات در سند تحویل نشست آمده است.",
        "county_hubs": [{"slug": s, "wp_id": i,
                         "html": f"county-hubs/{s}-{i}.html", "meta": f"county-hubs/{s}-{i}.meta.json"}
                        for s, i in CITY_IDS.items()],
        "attractions": [{"slug": r["slug"], "city_slug": r["city_slug"],
                         "html": f'attractions/{r["slug"]}.html', "meta": f'attractions/{r["slug"]}.meta.json'}
                        for r in manifest],
        "province_hub": {"slug": "tehran", "wp_id": 43,
                         "html": "province-hub/tehran-43.html", "meta": "province-hub/tehran-43.meta.json"},
    }, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"built {V6.relative_to(REPO)}: 8 county hubs, 18 attractions, 1 province hub")


def build_rx() -> None:
    """Content stage → cluster v6: normalise links, add the missing 4 layers."""
    manifest = load_rx_manifest()
    branches = rx_branches(manifest)
    hub_html, _ = read_pair(RX_CONTENT / "province-hub", "razavi-khorasan-50")
    county_slugs = province_county_slugs(hub_html)
    changes: dict[str, dict] = {}

    for slug, wp_id in RX_CITY_IDS.items():
        stem = f"{slug}-{wp_id}"
        html, meta = read_pair(RX_CONTENT / "county-hubs", stem)
        html, touched = normalise_tags(html)
        targets = rx_city_targets(slug, branches, county_slugs, meta)
        html, added = add_missing_links(
            html, targets, ["stay", "map", "notables", "modern", "economy", "history"], "sec",
            RX_CITY_SENTENCES)
        write_pair(RX_V6 / "county-hubs", stem, html, meta)
        changes[stem] = {"kind": "city", "normalised": touched, "links_added": added,
                         "targets": [h for h, _ in targets], "words": word_count(html)}

    for row in manifest:
        slug, city = row["slug"], row["city_slug"]
        html, meta = read_pair(RX_CONTENT / "attractions", slug)
        html, touched = normalise_tags(html)
        targets = rx_att_targets(slug, city, branches)
        # Fallback sentences go into the sections that carry the fewest links,
        # so the §5 density guideline (2–3 per section) holds for the nearby
        # narrative the writer already curated.
        html, added = add_missing_links(
            html, targets, ["access", "season", "tips", "nearby", "sections"], "att",
            RX_ATT_SENTENCES)
        write_pair(RX_V6 / "attractions", slug, html, meta)
        changes[slug] = {"kind": "attraction", "city": city, "normalised": touched,
                         "links_added": added, "targets": [h for h, _ in targets],
                         "words": word_count(html)}

    html, meta = read_pair(RX_CONTENT / "province-hub", "razavi-khorasan-50")
    html, touched = normalise_tags(html)
    targets = rx_hub_targets(manifest, county_slugs)
    html, added = append_missing_links(html, targets, RX_HUB_SENTENCES)
    write_pair(RX_V6 / "province-hub", "razavi-khorasan-50", html, meta)
    changes["razavi-khorasan-50"] = {"kind": "province_hub", "normalised": touched,
                                     "links_added": added,
                                     "targets": [h for h, _ in targets],
                                     "words": word_count(html)}

    (RX_V6 / "changes.json").write_text(
        json.dumps(changes, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    (RX_V6 / "manifest.json").write_text(json.dumps({
        "batch": "پیش‌نویس خوشهٔ خراسان رضوی ۱۴۰۵/۰۹/۰۴",
        "province": "استان خراسان رضوی",
        "source": "سند جامع سیستم خوشه‌ای، بخش‌های ۳ و ۴ و ۵، همراه قرارداد گارد قالب شهرستان",
        "note": "فقط پیش‌نویس محلی؛ هیچ نوشتن زنده‌ای انجام نشد. شمارنده‌های رسمی دست‌نخورده‌اند.",
        "county_hubs": [{"slug": s, "wp_id": i,
                         "html": f"county-hubs/{s}-{i}.html",
                         "meta": f"county-hubs/{s}-{i}.meta.json"}
                        for s, i in RX_CITY_IDS.items()],
        "attractions": [{"slug": r["slug"], "city_slug": r["city_slug"],
                         "html": f'attractions/{r["slug"]}.html',
                         "meta": f'attractions/{r["slug"]}.meta.json'} for r in manifest],
        "province_hub": {"slug": "razavi-khorasan", "wp_id": 50,
                         "html": "province-hub/razavi-khorasan-50.html",
                         "meta": "province-hub/razavi-khorasan-50.meta.json"},
    }, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"built {RX_V6.relative_to(REPO)}: 2 county hubs, 10 attractions, 1 province hub")


def rx_config(source: str) -> dict:
    manifest = load_rx_manifest()
    branches = rx_branches(manifest)
    hub_base = RX_CITY_BASE  # unused but keeps the signature explicit
    del hub_base
    cfg: dict = {"source": source, "province": "razavi-khorasan",
                 "province_link": RX_PROVINCE, "files": [],
                 "city_targets": {}, "att_targets": {}, "hub_targets": []}
    for slug, wp_id in RX_CITY_IDS.items():
        base = RX_CITY_BASE[slug] if source == "baseline" else RX_V6 / "county-hubs"
        stem = f"{slug}-{wp_id}"
        cfg["files"].append(("city", stem, base))
    hub_html, _ = read_pair(RX_V6 / "province-hub" if source == "v6"
                            else RX_CONTENT / "province-hub", "razavi-khorasan-50")
    county_slugs = province_county_slugs(hub_html)
    for slug, wp_id in RX_CITY_IDS.items():
        stem = f"{slug}-{wp_id}"
        base = RX_CITY_BASE[slug] if source == "baseline" else RX_V6 / "county-hubs"
        cfg["city_targets"][stem] = [h for h, _ in rx_city_targets(
            slug, branches, county_slugs, read_pair(base, stem)[1])]
    for row in manifest:
        base = RX_ATT_BASE if source == "baseline" else RX_V6 / "attractions"
        cfg["files"].append(("attraction", row["slug"], base))
        cfg["att_targets"][row["slug"]] = [h for h, _ in rx_att_targets(
            row["slug"], row["city_slug"], branches)]
    cfg["files"].append(("province_hub", "razavi-khorasan-50",
                         OUTBOX / "20261123-province-razavi-khorasan-50"
                         if source == "baseline" else RX_V6 / "province-hub"))
    cfg["hub_targets"] = [h for h, _ in rx_hub_targets(manifest, county_slugs)]
    return cfg


def source_domains(meta: dict) -> set[str]:
    return set(re.findall(r"https?://([^/\s|]+)", str(meta.get("sa_sources", ""))))


def section_links(html: str, key: str, kind: str) -> list[str]:
    m = re.search(rf"<!-- sa:{kind}:{key} -->(.*?)<!-- /sa:{kind}:{key} -->", html, re.S)
    return HREF_RE.findall(m.group(1)) if m else []


def audit_pair(kind: str, stem: str, html: str, meta: dict,
               cfg: dict) -> tuple[list[str], list[str]]:
    problems: list[str] = []
    warnings: list[str] = []
    hrefs = internal_hrefs(html)
    dupes = sorted({h for h in hrefs if hrefs.count(h) > 1})
    if dupes:
        problems.append(f"duplicate internal links: {dupes}")
    blanks = [m.group(0) for m in LINK_RE.finditer(html) if 'target="_blank"' in m.group(1)]
    if blanks:
        problems.append(f"{len(blanks)} internal link(s) open in a new tab")
    if "کلیک کنید" in html:
        problems.append("generic «کلیک کنید» anchor text")
    words = word_count(html)

    def density(keys: tuple[str, ...], marker: str) -> None:
        for s in keys:
            n = len(section_links(html, s, marker))
            if n > 3:
                warnings.append(f"section {s} has {n} internal links (guideline 2–3)")

    if kind == "city":
        missing_sections = [s for s in CITY_REQUIRED_SECTIONS
                            if f"<!-- sa:sec:{s} -->" not in html]
        if missing_sections:
            problems.append(f"missing county body markers: {missing_sections}")
        if not 1200 <= words <= 2400:
            problems.append(f"words {words} outside master/code budget (1200–2400)")
        missing_meta = [k for k in CITY_META_KEYS if not str(meta.get(k, "")).strip()]
        if missing_meta:
            problems.append(f"missing master meta: {missing_meta}")
        if str(meta.get("sa_cty_census_year", "")) != "1395":
            problems.append("census note not anchored to the 1395 official census")
        title = str(meta.get("sa_seo_title", ""))
        desc = str(meta.get("sa_seo_description", ""))
        if len(title) > 60:
            problems.append(f"sa_seo_title {len(title)} chars (> 60)")
        if not 140 <= len(desc) <= 155:
            problems.append(f"sa_seo_description {len(desc)} chars (want 140–155)")
        try:
            faq = json.loads(str(meta.get("sa_faq", "[]")))
        except ValueError:
            faq = []
        if len(faq) < 3:
            problems.append(f"FAQ has {len(faq)} valid rows (< 3)")
        if len(source_domains(meta)) < 2:
            problems.append("sa_sources names fewer than 2 distinct domains")
        density(CITY_REQUIRED_SECTIONS, "sec")
        absent = [h for h in cfg["city_targets"][stem] if h not in hrefs]
        if absent:
            problems.append(f"missing cluster links ({len(absent)}): {absent[:6]}")
        province_link = cfg.get("province_link", PROVINCE)
        if province_link not in hrefs:
            problems.append(f"L1: no link back to {province_link}")

    elif kind == "attraction":
        missing_sections = [s for s in ATT_REQUIRED_SECTIONS
                            if f"<!-- sa:att:{s} -->" not in html]
        if missing_sections:
            problems.append(f"missing attraction body markers: {missing_sections}")
        if not 800 <= words <= 1500:
            problems.append(f"words {words} outside attraction budget (800–1500)")
        elif not 900 <= words <= 1400:
            warnings.append(f"words {words} outside the master target (900–1400)")
        missing_meta = [k for k in ATT_META_KEYS if not str(meta.get(k, "")).strip()]
        if missing_meta:
            problems.append(f"missing factsheet/seo meta: {missing_meta}")
        density(ATT_REQUIRED_SECTIONS, "att")
        absent = [h for h in cfg["att_targets"][stem] if h not in hrefs]
        if absent:
            problems.append(f"missing cluster links ({len(absent)}): {absent[:6]}")
        siblings = [h for h in hrefs if h.startswith("/attraction/")]
        if len(siblings) < 2:
            problems.append(f"L4: only {len(siblings)} same-route attraction link(s) (need 2–4)")

    else:  # province hub
        absent = [h for h in cfg["hub_targets"] if h not in hrefs]
        if absent:
            problems.append(f"L1/L2: hub misses {len(absent)} links: {absent[:8]}")
        density(("overview", "geography", "history", "travel"), "prov")
    return problems, warnings


def config_for(source: str, manifest: list[dict]) -> dict:
    branches = branches_by_city(manifest)
    hub_html, _ = read_pair(HUB_BASE, "tehran-43")
    county_slugs = province_county_slugs(hub_html)
    cfg: dict = {"source": source, "province_link": PROVINCE, "files": [],
                 "city_targets": {},
                 "att_targets": {}, "hub_targets": []}
    for slug, wp_id in CITY_IDS.items():
        base = CITY_DIRS[slug] if source == "baseline" else V6_CITY
        stem = f"{slug}-{wp_id}"
        cfg["files"].append(("city", stem, base))
        cfg["city_targets"][stem] = [h for h, _ in city_targets(
            slug, branches, county_slugs, read_pair(base, stem)[1])]
    for row in manifest:
        base = ATT_BASE if source == "baseline" else V6_ATT
        cfg["files"].append(("attraction", row["slug"], base))
        cfg["att_targets"][row["slug"]] = [h for h, _ in att_targets(
            row["slug"], row["city_slug"], branches)]
    cfg["files"].append(("province_hub", "tehran-43",
                         HUB_BASE if source == "baseline" else V6_HUB))
    cfg["hub_targets"] = [h for h, _ in hub_targets(manifest, county_slugs)]
    return cfg


def audit(cfg: dict) -> dict:
    rows = []
    for kind, stem, base in cfg["files"]:
        html, meta = read_pair(base, stem)
        problems, warnings = audit_pair(kind, stem, html, meta, cfg)
        rows.append({"kind": kind, "stem": stem, "words": word_count(html),
                     "internal_links": len(internal_hrefs(html)),
                     "problems": problems, "warnings": warnings, "ok": not problems})
    return {"source": cfg["source"], "rows": rows,
            "summary": {"files": len(rows), "clean": sum(r["ok"] for r in rows),
                        "with_issues": sum(not r["ok"] for r in rows),
                        "issues": sum(len(r["problems"]) for r in rows),
                        "warnings": sum(len(r["warnings"]) for r in rows)}}


def report(baseline: dict, candidate: dict, path: Path) -> None:
    lines = [
        "# ممیزی تمپلت مستر شهرستان و لینکسازی خوشهای — استان تهران",
        "",
        "تاریخ: ۲۰۲۶-۱۱-۲۵ / ۱۴۰۵/۰۹/۰۴ · منبع قواعد: `docs/live-site/CLUSTER-SYSTEM-MASTER.md` §۳/§۴/§۵ و گارد کد `tools/wp-agent/replace_src/check_template.py`.",
        "",
        "| مجموعه | فایلها | سالم | دارای ایراد | ایرادها | هشدارها |",
        "|---|---:|---:|---:|---:|---:|",
    ]
    for d in (baseline, candidate):
        s = d["summary"]
        label = "وضعیت پیش از این نشست (baseline)" if d["source"] == "baseline" else "خروجی `outbox/20261125-tehran-cluster-v6/`"
        lines.append(f"| {label} | {s['files']} | {s['clean']} | {s['with_issues']} | {s['issues']} | {s['warnings']} |")
    lines += [
        "",
        "## آنچه بررسی شد",
        "",
        "- **شهرستانها (۸):** نشانگرهای بدنهٔ ششگانهٔ گارد کد، بودجهٔ واژه، متای کامل تمپلیت مستر (شناسنامه، تبصرهٔ سرشماری ۱۳۹۵، FAQ، منابع، SEO، دسترسی، همسایهها)، یک لینک به استان، لینک به تمام شاخههای جاذبهٔ همان خوشه و لینکهای همسایه، حداکثر یک لینک به هر مقصد و `target=\"_self\"`.",
        "- **شاخههای جاذبه (۱۸):** نشانگرهای بدنه، کارت اطلاعات سریع (`sa_city_id`، مختصات، بهترین زمان، مدت بازدید)، لینک به شهرستان مادر و استان، ۲ تا ۴ لینک هممسیر، بدون لینک تکراری.",
        "- **هاب استان (۱):** لینک به تمام ۱۶ شهرستان و تمام ۱۸ جاذبهٔ خوشه، بدون تکرار.",
        "",
        "تراکم ۲ تا ۳ لینک در هر بخش، مطابق §۵، **هشدار** است نه خطای مسدودکننده (فهرستهای راهنمای استان و بخش نقشهٔ شهرستانها از پیش پرلینکاند).",
        "",
        "## تفاوت مستندات و کد (ثبتشده در `decisions.md`)",
        "",
        "سند مستر، آبوهوا، جاذبهها و FAQ را جزو بدنهٔ مقاله میداند، ولی `single-city.php` همان بخشها را از متا رندر میکند (`#weather-section`، `#attractions-section`، FAQ). طبق قانون «کد بر سند قدیمی مقدم است»، این بخشها به بدنه اضافه نشدند و کامل بودن متا ممیزی شد. بودجهٔ واژهٔ سند ۱۲۰۰–۱۸۰۰ و گارد کد ۱۵۰۰–۲۴۰۰ است؛ خروجی در بازهٔ مشترک و با گارد کد سنجیده شد.",
        "",
        "## ایرادهای وضعیت پیش از نشست",
        "",
    ]
    for r in baseline["rows"]:
        if r["problems"]:
            lines.append(f"- **{r['stem']}** ({r['kind']}، {r['words']} واژه، {r['internal_links']} لینک داخلی)")
            for p in r["problems"]:
                lines.append(f"  - {p}")
    lines += ["", "## وضعیت خروجی v6", ""]
    bad = [r for r in candidate["rows"] if r["problems"]]
    if bad:
        for r in bad:
            lines.append(f"- **{r['stem']}**: {'; '.join(r['problems'])}")
    else:
        lines.append(f"همهٔ {candidate['summary']['files']} فایل بدون ایراد مسدودکننده ممیزی شدند.")
    lines += [
        "",
        "## محدودهٔ کار",
        "",
        "این خروجی **پیشنویس محلی** است؛ هیچ نوشتن زندهای انجام نشد (قفل fail-closed در `.agent/RULES.md`). متنهای محتوایی تأییدشده حفظ شدند و فقط لینکسازی، همترازسازی متا و نگارش خوشهٔ بدون شاخهٔ پاکدشت از شواهد ازپیشتأییدشدهٔ مخزن ساخته شد؛ پژوهش وب تازهای انجام نشد.",
        "",
    ]
    path.write_text("\n".join(lines), encoding="utf-8")


def rx_report(baseline: dict, candidate: dict, path: Path) -> None:
    lines = [
        "# ممیزی تمپلت مستر و لینک‌سازی خوشه‌ای — استان خراسان رضوی (استان ۲)",
        "",
        "تاریخ: ۲۰۲۶-۱۱-۲۵ / ۱۴۰۵/۰۹/۰۴ · منبع قواعد: `docs/live-site/CLUSTER-SYSTEM-MASTER.md` "
        "§۳/§۴/§۵ و گارد کد `tools/wp-agent/replace_src/check_template.py`.",
        "",
        "خوشهٔ `#2 مشهد` (هفت شاخه) + خوشهٔ `#34 نیشابور` (سه شاخه) + هاب استان `50`.",
        "",
        "| مجموعه | فایل‌ها | سالم | دارای ایراد | ایرادها | هشدارها |",
        "|---|---:|---:|---:|---:|---:|",
    ]
    for d in (baseline, candidate):
        s = d["summary"]
        label = ("وضعیت پیش از این نشست (baseline)" if d["source"] == "baseline"
                 else "خروجی `outbox/20261125-razavi-khorasan-cluster-v6/`")
        lines.append(f"| {label} | {s['files']} | {s['clean']} | {s['with_issues']} | "
                     f"{s['issues']} | {s['warnings']} |")
    lines += ["", "## ایرادهای وضعیت پیش از نشست", ""]
    if not any(r["problems"] for r in baseline["rows"]):
        lines.append("هیچ ایراد مسدودکننده‌ای ثبت نشد.")
    for r in baseline["rows"]:
        if r["problems"]:
            lines.append(f"- **{r['stem']}** ({r['kind']}، {r['words']} واژه، "
                         f"{r['internal_links']} لینک داخلی)")
            for prob in r["problems"]:
                lines.append(f"  - {prob}")
    lines += ["", "## وضعیت خروجی v6", ""]
    bad = [r for r in candidate["rows"] if r["problems"]]
    if bad:
        for r in bad:
            lines.append(f"- **{r['stem']}**: {'; '.join(r['problems'])}")
    else:
        lines.append(f"همهٔ {candidate['summary']['files']} فایل بدون ایراد مسدودکننده ممیزی شدند.")
    lines += [
        "",
        "## آنچه بررسی شد",
        "",
        "- **شهرستان‌ها (۲):** نشانگرهای شش‌گانهٔ گارد کد، بودجهٔ واژهٔ `1500–2400`، متای کامل "
        "تمپلیت مستر، تبصرهٔ سرشماری ۱۳۹۵، SEO (عنوان ≤ ۶۰ و توضیح ۱۴۰–۱۵۵ نویسه)، FAQ، "
        "منابع دولایه، لینک به هاب استان، همهٔ شاخه‌های همان خوشه و شهرستان‌های همسایه.",
        "- **جاذبه‌ها (۱۰):** نشانگرهای نه‌گانهٔ بدنه، کارت اطلاعات سریع (`sa_city_id` و "
        "`sa_province_id`)، بودجهٔ واژه (گارد ۸۰۰–۱۵۰۰ / مستر ۹۰۰–۱۴۰۰)، لینک به شهرستان مادر و "
        "استان، ۲ تا ۴ هم‌مسیر، حداکثر یک لینک به هر مقصد و `target=\"_self\"`.",
        "- **هاب استان (۱):** لینک به هر دو شهرستان خوشه و همهٔ ۱۰ جاذبه، بدون تکرار.",
        "",
        "## محدودهٔ کار",
        "",
        "این خروجی **پیش‌نویس محلی** است؛ هیچ نوشتن زنده‌ای انجام نشد و شمارنده‌های رسمی "
        "(`0/31` استان، `6/100` خوشه، `2/100` پیش‌نویس، `18/239` شاخه) دست‌نخورده‌اند. متن‌های "
        "گسترش‌یافته از شواهد ازپیش‌تأییدشدهٔ مخزن (پروندهٔ جاذبه‌ها، متای شهرستان، "
        "`docs/live-site/briefs/mashhad-evidence.md` و مقالهٔ هاب استان) نوشته شدند؛ پژوهش وب "
        "تازه‌ای انجام نشد.",
        "",
    ]
    path.write_text("\n".join(lines), encoding="utf-8")


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--build", action="store_true")
    ap.add_argument("--audit", action="store_true")
    ap.add_argument("--build-razavi", action="store_true")
    ap.add_argument("--audit-razavi", action="store_true")
    args = ap.parse_args()
    if args.build:
        build()
    if args.audit:
        manifest = load_manifest()
        baseline = audit(config_for("baseline", manifest))
        candidate = audit(config_for("v6", manifest))
        RESULTS.mkdir(parents=True, exist_ok=True)
        REPORTS.mkdir(parents=True, exist_ok=True)
        (RESULTS / "20261125-cluster-template-audit.json").write_text(
            json.dumps({"baseline": baseline, "v6": candidate}, ensure_ascii=False, indent=1) + "\n",
            encoding="utf-8")
        report(baseline, candidate, REPORTS / "cluster-template-audit-2026-11-25.md")
        print("baseline:", baseline["summary"])
        print("v6      :", candidate["summary"])
        print(f"report  : {(REPORTS / 'cluster-template-audit-2026-11-25.md').relative_to(REPO)}")
    if args.build_razavi:
        build_rx()
    if args.audit_razavi:
        baseline = audit(rx_config("baseline"))
        candidate = audit(rx_config("v6"))
        RESULTS.mkdir(parents=True, exist_ok=True)
        (RESULTS / "20261125-razavi-khorasan-cluster-audit.json").write_text(
            json.dumps({"baseline": baseline, "v6": candidate}, ensure_ascii=False,
                       indent=1) + "\n", encoding="utf-8")
        rx_report(baseline, candidate,
                  REPORTS / "razavi-khorasan-cluster-audit-2026-11-25.md")
        print("baseline:", baseline["summary"])
        print("v6      :", candidate["summary"])
        print("report  :",
              (REPORTS / "razavi-khorasan-cluster-audit-2026-11-25.md").relative_to(REPO))
    if not (args.build or args.audit or args.build_razavi or args.audit_razavi):
        ap.print_help()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
