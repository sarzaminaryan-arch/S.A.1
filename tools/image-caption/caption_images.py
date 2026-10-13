#!/usr/bin/env python3
"""Burn an English caption and the Sarzamin Aryan watermark onto images.

Designed for the owner's 1672x941 (16:9) WebP illustrations, but works with any size.

    python3 tools/image-caption/caption_images.py \
        --input  wp-content/themes/sarzaminaryan-child/assets/img/guides-inbox \
        --output wp-content/themes/sarzaminaryan-child/assets/img/guides-en \
        --captions tools/image-caption/captions.json

`captions.json` maps file names to English captions:

    { "1000102940.webp": "Emergency raincoat made from a trash bag" }

Files without an entry are only watermarked (no caption bar drawn), so the
watermark pass can run before the topics are known.

Styles:
  bar      dark navy gradient bar across the bottom + watermark top-right
  inline   same bar, watermark inside the bar at its right end
  chip     small rounded label bottom-left + watermark bottom-right

The watermark colour flips between the navy brand colour and white depending on
the brightness of the corner it sits on, so it stays visible on light artwork.
"""

import argparse
import json
import os
import sys

from PIL import Image, ImageDraw, ImageFont

# Brand palette (see assets/css/child.css)
NAVY = (7, 26, 48)        # --sa-ink
NAVY_SOFT = (11, 42, 74)  # --sa-profile-navy
GOLD = (201, 150, 44)     # --sa-id-gold, lightened for dark backgrounds
WHITE = (255, 255, 255)
SITE_URL = "sarzaminaryan.ir"

FALLBACK_FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FALLBACK_REGULAR = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"


def font(path_dir, name, size, regular=False):
    path = os.path.join(path_dir, name) if path_dir else ""
    if path and os.path.exists(path):
        return ImageFont.truetype(path, size)
    fallback = FALLBACK_REGULAR if regular else FALLBACK_FONT
    if os.path.exists(fallback):
        return ImageFont.truetype(fallback, size)
    return ImageFont.load_default()


def region_luminance(img, box):
    """Average luminance of a region, used to pick a readable watermark colour."""
    w, h = img.size
    x0, y0, x1, y1 = [max(0, int(v)) for v in box]
    x1, y1 = min(w, x1), min(h, y1)
    if x1 <= x0 or y1 <= y0:
        return 255
    crop = img.convert("RGB").crop((x0, y0, x1, y1)).resize((32, 32))
    pixels = list(crop.getdata())
    return sum(0.299 * r + 0.587 * g + 0.114 * b for r, g, b in pixels) / len(pixels)


def fit_text(draw, text, font_path, start_size, max_width, bold=True, regular=False):
    """Shrink the font until the line fits `max_width`."""
    size = start_size
    while size > 18:
        f = font(font_path, "Inter-SemiBold.ttf" if bold else "Inter-Regular.ttf", size, regular)
        if draw.textbbox((0, 0), text, font=f)[2] <= max_width:
            return f, size
        size -= 2
    return font(font_path, "Inter-SemiBold.ttf" if bold else "Inter-Regular.ttf", 18, regular), 18


