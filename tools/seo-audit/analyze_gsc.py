#!/usr/bin/env python3
"""Analyze the Google Search Console coverage exports for sarzaminaryan.ir.

Reads every .xlsx in google_drive/ (GSC "Coverage" exports the owner uploaded),
classifies each URL by URL pattern, cross-checks it against the registries that
live in the child theme (counties, provinces, taxonomy terms, the 301 map) and
writes:

  .tmp/gsc/all-urls.csv        one row per reported URL with cause + fix + task
  .tmp/gsc/summary.md          exact counts (replaces the estimates in the brief)

Usage: python3 tools/seo-audit/analyze_gsc.py [--xlsx-dir google_drive] [--out .tmp/gsc]
"""

import argparse
import csv
import os
import re
import sys
from collections import Counter, defaultdict
from datetime import date, timedelta

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from xlsx_to_csv import shared_strings, sheet_targets, read_sheet  # noqa: E402
import zipfile  # noqa: E402

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
THEME = os.path.join(REPO, "wp-content/themes/sarzaminaryan-child")
ORIGIN = "https://sarzaminaryan.ir"

EXCEL_EPOCH = date(1899, 12, 30)


# --------------------------------------------------------------------------- #
# repository registries
# --------------------------------------------------------------------------- #
def php_pairs(path, keys):
    """Extract simple `array( 'k' => 'v', ... )` rows from a PHP data file."""
    rows = []
    with open(path, encoding="utf-8") as fh:
        text = fh.read()
    for m in re.finditer(r"array\(([^()]*)\)", text):
        body = m.group(1)
        row = {}
        for k in keys:
            km = re.search(rf"'{k}'\s*=>\s*'([^']*)'", body)
            if km:
                row[k] = km.group(1)
        if row:
            rows.append(row)
    return rows


def load_registries():
    counties = php_pairs(os.path.join(THEME, "data/counties.php"), ("slug", "name", "province", "status"))
    provinces = php_pairs(os.path.join(THEME, "data/provinces.php"), ("slug", "name", "en", "center"))
    redirects = {}
    with open(os.path.join(THEME, "data/redirects.php"), encoding="utf-8") as fh:
        for m in re.finditer(r"'(/[^']*/)'\s*=>\s*'(/[^']*)/'?,", fh.read().replace("'/'", "'/'")):
            redirects[m.group(1)] = m.group(2)
    # redirect file is written as 'from' => 'to', — re-parse cleanly
    redirects = {}
    with open(os.path.join(THEME, "data/redirects.php"), encoding="utf-8") as fh:
        for m in re.finditer(r"^\s*'([^']+)'\s*=>\s*'([^']+)'", fh.read(), re.M):
            redirects[m.group(1)] = m.group(2)

    cfg = open(os.path.join(THEME, "inc/entities-config.php"), encoding="utf-8").read()

    def terms(tax):
        block = cfg.split(f"'{tax}' => array(", 1)
        if len(block) < 2:
            return []
        chunk = block[1].split("'terms' => array(", 1)
        if len(chunk) < 2:
            return []
        seg = chunk[1].split("),\n\t\t\t'source'", 1)[0]
        return re.findall(r"'slug'\s*=>\s*'([^']+)'", seg)

    return {
        "counties": {c["slug"]: c for c in counties},
        "provinces": {p["slug"]: p for p in provinces},
        "redirects": redirects,
        "attraction_types": terms("attraction_type"),
        "seasons": terms("travel_season"),
    }


# --------------------------------------------------------------------------- #
# GSC workbooks
# --------------------------------------------------------------------------- #
def read_workbook(path):
    """-> (issue, [ {url, crawled, status} ])"""
    with zipfile.ZipFile(path) as zf:
        strings = shared_strings(zf)
        sheets = {name: read_sheet(zf, target, strings) for name, target in sheet_targets(zf)}

    issue = ""
    for row in sheets.get("Metadata", []):
        if row and row[0] == "Issue":
            issue = row[1] if len(row) > 1 else ""
    if not issue:
        issue = "Valid" if "Valid" in os.path.basename(path) else "Unknown"

    rows = []
    for row in sheets.get("Table", []):
        if not row or not str(row[0]).startswith("http"):
            continue
        crawled = ""
        if len(row) > 1 and row[1]:
            try:
                crawled = (EXCEL_EPOCH + timedelta(days=int(float(row[1])))).isoformat()
            except ValueError:
                crawled = str(row[1])[:10]
        status = row[2] if len(row) > 2 else ""
        rows.append({"url": row[0].strip(), "crawled": crawled, "status": status})
    return issue, rows


