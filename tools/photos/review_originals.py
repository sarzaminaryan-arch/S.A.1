#!/usr/bin/env python3
"""Make an ephemeral, labelled visual proof before an editor approves an import.

No WP access, originals or derivatives committed to Git. Inspect sheets in the
workflow artifact alongside credits.json. This verifies the file, not its subject:
compare it to the Commons description, location/category and the proposed caption.
"""
import hashlib
import io
import json
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from urllib.parse import urlsplit

from import_photos import check

ROOT = Path(__file__).resolve().parent
LIMIT = 3 * 1024 * 1024  # same limit as sa_agent_rest_photo_import


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


def download(url):
    parsed = urlsplit(url)
    if (parsed.scheme != 'https' or parsed.netloc != 'upload.wikimedia.org'
            or parsed.query or parsed.fragment
            or not re.fullmatch(r'/wikipedia/commons/[0-9a-f]/[0-9a-f]{2}/[^/]+\.jpe?g', parsed.path, re.I)):
        raise ValueError('not a Commons original JPEG URL')
    req = urllib.request.Request(url, headers={'User-Agent': 'Sarzaminaryan-photo-review/1.0 (https://sarzaminaryan.ir; editorial review)'})
    with urllib.request.build_opener(NoRedirect()).open(req, timeout=25) as resp:
        if resp.status != 200:
            raise ValueError(f'status {resp.status}')
        data = resp.read(LIMIT + 1)
    if len(data) > LIMIT or not data.endswith(b'\xff\xd9'):
        raise ValueError('oversized or incomplete JPEG')
    return data


def main(batch, out):
    from PIL import Image, ImageDraw, ImageOps

    path = Path(batch).resolve()
    if path.parent != ROOT or path.suffix != '.json':
        raise SystemExit('select a batch directly under tools/photos')
    rows = json.loads(path.read_text())['photos']
    if not 0 < len(rows) <= 100:
        raise SystemExit('expected 1–100 records')
    out.mkdir(parents=True, exist_ok=True)
    report, bad = [], False
    for offset in range(0, len(rows), 6):
        sheet = Image.new('RGB', (960, 478), 'white')
        pen = ImageDraw.Draw(sheet)
        for index, row in enumerate(rows[offset:offset + 6]):
            x, y = (index % 3) * 320, (index // 3) * 239
            entry = {k: row[k] for k in ('city_id', 'place', 'url', 'author', 'license', 'license_url', 'source_page')}
            entry['number'] = offset + index + 1
            label = f"{entry['number']:02}  city {row['city_id']}  Commons {row.get('commons_page_id', '')}"
            pen.text((x + 5, y + 214), label, fill='black')
            try:
                if check(row):
                    raise ValueError('; '.join(check(row)))
                data = download(row['url'])
                if row.get('original_bytes', len(data)) != len(data):
                    raise ValueError('size differs from Commons imageinfo')
                with Image.open(io.BytesIO(data)) as image:
                    if image.format != 'JPEG' or image.width * image.height > 50_000_000:
                        raise ValueError('unexpected format or dimensions')
                    image.load()
                    entry.update(sha256=hashlib.sha256(data).hexdigest(), bytes=len(data), size=image.size)
                    thumb = ImageOps.contain(ImageOps.exif_transpose(image), (312, 208))
                    sheet.paste(thumb, (x + (320 - thumb.width) // 2, y + (212 - thumb.height) // 2))
            except (OSError, ValueError, urllib.error.URLError) as exc:
                entry['error'] = str(exc)
                print(f'{label}: {exc}', file=sys.stderr)
                bad = True
            report.append(entry)
            time.sleep(1)
        sheet.save(out / f'proof-{offset // 6 + 1}.jpg', quality=90)
    (out / 'credits.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    return int(bad)


if __name__ == '__main__':
    sys.exit(main(sys.argv[1], Path(sys.argv[2])))
