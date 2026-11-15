"""
content_audit.py — read-only quality checks for county (city) pages (audit proposal 2).

Google's March 2024 spam policy treats many low-value pages as "scaled content abuse"
no matter how they are made; the helpful-content guidance asks for original, people-first
pages. This module finds the concrete patterns the 2026-11-15 audit saw on /city/harand/:

  * dup_sentences      — the same sentence twice on one page;
  * template_ratio     — share of a page's sentences whose skeleton (county name and
                         digits masked) appears on many other county pages (boilerplate);
  * facts_numeric_only — the «حقایق جالب» list only repeats numbers already in the infobox;
  * climate_conflict   — infobox climate vs rainfall/temperature stated in the text;
  * impossible         — numbers outside physical/realistic bounds for Iran;
  * faq_template_ratio — share of FAQ questions that are the same template on most pages.

Nothing is written to the site. The output is a fix queue: indexable pages first, then by
population (a demand proxy), then by score. Mass rewrites wait until after the Core Update.
"""

from __future__ import annotations

import html
import json
import re
from collections import Counter

FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
TAG_RE = re.compile(r"<[^>]+>")
SUP_RE = re.compile(r"<sup\b[^>]*>.*?</sup>", re.S | re.I)
SENT_SPLIT = re.compile(r"(?<=[.!?؟؛])\s+|\n+")
WORD_RE = re.compile(r"[\w\u0600-\u06FF\u200c]+", re.U)

# Iran-wide realistic bounds (Damavand 5,610 m; wettest stations ~1,800–2,000 mm/yr).
MAX_ELEVATION_M = 5700
MAX_RAIN_MM = 2500
MAX_MEAN_TEMP_C = 30
MIN_MEAN_TEMP_C = -10


def norm(text: str) -> str:
    t = (text or "").replace("ي", "ی").replace("ك", "ک").translate(FA_DIGITS)
    t = t.replace("٫", ".").replace("٬", ",")  # Persian decimal / thousands separators
    t = t.replace("\u200c", " ")
    return re.sub(r"\s+", " ", t).strip()


def plain(content_html: str) -> str:
    t = SUP_RE.sub("", content_html or "")
    t = re.sub(r"</(p|li|h[1-6]|td|th|tr|div)>", "\n", t, flags=re.I)
    t = TAG_RE.sub(" ", t)
    t = re.sub(r"<!--.*?-->", " ", t, flags=re.S)
    return html.unescape(t)


def sentences(text: str, min_words: int = 6) -> list[str]:
    out = []
    for s in SENT_SPLIT.split(text):
        s = s.strip(" -–—•\t")
        if len(WORD_RE.findall(s)) >= min_words:
            out.append(s)
    return out


def county_name(title: str) -> str:
    t = norm(title)
    m = re.search(r"شهرستان\s+([^؛:|،\-–—]+)", t)
    name = (m.group(1) if m else t.split("؛")[0]).strip()
    return name


def skeleton(sentence: str, name: str) -> str:
    s = norm(sentence)
    if name:
        s = re.sub(r"(?<!\w)" + re.escape(norm(name)) + r"(?!\w)", "X", s)
    s = re.sub(r"\d[\d,٬.]*", "#", s)
    return re.sub(r"[^\w#X ]+", "", s, flags=re.U).strip()


def numbers(text: str) -> list[float]:
    vals = []
    for m in re.finditer(r"\d[\d,٬]*(?:\.\d+)?", norm(text)):
        try:
            vals.append(float(m.group(0).replace(",", "").replace("٬", "")))
        except ValueError:
            pass
    return vals


def section_items(content_html: str, heading_kw: str) -> list[str]:
    """List items (or paragraphs) under the first H2/H3 whose text contains heading_kw."""
    m = re.search(r"<h[23][^>]*>([^<]*" + re.escape(heading_kw) + r"[^<]*)</h[23]>(.*?)(?=<h[23]\b|$)",
                  content_html or "", re.S)
    if not m:
        return []
    body = m.group(2)
    items = re.findall(r"<li[^>]*>(.*?)</li>", body, re.S) or re.findall(r"<p[^>]*>(.*?)</p>", body, re.S)
    return [norm(plain(i)) for i in items if norm(plain(i))]


