#!/usr/bin/env python3
"""Run the actual workflow shell with stub test commands to verify failure propagation."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class ReleaseGateTests(unittest.TestCase):
    def test_test_failures_block_release_and_keep_report(self):
        yml = (ROOT / '.github/workflows/release-child-theme.yml').read_text()
        triggers = yml.split('\non:\n', 1)[1].split('\npermissions:', 1)[0]
        self.assertNotIn('arena/**', triggers)
        self.assertIn("if: ${{ github.ref == 'refs/heads/main' }}", yml)
        step = yml.split("      - name: 'Run licensed-photo tests", 1)[1].split('\n      - name:', 1)[0]
        self.assertNotIn('continue-on-error: true', step)
        script = '\n'.join(line[10:] for line in step.split('        run: |\n', 1)[1].splitlines())
        for php_code, python_code in ((0, 0), (1, 0), (0, 1), (1, 1)):
            with self.subTest(php=php_code, python=python_code), tempfile.TemporaryDirectory() as tmp:
                root = Path(tmp)
                bin_dir = root / 'bin'
                bin_dir.mkdir()
                for name, code in [('php', php_code), ('python3', python_code)]:
                    exe = bin_dir / name
                    exe.write_text(f'#!/bin/sh\ncase "$1" in -v|-V) echo stub; exit 0;; esac\nexit {code}\n')
                    exe.chmod(0o755)
                env = {**os.environ, 'PATH': str(bin_dir) + ':' + os.environ['PATH'], 'THEME_DIR': 'theme'}
                result = subprocess.run(['bash', '-e', '-o', 'pipefail', '-c', script], cwd=root, env=env, capture_output=True)
                self.assertEqual(result.returncode == 0, php_code == python_code == 0)
                self.assertTrue((root / 'tools/photos/results/licensed-photo-tests.txt').exists())


if __name__ == '__main__':
    unittest.main()
