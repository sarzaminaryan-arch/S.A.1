"""Render the geographic contract in a disposable WordPress/SQLite installation.

Requires curl, tar, PHP CLI with pdo_sqlite & mbstring. No server, credentials,
production DB, or HTTP requests to the site. Only pinned core/plugin downloads.
With --all, render all baseline pages; default is the Fars pilot.
"""
import argparse
import csv
import json
import shutil
import sqlite3
import subprocess
import tempfile
from pathlib import Path

from audit_province_links import ROOT, attraction_records, main as contract
from check_relation_html import Links, check, key

CORE = 'https://codeload.github.com/WordPress/WordPress/tar.gz/ba9e7f97f08a7fbb88fbfa35641bcc230500b37a'  # 6.8.3
SQLITE = 'https://codeload.github.com/WordPress/sqlite-database-integration/tar.gz/5a5c7b99dbc97a4f31c8169d74301a34ba5f6f37'  # 2.2.16


def unpack(url, directory):
    directory.mkdir(parents=True)
    with tempfile.NamedTemporaryFile() as archive:
        subprocess.run(['curl', '--retry', '2', '-fsSL', url, '-o', archive.name], check=True)
        subprocess.run(['tar', '-xzf', archive.name, '--strip-components=1', '-C', directory], check=True)


def install(wp, php):
    unpack(CORE, wp)
    content = wp / 'wp-content'
    plugin = content / 'plugins/sqlite-database-integration'
    unpack(SQLITE, plugin)
    shutil.copyfile(plugin / 'db.copy', content / 'db.php')
    for theme in ('sarzaminaryan', 'sarzaminaryan-child'):
        shutil.copytree(ROOT / 'wp-content/themes' / theme, content / 'themes' / theme)
    (wp / 'wp-config.php').write_text('''<?php
$table_prefix = 'wp_';
define('DB_NAME','fixture'); define('DB_USER','fixture'); define('DB_PASSWORD',''); define('DB_HOST','localhost');
define('DB_CHARSET','utf8mb4'); define('DB_COLLATE',''); define('WP_HOME','https://sarzaminaryan.ir'); define('WP_SITEURL',WP_HOME);
define('WP_HTTP_BLOCK_EXTERNAL',true); define('DISABLE_WP_CRON',true); define('AUTOMATIC_UPDATER_DISABLED',true);
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'sarzaminaryan.ir'; $_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require __DIR__.'/wp-settings.php';
''')
    (wp / 'setup.php').write_text('''<?php
define('WP_INSTALLING',true); require __DIR__.'/wp-load.php'; require_once ABSPATH.'wp-admin/includes/upgrade.php';
add_filter('pre_wp_mail','__return_true');
wp_install('Fixture','fixture','fixture@example.invalid',0,'',wp_generate_password());
update_option('permalink_structure','/%postname%/'); switch_theme('sarzaminaryan-child');
''')
    subprocess.run([php, 'setup.php'], cwd=wp, check=True)
    (wp / 'render.php').write_text('''<?php
[$_, $id, $type, $slug] = $argv; $_SERVER['REQUEST_URI'] = "/$type/$slug/"; require __DIR__.'/wp-load.php';
$wp_the_query = $wp_query = new WP_Query(['post_type'=>$type, 'p'=>(int)$id]);
if (!$wp_query->is_singular($type)) { throw new RuntimeException('missing fixture'); }
include get_stylesheet_directory()."/single-$type.php";
''')


