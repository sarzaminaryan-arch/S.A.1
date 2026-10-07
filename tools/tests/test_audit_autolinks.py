#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""تست‌های ابزار ممیزیِ لینک‌سازی داخلیِ خودکار (شبیه‌سازی رندر)."""
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

import audit_autolinks as aa  # noqa: E402

REGISTRY_FIXTURE = """<?php
return array(
    array( 'slug' => 'alborz-qazvin', 'name' => 'البرز', 'province' => 'qazvin', 'status' => 'empty' ),
    array( 'slug' => 'karaj', 'name' => 'کرج', 'province' => 'alborz', 'status' => 'published' ),
    array( 'slug' => 'tehran-city', 'name' => 'تهران', 'province' => 'tehran', 'status' => 'published' ),
);
"""

PROVINCES_FIXTURE = """<?php
return array(
    array( 'slug' => 'alborz', 'name' => 'البرز', 'en' => 'Alborz', 'center' => 'کرج' ),
    array( 'slug' => 'qazvin', 'name' => 'قزوین', 'en' => 'Qazvin', 'center' => 'قزوین' ),
);
"""


def write_tmp(suffix, text):
    fd, path = tempfile.mkstemp(suffix=suffix)
    with os.fdopen(fd, 'w', encoding='utf-8') as fh:
        fh.write(text)
    return path


def live_item(post_type, slug, title, status='publish', province=''):
    return {'type': post_type, 'slug': slug, 'title': title, 'status': status,
            'province': province, 'content': '<p>متن</p>'}


class NormalizeTests(unittest.TestCase):
    def test_strips_prefixes_and_normalizes_letters(self):
        self.assertEqual(aa.normalize('شهرستان البرز'), 'البرز')
        self.assertEqual(aa.normalize('استان البرز'), 'البرز')
        self.assertEqual(aa.normalize('انزل\u064a'), 'انزلی')
        self.assertEqual(aa.normalize('ماه\u200cنشان'), 'ماه نشان')


class AmbiguityTests(unittest.TestCase):
    def setUp(self):
        self.provinces = aa.read_provinces(write_tmp('.php', PROVINCES_FIXTURE))
        self.registry = aa.read_registry_rows(write_tmp('.php', REGISTRY_FIXTURE))

    def test_reads_php_rows(self):
        self.assertEqual(len(self.registry), 3)
        self.assertEqual(self.registry[0]['name'], 'البرز')
        self.assertEqual(self.provinces[0]['name'], 'البرز')

    def test_ambiguous_needle_for_shared_name(self):
        live = [live_item('province', 'alborz', 'استان البرز'),
                live_item('city', 'alborz-qazvin', 'شهرستان البرز', province='qazvin')]
        needles = aa.ambiguous_needles(self.provinces[:1], self.registry[:1], live)
        self.assertIn('البرز', needles)
        self.assertEqual(len(needles['البرز']), 2)

    def test_registry_row_without_live_post_is_not_a_needle(self):
        live = [live_item('province', 'alborz', 'استان البرز')]
        needles = aa.ambiguous_needles(self.provinces[:1], self.registry[1:2], live)
        self.assertEqual(needles, {})


class AnalysisTests(unittest.TestCase):
    def setUp(self):
        self.subjects = [live_item('province', 'alborz', 'استان البرز', province=''),
                         live_item('city', 'alborz-qazvin', 'شهرستان البرز', province='qazvin'),
                         live_item('city', 'kerman', 'شهرستان کرمان', province='kerman')]
        self.live_province = {'alborz-qazvin': 'qazvin', 'kerman': 'kerman'}
        self.result = {
            'max_links': 30,
            'subjects': [
                {'slug': 'alborz', 'type': 'province', 'counts': {'city': 1, 'province': 0,
                                                                 'attraction': 0, 'other': 2},
                 'total': 3,
                 'links': [{'kind': 'city', 'path': '/city/alborz-qazvin/', 'label': 'البرز',
                            'generated': True},
                           {'kind': 'province', 'path': '/province/qazvin/', 'label': 'قزوین',
                            'generated': True}]},
                {'slug': 'alborz-qazvin', 'type': 'city',
                 'counts': {'city': 1, 'province': 1, 'attraction': 0, 'other': 0}, 'total': 2,
                 'links': [{'kind': 'province', 'path': '/province/alborz/', 'label': 'البرز',
                            'generated': True},
                           {'kind': 'city', 'path': '/city/alborz-qazvin/', 'label': 'شهرستان البرز',
                            'generated': False}]},
                {'slug': 'kerman', 'type': 'city',
                 'counts': {'city': 1, 'province': 0, 'attraction': 0, 'other': 0}, 'total': 1,
                 'links': [{'kind': 'city', 'path': '/city/kerman/', 'label': 'شهرستان کرمان',
                            'generated': False}]},
            ],
        }
        self.report = aa.analyse(self.result, self.subjects, {'kerman': 1}, self.live_province)

    def test_wrong_cross_province_own_name_links(self):
        wrong = {(i['title'], i['path']) for i in self.report['issues']['own_name_collisions']}
        self.assertIn(('استان البرز', '/city/alborz-qazvin/'), wrong)
        self.assertIn(('شهرستان البرز', '/province/alborz/'), wrong)
        self.assertEqual(len(wrong), 2)

    def test_authored_self_link_is_not_engine_issue(self):
        self.assertEqual(self.report['issues']['self_links'], [])
        authored = [i['slug'] for i in self.report['issues']['authored_self_links']]
        self.assertEqual(sorted(authored), ['alborz-qazvin', 'kerman'])

    def test_cross_province_counter(self):
        rows = {r['slug']: r for r in self.report['rows']}
        self.assertEqual(rows['alborz']['cross_province'], 1)
        self.assertEqual(rows['alborz-qazvin']['cross_province'], 0)

    def test_summary_totals(self):
        self.assertEqual(self.report['summary']['total_city_links'], 3)
        self.assertEqual(self.report['summary']['provinces_without_city_links'], [])

    def test_markdown_has_sections_and_no_article_text(self):
        markdown = aa.build_markdown(self.report)
        self.assertIn('## ۱. روش', markdown)
        self.assertIn('## ۴. یافته‌های خطا', markdown)
        self.assertIn('`/province/alborz/`', markdown)
        self.assertNotIn('<p>متن</p>', markdown)


