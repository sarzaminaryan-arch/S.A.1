#!/usr/bin/env python3
"""Conservative editorial cleanup for Markdown articles.

Reports exact boilerplate across files, removes only standalone editor/process
sentences, exact repeated paragraphs within a single article, and google.com
entries in a sources section. Cross-article repeats are reported, not removed:
repetition may be factual or safety-critical in context.
"""
from __future__ import annotations

import argparse
import html
import re
import sys
from collections import defaultdict
from pathlib import Path

# These patterns are intentionally specific: a sentence must be about the
# editorial workflow, not merely contain a word such as «بررسی» or «منبع».
EDITOR_SENTENCE = re.compile(
    r"(?:این\s+مقاله(?=[^.!؟\n]*(?:بازبینی|ویرایش\s+انسانی|ویراستار|برای\s+انتشار))[^.!؟\n]+|"
    r"(?:داده‌ها|اطلاعات|آمار|منابع?)(?=[^.!؟\n]*(?:پیش\s+از\s+انتشار|تطبیق\s+داده\s+شو(?:د|ند)|بازبینی\s+شود|تأیید\s+شود|بررسی\s+شود))[^.!؟\n]+|"
    r"نیاز\s+به\s+بررسی|تأیید\s+شود|منبع\s+رسمی\s+بازبینی\s+شود|برای\s+ویراستار|یادداشت\s+ویراستار)"
)
EDITOR_MARKER = re.compile(
    r"(?:ویرایش\s+انسانی|ویراستار|پیش\s+از\s+انتشار|تطبیق\s+داده\s+شود|"
    r"تأیید\s+شود|نیاز\s+به\s+بررسی|برای\s+بازبینی|بازبینی\s+شود|"
    r"منبع\s+رسمی[^.!؟\n]{0,40}بازبینی\s+شود)"
)
SENTENCE_SPLIT = re.compile(r"(?<=[.!؟])\s+")
SOURCE_HEADING = re.compile(r"^\s{0,3}#{1,6}\s+.*(?:منابع|مآخذ|sources|references).*", re.I)
LIST_ITEM = re.compile(r"^\s*(?:[-*+]\s+|\d+[.)]\s+)")
NUMBERED_ITEM = re.compile(r"^(\s*)(\d+)([.)]\s+)(.*)$")
GOOGLE_HOST = re.compile(r"(?i)(?:https?://)?(?:www\.)?google\.com(?:[:/\s?#]|$)")


def _is_prose_paragraph(block: str) -> bool:
    lines = block.splitlines()
    if not lines or any(not line.strip() for line in lines):
        return False
    joined = " ".join(line.strip() for line in lines)
    return (
        not joined.startswith(("#", ">", "|", "```", "~~~", "<!--", ":::"))
        and not LIST_ITEM.match(joined)
        and not re.match(r"^\*\*[^*]+:\*\*", joined)
    )


def _normalize(block: str) -> str:
    return re.sub(r"\s+", " ", block).strip()


def clean_markdown(text: str) -> tuple[str, list[str]]:
    """Apply only high-confidence, structure-preserving edits."""
    final_newline = text.endswith("\n")
    blocks = re.split(r"(\n\s*\n)", text)
    notes: list[str] = []
    seen_paragraphs: set[str] = set()
    in_sources = False
    output: list[str] = []

    for block in blocks:
        if not block or re.fullmatch(r"\n\s*\n", block):
            output.append(block)
            continue
        if SOURCE_HEADING.match(block.strip()):
            in_sources = True
            output.append(block)
            continue
        if re.match(r"^\s{0,3}#{1,6}\s+", block):
            in_sources = False

        lines = block.splitlines()
        if in_sources and any(GOOGLE_HOST.search(line) for line in lines):
            kept = [line for line in lines if not GOOGLE_HOST.search(line)]
            removed = len(lines) - len(kept)
            if removed:
                notes.append(f"حذف {removed} مدخل google.com از منابع")
                # Renumber a consecutively numbered reference list after removal.
                n = 1
                for idx, line in enumerate(kept):
                    match = NUMBERED_ITEM.match(line)
                    if match:
                        kept[idx] = f"{match.group(1)}{n}{match.group(3)}{match.group(4)}"
                        n += 1
                block = "\n".join(kept)
                if not block.strip():
                    continue
                lines = block.splitlines()

        # Remove a meta sentence only when it stands alone as a whole sentence;
        # mixed factual/editorial sentences are retained for human review.
        if _is_prose_paragraph(block):
            raw = " ".join(line.strip() for line in lines)
            plain = html.unescape(re.sub(r"<[^>]+>", " ", raw))
            sentences = SENTENCE_SPLIT.split(plain)
            candidates = [s for s in sentences if EDITOR_MARKER.search(s)]
            standalone = [s for s in sentences if EDITOR_SENTENCE.fullmatch(s.strip().rstrip(".!؟"))]
            if standalone and len(standalone) == len(sentences):
                notes.append("حذف جملهٔ مستقل خطاب به ویراستار/فرایند نگارش")
                continue
            if candidates:
                # Keep factual or mixed sentences untouched; report that the
                # note needs human editing rather than risk deleting facts.
                notes.append("یادداشت فرایندی در جملهٔ آمیخته باقی ماند تا دستی بازنویسی شود")

            key = _normalize(block)
            if len(key.split()) >= 8:
                if key in seen_paragraphs:
                    notes.append("حذف بند تکراری در همین مقاله")
                    continue
                seen_paragraphs.add(key)

        output.append(block)

    result = "".join(output)
    if final_newline and not result.endswith("\n"):
        result += "\n"
    return result, notes


