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
import hashlib
import os
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

from job_state import Journal, READ_ONLY, StateError
from write_verification import make_intent

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
    # newline="" keeps CRLF exactly as stored, so a live update changes only what the editor changed.
    if not path:
        return None
    with open(path, encoding="utf-8", newline="") as fh:
        return fh.read()


def read_json(path: str | None) -> dict:
    return {} if not path else json.loads(Path(path).read_text(encoding="utf-8"))


def check_banned(*texts: str | None, fa: bool = True) -> None:
    for t in texts:
        for phrase in BANNED:
            if t and phrase.lower() in t.lower():
                raise AgentError(f"banned phrase in text: {phrase}")
    if not fa:
        return
    # فیلتر املا و نگارش فارسی (دستور مالک، ۱۶ نوامبر ۱۴۰۵): هیچ متنی با نام ماه
    # میلادی یا نیم‌فاصلهٔ غلط به سایت نمی‌رود. هر یافته نوشتن را رد می‌کند.
    from replace_src.fa_lint import scan as fa_scan  # local import: keeps `ping` fast
    for t in texts:
        problems = fa_scan(t or "")
        if problems:
            raise AgentError("فیلتر املا/نگارش: " + " | ".join(problems[:4]))


def check_county_shape(content: str, where: str = "") -> None:
    """Shape guard for a county body on the write path (owner order 2026-11-17).

    The template registry is only worth something if the thing that touches the
    live site enforces it: a body that has the retired/removed sections, that
    lost its section markers, or that carries the theme's own map block is
    refused here, before a backup is taken. Runs on every `update` so a batch
    built with last month's template cannot slip through.

    `body` is the marked HTML of a county page; anything that is not a county
    body (no markers at all) is left to the existing guards.
    """
    from replace_src.template import BY_ID, MARK_OPEN, MARK_CLOSE, REQUIRED_IDS, SECTIONS
    import re as _re

    if not any(MARK_OPEN.format(k) in content for k in BY_ID):
        return  # not a marked county body — not this guard's business
    problems = []
    for s in SECTIONS:
        key = s["id"]
        if key in content and (MARK_OPEN.format(key) not in content or MARK_CLOSE.format(key) not in content):
            problems.append(f"half marker pair for «{key}»")
        if (s.get("legacy") or s.get("removed") or s.get("retired")) and MARK_OPEN.format(key) in content:
            problems.append(f"retired/removed section present: «{key}»")
    # A marked body must mark every required section: mixing a marked chapter
    # with a hand-written one is how old templates creep back in. The H2
    # fallback belongs to pre-marker (legacy) pages only, and those carry no
    # markers at all — `return` above already sent them home.
    missing = [k for k in REQUIRED_IDS if MARK_OPEN.format(k) not in content]
    if missing:
        problems.append(f"required sections missing: {missing}")
    if 'id="sa-map"' in content or "sa-map__facade" in content:
        problems.append("map block baked into post_content")
    if problems:
        raise AgentError(f"{where}county template: " + "; ".join(problems[:4]))


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
        self.county_cover_figures = 0
        self.county_cover_images = 0
        self.county_cover_image_srcs: list[str] = []
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
        self.map_iframes = 0          # our own map block: iframe.sa-map__frame
        self.map_coords = False       # the same iframe carries data-lat/data-lng
        self.map_frame_buttons = 0    # retired «بارگذاری نقشهٔ تعاملی» must never come back
        # v2.22.98: licensed place photos — the credit line is part of the design, so a
        # block without a named photographer is a defect, not a style choice.
        # v2.22.116: «مکان در یک نگاه» برداشته شد؛ گالری تصاویر شهرستان
        # (section.sa-county-gallery) جای آن را گرفته است. شمارندهٔ spotlight
        # نگه داشته شده تا اگر قالب قدیمی برگردد، پروب آن را ببیند و هشدار دهد.
        self.spotlight = 0
        self.spotlight_credits = 0
        self.strip_tiles = 0
        self.strip_credits = 0
        self.county_gallery = 0          # section.sa-county-gallery#county-gallery
        self.county_gallery_slides = 0   # slide.sa-strip__slide داخل همان گالری
        self.county_zoom_frames = 0      # قاب‌های data-sa-zoom (بازشدن لایت‌باکس)
        self._in_spotlight = 0
        self._in_strip_tile = 0
        self._in_county_gallery = 0
        self._in_credit = 0
        self._credit_buf = ""
        self.photo_credits = []       # متن خط اعتبارهای چاپ‌شده (نام عکاس + مجوز) برای بازبینی مالک
        # آکاردئون‌های بستهٔ v2.22.116: فهرست شهرستان‌های هم‌استان + منابع
        self.accordions = 0          # details.sa-county-accordion
        self.accordions_open = 0     # همان‌ها که با open شروع می‌شوند (باید صفر باشد)
        self.accordion_summaries = 0
        self._details_open = []
        # لایهٔ «از تجربهٔ مسافرها» (متای صفحه، بدون ارتقای قالب): شمارش
        # کارت‌های چاپ‌شده تا معلوم شود بلوک واقعاً روی صفحه هست یا نه.
        self.experience_block = 0
        self.experience_facts = 0
        self.experience_seasons = 0
        self.experience_places = 0
        self.experience_tips = 0
        # v2.22.147: شناسنامهٔ تاشو — شمارش خانه‌های جدول شناسنامه و دکمهٔ
        # «نمایش کامل»، تا وارسیِ بعد از انتشار ببیند هر شهرستان چند خانه دارد و
        # دکمه سر جایش است (فقط‌خواندنی، هیچ اثری روی صفحه ندارد).
        self.identity_cells = 0
        self.identity_fold_buttons = 0
        # v2.22.148: سلسله‌مراتب تیتر «تجربهٔ مسافرها» — بلوک باید H3 و
        # زیربخش‌هایش H4 باشند و عنوان‌ها شمارهٔ فارسی بگیرند.
        self.traveller_h3 = 0
        self.traveller_h4 = 0
        self.traveller_numbered = 0
        self._in_traveller = 0
        self._heading_buf = None
        self._in_county_grid = 0
        self._main = 0

    def handle_starttag(self, tag, attrs):
        a = {k.lower(): (v or "") for k, v in attrs}
        if tag == "svg":
            self._svg += 1
        classes = set((a.get("class") or "").split())
        if tag == "main":
            self._main += 1
        if tag == "iframe" and "sa-map__frame" in classes:
            self.map_iframes += 1
            if a.get("data-lat", "").strip() and a.get("data-lng", "").strip():
                self.map_coords = True
        if "sa-map__load" in classes or "sa-map__facade" in classes:
            self.map_frame_buttons += 1
        if tag == "figure" and "sa-county-cover" in classes:
            self.county_cover_figures += 1
        if "sa-field-notes" in classes:
            self.experience_block += 1
        if "sa-field-fact__value" in classes:
            self.experience_facts += 1
        if "sa-field-season__name" in classes:
            self.experience_seasons += 1
        if "sa-field-place" in classes:
            self.experience_places += 1
        if "sa-field-tip" in classes:
            self.experience_tips += 1
        if tag == "div" and "sa-cyber-grid" in classes:
            self._in_county_grid = 1 if "sa-cyber-grid--county" in classes else 0
        elif tag == "div" and "sa-cyber-grid__cell" in classes and self._in_county_grid:
            self.identity_cells += 1
        if tag == "button" and "sa-cyber-id__fold-btn" in classes:
            self.identity_fold_buttons += 1
        if tag == "section" and "sa-field-notes" in classes:
            self._in_traveller = 1
        if self._in_traveller and tag in ("h3", "h4"):
            if tag == "h3":
                self.traveller_h3 += 1
            else:
                self.traveller_h4 += 1
            self._heading_buf = ""
        if "sa-spotlight" in classes:
            self.spotlight += 1
            self._in_spotlight += 1
        if "sa-county-gallery" in classes:
            self.county_gallery += 1
            self._in_county_gallery += 1
        if "sa-strip__slide" in classes and self._in_county_gallery:
            self.county_gallery_slides += 1
        if a.get("data-sa-zoom") and "sa-strip__frame" in classes:
            self.county_zoom_frames += 1
        if "sa-strip__tile" in classes:
            self.strip_tiles += 1
            self._in_strip_tile += 1
        if tag == "details" and "sa-county-accordion" in classes:
            self.accordions += 1
            self._details_open.append("open" in a)
            if "open" in a:
                self.accordions_open += 1
        if tag == "summary" and "sa-county-accordion__summary" in classes:
            self.accordion_summaries += 1
        if ("sa-photo__credit" in classes or "sa-strip__credit" in classes) and not self._in_credit:
            # متن اعتبار تا بسته شدن همین عنصر جمع می‌شود و همان‌جا شمرده می‌شود؛
            # تگ‌های درونی (<a>، <span>) شمارش را جابه‌جا نمی‌کنند.
            self._in_credit = 1
            self._credit_buf = ""
        if tag == "img" and "sa-county-cover__img" in classes:
            self.county_cover_images += 1
            src = a.get("src") or a.get("data-src") or ""
            if src and len(self.county_cover_image_srcs) < 5:
                self.county_cover_image_srcs.append(src)
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
        # خط اعتبار یا <figcaption> است یا <p>؛ تگ‌های درونی (<span>، <a>) آن را نمی‌بندند.
        if self._in_credit and tag in ("figcaption", "p"):
            self._in_credit = 0
            line = " ".join(self._credit_buf.split())
            if line:
                if self._in_strip_tile:
                    self.strip_credits += 1
                elif self._in_spotlight:
                    self.spotlight_credits += 1
                if len(self.photo_credits) < 12:
                    self.photo_credits.append(line)
        if tag == "section" and self._in_spotlight:
            self._in_spotlight -= 1
        if tag == "section" and self._in_county_gallery:
            self._in_county_gallery -= 1
        if tag in ("h3", "h4") and self._heading_buf is not None:
            # شمارهٔ فارسی ابتدای عنوان («۱. ») نشانهٔ شماره‌گذاری زیربخش است.
            if re.match(r"^[0-9\u06F0-\u06F9]+\.", self._heading_buf.strip()):
                self.traveller_numbered += 1
            self._heading_buf = None
        if tag == "section" and self._in_traveller:
            self._in_traveller = 0
        if tag == "details" and self._details_open:
            self._details_open.pop()
        if tag == "article" and self._in_strip_tile:
            self._in_strip_tile -= 1
        if tag == "main" and self._main:
            self._main -= 1
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
        if self._heading_buf is not None:
            self._heading_buf += data
        if self._in_credit:
            self._credit_buf += data  # خط اعتبار عکس: نام عکاس + مجوز
        if self._in_title:
            self.title += data
        if self._in_jsonld:
            self._buf += data
        elif not self._skip and self._main:
            # متنِ <main> فقط: منو، فوتر و آکاردئون خانه را «واژهٔ مقاله» نمی‌شماریم
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


