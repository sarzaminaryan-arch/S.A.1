"""خط فرمان ai_cleaner: scan | next | status | revert

- scan: فقط گزارش، هیچ تغییری نمی‌دهد.
- next: فقط «یک» دسته، بعد توقف کامل تا فرمان بعدی.
- status: دسته‌های انجام‌شده و باقی‌مانده.
- revert --batch NNN: برگرداندن کامیت یک دسته با git revert (بدون force).
"""
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path

from .batch import (
    BATCH_SIZE,
    DEFAULT_CONTENT_DIR,
    compute_duplicates,
    discover_files,
    discover_with_fallback,
    load_progress,
    make_batches,
    process_files,
    save_progress,
)
from .github_ops import ensure_batch_branch, git_current_branch, load_env, publish_batch
from .patterns import load_patterns
from .report import write_csv, write_html


def _summary_line(label: str, r) -> str:
    return (
        f"{label}: مقاله‌های بررسی‌شده={r.scanned}، تغییرکرده={r.changed}، "
        f"جمله‌های حذف‌شده={r.deleted_sentences}، موارد FLAG={r.flagged}، "
        f"بررسی دستی={r.manual_review_files}"
    )


def _add_common(ap: argparse.ArgumentParser) -> None:
    ap.add_argument("--root", default=".", help="ریشهٔ مخزن (پیش‌فرض: پوشهٔ جاری)")
    ap.add_argument("--content-dir", default=DEFAULT_CONTENT_DIR,
                    help=f"پوشهٔ مقاله‌ها نسبت به ریشه (پیش‌فرض: {DEFAULT_CONTENT_DIR})")
    ap.add_argument("--patterns", default=None, help="مسیر patterns.yaml (پیش‌فرض: کنار بسته)")
    ap.add_argument("--batch-size", type=int, default=BATCH_SIZE)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="ai_cleaner", description="پاک‌سازی جمله‌های هوش مصنوعی از مقاله‌ها")
    sub = parser.add_subparsers(dest="command", required=True)

    p_scan = sub.add_parser("scan", help="بررسی کل مخزن و ساخت گزارش؛ هیچ تغییری نمی‌دهد")
    _add_common(p_scan)

    p_next = sub.add_parser("next", help="اصلاح فقط دستهٔ بعدی، انتشار شاخه/PR/Release و توقف")
    _add_common(p_next)
    p_next.add_argument("--base", default="main", help="شاخهٔ اصلی برای ساخت شاخهٔ دسته")
    p_next.add_argument("--local-only", action="store_true",
                        help="بدون push/PR/Release (شاخه و کامیت محلی)")

    p_status = sub.add_parser("status", help="دسته‌های انجام‌شده و باقی‌مانده")
    _add_common(p_status)

    p_revert = sub.add_parser("revert", help="برگرداندن تغییرات یک دسته")
    _add_common(p_revert)
    p_revert.add_argument("--batch", type=int, required=True, help="شمارهٔ دسته، مثل 1")
    return parser


def _paths(args) -> tuple[Path, Path, Path]:
    root = Path(args.root).resolve()
    progress = root / "progress.json"
    reports = root / "reports"
    return root, progress, reports


def cmd_scan(args) -> int:
    root, _progress, reports_dir = _paths(args)
    patterns = load_patterns(args.patterns)
    files, warning = discover_with_fallback(root, args.content_dir)
    if warning:
        print(f"هشدار: {warning}")
    duplicates = compute_duplicates(root, files)
    # scan = پردازش بدون نوشتن (write=False) روی همهٔ فایل‌ها، در قالب یک «دستهٔ صفر»
    result = process_files(root, files, patterns, duplicates, batch_no=0, write=False)
    for f in result.findings:
        if f.action == "deleted":
            f.action = "would-delete"
    out_dir = reports_dir / "scan"
    write_csv(result.findings, out_dir / "report.csv")
    write_html(result.findings, {}, out_dir / "report.html", "scan")
    batches = make_batches(files, args.batch_size)
    print(f"فایل‌های بررسی‌شده: {len(files)} (دسته‌های {args.batch_size}تاییِ پیش‌رو: {len(batches)})")
    print(
        f"scan: مقاله‌های بررسی‌شده={result.scanned}، "
        f"فایل‌هایی که در صورت اعمال تغییر می‌کردند={result.changed}، "
        f"جمله‌های قابل حذف (would-delete)={result.deleted_sentences}، "
        f"موارد FLAG={result.flagged}، بررسی دستی={result.manual_review_files}"
    )
    print(f"گزارش: {out_dir / 'report.csv'}")
    print(f"گزارش: {out_dir / 'report.html'}")
    print("هیچ فایلی تغییر نکرد (scan فقط گزارش است).")
    return 0


