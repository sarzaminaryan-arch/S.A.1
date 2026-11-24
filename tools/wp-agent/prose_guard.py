#!/usr/bin/env python3
"""نگهبان نثر — متن ماشینی/پیش‌نویس را پیش از انتشار می‌گیرد.

چرا این ابزار ساخته شد (۲۰۲۶-۱۱-۲۳): بازنویسی هفت صفحهٔ خوزستان (شغل `20261122-13`)
روی سایت منتشر شد و متن آن با هیچ‌یک از دروازه‌های موجود گرفته نشد. متن، فارسیِ
بی‌غلط بود و حجمش هم بالای کف؛ ولی **نثر نبود**:

- جمله‌های تکراری و نیمه‌تکراری («کشاورزی شوش، آبی و دیم، با آب از سد شاوور و دز
  تغذیه می‌شود…» دو بار در یک صفحه)؛
- یک الگوی جمله که ده‌ها بار با اسم عوض‌شده تکرار می‌شد («در لالی، …» ×۱۵)؛
- خروجی ماشینیِ شکسته (`&#۸۲۲۰;\\``)، نویسه‌های غیرفارسی (Urdu «ہ» در شوشتر،
  عبری «סבי» در لالی) و فهرست‌های نامک لاتین؛
- متن پیش‌نویس پژوهشی با جدول و «باید بررسی شود» و «پیش‌نویس» روی صفحهٔ منتشرشده.

هیچ‌یک از این‌ها «غلط املایی» نیست؛ پس `fa_lint` (که فقط املا و ارقام و ماه میلادی
را می‌گیرد) از آن‌ها رد شد. این ابزار همان لایهٔ گم‌شده است: **آیا این متن، نوشتهٔ
انسانیِ یک بارِ مشخص است یا خروجی تکراری یک قالب؟**

سنجه‌ها (همه روی متنِ نمایان، بدون تگ):

۱. `stub` — نشانهٔ پیش‌نویس/جای‌نگهدار («پیش‌نویس»، «نیازمند بررسی»، «باید بررسی
   شود»، «در حال تکمیل»، «نامشخص»، «جای‌نگهدار»، «TBD»). قاعدهٔ v3: جای خالی در
   گزارش داخلی، نه در متن منتشرشده.
۲. `script` — نویسهٔ غیرفارسی در متن فارسی: حروف اردو/عربیِ خارج از فارسی
   (ہ ے ٹ ڈ ڑ ں ھ ۃ) و هر نویسهٔ عبری/سریانی/سیریلیک.
۳. `junk` — بازماندهٔ ماشینی: `&#<رقم>;` رندرنشده، بک‌اسلشِ گریخته، `\\``،
   ستارهٔ مارک‌داون، تگ HTML خام در متن.
۴. `table` — جدول: `<table>`، ردیف `|`، یا بلوک جدول گوتنبرگ. قاعدهٔ مطلق v3.
۵. `slug` — نامک لاتین در متن فارسی (`haftkel - جنوب`) یا واژهٔ لاتین بی‌جا
   (واژه‌های مجاز: UNESCO، OSM، NATO و همان‌هایی که `fa_lint` مجاز می‌داند).
۶. `near_dup` — جمله‌های تکرار/نیمه‌تکرار: نسبت جفت‌های با شباهت ≥ ۰٫۷۵ به کل
   جمله‌ها. آستانه: بیش از ۲٪ جمله‌ها (و دست‌کم ۳ جفت).
۷. `repeat` — یک آغاز دوواژه‌ای که در بیش از ۸٪ جمله‌ها تکرار شده («در لالی، …»).
   این همان الگویی است که «در شهرستان {نام}» را ده‌ها بار می‌سازد.
۸. `gap` — متنِ بریده/چسبیده: دو فاصلهٔ پیاپی، «؛» در پایان خط، تکرار «،،».

خروجی: `check(text)` → `{"ok", "score", "problems", "metrics"}`. `problems` فهرست
رشته‌های فارسی است؛ هر عضو، انتشار را متوقف می‌کند. ابزارها:
`county_page.py` (بیلد)، `check_template.py` (نگهبان قالب)، `build_section_batch.py`
(ساخت بچ) و `wp_agent.py` (پیش از هر نوشتن زنده).

استفاده:

    python3 tools/wp-agent/prose_guard.py tools/wp-agent/replace_src/data/shush.json
    python3 tools/wp-agent/prose_guard.py --text "متن …"
"""
from __future__ import annotations