class RuleMetricTests(unittest.TestCase):
    """قاعدهٔ «متنِ لینک = نامِ کامل» و شمارشِ تخلف‌های آن."""

    def test_type_prefix_detection(self):
        for ok in ('شهرستان نطنز', 'استان گیلان', 'شهر کرج', 'دهستان مرکزی', 'بخش مرکزی'):
            self.assertTrue(aa._has_type_prefix(ok), ok)
        for bad in ('نطنز', 'بافت', 'شهرضا', 'کرمان', '', 'کاروانسرای نطنز'):
            self.assertFalse(aa._has_type_prefix(bad), bad)

    def test_bare_anchor_counter_is_aggregated(self):
        subjects = [live_item('province', 'kerman', 'استان کرمان', province=''),
                    live_item('city', 'natanz', 'شهرستان نطنز', province='isfahan')]
        result = {'max_links': 30, 'subjects': [
            {'slug': 'kerman', 'type': 'province', 'bare_anchors': 0, 'total': 2,
             'counts': {'city': 2, 'province': 0, 'attraction': 0, 'other': 0},
             'links': [{'kind': 'city', 'path': '/city/natanz/', 'label': 'شهرستان نطنز',
                        'generated': True},
                       {'kind': 'city', 'path': '/city/baft/', 'label': 'شهرستان بافت',
                        'generated': True}]},
            {'slug': 'natanz', 'type': 'city', 'bare_anchors': 1, 'total': 1,
             'counts': {'city': 1, 'province': 0, 'attraction': 0, 'other': 0},
             'links': [{'kind': 'city', 'path': '/city/kashan/', 'label': 'کاشان',
                        'generated': True}]},
        ]}
        report = aa.analyse(result, subjects, {}, {'natanz': 'isfahan', 'baft': 'kerman'})
        self.assertEqual(report['summary']['bare_anchors'], 1)
        self.assertEqual(report['summary']['generated_city_links'], 3)
        markdown = aa.build_markdown(report)
        self.assertIn('| لینکِ تولیدشده با متنِ بدونِ پیشوند (نباید رخ دهد) | 1 |', markdown)
        self.assertIn('تخلفِ قاعدهٔ ۱', markdown)


class JobTests(unittest.TestCase):
    def test_build_job_skips_empty_and_draft(self):
        cities = [live_item('city', 'karaj', 'شهرستان کرج'),
                  live_item('city', 'draft-city', 'پیش‌نویس', status='draft')]
        provinces = [live_item('province', 'alborz', 'استان البرز'),
                     {'type': 'province', 'slug': 'empty', 'title': 'خالی', 'status': 'publish',
                      'content': '   '}]
        job = aa.build_job(cities, provinces)
        self.assertEqual(len(job['roster']), 3)
        self.assertEqual([s['slug'] for s in job['subjects']], ['alborz'])


class BatchTests(unittest.TestCase):
    def test_simulation_is_split_into_batches(self):
        seen = []

        def fake_run_once(job, tmp_dir, runner, node, timeout):
            seen.append(len(job['subjects']))
            return {'max_links': 30, 'subjects': [{'slug': s['slug'], 'type': 'province',
                                                   'counts': {'city': 0, 'province': 0, 'attraction': 0,
                                                              'other': 0},
                                                   'total': 0, 'links': []}
                                                  for s in job['subjects']]}

        original = aa._run_once
        aa._run_once = fake_run_once
        try:
            job = {'roster': [], 'subjects': [{'slug': 'p%d' % i, 'type': 'province', 'title': 't',
                                               'content': '<p>x</p>'} for i in range(7)]}
            out = aa.run_simulation(job, tempfile.mkdtemp(), batch=3)
        finally:
            aa._run_once = original
        self.assertEqual(seen, [3, 3, 1])
        self.assertEqual(len(out['subjects']), 7)


class RegistryCountTests(unittest.TestCase):
    def test_counts_per_province(self):
        counts = aa.read_registry_counties(write_tmp('.php', REGISTRY_FIXTURE))
        self.assertEqual(counts, {'qazvin': 1, 'alborz': 1, 'tehran': 1})


if __name__ == '__main__':
    unittest.main()