def _jsonld_images(blobs: list[str]) -> list[str]:
    images: list[str] = []

    def add(value):
        if isinstance(value, str) and value.startswith(("http://", "https://")):
            images.append(value)
        elif isinstance(value, dict):
            for key in ("url", "contentUrl"):
                if isinstance(value.get(key), str):
                    images.append(value[key])
        elif isinstance(value, list):
            for item in value:
                add(item)

    def walk(node):
        if isinstance(node, dict):
            if "image" in node:
                add(node["image"])
            for value in node.values():
                if isinstance(value, (dict, list)):
                    walk(value)
        elif isinstance(node, list):
            for value in node:
                walk(value)

    for blob in blobs:
        try:
            walk(json.loads(blob))
        except ValueError:
            continue
    return sorted(set(images))


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
        "county_cover_figures": p.county_cover_figures, "county_cover_images": p.county_cover_images,
        "photo_spotlight": p.spotlight, "photo_spotlight_credits": p.spotlight_credits,
        "photo_strip_tiles": p.strip_tiles, "photo_strip_credits": p.strip_credits,
        "county_gallery": p.county_gallery, "county_gallery_slides": p.county_gallery_slides,
        "county_zoom_frames": p.county_zoom_frames,
        "county_accordions": p.accordions, "county_accordions_open": p.accordions_open,
        "county_accordion_summaries": p.accordion_summaries,
        "photo_credit_texts": p.photo_credits,
        "county_cover_image_srcs": p.county_cover_image_srcs, "jsonld": _jsonld_types(p.jsonld),
        "jsonld_images": _jsonld_images(p.jsonld), "internal_links": p.links_internal,
        "words_rendered": len(text.split()), "viewport": bool(p.meta.get("viewport")),
        "map_iframes": p.map_iframes, "map_frame_buttons": p.map_frame_buttons,
        "map_coords": p.map_coords,
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
                "desc": p.meta.get("description", ""), "h1": p.h1, "og": og,
                "county_cover_figures": p.county_cover_figures,
                "county_cover_images": p.county_cover_images,
                "county_cover_image_srcs": p.county_cover_image_srcs,
                "photo_spotlight": p.spotlight, "photo_spotlight_credits": p.spotlight_credits,
                "photo_strip_tiles": p.strip_tiles, "photo_strip_credits": p.strip_credits,
                "county_gallery": p.county_gallery, "county_gallery_slides": p.county_gallery_slides,
                "county_zoom_frames": p.county_zoom_frames,
                "county_accordions": p.accordions, "county_accordions_open": p.accordions_open,
                "county_accordion_summaries": p.accordion_summaries,
                "photo_credit_texts": p.photo_credits,
                "experience_block": p.experience_block,
                "experience_facts": p.experience_facts,
                "experience_seasons": p.experience_seasons,
                "experience_places": p.experience_places,
                "experience_tips": p.experience_tips,
                "identity_cells": p.identity_cells, "identity_fold_buttons": p.identity_fold_buttons,
                "traveller_h3": p.traveller_h3, "traveller_h4": p.traveller_h4,
                "traveller_numbered": p.traveller_numbered,
                "jsonld": _jsonld_types(p.jsonld), "jsonld_images": _jsonld_images(p.jsonld),
                "map_iframes": p.map_iframes, "map_frame_buttons": p.map_frame_buttons,
                "map_coords": p.map_coords})
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
    p.add_argument("--sync-ready-article", action="store_true", help="sync post fields, meta, FAQ and bundled cover from theme ready-articles/ready-guides data")
    p.add_argument("--expected-modified-gmt", help="refuse if the post changed since this snapshot (edit was prepared from it)")
    p = sub.add_parser("section-tests", help="add a batch of county sections from a tests/ file (runner-side, idempotent)")
    p.add_argument("--file", required=True, help="tests file inside the repository")
    p.add_argument("--dry-run", action="store_true")
    p.add_argument("--apply", action="store_true", help="write (owner-approved); without it the endpoint stays read-only")
    p.add_argument("--remove", action="store_true", help="remove the test block from the ten counties")
    p = sub.add_parser("sections", help="replace ONE marked content section inside a county body (diff-based, keeps the rest)")
    p.add_argument("--section", required=True, help="section id from the template registry (intro, map, history, ...)")
    p.add_argument("--id", type=int, required=True)
    p.add_argument("--section-file", required=True, help="file holding the new section HTML (without markers)")
    p.add_argument("--expected-modified-gmt", default="")
    p.add_argument("--reason", default="")
    p.add_argument("--owner-approved-live-edit", action="store_true")
    p = sub.add_parser("backups")
    p.add_argument("--target", type=int, default=0)
    p = sub.add_parser("featured-audit", help="read-only: featured-image audit (a/b/c groups, theme 2.22.141+)")
    p.add_argument("--type", default="city", choices=["city", "attraction", "post", "province"])
    p = sub.add_parser("storage", help="read-only: real size of agent backups, UpdraftPlus sets and uploads")
    p = sub.add_parser("updraft-drive-test", help="read-only: real Google Drive connection test (token + listing, theme 2.22.133+)")
    p = sub.add_parser("updraft-settings", help="change only interval/retain/retain_db/delete_local (dry run unless --owner-approved-settings)")
    p.add_argument("--interval", default="")
    p.add_argument("--retain", type=int, default=0)
    p.add_argument("--retain-db", type=int, default=0)
    p.add_argument("--delete-local", type=int, default=-1, choices=[-1, 0, 1])
    p.add_argument("--apply", action="store_true")
    p.add_argument("--owner-approved-settings", action="store_true")
    p = sub.add_parser("updraft-log", help="read-only: relevant lines of one UpdraftPlus set log (theme 2.22.130+)")
    p.add_argument("--nonce", required=True)
    p.add_argument("--limit", type=int, default=200)
    p = sub.add_parser("updraft-cloud", help="read-only: three evidences that a set really uploaded to the cloud (theme 2.22.129+)")
    p.add_argument("--nonce", required=True)
    p = sub.add_parser("updraft-delete-local", help="delete a set's local files, only after the cloud copy is verified (dry run unless --owner-approved-prune)")
    p.add_argument("--nonce", required=True)
    p.add_argument("--apply", action="store_true")
    p.add_argument("--owner-approved-prune", action="store_true")
    p = sub.add_parser("updraft-prune", help="delete the files of one outdated UpdraftPlus set (dry run unless --owner-approved-prune)")
    p.add_argument("--nonce", required=True, help="the set's nonce from GET /storage")
    p.add_argument("--owner-approved-prune", action="store_true")
    p.add_argument("--apply", action="store_true", help="really delete (still needs --owner-approved-prune)")
    p = sub.add_parser("backups-prune", help="retention for agent diff backups (dry run unless --owner-approved-prune)")
    p.add_argument("--per-target", type=int, default=3, help="how many backups to keep per post/target")
    p.add_argument("--total", type=int, default=400, help="global ceiling of backup records")
    p.add_argument("--owner-approved-prune", action="store_true", help="owner order: actually delete the old records")
    p.add_argument("--apply", action="store_true", help="do the delete (still needs --owner-approved-prune)")
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
    p = sub.add_parser("photos-prune", help="owner-approved: trash duplicate copies of the same place (newest kept)")
    p.add_argument("--city-id", type=int, required=True)
    p.add_argument("--owner-approved", action="store_true")
    p.add_argument("--dry-run", action="store_true", help="report what would be trashed and change nothing")
    p = sub.add_parser("photos", help="read-only: licensed place photos with author, licence and source")
    p.add_argument("--city-id", type=int, default=0)
    p = sub.add_parser("photos-optimize", help="owner-approved: re-encode a county's photos to 4:3 WebP under 100 KB")
    p.add_argument("--city-id", type=int, default=0, help="0 = every county with photos")
    p.add_argument("--photo-id", action="append", type=int, default=[], help="limit to these attachment ids")
    p.add_argument("--force", action="store_true", help="re-encode even when the file already fits")
    p.add_argument("--limit", type=int, default=30, help="photos per call (the response says how many remain)")
    p.add_argument("--owner-approved", action="store_true")
    p = sub.add_parser("photos-manage", help="owner-approved: hide/unhide, lead, detach, trash or edit one county photo")
    p.add_argument("--photo-id", type=int, required=True)
    p.add_argument("--action", required=True, choices=["hide", "unhide", "lead", "detach", "trash", "update"])
    p.add_argument("--order", type=int, default=0)
    p.add_argument("--place", default="")
    p.add_argument("--highlight", default="")
    p.add_argument("--tag", default="")
    p.add_argument("--note", default="")
    p.add_argument("--owner-approved", action="store_true")
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
    p.add_argument("--backup-nocloud", action="store_true",
                   help="keep that full backup on the server only (skip the remote upload, e.g. when the cloud step failed)")
    p.add_argument("--backup-wait", type=int, default=1500)
    p.add_argument("--probe", action="append", default=[], help="path that must answer 200/301 after the update")
    p.add_argument("--rollback-to", default="", help="version to reinstall if a probe fails")
    p.add_argument("--allow-without-backup", action="store_true",
                   help="owner order: skip the 6-hour UpdraftPlus freshness gate (rollback still armed)")
    p.add_argument("--wait-for-release", type=int, default=0,
                   help="seconds to wait for a just-pushed release (the release job runs in parallel)")
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


