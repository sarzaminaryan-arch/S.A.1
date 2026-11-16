import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../../..');
const REGISTRY = path.join(ROOT, 'docs/live-site/registry/live-articles.json');
const PROVINCES = path.join(ROOT, 'wp-content/themes/sarzaminaryan-child/data/provinces.php');
const RECONCILIATION = path.join(HERE, 'live-reconciliation.json');
const OUTPUT = path.join(HERE, 'metadata.json');

const provinceSource = fs.readFileSync(PROVINCES, 'utf8');
const provinceRows = [...provinceSource.matchAll(/array\s*\(\s*'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'\s*,\s*'en'\s*=>\s*'([^']+)'/g)]
  .map(([, slug, name, en]) => ({ slug, name, en }));
if (provinceRows.length !== 31) throw new Error(`Expected 31 provinces, found ${provinceRows.length}`);

function letters(value) { return value.toUpperCase().replace(/[^A-Z]/g, ''); }
function consonants(value) { return letters(value).replace(/[AEIOU]/g, ''); }
function makeUniqueCodes(rows, getName, width, preferConsonants) {
  const used = new Set();
  return rows.map((row) => {
    const name = getName(row);
    const cons = consonants(name);
    const alpha = letters(name);
    const initial = preferConsonants && cons.length >= width ? cons.slice(0, width) : alpha.slice(0, width);
    const candidates = [initial];
    for (let i = 0; i <= alpha.length - width; i++) candidates.push(alpha.slice(i, i + width));
    for (const a of alpha) for (const b of alpha) for (const c of alpha) if (width === 3) candidates.push(a + b + c);
    if (width === 2) for (const a of alpha) for (const b of alpha) candidates.push(a + b);
    const code = candidates.find((candidate) => candidate.length === width && !used.has(candidate));
    if (!code) throw new Error(`Unable to assign unique ${width}-letter code to ${name}`);
    used.add(code);
    return { ...row, code };
  });
}

const codedProvinces = makeUniqueCodes(provinceRows, (p) => p.en, 2, false);
const provinceBySlug = new Map(codedProvinces.map((p) => [p.slug, p]));
const source = JSON.parse(fs.readFileSync(REGISTRY, 'utf8'));
const reconciliation = JSON.parse(fs.readFileSync(RECONCILIATION, 'utf8'));
const excluded = new Map(reconciliation.exclude.map((item) => [item.slug, item]));
const liveCities = source.records.filter((r) => r.post_type === 'city' && r.status === 'publish' && !excluded.has(r.slug));
for (const item of reconciliation.exclude) {
  const sourceRow = source.records.find((r) => r.post_type === 'city' && r.slug === item.slug);
  if (!sourceRow || Number(sourceRow.wp_id) !== Number(item.id)) throw new Error(`Reconciliation exclusion does not match snapshot: ${item.slug}/${item.id}`);
}
for (const item of reconciliation.include) {
  if (liveCities.some((row) => row.slug === item.slug) || !Number.isInteger(item.id) || !item.title || !item.province_slug) {
    throw new Error(`Invalid or duplicate live reconciliation inclusion: ${item.slug}`);
  }
  liveCities.push({
    post_type: 'city', wp_id: item.id, slug: item.slug, status: item.status,
    title: item.title, terms: { province_tax: [item.province_slug] },
  });
}
if (liveCities.length !== 484) throw new Error(`Expected 484 currently published cities after documented reconciliation, found ${liveCities.length}`);

function cleanFa(title) {
  return String(title).replace(/^\s*(?:شهرستان|شهر)\s+/u, '').trim();
}
function cleanEn(slug, provinceSlug) {
  let clean = slug.toLowerCase();
  for (const suffix of ['-county', '-city']) if (clean.endsWith(suffix)) clean = clean.slice(0, -suffix.length);
  if (clean.endsWith(`-${provinceSlug}`)) clean = clean.slice(0, -(provinceSlug.length + 1));
  return clean.split('-').filter(Boolean).map((word) => word[0].toUpperCase() + word.slice(1)).join(' ');
}
const rows = liveCities.map((r) => {
  const provinceSlug = r.terms?.province_tax?.[0];
  const province = provinceBySlug.get(provinceSlug);
  if (!province) throw new Error(`Unknown province for city ${r.slug}: ${provinceSlug}`);
  const nameFa = cleanFa(r.title);
  return {
    id: Number(r.wp_id), slug: r.slug, status: r.status,
    name_fa: nameFa, name_en: cleanEn(r.slug, provinceSlug), province_slug: provinceSlug,
    province_fa: province.name, province_en: province.en, province_code: province.code,
    slogan: `شناخت جغرافیا، تاریخ و زندگی در ${nameFa}`,
  };
});
const groups = new Map();
for (const row of rows) {
  if (!groups.has(row.province_slug)) groups.set(row.province_slug, []);
  groups.get(row.province_slug).push(row);
}
for (const group of groups.values()) {
  const coded = makeUniqueCodes(group, (r) => r.name_en, 3, true);
  coded.forEach((row, i) => { group[i].county_code = row.code; });
}
const unique = new Set(rows.map((r) => `${r.province_slug}:${r.county_code}`));
if (unique.size !== rows.length) throw new Error('County badge-code collision within province');
const output = {
  source_snapshot: source.snapshot_date,
  reconciliation_reviewed_at: reconciliation.reviewed_at,
  reconciliation_evidence: reconciliation.evidence,
  expected_live_count: rows.length,
  provinces: codedProvinces.map(({ slug, name, en, code }) => ({ slug, name_fa: name, name_en: en, code })),
  counties: rows.sort((a, b) => (a.province_slug < b.province_slug ? -1 : a.province_slug > b.province_slug ? 1 : a.slug < b.slug ? -1 : a.slug > b.slug ? 1 : 0)),
};
fs.writeFileSync(OUTPUT, `${JSON.stringify(output, null, 2)}\n`);
console.log(`Wrote ${OUTPUT}: ${rows.length} counties, ${codedProvinces.length} province codes, no collisions.`);
