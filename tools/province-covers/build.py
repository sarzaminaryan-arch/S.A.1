#!/usr/bin/env python3
"""Build the installable first-party plugin with external, checksum-verified images.

Usage: build.py OPTIMIZED_ASSETS_DIRECTORY OUTPUT.zip
Do NOT use GitHub's source-code ZIP as the installable package.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import re
import zipfile
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
PLUGIN = ROOT / 'wp-content/plugins/sa-province-covers'
FILES = (
    'sa-province-covers.php', 'readme.txt', 'data/provinces.json',
    'includes/catalog.php', 'includes/class-sa-pc-store.php',
    'includes/class-sa-pc-public.php', 'includes/class-sa-pc-admin.php',
    'assets/public.css', 'assets/admin.css', 'assets/admin.js',
)


def build(assets: Path, output: Path) -> str:
    rows = json.loads((PLUGIN / 'data/provinces.json').read_text(encoding='utf-8'))
    manifest = json.loads((assets / 'manifest.json').read_text(encoding='utf-8'))
    expected = {row['slug'] for row in rows}
    if len(rows) != 31 or len(expected) != 31 or expected != set(manifest):
        raise ValueError('Expected exactly the canonical 31-province assets')
    for row in rows:
        item = manifest[row['slug']]
        name = row['file']
        if name != Path(name).name or not re.fullmatch(r'[a-z-]+\.webp', name) or item['file'] != name:
            raise ValueError('Unsafe or mismatched asset filename')
        file = assets / name
        if hashlib.sha256(file.read_bytes()).hexdigest() != item['sha256'] or file.stat().st_size != item['bytes']:
            raise ValueError('Image checksum/size mismatch: ' + name)
        with Image.open(file) as image:
            if image.format != 'WEBP' or image.size != (item['width'], item['height']):
                raise ValueError('Image MIME/dimension mismatch: ' + name)
            image.verify()
    version = re.search(r'^ \* Version: (\S+)', (PLUGIN / 'sa-province-covers.php').read_text(), re.M).group(1)
    if f'Stable tag: {version}' not in (PLUGIN / 'readme.txt').read_text():
        raise ValueError('Plugin version mismatch')
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in FILES:
            archive.write(PLUGIN / name, 'sa-province-covers/' + name)
        archive.write(assets / 'manifest.json', 'sa-province-covers/assets/images/manifest.json')
        for row in rows:
            archive.write(assets / row['file'], 'sa-province-covers/assets/images/' + row['file'])
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None or len(archive.namelist()) != len(FILES) + 32:
            raise ValueError('Archive failed verification')
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix(output.suffix + '.sha256').write_text(digest + '  ' + output.name + '\n', encoding='ascii')
    return digest


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('assets', type=Path)
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    print(build(args.assets, args.output))
    print(f'{args.output}: {args.output.stat().st_size} bytes')
