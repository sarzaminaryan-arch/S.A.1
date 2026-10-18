"""تست‌های رَردیابِ اسکرین‌شات گوگل‌مپ.

ابزار روی تصویرِ گوگل کار می‌کند، پس تست‌ها یک «اسکرین‌شات مصنوعی» می‌سازند
(زمین بژ، جادهٔ آبی‌خاکستری، پارک سبز، آب آبی و مرزِ نقطه‌چین قرمز) و می‌پرسند
که آیا همان چیزی بیرون می‌آید که باید: شبکهٔ جاده، پهنه‌ها، مرز، شمال‌بالا بودن
بردار و رندرِ هر دو مدل.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

import numpy as np
import pytest
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools' / 'maps'))

import trace_maps_screenshots as tracer  # noqa: E402


W, H = 1080, 1440
LAND = (240, 240, 224)
CITY = (240, 240, 240)


def _fake_screenshot(path: Path, city_rows=slice(700, 1400)) -> Path:
    """اسکرین‌شاتی شبیه سبک روشن گوگل‌مپ می‌سازد."""
    img = np.zeros((H + 400, W, 3), np.uint8)
    img[...] = LAND
    img[city_rows.start + 400:city_rows.stop + 400] = CITY
    # پارک
    img[900:1000, 700:800] = (168, 218, 168)
    # دریاچه
    img[1250:1330, 900:1000] = (170, 200, 230)
    # شبکهٔ خیابان: پهنا از ۲ تا ۷ پیکسل
    for i in range(6):
        y = city_rows.start + 60 + i * 90
        img[y:y + 3 + i, 40:1040] = (120, 137, 144)
    for i in range(5):
        x = 60 + i * 210
        img[city_rows.start + 400:city_rows.stop + 400, x:x + 3 + i] = (120, 137, 144)
    # بزرگراه پهن
    img[city_rows.start + 300:city_rows.start + 310, 0:W] = (90, 108, 118)
    # مرزِ نقطه‌چین قرمز
    for y in range(600, 1400, 22):
        img[y:y + 10, 40:48] = (208, 48, 40)
    Image.fromarray(img).save(path)
    return path


@pytest.fixture(scope='module')
def screenshot(tmp_path_factory):
    return _fake_screenshot(tmp_path_factory.mktemp('shots') /
                            'Screenshot_2026-10-18-09-00-00-000_test.jpg')


# ------------------------------------------------------------------ مقیاس ----
def test_scale_table_covers_every_supplied_screenshot():
    assert set(tracer.SCALE_BARS) == {
        'Screenshot_2026-10-18-07-22-45-505_com.google.android.apps.maps.jpg',
        'Screenshot_2026-10-18-07-22-52-881_com.google.android.apps.maps.jpg',
        'Screenshot_2026-10-18-07-23-06-015_com.google.android.apps.maps.jpg',
        'Screenshot_2026-10-18-07-23-12-398_com.google.android.apps.maps.jpg',
        'Screenshot_2026-10-18-07-23-16-436_com.google.android.apps.maps.jpg',
        'Screenshot_2026-10-18-07-23-20-220_com.google.android.apps.maps.jpg',
    }
    # نوارِ ۱۰ کیلومتریِ ۲۲۴ پیکسلی و نوارِ ۵ کیلومتریِ ۲۵۰ پیکسلی
    assert tracer.SCALE_BARS[
        'Screenshot_2026-10-18-07-22-45-505_com.google.android.apps.maps.jpg'] \
        == pytest.approx(10000.0 / 224)
    assert tracer.SCALE_BARS[
        'Screenshot_2026-10-18-07-23-06-015_com.google.android.apps.maps.jpg'] \
        == pytest.approx(20.0)
    close = [v for k, v in tracer.SCALE_BARS.items() if '07-23' in k]
    assert len(set(close)) == 1          # چهار نمای نزدیک یک بزرگ‌نمایی‌اند


def test_stamp_extracts_time_of_day():
    name = ('Screenshot_2026-10-18-07-23-20-220_com.google.android.apps.maps.jpg')
    assert tracer._stamp(Path(name)) == '07-23-20'
    assert tracer._stamp(Path('plate.png')) == 'plate'


def test_window_crop_is_kept_in_the_picture():
    assert tracer.WINDOW[0] < tracer.WINDOW[1]
    assert tracer.WINDOW[0] > 0          # نوارِ بالای گوشی بریده می‌شود


# --------------------------------------------------------------- طبقه‌بندی --
def test_classify_splits_the_five_colour_families(screenshot):
    rgb = tracer.load_rgb(str(screenshot))
    assert rgb.shape == (tracer.WINDOW[1] - tracer.WINDOW[0], W, 3)
    layers = tracer.classify(rgb)
    assert set(layers) == {'road', 'water', 'park', 'boundary'}
    for key in layers:
        assert layers[key].dtype == bool
        assert layers[key].shape == rgb.shape[:2]
    assert layers['road'].mean() > 0.01
    assert layers['park'].any() and layers['water'].any()
    assert layers['boundary'].any()


def test_red_boundary_is_never_a_road(screenshot):
    rgb = tracer.load_rgb(str(screenshot))
    layers = tracer.classify(rgb)
    assert not (layers['road'] & layers['boundary']).any()


def test_clean_roads_drops_wide_blobs_but_keeps_the_network(screenshot):
    rgb = tracer.load_rgb(str(screenshot))
    road = tracer.clean_roads(tracer.classify(rgb)['road'])
    assert road.sum() > 1000
    from scipy import ndimage as ndi
    dist = ndi.distance_transform_edt(road)
    assert dist.max() <= 10.0            # دکمه‌های رابط کاربری حذف شده‌اند


def test_urban_mask_ignores_beige_land_and_keeps_neutral_blocks():
    rgb = np.zeros((600, 600, 3), np.uint8)
    rgb[...] = LAND
    rgb[100:500, 100:500] = CITY        # بلوک شهریِ خنثی
    road = np.zeros((600, 600), bool)
    mask = tracer.urban_mask(rgb, road)
    assert mask[300, 300]                # داخل بلوک شهری
    assert not mask[20, 300]             # زمین بژ بیرون شهر
    assert mask.mean() < 0.6


def test_road_classes_follow_the_traced_width():
    assert tracer._road_class(12.0) == 'motorway'
    assert tracer._road_class(6.5) == 'trunk'
    assert tracer._road_class(4.5) == 'primary'
    assert tracer._road_class(3.0) == 'secondary'
    assert tracer._road_class(2.0) == 'street'
    widths = [tracer.ROAD_STYLE[k][0] for k in
              ('street', 'secondary', 'primary', 'trunk', 'motorway')]
    assert widths == sorted(widths)


# ------------------------------------------------------------------ ردیابی --
def test_trace_image_is_north_up_and_in_metres(screenshot):
    traced = tracer.trace_image(str(screenshot), 20.0)
    assert traced['size'] == [W, H]
    assert traced['metre_per_px'] == 20.0
    ys = [p[1] for r in traced['roads'] for p in r['points']]
    xs = [p[0] for r in traced['roads'] for p in r['points']]
    assert ys                                   # شبکه پیدا شد
    assert min(ys) > -H * 20.0 and max(ys) < 0.0  # شمال بالا: y رو به بالا
    assert 0.0 <= min(xs) and max(xs) <= W * 20.0
    assert len(traced['parks']) >= 1 and len(traced['water']) >= 1
    assert len(traced['urban']) >= 1
    assert traced['boundary']                    # خطِ قرمز پیدا شد


def test_trace_image_is_deterministic(screenshot):
    a = tracer.trace_image(str(screenshot), 20.0)
    b = tracer.trace_image(str(screenshot), 20.0)
    assert json.dumps(a, sort_keys=True) == json.dumps(b, sort_keys=True)


def test_note_discloses_the_screenshot_source(screenshot):
    traced = tracer.trace_image(str(screenshot), 20.0)
    assert 'SCREENSHOT' in traced['note']
    assert 'NOT FOR NAVIGATION' in traced['note']


# -------------------------------------------------------------------- رندر --
@pytest.mark.parametrize('style', ['sand', 'ink'])
def test_render_writes_a_north_up_poster(screenshot, tmp_path, style):
    traced = tracer.trace_image(str(screenshot), 20.0)
    out = tmp_path / ('plate-%s.png' % style)
    tracer.render(traced, style, out, 'Shiraz', 'بازسازی‌شده از گوگل‌مپ',
                  dpi=60, height=6.0)
    assert out.exists() and out.stat().st_size > 8000
    with Image.open(out) as im:
        assert im.width > 200 and im.height > im.width   # قاب عمودی


# --------------------------------------------------------------------- CLI --
def test_cli_renders_both_styles_and_writes_json(screenshot, tmp_path):
    out_dir = tmp_path / 'out'
    code = tracer.main([str(screenshot), '--out-dir', str(out_dir),
                        '--json', str(tmp_path / 'plate.json'), '--dpi', '60',
                        '--metres-per-px', '20'])
    assert code == 0
    assert sorted(p.name for p in out_dir.glob('*.png')) == [
        'traced-shiraz-09-00-00-ink.png', 'traced-shiraz-09-00-00-sand.png']
    data = json.loads((tmp_path / 'plate.json').read_text(encoding='utf-8'))
    assert data['source'].endswith('_test.jpg')
    assert data['roads'] and data['note']


def test_cli_skips_a_screenshot_without_a_scale_bar(screenshot, tmp_path):
    other = tmp_path / 'Screenshot_2026-10-18-11-11-11-111_unknown.jpg'
    other.write_bytes(screenshot.read_bytes())
    assert tracer.main([str(other), '--out-dir', str(tmp_path / 'o'),
                        '--no-render']) == 0
    assert not list((tmp_path / 'o').glob('*.png'))