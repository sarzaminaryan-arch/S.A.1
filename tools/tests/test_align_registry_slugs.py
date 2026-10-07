#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""تست‌های ابزار هم‌راستاسازیِ نامک‌های رجیستری جغرافیا با خروجی زنده."""
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from align_registry_slugs import (  # noqa: E402
    ROW,
    apply_fixes,
    build_markdown,
    key_variants,
    main,
    plan,
    read_live,
    read_redirects,
    read_registry,
    validate,
)

WXR = """<?xml version="1.0" encoding="UTF-8" ?>
<rss xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <item><title>شهرستان بوشهر</title><wp:post_type>city</wp:post_type>
    <wp:post_name>bushehr-county</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="bushehr">بوشهر</category></item>
  <item><title>شهر بوشهر</title><wp:post_type>city</wp:post_type>
    <wp:post_name>bushehr-city</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="bushehr">بوشهر</category></item>
  <item><title>شهرستان نطنز</title><wp:post_type>city</wp:post_type>
    <wp:post_name>natanz</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="isfahan">اصفهان</category></item>
  <item><title>شهرستان ایجرود</title><wp:post_type>city</wp:post_type>
    <wp:post_name>ejrud</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="zanjan">زنجان</category></item>
  <item><title>شهرستان ایجرود</title><wp:post_type>city</wp:post_type>
    <wp:post_name>ijrud</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="zanjan">زنجان</category></item>
  <item><title>شهرستان اصفهان</title><wp:post_type>city</wp:post_type>
    <wp:post_name>isfahan-city</wp:post_name><wp:status>publish</wp:status>
    <category domain="province_tax" nicename="isfahan">اصفهان</category></item>
  <item><title>پیش‌نویس</title><wp:post_type>city</wp:post_type>
    <wp:post_name>draft-one</wp:post_name><wp:status>draft</wp:status></item>
</channel>
</rss>
"""

REGISTRY = """<?php
return array(
	// کامنتِ حفظ‌شدنی
	array( 'slug' => 'natanz', 'name' => 'نطنز', 'province' => 'isfahan', 'status' => 'published' ),
	array( 'slug' => 'isfahan', 'name' => 'اصفهان', 'province' => 'isfahan', 'status' => 'published' ),
	array( 'slug' => 'bushehr-county', 'name' => 'بوشهر', 'province' => 'bushehr', 'status' => 'published' ),
	array( 'slug' => 'ejrud', 'name' => 'ایجرود', 'province' => 'zanjan', 'status' => 'empty' ),
	array( 'slug' => 'kharadere', 'name' => 'خرمدره', 'province' => 'zanjan', 'status' => 'empty' ),
);
"""

REDIRECTS = """<?php
return array(
	'/city/ijrud/' => '/city/ejrud/',
);
"""


def write_temp(text, suffix='.php'):
    handle = tempfile.NamedTemporaryFile('w', suffix=suffix, delete=False, encoding='utf-8')
    handle.write(text)
    handle.close()
    return handle.name


class NameTests(unittest.TestCase):
    def test_key_variants_drop_type_prefixes(self):
        self.assertEqual(key_variants('شهرستان بوشهر'), {'بوشهر'})
        self.assertEqual(key_variants('شهر بوشهر'), {'شهر بوشهر', 'بوشهر'})
        self.assertEqual(key_variants('استان البرز'), {'البرز'})

    def test_key_variants_normalize_arabic_letters_and_zwnj(self):
        self.assertIn('علی آباد کتول', key_variants('علی‌آباد کتول'))
        self.assertEqual(key_variants('كاشان'), key_variants('کاشان'))


class ReadTests(unittest.TestCase):
    def test_read_registry_keeps_line_numbers(self):
        path = write_temp(REGISTRY)
        self.addCleanup(os.unlink, path)
        rows, lines = read_registry(path)
        self.assertEqual(len(rows), 5)
        self.assertEqual(rows[0]['slug'], 'natanz')
        self.assertEqual(rows[0]['line'], 3)
        self.assertEqual(len(lines), len(REGISTRY.split('\n')))

    def test_read_live_reads_published_city_only(self):
        path = write_temp(WXR, '.xml')
        self.addCleanup(os.unlink, path)
        live = read_live(path)
        self.assertEqual(sorted(item['slug'] for item in live),
                         ['bushehr-city', 'bushehr-county', 'ejrud', 'ijrud', 'isfahan-city', 'natanz'])
        natanz = [item for item in live if item['slug'] == 'natanz'][0]
        self.assertEqual(natanz['province'], 'isfahan')

    def test_read_redirects(self):
        path = write_temp(REDIRECTS)
        self.addCleanup(os.unlink, path)
        self.assertEqual(read_redirects(path), {'/city/ijrud/'})


