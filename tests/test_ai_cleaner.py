"""تست‌های ai_cleaner — هر ۹ تستِ خواسته‌شدهٔ مالک + قفل‌های ایمنی."""
from __future__ import annotations

from pathlib import Path

import pytest

from ai_cleaner.batch import discover_files, make_batches, process_files
from ai_cleaner.cleaner import clean_text, process_document
from ai_cleaner.patterns import load_patterns
from ai_cleaner.sources import clean_sources

PATTERNS = load_patterns()


# ۱ ---------------------------------------------------------------------
def test_1_intro_sentence_removed_facts_kept():
    src = (
        "شهرستان کوهسرخ با مرکزیت شهر ریوش در استان خراسان رضوی قرار دارد. "
        "این مقاله مشخصات اداری، تاریخ، فرهنگ، زبان، صنایع و جاذبه‌های شهرستان را با رویکرد مستند معرفی می‌کند. "
        "ریوش مرکز اداری شهرستان است."
    )
    out = clean_text(src, PATTERNS).text
    assert out == (
        "شهرستان کوهسرخ با مرکزیت شهر ریوش در استان خراسان رضوی قرار دارد. "
        "ریوش مرکز اداری شهرستان است."
    )


# ۲ ---------------------------------------------------------------------
def test_2_process_sentence_fully_removed():
    src = "داده‌های عددی پیش از انتشار نهایی باید با مرکز آمار، وزارت کشور و منابع رسمی استان تطبیق داده شوند."
    # clean_text لایهٔ جمله‌ای است؛ قاعدهٔ ۲۰٪ در process_document (سطح مقاله) اعمال می‌شود.
    assert clean_text(src, PATTERNS).text == ""


# ۳ ---------------------------------------------------------------------
def test_3_arabic_letters_and_no_zwnj_also_matched():
    # همان جملهٔ تست ۲ با ي/ك عربی و بدون نیم‌فاصله
    src = "داده‌هاي عددي پيش از انتشار نهايي بايد با مركز آمار، وزارت كشور و منابع رسمي استان تطبيق داده شوند."
    assert clean_text(src, PATTERNS).text == ""


# ۴ ---------------------------------------------------------------------
def test_4_no_pattern_byte_identical(tmp_path):
    src = "# عنوان مقاله\n\nشهرستان کوهسرخ در استان خراسان رضوی قرار دارد.\nمرکز آن شهر ریوش است.\n"
    result = process_document(src, PATTERNS, file="a.md")
    assert result.text.encode("utf-8") == src.encode("utf-8")


# ۵ ---------------------------------------------------------------------
def test_5_idempotent_on_test1_output():
    once = clean_text(
        "شهرستان کوهسرخ با مرکزیت شهر ریوش در استان خراسان رضوی قرار دارد. "
        "این مقاله مشخصات اداری، تاریخ، فرهنگ، زبان، صنایع و جاذبه‌های شهرستان را با رویکرد مستند معرفی می‌کند. "
        "ریوش مرکز اداری شهرستان است.",
        PATTERNS,
    ).text
    twice = clean_text(once, PATTERNS)
    assert twice.text == once
    assert twice.deleted_sentences == 0


# ۶ ---------------------------------------------------------------------
def test_6_sources_bad_removed_and_renumbered():
    src = (
        "متن مقاله این‌جا است [1] و این بخش هم ارجاع دارد [3].\n\n"
        "## منابع\n"
        "1. [ویکی‌پدیای فارسی](https://fa.wikipedia.org/wiki/کوهسرخ)\n"
        "2. [گوگل](https://google.com/search?q=kuhsorkh)\n"
        "3. [فرمانداری کوهسرخ](https://kuhsorkh.khorasan.ir/)\n"
    )
    out = clean_sources(src).text
    items = [ln for ln in out.splitlines() if ln.strip()[:2] in ("1.", "2.", "3.")]
    assert len(items) == 2
    assert items[0].startswith("1.") and "fa.wikipedia.org" in items[0]
    assert items[1].startswith("2.") and "kuhsorkh.khorasan.ir" in items[1]
    assert "google.com" not in out
    assert "[2]" in out  # ارجاع [3] به [2] تبدیل شده


