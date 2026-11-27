#!/usr/bin/env python3
"""سنجش ساختار دامپ SQL وردپرس (فقط‌خواندنی، بدون وابستگی: sqlite3 استاندارد).

ورودی: دامپ MariaDB/MySQL (`.sql` یا `.sql.gz`)، یا خروجی CSV/LibreOffice با فواصل `;`.
خروجی: `docs/live-site/SITE-STRUCTURE.json` و `SITE-STRUCTURE.md` (نسخهٔ ماشینی + انسانی).

چه چیزی می‌سنجد؟
  * شمارش هر نوع‌محتوا به تفکیک وضعیت (province / city / attraction / guide / region / page…)
  * بودجهٔ واژه در هر نوع‌محتوا (میانگین، میانه، کمینه/بیشینه، تعداد زیر ۷۰۰ واژه)
  * پوشش متای SEO/منابع/FAQ/تصویر شاخص برای هر نوع‌محتوا
  * تاکسونومی‌ها و توزیع شهرها در استان‌ها (پایهٔ قانون خوشه)
  * موتور ممیزی (`iaa_*`) و مشارکت کاربران (`cc_*`) اگر در دامپ باشند
  * نسخهٔ قالب و گزینه‌های کلیدی (siteurl/home) برای تشخیص مهاجرت دامنه

این ابزار هیچ نوشتنی در سایت یا دامپ ندارد و فقط فایل‌های مستندات را تولید می‌کند.
"""
from __future__ import annotations

import argparse
import gzip
import json
import re
import sqlite3
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SAFE_COL = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")
ESC = {"n": "\n", "r": "\r", "t": "\t", "0": "\0", "Z": "\x1a", "b": "\b", "f": "\f", "v": "\v"}
NEEDED = {"posts", "postmeta", "terms", "term_taxonomy", "term_relationships", "options",
          "users", "iaa_reports", "iaa_issues", "iaa_claims", "iaa_jobs", "cc_ratings", "comments"}


# ---------------------------------------------------------------- parsing helpers
def unquote(raw: str | None):
    if raw is None:
        return None
    raw = raw.strip()
    if raw.upper() == "NULL":
        return None
    if not raw.startswith("'"):
        return raw
    raw = raw[1:-1] if raw.endswith("'") else raw[1:]
    raw = raw.replace("''", "'")
    out, i = [], 0
    while i < len(raw):
        if raw[i] == "\\" and i + 1 < len(raw):
            out.append(ESC.get(raw[i + 1], raw[i + 1]))
            i += 2
        else:
            out.append(raw[i])
            i += 1
    return "".join(out)


def split_rows(payload: str) -> list[list[str]]:
    rows, i, n = [], 0, len(payload)
    while i < n:
        if payload[i] != "(":
            i += 1
            continue
        i += 1
        fields, buf, in_quote = [], [], False
        while i < n:
            ch = payload[i]
            if in_quote:
                if ch == "\\" and i + 1 < n:
                    buf.append(payload[i:i + 2])
                    i += 2
                    continue
                if ch == "'":
                    if i + 1 < n and payload[i + 1] == "'":
                        buf.append("''")
                        i += 2
                        continue
                    in_quote = False
                    i += 1
                    continue
                buf.append(ch)
                i += 1
                continue
            if ch == "'":
                in_quote = True
                buf.append("'")
                i += 1
                continue
            if ch == ",":
                fields.append("".join(buf))
                buf = []
                i += 1
                continue
            if ch == ")":
                fields.append("".join(buf))
                i += 1
                break
            buf.append(ch)
            i += 1
        rows.append(fields)
        while i < n and payload[i] in ",\r\n\t ":
            i += 1
    return rows


def rows_from_sql(path: Path, prefix: str):
    """جریان (جدول، ستون‌ها، ردیف‌ها) از دامپ SQL."""
    opener = gzip.open if path.suffix == ".gz" else open
    with opener(path, "rt", encoding="utf-8", errors="replace") as fh:
        pending: list[str] = []
        for line in fh:
            if pending:
                pending.append(line)
                if line.rstrip().endswith(";"):
                    text = "".join(pending)
                    pending = []
                    yield from _statements(text, prefix)
                continue
            if not line.startswith("INSERT INTO `"):
                continue
            if not line.rstrip().endswith(";"):
                pending = [line]
                continue
            yield from _statements(line, prefix)