def meta_numbers(meta: dict) -> set[float]:
    keys = ("sa_cty_population", "sa_city_population", "sa_cty_area", "sa_cty_census_year", "sa_cty_cities",
            "sa_cty_founded", "sa_cty_dehestans", "sa_cty_districts", "sa_city_elevation")
    out: set[float] = set()
    for k in keys:
        for v in numbers(str(meta.get(k) or "")):
            out.add(v)
    return out


def climate_checks(meta: dict, text: str) -> list[str]:
    issues = []
    t = norm(text)
    climate = norm(str(meta.get("sa_cty_climate") or ""))
    rain = [float(x.replace(",", "")) for x in re.findall(r"بارش[^؛\n]{0,40}?(?<![\d.,])(\d[\d,]*(?:\.\d+)?)\s*میلی", t)]
    temp = [float(x) for x in re.findall(r"میانگین دمای? سالانه[^؛\n]{0,25}?(?<![\d.,])(-?\d+(?:\.\d+)?)\s*درجه", t)]
    if rain:
        r = max(rain)
        # «مرطوب/شرجی» on the southern coast means humidity, not rain, so it is not a conflict.
        if r < 100 and re.search(r"پرباران|خزری|معتدل کوهستانی|سردسیر", climate) and not re.search(r"ساحلی|شرجی|گرم", climate):
            issues.append(f"اقلیم «{climate[:40]}» با بارش سالانه {r:g} میلی‌متر نمی‌خواند")
        if r > 900 and re.search(r"خشک|بیابانی|کویری", climate):
            issues.append(f"اقلیم «{climate[:40]}» با بارش سالانه {r:g} میلی‌متر نمی‌خواند")
    if temp:
        tv = max(temp)
        if tv >= 22 and re.search(r"تابستان(?:‌های)? خنک|سرد کوهستانی", climate):
            issues.append(f"اقلیم «{climate[:40]}» با میانگین دمای سالانه {tv:g} درجه نمی‌خواند")
    return issues


def impossible_checks(meta: dict, text: str) -> list[str]:
    issues = []
    t = norm(text)
    for v in re.findall(r"میانگین دمای? سالانه[^؛\n]{0,25}?(?<![\d.,])(-?\d+(?:\.\d+)?)\s*درجه", t):
        if not MIN_MEAN_TEMP_C <= float(v) <= MAX_MEAN_TEMP_C:
            issues.append(f"میانگین دمای سالانه {v} درجه")
    for v in re.findall(r"بارش[^؛\n]{0,40}?(?<![\d.,])(\d[\d,]*(?:\.\d+)?)\s*میلی", t):
        if float(v.replace(",", "")) > MAX_RAIN_MM:
            issues.append(f"بارش سالانه {v} میلی‌متر")
    for v in re.findall(r"ارتفاع[^؛\n]{0,30}?(?<![\d.,])(\d[\d,]*)\s*متر", t):
        if float(v.replace(",", "")) > MAX_ELEVATION_M:
            issues.append(f"ارتفاع {v} متر")
    pop = numbers(str(meta.get("sa_cty_population") or ""))
    area = numbers(str(meta.get("sa_cty_area") or ""))
    if pop and area and area[0] > 0 and pop[0] / area[0] > 30000:
        issues.append(f"تراکم {pop[0] / area[0]:,.0f} نفر در کیلومتر مربع (جمعیت/مساحت متا)")
    return issues


def faq_questions(meta: dict) -> list[str]:
    raw = meta.get("sa_faq")
    try:
        data = json.loads(raw) if isinstance(raw, str) else (raw or [])
    except ValueError:
        return []
    return [str(i.get("q", "")) for i in data if isinstance(i, dict) and i.get("q")]


