"""تست‌های ابزار نقشهٔ شهری: ساخت پرس‌وجوی Overpass، تبدیل پاسخ و رندر دو مدل."""

from __future__ import annotations

import json
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools' / 'maps'))

import make_city_maps as city  # noqa: E402

BBOX = (30.5, 51.0, 31.5, 52.0)

# پاسخ کوچک و ساختگی در همان ساختار «out geom» اورپس
FAKE_OVERPASS = {
    'elements': [
        {'type': 'way', 'tags': {'highway': 'motorway'},
         'geometry': [{'lat': 30.8, 'lon': 51.4}, {'lat': 31.0, 'lon': 51.6}]},
        {'type': 'way', 'tags': {'highway': 'residential'},
         'geometry': [{'lat': 30.9, 'lon': 51.2}, {'lat': 31.1, 'lon': 51.3}]},
        {'type': 'way', 'tags': {'landuse': 'residential'},
         'geometry': [{'lat': 30.8, 'lon': 51.3}, {'lat': 31.0, 'lon': 51.3},
                      {'lat': 31.0, 'lon': 51.5}, {'lat': 30.8, 'lon': 51.5},
                      {'lat': 30.8, 'lon': 51.3}]},
        {'type': 'way', 'tags': {'natural': 'wood'},
         'geometry': [{'lat': 30.6, 'lon': 51.7}, {'lat': 30.8, 'lon': 51.7},
                      {'lat': 30.8, 'lon': 51.9}, {'lat': 30.6, 'lon': 51.9},
                      {'lat': 30.6, 'lon': 51.7}]},
        {'type': 'way', 'tags': {'leisure': 'park'},
         'geometry': [{'lat': 31.1, 'lon': 51.1}, {'lat': 31.2, 'lon': 51.1},
                      {'lat': 31.2, 'lon': 51.2}, {'lat': 31.1, 'lon': 51.2},
                      {'lat': 31.1, 'lon': 51.1}]},
        {'type': 'way', 'tags': {'waterway': 'stream'},
         'geometry': [{'lat': 30.7, 'lon': 51.1}, {'lat': 30.7, 'lon': 51.9}]},
        {'type': 'way', 'tags': {'highway': 'motorway'},
         'geometry': [{'lat': 99.0, 'lon': 99.0}]},          # بی‌معنا، نباید بترکد
    ],
}


def _transformer():
    from pyproj import Transformer
    return Transformer.from_crs('EPSG:4326', city.core.IRAN_ALBERS, always_xy=True)


# --------------------------------------------------------------- پرس‌وجو ----
def test_overpass_query_asks_for_highways_and_landcover():
    query = city.overpass_query(BBOX)
    assert '[out:json]' in query
    assert 'out geom;' in query
    assert 'motorway' in query and 'residential' in query
    assert 'landuse' in query and 'natural' in query and 'leisure' in query
    # کادر درست در چهار سویه درج شده باشد
    for value in BBOX:
        assert str(value) in query


def test_overpass_query_covers_every_road_class_we_draw():
    query = city.overpass_query(BBOX)
    for name, _ in city.ROAD_CLASSES:
        assert name in query


# --------------------------------------------------------------- تبدیل ------
def test_payload_becomes_roads_areas_and_rivers():
    layers = city.lonlat_to_geojson(FAKE_OVERPASS, _transformer())
    classes = {r['class'] for r in layers['roads']}
    assert classes == {'motorway', 'residential'}
    tags = {json.dumps(a['tags'], sort_keys=True) for a in layers['areas']}
    assert any('wood' in t for t in tags)
    assert any('park' in t for t in tags)
    assert any('residential' in t for t in tags)
    assert len(layers['rivers']) == 1


def test_projected_geometry_lands_in_iran_albers_range():
    layers = city.lonlat_to_geojson(FAKE_OVERPASS, _transformer())
    for road in layers['roads']:
        x, y = road['geometry'].coords[0]
        assert 1e5 < abs(x) < 5e6, 'مختصات باید در تصویر معادل ایران باشد'
        assert 1e5 < abs(y) < 5e6


def test_paths_flattens_multipart_and_skips_empty_geometries():
    import shapely.geometry as sg
    multi = sg.MultiLineString([[(0, 0), (1, 1)], [(2, 2), (3, 3)]])
    poly = sg.Polygon([(0, 0), (1, 0), (1, 1)])
    empty = sg.Polygon()
    paths = city._paths([multi, poly, None, empty])
    # چندخطی دو تکه است و چندضلعی یک مسیر بسته
    assert len(paths) == 3
    assert paths[0] == [(0.0, 0.0), (1.0, 1.0)]
    assert paths[2][0] == paths[2][-1], 'مسیر چندضلعی باید بسته باشد'


