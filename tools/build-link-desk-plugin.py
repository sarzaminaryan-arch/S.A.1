"""Package only the installable WordPress files; omit the development fixtures."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import sys

root = Path(__file__).resolve().parents[1]
slug = 'sa-link-desk'
folder = root / 'wp-content/plugins' / slug
target = Path(sys.argv[1]) if len(sys.argv) > 1 else root / 'release/sa-link-desk-1.0.0.zip'
target.parent.mkdir(parents=True, exist_ok=True)
with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
    for name in (f'{slug}.php', 'admin.php', 'audit.js', 'readme.txt', 'uninstall.php'):
        archive.write(folder / name, f'{slug}/{name}')
print(target)
