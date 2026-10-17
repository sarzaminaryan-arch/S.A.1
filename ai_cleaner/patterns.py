"""بارگذاری patterns.yaml بدون وابستگی بیرونی.

قالب پشتیبانی‌شده دقیقاً همان قالبی است که در patterns.yaml این بسته هست:
یک کلید ``patterns:`` و فهرست آیتم‌هایی با id / regex / level / description.
اگر PyYAML نصب باشد از آن استفاده می‌شود؛ وگرنه parser سادهٔ داخلی همین قالب
را می‌خواند تا ابزار روی Python 3.11+ خالص هم کار کند.
"""
from __future__ import annotations

import re
from dataclasses import dataclass
from pathlib import Path

from .normalize import normalize_for_match

LEVELS = ("DELETE", "FLAG")


def compile_pattern(regex: str) -> re.Pattern[str]:
    """regex الگو را برای متن نرمال‌شده آماده می‌کند.

    فاصله‌های داخل الگو به «فاصله/نیم‌فاصله/هیچ» تبدیل می‌شوند تا مثلاً
    «می کند» و «می‌کند» و «میکند» هر سه با یک الگو گرفته شوند.
    """
    normalized = normalize_for_match(regex)
    # فاصلهٔ لفظی در الگو = هر ترکیب فاصله و نیم‌فاصله، یا هیچ‌کدام.
    # نیم‌فاصله در normalize حذف شده، پس فقط جای فاصله‌های باقی‌مانده را عوض می‌کنیم.
    flexible = normalized.replace(" ", r"[\s\u200c]*")
    return re.compile(flexible, re.IGNORECASE)


@dataclass(frozen=True)
class Pattern:
    id: str
    regex: str
    level: str  # DELETE | FLAG
    description: str = ""

    @property
    def compiled(self) -> re.Pattern[str] | None:
        if not self.regex:
            return None
        return compile_pattern(self.regex)


def _load_with_yaml(text: str) -> list[Pattern] | None:
    try:
        import yaml  # type: ignore
    except Exception:
        return None
    data = yaml.safe_load(text) or {}
    items = data.get("patterns") or []
    return [
        Pattern(
            id=str(it["id"]),
            regex=str(it.get("regex") or ""),
            level=str(it.get("level") or "FLAG").upper(),
            description=str(it.get("description") or ""),
        )
        for it in items
    ]


def _unquote(value: str) -> str:
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
        inner = value[1:-1]
        if value[0] == "'":
            return inner.replace("''", "'")
        return bytes(inner, "utf-8").decode("unicode_escape") if "\\" in inner else inner
    return value


def _load_simple(text: str) -> list[Pattern]:
    patterns: list[Pattern] = []
    current: dict[str, str] | None = None
    for raw in text.splitlines():
        line = raw.rstrip()
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or stripped == "patterns:":
            continue
        if stripped.startswith("- "):
            if current:
                patterns.append(_to_pattern(current))
            current = {}
            stripped = stripped[2:].strip()
            if not stripped:
                continue
        if current is None:
            continue
        m = re.match(r"^(id|regex|level|description)\s*:\s*(.*)$", stripped)
        if m:
            current[m.group(1)] = _unquote(m.group(2))
    if current:
        patterns.append(_to_pattern(current))
    return patterns


def _to_pattern(item: dict[str, str]) -> Pattern:
    level = (item.get("level") or "FLAG").upper()
    if level not in LEVELS:
        raise ValueError(f"سطح نامعتبر برای الگوی {item.get('id')!r}: {level!r} (DELETE یا FLAG)")
    if not item.get("id"):
        raise ValueError("الگوی بدون id در patterns.yaml")
    return Pattern(
        id=item["id"],
        regex=item.get("regex") or "",
        level=level,
        description=item.get("description") or "",
    )


def load_patterns(path: str | Path | None = None) -> list[Pattern]:
    if path is None:
        path = Path(__file__).with_name("patterns.yaml")
    text = Path(path).read_text(encoding="utf-8")
    patterns = _load_with_yaml(text)
    if patterns is None:
        patterns = _load_simple(text)
    seen: set[str] = set()
    for p in patterns:
        if p.id in seen:
            raise ValueError(f"id تکراری در patterns.yaml: {p.id}")
        seen.add(p.id)
        if p.regex:
            compile_pattern(p.regex)  # خطای regex همین‌جا معلوم شود، نه وسط دسته
    return patterns