# ------------------------------------------------------------- فاصلهٔ نام ----
def test_letterspacing_matches_the_reference_style():
    assert city._letterspace('Rome') == 'R O M E'
    assert '   ' in city._letterspace('Razavi Khorasan')


# --------------------------------------------------------------- رندر ------
def test_both_styles_render_with_street_data(tmp_path, monkeypatch):
    pytest.importorskip('matplotlib')
    import matplotlib
    matplotlib.use('Agg')
    import geopandas as gpd

    monkeypatch.setattr(city, 'fetch_streets',
                        lambda *a, **k: FAKE_OVERPASS)

    province = [p for p in city.core.load_provinces() if p['slug'] == 'qom'][0]
    frame = gpd.read_file(city.core.ensure_provinces()).to_crs(city.core.IRAN_ALBERS)
    fa2slug = {city.core.norm(p['name_fa']): p['slug']
               for p in city.core.load_provinces()}
    row = frame[frame['name'].map(lambda x: fa2slug.get(city.core.norm(x), ''))
                == 'qom']
    geom = row.geometry.iloc[0]
    if not geom.is_valid:
        geom = geom.buffer(0)
    counties, _ = city.core.build_counties(
        city.core.ensure_counties(), province, city.core.load_counties(),
        geom, frame.crs)

    args = city.build_parser().parse_args(['--out', str(tmp_path)])
    from pyproj import Transformer
    tr = Transformer.from_crs('EPSG:4326', city.core.IRAN_ALBERS, always_xy=True)
    layers = city.lonlat_to_geojson(FAKE_OVERPASS, tr)

    written = []
    for style in city.DEFAULT_STYLES:
        written.append(city._render(province, style, counties, geom, layers,
                                    True, 34.64, 50.88, tmp_path, args))
    assert len(written) == 2
    for path in written:
        assert path.is_file()
        assert path.stat().st_size > 20_000
        # نام فایل بدون پسوند «nostreets» است چون دادهٔ خیابان واقعی بود
        assert 'nostreets' not in path.name
        assert path.name.endswith('%s.png' % style_of(path))


def style_of(path):
    return path.stem.rsplit('-', 1)[-1]


def test_fallback_layers_never_invent_roads(tmp_path, monkeypatch):
    """وقتی Overpass نیست، فقط مرز شهرستان — نه جادهٔ ساختگی."""
    pytest.importorskip('matplotlib')
    import matplotlib
    matplotlib.use('Agg')
    import geopandas as gpd

    def boom(*_a, **_k):
        raise RuntimeError('Overpass در دسترس نیست')

    monkeypatch.setattr(city, 'fetch_streets', boom)

    province = [p for p in city.core.load_provinces() if p['slug'] == 'qom'][0]
    frame = gpd.read_file(city.core.ensure_provinces()).to_crs(city.core.IRAN_ALBERS)
    fa2slug = {city.core.norm(p['name_fa']): p['slug']
               for p in city.core.load_provinces()}
    row = frame[frame['name'].map(lambda x: fa2slug.get(city.core.norm(x), ''))
                == 'qom']
    geom = row.geometry.iloc[0]
    counties, _ = city.core.build_counties(
        city.core.ensure_counties(), province, city.core.load_counties(),
        geom, frame.crs)
    layers = city.county_fallback(counties)
    assert layers['areas'] == [] and layers['rivers'] == []
    assert all(r['class'] == 'residential' for r in layers['roads'])

    args = city.build_parser().parse_args(['--out', str(tmp_path)])
    path = city._render(province, 'sand', counties, geom, layers, False,
                        34.64, 50.88, tmp_path, args)
    assert path.is_file()
    assert path.name.endswith('-nostreets.png'), 'بدون خیابان باید صریح علامت بخورد'


# --------------------------------------------------------------- گزینه‌ها ----
def test_defaults_cover_qom_and_fars_in_both_styles():
    args = city.build_parser().parse_args([])
    assert args.only == 'qom,fars'
    assert args.styles == 'sand,ink'
    assert set(city.DEFAULT_STYLES) <= set(city.STYLES)


def test_unknown_style_and_province_are_rejected():
    assert city.main(['--styles', 'neon', '--check']) == 2
    assert city.main(['--only', 'atlantis', '--check']) == 2