def seed(wp, all_pages):
    # Use the archived inventory and relation records, NOT the expected CSV to
    # construct links. The actual theme must derive the contract independently.
    records = json.loads((ROOT / 'docs/live-site/registry/live-articles.json').read_text())['records']
    work = json.loads((ROOT / 'tools/wp-agent/template_worklist.json').read_text())
    provinces = {r['slug']: r for r in records if r['post_type'] == 'province' and r['status'] == 'publish'
                 and (all_pages or r['slug'] == 'fars')}
    cities = {r['id']: r for r in work if r['province'] in provinces and r['status'] == 'publish'}
    pages = [(r['wp_id'], 'province', r['slug'], r['title'], {}) for r in provinces.values()]
    pages += [(i, 'city', r['slug'], 'شهرستان ' + r['name'], {'sa_province_id': provinces[r['province']]['wp_id']})
              for i, r in cities.items()]
    pages += [(r['wp_id'], 'attraction', r['slug'], r['title'], {'sa_city_id': r['relations']['city_id'],
               'sa_province_id': r['relations']['province_id']}) for r in attraction_records(records) if r['relations'].get('city_id') in cities]
    db = wp / 'wp-content/database/.ht.sqlite'
    assert db.is_file(), 'installer failed to create disposable SQLite database'
    snippet = '<p><a href="/city/shiraz/">شیراز</a> و <a href="/province/fars/">استان فارس</a></p>'
    with sqlite3.connect(db) as conn:
        for i, kind, slug, title, meta in pages:
            body = snippet
            if slug == 'eram-garden' and kind == 'attraction':
                body += '<p><a href="/attraction/vakil-complex/">بازار وکیل</a> و <a href="/attraction/shah-cheragh/">شاهچراغ</a></p>'
            if i == 323:
                meta.update({'sa_cty_population': '۱٬۸۶۹٬۰۰۱', 'sa_cty_census_year': '۱۳۹۵'})
                body += (ROOT / 'tools/wp-agent/outbox/20261117-replace-shiraz/shiraz-323.html').read_text()
            conn.execute('INSERT INTO wp_posts (ID,post_type,post_name,post_title,post_content,post_date,post_modified,post_date_gmt) '
                         'VALUES (?,?,?,?,?,?,?,?)', (i, kind, slug, title, body, *['2026-11-17 12:00:00'] * 3))
            conn.executemany('INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES (?,?,?)',
                             [(i, k, v) for k, v in meta.items()])
    return pages


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--all', action='store_true')
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='sa-contract-') as tmp:
        wp, mirror = Path(tmp) / 'wordpress', Path(tmp) / 'html'
        install(wp, args.php)
        pages = seed(wp, args.all)
        csv_path = ROOT / 'docs/live-site/province-links/fars-links.csv'
        if args.all:
            csv_path = Path(tmp) / 'all.csv'
            contract(csv_path, write_report=False)
        with csv_path.open(encoding='utf-8-sig', newline='') as f:
            sources = {row['مبدأ'] for row in csv.DictReader(f)}
        for i, kind, slug, _, _ in pages:
            if f'https://sarzaminaryan.ir/{kind}/{slug}/' not in sources:
                continue  # Still seed redirect sources so they can expose erroneous inbound links.
            directory = mirror / kind / slug
            directory.mkdir(parents=True)
            html = subprocess.check_output([args.php, 'render.php', str(i), kind, slug], cwd=wp)
            if b'</html>' not in html or b'Fatal error:' in html or b'WordPress database error' in html:
                raise RuntimeError(f'incomplete render: {kind}/{slug}')
            if kind == 'attraction' and slug == 'eram-garden':
                targets = [key(f'https://sarzaminaryan.ir/attraction/{s}/') for s in ('vakil-complex', 'shah-cheragh')]
                seen = Links('https://sarzaminaryan.ir/attraction/eram-garden/', targets)
                seen.feed(html.decode('utf-8'))
                if any(seen.article[t] != 1 for t in targets):
                    raise RuntimeError('Eram: card and body must share one link per destination')
            if i == 323 and not all(label.encode() in html for label in ('۱٬۸۶۹٬۰۰۰ نفر', 'سرشماری رسمی ۱۳۹۵')):
                raise RuntimeError('Shiraz: incorrect localized population or census year')
            (directory / 'index.html').write_bytes(html)
        problems = check(csv_path, mirror)
        print(f'{len(sources)} expected WordPress pages, {len(problems)} discrepancies')
        for problem in problems:
            print(problem)
        return bool(problems)


if __name__ == '__main__':
    raise SystemExit(main())