def analyze_page(snap: dict) -> dict:
    """Per-page facts (cross-page ratios are filled in by aggregate())."""
    meta = snap.get("meta") or {}
    name = county_name(snap.get("title", ""))
    body_html = re.sub(r"<h[1-6]\b[^>]*>.*?</h[1-6]>", "\n", snap.get("content", "") or "", flags=re.S)
    text = plain(body_html)
    try:
        from content_fix import NOTE_RE  # lazy: content_fix imports this module
    except ImportError:  # pragma: no cover
        NOTE_RE = None
    notes = NOTE_RE.findall(norm(text)) if NOTE_RE else []
    sents = sentences(text)
    normed = [norm(s) for s in sents]
    dups = [s for s, n in Counter(normed).items() if n > 1]
    facts = section_items(snap.get("content", ""), "حقایق")
    mnums = meta_numbers(meta)
    numeric_only = 0
    for f in facts:
        nums = numbers(f)
        if nums and all(n in mnums for n in nums) and len(WORD_RE.findall(f)) <= 18:
            numeric_only += 1
    gate = snap.get("index_gate") or {}
    pop = numbers(str(meta.get("sa_cty_population") or ""))
    return {
        "id": snap.get("id"),
        "slug": snap.get("slug"),
        "title": snap.get("title"),
        "index": bool(gate.get("index", True)),
        "population": int(pop[0]) if pop else 0,
        "words": len(WORD_RE.findall(text)),
        "sentences": len(sents),
        "dup_sentences": len(dups),
        "dup_examples": [d[:120] for d in dups[:2]],
        "facts": len(facts),
        "facts_numeric_only": numeric_only,
        "editorial_notes": len(notes),
        "note_examples": [str(n)[:60] for n in notes[:3]],
        "climate_conflict": climate_checks(meta, text),
        "impossible": impossible_checks(meta, text),
        "_skeletons": sorted({skeleton(s, name) for s in sents}),
        "_faq_skeletons": sorted({skeleton(q, name) for q in faq_questions(meta)}),
        "faq": len(faq_questions(meta)),
    }


def aggregate(pages: list[dict], template_min_pages: int = 20) -> dict:
    """Cross-page boilerplate ratios, a score, and the ordered fix queue."""
    sk_count = Counter(sk for p in pages for sk in p["_skeletons"])
    faq_count = Counter(sk for p in pages for sk in p["_faq_skeletons"])
    faq_cut = max(10, len(pages) // 2)
    for p in pages:
        n = len(p["_skeletons"]) or 1
        p["template_ratio"] = round(sum(1 for sk in p["_skeletons"] if sk_count[sk] >= template_min_pages) / n, 2)
        nf = len(p["_faq_skeletons"]) or 1
        p["faq_template_ratio"] = round(sum(1 for sk in p["_faq_skeletons"] if faq_count[sk] >= faq_cut) / nf, 2) if p["faq"] else 0.0
        score = (p["dup_sentences"] * 2 + p["template_ratio"] * 10 + p["faq_template_ratio"] * 3
                 + (3 if p["facts"] and p["facts_numeric_only"] >= max(2, p["facts"] // 2) else 0)
                 + 5 * len(p["climate_conflict"]) + 5 * len(p["impossible"])
                 + 2 * min(p.get("editorial_notes", 0), 10))
        p["score"] = round(score, 1)
    top_templates = [{"pages": c, "skeleton": sk[:140]} for sk, c in sk_count.most_common(15)]
    for p in pages:
        p.pop("_skeletons", None)
        p.pop("_faq_skeletons", None)
    flagged = [p for p in pages if p["score"] >= 3]
    queue = sorted(flagged, key=lambda p: (not p["index"], -p["population"], -p["score"]))
    summary = {
        "pages": len(pages),
        "flagged": len(flagged),
        "dup_sentences_pages": sum(1 for p in pages if p["dup_sentences"]),
        "template_ratio_ge_50": sum(1 for p in pages if p["template_ratio"] >= 0.5),
        "facts_numeric_only_pages": sum(1 for p in pages if p["facts"] and p["facts_numeric_only"] >= max(2, p["facts"] // 2)),
        "climate_conflict_pages": sum(1 for p in pages if p["climate_conflict"]),
        "impossible_pages": sum(1 for p in pages if p["impossible"]),
        "editorial_notes_pages": sum(1 for p in pages if p.get("editorial_notes")),
        "editorial_notes_total": sum(p.get("editorial_notes", 0) for p in pages),
        "faq_template_ge_50": sum(1 for p in pages if p["faq_template_ratio"] >= 0.5),
    }
    return {"summary": summary, "top_templates": top_templates, "queue": queue}
