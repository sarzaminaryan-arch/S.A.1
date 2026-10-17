"""منابع: حذف منبعِ صرفاً google/chatgpt، پاک‌کردن utm_source=chatgpt.com
و مرتب‌کردن شماره‌گذاری منابع و ارجاع‌های داخل متن.

منبع معتبر هرگز حذف نمی‌شود: اگر آیتمی حتی یک لینک معتبر (مثل fa.wikipedia.org
یا هر سایت .ir) داشته باشد، کل آیتم می‌ماند.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field

from .normalize import normalize_for_match, parse_number, to_digit_script

BAD_HOSTS = {"google.com", "www.google.com", "chatgpt.com", "www.chatgpt.com",
             "chat.openai.com", "openai.com"}
# دامنه‌هایی که به‌تنهایی نشانهٔ «منبع صرفاً بد» نیستند ولی معتبر هم محسوب نمی‌شوند
# این‌جا لازم نیست؛ قاعدهٔ حذف فقط وقتی است که *همهٔ* دامنه‌های آیتم بد باشند.

_URL_RE = re.compile(r"https?://([^\s/)\]\"'<>]+)", re.IGNORECASE)
_BARE_DOMAIN_RE = re.compile(
    r"(?<![\w.-])((?:www\.)?[a-zA-Z0-9-]+\.(?:com|org|net|ir|io|info|gov|edu)(?:\.[a-zA-Z]{2})?)",
    re.IGNORECASE,
)


@dataclass
class SourceFinding:
    line: int
    pattern_id: str
    sentence: str
    action: str  # source-deleted | utm-cleaned


@dataclass
class SourcesResult:
    text: str
    findings: list[SourceFinding] = field(default_factory=list)
    removed_chars: int = 0


def _hosts_in(text: str) -> list[str]:
    hosts = [m.group(1).lower() for m in _URL_RE.finditer(text)]
    if not hosts:
        hosts = [m.group(1).lower() for m in _BARE_DOMAIN_RE.finditer(text)]
    return hosts


def _is_trusted(host: str) -> bool:
    return (
        host.endswith(".ir")
        or host == "fa.wikipedia.org"
        or host.endswith(".wikipedia.org")
        or host.endswith(".gov")
        or host.endswith(".gov.ir")
    )


def _is_bad_only(hosts: list[str]) -> bool:
    return bool(hosts) and all(h in BAD_HOSTS for h in hosts) and not any(
        _is_trusted(h) for h in hosts
    )


def clean_utm(text: str) -> tuple[str, int]:
    """utm_source=chatgpt.com را از لینک‌ها پاک می‌کند؛ خود لینک می‌ماند."""
    count = 0

    def _sub(pattern: str, repl: str, s: str) -> str:
        nonlocal count
        s2, n = re.subn(pattern, repl, s)
        count += n
        return s2

    text = _sub(r"([?&])utm_source=chatgpt\.com&", r"\1", text)
    text = _sub(r"&utm_source=chatgpt\.com", "", text)
    text = _sub(r"\?utm_source=chatgpt\.com(?=[\s)\]\"'#]|$)", "", text)
    return text, count


_SOURCES_HEADING = re.compile(
    r"^(?:#{1,6}\s*(?:منابع(?: و مراجع)?|مراجع|Sources|References)\s*$"
    r"|\*\*(?:منابع(?: و مراجع)?|مراجع)\*\*\s*$"
    r"|<h[1-6][^>]*>\s*(?:منابع(?: و مراجع)?|مراجع|Sources|References)\s*</h[1-6]>\s*)$",
    re.IGNORECASE,
)
_HEADING = re.compile(r"^(?:#{1,6}\s|<h[1-6][\s>])")
_LIST_ITEM = re.compile(
    r"^(\s*(?:[-*+]\s+|(\[?)(\d+|[۰-۹]+|[٠-٩]+)(\]?[.)]\s+)))(.*)$"
)


def clean_sources(text: str) -> SourcesResult:
    """منابع بد را حذف و شماره‌ها و ارجاع‌ها را مرتب می‌کند.

    اگر هیچ تغییری لازم نباشد، همان متن ورودی (بایت‌به‌بایت) برمی‌گردد.
    """
    findings: list[SourceFinding] = []
    removed_chars = 0

    new_text, utm_count = clean_utm(text)
    if utm_count:
        # خطِ اولین رخداد برای گزارش
        line_no = 1
        for i, line in enumerate(text.splitlines(), start=1):
            if "utm_source=chatgpt.com" in line:
                line_no = i
                break
        findings.append(SourceFinding(line_no, "utm-source-chatgpt", "utm_source=chatgpt.com", "utm-cleaned"))
    text = new_text

    lines = text.split("\n")
    # پیدا کردن بخش منابع (آخرین بخشی که با تیتر منابع شروع می‌شود)
    start = None
    for i, line in enumerate(lines):
        if _SOURCES_HEADING.match(line.strip()):
            start = i
    if start is None:
        return SourcesResult(text, findings, removed_chars)

    end = len(lines)
    for j in range(start + 1, len(lines)):
        if _HEADING.match(lines[j].strip()):
            end = j
            break

    section = lines[start + 1 : end]
    kept: list[str] = []
    old_to_new: dict[int, int] = {}
    removed_numbers: set[int] = set()
    next_number = 1
    changed = bool(utm_count)

    for offset, line in enumerate(section):
        m = _LIST_ITEM.match(line)
        if not m:
            kept.append(line)
            continue
        body = m.group(5)
        hosts = _hosts_in(line)
        if _is_bad_only(hosts):
            findings.append(
                SourceFinding(start + 2 + offset, "bad-source-only", line.strip(), "source-deleted")
            )
            removed_chars += len(line)
            changed = True
            old_no = parse_number(m.group(3)) if m.group(3) else None
            if old_no is not None:
                removed_numbers.add(old_no)
            continue
        # شماره‌گذاری دوبارهٔ آیتم‌های شماره‌دار
        if m.group(3):
            old_no = parse_number(m.group(3))
            if old_no is not None:
                old_to_new[old_no] = next_number
                if old_no != next_number:
                    changed = True
                digit_sample = m.group(3)
                new_no = to_digit_script(next_number, digit_sample)
                prefix = m.group(1)
                # فقط بخش عدد داخل پیشوند عوض می‌شود، قالب ([۱] / ۱. / ۱)) می‌ماند
                new_prefix = prefix.replace(digit_sample, new_no, 1)
                line = new_prefix + body
            next_number += 1
        kept.append(line)

    if not changed and not findings:
        return SourcesResult(text, findings, removed_chars)

    def _fix_citation(match: re.Match[str]) -> str:
        inner = match.group(1)
        parts = re.split(r"([,،])", inner)
        out: list[str] = []
        any_mapped = False
        for part in parts:
            token = part.strip()
            num = parse_number(token) if token and token not in ",،" else None
            if num is None:
                out.append(part)
                continue
            any_mapped = True
            if num in removed_numbers:
                continue  # ارجاع به منبع حذف‌شده می‌افتد، جمله نمی‌افتد
            if num in old_to_new:
                sample = token
                out.append(to_digit_script(old_to_new[num], sample))
            else:
                out.append(part)
        joined = "".join(out).strip(" ,،")
        if not joined:
            return ""
        return f"[{joined}]"

    # بازسازی با حفظ ترتیب اصلی و اصلاح ارجاع‌های [n] فقط بیرونِ بخش منابع
    before = "\n".join(lines[: start + 1])
    after = "\n".join(lines[end:])
    rebuilt_before = re.sub(
        r"\[([0-9۰-۹٠-٩][0-9۰-۹٠-٩\s,،]*)\]", _fix_citation, before
    ) if (old_to_new or removed_numbers) else before
    rebuilt_after = re.sub(
        r"\[([0-9۰-۹٠-٩][0-9۰-۹٠-٩\s,،]*)\]", _fix_citation, after
    ) if (old_to_new or removed_numbers) else after
    parts = [rebuilt_before, *kept]
    if rebuilt_after or end < len(lines):
        parts.append(rebuilt_after)
    result = "\n".join(parts)
    return SourcesResult(result, findings, removed_chars)