THEME_RELEASE_WAIT_SECONDS = 300
THEME_RELEASE_POLL_SECONDS = 15


def theme_install(c: "Client", version: str, expected: str, allow_without_backup: bool = False,
                  wait_seconds: int = 0, sleep=time.sleep) -> dict:
    """Install one release. The rollback stays armed (`--rollback-to` is reinstalled when a
    post-change probe is unhealthy) and the theme is re-downloaded from its GitHub release.

    v2.22.120 (owner order, 28 Aban 1405 — slimmer backups): the 6-hour full-site UpdraftPlus
    gate is gone. Theme code is tagged in Git and a rollback is «install release N-1», which
    never needed a site-wide backup. `allow_without_backup` is kept for old job files.

    `wait_seconds` exists because the push that bumps the version starts two workflows at once:
    the release job that publishes the ZIP and the agent job that installs it. When the agent
    wins that race the site answers 409 («انتشار پیدا نشده»), which is not a real failure — the
    release appears a minute later. Jobs 127 and 130 both died that way and each needed a
    manual re-run, so the install now waits for the release instead of failing on the first 409.
    """
    body = {"owner_approved_theme_update": True, "expected_version": expected}
    if version:
        body["version"] = version
    # No full-site backup is requested for a theme install any more (2.22.120). An owner who
    # explicitly wants one sends require_full_backup through the job step / the site gate.
    if allow_without_backup:
        body["allow_without_backup"] = True
    deadline = time.monotonic() + max(0, int(wait_seconds or 0))
    while True:
        try:
            return c.call("POST", "/theme-update", body=body)
        except AgentError as err:
            pending = "sa_agent_version_mismatch" in str(err)
            if not pending or time.monotonic() >= deadline:
                raise
            sleep(THEME_RELEASE_POLL_SECONDS)


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
    elif a.cmd == "photos-prune":
        if not a.owner_approved:
            raise AgentError("photos-prune needs --owner-approved (owner's explicit order)")
        body = {"city_id": a.city_id, "owner_approved": True, "dry_run": bool(a.dry_run)}
        return c.call("POST", "/photos/prune", body=body)
    elif a.cmd == "photos":
        return c.call("GET", "/photos", {"city_id": a.city_id or None})
    elif a.cmd == "photos-optimize":
        if not a.owner_approved:
            raise AgentError("photos-optimize needs --owner-approved (owner's explicit order)")
        body = {"city_id": a.city_id, "photo_ids": [int(i) for i in a.photo_id],
                "force": bool(a.force), "limit": int(a.limit or 0), "owner_approved": True}
        return c.call("POST", "/photos/optimize", body=body)
    elif a.cmd == "photos-manage":
        if not a.owner_approved:
            raise AgentError("photos-manage needs --owner-approved (owner's explicit order)")
        body = {"id": a.photo_id, "action": a.action, "owner_approved": True}
        if a.order:
            body["order"] = int(a.order)
        for field in ("place", "highlight", "tag", "note"):
            value = getattr(a, field)
            if value:
                body[field] = value
        return c.call("POST", "/photos/manage", body=body)
    elif a.cmd == "get":
        data = c.call("GET", f"/post/{a.id}")
        if a.out:
            Path(a.out).write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        return data
    elif a.cmd == "draft":
        content = read_text(a.content_file) or ""
        check_banned(a.title, content, a.excerpt)
        draft_meta = read_json(a.meta_file)
        from replace_src.fa_lint import scan_meta as fa_scan_meta
        meta_problems = fa_scan_meta(draft_meta)
        if meta_problems:
            raise AgentError("فیلتر املا/نگارش (متا): " + " | ".join(meta_problems[:4]))
        body = {"type": a.type, "title": a.title, "content": content, "excerpt": a.excerpt,
                "slug": a.slug, "parent": a.parent, "meta": draft_meta}
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
        check_county_shape(fields.get("content") or "", f"post {a.id}: ")
        meta_in = read_json(a.meta_file)
        from replace_src.fa_lint import scan_meta as fa_scan_meta
        meta_problems = fa_scan_meta(meta_in)
        if meta_problems:
            raise AgentError("فیلتر املا/نگارش (متا): " + " | ".join(meta_problems[:4]))
        body = {"expected_modified_gmt": current["modified_gmt"], "fields": fields,
                "meta": meta_in, "reason": a.reason}
        if a.owner_approved_live_edit:
            body["owner_approved_live_edit"] = True
        if getattr(a, "sync_ready_article", False):
            body["sync_ready_article"] = True
        try:
            return c.call("POST", f"/post/{a.id}", body=body)
        except AgentError as err:
            if getattr(a, "sync_ready_article", False) and '"sa_agent_nothing"' in str(err):
                return {"id": a.id, "noop": True, "modified_gmt": current.get("modified_gmt", "")}
            raise
    elif a.cmd == "section-tests":
        data = read_json(Path(a.file) if Path(a.file).is_absolute() else (REPO_ROOT / a.file))
        tests = data.get("tests") or []
        if not tests:
            raise AgentError("tests file carries no tests")
        out = c.call("POST", "/section-tests", body={"tests": tests, "dry_run": bool(a.dry_run) or not a.apply,
                                                     "apply": bool(a.apply), "remove": bool(a.remove)})
        return out
    elif a.cmd == "sections":
        from replace_src.template import MARK_CLOSE, MARK_OPEN
        current = c.call("GET", f"/post/{a.id}")
        if a.expected_modified_gmt and current.get("modified_gmt") != a.expected_modified_gmt:
            raise AgentError(f"post {a.id} changed since the snapshot ({a.expected_modified_gmt} -> {current.get('modified_gmt')}); re-read and re-prepare")
        content = current.get("content") or ""
        open_mark = MARK_OPEN.format(a.section)
        close_mark = MARK_CLOSE.format(a.section)
        start = content.find(open_mark)
        if start < 0:
            raise AgentError(f"section '{a.section}' not found on post {a.id}")
        end = content.find(close_mark, start)
        if end < 0:
            raise AgentError(f"section '{a.section}' has no closing marker on post {a.id}")
        new_html = read_text(a.section_file).strip()
        check_banned(new_html)
        from replace_src.fa_lint import scan as fa_scan
        problems = fa_scan(new_html, where=f"{a.id}:{a.section}")
        if problems:
            raise AgentError("فیلتر املا/نگارش (سکشن): " + " | ".join(problems[:4]))
        replaced = content[:start] + open_mark + "\n" + new_html + "\n" + close_mark + content[end + len(close_mark):]
        check_county_shape(replaced, f"post {a.id}: ")
        save_local_backup(f"post-{a.id}-{a.section}", {"id": a.id, "content": content})
        body = {"expected_modified_gmt": current["modified_gmt"], "fields": {"content": replaced},
                "meta": {}, "reason": a.reason or f"بازتولید سکشن {a.section}"}
        if a.owner_approved_live_edit:
            body["owner_approved_live_edit"] = True
        result = c.call("POST", f"/post/{a.id}", body=body)
        return {"section": a.section, "id": a.id, "chars_before": len(content), "chars_after": len(replaced), "result": result}
    elif a.cmd == "backups":
        return c.call("GET", "/backups", {"target": a.target or None})
    elif a.cmd == "featured-audit":
        return c.call("GET", "/featured-audit", {"type": a.type})
    elif a.cmd == "storage":
        return c.call("GET", "/storage")
    elif a.cmd == "updraft-drive-test":
        return c.call("GET", "/updraft/drive-test")
    elif a.cmd == "updraft-settings":
        body: dict = {"dry_run": not a.apply}
        if a.interval:
            body["updraft_interval"] = a.interval
        if a.retain:
            body["updraft_retain"] = a.retain
        if a.retain_db:
            body["updraft_retain_db"] = a.retain_db
        if a.delete_local in (0, 1):
            body["updraft_delete_local"] = a.delete_local
        if a.apply:
            if not a.owner_approved_settings:
                raise AgentError("updraft-settings --apply needs --owner-approved-settings (owner's explicit order)")
            body["owner_approved_settings"] = True
        return c.call("POST", "/updraft/settings", body=body)
    elif a.cmd == "updraft-log":
        return c.call("GET", "/updraft/log", {"nonce": a.nonce, "limit": a.limit})
    elif a.cmd == "updraft-cloud":
        return c.call("GET", "/updraft/cloud", {"nonce": a.nonce})
    elif a.cmd == "updraft-delete-local":
        if a.apply and not a.owner_approved_prune:
            raise AgentError("updraft-delete-local --apply needs --owner-approved-prune (owner's explicit order)")
        body = {"nonce": a.nonce, "dry_run": not a.apply, "owner_approved_prune": bool(a.owner_approved_prune)}
        return c.call("POST", "/updraft/delete-local", body=body)
    elif a.cmd == "updraft-prune":
        if a.apply and not a.owner_approved_prune:
            raise AgentError("updraft-prune --apply needs --owner-approved-prune (owner's explicit order)")
        body = {"nonce": a.nonce, "dry_run": not (a.apply and a.owner_approved_prune)}
        if a.owner_approved_prune:
            body["owner_approved_prune"] = True
        return c.call("POST", "/updraft/prune", body=body)
    elif a.cmd == "backups-prune":
        if a.apply and not a.owner_approved_prune:
            raise AgentError("backups-prune --apply needs --owner-approved-prune (owner's explicit order)")
        body = {"per_target": a.per_target, "total": a.total,
                "dry_run": not (a.apply and a.owner_approved_prune)}
        if a.owner_approved_prune:
            body["owner_approved_prune"] = True
        return c.call("POST", "/backups/prune", body=body)
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
            # Deprecated in 2.22.120 (owner order: no full-site backup per change). Kept so an
            # old job file still runs, but no new job should set it; the theme install itself
            # is reversible through its Git-tagged release.
            started = updraft_start(c, "all", bool(a.backup_nocloud))
            report["backup"] = {"started": started, "status": updraft_wait(c, started.get("nonce", ""), a.backup_wait)}
            last = report["backup"]["status"].get("last_backup") or {}
            if not ((report["backup"]["status"].get("job") or {}).get("finished") and last.get("success")):
                raise AgentError("UpdraftPlus backup did not finish successfully in time; theme NOT updated: "
                                 + json.dumps(report["backup"]["status"], ensure_ascii=False)[:600])
        report["install"] = theme_install(c, a.version, a.expected_version, bool(a.allow_without_backup),
                                          wait_seconds=a.wait_for_release)
        time.sleep(5)
        try:  # theme 2.22.77+: visitors must not keep the old cached output
            report["cache_purge"] = c.call("POST", "/cache-purge", body={"scope": "all"})
        except AgentError as err:
            report["cache_purge"] = {"error": str(err)[:300]}
        # health is judged on fresh renders (a cache hit would hide a broken theme)
        report["probe_after"] = [probe_url(bust(u)) for u in urls]
        report["healthy"] = probes_healthy(report["probe_after"])
        if not report["healthy"] and a.rollback_to:
            # A rollback is never blocked by the backup gate: it is the safety net itself.
            report["rollback"] = theme_install(c, a.rollback_to, a.rollback_to, True)
            time.sleep(5)
            report["probe_after_rollback"] = [probe_url(bust(u)) for u in urls]
            # compact probe verdict first — the full report is too big for the error string
            probes_short = [{"url": p.get("url", ""), "status": p.get("status", 0),
                             "error": p.get("error", ""), "title": (p.get("title") or "")[:60]}
                            for p in report["probe_after"]]
            raise AgentError("probe failed after update; rolled back to " + a.rollback_to + ": "
                             + json.dumps({"probes": probes_short,
                                           "install": {k: report["install"].get(k) for k in ("updated", "from", "to")}},
                                          ensure_ascii=False)[:800])
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
        if pos in step and not isinstance(step[pos], list):  # a list of ids is a repeated --id flag
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


