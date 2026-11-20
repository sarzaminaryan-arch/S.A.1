import csv
import tempfile
import unittest
from pathlib import Path

from check_relation_html import check


class RoundTrip(unittest.TestCase):
    def test_compare_destinations_not_just_totals(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            csv_path = root / 'edges.csv'
            with csv_path.open('w', newline='') as f:
                w = csv.writer(f)
                w.writerow(['مبدأ', 'مقصد'])
                w.writerows([['https://test/a/', 'https://test/b/'], ['https://test/b/', 'https://test/a/']])
            for src, dst in [('a', 'b'), ('b', 'a')]:
                (root / src).mkdir()
                (root / src / 'index.html').write_text(f'<header><a href="/{dst}/">Menu</a></header><main><a data-sa-relation href="/{dst}/">Page</a></main>')
            self.assertEqual(check(csv_path, root), [])
            page = root / 'a/index.html'
            page.write_text('<main><a data-sa-relation href="/a/">Wrong</a><a href="/b/">B</a><a href="/b/">B</a></main>')
            errors = check(csv_path, root)
            self.assertEqual(len(errors), 3)  # extra self, missing marked b, duplicate body b
            page.unlink()
            self.assertIn('no captured page', check(csv_path, root)[0])


if __name__ == '__main__':
    unittest.main()
