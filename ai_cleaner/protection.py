"""Shared protected document regions."""
import re

_FRONT_MATTER_RE = re.compile(r"\A---[ \t]*\r?\n.*?\r?\n---[ \t]*(?:\r?\n|\Z)", re.DOTALL)
_CODE_FENCE_RE = re.compile(r"(```.*?```|~~~.*?~~~|<!--.*?-->)", re.DOTALL)


def split_protected(text: str) -> list[tuple[str, bool]]:
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

