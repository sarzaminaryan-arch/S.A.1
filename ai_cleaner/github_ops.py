"""انتشار دسته: branch + commit + push + PR + Release(zip) — هرگز merge، هرگز force.

توکن فقط از محیط یا فایل .env (کلید GITHUB_TOKEN) خوانده می‌شود؛ هیچ توکنی در کد نیست.
اگر توکن نباشد، از `gh` احراز‌شدهٔ سیستم استفاده می‌شود؛ اگر هیچ‌کدام نباشد،
مرحلهٔ انتشار رد می‌شود و هشدار روشن چاپ می‌شود (شاخه و کامیت محلی می‌مانند).
"""
from __future__ import annotations

import json
import os
import subprocess
import urllib.request
import zipfile
from pathlib import Path


def load_env(root: Path) -> None:
    """فایل .env ریشهٔ مخزن را (بدون بازنویسی متغیرهای موجود محیط) بار می‌کند."""
    env_file = root / ".env"
    if not env_file.exists():
        return
    for raw in env_file.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip("'\""))


def _run(cmd: list[str], cwd: Path, check: bool = True) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(cmd, cwd=cwd, text=True, capture_output=True)
    if check and result.returncode != 0:
        raise RuntimeError(f"دستور ناموفق: {' '.join(cmd)}\n{result.stdout}\n{result.stderr}")
    return result


def git_current_branch(root: Path) -> str:
    return _run(["git", "rev-parse", "--abbrev-ref", "HEAD"], root).stdout.strip()


def git_is_clean(root: Path) -> bool:
    return _run(["git", "status", "--porcelain"], root).stdout.strip() == ""


def ensure_batch_branch(root: Path, base: str, batch_no: int) -> str:
    """شاخهٔ cleanup/batch-NNN را از روی base می‌سازد یا به آن می‌رود (بدون force)."""
    branch = f"cleanup/batch-{batch_no:03d}"
    if git_current_branch(root) == branch:
        return branch
    exists = _run(["git", "rev-parse", "--verify", branch], root, check=False)
    if exists.returncode == 0:
        _run(["git", "checkout", branch], root)
    else:
        _run(["git", "checkout", "-b", branch, base], root)
    return branch


def publish_batch(
    root: Path,
    base: str,
    batch_no: int,
    changed_files: list[str],
    deleted_sentences: int,
    pr_body: str,
    local_only: bool = False,
) -> dict[str, str]:
    """شاخهٔ cleanup/batch-NNN می‌سازد، فقط فایل‌های همین دسته را کامیت می‌کند و
    (در صورت دسترسی) push و PR و Release می‌سازد. برنمی‌گردد تا merge کند."""
    # کامیت مستقیم روی main/master هرگز انجام نمی‌شود: اولین کار همین تابع
    # ساخت و رفتن به شاخهٔ دسته از روی base است و کامیت فقط بعد از آن رخ می‌دهد.
    branch = f"cleanup/batch-{batch_no:03d}"
    title = (
        f"پاک‌سازی جمله‌های هوش مصنوعی: دستهٔ {batch_no:03d} "
        f"({len(changed_files)} مقاله، {deleted_sentences} جمله حذف شد)"
    )
    info: dict[str, str] = {"branch": branch, "title": title, "pr_url": "", "release_url": ""}

    # ۱) شاخه از branch اصلی (اگر cmd_next قبلاً ساخته باشد، همان می‌ماند)
    ensure_batch_branch(root, base, batch_no)

    # ۲) فقط فایل‌های تغییرکردهٔ همین دسته
    _run(["git", "add", "--", *changed_files], root)
    _run(["git", "commit", "-m", title, "--", *changed_files], root)
    info["commit"] = _run(["git", "rev-parse", "HEAD"], root).stdout.strip()

    if local_only:
        info["note"] = "حالت local-only: push/PR/Release انجام نشد."
        return info

    # ۳) push (هرگز --force)
    _run(["git", "push", "-u", "origin", branch], root)

    # ۴) PR + Release: اول API با توکن .env، وگرنه gh
    load_env(root)
    token = os.environ.get("GITHUB_TOKEN", "")
    remote = _run(["git", "remote", "get-url", "origin"], root).stdout.strip()
    repo = _repo_slug(remote)
    tag = f"cleanup-v{batch_no:03d}"

    zip_path = root / f"cleanup-batch-{batch_no:03d}.zip"
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel in changed_files:
            zf.write(root / rel, rel)

    if token and repo:
        info["pr_url"] = _api_create_pr(token, repo, title, branch, base, pr_body)
        info["release_url"] = _api_create_release(token, repo, tag, title, pr_body, zip_path)
    else:
        gh_pr = _run(
            ["gh", "pr", "create", "--title", title, "--body", pr_body,
             "--head", branch, "--base", base],
            root, check=False,
        )
        if gh_pr.returncode == 0:
            info["pr_url"] = gh_pr.stdout.strip().splitlines()[-1] if gh_pr.stdout.strip() else ""
            gh_rel = _run(
                ["gh", "release", "create", tag, str(zip_path),
                 "--title", title, "--notes", pr_body],
                root, check=False,
            )
            if gh_rel.returncode == 0:
                info["release_url"] = gh_rel.stdout.strip().splitlines()[-1] if gh_rel.stdout.strip() else ""
        else:
            info["note"] = (
                "شاخه push شد ولی GITHUB_TOKEN در .env نیست و gh هم در دسترس/احراز نیست؛ "
                "PR و Release ساخته نشدند."
            )
    zip_path.unlink(missing_ok=True)
    return info


def _repo_slug(remote: str) -> str:
    remote = remote.strip().removesuffix(".git")
    if "github.com" not in remote:
        return ""
    return remote.split("github.com", 1)[1].lstrip(":/")


def _api(url: str, token: str, payload: dict, method: str = "POST") -> dict:
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        method=method,
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/vnd.github+json",
            "Content-Type": "application/json",
        },
    )
    with urllib.request.urlopen(req) as resp:  # noqa: S310 - آدرس ثابت گیت‌هاب
        return json.loads(resp.read().decode("utf-8"))


def _api_create_pr(token: str, repo: str, title: str, head: str, base: str, body: str) -> str:
    data = _api(
        f"https://api.github.com/repos/{repo}/pulls",
        token,
        {"title": title, "head": head, "base": base, "body": body},
    )
    return str(data.get("html_url", ""))


def _api_create_release(token: str, repo: str, tag: str, name: str, body: str, zip_path: Path) -> str:
    data = _api(
        f"https://api.github.com/repos/{repo}/releases",
        token,
        {"tag_name": tag, "name": name, "body": body, "draft": False, "prerelease": False},
    )
    upload_url = str(data.get("upload_url", "")).split("{")[0]
    if upload_url:
        req = urllib.request.Request(
            f"{upload_url}?name={zip_path.name}",
            data=zip_path.read_bytes(),
            method="POST",
            headers={"Authorization": f"Bearer {token}", "Content-Type": "application/zip"},
        )
        urllib.request.urlopen(req).read()
    return str(data.get("html_url", ""))
