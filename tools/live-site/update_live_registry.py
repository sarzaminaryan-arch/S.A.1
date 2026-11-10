#!/usr/bin/env python3
"""به‌روزرسانی «شبیه‌سازی سایت زنده» در docs/live-site از خروجی افزونهٔ ممیزی.

ورودی‌ها (از افزونهٔ sa-live-article-audit روی سایت زنده):
  --live    اسنپ‌شات زنده: CSV فارسی افزونه یا JSON کامل (records)
  --audit   گزارش تطبیق CSV افزونه (اختیاری ولی برای وضعیت مخزن لازم است)
  --baseline baseline.json مخزن (پیش‌فرض: خروجی مولد افزونه اگر موجود باشد)

خروجی‌ها در docs/live-site:
  registry/live-articles.json  رجیستری ماشین‌خوان (منبع اصلی ایجنت‌ها)
  INDEX.md                     خلاصه و نقشهٔ راه
  articles-<type>.md           فهرست خوانا به تفکیک نوع محتوا
  titles.md                    نمایهٔ عنوان‌ها (جلوگیری از محتوای تکراری)
  unpublished.md               رکوردهای مخزن که روی سایت زنده منتشر نشده‌اند
  fix-queue.md                 صف رفع اشکال (تکراری‌ها، اختلاف با مخزن، نقص‌ها)
  reconciliation-latest.md     گزارش همین تطبیق

قاعده: این اسکریپت را بعد از هر ممیزی تازه و هر تغییری که ایجنت روی محتوای
زنده/آماده اعمال می‌کند اجرا کنید و خروجی را کامیت کنید.
"""
from __future__ import annotations

import argparse
import collections
import csv
import datetime
import hashlib
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
DEFAULT_OUT = ROOT / "docs" / "live-site"
DEFAULT_BASELINE = ROOT / "wp-content" / "plugins" / "sa-live-article-audit" / "data" / "baseline.json"

FA_HEADERS = {
    "کلید": "key", "شناسه وردپرس": "wp_id", "نوع": "post_type", "نامک": "slug",
    "عنوان": "title", "وضعیت": "status", "انتشار": "date_published",
    "آخرین ویرایش": "date_modified", "نویسنده": "author", "پیوند": "permalink",
    "هش محتوا (نرمال)": "content_hash", "هش متن ساده": "content_text_hash",
    "واژه": "word_count", "تصویر شاخص": "featured_file", "طبقه‌بندی‌ها": "terms_raw",
    "تعداد متا": "meta_count",
}
THIN = {"post": 800, "attraction": 800, "page": 800, "city": 400, "province": 800}


def esc(text) -> str:
    return str(text or "").replace("|", "¦").replace("\n", " ").strip()