def cmd_status(args) -> int:
    root, progress_path, _ = _paths(args)
    files = discover_files(root, args.content_dir)
    if not files and not (root / args.content_dir).exists():
        print(f"پوشهٔ «{args.content_dir}» در این چک‌اوت نیست و فایل مقاله‌ای پیدا نشد.")
        print("نکته: ۴۸۳ شهرستان در رجیستری وردپرس ثبت‌اند؛ فایل‌های city در مخزن نیستند.")
    batches = make_batches(files, args.batch_size)
    progress = load_progress(progress_path)
    done = sorted(int(x) for x in progress.get("completed_batches", []))
    print(f"کل مقاله‌ها: {len(files)} | اندازهٔ دسته: {args.batch_size} | کل دسته‌ها: {len(batches)}")
    print(f"دسته‌های انجام‌شده: {len(done)} {done if done else ''}")
    print(f"دسته‌های باقی‌مانده: {len(batches) - len([d for d in done if d <= len(batches)])}")
    for no, batch in enumerate(batches, start=1):
        mark = "انجام‌شده" if no in done else "باقی‌مانده"
        print(f"  دستهٔ {no:03d}: {len(batch)} مقاله — {mark}")
    return 0


def cmd_next(args) -> int:
    root, progress_path, reports_dir = _paths(args)
    load_env(root)
    patterns = load_patterns(args.patterns)
    files = discover_files(root, args.content_dir)
    if not files:
        print(f"هیچ فایل مقاله‌ای در «{args.content_dir}» پیدا نشد؛ دسته‌ای اجرا نشد.")
        print("اگر مقاله‌های city هنوز به مخزن نیامده‌اند، اول آن‌ها را در همین پوشه بگذارید.")
        return 1
    batches = make_batches(files, args.batch_size)
    progress = load_progress(progress_path)
    progress.setdefault("completed_batches", [])
    progress.setdefault("batches", {})
    done = {int(x) for x in progress["completed_batches"]}
    next_no = next((i for i in range(1, len(batches) + 1) if i not in done), None)
    if next_no is None:
        print("همهٔ دسته‌ها انجام شده‌اند؛ دستهٔ باقی‌مانده‌ای نیست.")
        return 0

    batch_files = batches[next_no - 1]
    if not args.local_only:
        # شاخهٔ دسته «قبل» از دست‌کاری فایل‌ها ساخته می‌شود تا هیچ تغییری روی main نیفتد
        # و اگر ساخت شاخه نشد، هیچ مقاله‌ای دست نخورده بماند.
        try:
            ensure_batch_branch(root, args.base, next_no)
        except Exception as exc:
            print(f"ساخت شاخهٔ دسته ممکن نشد؛ هیچ فایلی تغییر نکرد: {exc}")
            return 1
    before_count = len(discover_files(root, args.content_dir))
    duplicates = compute_duplicates(root, files)
    result = process_files(root, batch_files, patterns, duplicates, batch_no=next_no, write=True)
    after_count = len(discover_files(root, args.content_dir))
    if after_count != before_count:
        print(f"خطای ایمنی: تعداد فایل‌ها قبل ({before_count}) و بعد ({after_count}) برابر نیست؛ توقف.")
        return 1

    out_dir = reports_dir / f"batch-{next_no:03d}"
    write_csv(result.findings, out_dir / "report.csv")
    write_html(result.findings, result.diffs, out_dir / "report.html", f"batch-{next_no:03d}")
    print(_summary_line(f"دستهٔ {next_no:03d}", result))
    print(f"گزارش: {out_dir / 'report.csv'}")
    print(f"گزارش: {out_dir / 'report.html'}")

    info: dict[str, str] = {}
    publish_failed = False
    if args.local_only:
        info = {"note": "حالت local-only: هیچ عملیات گیتی (شاخه/کامیت/push/PR/Release) انجام نشد."}
        print(info["note"])
    elif result.changed_files:
        rows = "\n".join(
            f"| {f.file} | {f.sentence} | {f.pattern_id} |"
            for f in result.findings if f.action == "deleted"
        ) or "| — | — | — |"
        pr_body = (
            "## تغییرات این دسته\n\n| فایل | جملهٔ حذف‌شده | id الگو |\n|---|---|---|\n"
            f"{rows}\n\nگزارش کامل: reports/batch-{next_no:03d}/report.csv"
        )
        try:
            info = publish_batch(
                root, args.base, next_no, result.changed_files,
                result.deleted_sentences, pr_body, local_only=args.local_only,
            )
        except Exception as exc:  # انتشار ناموفق: دسته «انجام‌شده» علامت نمی‌خورد
            print(f"هشدار انتشار: {exc}")
            info = {"note": f"انتشار انجام نشد: {exc}", "status": "publication-failed"}
            publish_failed = True
        if info.get("pr_url"):
            print(f"PR: {info['pr_url']}")
        if info.get("release_url"):
            print(f"Release: {info['release_url']}")
        if info.get("note"):
            print(info["note"])
    else:
        print("فایل تغییرکرده‌ای نیست؛ شاخه/PR/Release ساخته نشد.")

    if not publish_failed:
        progress["completed_batches"] = sorted(done | {next_no})
    progress["batches"][f"{next_no:03d}"] = {
        "files": batch_files,
        "changed_files": result.changed_files,
        "deleted_sentences": result.deleted_sentences,
        "flagged": result.flagged,
        "manual_review_files": result.manual_review_files,
        **{k: v for k, v in info.items()},
    }
    save_progress(progress_path, progress)
    if publish_failed:
        print(f"دستهٔ {next_no:03d} اصلاح شد ولی انتشارش ناتمام ماند و «انجام‌شده» علامت نخورد؛ بعد از رفع مشکل دوباره next بزنید.")
        return 1
    print(f"دستهٔ {next_no:03d} تمام شد و متوقف می‌شوم؛ برای دستهٔ بعد دوباره next بزنید.")
    return 0