def draw_caption_bar(img, caption, font_dir, style):
    w, h = img.size
    bar_h = max(96, int(h * 0.155))
    top = h - bar_h

    overlay = Image.new("RGBA", (w, bar_h), (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    for y in range(bar_h):
        # ease-in ramp: transparent at the top, almost opaque at the bottom
        alpha = int(238 * (y / max(1, bar_h - 1)) ** 1.7)
        d.line([(0, y), (w, y)], fill=(NAVY[0], NAVY[1], NAVY[2], alpha))
    img.alpha_coordless_paste = None
    img.paste(overlay, (0, top), overlay)

    d = ImageDraw.Draw(img)
    accent = max(3, int(h * 0.005))
    d.rectangle([0, top - accent, w, top], fill=GOLD)

    margin = int(w * 0.035)
    reserve = int(w * 0.34) if style == "inline" else 0
    d_probe = ImageDraw.Draw(img)
    f, size = fit_text(d_probe, caption, font_dir, int(h * 0.052), w - 2 * margin - reserve)
    y = top + (bar_h - size) // 2 - int(size * 0.06)
    d.text((margin, y), caption, font=f, fill=WHITE)
    return top


def draw_chip(img, caption, font_dir):
    w, h = img.size
    d = ImageDraw.Draw(img)
    margin = int(w * 0.03)
    f, size = fit_text(d, caption, font_dir, int(h * 0.042), int(w * 0.62))
    tw = d.textbbox((0, 0), caption, font=f)[2]
    pad_x, pad_y = int(size * 0.6), int(size * 0.45)
    box = [margin, h - margin - size - 2 * pad_y, margin + tw + 2 * pad_x, h - margin]
    layer = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ImageDraw.Draw(layer).rounded_rectangle(box, radius=int(size * 0.55),
                                            fill=(NAVY[0], NAVY[1], NAVY[2], 215))
    img.paste(Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB"), (0, 0))
    d = ImageDraw.Draw(img)
    d.text((margin + pad_x, box[1] + pad_y), caption, font=f, fill=WHITE)
    d.rectangle([box[0], box[1], box[0] + max(3, int(h * 0.004)), box[3]], fill=GOLD)


def draw_watermark(img, font_dir, logo_path, position="top-right"):
    w, h = img.size
    mark_h = max(28, int(h * 0.055))
    url_size = max(16, int(h * 0.030))

    logo = None
    if logo_path and os.path.exists(logo_path):
        logo = Image.open(logo_path).convert("RGBA")
        ratio = logo.width / logo.height
        logo = logo.resize((int(mark_h * ratio), mark_h), Image.LANCZOS)

    f_url = font(font_dir, "Inter-SemiBold.ttf", url_size)
    probe = ImageDraw.Draw(img)
    tw = probe.textbbox((0, 0), SITE_URL, font=f_url)[2]
    gap = int(mark_h * 0.35)
    block_w = (logo.width + gap if logo else 0) + tw

    margin = int(w * 0.028)
    if position == "top-right":
        x, y = w - margin - block_w, margin
    else:  # bottom-right
        x, y = w - margin - block_w, h - margin - mark_h

    lum = region_luminance(img, (x - margin // 2, y - margin // 2,
                                 x + block_w + margin // 2, y + mark_h + margin // 2))
    if lum > 150:  # light artwork -> navy mark
        colour, alpha, shadow = NAVY_SOFT, 150, None
    else:          # dark artwork -> white mark with a soft shadow
        colour, alpha, shadow = WHITE, 205, (0, 0, 0, 140)

    layer = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    cur = x
    if logo:
        tinted = Image.new("RGBA", logo.size, colour + (0,))
        tinted.putalpha(logo.getchannel("A").point(lambda v: int(v * alpha / 255)))
        layer.paste(tinted, (cur, y + (mark_h - logo.height) // 2), tinted)
        cur += logo.width + gap
    if shadow:
        ld.text((cur + 1, y + (mark_h - url_size) // 2 + 1), SITE_URL, font=f_url,
                fill=shadow)
    ld.text((cur, y + (mark_h - url_size) // 2), SITE_URL, font=f_url,
            fill=colour + (alpha,))
    img.paste(Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB"), (0, 0))


def process(src, dst, caption, font_dir, logo_path, style, quality=92):
    img = Image.open(src).convert("RGB")
    if caption:
        if style == "chip":
            draw_chip(img, caption, font_dir)
        else:
            draw_caption_bar(img, caption, font_dir, style)
        draw_watermark(img, font_dir, logo_path, "bottom-right" if style == "chip" else "top-right")
    else:
        draw_watermark(img, font_dir, logo_path, "top-right")
    os.makedirs(os.path.dirname(dst) or ".", exist_ok=True)
    img.save(dst, "WEBP", quality=quality, method=6)
    return dst


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    theme = os.path.join(here, "..", "..", "wp-content", "themes", "sarzaminaryan-child")
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--input", required=True)
    ap.add_argument("--output", required=True)
    ap.add_argument("--captions", default=os.path.join(here, "captions.json"))
    ap.add_argument("--style", default="bar", choices=["bar", "inline", "chip"])
    ap.add_argument("--font-dir", default="/tmp/inter")
    ap.add_argument("--logo", default=os.path.join(theme, "assets", "img", "logo.webp"))
    ap.add_argument("--quality", type=int, default=92)
    args = ap.parse_args()

    captions = {}
    if os.path.exists(args.captions):
        with open(args.captions, encoding="utf-8") as fh:
            captions = json.load(fh)

    files = sorted(f for f in os.listdir(args.input)
                   if f.lower().endswith((".webp", ".jpg", ".jpeg", ".png")))
    if not files:
        sys.exit("no images in " + args.input)

    done = 0
    for name in files:
        caption = captions.get(name, "").strip()
        process(os.path.join(args.input, name), os.path.join(args.output, name),
                caption, args.font_dir, args.logo, args.style, args.quality)
        done += 1
        print(("+ caption" if caption else "- watermark only").ljust(16), name, file=sys.stderr)
    print(f"{done} images -> {args.output}")


if __name__ == "__main__":
    main()
