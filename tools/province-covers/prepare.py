#!/usr/bin/env python3
"""Conservatively trim blank exterior white only; build an external WebP asset pack.

Usage: prepare.py SOURCE_DIRECTORY OUTPUT_DIRECTORY
Requires Pillow and numpy. Never edits originals or commits image binaries.
"""
from __future__ import annotations
import argparse
import hashlib
import io
import json
from pathlib import Path
import numpy as np
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[2]
CATALOG = ROOT / 'wp-content/plugins/sa-province-covers/data/provinces.json'


def exterior_box(image: Image.Image) -> tuple[int, int, int, int]:
    """Retain ALL pixels below RGB 245, plus a 2px safety margin.

    Only entirely blank outer rows/columns can go. Interior slots, cream paper,
    distressed frame and rounded corners are NOT flood-filled or trimmed away.
    """
    rgb = np.asarray(image.convert('RGB'))
    mask = np.any(rgb < 245, axis=2)
    ys, xs = np.nonzero(mask)
    w, h = image.size
    if not len(xs):
        return 0, 0, w, h
    box = max(0, int(xs.min()) - 2), max(0, int(ys.min()) - 2), min(w, int(xs.max()) + 3), min(h, int(ys.max()) + 3)
    # Refuse suspiciously large trims instead of cutting an intentional design.
    if (box[2] - box[0]) < w * .75 or (box[3] - box[1]) < h * .75:
        return 0, 0, w, h
    return box


def luminance_mean(image: Image.Image) -> float:
    rgb = np.asarray(image.convert('RGB'), dtype=np.float32)
    return float(np.mean(rgb @ np.array([.2126, .7152, .0722], dtype=np.float32)))


def lift_shadows(image: Image.Image, gamma: float = .78) -> Image.Image:
    """Lift dark values without washing white lettering or changing RGB hue ratios.

    A value-channel gamma curve fixes black/white endpoints. RGB channels share
    the same gain, so existing saturated highlights do not clip to white.
    """
    if not .65 <= gamma <= 1:
        raise ValueError('Brightness gamma must be between .65 and 1')
    rgb = np.asarray(image.convert('RGB'), dtype=np.float32) / 255
    value = rgb.max(axis=2, keepdims=True)
    gain = np.divide(np.power(value, gamma), value, out=np.ones_like(value), where=value > 0)
    return Image.fromarray(np.clip(np.rint(rgb * gain * 255), 0, 255).astype(np.uint8))


def prepare(source: Path, output: Path, catalog: Path = CATALOG, gamma: float = .78) -> dict:
    if not .65 <= gamma <= 1:
        raise ValueError('Brightness gamma must be between .65 and 1')
    output.mkdir(parents=True, exist_ok=True)
    entries = json.loads(catalog.read_text(encoding='utf-8'))
    if len(entries) != 31 or len({e['slug'] for e in entries}) != 31:
        raise ValueError('Expected the exact 31-province catalog')
    manifest = {}
    for entry in entries:
        src = source / entry['source']
        raw = src.read_bytes()
        with Image.open(io.BytesIO(raw)) as decoded:
            if decoded.format != 'WEBP':
                raise ValueError(f'Not WebP: {src}')
            image = ImageOps.exif_transpose(decoded).convert('RGB')
        before = image.size
        box = exterior_box(image)
        candidate = image.crop(box)
        candidate.thumbnail((1400, 1200), Image.Resampling.LANCZOS)
        mean_before = luminance_mean(candidate)
        applied_gamma = gamma if mean_before < 115 else 1.0
        if applied_gamma < 1:
            candidate = lift_shadows(candidate, applied_gamma)
        mean_after = luminance_mean(candidate)
        encoded = io.BytesIO()
        candidate.save(encoded, format='WEBP', quality=80, method=6)
        optimized = encoded.getvalue()
        # Never silently discard a requested brightness correction for byte savings.
        # For already-bright images, retain the original if re-encoding grows it.
        if applied_gamma == 1 and len(optimized) >= len(raw):
            optimized = raw
            box = (0, 0, before[0], before[1])
            candidate = image
            mean_before = mean_after = luminance_mean(image)
        target = output / entry['file']
        target.write_bytes(optimized)
        manifest[entry['slug']] = {
            'file': entry['file'], 'sha256': hashlib.sha256(optimized).hexdigest(),
            'bytes': len(optimized), 'width': candidate.width, 'height': candidate.height,
            'source_bytes': len(raw), 'source_sha256': hashlib.sha256(raw).hexdigest(),
            'source_width': before[0], 'source_height': before[1], 'crop_box': list(box),
            'brightness_gamma': applied_gamma, 'luminance_mean_before': round(mean_before, 2),
            'luminance_mean_after': round(mean_after, 2),
        }
    (output / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return manifest


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('output', type=Path)
    parser.add_argument('--brightness-gamma', type=float, default=.78, help='Shadow lifting for dark images; 1 disables it')
    args = parser.parse_args()
    report = prepare(args.source, args.output, gamma=args.brightness_gamma)
    old = sum(v['source_bytes'] for v in report.values())
    new = sum(v['bytes'] for v in report.values())
    print(json.dumps({'files': len(report), 'source_bytes': old, 'optimized_bytes': new,
                      'saving_percent': round((1 - new / old) * 100, 2),
                      'brightened': sum(v['brightness_gamma'] < 1 for v in report.values()),
                      'cropped': sum(v['crop_box'] != [0, 0, v['source_width'], v['source_height']] for v in report.values())}, indent=2))
