#!/usr/bin/env python3
"""Roll up ``audit_wxr_content.py`` reports into a Persian markdown audit + issue list.

The markdown is safe to publish: it contains aggregate numbers, slugs/URLs and flags
but never article text, SEO titles/descriptions or focus keywords. The per-item CSV is
the actionable worklist for the owner (published URLs only).

Usage:
  python3 tools/wxr_audit_rollup.py reports/a.json reports/b.json \
      --provenance reports/provenance.json \
      --out docs/YYYY-MM-DD-content-corpus-audit-fa.md \
      --issues docs/YYYY-MM-DD-content-issues-fa.csv
"""
from __future__ import annotations

import argparse
import csv
import json
import sys
from collections import Counter, defaultdict
from pathlib import Path

THIN_CITY_WORDS = 800
LOW_INTERNAL_LINKS = 5
SLUGS_PER_ISSUE = 40

RISK_ORDER = ("P0", "P1", "P2")

RISK_LABELS = {
    "P0": "P0 — ساختار و اعتماد (منتشرشده)",
    "P1": "P1 — یکدستی و تکراری (منتشرشده)",
    "P2": "P2 — پاکیزگی و بهینه‌سازی",
}

ISSUE_LABELS = {
    "body_h1": "H1 داخل بدنه (دو سربرگ اصلی در صفحه)",
    "editorial_residue": "نشانهٔ یادداشت/جای‌نگهدار تحریریه در متن",
    "empty_anchor": "پیوند بدون نام دسترس‌پذیر",
    "image_missing_alt": "تصویر بدون alt",
    "duplicate_seo": "عنوان/توضیح متای تکراری در گروهی از نوشته‌ها",
    "no_faq": "بدون پرسش متداول",
    "no_sources": "بدون منبع ثبت‌شده",
    "thin_content": "کوتاه‌تر از ۸۰۰ واژه (شهرستان)",
    "few_internal_links": "کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)",
    "softer_seo": "بدون override عنوان/توضیح متا (هشدار، نه خطا)",
    "empty_draft": "پیش‌نویس خالی (بدون متن)",
}


def load_reports(paths: list[Path]) -> list[dict]:
    reports = []
    for path in paths:
        reports.append(json.loads(Path(path).read_text(encoding="utf-8")))
    return reports


def has_flag(row: dict, flag: str) -> bool:
    return flag in (row.get("flags") or [])


def iter_published(reports: list[dict]):
    for report in reports:
        for row in report["items"]:
            if row.get("status") == "publish":
                yield report["summary"]["file"], row


def is_work_item(row: dict) -> bool:
    """Media (attachments) and trashed rows are not editorial work items."""
    return row.get("post_type") != "attachment" and row.get("status") in {"publish", "draft", "pending"}


def classify(row: dict, duplicate_slugs: set[str]) -> list[str]:
    """Return the issue keys for one row (order matters for readability)."""
    issues: list[str] = []
    if row.get("status") != "publish" and int(row.get("word_count") or 0) == 0:
        issues.append("empty_draft")
    if int(row.get("h1_count") or 0) > 0:
        issues.append("body_h1")
    if row.get("editorial_residue"):
        issues.append("editorial_residue")
    if int(row.get("empty_anchors") or 0) > 0:
        issues.append("empty_anchor")
    if int(row.get("images_missing_alt") or 0) > 0:
        issues.append("image_missing_alt")
    if row.get("slug") in duplicate_slugs:
        issues.append("duplicate_seo")
    if row.get("post_type") in {"city", "province", "attraction"}:
        if int(row.get("faq_count") or 0) == 0:
            issues.append("no_faq")
        if int(row.get("source_count") or 0) == 0:
            issues.append("no_sources")
    if row.get("post_type") == "city" and int(row.get("word_count") or 0) < THIN_CITY_WORDS:
        issues.append("thin_content")
    if int(row.get("internal_links") or 0) < LOW_INTERNAL_LINKS:
        issues.append("few_internal_links")
    if has_flag(row, "no_exported_seo_title_override") or has_flag(
        row, "no_exported_seo_description_override"
    ):
        issues.append("softer_seo")
    return issues


def risk_of(issue: str, status: str) -> str:
    if status != "publish":
        return "P2"
    return {
        "body_h1": "P0",
        "editorial_residue": "P0",
        "empty_anchor": "P0",
        "duplicate_seo": "P1",
        "image_missing_alt": "P1",
        "few_internal_links": "P2",  # مدل دادهٔ 1.2: هشدار تحریریه، نه مانع
        "thin_content": "P2",
        "no_faq": "P2",
        "no_sources": "P1",
        "softer_seo": "P2",
        "empty_draft": "P2",
    }.get(issue, "P2")