def load_live(path: pathlib.Path) -> list[dict]:
    if path.suffix.lower() == ".json":
        data = json.loads(path.read_text(encoding="utf-8"))
        records = data.get("records", data if isinstance(data, list) else [])
        out = []
        seo_keys = ("seo", "rank_math", "focus_keyword", "og_", "keyword")
        for r in records:
            terms = {}
            for tax, mapping in (r.get("terms") or {}).items():
                terms[tax] = sorted(mapping.keys()) if isinstance(mapping, dict) else list(mapping)
            meta = r.get("meta") or {}
            links = r.get("links") or []
            internal = sorted({(l.get("url") or "") for l in links if isinstance(l, dict) and l.get("internal")})
            feat = r.get("featured_image") or {}
            out.append({
                "key": r.get("key") or f"{r.get('post_type')}:{r.get('slug')}",
                "wp_id": r.get("wp_id"), "post_type": r.get("post_type"), "slug": r.get("slug"),
                "title": r.get("title"), "status": r.get("status", "publish"),
                "date_published": r.get("date_published"), "date_modified": r.get("date_modified"),
                "author": (r.get("author") or {}).get("display_name") if isinstance(r.get("author"), dict) else r.get("author"),
                "permalink": r.get("permalink"), "edit_link": r.get("edit_link"),
                "excerpt": (r.get("excerpt") or "")[:400],
                "content_hash": r.get("content_hash"),
                "content_text_hash": r.get("content_text_hash"), "word_count": r.get("word_count"),
                "featured_file": feat.get("file", ""),
                "featured": {"file": feat.get("file", ""), "alt": feat.get("alt", ""),
                             "width": feat.get("width"), "height": feat.get("height")} if feat else {},
                "terms": terms, "meta_count": len(meta),
                "meta_keys": sorted(meta.keys()),
                "seo": {k: str(v)[:300] for k, v in sorted(meta.items())
                        if any(s in k for s in seo_keys) and v not in ("", None, [])},
                "headings": [{"level": h.get("level"), "text": h.get("text")}
                             for h in (r.get("headings") or []) if isinstance(h, dict)],
                "faq_count": len(r.get("faq") or []),
                "faq_questions": [f.get("q") for f in (r.get("faq") or []) if isinstance(f, dict)],
                "internal_links": internal,
                "external_link_count": sum(1 for l in links if isinstance(l, dict) and not l.get("internal")),
                "content_image_files": [i.get("file") for i in (r.get("content_images") or [])
                                        if isinstance(i, dict) and i.get("file")],
                "relations": r.get("relations") or {},
            })
        return out
    rows = list(csv.DictReader(path.open(encoding="utf-8-sig")))
    out = []
    for row in rows:
        r = {FA_HEADERS.get(k, k): v for k, v in row.items()}
        terms = {}
        for part in (r.get("terms_raw") or "").split(";"):
            part = part.strip()
            if not part:
                continue
            if ":" in part:
                tax, slugs = part.split(":", 1)
                terms[tax.strip()] = [s for s in slugs.split("|") if s]
            else:
                terms[part] = []
        out.append({
            "key": r.get("key"), "wp_id": int(r["wp_id"]) if str(r.get("wp_id", "")).isdigit() else r.get("wp_id"),
            "post_type": r.get("post_type"), "slug": r.get("slug"), "title": r.get("title"),
            "status": r.get("status"), "date_published": r.get("date_published"),
            "date_modified": r.get("date_modified"), "author": r.get("author"),
            "permalink": r.get("permalink"), "content_hash": r.get("content_hash"),
            "content_text_hash": r.get("content_text_hash"),
            "word_count": int(r["word_count"]) if str(r.get("word_count", "")).isdigit() else None,
            "featured_file": r.get("featured_file") or "", "terms": terms,
            "meta_count": int(r["meta_count"]) if str(r.get("meta_count", "")).isdigit() else None,
        })
    return out


def load_audit(path: pathlib.Path | None) -> dict:
    """{key: {status, diffs:[{field,severity}]}} + baseline_only/live_only sets."""
    info: dict[str, dict] = {}
    if not path:
        return info
    if path.suffix.lower() == ".json":
        data = json.loads(path.read_text(encoding="utf-8"))
        for item in data.get("items", []):
            diffs = [{"field": d.get("field", ""), "severity": d.get("severity", "")}
                     for d in item.get("differences", [])]
            real = [d for d in diffs if d["severity"] != "info"]
            info[item["key"]] = {"audit_section": "matched", "diffs": diffs,
                                 "repo_status": "matched_different" if real else "matched_identical"}
        for item in data.get("live_only", []):
            info[item["key"]] = {"audit_section": "live_only", "diffs": [], "repo_status": "live_only"}
        for item in data.get("baseline_only", []):
            info[item["key"]] = {"audit_section": "baseline_only", "diffs": [], "repo_status": "baseline_only"}
        return info
    headers = None
    for row in csv.DictReader(path.open(encoding="utf-8-sig")):
        if headers is None:
            headers = list(row.keys())
        section = row.get("بخش") or row.get("section")
        key = row.get("کلید") or row.get("key")
        field = row.get("فیلد") or row.get("field") or ""
        severity = row.get("شدت") or row.get("severity") or ""
        if not key:
            continue
        entry = info.setdefault(key, {"audit_section": section, "diffs": []})
        if section == "matched":
            if field:
                entry["diffs"].append({"field": field, "severity": severity})
            entry["audit_section"] = "matched"
        elif section in ("live_only", "baseline_only"):
            entry["audit_section"] = section
    for entry in info.values():
        if entry["audit_section"] == "matched":
            real = [d for d in entry["diffs"] if d["severity"] != "info"]
            entry["repo_status"] = "matched_different" if real else "matched_identical"
        elif entry["audit_section"] == "live_only":
            entry["repo_status"] = "live_only"
        elif entry["audit_section"] == "baseline_only":
            entry["repo_status"] = "baseline_only"
    return info