def _statements(text: str, prefix: str):
    m = re.match(r"^INSERT INTO `([^`]+)`\s*(?:\(([^)]*)\))?", text)
    if not m:
        return
    raw = m.group(1)
    name = raw[len(prefix):] if raw.startswith(prefix) else raw
    if name not in NEEDED or " VALUES " not in text:
        return
    header = [c.strip().strip("`") for c in m.group(2).split(",")] if m.group(2) else []
    if header and not all(SAFE_COL.match(c) for c in header):
        return
    body = text.split(" VALUES ", 1)[1].rstrip().rstrip(";")
    yield name, header, split_rows(body)


# ---------------------------------------------------------------- SQLite loader
def load(rows, db_path: Path) -> tuple[sqlite3.Connection, dict]:
    if db_path.exists():
        db_path.unlink()
    con = sqlite3.connect(db_path)
    con.text_factory = lambda b: b.decode("utf-8", "replace")
    cur = con.cursor()
    cur.execute("PRAGMA journal_mode=OFF")
    cur.execute("PRAGMA synchronous=OFF")
    tables: dict[str, list[str]] = {}
    counts: dict[str, int] = defaultdict(int)
    buffers: dict[str, list[list[str]]] = defaultdict(list)

    def ensure(table: str, cols: list[str]) -> list[str] | None:
        if table in tables:
            return tables[table] if len(tables[table]) == len(cols) else None
        if not cols or not all(SAFE_COL.match(c) for c in cols):
            return None
        cur.execute(f'DROP TABLE IF EXISTS "{table}"')
        cur.execute(f'CREATE TABLE "{table}" ({", ".join(chr(34) + c + chr(34) + " TEXT" for c in cols)})')
        tables[table] = cols
        return cols

    def flush(table: str):
        rows_ = buffers[table]
        if rows_:
            cols = tables[table]
            cur.executemany(
                f'INSERT INTO "{table}" VALUES ({", ".join("?" * len(cols))})',
                [[unquote(x) for x in r] for r in rows_ if len(r) == len(cols)],
            )
            counts[table] += len(rows_)
        buffers[table] = []

    for table, cols, payload in rows:
        if not table:
            continue
        got = ensure(table, cols)
        if not got:
            continue
        buffers[table].extend(payload)
        if len(buffers[table]) >= 5000:
            flush(table)
    for table in list(buffers):
        flush(table)
    con.commit()
    return con, dict(counts)


# ---------------------------------------------------------------- statistics
def word_count(html: str | None) -> int:
    if not html:
        return 0
    text = re.sub(r"<[^>]+>", " ", html)
    text = re.sub(r"&[a-zA-Z#0-9]+;", " ", text)
    return len([w for w in re.split(r"\s+", text) if len(w) > 1])


