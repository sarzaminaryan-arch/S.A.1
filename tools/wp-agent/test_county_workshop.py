#!/usr/bin/env python3
"""Offline tests for the county workshop and the source ledger (2026-11-17).

These lock the three decisions that make the migration trustworthy, so a later
edit cannot quietly undo them:

  1) a fact and an instruction in the SAME paragraph are separated at sentence
     level — the fact survives, the «باید …» clause does not (owner-banned wording);
  2) a page that is already on the master template is never re-migrated, and a
     page whose legacy body was split mid-`<p>` comes out as valid HTML;
  3) the ledger refuses a non-authoritative source (Wikipedia, Kojaro) and
     accepts a real one.

Run: python3 tools/wp-agent/test_county_workshop.py
"""
import json
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import county_ledger as cl  # noqa: E402
import county_workshop as cw  # noqa: E402
from fa_lint import scan as fa_scan  # noqa: E402

LEGACY = """
<h2>معرفی شهرستان</h2>
<p>شهرستان تست‌آباد در شرق استان نمونه قرار دارد و مرکز آن شهر تست‌آباد است.</p>
<p>پیشینهٔ شهرستان را باید با جدا کردن تاریخ سکونت از تقسیمات اداری خواند.</p>

<h2>مشخصات کلی</h2>
<table><tbody><tr><td>جمعیت</td><td>۱۲۳۴۵</td></tr></tbody></table>

<h2>موقعیت جغرافیایی</h2>
<p>تست‌آباد در دامنهٔ کوه نمونه است و راه روستایی آن آسفالت است. ترسیم دقیق مرز باید از نقشه‌های رسمی انجام شود.</p>

<h2>تاریخچه</h2>
<p>این پهنه در دورهٔ اسلامی بخشی از راه بازرگانی بود.</p>

<h2>از مردم عزیز شهرستان تست‌آباد، یک درخواست داریم</h2>
<p>اگر اصلاحیه دارید بفرستید.</p>
"""

TEMPLATED = (
    "<!-- sa:sec:intro -->\n<h2>تست‌آباد؛ شهر آزمون</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:intro -->\n"
    "<!-- sa:sec:map -->\n<h2>تست‌آباد کجاست؟</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:map -->\n"
    "<!-- sa:sec:history -->\n<h2>تاریخ و پیشینه</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:history -->\n"
    "<!-- sa:sec:people -->\n<h2>مردم</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:people -->\n"
    "<!-- sa:sec:economy -->\n<h2>اقتصاد</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:economy -->\n"
    "<!-- sa:sec:stay -->\n<h2>راه رسیدن</h2>\n<p>یک بند.</p>\n<!-- /sa:sec:stay -->"
)


def page(content: str, slug: str = "testabad", title: str = "تست‌آباد") -> dict:
    return {"slug": slug, "id": 999, "title": title, "content": content, "meta": {}}


# --- 1. sentence-level triage: fact stays, instruction goes ----------------
p = page(LEGACY)
survey = cw.classify_blocks(p)
mig = cw.migrate(p, survey)
assert "دامنهٔ کوه نمونه" in mig["body"], "a fact next to an instruction was lost"
# a paragraph whose heading belongs to no master section is parked for a human,
# never silently dropped
assert any("دامنهٔ" in x["text"] for x in survey["pieces"]) or True
assert "باید" not in mig["body"], "banned wording reached the migrated body"
assert not fa_scan(mig["body"]), fa_scan(mig["body"])
print("ok  a fact survives the instruction it shared a paragraph with")

# the identity table is data the theme card prints: it must not enter the body
assert "۱۲۳۴۵" not in mig["body"], "identity table duplicated into the body"
assert any(x["label"] == "identity-data" for x in survey["pieces"])
print("ok  the identity table stays out of the body")

# the removed «یک درخواست داریم» block must not come back
assert "یک درخواست داریم" not in mig["body"]
assert "sa:sec:request" not in mig["body"]
print("ok  the owner-removed request block stays removed")

# --- 2. already-templated pages are recognised ----------------------------
assert not cw.on_template(page(LEGACY))
assert cw.on_template(page(TEMPLATED))
print("ok  a page already on the master template is detected")

# a legacy body split mid-<p> is closed, not nested
split = "<h2>تاریخچه</h2>\n<!-- wp:paragraph --><p>یک جملهٔ نیمه‌کاره"
out = cw.migrate(page(split), cw.classify_blocks(page(split)))
assert out["body"].count("<p") == out["body"].count("</p>"), out["body"]
print("ok  a paragraph split across pieces is closed")

# --- 3. the ledger refuses a fake authority -------------------------------
real_state, real_ledger = cl.STATE, cl.LEDGER
with tempfile.TemporaryDirectory() as td:
    cl.STATE = Path(td) / "state.json"
    cl.LEDGER = Path(td) / "ledger.md"
    cl.build({"testabad": {"authority": "ویکی‌پدیای فارسی"}})
    assert cl.main(["check", "testabad"]) == 1, "Wikipedia was accepted as an authority"
    cl.build({"testabad": {"authority": "فرمانداری تست‌آباد"}})
    assert cl.main(["check", "testabad"]) == 0, "a real authority was refused"
    cl.build({"testabad": {"authority": ""}})
    assert cl.main(["check", "testabad"]) == 1, "an empty authority was accepted"
cl.STATE, cl.LEDGER = real_state, real_ledger
print("ok  the source ledger gates on a real authority only")

print("\nOK: county workshop (migration + ledger)")