def load_baseline(path: pathlib.Path | None) -> list[dict]:
    if not path or not path.exists():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    return data.get("records", [])


def build(live, audit, baseline):
    live_keys = {(r["post_type"], r["slug"]) for r in live}
    records = []
    for r in sorted(live, key=lambda x: (x["post_type"] or "", x["slug"] or "")):
        a = audit.get(r["key"], {})
        repo_status = a.get("repo_status", "unknown")
        diffs = [d for d in a.get("diffs", []) if d["severity"] != "info"]
        flags = []
        if not r.get("featured_file"):
            flags.append("missing_featured_image")
        limit = THIN.get(r["post_type"] or "", 800)
        if r.get("word_count") is not None and r["word_count"] < limit:
            flags.append(f"thin_content(<{limit})")
        if repo_status == "matched_different":
            flags.append("differs_from_repo")
        if repo_status == "live_only":
            flags.append("not_in_repo_baseline")
        # پرچم‌های کیفی فقط وقتی دادهٔ کامل JSON هست (با CSV ساده این فیلدها نیستند).
        if "seo" in r:
            if not (r["seo"].get("rank_math_title") or r["seo"].get("sa_seo_title")):
                flags.append("missing_seo_title")
            if not (r["seo"].get("rank_math_description") or r["seo"].get("sa_seo_description")):
                flags.append("missing_seo_description")
        if "faq_count" in r and r["post_type"] in ("post", "attraction") and not r["faq_count"]:
            flags.append("missing_faq")
        if "headings" in r and r["post_type"] in ("post", "attraction") and len(r["headings"]) < 3:
            flags.append("few_headings")
        rec = dict(r)
        rec["repo"] = {"status": repo_status, "diff_fields": sorted({d["field"] for d in diffs})}
        rec["flags"] = flags
        records.append(rec)

    # duplicate detection: exact title, exact content hash, -N slug with live base
    by_title = collections.defaultdict(list)
    by_hash = collections.defaultdict(list)
    slugs = {(r["post_type"], r["slug"]) for r in records}
    for r in records:
        if r["title"]:
            by_title[r["title"]].append(r["key"])
        if r["content_hash"]:
            by_hash[r["content_hash"]].append(r["key"])
    dup_titles = {t: ks for t, ks in by_title.items() if len(ks) > 1}
    dup_hashes = {h: ks for h, ks in by_hash.items() if len(ks) > 1}
    for r in records:
        if r["key"] in {k for ks in dup_titles.values() for k in ks}:
            r["flags"].append("duplicate_title")
        if r["key"] in {k for ks in dup_hashes.values() for k in ks}:
            r["flags"].append("duplicate_content_hash")
        m = re.match(r"^(.*)-(\d+)$", r["slug"] or "")
        if m and (r["post_type"], m.group(1)) in slugs:
            r["flags"].append("slug_suffix_of_existing")

    unpublished = []
    for b in baseline:
        if (b.get("post_type"), b.get("slug")) not in live_keys:
            unpublished.append({
                "key": f"{b.get('post_type')}:{b.get('slug')}",
                "post_type": b.get("post_type"), "slug": b.get("slug"), "title": b.get("title"),
                "faq_count": len(b.get("faq") or []), "meta_count": len(b.get("meta") or {}),
                "has_cover": bool((b.get("cover") or {}).get("file")),
                "repo_status": "ready_in_repo_not_published_live",
            })
    return records, unpublished, dup_titles, dup_hashes