import difflib
import json
import re
import sys
from collections import Counter
from pathlib import Path

# --- ۱. پیش‌نویس و جای‌نگهدار -------------------------------------------------
STUB_RE = re.compile(
    r"پیش[‌ ]?نویس(?!ه)|نیازمند (?:بررسی|تطبیق|منبع|تکمیل)|باید (?:بررسی|تکمیل|تطبیق)"
    r"|در حال (?:تکمیل|ساخت|بررسی)|نامشخص|جای[‌ ]?نگهدار|تکمیل شود|بررسی شود"
    r"|انجام شود|متعاقباً|TBD|TODO|Lorem"
)

# --- ۲. نویسهٔ غیرفارسی -------------------------------------------------------
# حروفی که در فارسی نیستند و در متن فارسی همیشه نشانهٔ ورودی ماشینی‌اند.
FOREIGN_SCRIPT_CHARS = "ہےٹڈڑںھۃۂۓ"  # اردو/عربیِ غیرفارسی («ئ» و «ء» فارسی‌اند)
FOREIGN_SCRIPT_RE = re.compile(r"[\u0500-\u052F\u0590-\u05FF\u0700-\u074F\u0750-\u077F\uFB50-\uFDFF\uFE70-\uFEFF]")
FOREIGN_SET = set(FOREIGN_SCRIPT_CHARS)

# --- ۳. بازماندهٔ ماشینی ------------------------------------------------------
JUNK_PATTERNS = (
    (re.compile(r"&#\s*[0-9۰-۹]{2,};"), "نویسهٔ عددیِ رندرنشده (&#…;)"),
    (re.compile(r"\\[`\\nrt\"']"), "بک‌اسلشِ گریخته در متن"),
    (re.compile(r"\*\*[^*\n]{2,40}\*\*"), "ستارهٔ مارک‌داون در متن"),
    (re.compile(r"</?(?:p|div|span|br|li|ul|ol|table|tr|td)\b[^>]*>", re.I), "تگ HTML در متنِ نمایان"),
)
# نویسهٔ نامرئیِ ناخواسته (به‌جز نیم‌فاصله، ZWJ و BOM).
INVISIBLE_RE = re.compile(r"[\u200b\u200e\u200f\u202a-\u202e\u2060\ufeff]")

# --- ۴. جدول -----------------------------------------------------------------
TABLE_RE = re.compile(r"<table\b|<!--\s*wp:table", re.I)
PIPE_ROW_RE = re.compile(r"(?:^|\n)\s*\|[^\n]*\|", re.M)

# --- ۵. نامک/واژهٔ لاتین ------------------------------------------------------
LATIN_ALLOWED = {
    "unesco", "osm", "nato", "gps", "cpr", "sos", "usb", "dvd", "pdf", "html",
    "wp", "os", "id", "faq", "seo", "aed", "ii", "iii", "iv", "vi", "vii",
}
LATIN_TOKEN_RE = re.compile(r"[A-Za-z][A-Za-z0-9'’\-]*")
SLUG_TOKEN_RE = re.compile(r"\b[a-z]{2,}(?:-[a-z]{2,})+\b")
_COUNTY_SLUGS: set[str] | None = None


def county_slugs() -> set[str]:
    """نامک‌های ثبت‌شدهٔ شهرستان‌ها از worklist (۴۸۳ ردیف) — برای شناسایی نامک خام."""
    global _COUNTY_SLUGS
    if _COUNTY_SLUGS is None:
        path = Path(__file__).resolve().parent / "template_worklist.json"
        try:
            rows = json.loads(path.read_text(encoding="utf-8"))
            _COUNTY_SLUGS = {str(r.get("slug") or "") for r in rows if r.get("slug")}
        except (OSError, ValueError):
            _COUNTY_SLUGS = set()
    return _COUNTY_SLUGS

# --- ۶ و ۷. تکرار -----------------------------------------------------------------
SENT_SPLIT_RE = re.compile(r"(?<=[.!?؟])\s+")
NEAR_DUP_RATIO = 0.75          # شباهت دو جمله برای «نیمه‌تکرار»
NEAR_DUP_MIN_PAIRS = 3         # کمتر از این، اتفاق طبیعی است
NEAR_DUP_SHARE = 0.02          # سهم مجاز جفت‌های نیمه‌تکرار از کل جمله‌ها
REPEAT_MIN_OCCURRENCES = 6     # یک آغاز دوواژه‌ای چند بار تکرار شود که «الگو» شود
REPEAT_SHARE = 0.08            # سهم مجاز از کل جمله‌ها

