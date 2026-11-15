#!/usr/bin/env python3
"""
wp_agent.py — the agent's direct line to WordPress (theme 2.22.72+, route /wp-json/sa-agent/v1).

Owner rules, enforced both here and on the server (inc/agent-bridge.php):
  * Reading the site directly is allowed (published posts, drafts, entity post types, meta).
  * New articles go in as DRAFTS only. There is no publish command and no delete command.
  * Theme 2.22.74+: `trash` moves ONLY a duplicate whose URL is already a 301 source with a
    published target to the WordPress trash, with the owner's explicit flag and a backup first.
  * Theme 2.22.74+: `litespeed` reads LiteSpeed Cache settings; only crawler keys can be set.
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
    python3 tools/wp-agent/wp_agent.py trash 153 --owner-approved-trash --reason "دستور مالک ..."
    python3 tools/wp-agent/wp_agent.py litespeed
    python3 tools/wp-agent/wp_agent.py litespeed --set crawler=true --set crawler-sitemap=https://sarzaminaryan.ir/wp-sitemap.xml --reason "..."
    # theme 2.22.75+
    python3 tools/wp-agent/wp_agent.py index-gate [--only noindex]
    python3 tools/wp-agent/wp_agent.py relations-audit                          # 2.22.76+
    python3 tools/wp-agent/wp_agent.py cache-purge --all | --id 123            # 2.22.77+
    python3 tools/wp-agent/wp_agent.py updraft                                   # status only
    python3 tools/wp-agent/wp_agent.py updraft --start --owner-approved-backup [--what all] [--wait 1500]
    python3 tools/wp-agent/wp_agent.py theme-update --expected-version 2.22.76 --owner-approved-theme-update \
        --backup-first --probe / --probe /city/isfahan-city/ --rollback-to 2.22.75

Safe theme update (owner order, 15 Nov 2026): UpdraftPlus full backup -> wait until finished ->
core Theme_Upgrader::upgrade() (temp_backup auto-restores a half-finished install) -> probe ->
on a failed probe, reinstall --rollback-to through the same path. The DB is untouched by a theme
update; the UpdraftPlus backup is the fallback for anything else.
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
sys.path.insert(0, str(Path(__file__).resolve().parent))  # content_audit.py
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
        self.imgs_missing_alt = 0  # no alt attribute at all (real a11y/SEO gap)
        self.no_alt_srcs: list[str] = []  # first few srcs without a non-empty alt
        self.jsonld: list[str] = []
        self._in_jsonld = False
        self._buf = ""
        self.links_internal = 0
        self.text_parts: list[str] = []
        self._skip = 0
        self.hreflang = 0
        self.lazy_imgs = 0
        self._svg = 0
        self._title_done = False

    def handle_starttag(self, tag, attrs):
        a = {k.lower(): (v or "") for k, v in attrs}
        if tag == "svg":
            self._svg += 1
        if tag == "title":
            # Only the document <title>; <title> inside inline SVG (province map) is a tooltip.
            self._in_title = not self._svg and not self._title_done
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
            if not (a.get("alt") or "").strip():
                self.imgs_no_alt += 1  # alt="" is valid for decorative images (e.g. a logo inside a named link)
                if len(self.no_alt_srcs) < 4:
                    self.no_alt_srcs.append((a.get("src") or a.get("data-src") or "")[:160])
            if "alt" not in a:
                self.imgs_missing_alt += 1
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
        if tag == "svg" and self._svg:
            self._svg -= 1
        if tag == "title":
            if self._in_title:
                self._title_done = True
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
        "h1": p.h1, "h2": p.h2, "imgs": p.imgs, "imgs_no_alt": p.imgs_no_alt, "imgs_missing_alt": p.imgs_missing_alt, "no_alt_srcs": p.no_alt_srcs, "lazy_imgs": p.lazy_imgs,
        "jsonld": _jsonld_types(p.jsonld), "internal_links": p.links_internal,
        "words_rendered": len(text.split()), "viewport": bool(p.meta.get("viewport")),
    }


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):  # noqa: D102
        return None


def probe_url(url: str) -> dict:
    """One public GET without following redirects; for HTML pages also fetch og:image."""
    opener = urllib.request.build_opener(_NoRedirect)
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; sa-seo-scan/1.0)", "Accept-Language": "fa"})
    t0 = time.time()
    try:
        resp = opener.open(req, timeout=40)
        status, headers, body = resp.status, {k.lower(): v for k, v in resp.headers.items()}, resp.read()
    except urllib.error.HTTPError as err:
        status, headers, body = err.code, {k.lower(): v for k, v in (err.headers or {}).items()}, b""
    except Exception as err:  # noqa: BLE001
        return {"url": url, "status": 0, "error": str(err)[:200]}
    out = {"url": url, "status": status, "ms": int((time.time() - t0) * 1000),
           "location": headers.get("location", ""), "cache": headers.get("x-litespeed-cache", ""),
           "content_type": headers.get("content-type", "")}
    if status != 200 or "html" not in out["content_type"]:
        if status == 200:
            out["bytes"] = len(body)
            out["head"] = body[:300].decode("utf-8", "replace")
        return out
    p = _SeoParser()
    try:
        p.feed(body.decode("utf-8", "replace"))
    except Exception:  # noqa: BLE001
        pass
    og = {k: v for k, v in p.meta.items() if k.startswith("og:") or k.startswith("twitter:")}
    out.update({"title": p.title.strip(), "robots": p.meta.get("robots", ""), "canonical": p.canonical,
                "desc": p.meta.get("description", ""), "h1": p.h1, "og": og})
    img = og.get("og:image", "")
    if img:
        try:
            with urllib.request.urlopen(urllib.request.Request(img, headers={"User-Agent": "Mozilla/5.0 (compatible; sa-seo-scan/1.0)"}), timeout=40) as r2:
                data = r2.read()
                w = h = 0
                if data[:2] == b"\xff\xd8":  # JPEG: read SOF size
                    i = 2
                    while i < len(data) - 9:
                        if data[i] != 0xFF:
                            i += 1
                            continue
                        marker = data[i + 1]
                        if marker in (0xC0, 0xC1, 0xC2):
                            h, w = int.from_bytes(data[i + 5:i + 7], "big"), int.from_bytes(data[i + 7:i + 9], "big")
                            break
                        i += 2 + int.from_bytes(data[i + 2:i + 4], "big")
                elif data[:8] == b"\x89PNG\r\n\x1a\n":
                    w, h = int.from_bytes(data[16:20], "big"), int.from_bytes(data[20:24], "big")
                out["og_image_fetch"] = {"status": r2.status, "type": r2.headers.get("content-type", ""),
                                         "bytes": len(data), "width": w, "height": h}
        except urllib.error.HTTPError as err:
            out["og_image_fetch"] = {"status": err.code}
        except Exception as err:  # noqa: BLE001
            out["og_image_fetch"] = {"status": 0, "error": str(err)[:200]}
    return out


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
    p = sub.add_parser("probe", help="read-only public check of given URLs (no redirect following; og:image fetched)")
    p.add_argument("--url", action="append", default=[], help="path or full URL on the site")
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
    p.add_argument("--expected-modified-gmt", help="refuse if the post changed since this snapshot (edit was prepared from it)")
    p = sub.add_parser("backups")
    p.add_argument("--target", type=int, default=0)
    p = sub.add_parser("restore")
    p.add_argument("backup_id", type=int)
    p = sub.add_parser("run-job")
    p.add_argument("job", help="tools/wp-agent/jobs/<name>.json")
    p = sub.add_parser("theme-mods")
    p.add_argument("--set", action="append", default=[], help="key=value (true/false/number/text)")
    p.add_argument("--reason", default="تغییر ظاهر")
    p = sub.add_parser("trash", help="owner-approved: duplicate behind a 301 -> WordPress trash (backup first)")
    p.add_argument("id", type=int)
    p.add_argument("--owner-approved-trash", action="store_true")
    p.add_argument("--reason", default="زباله‌دان نسخهٔ تکراری (دستور مالک)")
    p = sub.add_parser("litespeed", help="read LiteSpeed Cache settings; --set only crawler keys")
    p.add_argument("--set", action="append", default=[], help="crawler=true | crawler-sitemap=URL | crawler-crawl_interval=SEC | crawler-load_limit=N")
    p.add_argument("--reason", default="تنظیم خزندهٔ LiteSpeed")
    p = sub.add_parser("index-gate", help="read-only: index/noindex decision and reason for every published city")
    p.add_argument("--only", default="", choices=["", "noindex", "index"])
    p = sub.add_parser("cache-purge", help="LiteSpeed page cache purge: --all or --id N (theme 2.22.77+)")
    p.add_argument("--all", action="store_true")
    p.add_argument("--id", action="append", default=[], type=int)
    p = sub.add_parser("content-audit", help="read-only: duplicate/template/contradiction checks on published county pages")
    p.add_argument("--type", default="city")
    p.add_argument("--limit", type=int, default=0)
    p.add_argument("--workers", type=int, default=4)
    p.add_argument("--template-min-pages", type=int, default=20)
    sub.add_parser("relations-audit", help="read-only: published items whose city/province parent is not published (theme 2.22.76+)")
    p = sub.add_parser("updraft", help="UpdraftPlus status; --start begins a backup (owner-approved)")
    p.add_argument("--start", action="store_true")
    p.add_argument("--owner-approved-backup", action="store_true")
    p.add_argument("--what", default="all", choices=["all", "db", "files"])
    p.add_argument("--nocloud", action="store_true")
    p.add_argument("--nonce", default="")
    p.add_argument("--wait", type=int, default=0, help="seconds to wait for the job to finish")
    p = sub.add_parser("theme-update", help="owner-approved: install a GitHub release of the child theme (probe + rollback)")
    p.add_argument("--expected-version", default="")
    p.add_argument("--version", default="", help="install this exact release (rollback); default newest")
    p.add_argument("--owner-approved-theme-update", action="store_true")
    p.add_argument("--backup-first", action="store_true", help="take an UpdraftPlus full backup and wait for it first")
    p.add_argument("--backup-wait", type=int, default=1500)
    p.add_argument("--probe", action="append", default=[], help="path that must answer 200/301 after the update")
    p.add_argument("--rollback-to", default="", help="version to reinstall if a probe fails")
    return ap


def parse_value(v: str):
    low = v.strip().lower()
    if low in ("true", "false"):
        return low == "true"
    if low.lstrip("-").isdigit():
        return int(low)
    try:
        return float(low)
    except ValueError:
        return v.strip()



def updraft_wait(c: "Client", nonce: str, seconds: int) -> dict:
    """Poll GET /updraft?nonce= until the job is finished (in history) or time runs out."""
    deadline = time.time() + max(0, seconds)
    status = c.call("GET", "/updraft", {"nonce": nonce})
    while not (status.get("job") or {}).get("finished") and time.time() < deadline:
        time.sleep(30)
        try:
            status = c.call("GET", "/updraft", {"nonce": nonce})
        except AgentError as err:  # a busy host can time out one poll; keep waiting
            print(f"poll: {err}", file=sys.stderr)
    return status


def updraft_start(c: "Client", what: str, nocloud: bool) -> dict:
    """POST /updraft. The server answers, closes the connection, then runs the backup."""
    return c.call("POST", "/updraft", body={"owner_approved_backup": True, "what": what, "nocloud": nocloud})


def bust(url: str) -> str:
    """Cache-busting query so a health probe renders the page with the code now on disk."""
    sep = "&" if "?" in url else "?"
    return f"{url}{sep}sa_probe={int(time.time())}"


def probes_healthy(probes: list[dict]) -> bool:
    """Every probe answered 200 (HTML with a title) or 301; nothing 5xx/0."""
    for pr in probes:
        st = int(pr.get("status") or 0)
        if st == 301:
            continue
        if st != 200:
            return False
        if "html" in pr.get("content_type", "") and not pr.get("title"):
            return False
    return True


def theme_install(c: "Client", version: str, expected: str) -> dict:
    body = {"owner_approved_theme_update": True, "expected_version": expected}
    if version:
        body["version"] = version
    return c.call("POST", "/theme-update", body=body)


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
    elif a.cmd == "probe":
        site = c.base.split("/wp-json/")[0]
        return {"probes": [probe_url(u if u.startswith("http") else site + u) for u in a.url]}
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
        if a.expected_modified_gmt and current.get("modified_gmt") != a.expected_modified_gmt:
            raise AgentError(f"post {a.id} changed since the snapshot ({a.expected_modified_gmt} -> {current.get('modified_gmt')}); re-read and re-prepare")
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
        elif b.get("kind") == "litespeed":
            save_local_backup("litespeed-pre-restore", c.call("GET", "/litespeed"))
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
    elif a.cmd == "trash":
        if not a.owner_approved_trash:
            raise AgentError("trash needs --owner-approved-trash (owner's explicit order)")
        current = c.call("GET", f"/post/{a.id}")
        local = save_local_backup(f"post-{a.id}-pre-trash", current)
        print(f"local backup: {local.relative_to(REPO_ROOT)}", file=sys.stderr)
        return c.call("POST", f"/post/{a.id}/trash", body={"owner_approved_trash": True,
                                                         "expected_modified_gmt": current["modified_gmt"], "reason": a.reason})
    elif a.cmd == "litespeed":
        current = c.call("GET", "/litespeed")
        if not a.set:
            return current
        local = save_local_backup("litespeed", current)
        print(f"local backup: {local.relative_to(REPO_ROOT)}", file=sys.stderr)
        changes = {}
        for item in a.set:
            k, _, v = item.partition("=")
            changes[k.strip()] = parse_value(v) if k.strip() != "crawler-sitemap" else v.strip()
        return c.call("POST", "/litespeed", body={"expected_hash": current["hash"], "set": changes, "reason": a.reason})
    elif a.cmd == "index-gate":
        rows, page = [], 1
        while True:
            chunk = c.call("GET", "/index-gate", {"page": page, "per_page": 100, "only": a.only})
            rows += chunk.get("rows", [])
            if page >= int(chunk.get("total_pages") or 0):
                break
            page += 1
        reasons: dict[str, int] = {}
        for r in rows:
            reasons[r.get("reason") or "index"] = reasons.get(r.get("reason") or "index", 0) + 1
        return {"theme": chunk.get("theme", ""), "count": len(rows), "by_reason": reasons, "rows": rows}
    elif a.cmd == "cache-purge":
        if a.id:
            return c.call("POST", "/cache-purge", body={"ids": a.id})
        if not a.all:
            raise AgentError("cache-purge needs --all or --id")
        return c.call("POST", "/cache-purge", body={"scope": "all"})
    elif a.cmd == "content-audit":
        import content_audit  # local module next to this file
        from concurrent.futures import ThreadPoolExecutor
        ids, page = [], 1
        while True:
            chunk = c.call("GET", "/posts", {"type": a.type, "status": "publish", "per_page": 100, "page": page})
            ids += [i["id"] for i in chunk.get("items", [])]
            if page >= int(chunk.get("pages") or 0):
                break
            page += 1
        ids = sorted(set(ids))[: a.limit or None]
        errors: list[str] = []

        def one(pid: int):
            try:
                return content_audit.analyze_page(c.call("GET", f"/post/{pid}"))
            except AgentError as err:
                errors.append(f"{pid}: {str(err)[:120]}")
                return None

        with ThreadPoolExecutor(max_workers=max(1, a.workers)) as pool:
            pages = [p for p in pool.map(one, ids) if p]
        report = content_audit.aggregate(pages, a.template_min_pages)
        report["errors"] = errors[:20]
        return report
    elif a.cmd == "relations-audit":
        return c.call("GET", "/relations-audit")
    elif a.cmd == "updraft":
        if not a.start:
            status = c.call("GET", "/updraft", {"nonce": a.nonce})
            return updraft_wait(c, a.nonce, a.wait) if (a.nonce and a.wait) else status
        if not a.owner_approved_backup:
            raise AgentError("updraft --start needs --owner-approved-backup")
        started = updraft_start(c, a.what, a.nocloud)
        if not a.wait:
            return started
        final = updraft_wait(c, started.get("nonce", ""), a.wait)
        return {"started": started, "status": final}
    elif a.cmd == "theme-update":
        if not a.owner_approved_theme_update:
            raise AgentError("theme-update needs --owner-approved-theme-update")
        if not a.expected_version:
            raise AgentError("theme-update needs --expected-version")
        report: dict = {"before": c.call("GET", "/theme-update")}
        save_local_backup("theme-update-pre", report["before"])
        site = c.base.split("/wp-json/")[0]
        urls = [u if u.startswith("http") else site + u for u in (a.probe or ["/"])]
        report["probe_before"] = [probe_url(u) for u in urls]
        if a.backup_first:
            started = updraft_start(c, "all", False)
            report["backup"] = {"started": started, "status": updraft_wait(c, started.get("nonce", ""), a.backup_wait)}
            last = report["backup"]["status"].get("last_backup") or {}
            if not ((report["backup"]["status"].get("job") or {}).get("finished") and last.get("success")):
                raise AgentError("UpdraftPlus backup did not finish successfully in time; theme NOT updated: "
                                 + json.dumps(report["backup"]["status"], ensure_ascii=False)[:600])
        report["install"] = theme_install(c, a.version, a.expected_version)
        time.sleep(5)
        try:  # theme 2.22.77+: visitors must not keep the old cached output
            report["cache_purge"] = c.call("POST", "/cache-purge", body={"scope": "all"})
        except AgentError as err:
            report["cache_purge"] = {"error": str(err)[:300]}
        # health is judged on fresh renders (a cache hit would hide a broken theme)
        report["probe_after"] = [probe_url(bust(u)) for u in urls]
        report["healthy"] = probes_healthy(report["probe_after"])
        if not report["healthy"] and a.rollback_to:
            report["rollback"] = theme_install(c, a.rollback_to, a.rollback_to)
            time.sleep(5)
            report["probe_after_rollback"] = [probe_url(bust(u)) for u in urls]
            raise AgentError("probe failed after update; rolled back to " + a.rollback_to + ": "
                             + json.dumps({k: report[k] for k in ("install", "rollback")}, ensure_ascii=False)[:800])
        if not report["healthy"]:
            raise AgentError("probe failed after update and no --rollback-to was given")
        return report
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
