#!/usr/bin/env python3
"""
wp_agent.py — the agent's direct line to WordPress (theme 2.22.72+, route /wp-json/sa-agent/v1).

Owner rules, enforced both here and on the server (inc/agent-bridge.php):
  * Reading the site directly is allowed (published posts, drafts, entity post types, meta).
  * New articles go in as DRAFTS only. There is no publish command and no delete command.
  * Before any change to an existing post or to theme settings, a backup of the prior
    state is taken: on the server (private "agent backups" list under Tools) AND locally
    in backup/agent/ (gitignored). If the local backup cannot be written, nothing is sent.
  * Published text is never overwritten; use `draft-copy` for a side draft. Only with
    the owner's explicit order, `update --owner-approved-live-edit`.

Credentials come only from .env in the repo root (never printed, never committed):
    SA_SITE_URL=https://sarzaminaryan.ir
    SA_USER=<editor/admin username>
    SA_APP_PASSWORD=<Application Password, with or without spaces>

Commands (run from the repo root):
    python3 tools/wp-agent/wp_agent.py ping
    python3 tools/wp-agent/wp_agent.py list --type post --status draft --search کاشان
    python3 tools/wp-agent/wp_agent.py inventory [--types post,city]
    python3 tools/wp-agent/wp_agent.py get 1234 [--out file.json]
    python3 tools/wp-agent/wp_agent.py draft --type post --title "..." --content-file a.html [--excerpt ...] [--slug ...] [--category travel] [--meta-file m.json]
    python3 tools/wp-agent/wp_agent.py draft-copy 1234 --content-file new.html [--title ...]
    python3 tools/wp-agent/wp_agent.py update 1234 --meta-file m.json [--reason "..."]
    python3 tools/wp-agent/wp_agent.py update 1234 --content-file new.html --owner-approved-live-edit --reason "دستور مالک ..."
    python3 tools/wp-agent/wp_agent.py backups [--target 1234]
    python3 tools/wp-agent/wp_agent.py restore <backup_id>
    python3 tools/wp-agent/wp_agent.py theme-mods
    python3 tools/wp-agent/wp_agent.py theme-mods --set sa_show_featured=false --reason "..."
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
BACKUP_DIR = REPO_ROOT / "backup" / "agent"
NS = "/wp-json/sa-agent/v1"
BANNED = ("راستش", "هوش مصنوعی", "بازبینی انسانی", "ChatGPT", "Claude", "Gemini")
JOBS_DIR = REPO_ROOT / "tools" / "wp-agent" / "jobs"
RESULTS_DIR = REPO_ROOT / "tools" / "wp-agent" / "results"


class AgentError(Exception):
    """A refused or failed step. Nothing after it in a job runs."""


def load_env() -> dict[str, str]:
    env: dict[str, str] = {}
    path = REPO_ROOT / ".env"
    if path.exists():
        for line in path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    for k in ("SA_SITE_URL", "SA_USER", "SA_APP_PASSWORD"):
        if os.environ.get(k):
            env[k] = os.environ[k]
    missing = [k for k in ("SA_SITE_URL", "SA_USER", "SA_APP_PASSWORD") if not env.get(k)]
    if missing:
        raise AgentError("missing in .env / environment: " + ", ".join(missing))
    return env


class Client:
    def __init__(self, env: dict[str, str]) -> None:
        self.base = env["SA_SITE_URL"].rstrip("/") + NS
        self.secret = env["SA_APP_PASSWORD"]
        token = base64.b64encode(f'{env["SA_USER"]}:{env["SA_APP_PASSWORD"]}'.encode()).decode()
        self.headers = {
            "Authorization": "Basic " + token,
            "X-SA-Authorization": "Basic " + token,  # fallback if the host strips Authorization
            "Accept": "application/json",
            "User-Agent": "sa-wp-agent/1.0",
        }

    def call(self, method: str, path: str, params: dict | None = None, body: dict | None = None):
        url = self.base + path
        if params:
            url += "?" + urllib.parse.urlencode({k: v for k, v in params.items() if v not in (None, "")})
        data = None
        headers = dict(self.headers)
        if body is not None:
            data = json.dumps(body, ensure_ascii=False).encode("utf-8")
            headers["Content-Type"] = "application/json; charset=utf-8"
        req = urllib.request.Request(url, data=data, method=method, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=90) as resp:
                return json.loads(resp.read().decode("utf-8") or "null")
        except urllib.error.HTTPError as err:
            text = err.read().decode("utf-8", "replace").replace(self.secret, "***")
            raise AgentError(f"HTTP {err.code} on {method} {path}: {text[:800]}")
        except urllib.error.URLError as err:
            raise AgentError(f"network error on {method} {path}: {err.reason}")


def save_local_backup(name: str, data) -> Path:
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    path = BACKUP_DIR / f"{name}-{stamp}.json"
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    if not path.exists() or path.stat().st_size == 0:
        raise AgentError("local backup failed; nothing was sent")
    return path


def read_text(path: str | None) -> str | None:
    return None if not path else Path(path).read_text(encoding="utf-8")


def read_json(path: str | None) -> dict:
    return {} if not path else json.loads(Path(path).read_text(encoding="utf-8"))


def check_banned(*texts: str | None) -> None:
    for t in texts:
        for phrase in BANNED:
            if t and phrase.lower() in t.lower():
                raise AgentError(f"banned phrase in text: {phrase}")


def out(data) -> None:
    print(json.dumps(data, ensure_ascii=False, indent=2))


# ---------------------------------------------------------------------------
# seo-scan: read-only public crawl of published URLs (no credentials sent).
# ---------------------------------------------------------------------------
import html.parser
import re
import time
from concurrent.futures import ThreadPoolExecutor


class _SeoParser(html.parser.HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.title = ""
        self._in_title = False
        self.meta: dict[str, str] = {}
        self.canonical = ""
        self.h1 = 0
        self.h2 = 0
        self.imgs = 0
        self.imgs_no_alt = 0
        self.jsonld: list[str] = []
        self._in_jsonld = False
        self._buf = ""
        self.links_internal = 0
        self.text_parts: list[str] = []
        self._skip = 0
        self.hreflang = 0
        self.lazy_imgs = 0

    def handle_starttag(self, tag, attrs):
        a = {k.lower(): (v or "") for k, v in attrs}
        if tag == "title":
            self._in_title = True
        elif tag == "meta":
            key = (a.get("name") or a.get("property") or "").lower()
            if key:
                self.meta[key] = a.get("content", "")
        elif tag == "link":
            rel = a.get("rel", "").lower()
            if rel == "canonical":
                self.canonical = a.get("href", "")
            if rel == "alternate" and a.get("hreflang"):
                self.hreflang += 1
        elif tag == "h1":
            self.h1 += 1
        elif tag == "h2":
            self.h2 += 1
        elif tag == "img":
            self.imgs += 1
            if not a.get("alt", "").strip():
                self.imgs_no_alt += 1
            if a.get("loading") == "lazy":
                self.lazy_imgs += 1
        elif tag == "script" and "ld+json" in a.get("type", ""):
            self._in_jsonld = True
            self._buf = ""
        elif tag == "a":
            href = a.get("href", "")
            if "sarzaminaryan.ir" in href or href.startswith("/"):
                self.links_internal += 1
        if tag in ("script", "style", "noscript"):
            self._skip += 1

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        elif tag == "script" and self._in_jsonld:
            self._in_jsonld = False
            self.jsonld.append(self._buf)
        if tag in ("script", "style", "noscript") and self._skip:
            self._skip -= 1

    def handle_data(self, data):
        if self._in_title:
            self.title += data
        if self._in_jsonld:
            self._buf += data
        elif not self._skip:
            self.text_parts.append(data)


def _jsonld_types(blobs: list[str]) -> list[str]:
    types: list[str] = []

    def walk(node):
        if isinstance(node, dict):
            t = node.get("@type")
            if isinstance(t, str):
                types.append(t)
            elif isinstance(t, list):
                types.extend(str(x) for x in t)
            for v in node.values():
                walk(v)
        elif isinstance(node, list):
            for v in node:
                walk(v)

    for b in blobs:
        try:
            walk(json.loads(b))
        except ValueError:
            types.append("INVALID_JSON")
    return sorted(set(types))


def scan_url(url: str) -> dict:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; sa-seo-scan/1.0)", "Accept-Language": "fa"})
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=40) as resp:
            body = resp.read()
            status = resp.status
            final = resp.geturl()
            headers = {k.lower(): v for k, v in resp.headers.items()}
    except urllib.error.HTTPError as err:
        return {"url": url, "status": err.code}
    except Exception as err:  # noqa: BLE001
        return {"url": url, "status": 0, "error": str(err)[:200]}
    ms = int((time.time() - t0) * 1000)
    p = _SeoParser()
    try:
        p.feed(body.decode("utf-8", "replace"))
    except Exception:  # noqa: BLE001
        pass
    text = re.sub(r"\s+", " ", " ".join(p.text_parts)).strip()
    return {
        "url": url, "status": status, "final": final if final != url else "", "ms": ms, "kb": len(body) // 1024,
        "cache": headers.get("x-litespeed-cache", ""), "x_robots": headers.get("x-robots-tag", ""),
        "title": p.title.strip(), "title_len": len(p.title.strip()),
        "desc": p.meta.get("description", ""), "desc_len": len(p.meta.get("description", "")),
        "robots": p.meta.get("robots", ""), "canonical": p.canonical,
        "og_image": bool(p.meta.get("og:image")), "og_title": bool(p.meta.get("og:title")),
        "h1": p.h1, "h2": p.h2, "imgs": p.imgs, "imgs_no_alt": p.imgs_no_alt, "lazy_imgs": p.lazy_imgs,
        "jsonld": _jsonld_types(p.jsonld), "internal_links": p.links_internal,
        "words_rendered": len(text.split()), "viewport": bool(p.meta.get("viewport")),
    }


def seo_scan(urls: list[str], workers: int = 4) -> list[dict]:
    with ThreadPoolExecutor(max_workers=workers) as pool:
        return list(pool.map(scan_url, urls))


def build_parser() -> argparse.ArgumentParser:
    ap = argparse.ArgumentParser(description="direct WordPress access for the agent (drafts only, backup first)")
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("ping")
    p = sub.add_parser("list")
    p.add_argument("--type", default="post")
    p.add_argument("--status", default="")
    p.add_argument("--search", default="")
    p.add_argument("--slug", default="")
    p.add_argument("--modified-after", default="")
    p.add_argument("--per-page", type=int, default=50)
    p.add_argument("--page", type=int, default=1)
    p = sub.add_parser("inventory", help="every item of the given types (all statuses), paginated")
    p.add_argument("--types", default="post,page,province,city,attraction,travel_route,local_food,souvenir")
    p = sub.add_parser("seo-scan", help="read-only public crawl of published URLs (no credentials)")
    p.add_argument("--types", default="post,page,province,city,attraction")
    p.add_argument("--extra", action="append", default=[], help="extra public URL (robots.txt, sitemap, ...)")
    p.add_argument("--limit", type=int, default=0)
    p.add_argument("--workers", type=int, default=4)
    p = sub.add_parser("get")
    p.add_argument("id", type=int)
    p.add_argument("--out")
    p = sub.add_parser("draft")
    p.add_argument("--type", default="post")
    p.add_argument("--title", required=True)
    p.add_argument("--content-file")
    p.add_argument("--excerpt", default="")
    p.add_argument("--slug", default="")
    p.add_argument("--parent", type=int, default=0)
    p.add_argument("--category", action="append", default=[])
    p.add_argument("--meta-file")
    p = sub.add_parser("draft-copy")
    p.add_argument("id", type=int)
    p.add_argument("--title")
    p.add_argument("--content-file")
    p.add_argument("--excerpt")
    p.add_argument("--meta-file")
    p = sub.add_parser("update")
    p.add_argument("id", type=int)
    p.add_argument("--title")
    p.add_argument("--content-file")
    p.add_argument("--excerpt")
    p.add_argument("--meta-file")
    p.add_argument("--reason", default="به‌روزرسانی ایجنت")
    p.add_argument("--owner-approved-live-edit", action="store_true")
    p = sub.add_parser("backups")
    p.add_argument("--target", type=int, default=0)
    p = sub.add_parser("restore")
    p.add_argument("backup_id", type=int)
    p = sub.add_parser("run-job")
    p.add_argument("job", help="tools/wp-agent/jobs/<name>.json")
    p = sub.add_parser("theme-mods")
    p.add_argument("--set", action="append", default=[], help="key=value (true/false/number/text)")
    p.add_argument("--reason", default="تغییر ظاهر")
    return ap



def run(a: argparse.Namespace, c: "Client"):
    """Run one command and return its JSON result (raises AgentError on refusal)."""
    if a.cmd == "ping":
        return c.call("GET", "/ping")
    elif a.cmd == "list":
        return c.call("GET", "/posts", {"type": a.type, "status": a.status, "search": a.search, "slug": a.slug,
                                         "modified_after": a.modified_after, "per_page": a.per_page, "page": a.page})
    elif a.cmd == "inventory":
        report = {}
        for ptype in [t.strip() for t in a.types.split(",") if t.strip()]:
            items, page = [], 1
            while True:
                chunk = c.call("GET", "/posts", {"type": ptype, "per_page": 100, "page": page})
                items += chunk.get("items", [])
                if page >= int(chunk.get("pages") or 0):
                    break
                page += 1
            report[ptype] = {"total": len(items), "items": items}
        return report
    elif a.cmd == "seo-scan":
        urls: list[str] = []
        for ptype in [t.strip() for t in a.types.split(",") if t.strip()]:
            page = 1
            while True:
                chunk = c.call("GET", "/posts", {"type": ptype, "status": "publish", "per_page": 100, "page": page})
                urls += [i["link"] for i in chunk.get("items", []) if i.get("link")]
                if page >= int(chunk.get("pages") or 0):
                    break
                page += 1
        urls = list(dict.fromkeys(urls))
        if a.limit:
            urls = urls[: a.limit]
        site = c.base.split("/wp-json/")[0]
        extras = {}
        for path in ["/robots.txt", "/wp-sitemap.xml"] + a.extra:
            u = path if path.startswith("http") else site + path
            req = urllib.request.Request(u, headers={"User-Agent": "Mozilla/5.0 (compatible; sa-seo-scan/1.0)"})
            try:
                with urllib.request.urlopen(req, timeout=40) as resp:
                    extras[u] = {"status": resp.status, "body": resp.read().decode("utf-8", "replace")[:20000]}
            except urllib.error.HTTPError as err:
                extras[u] = {"status": err.code}
            except Exception as err:  # noqa: BLE001
                extras[u] = {"status": 0, "error": str(err)[:200]}
        return {"count": len(urls), "extras": extras, "pages": seo_scan(urls, a.workers)}
    elif a.cmd == "get":
        data = c.call("GET", f"/post/{a.id}")
        if a.out:
            Path(a.out).write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        return data
    elif a.cmd == "draft":
        content = read_text(a.content_file) or ""
        check_banned(a.title, content, a.excerpt)
        body = {"type": a.type, "title": a.title, "content": content, "excerpt": a.excerpt,
                "slug": a.slug, "parent": a.parent, "meta": read_json(a.meta_file)}
        if a.category:
            body["terms"] = {"category": a.category}
        return c.call("POST", "/draft", body=body)
    elif a.cmd == "draft-copy":
        fields = {k: v for k, v in {"title": a.title, "content": read_text(a.content_file), "excerpt": a.excerpt}.items() if v is not None}
        check_banned(*fields.values())
        return c.call("POST", f"/post/{a.id}/draft-copy", body={"fields": fields, "meta": read_json(a.meta_file)})
    elif a.cmd == "update":
        current = c.call("GET", f"/post/{a.id}")
        local = save_local_backup(f"post-{a.id}", current)
        print(f"local backup: {local.relative_to(REPO_ROOT)}", file=sys.stderr)
        fields = {k: v for k, v in {"title": a.title, "content": read_text(a.content_file), "excerpt": a.excerpt}.items() if v is not None}
        check_banned(*fields.values())
        body = {"expected_modified_gmt": current["modified_gmt"], "fields": fields,
                "meta": read_json(a.meta_file), "reason": a.reason}
        if a.owner_approved_live_edit:
            body["owner_approved_live_edit"] = True
        return c.call("POST", f"/post/{a.id}", body=body)
    elif a.cmd == "backups":
        return c.call("GET", "/backups", {"target": a.target or None})
    elif a.cmd == "restore":
        b = c.call("GET", f"/backup/{a.backup_id}")
        if b.get("kind") == "post" and b.get("target"):
            save_local_backup(f"post-{b['target']}-pre-restore", c.call("GET", f"/post/{b['target']}"))
        else:
            save_local_backup("theme-mods-pre-restore", c.call("GET", "/theme-mods"))
        return c.call("POST", f"/restore/{a.backup_id}", body={})
    elif a.cmd == "theme-mods":
        current = c.call("GET", "/theme-mods")
        if not a.set:
            return current
        local = save_local_backup("theme-mods", current)
        print(f"local backup: {local.relative_to(REPO_ROOT)}", file=sys.stderr)
        changes = {}
        for item in a.set:
            k, _, v = item.partition("=")
            low = v.strip().lower()
            if low in ("true", "false"):
                changes[k.strip()] = low == "true"
            elif low.lstrip("-").isdigit():
                changes[k.strip()] = int(low)
            else:
                changes[k.strip()] = v
        return c.call("POST", "/theme-mods", body={"expected_hash": current["hash"], "set": changes, "reason": a.reason})
    raise AgentError(f"unknown command {a.cmd}")


def step_to_argv(step: dict) -> list[str]:
    """{"cmd": "draft", "title": "...", "content_file": "..."} -> ["draft", "--title", "...", ...]."""
    step = dict(step)
    cmd = step.pop("cmd", None)
    if cmd in (None, "run-job"):
        raise AgentError("each step needs a cmd (run-job cannot nest)")
    argv = [cmd]
    for pos in ("id", "backup_id"):
        if pos in step:
            argv.append(str(step.pop(pos)))
    for key, value in step.items():
        flag = "--" + key.replace("_", "-")
        if value is True:
            argv.append(flag)
        elif value in (False, None):
            continue
        elif isinstance(value, list):
            for item in value:
                argv += [flag, str(item)]
        else:
            argv += [flag, str(value)]
    return argv


def run_job(job_path: str, c: "Client") -> dict:
    """Run a job file (a list of steps); stop at the first refusal. Writes results/<name>.json."""
    path = (REPO_ROOT / job_path).resolve() if not Path(job_path).is_absolute() else Path(job_path)
    job = json.loads(path.read_text(encoding="utf-8"))
    steps = job.get("steps", job if isinstance(job, list) else [])
    parser = build_parser()
    report = {"job": str(path.relative_to(REPO_ROOT)) if REPO_ROOT in path.parents else str(path),
              "started_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
              "note": job.get("note", "") if isinstance(job, dict) else "", "ok": True, "steps": []}
    for i, step in enumerate(steps, 1):
        entry = {"n": i, "cmd": step.get("cmd")}
        try:
            entry["result"] = run(parser.parse_args(step_to_argv(step)), c)
        except (AgentError, SystemExit) as err:
            entry["error"] = str(err)
            report["ok"] = False
            report["steps"].append(entry)
            break
        report["steps"].append(entry)
    report["finished_utc"] = datetime.now(timezone.utc).isoformat(timespec="seconds")
    RESULTS_DIR.mkdir(parents=True, exist_ok=True)
    (RESULTS_DIR / path.name).write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return report


def main(argv: list[str] | None = None) -> int:
    a = build_parser().parse_args(argv)
    try:
        c = Client(load_env())
        data = run_job(a.job, c) if a.cmd == "run-job" else run(a, c)
    except AgentError as err:
        print(f"refused: {err}", file=sys.stderr)
        return 2
    out(data)
    if isinstance(data, dict) and data.get("ok") is False:
        return 2
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
