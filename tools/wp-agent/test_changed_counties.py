#!/usr/bin/env python3
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from changed_counties import changed


class ChangesTest(unittest.TestCase):
    def test_multicommit_push_pr_and_invalid_base(self):
        original = os.getcwd()
        with tempfile.TemporaryDirectory() as tmp:
            os.chdir(tmp)
            try:
                def git(*args):
                    return subprocess.run(['git', *args], check=True, capture_output=True, text=True).stdout.strip()
                git('init', '-q')
                git('config', 'user.email', 'test@example.com')
                git('config', 'user.name', 'test')
                Path('init').write_text('initial')
                git('add', '.')
                git('commit', '-qm', 'base')
                base = git('rev-parse', 'HEAD')
                folder = Path('tools/wp-agent/replace_src/data')
                folder.mkdir(parents=True)
                for slug in ('one', 'two'):
                    (folder / f'{slug}.json').write_text('{}')
                    git('add', '.')
                    git('commit', '-qm', slug)
                head = git('rev-parse', 'HEAD')
                self.assertEqual(changed('push', base, head, ''), ['one', 'two'])
                self.assertEqual(changed('pull_request', '', head, base), ['one', 'two'])
                with self.assertRaises(subprocess.CalledProcessError):
                    changed('push', 'not-a-sha', head, '')
                (folder / 'bad name.json').write_text('{}')
                git('add', '.')
                git('commit', '-qm', 'bad')
                with self.assertRaises(ValueError):
                    changed('push', head, git('rev-parse', 'HEAD'), '')
            finally:
                os.chdir(original)


if __name__ == '__main__':
    unittest.main()