def job_deferred(job: dict, now: datetime | None = None) -> bool:
    """A job with `not_before` (ISO-8601 UTC) waits for a later run (daily page caps)."""
    nb = job.get("not_before")
    if not nb:
        return False
    when = datetime.fromisoformat(str(nb).replace("Z", "+00:00"))
    if when.tzinfo is None:
        when = when.replace(tzinfo=timezone.utc)
    return (now or datetime.now(timezone.utc)) < when


def unhealthy_pages(probes: list[dict]) -> list[str]:
    """Live-edit health rule: 200, a title, `index` robots, exactly one H1 — and on a
    county page the map is the OPEN interactive iframe (no «بارگذاری» button, 2.22.91)."""
    bad = []
    for pr in probes:
        url = pr.get("url", "?")
        if int(pr.get("status") or 0) != 200 or not pr.get("title"):
            bad.append(f"{url} status {pr.get('status')}")
        elif "noindex" in str(pr.get("robots", "")):
            bad.append(f"{url} robots {pr.get('robots')}")
        elif pr.get("h1") is not None and int(pr.get("h1")) != 1:
            bad.append(f"{url} h1 {pr.get('h1')}")
        elif "city" in str(pr.get("url", "")) and pr.get("map_iframes") is not None:
            # A page with an OPEN map must have it (2.22.91: no «بارگذاری» gate,
            # ever). A county with no coordinates has no map to render and is
            # «incomplete», not broken — Khorramshahr is that case, and calling
            # it broken once rolled back a healthy Maragheh edit.
            if int(pr.get("map_iframes") or 0) < 1 and pr.get("map_coords"):
                bad.append(f"{url} map iframe missing")
        if "city" in str(pr.get("url", "")) and pr.get("map_frame_buttons"):
            bad.append(f"{url} retired map button is back ({pr.get('map_frame_buttons')})")
    return bad