def to_path(url):
    u = url.replace(ORIGIN, "")
    return u if u else "/"


# --------------------------------------------------------------------------- #
# classification
# --------------------------------------------------------------------------- #
def classify(path, reg):
    """-> (pattern, entity, note)"""
    p = path
    if p == "/":
        return "homepage", "", ""
    if "*" in p:
        return "wildcard", p, "الگوی wildcard — در هیچ سایت‌مپ واقعی نباید باشد"
    if p.startswith("/?") or "?" in p:
        return "query-string", p, ""
    if p.startswith("/wp-"):
        return "wp-core-file", p, ""
    if p.startswith("/wp-content/"):
        return "wp-content", p, ""

    m = re.fullmatch(r"/season/([^/]+)/page/(\d+)/", p)
    if m:
        return "season-pagination", m.group(1), f"صفحهٔ {m.group(2)}"
    m = re.fullmatch(r"/season/([^/]+)/", p)
    if m:
        return "season-term", m.group(1), ""
    m = re.fullmatch(r"/attraction-type/([^/]+)/", p)
    if m:
        term = m.group(1)
        known = term in reg["attraction_types"]
        return "attraction-type", term, "" if known else "ترم خارج از کانفیگ قالب (۱۲ ترم)"
    m = re.fullmatch(r"/ostan/([^/]+)/", p)
    if m:
        return "ostan-tax", m.group(1), "آرشیو تکسونومی province_tax"
    m = re.fullmatch(r"/provinces/([^/]+)/", p)
    if m:
        return "provinces-alias", m.group(1), "الگوی سوم استان — در ریپو ثبت نشده"
    m = re.fullmatch(r"/province/([^/]+)/page/(\d+)/", p)
    if m:
        return "province-pagination", m.group(1), ""
    m = re.fullmatch(r"/province/([^/]+)/", p)
    if m:
        return "province-cpt", m.group(1), "" if m.group(1) in reg["provinces"] else "خارج از فهرست ۳۱ استان"
    m = re.fullmatch(r"/city/([^/]+)/", p)
    if m:
        slug = m.group(1)
        notes = []
        if slug not in reg["counties"]:
            notes.append("خارج از رجیستری ۴۸۳ شهرستانی")
        else:
            notes.append("رجیستری: " + reg["counties"][slug]["status"])
        if slug.endswith("-city") or slug.endswith("-county"):
            notes.append("پسوند ناسازگار")
        return "city", slug, "; ".join(notes)
    m = re.fullmatch(r"/attraction/([^/]+)/", p)
    if m:
        slug = m.group(1)
        note = "اسلاگ عددی (شناسهٔ خروجی)" if re.match(r"^\d+-", slug) else ""
        return "attraction", slug, note
    m = re.fullmatch(r"/author/([^/]+)(?:/page/(\d+))?/", p)
    if m:
        return "author-archive", m.group(1), f"page {m.group(2)}" if m.group(2) else "صفحهٔ اول"
    m = re.fullmatch(r"/tag/([^/]+)(?:/page/(\d+))?/", p)
    if m:
        return "tag-archive", m.group(1), f"page {m.group(2)}" if m.group(2) else "صفحهٔ اول"
    m = re.fullmatch(r"/category/([^/]+)(?:/page/(\d+))?/", p)
    if m:
        return "category-archive", m.group(1), ""
    m = re.fullmatch(r"/page/(\d+)/", p)
    if m:
        return "blog-pagination", m.group(1), ""
    m = re.fullmatch(r"/feed(/|$)", p)
    if m:
        return "feed", "feed", ""
    for slug in ("dashboard", "policy", "privacy", "editorial-policy", "about", "contact", "blog"):
        if p == f"/{slug}/":
            return "static-page", slug, ""
    for base in ("route", "food", "souvenir", "city", "province", "attraction", "accommodation"):
        if p == f"/{base}/":
            return "cpt-archive", base, ""
    if re.fullmatch(r"/[a-z0-9\-]+/", p):
        return "post", p.strip("/"), "نوشتهٔ راهنما (بدون پیشوند)"
    return "other", p, ""


# --------------------------------------------------------------------------- #
# duplicate detection
# --------------------------------------------------------------------------- #
def norm_city(slug):
    s = slug
    for suf in ("-city", "-county"):
        if s.endswith(suf):
            s = s[: -len(suf)]
    return s


