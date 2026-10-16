#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Builder for the ready-article attraction records of the dashboard's featured sites."""

import importlib.util
import os
import re
import sys

THEME = "/home/user/S.A.1/wp-content/themes/sarzaminaryan-child"
PHP = os.path.join(THEME, "data/ready-articles.php")
VERIFIED = "2026-10-16"

GENERIC_SOURCES = [
    "ویزیت ایران، جاذبه‌های گردشگری ایران | https://www.visitiran.ir/",
    "وزارت میراث‌فرهنگی، گردشگری و صنایع‌دستی | https://www.mcth.ir/",
]


def q(value):
    """PHP single-quoted string."""
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def arr_map(items, indent):
    pad = "\t" * indent
    rows = []
    for k, v in items.items():
        rows.append(pad + "\t\t" + q(k) + " => " + q(v) + ",")
    return "array(\n" + "\n".join(rows) + "\n" + pad + "\t)"


def sources_block(lines, indent):
    pad = "\t" * indent
    inner = "\n".join(pad + "\t\t\t" + q(line) + "," for line in lines)
    return "implode(\n" + pad + "\t\t\t\"\\n\",\n" + pad + "\t\t\tarray(\n" + inner + "\n" + pad + "\t\t\t)\n" + pad + "\t\t)"


def record_php(spec):
    """Render one full PHP record."""
    slug = spec["slug"]
    meta = dict(spec["meta"])
    meta.setdefault("sa_last_verified_date", VERIFIED)
    meta.setdefault("sa_facts_checked", "")
    meta["sa_seo_title"] = spec["meta"]["sa_seo_title"]
    meta.setdefault("rank_math_title", spec["meta"]["sa_seo_title"])
    meta.setdefault("rank_math_description", spec["meta"]["sa_seo_description"])
    meta.setdefault("rank_math_focus_keyword", spec["meta"]["sa_focus_keyword"])

    out = []
    out.append("\t'" + slug + "' => array(")
    out.append("\t\t'title'         => " + q(spec["title"]) + ",")
    out.append("\t\t'type'          => 'attraction',")
    out.append("\t\t'slug'          => " + q(slug) + ",")
    out.append("\t\t'short_fa'      => " + q(spec["short_fa"]) + ",")
    out.append("\t\t'short_en'      => " + q(spec["short_en"]) + ",")
    out.append("\t\t'city_en'       => " + q(spec["city_en"]) + ",")
    out.append("\t\t'province_en'   => " + q(spec["province_en"]) + ",")
    out.append("\t\t'remove_featured_image' => true,")
    out.append("\t\t'excerpt'       => " + q(spec["excerpt"]) + ",")
    out.append("\t\t'english'       => " + q(spec["english"]) + ",")
    out.append("\t\t'kind'          => " + q(spec["kind"]) + ",")
    out.append("\t\t'province'      => " + q(spec["province"]) + ",")
    out.append("\t\t'province_name' => " + q(spec["province_name"]) + ",")
    out.append("\t\t'city'          => " + q(spec["city"]) + ",")
    out.append("\t\t'city_name'     => " + q(spec["city_name"]) + ",")
    out.append("\t\t'terms'         => array(")
    for tax, terms in spec["terms"].items():
        out.append("\t\t\t" + q(tax) + " => " + arr_map(terms, 3).replace("array(\n", "array(", 1) + ",")
    out.append("\t\t),")
    out.append("\t\t'meta'          => array(")
    for key, value in meta.items():
        if key == "sa_sources":
            out.append("\t\t\t'sa_sources'         => " + sources_block(value, 3) + ",")
        else:
            out.append("\t\t\t" + q(key) + " => " + q(value) + ",")
    out.append("\t\t),")
    out.append("\t\t'faq'           => array(")
    for question, answer in spec["faq"]:
        out.append("\t\t\tarray( 'q' => " + q(question) + ", 'a' => " + q(answer) + " ),")
    out.append("\t\t),")
    out.append("\t\t'gallery'      => array(")
    out.append("\t\t\t'drone' => array(")
    out.append("\t\t\t\t'theme_img'    => " + q("%s/01-drone-view.webp" % slug) + ",")
    out.append("\t\t\t\t'caption'      => " + q(spec["gallery_caption"]) + ",")
    out.append("\t\t\t\t'alt'          => " + q(spec["gallery_alt"]) + ",")
    out.append("\t\t\t\t'credit'       => 'سرزمین آریان — تصویر ساخته‌شده با هوش مصنوعی',")
    out.append("\t\t\t\t'edit_note'    => 'نمای هوایی بازسازی‌شده با هوش مصنوعی؛ کادر ۱۶:۹، تنظیم رنگ زنده و نشان سرزمین آریان.',")
    out.append("\t\t\t),")
    out.append("\t\t),")
    out.append("\t\t'content'       => <<<'SA_ART'")
    out.append(spec["content"].strip("\n"))
    out.append("SA_ART,")
    out.append("\t),")
    out.append("")
    return "\n".join(out)


def sanitize_links(content, all_keys):
    """Links to attractions without a record yet collapse to plain text."""

    def repl(match):
        return match.group(0) if match.group(2) in all_keys else match.group(3)

    return re.sub(r'<a href="(/attraction/([^"#?]+)/)">([^<]*)</a>', repl, content)


def validate(records, all_keys):
    for spec in records:
        slug = spec["slug"]
        assert spec["content"].strip(), slug
        assert not re.search(r"^SA_ART\s*$", spec["content"], re.M), slug
        assert spec["content"].count("<h2>") >= 4, slug + ": needs several h2 sections"
        assert spec["content"].count("{{GALLERY:drone}}") == 1, slug + ": exactly one gallery placeholder"
        for href in re.findall(r'href="(/attraction/[^"#?]+)/"', spec["content"]):
            target = href.strip("/").split("/", 1)[1]
            assert target in all_keys, "%s: unknown attraction link %s" % (slug, href)
        meta = spec["meta"]
        for key in ("sa_english_name", "sa_latitude", "sa_longitude", "sa_seo_title", "sa_seo_description", "sa_focus_keyword", "sa_sources"):
            assert meta.get(key), "%s: meta %s missing" % (slug, key)
        assert len(spec["faq"]) >= 5, slug + ": needs at least 5 FAQ rows"
        assert os.path.isfile(os.path.join(THEME, "assets/img/articles", slug, "01-drone-view.webp")), slug + ": drone image missing"


def append_records(records):
    with open(PHP, encoding="utf-8") as handle:
        text = handle.read()
    existing = set(re.findall(r"^\t'([a-z0-9-]+)' => array\(", text, re.M))
    all_keys = existing | {r["slug"] for r in records}
    for spec in records:
        spec["content"] = sanitize_links(spec["content"], all_keys)
    validate(records, all_keys)
    duplicates = existing & {r["slug"] for r in records}
    assert not duplicates, "already present: %s" % duplicates

    block = "\n".join(record_php(r) for r in records)
    marker = text.rstrip()
    assert marker.endswith(");"), "unexpected end of file"
    head = marker[: -len(");")].rstrip("\n")
    new_text = head + "\n\n\t// —— جاذبه‌های شاخص داشبورد: %s\n" % ", ".join(r["slug"] for r in records) + block + ");\n"
    with open(PHP, "w", encoding="utf-8") as handle:
        handle.write(new_text)
    return len(records), sorted(existing)


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


if __name__ == "__main__":
    batch = load_module(sys.argv[1], "batchdata")
    records = batch.ARTICLES
    count, existing = append_records(records)
    print("appended %d records; file previously had %d" % (count, len(existing)))
