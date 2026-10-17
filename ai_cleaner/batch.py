"""دسته‌بندی الفبایی، progress.json و پردازش یک دسته (بدون انتشار)."""
from __future__ import annotations

import json
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path

from .cleaner import Finding, collect_duplicate_sentences, process_document
from .patterns import Pattern

EXTENSIONS = {".md", ".html", ".txt"}
EXCLUDED_DIRS = {".git", "reports", "ai_cleaner", "__pycache__", "node_modules", ".pytest_cache"}
BATCH_SIZE = 20
DEFAULT_CONTENT_DIR = "city"


@dataclass
class BatchResult:
    batch_no: int
    files: list[str] = field(default_factory=list)
    changed_files: list[str] = field(default_factory=list)
    findings: list[Finding] = field(default_factory=list)
    diffs: dict[str, tuple[str, str]] = field(default_factory=dict)
    scanned: int = 0
    changed: int = 0
    deleted_sentences: int = 0
    flagged: int = 0
    manual_review_files: int = 0


def discover_files(root: Path, content_dir: str | Path = DEFAULT_CONTENT_DIR) -> list[str]:
    """فایل‌های .md/.html/.txt زیر پوشهٔ مقاله‌ها، مرتب الفبایی بر اساس مسیر."""
    base = (root / content_dir).resolve() if not Path(content_dir).is_absolute() else Path(content_dir)
    if base.is_file():
        return [str(base.relative_to(root))] if base.suffix.lower() in EXTENSIONS else []
    if not base.exists():
        return []
    found: list[str] = []
    for path in base.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in EXTENSIONS:
            continue
        rel_parts = path.relative_to(base).parts
        if any(part in EXCLUDED_DIRS for part in rel_parts):
            continue
        found.append(str(path.relative_to(root)) if path.is_relative_to(root) else str(path))
    return sorted(found)


def discover_with_fallback(root: Path, content_dir: str) -> tuple[list[str], str | None]:
    """اگر پوشهٔ مقاله‌ها نبود، برای scan فقط: کل مخزن (به‌جز ابزار و گزارش‌ها).

    برای next از این fallback استفاده نمی‌شود؛ نبود پوشه یعنی «مقاله‌ای برای اصلاح نیست».
    """
    files = discover_files(root, content_dir)
    if files or (root / content_dir).exists():
        return files, None
    warning = (
        f"پوشهٔ «{content_dir}» در این چک‌اوت نیست؛ scan اطلاعاتی روی کل مخزن اجرا می‌شود. "
        "مقاله‌های شهرستان (city) در وردپرس‌اند و فایل .md آن‌ها در مخزن نیست."
    )
    all_files = discover_files(root, ".")
    # خود ابزار، تست‌ها و فایل الگوها مقاله نیستند
    all_files = [
        f for f in all_files
        if not f.startswith(("ai_cleaner/", "tests/", "reports/"))
    ]
    return all_files, warning


def make_batches(files: list[str], size: int = BATCH_SIZE) -> list[list[str]]:
    return [files[i : i + size] for i in range(0, len(files), size)]


def load_progress(path: Path) -> dict:
    if path.exists():
        try:
            return json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            return {"version": 1, "completed_batches": [], "batches": {}}
    return {"version": 1, "completed_batches": [], "batches": {}}


def save_progress(path: Path, data: dict) -> None:
    data["updated_at"] = datetime.now(timezone.utc).isoformat()
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def compute_duplicates(root: Path, files: list[str]) -> set[str]:
    texts: dict[str, str] = {}
    for rel in files:
        try:
            texts[rel] = (root / rel).read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError):
            continue
    return collect_duplicate_sentences(texts)


def process_files(
    root: Path,
    files: list[str],
    patterns: list[Pattern],
    duplicate_keys: set[str],
    batch_no: int = 1,
    write: bool = True,
) -> BatchResult:
    """فایل‌های یک دسته را پردازش می‌کند. هیچ فایلی ساخته/حذف/تغییرنام نمی‌شود؛
    فقط همان فایل تغییرکرده درجا بازنویسی می‌شود."""
    result = BatchResult(batch_no=batch_no, files=list(files), scanned=len(files))
    manual_files: set[str] = set()
    for rel in files:
        path = root / rel
        try:
            before = path.read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError) as exc:
            print(f"هشدار: خواندن {rel} ممکن نشد: {exc}")
            continue
        doc = process_document(before, patterns, file=rel, duplicate_keys=duplicate_keys)
        result.findings.extend(doc.findings)
        if doc.manual_review:
            manual_files.add(rel)
        if doc.text != before:
            result.changed_files.append(rel)
            result.diffs[rel] = (before, doc.text)
            if write:
                path.write_text(doc.text, encoding="utf-8")
    result.changed = len(result.changed_files)
    result.deleted_sentences = sum(1 for f in result.findings if f.action == "deleted")
    result.flagged = sum(1 for f in result.findings if f.action == "flagged")
    result.manual_review_files = len(manual_files)
    return result
