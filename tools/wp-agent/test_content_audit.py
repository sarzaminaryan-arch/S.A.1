"""Run: python3 tools/wp-agent/test_content_audit.py"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import content_audit as ca  # noqa: E402


def page(i, name, body, meta=None, index=True):
    return {"id": i, "slug": f"c{i}", "title": f"شهرستان {name}؛ راهنمای سفر", "index_gate": {"index": index},
            "meta": meta or {}, "content": body}


bad = page(1, "هرند",
           "<p>زبان مردم شهرستان هرند فارسی است و لهجهٔ محلی دارند و همه آن را می‌فهمند.</p>" * 2
           + "<p>بارش سالانه حدود ۱۹ میلی‌متر است. میانگین دمای سالانه ۳۲ درجه است و هوا گرم است.</p>"
           + "<h2>حقایق جالب</h2><ul><li>جمعیت: ۵۰٬۰۰۰ نفر</li><li>مساحت ۱۵۰۰ کیلومتر مربع</li></ul>",
           {"sa_cty_population": "50000", "sa_cty_area": "1500", "sa_cty_climate": "معتدل کوهستانی، تابستان خنک"},
           index=False)
tmpl = "<p>شهرستان {n} یکی از شهرستان‌های استان است که جمعیت آن {p} نفر در سرشماری ۱۳۹۵ بوده است.</p>"
others = [page(10 + k, n, tmpl.format(n=n, p=1000 + k), {"sa_cty_population": str(1000 + k)})
          for k, n in enumerate(["ری", "بم", "تفت", "نور"])]
clean = page(2, "اصفهان", "<p>میدان نقش جهان در مرکز شهر اصفهان است و هر روز گردشگران زیادی دارد.</p>",
             {"sa_cty_population": "2130970", "sa_cty_climate": "خشک"})
pages = [ca.analyze_page(p) for p in [bad, clean] + others]
rep = ca.aggregate(pages, template_min_pages=3)
by = {p["slug"]: p for p in pages}
assert by["c1"]["dup_sentences"] == 1, by["c1"]
assert by["c1"]["impossible"] == ["میانگین دمای سالانه 32 درجه"], by["c1"]["impossible"]
assert len(by["c1"]["climate_conflict"]) == 2
assert by["c1"]["facts_numeric_only"] == 2
assert by["c2"]["score"] == 0 and not by["c2"]["climate_conflict"], by["c2"]
assert all(by[f"c{10 + k}"]["template_ratio"] == 1.0 for k in range(4)), [by[f"c{10 + k}"]["template_ratio"] for k in range(4)]
assert rep["queue"][0]["index"] is True, "indexable pages lead the queue"
assert rep["queue"][-1]["slug"] == "c1"
assert ca.county_name("شهرستان خمینی‌شهر؛ راهنمای سفر") == "خمینی شهر"
assert ca.skeleton("بوستان ری و روستای ریزاب", "ری") == "بوستان X و روستای ریزاب"
assert not ca.impossible_checks({}, "ارتفاع ۵۶۱۰ متر")
assert ca.impossible_checks({}, "ارتفاع ۸۰۰۰ متر")
# Persian separators: «۵۲۵٫۸ میلی‌متر» is 525.8, not 8 or 5.
assert not ca.climate_checks({"sa_cty_climate": "معتدل کوهستانی"}, "بارش سالانه ۵۲۵٫۸ میلی‌متر است")
assert ca.climate_checks({"sa_cty_climate": "معتدل کوهستانی"}, "بارش سالانه ۱۹ میلی‌متر است")
# southern coast: humid air with little rain is real, not a contradiction
assert not ca.climate_checks({"sa_cty_climate": "گرم و مرطوب ساحلی"}, "بارش سالانه ۱۱۰ میلی‌متر است")
assert ca.numbers("۲٬۱۳۰٬۹۷۰ نفر") == [2130970.0]
print("OK: content_audit", json.dumps(rep["summary"], ensure_ascii=False))
