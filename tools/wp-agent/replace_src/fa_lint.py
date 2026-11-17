#!/usr/bin/env python3
"""فیلتر املا و نگارش فارسی — پیش از هر انتشار روی متن و متای صفحه اجرا می‌شود.

دستور مالک (۱۶ نوامبر ۱۴۰۵): «ظهرهای ژوئیه و اوت در فارسی نداریم، یک فیلتر
برای غلط املایی یا چنین مواردی بذار». این ابزار همان فیلتر است و چهار جا
صدا زده می‌شود: `build_section_batch.py` (پیش از نوشتن)، `check_template.py`
(گارد انتشار)، `wp_agent.py` (پیش از هر update زنده) و `--fix` دستی.

چه چیزهایی را می‌گیرد:
  ۱) حروف و ارقام عربی («ي»، «ك»، «ى»، «ة»، ٠١٢…)؛
  ۲) نام ماه‌های میلادی و سال میلادی در متن فارسی (ژوئیه، اوت، اکتبر، ۲۰۲۴ …)
     — معادل خورشیدی/فصلی پیشنهاد می‌شود؛
  ۳) نیم‌فاصله‌های جاافتاده (می رود، کتاب ها، بجا‌مانده) و نیم‌فاصله‌های تکراری؛
  ۴) «ها»ی بی‌نیم‌فاصله در واژه‌های پرتکرار (بیمارستانها، دانشگاهها …)؛
  ۵) فاصلهٔ تکراری، فاصله پیش از نشانهٔ نگارش، نویسهٔ نامرئی تکراری؛
  ۶) واژه‌های ممنوع تحریریه (راستش، بازبینی انسانی، واژه‌های هوش مصنوعی) و
     عبارت‌های «بی‌منبع» که مالک ممنوع کرده؛
  ۷) واژهٔ لاتین بی‌جا در متن فارسی (نام‌های مجاز مثل UNESCO، OSM، NBA مستثنا).

هر یافته یک خط است: «<شناسه>: <توضیح> — «<نمونه>»». کد خروج ۱ اگر یافته‌ای
باشد تا بیلد یا کار منتشر نشود. `--fix` فقط اصلاح‌های صددرصد مطمئن را انجام
می‌دهد و بقیه را برای بازنویسی دستی گزارش می‌کند.

استفاده (از ریشهٔ مخزن):
    python3 tools/wp-agent/replace_src/fa_lint.py tools/wp-agent/outbox/<dir>
    python3 tools/wp-agent/replace_src/fa_lint.py --text "ظهرهای ژوئیه …"
    python3 tools/wp-agent/replace_src/fa_lint.py --fix <file.html|dir>
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ZWNJ = "\u200c"
P = "\u0600-\u06FF"      # هر حرف فارسی/عربی
H = P + ZWNJ             # حرف یا نیم‌فاصله

# --- ۱. حروف و ارقام عربی ---------------------------------------------------
ARABIC_CHARS = {"ي": "ی", "ك": "ک", "ى": "ی", "ة": "ه", "ﻻ": "لا"}
ARABIC_DIGIT_CHARS = "٠١٢٣٤٥٦٧٨٩"
ARABIC_DIGITS = str.maketrans(ARABIC_DIGIT_CHARS, "۰۱۲۳۴۵۶۷۸۹")


def _word(term: str) -> str:
    """مرز واژه: نه حرف فارسی و نه لاتین در دو طرف (وگرنه «مارس» در «بیمارستان» پیدا می‌شود)."""
    return r"(?<![\u0600-\u06FFA-Za-z])" + term + r"(?![\u0600-\u06FFA-Za-z])"


# --- ۲. ماه‌های میلادی و سال میلادی ------------------------------------------
FOREIGN_WORDS = (
    (_word("ژوئیه"), "تیر و مرداد", "نام ماه میلادی در متن فارسی"),
    (_word("جولای"), "تیر و مرداد", "نام ماه میلادی در متن فارسی"),
    (_word("اوت"), "مرداد", "نام ماه میلادی در متن فارسی"),
    (_word("آگوست"), "مرداد", "نام ماه میلادی در متن فارسی"),
    (_word("سپتامبر"), "شهریور و مهر", "نام ماه میلادی در متن فارسی"),
    (_word("اکتبر"), "مهر و آبان", "نام ماه میلادی در متن فارسی"),
    (_word("نوامبر"), "آبان و آذر", "نام ماه میلادی در متن فارسی"),
    (_word("دسامبر"), "آذر و دی", "نام ماه میلادی در متن فارسی"),
    (_word("ژانویه"), "دی و بهمن", "نام ماه میلادی در متن فارسی"),
    (_word("فوریه"), "بهمن و اسفند", "نام ماه میلادی در متن فارسی"),
    (_word("مارس"), "اسفند و فروردین", "نام ماه میلادی در متن فارسی"),
    (_word("آپریل"), "فروردین و اردیبهشت", "نام ماه میلادی در متن فارسی"),
    (_word("آوریل"), "فروردین و اردیبهشت", "نام ماه میلادی در متن فارسی"),
    (_word("ژوئن"), "خرداد و تیر", "نام ماه میلادی در متن فارسی"),
    (_word("جون"), "خرداد و تیر", "نام ماه میلادی در متن فارسی"),
    (r"(?<![\d/])(?:۱۹|۲۰)\d{2}(?![\d/])", "۱۴۰۵ و مانند آن", "سال میلادی در متن فارسی"),
)

# --- ۳. نیم‌فاصله‌ها و نگارش‌های نادرست ---------------------------------------
# (الگو، جانشین، توضیح)؛ جانشین با گروه‌های همان الگو کار می‌کند.
WRONG_PHRASES = (
    (r"بجا" + ZWNJ + "مانده", "به‌جامانده", "نگارش نادرست «به‌جامانده»"),
    (r"بجا مانده", "به‌جامانده", "نگارش نادرست «به‌جامانده»"),
    (r"به جا مانده", "به‌جامانده", "نگارش نادرست «به‌جامانده»"),
    # «در می رود» → «می‌رود»؛ «می» باید پیشوند فعل باشد، نه واژهٔ مستقل («می ناب»)
    (r"(?<![" + P + ZWNJ + r"])می (?=[ا-ی]{2,})", "می‌", "نیم‌فاصلهٔ «می» جاافتاده"),
    (r"(?<![" + P + ZWNJ + r"])نمی (?=[ا-ی]{2,})", "نمی‌", "نیم‌فاصلهٔ «نمی» جاافتاده"),
    # «کتاب ها» → «کتاب‌ها»
    (r"(?<![" + H + r"])(" + H + r") +(ها|های|هایی|هایم|هایت|هایش)(?![" + P + r"])",
     r"\1" + ZWNJ + r"\2", "نیم‌فاصلهٔ جمع جاافتاده"),
    (r"([" + P + r"])" + ZWNJ + r"{2,}", r"\1" + ZWNJ, "نیم‌فاصلهٔ تکراری"),
    (r"([" + P + r"])" + ZWNJ + r"(?=[،.:؛؟!\s]|$)", r"\1", "نیم‌فاصله پیش از نشانه یا فاصله"),
    (r"([^" + P + ZWNJ + r"\s])" + ZWNJ + r"(?=[" + P + r"])", r"\1", "نیم‌فاصلهٔ بی‌جا پس از نشانه"),
)

# «ها»ی چسبیده فقط یک جا غلط قطعی است: واژه‌ای که به «ه» یا «ی» تمام می‌شود
# (خانه‌ها، دانشگاه‌ها، مدرسه‌ها). در بقیه («کتابها»، «شهرها») شکل پیوسته هم در
# رسم‌الخط رایج پذیرفته است و فیلتر درباره‌اش حکم نمی‌دهد.
GLUED_HA = (
    # فقط واژه‌هایی که به «ه» تمام می‌شوند: این‌جا چسبیدن «ها» خطای قطعی است
    "خانه", "دانشگاه", "مدرسه", "کارخانه", "اداره", "خانواده", "منطقه", "آرامگاه",
    "امامزاده", "قلعه", "تپه", "چشمه", "موزه", "نیمه", "پروانه", "دسته", "نوشته",
)

# --- ۴. نویسه‌های نامرئی و نشانه‌های تکراری (فاصله‌های HTML عمداً سنجیده
#        نمی‌شوند: مرورگر آن‌ها را جمع می‌کند و گزارشش فقط سر‌و‌صدا است) ---------
STRUCTURE = (
    (r"[\u200b\u200e\u200f\ufeff]", "نویسهٔ نامرئی ناخواسته (ZWSP/جهت‌نما/BOM)"),
    (r" +([،.:؛؟!])", "فاصله پیش از نشانهٔ نگارش"),
    (r"([،.:؛؟!])  +", "فاصلهٔ تکراری پس از نشانهٔ نگارش"),
    (r"\.{3,}", "سه‌نقطهٔ تکراری"),
    (r"؛;|;؛", "نشانهٔ نگارش تکراری"),
    (r"،,|,،", "ویرگول فارسی و لاتین کنار هم"),
)

# --- ۵. واژه‌های ممنوع تحریریه (هم‌گام با wp_agent.BANNED و check_template) ----
BANNED = (
    "راستش", "هوش مصنوعی", "بازبینی انسانی", "بازبینی", "ChatGPT", "Claude", "Gemini",
    "منبع معتبر", "فاقد منبع", "بدون منبع", "تأیید نمی‌شود", "بررسی بیشتر", "منبعی یافت",
    "در حال ساخت", "کلنگ", "به‌زودی",
)

# --- ۶. بخش‌های غیرمتنی ------------------------------------------------------
TAG_RE = re.compile(r"<[^>]+>")
COMMENT_RE = re.compile(r"<!--.*?-->", re.S)
URL_RE = re.compile(r"https?://[^\s\"'<>]+")
SCRIPT_RE = re.compile(r"<(script|style)\b.*?</\1>", re.S | re.I)
LATIN_TOKEN_RE = re.compile(r"[A-Za-z][A-Za-z0-9.'’\-]*")
# Photo credits must retain the creator’s original name and licence. Their Latin
# text is exempt only inside an explicitly marked, plain-text credit span.
PHOTO_CREDIT_RE = re.compile(r'<span\s+class=["\']sa-photo-credit-latin["\']\s*>([^<>]*)</span>', re.I)
LATIN_ALLOWED = re.compile(
    r"^(?:[A-Z]{2,6}|[A-Z]\.[A-Z]\.G|WordPress|wp|sa|km|kg|mm|m|ID|URL|HTML|CSS|JSON|LD|"
    r"svg|png|webp|jpg|jpeg|gif|pdf|mp4|3G|4G|5G|nba|ci|OSM|GPS|API|FAQ|UNESCO|"
    r"Google|Maps|Mail|sarzaminaryan\.ir|OpenStreetMap|Balad|Neshan)$"
)


def visible_text(text: str) -> str:
    """فقط متنی که خواننده می‌بیند: تگ، کامنت، اسکریپت و نشانی حذف می‌شود."""
    text = SCRIPT_RE.sub(" ", text)
    text = PHOTO_CREDIT_RE.sub(lambda m: LATIN_TOKEN_RE.sub("", m.group(1)), text)
    text = COMMENT_RE.sub(" ", text)
    text = TAG_RE.sub("\u0000", text)   # مرز بلوک: نه فاصله، نه حرف
    text = URL_RE.sub("\u00a7", text)   # نشانی کامل: یک نشانگر
    return text


def _label(path: Path) -> str:
    """برچسب کوتاه برای گزارش: مسیر نسبت به ریشهٔ مخزن."""
    try:
        return str(path.resolve().relative_to(Path.cwd().resolve()))
    except ValueError:
        return str(path)


def _show(text: str) -> str:
    return text.replace(ZWNJ, "␣").replace("\u00a7", "…")


# زمینه‌های مجاز: در این جمله‌ها «منبع معتبر» درخواست اصلاحیه از خواننده است،
# نه جملهٔ «بی‌منبع» که مالک ممنوع کرده. برای همین پیش از بررسی، آن جمله‌ها از
# متن سنجیده‌شده کنار می‌روند.
SANCTIONED_BOX = re.compile(
    r"اگر[^<]{0,80}(?:اصلاحی|اصلاح|داده|عکس|منبع)[^<]{0,120}(?:بفرستید|بنویسید)[^<]{0,20}", re.S
)


def scan(text: str, where: str = "", spacing: bool = False) -> list[str]:
    """همهٔ یافته‌های املا/نگارش (بدون تغییر متن).

    `spacing=False` (پیش‌فرض در بیلد و کار زنده) فقط خطاهای قطعی را می‌آورد؛
    گزارش دزدگیر فاصله‌ها با `spacing=True` یا `--spacing` است، چون در HTML
    فاصله‌های اضافی فقط قالب‌بندی‌اند و مرورگر جمعشان می‌کند.
    """
    issues: list[str] = []
    body = SANCTIONED_BOX.sub("\u0000", visible_text(text))

    for bad, good in ARABIC_CHARS.items():
        if bad in body:
            issues.append(f"{where}AR: حرف عربی «{bad}» جای «{good}» — ×{body.count(bad)}")
    for ch in ARABIC_DIGIT_CHARS:
        if ch in body:
            issues.append(f"{where}AR: رقم عربی «{ch}» — باید فارسی/لاتین باشد")

    for pattern, suggestion, note in FOREIGN_WORDS:
        for m in re.finditer(pattern, body):
            ctx = body[max(0, m.start() - 22):m.end() + 22].strip()
            issues.append(f"{where}FOREIGN: {note} «{m.group(0)}» → «{suggestion}» — «…{_show(ctx)}…»")

    for pattern, replacement, note in WRONG_PHRASES:
        for m in re.finditer(pattern, body):
            issues.append(f"{where}PHRASE: {note} — «{_show(m.group(0))}» → «{_show(m.expand(replacement))}»")

    for stem in GLUED_HA:
        for m in re.finditer(_word(re.escape(stem) + "ها"), body):
            issues.append(f"{where}GLUE: «{m.group(0)}» → «{stem}{ZWNJ}ها» (نیم‌فاصلهٔ جمع)")

    for pattern, note in STRUCTURE:
        if note.startswith(("فاصله پیش", "فاصلهٔ تکراری پس")) and not spacing:
            continue
        for m in re.finditer(pattern, body):
            ctx = body[max(0, m.start() - 14):m.end() + 14].strip()
            issues.append(f"{where}SPACE: {note} — «{_show(ctx)}»")

    for phrase in BANNED:
        if phrase in body:
            issues.append(f"{where}BANNED: واژهٔ ممنوع «{phrase}»")

    for m in LATIN_TOKEN_RE.finditer(body):
        token = m.group(0)
        if LATIN_ALLOWED.match(token):
            continue
        ctx = body[max(0, m.start() - 18):m.end() + 18].strip()
        issues.append(f"{where}LATIN: واژهٔ لاتین «{token}» در متن فارسی — «…{_show(ctx)}…»")
    return issues


def fix_text(text: str) -> tuple[str, list[str]]:
    """اصلاح‌های قطعی + فهرست تغییرها (بقیه برای بازنویسی دستی گزارش می‌شوند)."""
    applied: list[str] = []
    for bad, good in ARABIC_CHARS.items():
        if bad in text:
            applied.append(f"AR: «{bad}» → «{good}» ×{text.count(bad)}")
            text = text.replace(bad, good)
    if any(ch in text for ch in ARABIC_DIGIT_CHARS):
        applied.append("AR: ارقام عربی → فارسی")
        text = text.translate(ARABIC_DIGITS)
    for pattern, suggestion, _note in FOREIGN_WORDS:
        text, n = re.subn(pattern, suggestion, text)
        if n:
            applied.append(f"FOREIGN: → «{suggestion}» ×{n}")
    for pattern, replacement, note in WRONG_PHRASES:
        text, n = re.subn(pattern, replacement, text)
        if n:
            applied.append(f"PHRASE: {note} ×{n}")
    for stem in GLUED_HA:
        text, n = re.subn(_word(re.escape(stem) + "ها"), stem + ZWNJ + "ها", text)
        if n:
            applied.append(f"GLUE: «{stem}ها» → «{stem}‌ها» ×{n}")
    return text, applied


# --- CLI --------------------------------------------------------------------
# ---- متا: کلیدهای ماشینی (نامک، مختصات، نقشه، اسکیما) متن فارسی نیستند ----
META_SKIP_KEYS = {
    "slug", "sa_city_slug", "sa_province_id", "sa_schema_contained_in", "lat", "lng",
    "sa_google_map_url", "sa_county_schema_type", "sa_city_schema_type", "sa_map_provider",
    "sa_neshan_map_key", "sa_city_latitude", "sa_city_longitude", "sa_map_zoom",
    "sa_cty_population", "sa_city_population", "sa_cty_census_year", "sa_city_elevation",
    "sa_cty_districts", "sa_cty_cities", "sa_cty_rural_districts",
}


def scan_meta(meta: dict, where: str = "") -> list[str]:
    """متای صفحه: فقط متنِ نمایشی سنجیده می‌شود.

    - کلیدهای ماشینی (نامک، مختصات، نقشه، اسکیما) کنار گذاشته می‌شوند؛
    - در `sa_cty_neighbors` فقط نام فارسی هر خط (بعد از « - ») و در
      `sa_cty_poi_*` فقط متن فارسی هر خط بررسی می‌شود؛
    - `sa_faq` (JSON) به پرسش/پاسخ باز می‌شود؛
    - `sa_schema_sameas` و `sa_google_map_url` نشانی‌اند و نمی‌آیند.
    """
    issues: list[str] = []
    for key, value in meta.items():
        if key in META_SKIP_KEYS or key in ("sa_schema_sameas", "cc_rating_sum", "cc_rating_count",
                                            "cc_rating_avg"):
            continue
        if not isinstance(value, str):
            continue
        if key == "sa_faq":
            try:
                rows = json.loads(value)
            except ValueError:
                rows = []
            for row in rows if isinstance(rows, list) else []:
                if isinstance(row, dict):
                    issues += scan(str(row.get("q", "")) + " " + str(row.get("a", "")), f"{where}sa_faq: ")
            continue
        if key == "sa_cty_neighbors":
            for line in value.splitlines():
                name_fa = line.split(" - ")[1].strip() if " - " in line else ""
                if name_fa:
                    issues += scan(name_fa, f"{where}{key}: ")
            continue
        if key.startswith("sa_cty_poi_"):
            for line in value.splitlines():
                # «نام فارسی؛ توضیح» — بخش لاتین نام تخصصی مجاز است
                issues += scan(re.sub(r"[A-Za-z][A-Za-z0-9.'’\-]*", " ", line), f"{where}{key}: ")
            continue
        issues += scan(value, f"{where}{key}: ")
    return issues


def collect(paths: list[str]) -> list[Path]:
    files: list[Path] = []
    for raw in paths:
        p = Path(raw)
        if p.is_dir():
            files += sorted(x for x in p.rglob("*")
                            if x.suffix.lower() in (".html", ".json") and not x.name.startswith(".")
                            and x.name != "summary.json")
        elif p.is_file():
            files.append(p)
    return files


def _texts(raw: str, suffix: str) -> list[str]:
    if suffix != ".json":
        return [raw]
    try:
        data = json.loads(raw)
    except ValueError:
        return [raw]
    if isinstance(data, dict):
        return [v for v in data.values() if isinstance(v, str)]
    if isinstance(data, list):
        return [v for v in data if isinstance(v, str)]
    return []


def main(argv: list[str]) -> int:
    args = [a for a in argv[1:] if not a.startswith("--")]
    fix = "--fix" in argv
    quiet = "--quiet" in argv

    if "--text" in argv:
        i = argv.index("--text")
        text = argv[i + 1] if i + 1 < len(argv) else ""
        issues = scan(text, "text: ")
        for line in issues:
            print(line)
        print(f"\n{len(issues)} issue(s)")
        return 1 if issues else 0

    files = collect(args)
    if not files:
        print("فایلی پیدا نشد (مسیر پوشه یا فایل بدهید).")
        return 2

    total = 0
    for path in files:
        raw = path.read_text(encoding="utf-8")
        if fix and path.suffix.lower() in (".html", ".txt", ".md"):
            fixed, applied = fix_text(raw)
            if applied:
                path.write_text(fixed, encoding="utf-8")
                raw = fixed
                if not quiet:
                    print(f"FIXED {path} — " + "; ".join(applied[:8]))
        issues: list[str] = []
        if path.suffix.lower() == ".json":
            try:
                data = json.loads(raw)
            except ValueError:
                data = None
            if isinstance(data, dict):
                issues += scan_meta(data, f"{_label(path)}: ")
            else:
                for chunk in _texts(raw, ".json"):
                    issues += scan(chunk, f"{_label(path)}: ")
        else:
            issues += scan(raw, f"{_label(path)}: ")
        total += len(issues)
        if issues:
            print(f"FAIL {path}")
            for line in issues:
                print(f"      - {line}")
        elif not quiet:
            print(f"OK   {path}")
    print(f"\n{len(files)} file(s), {total} issue(s)")
    return 1 if total else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
