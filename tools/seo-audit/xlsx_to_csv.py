#!/usr/bin/env python3
"""Minimal XLSX -> CSV reader (no external deps).

Used by the SEO/sitemap audit to read the Google Search Console coverage
exports the owner uploads to Google Drive. An .xlsx is a zip of XML parts;
we only need the shared-string table and the sheet cells.

Usage:
    python3 tools/seo-audit/xlsx_to_csv.py input.xlsx out_dir/
Writes one CSV per worksheet: out_dir/<input-stem>__<sheet>.csv
"""

import csv
import os
import re
import sys
import zipfile
import xml.etree.ElementTree as ET

NS = "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}"
REL_NS = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
PKG_REL_NS = "{http://schemas.openxmlformats.org/package/2006/relationships}"


def col_to_index(ref):
    """'BC12' -> 54 (0-based column index)."""
    letters = re.match(r"([A-Z]+)", ref).group(1)
    idx = 0
    for ch in letters:
        idx = idx * 26 + (ord(ch) - 64)
    return idx - 1


def shared_strings(zf):
    if "xl/sharedStrings.xml" not in zf.namelist():
        return []
    root = ET.fromstring(zf.read("xl/sharedStrings.xml"))
    out = []
    for si in root.findall(f"{NS}si"):
        out.append("".join(t.text or "" for t in si.iter(f"{NS}t")))
    return out


def sheet_targets(zf):
    """[(sheet_name, xml_path)] in workbook order."""
    wb = ET.fromstring(zf.read("xl/workbook.xml"))
    rels = ET.fromstring(zf.read("xl/_rels/workbook.xml.rels"))
    rid_to_target = {r.get("Id"): r.get("Target") for r in rels.findall(f"{PKG_REL_NS}Relationship")}
    sheets = []
    for sh in wb.find(f"{NS}sheets").findall(f"{NS}sheet"):
        target = rid_to_target[sh.get(f"{REL_NS}id")]
        if not target.startswith("/"):
            target = "xl/" + target.lstrip("./")
        else:
            target = target.lstrip("/")
        sheets.append((sh.get("name"), target))
    return sheets


def read_sheet(zf, path, strings):
    root = ET.fromstring(zf.read(path))
    rows = []
    for row in root.iter(f"{NS}row"):
        cells = {}
        for c in row.findall(f"{NS}c"):
            ref = c.get("r") or ""
            ctype = c.get("t")
            v = c.find(f"{NS}v")
            is_node = c.find(f"{NS}is")
            if ctype == "s" and v is not None:
                value = strings[int(v.text)]
            elif ctype == "inlineStr" and is_node is not None:
                value = "".join(t.text or "" for t in is_node.iter(f"{NS}t"))
            elif v is not None:
                value = v.text or ""
            else:
                value = ""
            cells[col_to_index(ref)] = value
        if cells:
            width = max(cells) + 1
            rows.append([cells.get(i, "") for i in range(width)])
    return rows


def convert(src, out_dir):
    os.makedirs(out_dir, exist_ok=True)
    stem = os.path.splitext(os.path.basename(src))[0]
    written = []
    with zipfile.ZipFile(src) as zf:
        strings = shared_strings(zf)
        for name, path in sheet_targets(zf):
            rows = read_sheet(zf, path, strings)
            safe = re.sub(r"[^A-Za-z0-9._-]+", "_", name)
            dst = os.path.join(out_dir, f"{stem}__{safe}.csv")
            with open(dst, "w", newline="", encoding="utf-8") as fh:
                csv.writer(fh).writerows(rows)
            written.append((dst, len(rows)))
    return written


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(1)
    for dest, count in convert(sys.argv[1], sys.argv[2]):
        print(f"{dest}\t{count} rows")
