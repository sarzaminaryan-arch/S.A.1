#!/usr/bin/env python3
"""Regenerate data-model.json from data-model.yaml and run basic integrity checks.

Usage:  python3 build_json.py          (run from any directory)
Requires: pyyaml  (pip install pyyaml)
"""
import json
import pathlib
import sys

import yaml

HERE = pathlib.Path(__file__).resolve().parent
SRC = HERE / "data-model.yaml"
DST = HERE / "data-model.json"


def check(data: dict) -> None:
    ents = data["entities"]
    taxes = data["taxonomies"]
    for key, ent in ents.items():
        names = [f["name"] for f in ent["fields"]]
        assert ent["primary_key"] in names, f"{key}: primary_key not in fields"
        assert ent["primary_taxonomy"] in taxes, f"{key}: unknown primary_taxonomy"
        assert data["urls"]["entities"][key]["pattern"] == ent["url_pattern"], f"{key}: url mismatch"
        for rel in ent["relations"]:
            assert rel["target"] in ents, f"{key}: relation to unknown entity {rel['target']}"
        for fld in ent["fields"]:
            if fld.get("taxonomy"):
                assert fld["taxonomy"] in taxes, f"{key}.{fld['name']}: unknown taxonomy"


def main() -> int:
    data = yaml.safe_load(SRC.read_text(encoding="utf-8"))
    check(data)
    DST.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"OK  v{data['model']['version']}  entities={len(data['entities'])}  -> {DST.name}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
