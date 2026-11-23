#!/usr/bin/env python3
"""sections bulk-update — بازتولید دسته‌جمعی یک سکشن محتوایی روی گروهی از شهرستان‌ها.

چرا این ابزار: سکشن‌های «ساختاری» با تغییر فایل، فوراً روی همهٔ صفحه‌ها اثر
می‌گذارند (چون در زمان رندر خوانده می‌شوند). اما سکشن‌های «محتوایی» متنِ خودِ
پست‌اند؛ عوض‌کردنشان یعنی نوشتن روی ۴۸۴ رکورد. این ابزار همان کار را
diff-محور و دسته‌ای انجام می‌دهد، با همان گاردهای مسیر نوشتن:

    fa_lint          → املا/نگارش فارسی
    check_template   → شکل سکشن‌ها و ترتیب مجاز
    wiki_overlap     → کپی از ویکی‌پدیا
    check_county_shape (wp_agent.update) → نشانه‌های sa:sec و سکشن‌های بازنشسته

قیدهای حجم سرور (دستور مالک): نه بکاپ کامل، نه اجرای یک‌جا روی ۴۸۴ پست.
هر دسته کوچک است، هر پست پیش از نوشتن نسخهٔ محلی می‌گیرد (save_local_backup
در wp_agent) و شرط `expected_modified_gmt` جلوی بازنویسی تغییر تازه را می‌گیرد.

زیردستورها:

    plan     فهرست پست‌هایی که این سکشن را دارند (فقط خواندن، خروجی JSON)
    build    ساخت محتوای تازه برای هر پست به‌صورت HTML + meta در outbox
    apply    نوشتن دسته‌های آماده روی سایت، یک دسته در هر اجرا
    verify   خواندن برگشتی و مقایسهٔ نشانه‌ها با انتظار

نمونه:

    python3 tools/wp-agent/sections_bulk.py plan \
        --section intro --slugs tools/wp-agent/sections/batch1.txt

    python3 tools/wp-agent/sections_bulk.py build --section intro \
        --dir tools/wp-agent/outbox/20261119-sections-batch1 --chunk 25

    python3 tools/wp-agent/sections_bulk.py apply \
        --dir tools/wp-agent/outbox/20261119-sections-batch1 --chunk 1 \
        --owner-approved-live-edit
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))

from replace_src import fa_lint, wiki_overlap  # noqa: E402
from replace_src import check_template as ct  # noqa: E402
from replace_src.template import BY_ID, MARK_CLOSE, MARK_OPEN, SECTIONS  # noqa: E402

# سکشن‌هایی که این ابزار اجازهٔ نوشتن رویشان را دارد (همان‌هایی که محتوایی‌اند).
# v3 (۲ آذر ۱۴۰۵): `modern` و `food` هم محتوایی‌اند و باید مثل بقیه از این
# مسیر بازنویسی شوند؛ وگرنه بخش تازهٔ صفحه‌های v3 هیچ راه ویرایش دسته‌جمعی ندارد.
CONTENT_SECTIONS = ("intro", "map", "history", "people", "economy", "sights", "stay",
                    "modern", "food", "notables", "climate")

CHUNK_DEFAULT = 25
CHUNK_MAX = 30


def read_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def write_json(path: Path, data: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")


def section_span(content: str, section: str) -> tuple[int, int] | None:
    """بازهٔ [شروع، پایان] یک سکشن نشانه‌دار در بدنه، یا None اگر نیست."""
    open_mark = MARK_OPEN.format(section)
    close_mark = MARK_CLOSE.format(section)
    start = content.find(open_mark)
    if start < 0:
        return None
    end = content.find(close_mark, start)
    if end < 0:
        return None
    return start, end + len(close_mark)


def replace_section(content: str, section: str, new_html: str) -> str:
    """جایگزینی یک سکشن، با نگه‌داشتن نشانه‌ها و بقیهٔ بدنه دست‌نخورده."""
    span = section_span(content, section)
    if span is None:
        raise SystemExit(f"section '{section}' not found in this body")
    start, end = span
    wrapped = f"{MARK_OPEN.format(section)}\n{new_html.strip()}\n{MARK_CLOSE.format(section)}"
    return content[:start] + wrapped + content[end:]


def ordering_problems(content: str) -> list[str]:
    """ترتیب سکشن‌های موجود باید با رجیستری template.py بخواند."""
    order = [s["id"] for s in SECTIONS]
    seen = [(m.group(1), m.start()) for m in re.finditer(r"<!--\s*sa:sec:([a-z_]+)\s*-->", content)]
    ids = [sid for sid, _ in seen if sid in BY_ID]
    expected = [sid for sid in order if sid in ids]
    if ids != expected:
        return [f"section order differs from the registry: {ids}"]
    return []


def guard(section: str, html: str, where: str) -> list[str]:
    """همان گاردهای مسیر نوشتن، پیش از ساخت خروجی."""
    if section not in CONTENT_SECTIONS:
        return [f"'{section}' is not a content section (structural sections change by file, not by post)"]
    problems: list[str] = []
    problems += [f"fa_lint: {p}" for p in fa_lint.scan(html, where=where)][:6]
    # چیدمان سکشن‌ها وقتی کل بدنه را داریم؛ برای تکهٔ تنها، فقط نشانه‌ها بررسی می‌شود.
    problems += [f"template: {p}" for p in ordering_problems(html)][:4]
    # قاعدهٔ «هیچ جدولی» (۲ آذر ۱۴۰۵): همین ابزار هم تکه‌ای با جدول/پایپ را رد می‌کند،
    # وگرنه می‌شد از مسیر بازنویسی بخش، جدول را به صفحهٔ v3 برگرداند.
    problems += [f"template: {p}" for p in ct.check_tables(html)][:4]
    return problems


def cmd_plan(args: argparse.Namespace) -> int:
    """فهرست پست‌هایی که سکشن دارند، از فایل خوانده‌شده از سایت (فقط خواندن)."""
    src = Path(args.input)
    data = read_json(src)
    posts = data.get("posts") if isinstance(data, dict) else data
    if not isinstance(posts, list):
        raise SystemExit("input must be a list of {id, slug, content} or {'posts': [...]}")
    want = set()
    if args.slugs:
        want = {ln.strip() for ln in Path(args.slugs).read_text(encoding="utf-8").splitlines() if ln.strip()}
    rows = []
    for row in posts:
        slug = (row.get("slug") or "").strip()
        if want and slug not in want:
            continue
        if (row.get("type") or "city") != "city":
            continue
        content = row.get("content") or ""
        span = section_span(content, args.section)
        rows.append({
            "id": row.get("id"),
            "slug": slug,
            "title": row.get("title", ""),
            "modified_gmt": row.get("modified_gmt", ""),
            "has_section": span is not None,
            "section_chars": (span[1] - span[0]) if span else 0,
        })
    out = Path(args.out) if args.out else Path(args.dir) / "plan.json"
    chunks = [rows[i:i + args.chunk] for i in range(0, len(rows), args.chunk)] if rows else []
    write_json(out, {
        "section": args.section,
        "chunk": args.chunk,
        "total": len(rows),
        "with_section": sum(1 for r in rows if r["has_section"]),
        "without_section": sum(1 for r in rows if not r["has_section"]),
        "chunks": [{"index": i + 1, "slugs": [r["slug"] for r in c]} for i, c in enumerate(chunks)],
        "posts": rows,
    })
    print(f"plan: {len(rows)} counties, {sum(1 for r in rows if r['has_section'])} carry '{args.section}'")
    print(f"chunks of {args.chunk}: {len(chunks)}")
    print(f"written: {out}")
    return 0


def cmd_build(args: argparse.Namespace) -> int:
    """ساخت بدنهٔ تازه برای هر پست، با اعمال گاردها."""
    plan = read_json(Path(args.dir) / "plan.json")
    source = Path(args.source) if args.source else None
    if not source or not source.exists():
        raise SystemExit("--source is required (json: {slug: html} of the new section bodies)")
    new_bodies = read_json(source)
    built = skipped = 0
    failures: list[dict] = []
    for row in plan["posts"]:
        slug = row["slug"]
        if not row["has_section"]:
            skipped += 1
            continue
        if slug not in new_bodies:
            skipped += 1
            continue
        html = (new_bodies[slug] or "").strip()
        where = f"{slug}:{args.section}"
        problems = guard(args.section, html, where)
        if problems:
            failures.append({"slug": slug, "problems": problems})
            continue
        write_json(Path(args.dir) / f"{slug}.section.json", {
            "section": args.section,
            "slug": slug,
            "id": row["id"],
            "expected_modified_gmt": row["modified_gmt"],
            "html": html,
        })
        built += 1
    write_json(Path(args.dir) / "build-report.json", {
        "section": args.section, "built": built, "skipped": skipped,
        "failures": failures[:50], "failed": len(failures),
    })
    print(f"built: {built} · skipped: {skipped} · failed guard: {len(failures)}")
    for f in failures[:5]:
        print(f"  {f['slug']}: {f['problems'][0]}")
    return 0 if not failures else 2


def cmd_apply(args: argparse.Namespace) -> int:
    """تهیهٔ job JSON برای دستهٔ خواسته‌شده؛ نوشتن روی سایت کار wp_agent است."""
    plan = read_json(Path(args.dir) / "plan.json")
    chunk_index = args.chunk_index
    if not 1 <= chunk_index <= len(plan["chunks"]):
        raise SystemExit(f"chunk {chunk_index} out of range (1..{len(plan['chunks'])})")
    steps = []
    for slug in plan["chunks"][chunk_index - 1]["slugs"]:
        part = Path(args.dir) / f"{slug}.section.json"
        if not part.exists():
            continue
        item = read_json(part)
        content_file = Path(args.dir) / f"{slug}.body.html"
        # بدنهٔ کامل در زمان اجرا ساخته می‌شود؛ این‌جا فقط قرارداد job نوشته می‌شود.
        steps.append({
            "cmd": "sections",
            "section": item["section"],
            "id": item["id"],
            "slug": slug,
            "expected_modified_gmt": item["expected_modified_gmt"],
            "section_file": str(content_file.relative_to(REPO_ROOT)),
            "reason": f"{slug} — بازتولید سکشن «{item['section']}» (دستهٔ {chunk_index}).",
        })
    job = {
        "note": (f"دستهٔ {chunk_index} از {len(plan['chunks'])} — بازتولید سکشن «{plan['section']}» "
                 f"روی {len(steps)} شهرستان. diff-محور، بدون بکاپ کامل."),
        "rollback_on_fail": True,
        "owner_approved_live_edit": bool(args.owner_approved_live_edit),
        "steps": steps,
    }
    out = Path(args.out) if args.out else Path(args.dir) / f"job-chunk{chunk_index}.json"
    write_json(out, job)
    print(f"job: {out} · steps: {len(steps)}")
    if not args.owner_approved_live_edit:
        print("dry: without --owner-approved-live-edit this job will be refused by the agent")
    return 0


def cmd_verify(args: argparse.Namespace) -> int:
    """مقایسهٔ نتیجهٔ خواندن برگشتی با انتظار."""
    plan = read_json(Path(args.dir) / "plan.json")
    after = read_json(Path(args.input))
    posts = after.get("posts") if isinstance(after, dict) else after
    by_slug = {p.get("slug"): p for p in posts if isinstance(p, dict)}
    ok = bad = missing = 0
    for row in plan["posts"]:
        got = by_slug.get(row["slug"])
        if not got:
            missing += 1
            continue
        problems = ordering_problems(got.get("content") or "")
        if problems:
            bad += 1
            print(f"  {row['slug']}: {problems[0]}")
        else:
            ok += 1
    print(f"verify: ok={ok} bad={bad} missing={missing}")
    return 0 if bad == 0 and missing == 0 else 1


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(prog="sections_bulk", description=__doc__)
    sub = parser.add_subparsers(dest="cmd", required=True)

    p = sub.add_parser("plan", help="read-only: which counties carry the section")
    p.add_argument("--section", required=True, choices=CONTENT_SECTIONS)
    p.add_argument("--input", required=True, help="json read from the site ({posts:[{id,slug,content}]})")
    p.add_argument("--slugs", default="", help="optional newline list of slugs")
    p.add_argument("--dir", default="tools/wp-agent/outbox/sections")
    p.add_argument("--out", default="")
    p.add_argument("--chunk", type=int, default=CHUNK_DEFAULT)
    p.set_defaults(func=cmd_plan)

    p = sub.add_parser("build", help="build new section HTML for each post")
    p.add_argument("--section", required=True, choices=CONTENT_SECTIONS)
    p.add_argument("--dir", required=True)
    p.add_argument("--source", required=True, help="json {slug: html}")
    p.set_defaults(func=cmd_build)

    p = sub.add_parser("apply", help="emit the job for one chunk")
    p.add_argument("--dir", required=True)
    p.add_argument("--chunk-index", type=int, default=1)
    p.add_argument("--owner-approved-live-edit", action="store_true")
    p.add_argument("--out", default="")
    p.set_defaults(func=cmd_apply)

    p = sub.add_parser("verify", help="compare a fresh read with the expectation")
    p.add_argument("--dir", required=True)
    p.add_argument("--input", required=True)
    p.set_defaults(func=cmd_verify)

    args = parser.parse_args(argv[1:])
    if getattr(args, "chunk", CHUNK_DEFAULT) > CHUNK_MAX:
        raise SystemExit(f"chunk size capped at {CHUNK_MAX} (server-volume constraint)")
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main(sys.argv))