def rollback(c: "Client", backup_ids: list[int]) -> list[dict]:
    """Restore this job's server backups, newest first; never raises."""
    out = []
    for bid in reversed(backup_ids):
        try:
            out.append({"backup_id": bid, "result": c.call("POST", f"/restore/{bid}", body={})})
        except Exception as err:
            # Unexpected failures must not prevent attempts for earlier backups.
            # Do not put arbitrary exception text (which may contain secrets) in reports.
            message = str(err) if isinstance(err, AgentError) else f"{type(err).__name__}: restore failed"
            out.append({"backup_id": bid, "error": message})
    return out


def run_county_covers(step: dict) -> dict:
    """Build and attach county cyber-cards only from an explicitly approved job step."""
    if step.get("owner_approved") is not True:
        raise AgentError("county-covers needs owner_approved: true")
    force = step.get("force_replace", False)
    if not isinstance(force, bool):
        raise AgentError("county-covers force_replace must be a JSON boolean")
    try:
        max_items = int(step.get("max_items", 0))
        start_index = int(step.get("start_index", 0))
    except (TypeError, ValueError) as err:
        raise AgentError("county-covers max_items and start_index must be non-negative integers") from err
    if max_items < 0 or start_index < 0:
        raise AgentError("county-covers max_items and start_index must be non-negative integers")
    tool_dir = REPO_ROOT / "tools" / "images" / "county-covers"
    env = os.environ.copy()
    env.update({"OWNER_APPROVED": "yes", "FORCE_REPLACE": "yes" if force else "no", "MAX_ITEMS": str(max_items), "START_INDEX": str(start_index)})
    env.setdefault("REQUEST_DELAY_MS", "250")
    render_command = ["node", "render.mjs"]
    if max_items or start_index:
        registry = json.loads((tool_dir / "metadata.json").read_text(encoding="utf-8"))
        selected = registry["counties"][start_index:start_index + max_items if max_items else None]
        render_command.extend(row["slug"] for row in selected)
    commands = [
        ["npm", "ci", "--no-audit", "--no-fund"],
        ["node", "metadata.mjs"],
        ["node", "test-metadata.mjs"],
        render_command,
        ["node", "sync-live.mjs"],
    ]
    for command in commands:
        try:
            completed = subprocess.run(command, cwd=tool_dir, env=env, capture_output=True, text=True, check=False)
        except FileNotFoundError as err:
            raise AgentError(f"county-covers runtime is missing: {err}") from err
        stdout, stderr = completed.stdout or "", completed.stderr or ""
        secret = env.get("SA_APP_PASSWORD", "")
        if secret:
            stdout, stderr = stdout.replace(secret, "***"), stderr.replace(secret, "***")
        if stdout:
            print(stdout[-12000:], end="")
        if stderr:
            print(stderr[-4000:], file=sys.stderr, end="")
        if completed.returncode:
            detail = (stderr + "\n" + stdout).strip()
            if secret:
                detail = detail.replace(secret, "***")
            detail = detail[-1800:]
            raise AgentError(f"county-covers command failed ({' '.join(command)}; exit {completed.returncode}): {detail}")
    reports = sorted((tool_dir / "outputs").glob("sync-report-*.json"), key=lambda p: p.stat().st_mtime, reverse=True)
    if not reports:
        raise AgentError("county-covers completed without a live sync report")
    return json.loads(reports[0].read_text(encoding="utf-8"))