WORD_RE = re.compile(r"[^\s]+")


def visible_text(html: str) -> str:
    """HTML → متن نمایان (تگ‌ها حذف، نویسه‌های HTML باز، فاصله‌ها یکی)."""
    import html as _html

    s = re.sub(r"<(script|style)\b.*?</\1>", " ", html or "", flags=re.S | re.I)
    s = re.sub(r"<[^>]+>", " ", s)
    s = _html.unescape(s)
    return re.sub(r"[ \t\u00a0]+", " ", s)


def sentences(text: str) -> list[str]:
    """جمله‌های معنی‌دار (دست‌کم سه واژه)."""
    out = []
    for piece in SENT_SPLIT_RE.split(re.sub(r"\s+", " ", text)):
        piece = piece.strip()
        if len(piece.split()) >= 3:
            out.append(piece)
    return out


def _nice(text: str, n: int = 80) -> str:
    return re.sub(r"\s+", " ", text).strip()[:n]


def _near_dup_pairs(sents: list[str]) -> tuple[int, list[str]]:
    pairs = 0
    examples: list[str] = []
    for i in range(len(sents)):
        a = sents[i]
        for j in range(i + 1, len(sents)):
            b = sents[j]
            shorter, longer = (a, b) if len(a) <= len(b) else (b, a)
            if len(shorter) / max(1, len(longer)) < 0.7:      # طول خیلی متفاوت = جفت نیست
                continue
            if difflib.SequenceMatcher(None, a, b).ratio() >= NEAR_DUP_RATIO:
                pairs += 1
                if len(examples) < 2:
                    examples.append(_nice(a))
    return pairs, examples


def _repeat_openers(sents: list[str], name: str = "") -> tuple[int, str, int]:
    openers = Counter()
    name = (name or "").strip()
    for s in sents:
        words = s.split()
        if len(words) < 2:
            continue
        # جمله‌ای که با خودِ نام یا با «شهرستان» آغاز می‌شود طبیعی است («شوشتر شهری
        # است…»)؛ ولی «در لالی، …» پانزده بار، الگوی ماشینی است و باید گرفته شود.
        if name and (" ".join(words[:len(name.split())]) == name or words[0] == "شهرستان"):
            continue
        openers[" ".join(words[:2])] += 1
    if not openers:
        return 0, "", 0
    opener, count = openers.most_common(1)[0]
    return count, opener, len(sents)


