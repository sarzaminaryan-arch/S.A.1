#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""تست‌های ابزار مقایسهٔ رجیستری جغرافیا با خروجی WXR."""
import json
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from registry_live_diff import (  # noqa: E402
    build_markdown,
    duplicate_published,
    main,
    name_variants_same_slug,
    nonpublish_collisions,
    normalize_name,
    orphans,
    province_link_stats,
    read_items,
    read_registry,
    slug_divergences,
)

PHP_FIXTURE = """<?php
return array(
    array( 'slug' => 'bushehr-city', 'name' => 'بوشهر', 'province' => 'bushehr', 'status' => 'published' ),
    array( 'slug' => 'bandar-e-anzali', 'name' => 'انزلى', 'province' => 'gilan', 'status' => 'empty' ),
    array( 'slug' => 'isfahan', 'name' => 'اصفهان', 'province' => 'isfahan', 'status' => 'draft' ),
);
"""

WXR_FIXTURE = """<?xml version="1.0" encoding="UTF-8" ?>
<rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
  <item><title>شهرستان بوشهر</title><content:encoded><![CDATA[<p>متن</p>]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>bushehr-county</wp:post_name><wp:status>publish</wp:status></item>
  <item><title>شهرستان بندر انزلی</title><content:encoded><![CDATA[<a href="/city/lahijan/">لاهیجان</a>]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>bandar-e-anzali</wp:post_name><wp:status>publish</wp:status></item>
  <item><title>شهرستان اصفهان</title><content:encoded><![CDATA[]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>isfahan-city</wp:post_name><wp:status>publish</wp:status></item>
  <item><title>اصفهان</title><content:encoded><![CDATA[]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>isfahan</wp:post_name><wp:status>draft</wp:status></item>
  <item><title>شهرستان خرمدره</title><content:encoded><![CDATA[]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>kharadere</wp:post_name><wp:status>publish</wp:status></item>
  <item><title>شهرستان خرمدره</title><content:encoded><![CDATA[]]></content:encoded>
    <wp:post_type>city</wp:post_type><wp:post_name>khorramdarreh</wp:post_name><wp:status>publish</wp:status></item>
  <item><title>استان گیلان</title><content:encoded><![CDATA[<a href="/city/lahijan/">ل</a><a href="/city/rasht/">ر</a>]]></content:encoded>
    <wp:post_type>province</wp:post_type><wp:post_name>gilan</wp:post_name><wp:status>publish</wp:status></item>
</channel></rss>
"""


def write_tmp(suffix, text):
    fd, path = tempfile.mkstemp(suffix=suffix)
    with os.fdopen(fd, 'w', encoding='utf-8') as fh:
        fh.write(text)
    return path


class NormalizeTests(unittest.TestCase):
    def test_normalizes_arabic_letters_and_prefix(self):
        self.assertEqual(normalize_name('انزل\u064a'), 'انزل\u06cc')
        self.assertEqual(normalize_name('انزل\u0649'), 'انزل\u06cc')
        self.assertEqual(normalize_name('شهرستان بندر  انزلی'), 'بندر انزلی')
        self.assertEqual(normalize_name('ماه‌نشان'), 'ماه نشان')

    def test_arabic_kaf(self):
        self.assertEqual(normalize_name('كوثر'), 'کوثر')


class CompareTests(unittest.TestCase):
    def setUp(self):
        self.registry = read_registry(write_tmp('.php', PHP_FIXTURE))
        self.live = read_items(write_tmp('.xml', WXR_FIXTURE), 'city')

    def test_slug_divergence_found_for_same_name(self):
        rows = slug_divergences(self.registry, self.live)
        pairs = {(r['registry_slug'], r['live_slug']) for r in rows}
        self.assertIn(('bushehr-city', 'bushehr-county'), pairs)
        self.assertIn(('isfahan', 'isfahan-city'), pairs)
        self.assertEqual(len(rows), 2)

    def test_duplicate_published_pairs(self):
        dups = duplicate_published(self.live)
        self.assertEqual(len(dups), 1)
        self.assertEqual(sorted(dups[0]['slugs']), ['kharadere', 'khorramdarreh'])

    def test_draft_collision_with_published(self):
        collisions = nonpublish_collisions(self.live)
        self.assertEqual([c['slug'] for c in collisions], ['isfahan'])
        self.assertEqual(collisions[0]['published_slugs'], ['isfahan-city'])

    def test_name_variant_same_slug(self):
        rows = name_variants_same_slug(self.registry, self.live)
        self.assertEqual([r['slug'] for r in rows], ['bandar-e-anzali'])
        self.assertEqual(rows[0]['registry_name'], 'انزل\u0649')
        self.assertEqual(rows[0]['live_title'], 'شهرستان بندر انزلی')

    def test_orphan_missing_registry_row(self):
        audit = orphans(self.registry, self.live)
        self.assertIn('bushehr-county', audit['live_slugs_not_in_registry'])
        self.assertIn('خرمدره', audit['live_published_names_not_in_registry'])
        self.assertIn('انزلی', audit['registry_names_without_live_page'])
        self.assertEqual(audit['registry_status_counts'],
                         {'published': 1, 'empty': 1, 'draft': 1})

    def test_province_link_stats(self):
        stats = province_link_stats(read_items(write_tmp('.xml', WXR_FIXTURE), 'province'))
        self.assertEqual(stats['provinces'], 1)
        self.assertEqual(stats['total_city_links'], 2)
        self.assertEqual(stats['provinces_with_zero'], [])

    def test_markdown_has_tables_and_no_article_text(self):
        report = {
            'slug_divergences': slug_divergences(self.registry, self.live),
            'duplicate_published': duplicate_published(self.live),
            'nonpublish_collisions': nonpublish_collisions(self.live),
            'name_variants_same_slug': name_variants_same_slug(self.registry, self.live),
            'orphans': orphans(self.registry, self.live),
            'provinces': {'provinces': 1, 'total_city_links': 2, 'provinces_with_zero': []},
        }
        md = build_markdown(report)
        self.assertIn('جدول ۱', md)
        self.assertIn('`kharadere`', md)
        self.assertIn('هابِ استان', md)
        self.assertNotIn('لاهیجان', md)


class CliTests(unittest.TestCase):
    def test_cli_writes_json_and_markdown(self):
        php = write_tmp('.php', PHP_FIXTURE)
        xml = write_tmp('.xml', WXR_FIXTURE)
        out_json = write_tmp('.json', '{}')
        out_md = write_tmp('.md', '')
        rc = main(['--registry', php, '--wxr', xml, '--wxr-provinces', xml,
                   '--json', out_json, '--markdown', out_md])
        self.assertEqual(rc, 0)
        data = json.load(open(out_json, encoding='utf-8'))
        self.assertIn('slug_divergences', data)
        self.assertIn('provinces', data)
        self.assertIn('جدول ۳', open(out_md, encoding='utf-8').read())


if __name__ == '__main__':
    unittest.main()