def run_photos_import(step: dict) -> dict:
    """Import a licensed, attributed photo batch through the site's own /photos/import route.

    Why a separate command: this sandbox cannot download from image hosts, but the site
    can. The batch file only carries URLs plus the licence and photographer we verified
    on the source page; the theme re-checks both before it stores anything.
    """
    batch = str(step.get("batch") or "").strip()
    if not batch:
        raise AgentError("photos-import needs batch: tools/photos/<name>.json")
    path = (REPO_ROOT / batch).resolve()
    if REPO_ROOT not in path.parents:
        raise AgentError("photos-import batch must live inside the repository")
    tool_dir = REPO_ROOT / "tools" / "photos"
    env = os.environ.copy()
    try:
        completed = subprocess.run(
            [sys.executable, str(tool_dir / "import_photos.py"), str(path)],
            cwd=REPO_ROOT, env=env, capture_output=True, text=True, check=False,
        )
    except FileNotFoundError as err:
        raise AgentError(f"photos-import runtime is missing: {err}") from err
    stdout, stderr = completed.stdout or "", completed.stderr or ""
    secret = env.get("SA_APP_PASSWORD", "")
    if secret:
        stdout, stderr = stdout.replace(secret, "***"), stderr.replace(secret, "***")
    if stdout:
        print(stdout[-4000:], end="")
    if stderr:
        print(stderr[-4000:], file=sys.stderr, end="")
    if completed.returncode:
        raise AgentError(f"photos-import failed (exit {completed.returncode}): {(stderr or stdout).strip()[-1500:]}")
    report_path = tool_dir / "results" / f"{path.stem}.json"
    if not report_path.exists():
        raise AgentError("photos-import finished without a result report")
    return json.loads(report_path.read_text(encoding="utf-8"))


