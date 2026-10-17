"""گزارش هر دسته: report.csv + report.html (راست‌به‌چپ، diff رنگی)."""
from __future__ import annotations

import csv
import difflib
import html
from pathlib import Path

from .cleaner import Finding

CSV_FIELDS = ["file", "title", "line", "pattern_id", "level", "sentence", "action"]


def write_csv(findings: list[Finding], path: str | Path) -> Path:
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_FIELDS)
        writer.writeheader()
        for f in findings:
            writer.writerow(
                {
                    "file": f.file,
                    "title": f.title,
                    "line": f.line,
                    "pattern_id": f.pattern_id,
                    "level": f.level,
                    "sentence": f.sentence,
                    "action": f.action,
                }
            )
    return path


def _diff_html(before: str, after: str) -> str:
    out: list[str] = []
    for line in difflib.ndiff(before.splitlines(), after.splitlines()):
        code, content = line[:2], html.escape(line[2:])
        if code == "- ":
            out.append(f'<div class="del">- {content}</div>')
        elif code == "+ ":
            out.append(f'<div class="ins">+ {content}</div>')
        elif code == "  ":
            out.append(f'<div class="ctx"> {content}</div>')
    return "\n".join(out) or '<div class="ctx">(بدون تغییر متنی)</div>'


def write_html(
    findings: list[Finding],
    diffs: dict[str, tuple[str, str]],
    path: str | Path,
    batch_label: str = "",
) -> Path:
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    rows = "\n".join(
        "<tr>"
        f"<td>{html.escape(f.file)}</td><td>{html.escape(f.title)}</td>"
        f"<td>{f.line}</td><td>{html.escape(f.pattern_id)}</td>"
        f"<td>{html.escape(f.level)}</td><td>{html.escape(f.sentence)}</td>"
        f"<td>{html.escape(f.action)}</td></tr>"
        for f in findings
    )
    diff_sections = "\n".join(
        f"<h2>{html.escape(name)}</h2><div class=\"diff\">{_diff_html(b, a)}</div>"
        for name, (b, a) in diffs.items()
    )
    doc = f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8">
<title>گزارش پاک‌سازی {html.escape(batch_label)}</title>
<style>
body{{font-family:sans-serif;margin:2rem;background:#fff;color:#111}}
table{{border-collapse:collapse;width:100%}}
th,td{{border:1px solid #ccc;padding:.35rem .5rem;text-align:right;vertical-align:top}}
.diff{{border:1px solid #ddd;padding:1rem;white-space:pre-wrap;direction:rtl}}
.del{{background:#ffe3e3;text-decoration:line-through}}
.ins{{background:#e2ffe5}}
.ctx{{color:#444}}
</style></head>
<body>
<h1>گزارش پاک‌سازی {html.escape(batch_label)}</h1>
<table><thead><tr>
<th>فایل</th><th>عنوان</th><th>خط</th><th>id الگو</th><th>سطح</th><th>جملهٔ کامل</th><th>اقدام</th>
</tr></thead><tbody>{rows}</tbody></table>
{diff_sections}
</body></html>
"""
    path.write_text(doc, encoding="utf-8")
    return path