def collect_stats(cur: sqlite3.Cursor) -> dict:
    def all_(sql, args=()):
        return cur.execute(sql, args).fetchall()

    def one(sql, args=()):
        row = cur.execute(sql, args).fetchone()
        return row[0] if row else None

    def has(table):
        return cur.execute(
            "select count(*) from sqlite_master where type='table' and name=?", (table,)
        ).fetchone()[0] > 0

    out: dict = {"posts_by_type_status": {}, "budgets": {}, "taxonomies": {},
                 "province_terms": [], "cities_per_province": [], "community": {},
                 "audit_engine": {}, "key_options": {}}
    if not has("posts"):
        return out

    out["posts_by_type_status"] = {
        f"{t}|{s}": n for t, s, n in all_(
            "select post_type, post_status, count(*) from posts group by 1,2 order by 3 desc")
    }

    for ptype in ("province", "city", "attraction", "guide", "region", "page"):
        ids = [r[0] for r in all_(
            "select ID from posts where post_type=? and post_status='publish'", (ptype,))]
        words = sorted(word_count(c) for (c,) in all_(
            "select post_content from posts where post_type=? and post_status='publish'", (ptype,)))
        n = len(words)
        coverage = {}
        if ids and has("postmeta"):
            marks = ",".join("?" * len(ids))
            for key in ("sa_seo_title", "sa_seo_description", "sa_sources", "sa_faq", "_thumbnail_id"):
                coverage[key] = one(
                    "select count(distinct post_id) from postmeta where meta_key=? "
                    f"and length(trim(meta_value))>2 and post_id in ({marks})", (key, *ids))
        out["budgets"][ptype] = {
            "count": n,
            "avg_words": round(sum(words) / n) if n else 0,
            "median_words": words[n // 2] if n else 0,
            "min_words": words[0] if n else 0,
            "max_words": words[-1] if n else 0,
            "under_700": len([w for w in words if w < 700]),
            "meta_coverage": coverage,
        }

    if has("term_taxonomy"):
        out["taxonomies"] = {
            tax: {"terms": n, "tagged": int(tagged or 0)}
            for tax, n, tagged in all_(
                "select taxonomy, count(*), sum(cast(count as integer)) from term_taxonomy "
                "group by 1 order by 2 desc")
        }
        out["province_terms"] = [
            {"name": name, "slug": slug, "cities": int(c or 0)}
            for name, slug, c in all_(
                "select t.name, t.slug, tt.count from term_taxonomy tt join terms t "
                "on t.term_id=tt.term_id where tt.taxonomy='province_tax' "
                "order by cast(tt.count as integer) desc")
        ]
        out["cities_per_province"] = [
            {"province": name, "slug": slug, "cities": n}
            for name, slug, n in all_(
                "select t.name, t.slug, count(distinct p.ID) from term_relationships tr "
                "join term_taxonomy tt on tt.term_taxonomy_id=tr.term_taxonomy_id "
                "join terms t on t.term_id=tt.term_id "
                "join posts p on p.ID=tr.object_id and p.post_type='city' and p.post_status='publish' "
                "where tt.taxonomy='province_tax' group by 1,2 order by 3 desc")
        ]
        out["attractions_with_city"] = (
            one("select count(*) from postmeta where meta_key='sa_city_id'")
            if has("postmeta") else 0)

    out["community"] = {
        "ratings": one("select count(*) from cc_ratings") if has("cc_ratings") else 0,
        "comments": one("select count(*) from comments") if has("comments") else 0,
        "submissions": {
            s: n for s, n in all_(
                "select post_status, count(*) from posts where post_type='cc_submission' group by 1")
        },
    }
    if has("iaa_reports"):
        out["audit_engine"] = {
            "reports": one("select count(*) from iaa_reports"),
            "issues": one("select count(*) from iaa_issues") if has("iaa_issues") else 0,
            "claims": one("select count(*) from iaa_claims") if has("iaa_claims") else 0,
            "report_dates": [d for (d,) in all_(
                "select distinct substr(post_modified_gmt,1,10) from iaa_reports "
                "order by 1 desc limit 3")],
        }
    if has("options"):
        for name in ("siteurl", "home", "blogname", "template", "stylesheet", "current_theme",
                     "permalink_structure", "sa_child_version", "sa_laa_version", "show_on_front"):
            val = one("select option_value from options where option_name=?", (name,))
            if val is not None:
                out["key_options"][name] = val
        out["agent_backups"] = one(
            "select count(*) from posts where post_type='sa_agent_backup'") or 0
    return out


# ---------------------------------------------------------------- markdown render
def render_md(data: dict, source: str) -> str:
    b = data["budgets"]
    L: list[str] = []
    A = L.append
    A("# ساختار فعلی سایت — تصویر ماشینی از دامپ پایگاه‌داده")
    A("")
    A(f"> منبع: `{source}` · ساخته‌شده با `tools/ops/site_structure.py` (فقط‌خواندنی).")
    A("> اعداد این فایل از **دادهٔ خود سایت** است، نه برآورد. برای بازسازی: ")
    A("> `python3 tools/ops/site_structure.py --sql <dump.sql.gz>`")
    A("")
    A("---")
    A("")
    A("## ۱. شمارش نوع‌محتوا (منتشرشده)")
    A("")
    A("| نوع | منتشرشده | پیش‌نویس | سایر |")
    A("|---|---:|---:|---:|")
    by_type_status = data["posts_by_type_status"]
    types = ["province", "city", "attraction", "guide", "region", "page",
             "cc_submission", "sa_agent_backup", "attachment"]
    for t in types:
        pub = sum(v for k, v in by_type_status.items() if k.startswith(t + "|") and "publish" in k)
        draft = sum(v for k, v in by_type_status.items() if k.startswith(t + "|") and "draft" in k)
        other = sum(v for k, v in by_type_status.items() if k.startswith(t + "|")) - pub - draft
        if pub or draft or other:
            A(f"| `{t}` | {pub} | {draft} | {other} |")
    A("")
    A("## ۲. بودجهٔ واژه و پوشش متا")
    A("")
    A("| نوع | تعداد | میانگین واژه | میانه | کمینه | زیر ۷۰۰ واژه | SEO/منبع/FAQ | تصویر شاخص |")
    A("|---|---:|---:|---:|---:|---:|---:|---:|")
    for t in ("province", "city", "attraction", "guide"):
        d = b.get(t, {})
        if not d.get("count"):
            continue
        cov = d.get("meta_coverage", {})
        A(f"| `{t}` | {d['count']} | {d['avg_words']} | {d['median_words']} | {d['min_words']} | "
          f"{d['under_700']} | {cov.get('sa_seo_title', 0)}/{cov.get('sa_sources', 0)}/"
          f"{cov.get('sa_faq', 0)} | {cov.get('_thumbnail_id', 0)} |")
    A("")
    A("## ۳. توزیع شهرها در استان‌ها (پایهٔ قانون خوشه)")
    A("")
    cpp = data.get("cities_per_province") or []
    if cpp:
        A("| استان | slug | شهرستان منتشرشده |")
        A("|---|---|---:|")
        for r in cpp:
            A(f"| {r['province']} | `{r['slug']}` | {r['cities']} |")
        A("")
        A(f"**جمع:** {sum(r['cities'] for r in cpp)} شهرستان در {len(cpp)} استان.")
    A("")
    A("## ۴. تاکسونومی‌ها")
    A("")
    A("| تاکسونومی | تعداد ترم | تعداد شیء |")
    A("|---|---:|---:|")
    for tax, d in data["taxonomies"].items():
        A(f"| `{tax}` | {d['terms']} | {d['tagged']} |")
    A("")
    return "\n".join(L)


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="تصویر ماشینی از ساختار دامپ سایت (فقط‌خواندنی)")
    ap.add_argument("--sql", required=True, type=Path, help="دامپ .sql / .sql.gz")
    ap.add_argument("--prefix", default="IR_", help="پیشوند جداول (پیش‌فرض IR_)")
    ap.add_argument("--out-dir", type=Path, default=ROOT / "docs/live-site")
    ap.add_argument("--workdir", type=Path, default=Path("/tmp/sa-site-structure"))
    args = ap.parse_args(argv)

    args.workdir.mkdir(parents=True, exist_ok=True)
    rows = rows_from_sql(args.sql, args.prefix)
    source = args.sql.name
    con, counts = load(rows, args.workdir / "site.db")
    data = collect_stats(con.cursor())
    data["dump_tables_loaded"] = counts
    data["source_file"] = source
    con.close()

    args.out_dir.mkdir(parents=True, exist_ok=True)
    (args.out_dir / "SITE-STRUCTURE.json").write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (args.out_dir / "SITE-STRUCTURE.md").write_text(render_md(data, source) + "\n", encoding="utf-8")
    print(json.dumps({"source": source, "tables": counts,
                      "types": data["posts_by_type_status"]}, ensure_ascii=False, indent=1))
    return 0


if __name__ == "__main__":
    sys.exit(main())