def find_duplicates(rows_by_pattern):
    """city slug variants pointing at the same county."""
    cities = [r["entity"] for r in rows_by_pattern.get("city", [])]
    groups = defaultdict(list)
    for c in cities:
        groups[norm_city(c)].append(c)
    # known registry aliases where the short form is a different county
    dupes = {k: sorted(v) for k, v in groups.items() if len(v) > 1}
    return dupes



def attraction_numeric_pairs(rows_by_pattern):
    """numeric-slug attraction ↔ its clean-slug twin, with issue per side."""
    att = rows_by_pattern.get("attraction", [])
    clean = {r["entity"]: r for r in att if not re.match(r"^\d+-", r["entity"])}
    pairs = []
    for r in att:
        m = re.match(r"^(\d+)-(.+)$", r["entity"])
        if not m:
            continue
        stem = m.group(2)
        twin = clean.get(stem)
        if not twin:
            for c in clean:
                if c.startswith(stem) or stem.startswith(c):
                    twin = clean[c]
                    break
        pairs.append((r, twin))
    return pairs


def province_pattern_table(rows_by_pattern):
    """which provinces are known under /province/, /ostan/, /provinces/."""
    tab = {}
    for key, pat in (("province", "province-cpt"), ("ostan", "ostan-tax"), ("provinces", "provinces-alias")):
        for r in rows_by_pattern.get(pat, []):
            tab.setdefault(r["entity"], {})[key] = r["issue"]
    return tab