def check(text: str, min_sentences: int = 8, name: str = "") -> dict:
    """همهٔ سنجه‌ها روی متنِ نمایانِ یک صفحه.

    `text` می‌تواند HTML باشد؛ خودش نمایان می‌کند.
    """
    raw = text or ""
    plain = visible_text(raw)
    sents = sentences(plain)
    words = len(WORD_RE.findall(plain))
    problems: list[str] = []
    metrics = {"words": words, "sentences": len(sents)}

    # ۱. پیش‌نویس
    stubs = sorted({m.group(0) for m in STUB_RE.finditer(plain)})
    if stubs:
        problems.append("متن پیش‌نویس/جای‌نگهدار: " + "، ".join(f"«{s}»" for s in stubs[:4]))

    # ۲. نویسهٔ غیرفارسی
    foreign = Counter(ch for ch in plain if ch in FOREIGN_SET)
    foreign.update(m.group(0) for m in FOREIGN_SCRIPT_RE.finditer(plain))
    if foreign:
        sample = "، ".join(f"«{ch}»×{n}" for ch, n in foreign.most_common(4))
        problems.append(f"نویسهٔ غیرفارسی در متن: {sample}")

    # ۳. بازماندهٔ ماشینی
    for rx, label in JUNK_PATTERNS:
        m = rx.search(plain)
        if m:
            problems.append(f"{label}: «{_nice(m.group(0), 40)}»")
    inv = INVISIBLE_RE.search(plain)
    if inv:
        problems.append("نویسهٔ نامرئی ناخواسته در متن")

    # ۴. جدول
    if TABLE_RE.search(raw):
        problems.append("جدول در بدنه (قاعدهٔ مطلق v3: هیچ جدولی)")
    if PIPE_ROW_RE.search(plain):
        problems.append("ردیف جدول با «|» در متن")

    # ۵. نامک و واژهٔ لاتین
    latin = [t for t in LATIN_TOKEN_RE.findall(plain) if t.lower() not in LATIN_ALLOWED
             and not re.fullmatch(r"[IVXLC]+", t)]
    known = county_slugs()
    slugs = sorted({m.group(0) for m in SLUG_TOKEN_RE.finditer(plain)})
    slugs += sorted({t for t in latin if t.lower() in known})
    if slugs:
        problems.append("نامک لاتین در متن: " + "، ".join(sorted(set(slugs))[:3]))
    elif latin:
        problems.append("واژهٔ لاتین بی‌جا در متن: " + "، ".join(sorted(set(latin))[:3]))

    # ۶. نیمه‌تکرار
    pairs, dup_examples = _near_dup_pairs(sents) if len(sents) >= min_sentences else (0, [])
    metrics["near_dup_pairs"] = pairs
    if pairs >= NEAR_DUP_MIN_PAIRS and pairs > NEAR_DUP_SHARE * max(1, len(sents)):
        problems.append(
            f"{pairs} جفت جملهٔ تکرار/نیمه‌تکرار در {len(sents)} جمله "
            f"(سقف {int(NEAR_DUP_SHARE * 100)}٪) — نمونه: «{dup_examples[0] if dup_examples else ''}»"
        )

    # ۷. تکرار آغاز جمله
    count, opener, total = _repeat_openers(sents, name) if len(sents) >= min_sentences else (0, "", len(sents))
    metrics["repeat_opener"] = {"phrase": opener, "count": count}
    if count >= REPEAT_MIN_OCCURRENCES and count > REPEAT_SHARE * max(1, total):
        problems.append(
            f"آغاز «{opener}» در {count} جمله از {total} (سقف {int(REPEAT_SHARE * 100)}٪) — الگوی ماشینی"
        )

    # ۸. بریدگی متن
    gaps = []
    if re.search(r"[^\n] {2,}[^\n]", plain.strip()):
        gaps.append("فاصلهٔ دوتایی")
    if re.search(r"؛\s*\n", plain):
        gaps.append("«؛» در پایان خط")
    if "،،" in plain or re.search(r"،\s*،", plain):
        gaps.append("کامای تکراری")
    if plain.count("\\") > 1 and not JUNK_PATTERNS[1][0].search(plain):
        gaps.append("بک‌اسلش در متن")
    if gaps:
        problems.append("بریدگی متن: " + "، ".join(gaps))

    score = len(problems)
    return {"ok": not problems, "score": score, "problems": problems, "metrics": metrics}


def check_file(path: Path) -> dict:
    """JSON دادهٔ شهرستان یا HTML/متن یک صفحه."""
    p = Path(path)
    text = p.read_text(encoding="utf-8")
    if p.suffix == ".json":
        try:
            data = json.loads(text)
        except ValueError:
            return check(text)
        if isinstance(data, dict):
            body = [data.get(k) for k in ("intro", "map", "history", "people", "economy",
                                          "stay", "modern", "food", "notables")]
            meta_bits = [str(data.get("tagline") or "")]
            text = "\n".join(_flatten(body))
            meta = data.get("meta") or {}
            meta_bits += [str(meta.get(k) or "") for k in
                          ("sa_cty_best_time", "sa_cty_livelihood", "sa_cty_climate",
                           "sa_seo_title", "sa_seo_description")]
            out = check(text, name=str(data.get("name") or ""))
            meta_problems = check("\n".join(meta_bits))
            if meta_problems["problems"]:
                out["problems"] += [f"meta: {x}" for x in meta_problems["problems"]]
                out["score"] = len(out["problems"])
                out["ok"] = False
            return out
    return check(text)


# کلیدهایی که متنِ منتشرشده‌اند؛ کلیدهای دیگر (sources، id، …) داده‌اند نه نثر.
TEXT_KEYS = ("p", "h", "list", "title", "q", "a", "label", "text")


def _flatten(node) -> list[str]:
    if isinstance(node, str):
        return [node]
    if isinstance(node, list):
        return [x for item in node for x in _flatten(item)]
    if isinstance(node, dict):
        return [x for k, v in node.items() if k in TEXT_KEYS for x in _flatten(v)]
    return []


def main(argv: list[str]) -> int:
    if not argv:
        print(__doc__)
        return 2
    if argv[0] == "--text":
        result, label = check(" ".join(argv[1:])), "--text"
    else:
        result, label = check_file(Path(argv[0])), argv[0]
    print(json.dumps({"target": label, **result}, ensure_ascii=False, indent=1))
    return 0 if result["ok"] else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
