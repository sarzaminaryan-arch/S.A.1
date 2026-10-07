#!/usr/bin/env python3
"""Audit a WordPress WXR export without loading its full content into memory.

The report is a deterministic editorial/technical checklist aid, not an SEO score.
It reads only the selected post fields and SEO meta keys; it never prints post bodies,
raw metadata, authors, emails, or database credentials.

Usage:
  python3 tools/audit_wxr_content.py export.xml --site-url https://sarzaminaryan.ir
  python3 tools/audit_wxr_content.py export.xml --csv /tmp/audit.csv --json /tmp/audit.json
"""
from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlparse

WXR_LOCAL_NAMES = {
    "post_id",
    "post_type",
    "status",
    "post_name",
    "postmeta",
    "meta_key",
    "meta_value",
}
SEO_TITLE_KEYS = ("sa_seo_title", "rank_math_title", "_yoast_wpseo_title")
SEO_DESCRIPTION_KEYS = (
    "sa_seo_description",
    "rank_math_description",
    "_yoast_wpseo_metadesc",
)
SEO_KEYWORD_KEYS = ("sa_focus_keyword", "rank_math_focus_keyword", "_yoast_wpseo_focuskw")
EDITORIAL_RESIDUE_PATTERNS = (
    re.compile(r"\b(?:TODO|FIXME|TBD|PLACEHOLDER|INSERT[_ -]?HERE|LIPSUM)\b", re.I),
    re.compile(r"\{\{[^{}]{1,120}\}\}"),
    re.compile(r"(?:برای انتشار نهایی|یادداشت برای نویسنده|یادداشت ویراستاری|این بخش را تکمیل کنید)"),
    re.compile(r"«\s*»"),
)
URL_RE = re.compile(r"https?://[^\s|<>]+", re.I)
WORD_RE = re.compile(r"[\w]+(?:\u200c[\w]+)*", re.UNICODE)


def local_name(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def node_text(node: ET.Element | None) -> str:
    return "" if node is None or node.text is None else node.text.strip()


def direct_child_text(item: ET.Element, name: str) -> str:
    for child in item:
        if local_name(child.tag) == name:
            return node_text(child)
    return ""


def descendant_text(node: ET.Element, name: str) -> str:
    for child in node.iter():
        if local_name(child.tag) == name:
            return node_text(child)
    return ""


def extract_meta(item: ET.Element) -> dict[str, str]:
    meta: dict[str, str] = {}
    for node in item:
        if local_name(node.tag) != "postmeta":
            continue
        key = descendant_text(node, "meta_key")
        value = descendant_text(node, "meta_value")
        if key:
            meta[key] = value
    return meta


def first_meta(meta: dict[str, str], candidates: tuple[str, ...]) -> str:
    for key in candidates:
        value = meta.get(key, "").strip()
        if value:
            return value
    return ""


class ContentAuditParser(HTMLParser):
    """Collect accessible-link, image-alt, heading, and text metrics from post HTML."""

    def __init__(self, site_host: str) -> None:
        super().__init__(convert_charrefs=True)
        self.site_host = site_host
        self.text_parts: list[str] = []
        self.headings = Counter()
        self.images = 0
        self.images_missing_alt = 0
        self.anchors: list[dict[str, object]] = []
        self.anchor_stack: list[dict[str, object]] = []
        self.skip_tags: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag = tag.lower()
        attrs_map = {key.lower(): value or "" for key, value in attrs}
        if tag in {"script", "style", "noscript"}:
            self.skip_tags.append(tag)
            return
        if self.skip_tags:
            return
        if tag in {"h1", "h2", "h3", "h4", "h5", "h6"}:
            self.headings[tag] += 1
        if tag == "a":
            self.anchor_stack.append(
                {
                    "href": attrs_map.get("href", "").strip(),
                    "aria_label": attrs_map.get("aria-label", "").strip(),
                    "title": attrs_map.get("title", "").strip(),
                    "parts": [],
                }
            )
        if tag == "img":
            self.images += 1
            if "alt" not in attrs_map:
                self.images_missing_alt += 1
            alt = attrs_map.get("alt", "").strip()
            if alt and self.anchor_stack:
                self.anchor_stack[-1]["parts"].append(alt)

    def handle_startendtag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attrs)
        if tag.lower() not in {"img", "br", "hr", "input", "source", "wbr"}:
            self.handle_endtag(tag)

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        if self.skip_tags:
            if tag == self.skip_tags[-1]:
                self.skip_tags.pop()
            return
        if tag == "a" and self.anchor_stack:
            self.anchors.append(self.anchor_stack.pop())

    def handle_data(self, data: str) -> None:
        if self.skip_tags or not data:
            return
        self.text_parts.append(data)
        if self.anchor_stack:
            self.anchor_stack[-1]["parts"].append(data)

    def metrics(self) -> dict[str, object]:
        # Close any malformed, unclosed anchors conservatively.
        self.anchors.extend(reversed(self.anchor_stack))
        self.anchor_stack.clear()
        internal = 0
        external = 0
        domains: set[str] = set()
        empty_anchors = 0
        for anchor in self.anchors:
            label = " ".join(str(part) for part in anchor["parts"]).strip()
            accessible_name = label or str(anchor["aria_label"]) or str(anchor["title"])
            if not accessible_name:
                empty_anchors += 1
            href = str(anchor["href"])
            parsed = urlparse(href)
            if href.startswith("/") and not href.startswith("//"):
                internal += 1
            elif parsed.hostname:
                host = parsed.hostname.lower().removeprefix("www.")
                if host == self.site_host:
                    internal += 1
                elif parsed.scheme in {"http", "https"}:
                    external += 1
                    domains.add(host)
        text = " ".join(self.text_parts)
        word_count = len(WORD_RE.findall(text))
        has_residue = any(pattern.search(text) for pattern in EDITORIAL_RESIDUE_PATTERNS)
        return {
            "word_count": word_count,
            "h1_count": int(self.headings["h1"]),
            "h2_count": int(self.headings["h2"]),
            "image_count": self.images,
            "images_missing_alt": self.images_missing_alt,
            "internal_links": internal,
            "external_links": external,
            "external_domains": len(domains),
            "empty_anchors": empty_anchors,
            "editorial_residue": has_residue,
        }


