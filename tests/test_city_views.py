"""تست‌های نمای شهری گرافیکی: هندسهٔ تولیدی، قطعی‌بودن بذر و رندر هر دو مدل.

این ابزار عمداً **دادهٔ ساختگیِ صریح** می‌سازد (شبکهٔ خیابان ترسیمی است، نه OSM)؛
پس تست‌ها هم روی «شکل درست و قطعی» تمرکز دارند، نه روی دقت جغرافیایی.
"""

from __future__ import annotations

import math
import random
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools' / 'maps'))

import make_city_views as views  # noqa: E402


# ------------------------------------------------------------------ شهرها ---
def test_every_city_has_plausible_geometry_and_metadata():
    for slug, city in views.CITIES.items():
        assert city['province'] in {p['slug'] for p in views.core.load_provinces()}
        assert 25.0 < city['lat'] < 40.0 and 44.0 < city['lon'] < 62.0
        assert city['span'][0] > 5000 and city['span'][1] > 5000
        assert city['layout'] in ('grid', 'valley')
        assert city['fa'] and city['en']
        assert city['seed'] > 0


def test_requested_cities_are_present():
    assert {'shiraz', 'tabriz', 'dorud', 'hamadan'} <= set(views.CITIES)


def test_palette_extension_covers_both_styles():
    for style in ('sand', 'ink'):
        merged = dict(views.base.STYLES[style])
        merged.update(views.EXTRA_PALETTE[style])
        for key in ('relief', 'relief_shade', 'relief_line', 'county', 'label'):
            assert merged[key].startswith('#')


def test_road_hierarchy_is_monotonic_and_complete():
    widths = [views.ROAD_WIDTH[key] for key in
              ('motorway', 'trunk', 'primary', 'secondary', 'street', 'service')]
    assert widths == sorted(widths, reverse=True)
    for key in views.CASED:
        assert key in views.ROAD_WIDTH


# ------------------------------------------------------------------ هندسه ----
def test_fractal_keeps_endpoints_and_stays_near_the_segment():
    rng = random.Random(7)
    pts = views._fractal(rng, (0.0, 0.0), (1000.0, 0.0), 120.0, 0.58, 5)
    assert pts[0] == (0.0, 0.0)
    assert pts[-1] == (1000.0, 0.0)
    assert len(pts) == 2 ** 5 + 1
    assert max(abs(p[1]) for p in pts) <= 4 * 120.0


def test_smooth_noise_is_bounded_and_deterministic():
    a = views._smooth_noise(random.Random(3), 40, 25.0, 3)
    b = views._smooth_noise(random.Random(3), 40, 25.0, 3)
    assert a == b
    assert len(a) == 40
    assert max(abs(v) for v in a) <= 25.0 + 1e-9


def test_shrink_pulls_polygon_towards_its_centroid():
    poly = [(0.0, 0.0), (400.0, 0.0), (400.0, 300.0), (0.0, 300.0)]
    small = views._shrink(poly, 0.5)

    def area(points):
        return abs(sum(points[i][0] * points[(i + 1) % 4][1]
                       - points[(i + 1) % 4][0] * points[i][1]
                       for i in range(4))) / 2.0

    assert area(small) == pytest.approx(area(poly) * 0.25, rel=1e-6)
    assert small[0] == (100.0, 75.0)


def test_chord_is_none_outside_the_city_and_bounded_inside():
    rng = random.Random(11)
    radius_at = views._boundary(rng, 4000.0)
    assert views._chord(radius_at, (9000.0, 0.0), (1.0, 0.0), 20000.0) is None
    run = views._chord(radius_at, (0.0, 500.0), (1.0, 0.0), 20000.0)
    assert run is not None
    t0, t1 = run
    assert t0 < 0.0 < t1 and (t1 - t0) < 2.0 * 4000.0 * 1.6


def test_boundary_stays_within_reasonable_band():
    radius_at = views._boundary(random.Random(5), 5000.0)
    values = [radius_at(math.tau * i / 36.0) for i in range(36)]
    assert min(values) > 5000.0 * 0.4
    assert max(values) < 5000.0 * 1.9


def test_relief_polygon_and_shift_point_away_and_towards_the_city():
    ridge = [(-2000.0, 4000.0), (0.0, 4200.0), (2000.0, 4000.0)]
    poly = views._relief_polygon(ridge, 9000.0)
    assert len(poly) == len(ridge) + 2
    assert views._len(poly[0]) == pytest.approx(9000.0, rel=1e-6)
    shifted = views._shift(ridge, 500.0)
    assert views._len(shifted[1]) < views._len(ridge[1])


def test_seg_distance_is_zero_on_the_line():
    assert views._seg_distance((10.0, 5.0), (0.0, 0.0), (1.0, 0.0)) == pytest.approx(5.0)
    assert views._seg_distance((10.0, 0.0), (0.0, 0.0), (1.0, 0.0)) == pytest.approx(0.0)


def test_local_projector_puts_the_city_centre_at_origin():
    convert = views._local_projector(views.CITIES['shiraz'])
    assert convert(views.CITIES['shiraz']['lon'],
                   views.CITIES['shiraz']['lat']) == (0.0, 0.0)
    east = convert(views.CITIES['shiraz']['lon'] + 0.1,
                   views.CITIES['shiraz']['lat'])
    assert 9000.0 < east[0] < 11000.0 and east[1] == pytest.approx(0.0, abs=1e-6)