# --------------------------------------------------------------------------- #
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--xlsx-dir", default=os.path.join(REPO, "google_drive"))
    ap.add_argument("--out", default=os.path.join(REPO, ".tmp/gsc"))
    args = ap.parse_args()

    reg = load_registries()
    os.makedirs(args.out, exist_ok=True)

    files = sorted(f for f in os.listdir(args.xlsx_dir) if f.endswith(".xlsx"))
    if not files:
        sys.exit(f"no .xlsx in {args.xlsx_dir}")

    records = []
    per_issue = Counter()
    for fn in files:
        issue, rows = read_workbook(os.path.join(args.xlsx_dir, fn))
        per_issue[issue] += len(rows)
        for r in rows:
            path = to_path(r["url"])
            pattern, entity, note = classify(path, reg)
            redir = reg["redirects"].get(path, "")
            records.append(
                {
                    "issue": issue,
                    "url": r["url"],
                    "path": path,
                    "pattern": pattern,
                    "entity": entity,
                    "last_crawled": r["crawled"],
                    "gsc_status": r["status"],
                    "registry_note": note,
                    "repo_301_target": redir,
                }
            )

    # ---- write full csv
    csv_path = os.path.join(args.out, "all-urls.csv")
    with open(csv_path, "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=list(records[0].keys()))
        w.writeheader()
        w.writerows(records)

    # ---- aggregates
    by_pattern = defaultdict(list)
    for r in records:
        by_pattern[r["pattern"]].append(r)
    issue_pattern = Counter((r["issue"], r["pattern"]) for r in records)

    dupes = find_duplicates(by_pattern)
    numeric_attr = sorted(r["entity"] for r in by_pattern.get("attraction", []) if re.match(r"^\d+-", r["entity"]))
    city_off_registry = sorted(
        r["entity"] for r in by_pattern.get("city", []) if r["entity"] not in reg["counties"]
    )
    city_suffixed = sorted(r["entity"] for r in by_pattern.get("city", []) if r["entity"].endswith(("-city", "-county")))
    at_types = sorted(r["entity"] for r in by_pattern.get("attraction-type", []))
    at_unknown = [t for t in at_types if t not in reg["attraction_types"]]
    prov_patterns = {
        k: sorted(r["entity"] for r in by_pattern.get(k, []))
        for k in ("province-cpt", "ostan-tax", "provinces-alias")
    }
    already_mapped = [r for r in records if r["repo_301_target"]]

    lines = []
    A = lines.append
    A("# GSC coverage export — شمارش واقعی (۲۰۲۶-۱۰-۲۳)\n")
    A(f"منبع: {len(files)} فایل xlsx در `google_drive/` · رجیستری قالب: {len(reg['counties'])} شهرستان، "
      f"{len(reg['provinces'])} استان، {len(reg['attraction_types'])} ترم attraction_type، "
      f"{len(reg['redirects'])} ردیف در data/redirects.php\n")
    A("## جمع هر گزارش\n")
    A("| گزارش (Issue) | تعداد URL |")
    A("|---|---:|")
    for issue, n in per_issue.most_common():
        A(f"| {issue} | {n} |")
    A(f"| **جمع** | **{sum(per_issue.values())}** |\n")

    A("## تفکیک الگو در هر گزارش\n")
    A("| گزارش | الگو | تعداد |")
    A("|---|---|---:|")
    for (issue, pattern), n in sorted(issue_pattern.items(), key=lambda x: (x[0][0], -x[1])):
        A(f"| {issue} | {pattern} | {n} |")
    A("")

    A("## یافته‌های کلیدی\n")
    A(f"- URLهای wildcard/پارامتری/فایل وردپرس: {len(by_pattern.get('wildcard', []))} wildcard، "
      f"{len(by_pattern.get('query-string', []))} کوئری، {len(by_pattern.get('wp-core-file', []))} wp-*.php، "
      f"{len(by_pattern.get('wp-content', []))} wp-content")
    A(f"- جاذبه با اسلاگ عددی: {len(numeric_attr)} → {', '.join(numeric_attr)}")
    A(f"- ترم‌های attraction_type در GSC: {len(at_types)} (کانفیگ قالب: {len(reg['attraction_types'])})؛ "
      f"خارج از کانفیگ: {', '.join(at_unknown) or '—'}")
    A(f"- الگوهای استان: /province/ = {len(prov_patterns['province-cpt'])}، /ostan/ = {len(prov_patterns['ostan-tax'])}، "
      f"/provinces/ = {len(prov_patterns['provinces-alias'])}")
    A(f"- شهرهای با پسوند ناسازگار (-city/-county): {len(city_suffixed)} → {', '.join(city_suffixed)}")
    A(f"- شهرهای خارج از رجیستری ۴۸۳ تایی: {len(city_off_registry)}")
    A(f"- صفحه‌بندی فصلی: {len(by_pattern.get('season-pagination', []))} URL "
      f"(ترم‌ها: {', '.join(sorted({r['entity'] for r in by_pattern.get('season-pagination', [])}))})")
    A(f"- مواردی که در ریپو ۳۰۱ دارند (یعنی اصلاح در کد هست ولی روی سایت اعمال نشده): {len(already_mapped)}")
    A("")
    A("### جفت‌های تکراری شهر (هم‌شهرستان، دو نشانی)\n")
    if dupes:
        for k, v in sorted(dupes.items()):
            A(f"- `{k}`: " + " · ".join(f"`/city/{x}/`" for x in v))
    else:
        A("- هیچ جفتی در این export دیده نشد.")
    A("")
    A("### شهرهای خارج از رجیستری (نیازمند تعیین تکلیف)\n")
    A(", ".join(f"`{c}`" for c in city_off_registry) or "—")
    A("")
    A("### جفت‌های جاذبهٔ عددی ↔ اسلاگ تمیز\n")
    A("| اسلاگ عددی | گزارش | اسلاگ تمیز | گزارش |")
    A("|---|---|---|---|")
    for num, twin in attraction_numeric_pairs(by_pattern):
        A(f"| `/attraction/{num['entity']}/` | {num['issue']} | "
          f"{'`/attraction/' + twin['entity'] + '/`' if twin else '—'} | {twin['issue'] if twin else '—'} |")
    A("")
    A("### ماتریس الگوهای استان (هر استان در کدام الگو دیده شده)\n")
    A("| استان | /province/ | /ostan/ | /provinces/ |")
    A("|---|---|---|---|")
    for slug, pats in sorted(province_pattern_table(by_pattern).items()):
        A(f"| {slug} | {pats.get('province','—')} | {pats.get('ostan','—')} | {pats.get('provinces','—')} |")
    A("")
    A("### مواردی که data/redirects.php ریپو پوشش می‌دهد\n")
    A("| URL گزارش‌شده | گزارش | مقصد ۳۰۱ در ریپو |")
    A("|---|---|---|")
    for r in sorted(already_mapped, key=lambda x: x["path"]):
        A(f"| `{r['path']}` | {r['issue']} | `{r['repo_301_target']}` |")
    A("")

    summary = os.path.join(args.out, "summary.md")
    with open(summary, "w", encoding="utf-8") as fh:
        fh.write("\n".join(lines))

    print("\n".join(lines[:60]))
    print(f"\n[written] {csv_path} ({len(records)} rows)\n[written] {summary}")


if __name__ == "__main__":
    main()