def table(headers, rows):
    out = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
    out += ["| " + " | ".join(esc(c) for c in row) + " |" for row in rows]
    return "\n".join(out) + "\n"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--live", required=True)
    ap.add_argument("--audit")
    ap.add_argument("--baseline", default=str(DEFAULT_BASELINE))
    ap.add_argument("--out", default=str(DEFAULT_OUT))
    args = ap.parse_args()

    live = load_live(pathlib.Path(args.live))
    audit = load_audit(pathlib.Path(args.audit) if args.audit else None)
    baseline = load_baseline(pathlib.Path(args.baseline) if args.baseline else None)
    records, unpublished, dup_titles, dup_hashes = build(live, audit, baseline)

    out = pathlib.Path(args.out)
    (out / "registry").mkdir(parents=True, exist_ok=True)
    today = datetime.date.today().isoformat()
    counts = {
        "live_total": len(records),
        "by_type": dict(collections.Counter(r["post_type"] for r in records)),
        "repo_status": dict(collections.Counter(r["repo"]["status"] for r in records)),
        "missing_featured_image": sum(1 for r in records if "missing_featured_image" in r["flags"]),
        "repo_unpublished": len(unpublished),
    }
    registry = {
        "tool": "sa-live-site-registry",
        "generated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "snapshot_date": today,
        "sources": {"live": str(args.live), "audit": str(args.audit or ""), "baseline_records": len(baseline)},
        "counts": counts,
        "records": records,
        "repo_unpublished": unpublished,
    }
    (out / "registry" / "live-articles.json").write_text(
        json.dumps(registry, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    # per-type readable lists
    for ptype, group in sorted(collections.defaultdict(list, {t: [r for r in records if r["post_type"] == t] for t in counts["by_type"]}).items()):
        rows = [[r["title"], r["slug"], r["word_count"], "دارد" if r["featured_file"] else "—",
                 r["repo"]["status"], ",".join(r["flags"]), r["permalink"]] for r in group]
        (out / f"articles-{ptype}.md").write_text(
            f"# مقالات زنده — {ptype} ({len(group)})\n\n> تولید خودکار از اسنپ‌شات {today}؛ دستی ویرایش نشود. منبع: `registry/live-articles.json`\n\n"
            + table(["عنوان", "نامک", "واژه", "تصویر شاخص", "وضعیت نسبت به مخزن", "پرچم‌ها", "پیوند"], rows), encoding="utf-8")

    # titles index (duplicate prevention)
    lines = [f"# نمایهٔ عنوان‌های سایت زنده ({len(records)})\n",
             "> پیش از ساخت محتوای تازه، عنوان و نامک را این‌جا و در `registry/live-articles.json` چک کنید تا محتوای تکراری ساخته نشود.\n"]
    for ptype in sorted(counts["by_type"]):
        lines.append(f"\n## {ptype}\n")
        for r in sorted([x for x in records if x["post_type"] == ptype], key=lambda x: x["title"] or ""):
            lines.append(f"- {esc(r['title'])} — `{r['slug']}`")
    (out / "titles.md").write_text("\n".join(lines) + "\n", encoding="utf-8")

    # unpublished (clearly marked)
    lines = ["# ⛔ منتشرنشده‌ها: آمادهٔ مخزن، غایب از سایت زنده\n",
             f"> به‌روزرسانی: {today} — این رکوردها در دادهٔ آمادهٔ مخزن هستند ولی در اسنپ‌شات سایت زنده نیستند.\n"]
    if unpublished:
        lines.append(table(["وضعیت", "نوع", "نامک", "عنوان", "FAQ", "متا", "کاور"],
                           [["⛔ منتشر نشده", u["post_type"], u["slug"], u["title"], u["faq_count"], u["meta_count"],
                             "دارد" if u["has_cover"] else "—"] for u in unpublished]))
    else:
        lines.append("موردی نیست؛ همهٔ رکوردهای آمادهٔ مخزن روی سایت زنده‌اند.\n")
    (out / "unpublished.md").write_text("\n".join(lines) + "\n", encoding="utf-8")

    # fix queue
    q = [f"# صف رفع اشکال محتوای زنده\n\n> تولید خودکار از تطبیق {today}. ترتیب پیشنهادی: تکراری‌ها ← اختلاف با مخزن ← نقص‌ها.\n"]
    q.append("\n## ۱) محتوای تکراری (اولویت بالا)\n")
    if dup_titles:
        q.append(table(["عنوان تکراری", "کلیدها"], [[t, " · ".join(ks)] for t, ks in dup_titles.items()]))
    if dup_hashes:
        q.append("\nهش محتوای یکسان:\n\n" + table(["هش (کوتاه)", "کلیدها"],
                 [[h[:12], " · ".join(ks)] for h, ks in dup_hashes.items()]))
    if not dup_titles and not dup_hashes:
        q.append("موردی پیدا نشد.\n")
    diff = [r for r in records if r["repo"]["status"] == "matched_different"]
    q.append(f"\n## ۲) روی سایت هست ولی با مخزن فرق دارد ({len(diff)})\n")
    q.append(table(["کلید", "عنوان", "فیلدهای متفاوت"],
                   [[r["key"], r["title"], ",".join(r["repo"]["diff_fields"])] for r in diff]))
    q.append("\n## ۳) نقص‌های قابل بررسی\n")
    feat_by_type = collections.Counter(r["post_type"] for r in records if "missing_featured_image" in r["flags"])
    q.append("\nبدون تصویر شاخص به تفکیک نوع (برای شهرستان/برگه ممکن است طراحی باشد؛ برای نوشته/دیدنی اولویت بررسی است):\n\n"
             + table(["نوع", "تعداد"], [[t, c] for t, c in sorted(feat_by_type.items())]))
    thin = [r for r in records if any(f.startswith("thin_content") for f in r["flags"])]
    q.append(f"\nمحتوای کوتاه ({len(thin)}):\n\n" + table(["کلید", "عنوان", "واژه"],
             [[r["key"], r["title"], r["word_count"]] for r in thin[:200]]))
    (out / "fix-queue.md").write_text("\n".join(q) + "\n", encoding="utf-8")

    # reconciliation summary + index
    summary = (f"# گزارش تطبیق {today}\n\n"
               f"- مقالهٔ منتشرشدهٔ زنده: **{counts['live_total']}** ({', '.join(f'{t}: {c}' for t, c in sorted(counts['by_type'].items()))})\n"
               f"- وضعیت نسبت به خط مبنای مخزن: {', '.join(f'{k}: {v}' for k, v in sorted(counts['repo_status'].items()))}\n"
               f"- آمادهٔ مخزن ولی منتشرنشده در سایت: **{len(unpublished)}** (فهرست: `unpublished.md`)\n"
               f"- عنوان تکراری: {len(dup_titles)} گروه؛ هش محتوای تکراری: {len(dup_hashes)} گروه (جزئیات: `fix-queue.md`)\n"
               f"- بدون تصویر شاخص: {counts['missing_featured_image']} از {counts['live_total']}\n\n"
               "خط مبنای مخزن فعلاً فقط «مقالات آماده» (۱۰۴ رکورد) است؛ پس `live_only` برای شهرستان‌ها/استان‌ها/برگه‌ها یعنی «در دادهٔ آمادهٔ مخزن نیست»، نه الزاماً مشکل.\n")
    (out / "reconciliation-latest.md").write_text(summary, encoding="utf-8")
    (out / "INDEX.md").write_text(
        "# شبیه‌سازی سایت زنده — نمای کلی\n\n" + summary +
        "\n## فایل‌ها\n\n- `registry/live-articles.json` — رجیستری کامل ماشین‌خوان (منبع اصلی ایجنت‌ها)\n"
        "- `articles-<type>.md` — فهرست خوانا به تفکیک نوع محتوا\n- `titles.md` — نمایهٔ عنوان‌ها برای جلوگیری از تکرار\n"
        "- `unpublished.md` — آماده‌های مخزنِ منتشرنشده ⛔\n- `fix-queue.md` — صف رفع اشکال\n"
        "- `CHANGELOG.md` — تغییرات اعمال‌شده توسط ایجنت بین دو ممیزی\n\nقواعد کار در `README.md` همین پوشه است.\n",
        encoding="utf-8")
    print(json.dumps(counts, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