def cmd_revert(args) -> int:
    root, progress_path, _ = _paths(args)
    if git_current_branch(root) in ("main", "master"):
        print("روی شاخهٔ اصلی نمی‌شود revert کرد؛ اول به یک شاخهٔ کاری بروید.")
        return 1
    progress = load_progress(progress_path)
    key = f"{args.batch:03d}"
    entry = (progress.get("batches") or {}).get(key) or {}
    commit = entry.get("commit", "")
    if not commit:
        branch = f"cleanup/batch-{args.batch:03d}"
        got = subprocess.run(
            ["git", "rev-parse", branch], cwd=root, text=True, capture_output=True
        )
        if got.returncode != 0:
            print(f"کامیت دستهٔ {key} در progress.json و شاخهٔ {branch} پیدا نشد.")
            return 1
        commit = got.stdout.strip()
    run = subprocess.run(["git", "revert", "--no-edit", commit], cwd=root, text=True, capture_output=True)
    if run.returncode != 0:
        print(f"revert ناموفق بود:\n{run.stdout}\n{run.stderr}")
        return 1
    progress["completed_batches"] = [
        x for x in progress.get("completed_batches", []) if int(x) != args.batch
    ]
    save_progress(progress_path, progress)
    print(f"دستهٔ {key} با git revert برگردانده شد (بدون force، بدون حذف فایل).")
    return 0


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    return {
        "scan": cmd_scan,
        "next": cmd_next,
        "status": cmd_status,
        "revert": cmd_revert,
    }[args.command](args)


if __name__ == "__main__":
    sys.exit(main())
