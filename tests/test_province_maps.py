# -*- coding: utf-8 -*-
"""تست‌های tools/maps — نرمال‌سازی نام و خواندن مرجع استان‌ها.

این تست‌ها به شبکه، geopandas یا matplotlib نیاز ندارند: تنها بخش شکنندهٔ ابزار —
تطبیق نام استان‌ها بین مرجع مخزن و GeoJSON — بدون آن‌ها هم قابل سنجش است.
"""
from __future__ import annotations

import sys
from pathlib import Path

import pytest

REPO = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / 'tools' / 'maps'))

import make_province_maps as maps  # noqa: E402
import make_province_3d as m3d  # noqa: E402


# ۱ ------------------------------------------------------- مرجع استان‌ها -----
def test_load_provinces_reads_all_31():
    provinces = maps.load_provinces()
    assert len(provinces) == 31
    slugs = {p['slug'] for p in provinces}
    assert 'lorestan' in slugs
    assert 'sistan-baluchestan' in slugs


def test_load_provinces_keeps_persian_name_and_center():
    by_slug = {p['slug']: p for p in maps.load_provinces()}
    lorestan = by_slug['lorestan']
    assert lorestan['name_fa'] == 'لرستان'
    assert lorestan['en'] == 'Lorestan'
    assert lorestan['center'] == 'خرم‌آباد'


def test_load_provinces_missing_file_raises():
    with pytest.raises(SystemExit):
        maps.load_provinces('/tmp/definitely-not-here.php')


# ۲ ------------------------------------------------------- نرمال‌سازی ------
def test_norm_strips_zero_width_and_directional_marks():
    assert maps.norm('قم‎') == maps.norm('قم')
    assert maps.norm('کهگیلویه و بویر‌احمد') == maps.norm('کهگیلویه و بویر احمد')


def test_norm_maps_arabic_letters_to_persian():
    assert maps.norm('آذربايجان شرقي') == maps.norm('آذربایجان شرقی')


def test_norm_is_case_and_space_insensitive():
    assert maps.norm('  North   Khorasan ') == maps.norm('north khorasan')


def test_norm_keeps_genuinely_different_names_apart():
    assert maps.norm('فارس') != maps.norm('فیروز')
    assert maps.norm('کرمان') != maps.norm('کرمانشاه')


# ۳ --------------------------------------------------------- تطبیق ----------
def _frame(rows):
    pytest.importorskip('geopandas')
    import geopandas as gpd
    from shapely.geometry import box
    return gpd.GeoDataFrame(rows, geometry=[box(i, 0, i + 1, 1) for i in range(len(rows))],
                            crs='EPSG:4326')


def test_match_provinces_uses_persian_name_first():
    # «قم‎» با کاراکتر جهت‌دهی در فایل مرزها، در مرجع بدون آن است → باید با نام فارسی بخواند
    gdf = _frame([{'name': 'قم‎', 'name:en': 'Qom'}])
    wanted = [{'slug': 'qom', 'name_fa': 'قم', 'en': 'Qom', 'center': 'قم'}]
    matched, missing = maps.match_provinces(gdf, wanted)
    assert not missing
    assert matched['qom']['matched_by'] == 'name:fa'


def test_match_provinces_falls_back_when_persian_name_differs():
    # مرجع مخزن «کهگیلویه و بویراحمد» (بدون فاصله) و فایل مرزها «… بویر احمد»
    # است؛ نرمال‌سازی این تفاوت را پر نمی‌کند، پس مسیر نام انگلیسی باید نجات دهد.
    gdf = _frame([{'name': 'کهگیلویه و بویر احمد',
                   'name:en': 'Kohgiluyeh and Boyer-Ahmad'}])
    wanted = [{'slug': 'kohgiluyeh-boyer-ahmad', 'name_fa': 'کهگیلویه و بویراحمد',
               'en': 'Kohgiluyeh and Boyer-Ahmad', 'center': 'یاسوج'}]
    matched, missing = maps.match_provinces(gdf, wanted)
    assert not missing
    assert matched['kohgiluyeh-boyer-ahmad']['matched_by'] == 'name:en'


def test_match_provinces_falls_back_to_english_name():
    # فایل مرزها نام فارسی را ندارد → باید با نام انگلیسی تطبیق بدهد
    gdf = _frame([{'name:en': 'Qom'}])
    wanted = [{'slug': 'qom', 'name_fa': 'قم', 'en': 'Qom', 'center': 'قم'}]
    matched, missing = maps.match_provinces(gdf, wanted)
    assert not missing
    assert matched['qom']['matched_by'] == 'name:en'


def test_match_provinces_reports_unmatched_province():
    gdf = _frame([{'name': 'قم', 'name:en': 'Qom'}])
    wanted = [{'slug': 'yazd', 'name_fa': 'یزد', 'en': 'Yazd', 'center': 'یزد'}]
    matched, missing = maps.match_provinces(gdf, wanted)
    assert not matched
    assert [p['slug'] for p in missing] == ['yazd']


# ۴ --------------------------------------------------------- پرچم‌ها ---------
def test_unknown_slug_exits_with_code_2():
    assert maps.main(['--only', 'atlantis', '--check']) == 2


def test_unsupported_format_exits_with_code_2():
    assert maps.main(['--formats', 'jpg', '--check']) == 2


def test_check_reports_missing_output(tmp_path):
    assert maps.main(['--check', '--out', str(tmp_path)]) == 1

# ۵ ------------------------------------------- ابزار سه‌بعدی: نگه‌داشتن نیم‌فاصله
def test_fa_keeps_zero_width_non_joiner():
    # reshape نیم‌فاصله را حذف می‌کند و دو واژه را می‌چسباند؛ ابزار باید برگرداندش.
    shaped3d = m3d.fa('چنگه‌ماغانه')
    shaped2d = maps.fa('چنگه‌ماغانه')
    assert shaped3d.count('‌') == 1
    assert shaped2d.count('‌') == 1


def test_fa_shapes_persian_without_reversing_words():
    # ترتیب دوسویه باید انجام شود؛ متن خامِ معکوس نشده نباید برگردد.
    assert m3d.fa('لرستان') != 'لرستان'
    assert m3d.fa('') is None


def test_county_prefix_is_stripped_before_matching():
    assert m3d._strip_county_prefix('شهرستان قم') == 'قم'
    assert m3d._strip_county_prefix('شهرستان بندر انزلی') == 'بندر انزلی'
    assert m3d._strip_county_prefix('قم') == 'قم'


def test_repo_registry_has_31_provinces_and_483_counties():
    assert len(m3d.load_provinces()) == 31
    assert len(m3d.load_counties()) == 483
