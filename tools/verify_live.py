#!/usr/bin/env python3
"""Owner-run GET-only verifier for approved live URLs. Never run during offline review."""

from __future__ import annotations

import argparse
import csv
import json
import sys
import time
from html.parser import HTMLParser
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener

TIMEOUT_SECONDS = 15
REQUEST_INTERVAL_SECONDS = 1.5
DEFAULT_BASE_URL = "https://sarzaminaryan.ir"
ALLOWED_HOSTS = {"sarzaminaryan.ir", "www.sarzaminaryan.ir"}
HTTPS_LINK_ALLOWLIST = {
    "www.iew.ir",
    "www.trjums.ac.ir",
    "lorestan.irib.ir",
    "wikipedia.org",
    "wikimedia.org",
}
DEFAULT_PATHS = (
    "/",
    "/blog/",
    "/about/",
    "/contact/",
    "/city/dorud/",
    "/editorial-policy/",
    "/city/sabzevar/",
    "/city/narmashir/",
    "/attraction/babahur-forest-park-dorud/",
)


class SameSiteHttpsRedirectHandler(HTTPRedirectHandler):
    """Follow only HTTPS redirects that stay on the approved site hosts."""

    def redirect_request(self, request, response, code, message, headers, new_url):
        parts = urlparse(new_url)
        if parts.scheme != "https" or parts.hostname not in ALLOWED_HOSTS:
            raise URLError("Refusing redirect outside the approved HTTPS hosts")
        return super().redirect_request(request, response, code, message, headers, new_url)