PHP_CONTENT = re.compile(
    r"(?m)^(?P<prefix>[ \t]*['\"]content['\"]\s*=>\s*<<<['\"](?P<tag>[A-Za-z_][A-Za-z0-9_]*)['\"]\s*\r?\n)"
    r"(?P<body>.*?)(?P<suffix>^\s*(?P=tag),?\s*$)",
    re.S,
)

def clean_php_content(text: str) -> tuple[str, list[str]]:
    """Clean only PHP article-content heredocs; leave metadata and code intact."""
    notes: list[str] = []

    def replace(match: re.Match[str]) -> str:
        body, found = clean_markdown(match.group("body"))
        notes.extend(found)
        return match.group("prefix") + body + match.group("suffix")

    return PHP_CONTENT.sub(replace, text), notes


def article_texts(path: Path, text: str) -> list[str]:
    if path.suffix.lower() == ".php":
        return [match.group("body") for match in PHP_CONTENT.finditer(text)]
    return [text]


def sentence_units(article_text: str, is_php: bool) -> list[str]:
    if is_php:
        blocks = re.findall(r"<(?:p|li)\b[^>]*>.*?</(?:p|li)>", article_text, flags=re.I | re.S)
    else:
        blocks = [block for block in re.split(r"\n\s*\n", article_text) if _is_prose_paragraph(block)]
    found: list[str] = []
    for block in blocks:
        plain = html.unescape(re.sub(r"<[^>]+>", " ", block))
        if "http://" in plain or "https://" in plain:
            continue
        for sentence in re.split(r"(?<=[.!؟])\s+", plain):
            normalized = _normalize(sentence.strip(" 	\r\n-•"))
            if len(normalized.split()) >= 8:
                found.append(normalized)
    return found


def collect(paths: list[str]) -> list[Path]:
    found: list[Path] = []
    for raw in paths:
        path = Path(raw)
        if path.is_dir():
            found.extend(p for p in sorted(path.rglob("*")) if p.is_file() and p.suffix.lower() in (".md", ".php"))
        elif path.is_file() and path.suffix.lower() in (".md", ".php"):
            found.append(path)
    return sorted(set(found))


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("paths", nargs="+", help="Markdown article files/folders or PHP files with content heredocs")
    parser.add_argument("--fix", action="store_true", help="write safe fixes; default is report-only")
    args = parser.parse_args(argv)
    files = collect(args.paths)
    if not files:
        parser.error("هیچ فایل Markdown/PHP پیدا نشد")

    cross: dict[str, list[str]] = defaultdict(list)
    cross_sentences: dict[str, list[str]] = defaultdict(list)
    changed = 0
    for path in files:
        original = path.read_text(encoding="utf-8")
        if path.suffix.lower() == ".php":
            cleaned, notes = clean_php_content(original)
        else:
            cleaned, notes = clean_markdown(original)
        # Gather exact repeats across separate article bodies for review only.
        bodies = article_texts(path, original)
        for article_no, article_text in enumerate(bodies, 1):
            article_label = str(path) if path.suffix.lower() != ".php" else f"{path}#content-{article_no}"
            for block in re.split(r"\n\s*\n", article_text):
                if _is_prose_paragraph(block):
                    normalized = _normalize(block)
                    if len(normalized.split()) >= 8:
                        cross[normalized].append(article_label)
            for sentence in sentence_units(article_text, path.suffix.lower() == ".php"):
                cross_sentences[sentence].append(article_label)
        if cleaned != original:
            changed += 1
            if args.fix:
                path.write_text(cleaned, encoding="utf-8")
            print(f"{'اصلاح' if args.fix else 'پیشنهاد'}: {path}")
            for note in notes:
                print(f"  - {note}")

    repeated = [(text, sorted(set(paths))) for text, paths in cross.items() if len(set(paths)) > 1]
    repeated_sentences = [(text, sorted(set(paths))) for text, paths in cross_sentences.items() if len(set(paths)) > 1]
    if repeated:
        print("\nبندهای عیناً مشترک میان مقاله‌ها (فقط برای بازبینی؛ خودکار حذف نمی‌شوند):")
        for text, paths in sorted(repeated, key=lambda row: (-len(row[1]), row[0]))[:25]:
            sample = ", ".join(str(p) for p in paths[:5])
            suffix = f" و {len(paths) - 5} فایل دیگر" if len(paths) > 5 else ""
            print(f"  - {len(paths)} فایل: {sample}{suffix}\n    {text[:240]}")
        if len(repeated) > 25:
            print(f"  … {len(repeated) - 25} مورد دیگر نیز در پیش‌نمایش نمایش داده نشد.")
    if repeated_sentences:
        print("\nجمله‌های عیناً مشترک میان مقاله‌ها (فقط برای بازبینی؛ خودکار حذف نمی‌شوند):")
        for sentence, labels in sorted(repeated_sentences, key=lambda row: (-len(row[1]), row[0]))[:25]:
            sample = ", ".join(labels[:5])
            suffix = f" و {len(labels) - 5} مقالهٔ دیگر" if len(labels) > 5 else ""
            print(f"  - {len(labels)} مقاله: {sample}{suffix}\n    {sentence[:240]}")
    print(f"\nبررسی شد: {len(files)} فایل؛ {'اصلاح شد' if args.fix else 'دارای پیشنهاد'}: {changed}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
