"""موتور اصلی: تقسیم به جمله، تطبیق الگو، حذف «کل جمله».

قانون‌های سخت که این ماژول رعایت می‌کند:
- front matter، بلوک کد، تگ HTML، آدرس لینک و تیترها (#) و ردیف‌های جدول دست نمی‌خورند.
- اگر هیچ جملهٔ DELETE پیدا نشود، متن بایت‌به‌بایت همان ورودی برمی‌گردد.
- خروجی همیشه متن اصلی است منهای جمله‌های حذف‌شده؛ هیچ متن تازه‌ای ساخته نمی‌شود
  (نرمال‌سازی فقط برای پیدا کردن است).
- قاعدهٔ ۲۰٪ در سطح سند (process_document) اعمال می‌شود، نه در clean_text خام:
  اگر بیش از ۲۰٪ متن یک مقاله قرار باشد حذف شود، سند دست‌نخورده می‌ماند و
  با برچسب «بررسی دستی» گزارش می‌شود.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field

from .normalize import normalize_for_match
from .patterns import Pattern
from .sources import clean_sources

SENTENCE_END = ".!?؟؛"
_SENTENCE_RE = re.compile(r"[^.!?؟؛\n]+(?:[.!?؟؛]+)?")
_FRONT_MATTER_RE = re.compile(r"\A---[ \t]*\r?\n.*?\r?\n---[ \t]*(?:\r?\n|\Z)", re.DOTALL)
_CODE_FENCE_RE = re.compile(r"(```.*?```|~~~.*?~~~|<!--.*?-->)", re.DOTALL)
_INLINE_PROTECTED_RE = re.compile(
    r"(`[^`\n]*`"  # کد درون‌خطی
    r"|<[^>\n]+>"  # تگ HTML
    r"|\]\([^)\n]*\)"  # مقصد لینک مارک‌داون ](url)
    r"|https?://[^\s)\]\"'<>]+"  # آدرس خام
    r"|www\.[^\s)\]\"'<>]+)"
)
_LIST_MARKER_ONLY = re.compile(r"^\s*(?:[-*+]|\d+[.)]|[۰-۹]+[.)])\s*$")


@dataclass
class Finding:
    file: str
    title: str
    line: int
    pattern_id: str
    level: str  # DELETE | FLAG
    sentence: str
    action: str  # deleted | flagged | manual-review | source-deleted | utm-cleaned


@dataclass
class CleanResult:
    text: str
    findings: list[Finding] = field(default_factory=list)
    deleted_sentences: int = 0
    flagged: int = 0
    deleted_chars: int = 0
    total_chars: int = 0
    manual_review: bool = False


def extract_title(text: str, fallback: str = "") -> str:
    m = re.search(r"^title\s*:\s*[\"']?(.+?)[\"']?\s*$", text, re.MULTILINE)
    if m:
        return m.group(1).strip()
    m = re.search(r"^#\s+(.+)$", text, re.MULTILINE)
    if m:
        return re.sub(r"<[^>]+>", "", m.group(1)).strip()
    m = re.search(r"<(?:title|h1)[^>]*>([^<]+)</(?:title|h1)>", text, re.IGNORECASE)
    if m:
        return m.group(1).strip()
    return fallback


def _split_protected(text: str) -> list[tuple[str, bool]]:
    """[(بخش، محافظت‌شده؟)] — front matter و بلوک‌های کد محافظت‌شده‌اند."""
    parts: list[tuple[str, bool]] = []
    rest = text
    m = _FRONT_MATTER_RE.match(rest)
    if m:
        parts.append((m.group(0), True))
        rest = rest[m.end() :]
    for chunk in _CODE_FENCE_RE.split(rest):
        if not chunk:
            continue
        protected = (
            (chunk.startswith(("```", "~~~")) and chunk.rstrip().endswith(("```", "~~~")))
            or (chunk.startswith("<!--") and chunk.rstrip().endswith("-->"))
        )
        parts.append((chunk, protected))
    return parts


def iter_sentences(text: str):
    """(شروع، پایان، جمله) برای بخش‌های غیرمحافظت‌شدهٔ متن."""
    base = 0
    for segment, protected in _split_protected(text):
        if not protected:
            for chunk_base, chunk, chunk_protected in _split_inline(segment):
                if chunk_protected:
                    base += 0  # آفست در ادامه با طول بخش‌ها حساب می‌شود
                # آفست واقعی با جست‌وجوی ترتیبی در قطعهٔ بعدی محاسبه می‌شود؛
                # این تابع فقط برای شمارش تکرار است و به آفست دقیق نیاز ندارد.
                if not chunk_protected:
                    for m in _SENTENCE_RE.finditer(chunk):
                        yield m.group(0)
        base += len(segment)


def _split_inline(segment: str) -> list[tuple[int, str, bool]]:
    out: list[tuple[int, str, bool]] = []
    pos = 0
    for m in _INLINE_PROTECTED_RE.finditer(segment):
        if m.start() > pos:
            out.append((pos, segment[pos : m.start()], False))
        out.append((m.start(), m.group(0), True))
        pos = m.end()
    if pos < len(segment):
        out.append((pos, segment[pos:], False))
    return out


def _match_sentence(sentence: str, patterns: list[Pattern]) -> Pattern | None:
    normalized = normalize_for_match(sentence)
    for p in patterns:  # ترتیب فایل: الگوهای خاص‌تر زودتر آمده‌اند
        if p.level != "DELETE" or p.compiled is None:
            continue
        if p.compiled.search(normalized):
            return p
    return None


def _match_flag(sentence: str, patterns: list[Pattern]) -> Pattern | None:
    normalized = normalize_for_match(sentence)
    for p in patterns:
        if p.level != "FLAG" or p.compiled is None:
            continue
        if p.compiled.search(normalized):
            return p
    return None


def normalized_sentence_key(sentence: str) -> str:
    key = normalize_for_match(sentence).strip(" .!?؟؛\n\t")
    return re.sub(r"\s+", " ", key)


def collect_duplicate_sentences(texts_by_file: dict[str, str], min_files: int = 4) -> set[str]:
    """کلید نرمال جمله‌هایی که عیناً در بیش از ۳ مقاله آمده‌اند (فقط FLAG)."""
    seen: dict[str, set[str]] = {}
    for path, text in texts_by_file.items():
        keys = {normalized_sentence_key(s) for s in iter_sentences(text)}
        for key in keys:
            if len(key) >= 20:  # جمله‌های خیلی کوتاه (مثل «منابع») تکرار معنادار نیستند
                seen.setdefault(key, set()).add(path)
    return {k for k, files in seen.items() if len(files) >= min_files}


def _clean_segment(
    segment: str,
    seg_offset: int,
    full_text: str,
    file: str,
    title: str,
    patterns: list[Pattern],
    duplicate_keys: set[str],
    findings: list[Finding],
) -> tuple[str, int]:
    """یک بخش غیرمحافظت‌شده را پاک می‌کند؛ (متن تازه، شمار نویسه‌های حذف‌شده)."""
    # قطعه‌های درون‌خطی محافظت‌شده (تگ/کد/آدرس) جمله را نمی‌شکنند ولی دست هم نمی‌خورند:
    # پردازش جمله‌ای روی کل بخش انجام می‌شود و جمله‌ای که داخل تگ/کد است با چک خط محافظت می‌شود.
    deletions: list[tuple[int, int]] = []
    deleted_chars = 0
    inline_spans = [(s, s + len(t)) for s, t, prot in _split_inline(segment) if prot]

    def _inside_inline(start: int, end: int) -> bool:
        return any(start >= s and end <= e for s, e in inline_spans)

    def _overlaps_inline(start: int, end: int) -> bool:
        # جمله‌ای که لینک/کد/تگ دارد خودکار حذف نمی‌شود (حذفش لینک یا کد را می‌برد)؛ فقط FLAG
        return any(start < e and end > s for s, e in inline_spans)

    for m in _SENTENCE_RE.finditer(segment):
        s, e = m.start(), m.end()
        raw = m.group(0)
        sentence = raw.strip()
        if not sentence:
            continue
        if _inside_inline(s, e):
            continue
        # تیتر و ردیف جدول هرگز حذف نمی‌شوند
        line_start = segment.rfind("\n", 0, s) + 1
        line_end = segment.find("\n", s)
        line_text = segment[line_start : line_end if line_end != -1 else len(segment)]
        stripped_line = line_text.lstrip()
        if stripped_line.startswith("#") or stripped_line.startswith("|"):
            continue
        if re.search(r"<h[1-6][^>]*>[^<]*$", segment[:s]):
            continue
        line_no = full_text.count("\n", 0, seg_offset + s) + 1

        delete_pat = _match_sentence(sentence, patterns)
        if delete_pat is not None and _overlaps_inline(s, e):
            findings.append(Finding(file, title, line_no, delete_pat.id, "FLAG", sentence, "flagged"))
            continue
        if delete_pat is not None:
            findings.append(Finding(file, title, line_no, delete_pat.id, "DELETE", sentence, "deleted"))
            deletions.append((s, e))
            deleted_chars += len(sentence)
            continue
        flag_pat = _match_flag(sentence, patterns)
        if flag_pat is not None:
            findings.append(Finding(file, title, line_no, flag_pat.id, "FLAG", sentence, "flagged"))
        elif normalized_sentence_key(sentence) in duplicate_keys:
            findings.append(Finding(file, title, line_no, "duplicate-sentence", "FLAG", sentence, "flagged"))

    if not deletions:
        return segment, 0

    out: list[str] = []
    pos = 0
    for s, e in deletions:
        out.append(segment[pos:s])
        pos = e
    out.append(segment[pos:])
    cleaned = "".join(out)
    cleaned = _tidy_after_deletion(cleaned)
    return cleaned, deleted_chars


def _tidy_after_deletion(text: str) -> str:
    """فقط وقتی حذفی رخ داده: آیتم لیست خالی، پاراگراف خالی و خط خالی اضافه مرتب می‌شود."""
    lines = [ln for ln in text.split("\n") if not _LIST_MARKER_ONLY.match(ln)]
    text = "\n".join(lines)
    text = re.sub(r"[ \t]{2,}", " ", text)
    text = re.sub(r"[ \t]+\n", "\n", text)
    text = re.sub(r"\n[ \t]+", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text


def clean_text(
    text: str,
    patterns: list[Pattern],
    file: str = "",
    duplicate_keys: set[str] | None = None,
) -> CleanResult:
    """حذف جمله‌های DELETE از متن خام (بدون قاعدهٔ ۲۰٪ و بدون دست‌کاری منابع).

    اگر هیچ حذفی نباشد، متن بایت‌به‌بایت همان ورودی است.
    """
    title = extract_title(text, file)
    findings: list[Finding] = []
    duplicate_keys = duplicate_keys or set()
    out_parts: list[str] = []
    deleted_chars = 0
    offset = 0
    for segment, protected in _split_protected(text):
        if protected:
            out_parts.append(segment)
        else:
            cleaned, dchars = _clean_segment(
                segment, offset, text, file, title, patterns, duplicate_keys, findings
            )
            out_parts.append(cleaned)
            deleted_chars += dchars
        offset += len(segment)

    deleted = sum(1 for f in findings if f.action == "deleted")
    if deleted == 0:
        # قانون طلایی تست ۴: بدون حذف = بایت‌به‌بایت یکسان (حتی فاصله‌ها دست نمی‌خورند)
        return CleanResult(text, findings, 0, sum(1 for f in findings if f.level == "FLAG"),
                           0, len(text))
    return CleanResult(
        "".join(out_parts),
        findings,
        deleted,
        sum(1 for f in findings if f.level == "FLAG"),
        deleted_chars,
        len(text),
    )


def process_document(
    text: str,
    patterns: list[Pattern],
    file: str = "",
    duplicate_keys: set[str] | None = None,
) -> CleanResult:
    """مسیر کامل یک سند: جمله‌ها + منابع + قاعدهٔ ۲۰٪ (بررسی دستی)."""
    result = clean_text(text, patterns, file=file, duplicate_keys=duplicate_keys)
    new_text = result.text

    sources = clean_sources(new_text)
    for sf in sources.findings:
        result.findings.append(
            Finding(file, result.findings[0].title if result.findings else extract_title(text, file),
                    sf.line, sf.pattern_id,
                    "DELETE" if sf.action == "source-deleted" else "FLAG",
                    sf.sentence, sf.action)
        )
    if sources.text != new_text:
        new_text = sources.text
        result.deleted_chars += sources.removed_chars

    changed = new_text != text
    if not changed:
        result.text = text
        return result

    ratio = (result.deleted_chars / len(text)) if text else 0.0
    if ratio > 0.20:
        # بیش از ۲۰٪: مقاله دست نمی‌خورد، همهٔ یافته‌ها «بررسی دستی» می‌شوند.
        for f in result.findings:
            if f.action in ("deleted", "source-deleted"):
                f.action = "manual-review"
                f.level = "FLAG"
        result.manual_review = True
        result.text = text
        result.deleted_sentences = 0
        return result

    result.text = new_text
    result.deleted_sentences = sum(1 for f in result.findings if f.action == "deleted")
    result.flagged = sum(1 for f in result.findings if f.action == "flagged")
    return result