class PlanTests(unittest.TestCase):
    def setUp(self):
        self.registry_path = write_temp(REGISTRY)
        self.wxr_path = write_temp(WXR, '.xml')
        self.addCleanup(os.unlink, self.registry_path)
        self.addCleanup(os.unlink, self.wxr_path)
        registry, _ = read_registry(self.registry_path)
        live = read_live(self.wxr_path)
        self.rows, self.live_only, self.extra = plan(registry, live)

    def entry(self, slug):
        return [row for row in self.rows if row['slug'] == slug][0]

    def test_exact_row_needs_no_change(self):
        row = self.entry('natanz')
        self.assertEqual(row['action'], 'keep')
        self.assertEqual(row['reason'], 'exact')

    def test_single_name_match_is_fixed(self):
        row = self.entry('isfahan')
        self.assertEqual(row['action'], 'fix')
        self.assertEqual(row['live_slug'], 'isfahan-city')

    def test_county_row_pointing_to_county_page_is_exact(self):
        # پس از هم‌راستاسازیِ دستی، ردیفِ «بوشهر» به صفحهٔ شهرستان اشاره می‌کند و exact است.
        row = self.entry('bushehr-county')
        self.assertEqual(row['reason'], 'exact')
        self.assertEqual(row['live_slug'], 'bushehr-county')

    def test_ambiguous_name_is_flagged(self):
        registry, _ = read_registry(self.registry_path)
        for row in registry:
            if row['slug'] == 'bushehr-county':
                row['slug'] = 'bushehr-old'
        rows, _, _ = plan(registry, read_live(self.wxr_path))
        row = [r for r in rows if r['slug'] == 'bushehr-old'][0]
        self.assertEqual(row['action'], 'flag')
        self.assertEqual(row['reason'], 'ambiguous-name')
        self.assertEqual(sorted(m['slug'] for m in row['matches']), ['bushehr-city', 'bushehr-county'])

    def test_taken_slug_is_flagged(self):
        registry, _ = read_registry(self.registry_path)
        for row in registry:
            if row['slug'] == 'isfahan':
                row['slug'] = 'ejrud'
        rows, _, _ = plan(registry, read_live(self.wxr_path))
        row = [r for r in rows if r['slug'] == 'ejrud'][0]
        self.assertEqual(row['action'], 'keep')

    def test_missing_name_is_reported(self):
        self.assertEqual(self.entry('kharadere')['reason'], 'missing-name')

    def test_extra_name_pages_are_listed(self):
        # bushehr-city («شهر بوشهر») و ijrud («ایجرودِ» تکراری) نامشان با یک ردیف یکی است
        # ولی نامکِ خودشان ردیفِ رجیستری نیست؛ isfahan-city چون مقصدِ ردیفِ اصلاح‌شده است
        # در این فهرست نمی‌آید.
        self.assertEqual(sorted(page['slug'] for page in self.extra), ['bushehr-city', 'ijrud'])
        ijrud = [page for page in self.extra if page['slug'] == 'ijrud'][0]
        self.assertIn('ejrud', ijrud['registry_slug'])

    def test_live_only_pages_are_listed(self):
        self.assertEqual([page['slug'] for page in self.live_only], [])


class ApplyTests(unittest.TestCase):
    def test_apply_changes_only_fix_rows_and_keeps_formatting(self):
        path = write_temp(REGISTRY)
        self.addCleanup(os.unlink, path)
        applied = apply_fixes(path, {'isfahan': 'isfahan-city'})
        self.assertEqual(applied, {'isfahan': 'isfahan-city'})
        text = open(path, encoding='utf-8').read()
        self.assertIn("'slug' => 'isfahan-city'", text)
        self.assertIn('// کامنتِ حفظ‌شدنی', text)
        self.assertEqual(len(text.split('\n')), len(REGISTRY.split('\n')))
        self.assertEqual(apply_fixes(path, {'isfahan': 'isfahan-city'}), {})

    def test_main_dry_run_does_not_touch_registry(self):
        registry = write_temp(REGISTRY)
        wxr = write_temp(WXR, '.xml')
        out = write_temp('', '.md')
        self.addCleanup(os.unlink, registry)
        self.addCleanup(os.unlink, wxr)
        self.addCleanup(os.unlink, out)
        before = open(registry, encoding='utf-8').read()
        main(['--registry', registry, '--wxr', wxr, '--markdown', out])
        self.assertEqual(open(registry, encoding='utf-8').read(), before)
        self.assertIn('isfahan-city', open(out, encoding='utf-8').read())

    def test_main_apply_writes_slug(self):
        registry = write_temp(REGISTRY)
        wxr = write_temp(WXR, '.xml')
        out = write_temp('', '.md')
        self.addCleanup(os.unlink, registry)
        self.addCleanup(os.unlink, wxr)
        self.addCleanup(os.unlink, out)
        main(['--registry', registry, '--wxr', wxr, '--markdown', out, '--apply'])
        self.assertIn("'slug' => 'isfahan-city'", open(registry, encoding='utf-8').read())


class ValidateTests(unittest.TestCase):
    def test_validate_flags_duplicates_and_bad_slugs(self):
        rows = [{'slug': 'natanz'}, {'slug': 'natanz'}, {'slug': 'Bad Slug'}]
        problems = validate(rows)
        self.assertIn('duplicate slug: natanz', problems)
        self.assertIn('invalid slug: Bad Slug', problems)

    def test_markdown_reports_counts(self):
        registry = write_temp(REGISTRY)
        wxr = write_temp(WXR, '.xml')
        self.addCleanup(os.unlink, registry)
        self.addCleanup(os.unlink, wxr)
        rows, live_only, extra = plan(read_registry(registry)[0], read_live(wxr))
        markdown = build_markdown({'rows': rows, 'live_only': live_only,
                                   'extra_name_pages': extra, 'applied': {'isfahan': 'isfahan-city'},
                                   'merged_paths': ['/city/ijrud/']})
        self.assertIn('| نامکِ اصلاح‌شده (fix) | 1 |', markdown)
        self.assertIn('`isfahan` | `isfahan-city`', markdown)
        self.assertIn('ادغام‌شده (۳۰۱)', markdown)

    def test_row_regex_matches_theme_format(self):
        match = ROW.search("\tarray( 'slug' => 'a-b', 'name' => 'نام', 'province' => 'p', 'status' => '' ),")
        self.assertIsNotNone(match)
        self.assertEqual(match.group(1), 'a-b')


if __name__ == '__main__':
    unittest.main()
