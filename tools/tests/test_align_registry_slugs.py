#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""تست‌های ابزار هم‌راستاسازیِ slugهای رجیستری جغرافیا."""
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from align_registry_slugs import apply_fixes, build_markdown, main, plan  # noqa: E402


def reg(slug, name, province='isfahan', status='published'):
    return {'slug': slug, 'name': name, 'province': province, 'status': status}


def live(slug, title, status='publish', province='isfahan'):
    return {'slug': slug, 'title': title, 'status': status, 'province': province}


class PlanTests(unittest.TestCase):
    def test_same_slug_is_kept(self):
        rows, live_only = plan([reg('natanz', 'نطنز')], [live('natanz', 'شهرستان نطنز')])
        self.assertEqual(rows[0]['action'], 'keep')
        self.assertEqual(rows[0]['reason'], 'same-slug')
        self.assertEqual(live_only, [])

    def test_single_name_match_becomes_fix(self):
        rows, _ = plan([reg('isfahan', 'اصفهان')], [live('isfahan-city', 'شهرستان اصفهان')])
        self.assertEqual(rows[0]['action'], 'fix')
        self.assertEqual(rows[0]['live_slug'], 'isfahan-city')

    def test_ambiguous_name_is_flagged(self):
        rows, _ = plan(
            [reg('bushehr-city', 'بوشهر')],
            [live('bushehr-city', 'شهر بوشهر'), live('bushehr-county', 'شهرستان بوشهر')],
        )
        # slug یکسان با یکی از صفحه‌ها → دست‌نخورده؛ ابهام در قالبِ «چند صفحهٔ هم‌نام» ثبت می‌شود.
        self.assertEqual(rows[0]['action'], 'keep')

        rows, _ = plan(
            [reg('bushehr-old', 'بوشهر')],
            [live('bushehr-city', 'شهر بوشهر'), live('bushehr-county', 'شهرستان بوشهر')],
        )
        self.assertEqual(rows[0]['action'], 'flag')
        self.assertEqual(rows[0]['reason'], 'ambiguous-name')
        self.assertEqual(rows[0]['candidates'], ['bushehr-city', 'bushehr-county'])

    def test_taken_slug_is_flagged(self):
        rows, _ = plan(
            [reg('old-slug', 'نطنز'), reg('natanz', 'شهرستان نطنز')],
            [live('natanz', 'نطنز')],
        )
        flagged = {r['slug']: r for r in rows}['old-slug']
        self.assertEqual(flagged['action'], 'flag')
        self.assertEqual(flagged['reason'], 'slug-taken')

    def test_missing_page_is_kept(self):
        rows, _ = plan([reg('gone', 'شهرستان رفتنی')], [])
        self.assertEqual(rows[0]['action'], 'keep')
        self.assertEqual(rows[0]['reason'], 'no-published-match')

    def test_drafts_are_ignored(self):
        rows, live_only = plan([reg('isfahan', 'اصفهان')], [live('isfahan', 'اصفهان', status='draft')])
        self.assertEqual(rows[0]['reason'], 'no-published-match')
        self.assertEqual(live_only, [])

    def test_live_only_pages_are_listed(self):
        _, live_only = plan([reg('natanz', 'نطنز')], [live('aliabad', 'شهرستان علی‌آباد کتول', province='golestan')])
        self.assertEqual([i['slug'] for i in live_only], ['aliabad'])
        self.assertEqual(live_only[0]['province'], 'golestan')


class ApplyTests(unittest.TestCase):
    PHP = """<?php
return array(
    // کامنتِ حفظ‌شدنی
    array( 'slug' => 'isfahan', 'name' => 'اصفهان', 'province' => 'isfahan', 'status' => 'published' ),
    array( 'slug' => 'natanz', 'name' => 'نطنز', 'province' => 'isfahan', 'status' => 'published' ),
);
"""

    def _write(self, text):
        handle = tempfile.NamedTemporaryFile('w', suffix='.php', delete=False, encoding='utf-8')
        handle.write(text)
        handle.close()
        self.addCleanup(lambda: os.path.exists(handle.name) and os.unlink(handle.name))
        return handle.name

    def test_apply_changes_only_fix_rows(self):
        path = self._write(self.PHP)
        rows = [dict(reg('isfahan', 'اصفهان'), action='fix', live_slug='isfahan-city'),
                dict(reg('natanz', 'نطنز'), action='keep', live_slug='natanz')]
        applied = apply_fixes(path, rows)
        self.assertEqual(applied, {'isfahan': 'isfahan-city'})
        text = open(path, encoding='utf-8').read()
        self.assertIn("'slug' => 'isfahan-city'", text)
        self.assertIn("'slug' => 'natanz'", text)
        self.assertIn('// کامنتِ حفظ‌شدنی', text)
        self.assertEqual(text.count('array('), self.PHP.count('array('))

    def test_second_apply_is_noop(self):
        path = self._write(self.PHP)
        rows = [dict(reg('isfahan', 'اصفهان'), action='fix', live_slug='isfahan-city')]
        apply_fixes(path, rows)
        self.assertEqual(apply_fixes(path, rows), {})

    def test_markdown_reports_counts(self):
        rows, live_only = plan([reg('isfahan', 'اصفهان')], [live('isfahan-city', 'شهرستان اصفهان')])
        markdown = build_markdown({'rows': rows, 'live_only': live_only, 'applied': {'isfahan': 'isfahan-city'}})
        self.assertIn('| اصلاحِ slug (یک نام، یک صفحهٔ هم‌نام) | 1 |', markdown)
        self.assertIn('`isfahan` | `isfahan-city`', markdown)
        self.assertIn('اصلاح‌های اعمال‌شده', markdown)

    def test_main_dry_run_does_not_touch_file(self):
        registry = self._write(self.PHP)
        wxr = self._write(
            '<?xml version="1.0" encoding="UTF-8" ?>\n'
            '<rss xmlns:wp="http://wordpress.org/export/1.2/">\n<channel>\n'
            '  <item><title>شهرستان اصفهان</title><wp:post_type>city</wp:post_type>'
            '<wp:post_name>isfahan-city</wp:post_name><wp:status>publish</wp:status></item>\n'
            '</channel>\n</rss>\n'
        )
        out = self._write('')
        before = open(registry, encoding='utf-8').read()
        main(['--registry', registry, '--wxr', wxr, '--markdown', out])
        self.assertEqual(open(registry, encoding='utf-8').read(), before)
        self.assertIn('isfahan-city', open(out, encoding='utf-8').read())


if __name__ == '__main__':
    unittest.main()