def parse_faq_count(value: str) -> int:
    if not value:
        return 0
    try:
        parsed = json.loads(value)
    except (json.JSONDecodeError, TypeError):
        return 0
    if not isinstance(parsed, list):
        return 0
    return sum(
        1
        for row in parsed
        if isinstance(row, dict)
        and str(row.get("q", "")).strip()
        and str(row.get("a", "")).strip()
    )


def audit_item(item: ET.Element, site_host: str) -> dict[str, object]:
    meta = extract_meta(item)
    content = direct_child_text(item, "encoded")
    parser = ContentAuditParser(site_host)
    try:
        parser.feed(content)
        parser.close()
    except Exception:
        # HTMLParser is intentionally tolerant; retain other metadata if malformed HTML
        # triggers a Python-version-specific parser exception.
        pass
    metrics = parser.metrics()
    post_type = direct_child_text(item, "post_type") or "unknown"
    status = direct_child_text(item, "status") or "unknown"
    slug = direct_child_text(item, "post_name")
    row: dict[str, object] = {
        "post_id": direct_child_text(item, "post_id"),
        "post_type": post_type,
        "status": status,
        "slug": slug,
        "url": direct_child_text(item, "link"),
        "seo_title": first_meta(meta, SEO_TITLE_KEYS),
        "seo_description": first_meta(meta, SEO_DESCRIPTION_KEYS),
        "focus_keyword": first_meta(meta, SEO_KEYWORD_KEYS),
        "faq_count": parse_faq_count(meta.get("sa_faq", "")),
        "source_count": len(URL_RE.findall(meta.get("sa_sources", ""))),
        **metrics,
    }
    flags: list[str] = []
    if row["h1_count"]:
        flags.append("h1_inside_editor_content")
    if row["empty_anchors"]:
        flags.append("empty_or_unlabelled_link")
    if row["images_missing_alt"]:
        flags.append("image_missing_alt_attribute")
    if row["editorial_residue"]:
        flags.append("possible_editorial_placeholder")
    if not row["seo_title"]:
        flags.append("no_exported_seo_title_override")
    if not row["seo_description"]:
        flags.append("no_exported_seo_description_override")
    row["flags"] = flags
    # Do not return SEO values or article text in the audit output.
    row.pop("seo_title")
    row.pop("seo_description")
    row.pop("focus_keyword")
    return row