class JournalledClient:
    """Checkpoint the hash immediately before sending a supported post update."""
    def __init__(self, client, journal):
        self._client, self._journal = client, journal

    def __getattr__(self, name):
        return getattr(self._client, name)

    def call(self, method, path, params=None, body=None):
        active = self._journal.data['active']
        match = re.fullmatch(r"/post/(\d+)", path)
        if (active and active.get('phase') == 'executing' and active.get('command') == 'update'
                and method == 'POST' and match):
            active['intent'] = make_intent(int(match.group(1)), body)
            self._journal.save()  # failure means the live write is never sent
        return self._client.call(method, path, params=params, body=body)


def run_job(job_path: str, c: "Client") -> dict:
    """Execute under an independent, locked state journal; fail closed on uncertainty."""
    try:
        with Journal(REPO_ROOT.resolve()) as journal:
            return _run_job(job_path, JournalledClient(c, journal), journal)
    except (StateError, OSError) as err:
        raise AgentError(f"job state: {err}") from err


def _run_job(job_path: str, c: "Client", journal: Journal) -> dict:
    """Run a job file (a list of steps); stop at the first refusal. Writes results/<name>.json."""
    path = (REPO_ROOT / job_path).resolve() if not Path(job_path).is_absolute() else Path(job_path)
    job = json.loads(path.read_text(encoding="utf-8"))
    steps = job.get("steps", []) if isinstance(job, dict) else job
    opts = job if isinstance(job, dict) else {}
    if job_deferred(opts):
        print(f"deferred {path.name}: not_before {opts['not_before']}", file=sys.stderr)
        return {"deferred": True, "not_before": opts["not_before"]}
    fingerprint = hashlib.sha256(path.read_bytes()).hexdigest()
    journal.recover(c, requested_job=path.name, fingerprint=fingerprint)
    if (RESULTS_DIR / path.name).exists():
        raise StateError('Result already exists; do not rerun the job.')
    journal.start(path.name, opts.get("rollback_on_fail"), fingerprint,
                  all(isinstance(step, dict) and step.get("cmd") in READ_ONLY for step in steps))
    parser = build_parser()
    report = {"job": str(path.relative_to(REPO_ROOT)) if REPO_ROOT in path.parents else str(path),
              "started_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
              "note": opts.get("note", ""), "ok": True, "steps": []}
    backups: list[int] = []
    for i, step in enumerate(steps, 1):
        entry = {"n": i, "cmd": step.get("cmd")}
        journal.before_step(step.get("cmd"))
        try:
            if step.get("cmd") == "county-covers":
                entry["result"] = run_county_covers(step)
            elif step.get("cmd") == "photos-import":
                entry["result"] = run_photos_import(step)
            else:
                entry["result"] = run(parser.parse_args(step_to_argv(step)), c)
            if step.get("cmd") == "update" and isinstance(entry["result"], dict) and entry["result"].get("backup_id"):
                backups.append(int(entry["result"]["backup_id"]))
            journal.after_step(step.get("cmd"), entry["result"])
            if step.get("cmd") == "probe" and opts.get("rollback_on_fail"):
                bad = unhealthy_pages(entry["result"].get("probes", []))
                if bad:
                    raise AgentError("post-change probe failed: " + "; ".join(bad))
        except (OSError, StateError):
            # Never continue or restore when the journal itself may not be durable.
            raise
        except (Exception, SystemExit) as err:
            # argparse exits with code 2 and no message when a step carries a flag the parser
            # does not know; "error: 2" told nobody what was wrong (jobs 43 and 128 died that way).
            entry["error"] = (
                f"bad step {i} ({step.get('cmd')}): a flag in this step is not accepted by the "
                f"command — run tools/wp-agent/test_wp_agent_jobs.py"
                if isinstance(err, SystemExit) and not isinstance(err, AgentError)
                else str(err) if isinstance(err, AgentError)
                else f"{type(err).__name__}: unexpected step failure"
            )
            report["ok"] = False
            report["steps"].append(entry)
            break
        report["steps"].append(entry)
    if not report["ok"]:
        active = journal.data['active']
        # An error in a write may mean the server changed state but the response was lost.
        # Never replay or roll back blindly in that case.
        if active['phase'] == 'executing' and active.get('command') not in READ_ONLY:
            report['recovery'] = 'manual reconciliation required: write outcome unknown'
        elif active['untracked']:
            report['recovery'] = 'manual reconciliation required: non-reversible step'
        elif opts.get("rollback_on_fail") and backups:
            try:
                journal.recover(c, allow_rollback=True)
            except StateError as err:
                if journal.data['active'] is not None:
                    report['recovery'] = str(err)
                else:
                    report['rollback'] = [{'backup_id': bid, 'restored': True} for bid in reversed(backups)]
        else:
            journal.finish('failed')
    else:
        journal.finish('complete')
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