# ------------------------------------------------------------------ شبکه ----
def test_grid_network_is_deterministic_and_uses_only_known_classes():
    city = views.CITIES['shiraz']
    cfg = city['grid']
    half_x, half_y = city['span'][0] / 2.0, city['span'][1] / 2.0
    first = views._grid_network(random.Random(city['seed']), cfg, half_x, half_y)
    second = views._grid_network(random.Random(city['seed']), cfg, half_x, half_y)
    assert first['roads'] == second['roads']
    assert len(first['blocks']) > 400
    classes = {cls for cls, _ in first['roads']}
    assert classes <= set(views.ROAD_WIDTH)
    assert {'motorway', 'trunk', 'primary'} <= classes


def test_grid_blocks_sit_inside_the_city_and_look_like_blocks():
    city = views.CITIES['tabriz']
    half_x, half_y = city['span'][0] / 2.0, city['span'][1] / 2.0
    scene = views._grid_network(random.Random(city['seed']), city['grid'],
                                half_x, half_y)
    core = city['grid']['radius']
    for poly, kind in scene['blocks'][:200]:
        assert len(poly) == 4
        assert kind in ('built', 'built_alt', 'park', 'industrial')
        centre = (sum(p[0] for p in poly) / 4.0, sum(p[1] for p in poly) / 4.0)
        assert views._len(centre) < core * 1.8


def test_valley_network_has_a_river_highway_and_long_blocks():
    city = views.CITIES['dorud']
    half_x, half_y = city['span'][0] / 2.0, city['span'][1] / 2.0
    scene = views._valley_network(random.Random(city['seed']), city['valley'],
                                 half_x, half_y)
    assert scene['river']['width'] > 0
    assert len(scene['river']['pts']) > 10
    classes = {cls for cls, _ in scene['roads']}
    assert 'motorway' in classes and 'street' in classes
    assert len(scene['blocks']) > 200
    # شهرِ دره‌ای کشیده است: طول از عرض بیشتر
    xs = [p[0] for poly, _ in scene['blocks'] for p in poly]
    ys = [p[1] for poly, _ in scene['blocks'] for p in poly]
    assert (max(ys) - min(ys)) > (max(xs) - min(xs))


def test_build_scene_has_every_layer():
    for slug, city in views.CITIES.items():
        scene = views.build_scene(random.Random(city['seed']), city)
        for key in ('roads', 'blocks', 'rivers', 'relief', 'lakes', 'greens'):
            assert key in scene, (slug, key)
        assert 'river' not in scene       # رود دره‌ای به لایهٔ رود منتقل شده
        for river in scene['rivers']:
            assert len(river['pts']) > 5 and river['width'] > 0
        assert len(scene['relief']) == len(city.get('relief', []))


def test_greens_stay_outside_the_built_up_core():
    city = views.CITIES['qom']
    scene = views.build_scene(random.Random(city['seed']), city)
    core = city['grid']['radius']
    for poly, tone in scene['greens']:
        assert tone in ('park', 'scrub')
        centre = (sum(p[0] for p in poly) / len(poly),
                  sum(p[1] for p in poly) / len(poly))
        assert views._len(centre) > core


# ------------------------------------------------------------ صداقت نقشه ---
def test_disclaimer_states_the_map_is_not_survey_accurate():
    assert 'STYLIZED' in views.DISCLAIMER
    assert 'NOT SURVEY ACCURATE' in views.DISCLAIMER


def test_default_cities_cover_the_requested_set():
    assert set(views.DEFAULT_CITIES) <= set(views.CITIES)


# ------------------------------------------------------------------- CLI ----
def test_list_mode_prints_every_city():
    assert views.main(['--list']) == 0


def test_unknown_city_and_style_are_rejected():
    assert views.main(['--only','atlantis']) == 2
    assert views.main(['--styles','neon']) == 2


def test_parser_defaults_point_at_the_output_folder():
    args = views.build_parser().parse_args([])
    assert args.out.endswith('city-views')
    assert args.prefix == 'city-'
    assert args.styles == 'sand,ink'


def test_check_mode_reports_missing_files(tmp_path, capsys):
    code = views.main(['--only', 'qom', '--styles', 'sand',
                       '--out', str(tmp_path), '--check'])
    assert code == 1
    assert '0 از 1' in capsys.readouterr().out
    (tmp_path / 'city-qom-sand.png').write_bytes(b'x')
    assert views.main(['--only', 'qom', '--styles', 'sand',
                       '--out', str(tmp_path), '--check']) == 0


@pytest.mark.parametrize('slug,style', [('qom', 'ink'), ('dorud', 'sand')])
def test_render_writes_a_png(tmp_path, slug, style):
    import matplotlib
    matplotlib.use('Agg')
    from PIL import Image

    args = views.build_parser().parse_args(
        ['--only', slug, '--styles', style, '--out', str(tmp_path),
         '--dpi', '60', '--no-counties'])
    path = views._render(slug, style, tmp_path, args, [], None)
    assert path.is_file()
    with Image.open(path) as image:
        assert image.format == 'PNG'
        assert image.size[0] > 400