def audit_wxr(path: Path, site_url: str) -> dict[str, object]:
    parsed_site = urlparse(site_url)
    if not parsed_site.hostname:
        raise ValueError("--site-url must include a hostname, e.g. https://sarzaminaryan.ir")
    site_host = parsed_site.hostname.lower().removeprefix("www.")
    rows: list[dict[str, object]] = []
    for _, element in ET.iterparse(path, events=("end",)):
        if local_name(element.tag) == "item":
            rows.append(audit_item(element, site_host))
            element.clear()

    type_counts = Counter(str(row["post_type"]) for row in rows)
    status_counts = Counter(str(row["status"]) for row in rows)
    title_slugs: dict[str, list[str]] = defaultdict(list)
    description_slugs: dict[str, list[str]] = defaultdict(list)
    slug_occurrences: dict[tuple[str, str], list[str]] = defaultdict(list)

    # A second lightweight pass extracts only SEO metadata for duplicate checks.
    for _, element in ET.iterparse(path, events=("end",)):
        if local_name(element.tag) == "item":
            meta = extract_meta(element)
            slug = direct_child_text(element, "post_name") or f"post-{direct_child_text(element, 'post_id')}"
            title = first_meta(meta, SEO_TITLE_KEYS)
            description = first_meta(meta, SEO_DESCRIPTION_KEYS)
            if title:
                title_slugs[" ".join(title.casefold().split())].append(slug)
            if description:
                description_slugs[" ".join(description.casefold().split())].append(slug)
            post_type = direct_child_text(element, "post_type") or "unknown"
            if direct_child_text(element, "post_name"):
                slug_occurrences[(post_type, direct_child_text(element, "post_name"))].append(slug)
            element.clear()

    def duplicate_groups(groups: dict[str, list[str]]) -> list[dict[str, object]]:
        return [
            {"slugs": sorted(set(slugs)), "occurrences": len(slugs)}
            for slugs in groups.values()
            if len(slugs) > 1
        ]

    duplicate_slugs = [
        {"post_type": post_type, "slug": slug, "occurrences": len(values)}
        for (post_type, slug), values in slug_occurrences.items()
        if len(values) > 1
    ]
    word_counts = sorted(int(row["word_count"]) for row in rows)
    p50 = word_counts[len(word_counts) // 2] if word_counts else 0
    summary = {
        "file": path.name,
        "items": len(rows),
        "types": dict(sorted(type_counts.items())),
        "statuses": dict(sorted(status_counts.items())),
        "posts_with_body_h1": sum(bool(row["h1_count"]) for row in rows),
        "posts_with_editorial_residue": sum(bool(row["editorial_residue"]) for row in rows),
        "posts_with_empty_anchors": sum(bool(row["empty_anchors"]) for row in rows),
        "images_missing_alt_attribute": sum(int(row["images_missing_alt"]) for row in rows),
        "missing_seo_title_override": sum("no_exported_seo_title_override" in row["flags"] for row in rows),
        "missing_seo_description_override": sum("no_exported_seo_description_override" in row["flags"] for row in rows),
        "duplicate_seo_titles": duplicate_groups(title_slugs),
        "duplicate_seo_descriptions": duplicate_groups(description_slugs),
        "duplicate_slugs": duplicate_slugs,
        "word_count_median_approx": p50,
        "limitations": [
            "WXR may not contain live theme_mod/customizer values or data stored outside the export.",
            "Absence of a SEO meta override is not necessarily an error; the theme or SEO plugin may generate a fallback.",
            "The report flags structural patterns only; it does not judge source authority, factual truth, usefulness, or Google ranking.",
            "The source count is URL syntax in sa_sources, not a quality assessment of those sources.",
        ],
    }
    return {"summary": summary, "items": rows}


def write_csv(report: dict[str, object], destination: Path) -> None:
    columns = [
        "post_id",
        "post_type",
        "status",
        "slug",
        "url",
        "word_count",
        "h1_count",
        "h2_count",
        "faq_count",
        "source_count",
        "internal_links",
        "external_links",
        "external_domains",
        "image_count",
        "images_missing_alt",
        "empty_anchors",
        "editorial_residue",
        "flags",
    ]
    with destination.open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=columns, extrasaction="ignore")
        writer.writeheader()
        for row in report["items"]:
            output = dict(row)
            output["flags"] = ";".join(output.get("flags", []))
            writer.writerow(output)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("wxr", type=Path, help="Path to a WordPress WXR/XML export")
    parser.add_argument("--site-url", default="https://sarzaminaryan.ir", help="Canonical site URL used to classify internal links")
    parser.add_argument("--json", type=Path, dest="json_path", help="Write the full summary and per-item report to this path")
    parser.add_argument("--csv", type=Path, help="Write a spreadsheet-friendly per-item report to this path")
    args = parser.parse_args(argv)
    if not args.wxr.is_file():
        parser.error(f"WXR file does not exist: {args.wxr}")
    report = audit_wxr(args.wxr, args.site_url)
    output = json.dumps(report, ensure_ascii=False, indent=2) + "\n"
    if args.json_path:
        args.json_path.write_text(output, encoding="utf-8")
    if args.csv:
        write_csv(report, args.csv)
    if not args.json_path:
        print(json.dumps(report["summary"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
