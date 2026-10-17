"""Synthetic image QA: no downloads, site writes or owner-only answers."""
import hashlib
import json
import tempfile
import unittest
import zipfile
from pathlib import Path
from PIL import Image
from prepare import exterior_box, prepare, CATALOG, lift_shadows, luminance_mean
from build import build, PLUGIN


class ImageTests(unittest.TestCase):
    def test_only_exterior_white_is_removed(self):
        image = Image.new('RGB', (120, 100), 'white')
        image.paste(Image.new('RGB', (100, 80), '#123f73'), (10, 10))
        image.paste(Image.new('RGB', (20, 10), 'white'), (60, 50))
        self.assertEqual((8, 8, 112, 92), exterior_box(image))
        cropped = image.crop(exterior_box(image))
        self.assertEqual((255, 255, 255), cropped.getpixel((52, 42)), 'Interior slots/white design survive')
        self.assertEqual((0, 0, 120, 100), exterior_box(Image.new('RGB', (120, 100), 'white')))
        self.assertEqual((0, 0, 120, 100), exterior_box(Image.new('RGB', (120, 100), '#eee5d2')), 'Cream poster paper is not a white margin')

    def test_oversized_crop_is_refused(self):
        image = Image.new('RGB', (200, 200), 'white')
        image.paste(Image.new('RGB', (20, 20), 'black'), (90, 90))
        self.assertEqual((0, 0, 200, 200), exterior_box(image))

    def test_catalog_matches_real_registry(self):
        import re
        rows = json.loads(CATALOG.read_text())
        registry = PLUGIN.parent.parent / 'themes/sarzaminaryan-child/inc/entity-bars.php'
        entries = re.findall(r"array\( '([^']+)', '([^']+)', \d+, \d+ \)", registry.read_text())[:31]
        self.assertEqual({slug: name for name, slug in entries}, {row['slug']: row['name'] for row in rows})
        self.assertEqual(31, len({row['source'] for row in rows}))
        self.assertTrue(all(row['name'] in row['alt'] and 'گرافیکی' in row['alt'] for row in rows))

    def test_shadow_lifting_preserves_endpoints_and_hue(self):
        import numpy as np
        image = Image.new('RGB', (4, 1))
        image.putdata([(0, 0, 0), (255, 255, 255), (30, 60, 90), (245, 240, 230)])
        result = lift_shadows(image)
        pixels = list(map(tuple, np.asarray(result)[0].tolist()))
        self.assertEqual((0, 0, 0), pixels[0])
        self.assertEqual((255, 255, 255), pixels[1])
        self.assertTrue(all(pixels[2][i] > [30, 60, 90][i] for i in range(3)))
        self.assertAlmostEqual(pixels[2][0] / pixels[2][2], 1 / 3, delta=.02)
        self.assertLessEqual(max(pixels[3]), 247, 'Bright lettering is protected, not blown out')
        self.assertGreater(luminance_mean(result), luminance_mean(image))
        self.assertTrue(np.array_equal(np.asarray(lift_shadows(image, 1)), np.asarray(image)))
        with self.assertRaises(ValueError):
            lift_shadows(image, .4)

    def test_complete_local_pack_and_integrity(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source, optimized = root / 'source', root / 'optimized'
            source.mkdir()
            rows = json.loads(CATALOG.read_text())
            original = {}
            for row in rows:
                file = source / row['source']
                Image.new('RGB', (32, 24), '#123f73').save(file, format='WEBP')
                original[file.name] = hashlib.sha256(file.read_bytes()).hexdigest()
            manifest = prepare(source, optimized)
            for row in rows:
                self.assertEqual(original[row['source']], hashlib.sha256((source / row['source']).read_bytes()).hexdigest())
                item = manifest[row['slug']]
                if item['brightness_gamma'] == 1:
                    self.assertLessEqual(item['bytes'], item['source_bytes'], 'Unchanged bright images must not grow')
                else:
                    self.assertGreater(item['luminance_mean_after'], item['luminance_mean_before'], 'Do not discard brightness corrections')
                self.assertEqual(item['sha256'], hashlib.sha256((optimized / row['file']).read_bytes()).hexdigest())
            output = root / 'plugin.zip'
            digest = build(optimized, output)
            self.assertIn(digest, output.with_suffix('.zip.sha256').read_text())
            with zipfile.ZipFile(output) as archive:
                self.assertTrue(all(name.startswith('sa-province-covers/') and '..' not in name for name in archive.namelist()))
                self.assertEqual(31, sum(name.endswith('.webp') for name in archive.namelist()))
                self.assertFalse(any('/tests/' in name or 'source/' in name for name in archive.namelist()))
            file = optimized / rows[0]['file']
            file.write_bytes(b'broken')
            with self.assertRaisesRegex(ValueError, 'checksum'):
                build(optimized, root / 'bad.zip')


if __name__ == '__main__':
    unittest.main()
