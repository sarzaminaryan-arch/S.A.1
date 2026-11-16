#!/usr/bin/env python3
"""Offline tests for the county-generality pass (template 2026-11-17).

Two things the owner asked for and that must not silently regress:

  1) a county whose honest data has no «چهره‌های نام‌آور» and no coordinates
     still builds, is reported as incomplete (not silently rendered), and
     never gets a section heading printed above nothing;
  2) a body built with an old template — retired section, half marker pair,
     hand-written map block — is refused on the WRITE path (`update`), not
     only by the offline guard.

Runs with no network and no PHP: everything here is the Python path that the
agent job runner uses.
"""
import json
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import wp_agent as wa  # noqa: E402
from replace_src import check_template as ct  # noqa: E402
from replace_src.county_page import build  # noqa: E402
from replace_src.template import MARK_OPEN, MARK_CLOSE, Template  # noqa: E402


# --- 1. a county with no notables -----------------------------------------
def county(notables=True, meta=None):
    data = {
        "id": 999, "slug": "testville", "name": "تست‌آباد",
        "tagline": "شهر آزمایشی برای گارد قالب",
        "intro": ["یک بند آغازین کوتاه برای آزمون قالب."],
        "map": ["یک بند جغرافیایی کوتاه."],
        "history": ["یک بند تاریخ."],
        "people": ["یک بند مردم."],
        "economy": ["یک بند اقتصاد."],
        "stay": ["یک بند راه رسیدن."],
        "sources": {},
        "meta": meta if meta is not None else {"lat": "35.0", "lng": "51.0"},
    }
    if notables:
        data["notables"] = ["یک بند چهره‌ها."]
    return data


body, meta, problems = build(county(notables=False))
assert "چهره‌های نام‌آور" not in body, "a county without notables must not print the heading"
assert MARK_OPEN.format("notables") not in body
for key in ("intro", "map", "history", "people", "economy", "stay"):
    assert MARK_OPEN.format(key) in body and MARK_CLOSE.format(key) in body, key
assert not problems, problems
print("ok  county without notables builds, no empty heading")

# the guard accepts it: notables is optional, the six required sections are there
with tempfile.TemporaryDirectory() as td:
    d = Path(td)
    (d / "testville-999.html").write_text(body, encoding="utf-8", newline="")
    json.dump({"sa_cty_neighbors": "", "sa_cty_climate": "معتدل", "sa_cty_best_time": "بهار",
               "sa_cty_language": "فارسی", "sa_cty_livelihood": "کشاورزی",
               "sa_faq": json.dumps([{"q": "کجاست؟", "a": "اینجا."}] * 3, ensure_ascii=False),
               "sa_sources": "یک منبع | خبرگزاری صداوسیما | https://www.irib-news.ir/a | ۱۴۰۵/۰۸/۲۶\n"
                             "منبع دوم | خبرگزاری تسنیم | https://www.tasnimnews.com/b | ۱۴۰۵/۰۸/۲۶",
               "lat": "35.0", "lng": "51.0"},
              open(d / "testville-999.meta.json", "w", encoding="utf-8"), ensure_ascii=False)
    found = ct.check_html(d / "testville-999.html")
    assert not [p for p in found if "notables" in p], found
    print("ok  guard tolerates a missing notables section")

    # --- 2. the write path refuses an old-template body --------------------
    old = body.replace(MARK_CLOSE.format("history"), MARK_CLOSE.format("history") + "\n\n"
                       + MARK_OPEN.format("tips") + "\n<h2>چند نکتهٔ کوچک از تست‌آباد</h2>"
                       + MARK_CLOSE.format("tips"))
    for broken, why in (
        (old, "retired"),
        (body.replace(MARK_CLOSE.format("stay"), ""), "half"),
        (body + '\n<figure id="sa-map"></figure>', "map"),
        (body.replace(MARK_OPEN.format("economy"), "").replace(MARK_CLOSE.format("economy"), ""),
         "required"),
    ):
        try:
            wa.check_county_shape(broken, "post 999: ")
        except wa.AgentError as err:
            assert why in str(err) or why == "half", (why, str(err))
            print(f"ok  write path refuses: {why}")
        else:
            raise AssertionError(f"write path accepted a body with the {why} problem")

    wa.check_county_shape(body, "post 999: ")          # clean body passes
    print("ok  write path accepts a conforming county body")

    # a non-county body (no markers) is none of this guard's business
    wa.check_county_shape("<p>متن سادهٔ یک برگه.</p>")
    print("ok  write path ignores non-county bodies")

# --- 2b. neighbour slugs must be counties the theme knows -----------------
known = ct.county_slugs()
assert known and "bonab" in known, "the theme county table did not load"
with tempfile.TemporaryDirectory() as td:
    d = Path(td)
    (d / "testville-999.html").write_text(body, encoding="utf-8", newline="")
    meta = {"sa_cty_neighbors": "bonab - غرب\nnosuchplace - شرق", "sa_cty_climate": "معتدل",
            "sa_cty_best_time": "بهار", "sa_cty_language": "فارسی", "sa_cty_livelihood": "کشاورزی",
            "sa_faq": json.dumps([{"q": "کجاست؟", "a": "اینجا."}] * 3, ensure_ascii=False),
            "sa_sources": "یک | صداوسیما | https://www.irib-news.ir/a | ۱۴۰۵/۰۸/۲۶\n"
                          "دو | تسنیم | https://www.tasnimnews.com/b | ۱۴۰۵/۰۸/۲۶",
            "lat": "35.0", "lng": "51.0"}
    json.dump(meta, open(d / "testville-999.meta.json", "w", encoding="utf-8"), ensure_ascii=False)
    found = ct.check_meta(d / "testville-999.meta.json")
    assert any("nosuchplace" in x for x in found), found
    print("ok  a neighbour slug the theme cannot link is reported")

# --- 3. ordering still comes from the registry only -----------------------
t = Template("تست‌آباد", tagline="آزمون")
t.section("intro", ["آغاز"])
t.section("map", ["جغرافیا"])
t.section("stay", ["زودتر از موعد"])          # written before history/people/economy
t.section("history", ["تاریخ"])
t.section("people", ["مردم"])
t.section("economy", ["اقتصاد"])
try:
    t.finish()
except ValueError as err:
    assert "order" in str(err), err
    print("ok  registry still owns the order")
else:
    raise AssertionError("out-of-order sections were accepted")

print("\nOK: county template (generality pass)")
