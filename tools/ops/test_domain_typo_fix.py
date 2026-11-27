"""تست آفلاین سازندهٔ SQL اصلاح تایپوی دامنه (`tools/ops/domain_typo_fix.py`).

هدف: قفل کردن دو ویژگی که ایمنی این اصلاح به آن‌ها بند است:
  ۱. جایگزینی هم‌طول (۱۳ نویسه ← ۱۳ نویسه) تا مقدارهای سریالایز نشکنند.
  ۲. همهٔ UPDATE‌ها با WHERE محدود باشند و گام پشتیبان/راستی‌آزمایی وجود داشته باشد.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import domain_typo_fix as dtf  # noqa: E402

SQL = dtf.build_sql()


def test_replacement_is_same_length():
    """اگر طول‌ها برابر نباشند، `s:25:"…"` داخل سریالایزها می‌شکند."""
    assert len(dtf.WRONG) == len(dtf.RIGHT) == 13
    assert dtf.RIGHT == "sarzaminaryan"
    assert dtf.WRONG == "sarzaminyaran"


def test_every_update_has_a_scope():
    updates = [s for s in SQL.split(";") if s.strip().upper().startswith("UPDATE") or "\nUPDATE " in s]
    assert updates, "هیچ UPDATE تولید نشد"
    for stmt in updates:
        assert " WHERE " in stmt, f"UPDATE بدون محدوده: {stmt[:120]}"
        assert "LIKE '%sarzaminyaran%'" in stmt, "UPDATE باید فقط ردیف‌های تایپو را هدف بگیرد"


def test_backup_then_fix_then_verify_order():
    first_update = SQL.index("UPDATE IR_")
    assert SQL.index("CREATE TABLE IF NOT EXISTS") < first_update, "پشتیبان باید پیش از هر UPDATE باشد"
    assert SQL.rindex("remaining_") > first_update, "راستی‌آزمایی باید بعد از اصلاح باشد"


def test_siteurl_and_home_are_fixed_first():
    first = SQL.index("UPDATE IR_")
    first_stmt = SQL[first:SQL.index(";", first)]
    assert "'siteurl'" in first_stmt and "'home'" in first_stmt, "اولین UPDATE باید siteurl/home باشند"


def test_dangerous_tables_are_left_alone():
    for table in ("IR_clarity_collect_events", "IR_iaa_reports"):
        active = [ln for ln in SQL.splitlines() if ln.strip().upper().startswith("UPDATE")]
        assert not any(table in ln for ln in active), f"{table} نباید به‌روزرسانی شود"


def test_committed_sql_file_matches_generator():
    committed = Path(__file__).resolve().parents[2] / "docs/ops/sql/domain-typo-fix.sql"
    assert committed.exists(), "فایل SQL تولیدشده در مخزن نیست"
    assert committed.read_text(encoding="utf-8").strip() == SQL.strip(), (
        "فایل SQL با سازنده هم‌گام نیست؛ دوباره تولید کنید: "
        "python3 tools/ops/domain_typo_fix.py --out docs/ops/sql/domain-typo-fix.sql"
    )


def test_no_serialized_length_change_possible():
    """هر جایگزینی هم‌طول است، پس هیچ طول اعلام‌شده‌ای در سریالایز تغییر نمی‌کند."""
    for m in re.finditer(r"REPLACE\((\w+), '([^']+)', '([^']+)'\)", SQL):
        assert len(m.group(2)) == len(m.group(3)), f"جایگزینی ناهم‌طول: {m.group(0)}"
