#!/usr/bin/env python3
"""Ensure production-safety regressions are part of ordinary PR/push CI."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class CICoverageTests(unittest.TestCase):
    def test_build_gates_run_safety_regressions(self):
        workflow = (ROOT / '.github/workflows/build-gates.yml').read_text()
        for test in ('test_job_state_recovery.py', 'test_job_exception_recovery.py',
                     'test_workflow_safety.py', 'test_build_gate.py',
                     # ۲ آذر ۱۴۰۵: پروتکل اجرای شهرستان («یکسان برای همه») باید در CI باشد،
                     # وگرنه قاعده‌ای است که فقط در سند مانده است.
                     'test_county_run.py',
                     # ۳ آذر ۱۴۰۵: قانون ثبت حافظهٔ پایان نشست و نقشهٔ راه ۱۰۰ خوشه.
                     'test_memory_protocol.py',
                     # ۲۰۲۶-۱۱-۲۳: بازنویسیِ فارسیِ بی‌غلط ولی ماشینی روی سایت رفت؛
                     # نگهبان نثر باید در هر دروازه اجرا شود.
                     'test_prose_guard.py'):
            self.assertIn(f'python3 tools/wp-agent/{test}', workflow)


if __name__ == '__main__':
    unittest.main()