class PageAuditor(HTMLParser):
    """Collect lightweight SEO and link checks without executing page scripts."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title_parts = []
        self.description = ""
        self.robots = ""
        self.in_title = False
        self.schema_parts = []
        self.current_schema = None
        self.unresolved_w_hrefs = 0
        self.unallowlisted_http_hrefs = 0
        self.blank_without_noopener = 0

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag.lower() == "title":
            self.in_title = True
        if tag.lower() == "meta":
            name = (values.get("name") or "").lower()
            if name == "description":
                self.description = values.get("content") or ""
            elif name == "robots":
                self.robots = values.get("content") or ""
        if tag.lower() == "a":
            href = values.get("href") or ""
            if re_fullmatch_w_token(href):
                self.unresolved_w_hrefs += 1
            parsed = urlparse(href)
            if parsed.scheme.lower() == "http" and not allowed_https_host(parsed.hostname or ""):
                self.unallowlisted_http_hrefs += 1
            if (values.get("target") or "").lower() == "_blank":
                rel = set((values.get("rel") or "").lower().split())
                if "noopener" not in rel:
                    self.blank_without_noopener += 1
        if tag.lower() == "script" and (values.get("type") or "").lower() == "application/ld+json":
            self.current_schema = []

    def handle_endtag(self, tag):
        if tag.lower() == "title":
            self.in_title = False
        if tag.lower() == "script" and self.current_schema is not None:
            self.schema_parts.append("".join(self.current_schema))
            self.current_schema = None

    def handle_data(self, data):
        if self.in_title:
            self.title_parts.append(data)
        if self.current_schema is not None:
            self.current_schema.append(data)

    def result(self):
        ids = []
        types = set()
        invalid_jsonld = 0
        for raw in self.schema_parts:
            try:
                document = json.loads(raw)
            except (TypeError, json.JSONDecodeError):
                invalid_jsonld += 1
                continue
            nodes = document.get("@graph", []) if isinstance(document, dict) else []
            if isinstance(document, dict) and not nodes and "@id" in document:
                nodes = [document]
            for node in nodes if isinstance(nodes, list) else []:
                if not isinstance(node, dict):
                    continue
                if node.get("@id"):
                    ids.append(node["@id"])
                node_types = node.get("@type", [])
                types.update(node_types if isinstance(node_types, list) else [node_types])
        return {
            "title_present": bool("".join(self.title_parts).strip()),
            "description_present": bool(self.description.strip()),
            "robots": self.robots,
            "unresolved_w_hrefs": self.unresolved_w_hrefs,
            "unallowlisted_http_hrefs": self.unallowlisted_http_hrefs,
            "blank_without_noopener": self.blank_without_noopener,
            "duplicate_schema_ids": len(ids) - len(set(ids)),
            "invalid_jsonld_scripts": invalid_jsonld,
            "schema_types": sorted(str(value) for value in types),
        }


def re_fullmatch_w_token(value: str) -> bool:
    value = value.strip()
    return len(value) > 2 and value.startswith("W_") and all(char.isascii() and (char.isalnum() or char == "_") for char in value[2:])


def allowed_https_host(host: str) -> bool:
    host = host.lower().rstrip(".")
    return host in HTTPS_LINK_ALLOWLIST or any(
        host.endswith("." + domain) for domain in ("wikipedia.org", "wikimedia.org")
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="Approved site origin (default: %(default)s)")
    parser.add_argument("--paths-file", type=Path, help="Optional UTF-8 file with one site-relative path per line")
    parser.add_argument("--output", type=Path, help="Optional CSV output path")
    parser.add_argument("--yes-i-authorize-live-get", action="store_true", help="Explicitly authorize outbound GET requests")
    return parser.parse_args()


def read_paths(path_file: Path | None) -> list[str]:
    if path_file is None:
        return list(DEFAULT_PATHS)
    paths = []
    for line in path_file.read_text(encoding="utf-8").splitlines():
        value = line.strip()
        if value and not value.startswith("#"):
            paths.append(value)
    return paths


def main() -> int:
    args = parse_args()
    base = args.base_url.rstrip("/") + "/"
    base_parts = urlparse(base)
    if base_parts.scheme != "https" or base_parts.hostname not in ALLOWED_HOSTS:
        print("Refusing: base URL must be HTTPS on sarzaminaryan.ir or www.sarzaminaryan.ir.", file=sys.stderr)
        return 2
    if not args.yes_i_authorize_live_get:
        print("No request sent. Re-run with --yes-i-authorize-live-get after owner authorization.")
        return 2

    paths = read_paths(args.paths_file)
    rows = []
    previous_request = 0.0
    opener = build_opener(SameSiteHttpsRedirectHandler)
    for path in paths:
        if not path.startswith("/") or path.startswith("//"):
            print(f"Skipping non-relative path: {path}", file=sys.stderr)
            continue
        url = urljoin(base, path.lstrip("/"))
        if urlparse(url).hostname not in ALLOWED_HOSTS:
            print(f"Skipping non-allowlisted URL: {url}", file=sys.stderr)
            continue
        wait = REQUEST_INTERVAL_SECONDS - (time.monotonic() - previous_request)
        if previous_request and wait > 0:
            time.sleep(wait)
        request = Request(url, method="GET", headers={"User-Agent": "SarzaminAryan-Owner-QA/1.0"})
        previous_request = time.monotonic()
        try:
            with opener.open(request, timeout=TIMEOUT_SECONDS) as response:
                body = response.read().decode("utf-8", errors="replace")
                audit = PageAuditor()
                audit.feed(body)
                rows.append((url, response.status, response.geturl(), "", audit.result()))
        except HTTPError as error:
            rows.append((url, error.code, error.geturl(), "HTTPError", {}))
        except (TimeoutError, URLError, OSError) as error:
            rows.append((url, "", getattr(error, "url", "") or "", type(error).__name__, {}))
        print(f"{rows[-1][1] or 'ERR'}\t{url}\t{rows[-1][2]}\t{json.dumps(rows[-1][4], ensure_ascii=False)}\t{rows[-1][3]}")

    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        with args.output.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.writer(handle)
            writer.writerow(("requested_url", "status", "final_url", "error", "checks_json"))
            writer.writerows((url, status, final_url, error, json.dumps(checks, ensure_ascii=False)) for url, status, final_url, error, checks in rows)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