def aggregate(reports: list[dict]) -> list[dict]:
    groups: dict[tuple, list[dict]] = defaultdict(list)
    for report in reports:
        name = report["summary"]["file"]
        for row in report["items"]:
            groups[(name, row.get("post_type"), row.get("status"))].append(row)

    def median(values: list[int]) -> int:
        values = sorted(values)
        return values[len(values) // 2] if values else 0

    table = []
    for (name, post_type, status), rows in sorted(groups.items()):
        words = [int(r.get("word_count") or 0) for r in rows]
        table.append(
            {
                "file": name,
                "post_type": post_type,
                "status": status,
                "items": len(rows),
                "words_median": median(words),
                "words_total": sum(words),
                "h1": sum(1 for r in rows if int(r.get("h1_count") or 0) > 0),
                "faq": sum(int(r.get("faq_count") or 0) for r in rows),
                "sources": sum(int(r.get("source_count") or 0) for r in rows),
                "internal_links": sum(int(r.get("internal_links") or 0) for r in rows),
                "external_links": sum(int(r.get("external_links") or 0) for r in rows),
                "images": sum(int(r.get("image_count") or 0) for r in rows),
                "images_no_alt": sum(int(r.get("images_missing_alt") or 0) for r in rows),
                "empty_anchors": sum(1 for r in rows if int(r.get("empty_anchors") or 0) > 0),
                "residue": sum(1 for r in rows if r.get("editorial_residue")),
                "no_seo_override": sum(
                    1
                    for r in rows
                    if has_flag(r, "no_exported_seo_title_override")
                    and has_flag(r, "no_exported_seo_description_override")
                ),
            }
        )
    return table


def issue_rows(reports: list[dict]) -> list[dict]:
    duplicate_slugs: set[str] = set()
    for report in reports:
        for group in report["summary"].get("duplicate_seo_titles", []) or []:
            duplicate_slugs.update(group.get("slugs", []))
        for group in report["summary"].get("duplicate_seo_descriptions", []) or []:
            duplicate_slugs.update(group.get("slugs", []))
        for group in report["summary"].get("duplicate_slugs", []) or []:
            duplicate_slugs.add(group.get("slug", ""))

    rows = []
    for report in reports:
        name = report["summary"]["file"]
        for row in report["items"]:
            if not is_work_item(row):
                continue
            issues = classify(row, duplicate_slugs)
            if not issues:
                continue
            rows.append(
                {
                    "file": name,
                    "post_id": row.get("post_id", ""),
                    "post_type": row.get("post_type", ""),
                    "status": row.get("status", ""),
                    "slug": row.get("slug", ""),
                    "url": row.get("url", ""),
                    "risk": min((risk_of(i, str(row.get("status"))) for i in issues), key=RISK_ORDER.index),
                    "issues": ";".join(issues),
                    "word_count": row.get("word_count", 0),
                    "h1_count": row.get("h1_count", 0),
                    "faq_count": row.get("faq_count", 0),
                    "source_count": row.get("source_count", 0),
                    "internal_links": row.get("internal_links", 0),
                    "external_links": row.get("external_links", 0),
                    "top_external_domain": row.get("top_external_domain", ""),
                    "top_external_domain_links": row.get("top_external_domain_links", 0),
                    "images_missing_alt": row.get("images_missing_alt", 0),
                    "empty_anchors": row.get("empty_anchors", 0),
                    "editorial_residue": int(bool(row.get("editorial_residue"))),
                }
            )
    rows.sort(key=lambda r: (RISK_ORDER.index(r["risk"]), r["post_type"], r["slug"]))
    return rows


def fa_num(value) -> str:
    """Persian digits for prose (tables keep machine-readable Latin digits)."""
    return str(value).translate(str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹"))


def build_markdown(reports: list[dict], provenance: dict | None, issues: list[dict]) -> str:
    table = aggregate(reports)
    lines: list[str] = []
    lines.append("# ممیزی corpus سه خروجی WXR — سرزمین آریان")
    lines.append("")
    lines.append(
        "این گزارش از خروجی `tools/audit_wxr_content.py` روی سه فایل WordPress WXR ساخته شده است. "
        "متن مقاله، عنوان/توضیح سئو و کلیدواژه در آن چاپ نمی‌شود؛ فقط شمارش‌ها، نشانی‌ها و نشانه‌های ساختاری."
    )
    lines.append("")

    published = [r for r in (row for rep in reports for row in rep["items"]) if r.get("status") == "publish"]
    entity_published = [r for r in published if r.get("post_type") in {"city", "province", "attraction"}]
    body_h1 = sum(1 for r in entity_published if int(r.get("h1_count") or 0) > 0)
    residue = sum(1 for r in entity_published if r.get("editorial_residue"))
    with_links = [r for r in entity_published if int(r.get("external_links") or 0) >= 10]
    concentrated = sum(
        1
        for r in with_links
        if int(r.get("top_external_domain_links") or 0) / max(1, int(r.get("external_links") or 1)) > 0.35
    )
    lines.append("## ۱. خلاصهٔ اجرایی")
    lines.append("")
    lines.append(f"- موجودیت‌های منتشرشده در این سه فایل: **{fa_num(len(entity_published))}** (شهرستان/استان/نمای برتر).")
    lines.append(f"- **{fa_num(body_h1)}** صفحهٔ منتشرشده `<h1>` داخل بدنه دارد؛ دروازهٔ انتشار جلوی نمونه‌های تازه را می‌گیرد، این‌ها ماندهٔ گذشته‌اند.")
    lines.append(f"- **{fa_num(residue)}** صفحهٔ منتشرشده نشانهٔ یادداشت/جای‌نگهدار تحریریه در متن دارد.")
    lines.append(
        f"- از {fa_num(len(with_links))} صفحه‌ای که ۱۰ لینک بیرونی یا بیشتر دارند، "
        f"**{fa_num(concentrated)}** صفحه بیش از ۳۵٪ ارجاع‌هایش به یک دامنه است."
    )
    lines.append("- فهرست کامل و به‌تفکیک ریسک در بخش ۴ و در فایل CSV کنارِ همین سند آمده است.")
    lines.append("")

    lines.append("## ۲. فایل‌های مبنا و اثر انگشت")
    lines.append("")
    if provenance and provenance.get("files"):
        lines.append("| فایل | بایت | sha256 | زمان ثبت (Drive) |")
        lines.append("| --- | --- | --- | --- |")
        for item in provenance["files"]:
            lines.append(
                f"| `{item.get('name','')}` | {item.get('bytes','')} | `{item.get('sha256','')[:16]}…` | {item.get('modified','')} |"
            )
    else:
        lines.append("_فایل provenance داده نشد._")
    lines.append("")
    lines.append(
        f"مجموع اقلام بررسی‌شده: **{fa_num(sum(t['items'] for t in table))}** · "
        f"منتشرشده: **{fa_num(sum(t['items'] for t in table if t['status'] == 'publish'))}**"
    )
    lines.append("")

    lines.append("## ۳. تصویر کلی به تفکیک فایل/نوع/وضعیت")
    lines.append("")
    lines.append(
        "| فایل | نوع | وضعیت | تعداد | میانهٔ واژه | مجموع واژه | H1 بدنه | FAQ | منبع | لینک داخلی | لینک بیرونی | تصویر | alt ندارد | پیوند بی‌نام | یادداشت تحریریه | بی‌override سئو |"
    )
    lines.append("| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |")
    for t in table:
        lines.append(
            "| {file} | {post_type} | {status} | {items} | {words_median} | {words_total} | {h1} | {faq} | {sources} | "
            "{internal_links} | {external_links} | {images} | {images_no_alt} | {empty_anchors} | {residue} | {no_seo_override} |".format(**t)
        )
    lines.append("")

    lines.append("## ۴. فهرست کار به ترتیب ریسک")
    lines.append("")
    grouped: dict[tuple[str, str], list[dict]] = defaultdict(list)
    for row in issues:
        grouped[(row["risk"], row["issues"])].append(row)
    if not issues:
        lines.append("موردی برای اصلاح پیدا نشد.")
    for risk in RISK_ORDER:
        keys = [k for k in grouped if k[0] == risk]
        if not keys:
            continue
        total = sum(len(grouped[k]) for k in keys)
        lines.append(f"### {RISK_LABELS[risk]} — {fa_num(total)} مورد")
        lines.append("")
        for key in sorted(keys, key=lambda k: -len(grouped[k])):
            rows = grouped[key]
            labels = "، ".join(ISSUE_LABELS.get(i, i) for i in key[1].split(";"))
            lines.append(f"- **{labels}** — {fa_num(len(rows))} مورد")
            published = [r for r in rows if r["status"] == "publish"]
            shown = published[:SLUGS_PER_ISSUE] if published else rows[:SLUGS_PER_ISSUE]
            slugs = "، ".join(f"`{r['slug']}`" for r in shown)
            lines.append(
                f"  - نمونه‌ها: {slugs}"
                + (f" … (+{fa_num(len(rows) - len(shown))} مورد دیگر)" if len(rows) > len(shown) else "")
            )
        lines.append("")

    lines.append("## ۵. تمرکز دامنه‌های بیرونی (سرنخ تنوع منبع)")
    lines.append("")
    histogram: Counter[str] = Counter()
    for report in reports:
        histogram.update(report["summary"].get("external_domain_histogram", {}) or {})
    over = sum(int(r["summary"].get("posts_over_35pct_single_domain") or 0) for r in reports)
    if histogram:
        lines.append(f"نوشته‌هایی که بیش از ۳۵٪ ارجاع‌شان به یک دامنه است (≥۱۰ لینک): **{fa_num(over)}**")
        lines.append("")
        lines.append("| دامنه | تعداد ارجاع |")
        lines.append("| --- | --- |")
        for domain, count in histogram.most_common(15):
            lines.append(f"| `{domain}` | {count} |")
    else:
        lines.append("لینک بیرونی‌ای در این فایل‌ها نبود.")
    lines.append("")

    lines.append("## ۶. محدودیت‌ها و روش")
    lines.append("")
    lines.append("- این ابزار ساختار را می‌شمارد؛ درستی واقعیت، اعتبار منبع، سودمندی برای مسافر یا رتبهٔ Google را داوری نمی‌کند.")
    lines.append("- جدول‌ها را عمداً با رقم لاتین نگه داشتیم تا با اسکریپت/اکسل و ویرایش‌های بعدی ساده بماند؛ متن، رقم فارسی دارد.")
    lines.append("- نبود override سئو لزوماً خطا نیست؛ قالب یا افزونهٔ سئو می‌تواند عنوان/توضیح را خودکار بسازد.")
    lines.append("- متن کامل سایت، تصویرها، منوها، سفارشی‌ساز و پایگاه‌داده در این سه خروجی نیستند؛ این ممیزی فقط همین سه فایل را توصیف می‌کند.")
    lines.append("- داده‌های زمان‌محور (آمار، جمعیت، قیمت) بدون منبع و تاریخ اعتبار قابل انتشار نیستند؛ این ابزار چنین چیزی را هم بررسی نمی‌کند.")
    lines.append("")
    return "\n".join(lines) + "\n"


def write_issues_csv(rows: list[dict], destination: Path) -> None:
    columns = [
        "file",
        "risk",
        "issues",
        "post_type",
        "status",
        "slug",
        "url",
        "word_count",
        "h1_count",
        "faq_count",
        "source_count",
        "internal_links",
        "external_links",
        "top_external_domain",
        "top_external_domain_links",
        "images_missing_alt",
        "empty_anchors",
        "editorial_residue",
        "post_id",
    ]
    with destination.open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=columns, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("reports", nargs="+", type=Path, help="audit_wxr_content.py JSON reports")
    parser.add_argument("--provenance", type=Path, help="JSON with file name/sha256/bytes/modified")
    parser.add_argument("--out", type=Path, required=True, help="markdown output path")
    parser.add_argument("--issues", type=Path, help="per-item issue CSV output path")
    parser.add_argument("--json", type=Path, help="optional machine-readable rollup")
    args = parser.parse_args(argv)

    reports = load_reports(args.reports)
    provenance = json.loads(args.provenance.read_text(encoding="utf-8")) if args.provenance else None
    issues = issue_rows(reports)

    args.out.parent.mkdir(parents=True, exist_ok=True)
    args.out.write_text(build_markdown(reports, provenance, issues), encoding="utf-8")
    if args.issues:
        write_issues_csv(issues, args.issues)
    if args.json:
        payload = {
            "aggregate": aggregate(reports),
            "issue_counts": dict(Counter(r["issues"] for r in issues)),
            "risk_counts": dict(Counter(r["risk"] for r in issues)),
        }
        args.json.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    counts = Counter(r["risk"] for r in issues)
    print(
        f"wrote {args.out}"
        + (f" and {args.issues}" if args.issues else "")
        + f" — issues: P0={counts.get('P0', 0)} P1={counts.get('P1', 0)} P2={counts.get('P2', 0)}"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