# ۷ ---------------------------------------------------------------------
def test_7_code_block_and_front_matter_untouched():
    src = (
        "---\n"
        "title: نمونه\n"
        "note: این مقاله چیزی را با رویکرد مستند معرفی می‌کند\n"
        "---\n\n"
        "متن سالم مقاله این‌جا است.\n\n"
        "```text\n"
        "این مقاله چیزی را با رویکرد مستند معرفی می‌کند. TODO\n"
        "```\n"
    )
    result = process_document(src, PATTERNS, file="a.md")
    assert result.text == src


# ۸ ---------------------------------------------------------------------
def test_8_file_count_unchanged_after_batch(tmp_path):
    (tmp_path / "city").mkdir()
    for i in range(3):
        (tmp_path / "city" / f"a{i}.md").write_text(
            "متن سالم شهرستان است. این مقاله مشخصات شهرستان را با رویکرد مستند معرفی می‌کند.\n",
            encoding="utf-8",
        )
    before = discover_files(tmp_path, "city")
    process_files(tmp_path, before, PATTERNS, set(), batch_no=1, write=True)
    after = discover_files(tmp_path, "city")
    assert len(before) == len(after) == 3
    assert before == after


# ۹ ---------------------------------------------------------------------
def test_9_heading_never_deleted():
    src = "# این مقاله شهرستان را با رویکرد مستند معرفی می‌کند\n\nمتن سالم شهرستان است.\n"
    result = process_document(src, PATTERNS, file="a.md")
    assert result.text.splitlines()[0] == "# این مقاله شهرستان را با رویکرد مستند معرفی می‌کند"


# --- تست‌های اضافهٔ ایمنی -------------------------------------------------
def test_flag_patterns_never_delete():
    src = "در این مقاله به بررسی جاذبه‌های شهرستان می‌پردازیم. شهرستان کوهسرخ در خراسان رضوی است."
    result = clean_text(src, PATTERNS)
    assert result.text == src
    assert result.flagged >= 1


def test_manual_review_guard_over_20_percent():
    # تقریباً همهٔ متن جملهٔ حذفی است، پس مقاله دست نمی‌خورد و «بررسی دستی» می‌شود.
    bad = "این مقاله مشخصات شهرستان را با رویکرد مستند معرفی می‌کند. "
    src = bad * 4 + "ریوش مرکز شهرستان است."
    result = process_document(src, PATTERNS, file="big.md")
    assert result.text == src
    assert result.manual_review is True
    assert all(f.action == "manual-review" for f in result.findings if f.level == "FLAG")


def test_utm_source_chatgpt_cleaned_link_kept():
    src = "ببینید [این پیوند](https://example.ir/page?utm_source=chatgpt.com&id=5) را."
    out = clean_sources(src).text
    assert "utm_source=chatgpt.com" not in out
    assert "https://example.ir/page?id=5" in out or "https://example.ir/page?" in out


def test_sentence_with_link_is_flagged_not_deleted():
    src = "این مقاله شهرستان را با رویکرد مستند در [این پیوند](https://example.ir/) معرفی می‌کند. متن سالم است."
    result = clean_text(src, PATTERNS)
    assert result.text == src
    assert any(f.action == "flagged" for f in result.findings)


def test_html_comment_block_untouched():
    src = "<!-- این مقاله چیزی را با رویکرد مستند معرفی می‌کند -->\n\nمتن سالم شهرستان است.\n"
    assert process_document(src, PATTERNS, file="a.md").text == src


def test_batches_are_alphabetical_20s():
    files = [f"city/{i:03d}.md" for i in range(45)]
    batches = make_batches(files, 20)
    assert [len(b) for b in batches] == [20, 20, 5]
    assert batches[0] == sorted(batches[0])
