#!/usr/bin/env python3
"""دستور اجرای شهرستان — سند پروتکل را قابل‌اجرا می‌کند.

دستور مالک (۲ آذر ۱۴۰۵): «سند رو ثبت کن که برای همه یکسان انجام بدی، هر بار یک
استان رو انجام می‌دیم و ابتدا تک تک انجام بده، از استان خوزستان شروع کن، اول
اهواز.» سند در `docs/live-site/county-run-protocol.md` نوشته شده؛ این ابزار همان
سند را نگه می‌دارد تا قاعده با فراموشی اجراکننده نشکند:

  ۱) **یک استان در هر نوبت** — استان جاری بسته نشده، استان دیگری باز نمی‌شود.
  ۲) **یک شهرستان در هر اجرا** — در هر استان حداکثر یک `in_progress`.
  ۳) **ترتیب از فایل** — ترتیب شهرستان‌ها از `template_worklist.json` می‌آید، نه
     از حافظه؛ استان‌ها هم بر پایهٔ کمینهٔ رتبه مرتب می‌شوند.
  ۴) **«انجام‌شده» سه شاهد می‌خواهد** — بستهٔ ساخت، بریف شاهد، رکورد گیت‌ها.
     بدون یکی از این‌ها `done` رد می‌شود، حتی اگر کسی دستی وضعیت را عوض کند.

وضعیت در `county_run_state.json` نگه داشته می‌شود و Markdown از آن خوانده
می‌شود (همان الگوی `county_ledger.py`): جدولِ سند آینه است، نه منبع.

استفاده:

    python3 tools/wp-agent/county_run.py status
    python3 tools/wp-agent/county_run.py next
    python3 tools/wp-agent/county_run.py start ahvaz
    python3 tools/wp-agent/county_run.py check ahvaz
    python3 tools/wp-agent/county_run.py done ahvaz
    python3 tools/wp-agent/county_run.py block ahvaz --why "بدون دادهٔ مساحت و همسایه"
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent

WORKLIST = HERE / "template_worklist.json"
STATE = HERE / "county_run_state.json"
DOC = ROOT / "docs/live-site/county-run-protocol.md"
OUTBOX = HERE / "outbox"
BRIEFS = ROOT / "docs/live-site/briefs"
CHANGELOG = ROOT / "docs/live-site/CHANGELOG.md"

BUILD_DIR = "20261117-replace-{}"          # what county_page.py writes into
TODAY = "۱۴۰۵/۰۹/۰۲"

STATUSES = ("todo", "in_progress", "blocked", "done", "skip")


# -- صف ---------------------------------------------------------------------
def load_worklist() -> list[dict]:
    rows = json.loads(WORKLIST.read_text(encoding="utf-8"))
    rows.sort(key=lambda r: r["rank"])
    return rows


def province_order(rows: list[dict]) -> list[tuple[str, int]]:
    """(province, rank of its first county) — the order provinces run in."""
    first: dict[str, int] = {}
    count: dict[str, int] = {}
    for r in rows:
        p = r["province"]
        first[p] = min(first.get(p, r["rank"]), r["rank"])
        count[p] = count.get(p, 0) + 1
    return sorted(((p, first[p]) for p in first), key=lambda x: x[1])


def counties_of(rows: list[dict], province: str) -> list[dict]:
    return [r for r in rows if r["province"] == province]


# -- وضعیت --------------------------------------------------------------------
def load_state() -> dict:
    if STATE.is_file():
        data = json.loads(STATE.read_text(encoding="utf-8"))
    else:
        data = {}
    data.setdefault("province", "")
    data.setdefault("counties", {})
    return data


def save_state(state: dict) -> None:
    STATE.write_text(json.dumps(state, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")


def blank() -> dict:
    return {"status": "todo", "date": "", "note": ""}


def row_of(state: dict, slug: str) -> dict:
    return state["counties"].setdefault(slug, blank())


def open_counties(state: dict, province: str, rows: list[dict]) -> list[str]:
    """Slugs of this province a run has opened and not finished."""
    return [r["slug"] for r in counties_of(rows, province)
            if row_of(state, r["slug"])["status"] in ("in_progress", "blocked")]


def next_slug(state: dict, rows: list[dict], province: str) -> str | None:
    """The one county this run should write: in_progress first, then the first todo."""
    order = counties_of(rows, province)
    for r in order:
        if row_of(state, r["slug"])["status"] == "in_progress":
            return r["slug"]
    for r in order:
        if row_of(state, r["slug"])["status"] == "todo":
            return r["slug"]
    return None


# -- شاهدهای «انجام‌شده» -------------------------------------------------------
def build_bundle(slug: str) -> list[Path]:
    """The three outbox files county_page.py writes (empty = not built)."""
    pattern = f"*-replace-{slug}/{slug}-*.html"
    return sorted(OUTBOX.glob(pattern))


def brief_files(slug: str) -> tuple[Path, Path]:
    return BRIEFS / f"{slug}.md", BRIEFS / f"{slug}-evidence.md"


def gate_record(slug: str) -> Path:
    return BRIEFS / f"{slug}-gates.md"


def missing_witnesses(slug: str) -> list[str]:
    """What a `done` row still lacks — the DoD of protocol section 4."""
    missing = []
    if not build_bundle(slug):
        missing.append("بستهٔ ساخت (outbox/*-replace-" + slug + "/*.html)")
    brief, evidence = brief_files(slug)
    if not brief.is_file():
        missing.append(f"بریف {brief.relative_to(ROOT)}")
    if not evidence.is_file():
        missing.append(f"بریف شاهد {evidence.relative_to(ROOT)}")
    if not gate_record(slug).is_file():
        missing.append(f"رکورد پنج گیت {gate_record(slug).relative_to(ROOT)}")
    return missing


# -- دستورها ------------------------------------------------------------------
def cmd_status(args) -> int:
    rows = load_worklist()
    state = load_state()
    provinces = province_order(rows)
    province = state.get("province") or provinces[0][0]
    order = counties_of(rows, province)
    tally: dict[str, int] = {}
    for r in order:
        s = row_of(state, r["slug"])["status"]
        tally[s] = tally.get(s, 0) + 1
    done = tally.get("done", 0)
    print(f"استان جاری: {province} — {done}/{len(order)} شهرستان انجام‌شده")
    for status in STATUSES:
        if tally.get(status):
            print(f"  {status}: {tally[status]}")
    nxt = next_slug(state, rows, province)
    print(f"شهرستان این نوبت: {nxt or '— (استان بسته است)'}")
    opened = [s for s in open_counties(state, province, rows) if row_of(state, s)["status"] == "in_progress"]
    if len(opened) > 1:
        print(f"⚠️  بیش از یک شهرستان باز: {opened} — پروتکل یکی می‌خواهد", file=sys.stderr)
        return 1
    # استان بعدی، وقتی این یکی بسته شد
    if nxt is None:
        idx = [p for p, _ in provinces].index(province)
        following = [p for p, _ in provinces][idx + 1: idx + 2]
        if following:
            print(f"استان بعدی: {following[0]}")
    return 0


def cmd_next(args) -> int:
    rows = load_worklist()
    state = load_state()
    province = state.get("province") or province_order(rows)[0][0]
    slug = next_slug(state, rows, province)
    if not slug:
        print(f"همهٔ شهرستان‌های {province} وضعیت نهایی دارند؛ استان را ببندید.", file=sys.stderr)
        return 1
    print(slug)
    return 0


def cmd_start(args) -> int:
    rows = load_worklist()
    state = load_state()
    row = next((r for r in rows if r["slug"] == args.slug), None)
    if row is None:
        print(f"«{args.slug}» در worklist نیست", file=sys.stderr)
        return 1
    province = row["province"]
    if state.get("province") and state["province"] != province:
        open_before = [s for s in open_counties(state, state["province"], rows)
                       if row_of(state, s)["status"] == "in_progress"]
        if open_before:
            print(f"استان {state['province']} بسته نشده ({open_before} باز است); "
                  f"یک استان در هر نوبت", file=sys.stderr)
            return 1
    state["province"] = province
    busy = [s for s in open_counties(state, province, rows)
            if row_of(state, s)["status"] == "in_progress" and s != args.slug]
    if busy:
        print(f"در {province} شهرستان باز دیگری هست: {busy} — شهرستان‌ها تک‌تک", file=sys.stderr)
        return 1
    row_state = row_of(state, args.slug)
    row_state["status"] = "in_progress"
    row_state["date"] = TODAY
    if args.note:
        row_state["note"] = args.note
    save_state(state)
    print(f"باز شد: {args.slug} ({province}) — {TODAY}")
    return 0


def cmd_check(args) -> int:
    """گام‌های ۶ تا ۸ پروتکل، پشت سر هم، بدون نوشتن در سایت."""
    slug = args.slug
    build_dir = OUTBOX / BUILD_DIR.format(slug)
    steps = [
        ("ساخت", [sys.executable, str(HERE / "replace_src/county_page.py"), slug]),
        ("نگهبان قالب", [sys.executable, str(HERE / "replace_src/check_template.py"), str(build_dir)]),
        ("فیلتر فارسی", [sys.executable, str(HERE / "replace_src/fa_lint.py"), str(build_dir)]),
    ]
    for label, argv in steps:
        print(f"— {label}")
        proc = subprocess.run(argv, cwd=ROOT)
        if proc.returncode != 0:
            print(f"FAIL {label} ({slug}) — چیزی منتشر نمی‌شود", file=sys.stderr)
            return proc.returncode or 1
    print(f"PASS ساخت + نگهبان + فیلتر ({slug}). پنج گیت انسانی بماند.")
    return 0


def cmd_done(args) -> int:
    rows = load_worklist()
    state = load_state()
    if not any(r["slug"] == args.slug for r in rows):
        print(f"«{args.slug}» در worklist نیست", file=sys.stderr)
        return 1
    missing = missing_witnesses(args.slug)
    if missing:
        print(f"«{args.slug}» انجام‌شده نیست؛ این‌ها کم است:", file=sys.stderr)
        for item in missing:
            print(f"  - {item}", file=sys.stderr)
        return 1
    row_state = row_of(state, args.slug)
    row_state["status"] = "done"
    row_state["date"] = TODAY
    if args.note:
        row_state["note"] = args.note
    save_state(state)
    print(f"انجام شد: {args.slug} — {TODAY} (سه شاهد کامل)")
    return 0


def cmd_block(args) -> int:
    rows = load_worklist()
    state = load_state()
    if not any(r["slug"] == args.slug for r in rows):
        print(f"«{args.slug}» در worklist نیست", file=sys.stderr)
        return 1
    if not args.why.strip():
        print("دلیل لازم است: --why \"...\" (پروتکل `blocked` بی‌دلیل ندارد)", file=sys.stderr)
        return 1
    row_state = row_of(state, args.slug)
    row_state["status"] = "blocked"
    row_state["date"] = TODAY
    row_state["note"] = args.why.strip()
    save_state(state)
    print(f"متوقف شد: {args.slug} — {args.why.strip()}")
    return 0


# -- آینهٔ سند ----------------------------------------------------------------
def render_table(rows: list[dict], state: dict, province: str) -> str:
    fa = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
    lines = ["| # | شهرستان | نامک | شناسه | وضعیت |", "| --- | --- | --- | --- | --- |"]
    for i, r in enumerate(counties_of(rows, province), 1):
        s = row_of(state, r["slug"])["status"]
        note = row_of(state, r["slug"]).get("note") or ""
        status = {"todo": "در صف", "in_progress": "در حال کار", "blocked": "متوقف",
                  "done": "انجام‌شده", "skip": "خارج از دامنه"}[s]
        if s == "blocked" and note:
            status += f" — {note}"
        lines.append(f"| {str(i).translate(fa)} | {r['name']} | `{r['slug']}` | "
                     f"{str(r['id']).translate(fa)} | {status} |")
    return "\n".join(lines)


def cmd_sync(args) -> int:
    """جدول صف در سند را از JSON بازمی‌نویسد (سند آینه است، نه منبع)."""
    rows = load_worklist()
    state = load_state()
    province = state.get("province") or "khuzestan"
    text = DOC.read_text(encoding="utf-8")
    table = render_table(rows, state, province)
    pattern = re.compile(r"(\| # \| شهرستان \| نامک \| شناسه \| وضعیت \|\n\| --- .*?\n)(?:\|.*\n)+")
    if not pattern.search(text):
        print("جدول صف در سند پیدا نشد", file=sys.stderr)
        return 1
    DOC.write_text(pattern.sub(lambda m: m.group(1) + table.split("\n", 2)[2] + "\n", text, count=1),
                  encoding="utf-8")
    print(f"جدول صف {province} در سند به‌روز شد")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = parser.add_subparsers(dest="cmd", required=True)

    sub.add_parser("status").set_defaults(fn=cmd_status)
    sub.add_parser("next").set_defaults(fn=cmd_next)
    sub.add_parser("sync").set_defaults(fn=cmd_sync)

    p = sub.add_parser("start"); p.add_argument("slug"); p.add_argument("--note", default="")
    p.set_defaults(fn=cmd_start)
    p = sub.add_parser("check"); p.add_argument("slug"); p.set_defaults(fn=cmd_check)
    p = sub.add_parser("done"); p.add_argument("slug"); p.add_argument("--note", default="")
    p.set_defaults(fn=cmd_done)
    p = sub.add_parser("block"); p.add_argument("slug"); p.add_argument("--why", required=True)
    p.set_defaults(fn=cmd_block)

    args = parser.parse_args(argv[1:])
    return args.fn(args)


if __name__ == "__main__":
    sys.exit(main(sys.argv))
