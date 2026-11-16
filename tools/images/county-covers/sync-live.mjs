import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const metadata = JSON.parse(fs.readFileSync(path.join(HERE, 'metadata.json'), 'utf8'));
const outputDir = path.join(HERE, 'outputs');
const site = (process.env.SA_SITE_URL || '').replace(/\/+$/, '');
const user = process.env.SA_USER || '';
const password = (process.env.SA_APP_PASSWORD || '').replace(/\s/g, '');
const ownerApproved = process.env.OWNER_APPROVED === 'yes';
const force = process.env.FORCE_REPLACE === 'yes';
const maxItems = Number(process.env.MAX_ITEMS || 0);
const sleepMs = Number(process.env.REQUEST_DELAY_MS || 250);
if (!site || !user || !password) throw new Error('SA_SITE_URL, SA_USER and SA_APP_PASSWORD are required.');
if (!ownerApproved) throw new Error('Refusing live writes: OWNER_APPROVED must be yes.');
if (!/^https:\/\//i.test(site)) throw new Error('SA_SITE_URL must use HTTPS.');
if (!Number.isInteger(maxItems) || maxItems < 0) throw new Error('MAX_ITEMS must be a non-negative integer.');

const token = Buffer.from(`${user}:${password}`).toString('base64');
const base = `${site}/wp-json/sa-agent/v1`;
async function api(method, endpoint, body) {
  const response = await fetch(`${base}${endpoint}`, {
    method,
    headers: { Authorization: `Basic ${token}`, 'X-SA-Authorization': `Basic ${token}`, Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(120_000),
  });
  const text = await response.text();
  let data;
  try { data = JSON.parse(text); } catch { throw new Error(`${method} ${endpoint} returned non-JSON HTTP ${response.status}: ${text.slice(0, 300)}`); }
  if (!response.ok) throw new Error(`${method} ${endpoint} HTTP ${response.status}: ${JSON.stringify(data).slice(0, 800)}`);
  return data;
}
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const ping = await api('GET', '/ping');
if (ping.theme !== '2.22.90') throw new Error(`Live child-theme version is ${ping.theme}; expected 2.22.90 before county media rollout.`);
if (!ping.can_write || !ping.can_admin) throw new Error('The configured WordPress account must have edit_others_posts and manage_options capabilities for the guarded media/cache workflow.');

const first = await api('GET', '/posts?type=city&status=publish&per_page=100&page=1');
const freshRows = [...first.items];
for (let page = 2; page <= Number(first.pages); page++) {
  const batch = await api('GET', `/posts?type=city&status=publish&per_page=100&page=${page}`);
  freshRows.push(...batch.items);
}
if (freshRows.length !== Number(first.total) || freshRows.length !== metadata.expected_live_count) {
  throw new Error(`Fresh city count ${freshRows.length}, API total ${first.total}, metadata count ${metadata.expected_live_count}; refusing mismatched rollout.`);
}
const metaBySlug = new Map(metadata.counties.map((row) => [row.slug, row]));
const seenSlugs = new Set();
for (const row of freshRows) {
  const info = metaBySlug.get(row.slug);
  if (!info || Number(row.id) !== Number(info.id) || row.type !== 'city' || row.status !== 'publish') {
    throw new Error(`Live identity mismatch for ${row.slug || '(missing slug)'}: id=${row.id} type=${row.type} status=${row.status}`);
  }
  if (!row.modified_gmt) throw new Error(`Missing modified_gmt for ${row.id}/${row.slug}`);
  if (seenSlugs.has(row.slug)) throw new Error(`Fresh paginated list contains duplicate slug: ${row.slug}`);
  seenSlugs.add(row.slug);
}
if (seenSlugs.size !== metadata.counties.length) throw new Error(`Fresh listing has ${seenSlugs.size} unique slugs; expected ${metadata.counties.length}.`);

const manifest = maxItems > 0 ? metadata.counties.slice(0, maxItems) : metadata.counties;
const liveBySlug = new Map(freshRows.map((row) => [row.slug, row]));
const reportPath = path.join(outputDir, `sync-report-${new Date().toISOString().replace(/[:.]/g, '-')}.json`);
const report = { started_at: new Date().toISOString(), theme: ping.theme, total_live: freshRows.length, planned: manifest.length, force, attached: [], skipped_existing: [], skipped_changed: [], failed: [], file: reportPath };
fs.mkdirSync(outputDir, { recursive: true });
function saveReport() { fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`); }
saveReport();

for (let index = 0; index < manifest.length; index++) {
  const info = manifest[index];
  const listed = liveBySlug.get(info.slug);
  const imagePath = path.join(outputDir, `${info.slug}.webp`);
  try {
    if (!fs.existsSync(imagePath)) throw new Error(`Rendered cover missing: ${imagePath}`);
    if (listed.featured_media > 0 && !listed.featured_media_missing && !force) {
      report.skipped_existing.push({ id: listed.id, slug: listed.slug, featured_media: listed.featured_media });
      saveReport();
      continue;
    }
    const current = await api('GET', `/post/${listed.id}`);
    if (Number(current.id) !== Number(listed.id) || current.type !== 'city' || current.status !== 'publish' || current.slug !== info.slug) {
      throw new Error(`Post identity/status changed before write: id=${listed.id} slug=${info.slug}`);
    }
    if (current.modified_gmt !== listed.modified_gmt) {
      report.skipped_changed.push({ id: listed.id, slug: info.slug, listed_modified_gmt: listed.modified_gmt, current_modified_gmt: current.modified_gmt });
      console.warn(`Skip stale ${info.slug}: modified_gmt changed since fresh listing.`);
      saveReport();
      continue;
    }
    const existingValid = Number(current.featured_media || 0) > 0 && current.featured_media_missing !== true;
    if (existingValid && !force) {
      report.skipped_existing.push({ id: current.id, slug: current.slug, featured_media: current.featured_media });
      saveReport();
      continue;
    }
    const bytes = fs.readFileSync(imagePath);
    if (bytes.length > 3 * 1024 * 1024) throw new Error(`${info.slug} WebP exceeds the 3 MB REST limit.`);
    const media = await api('POST', '/media', {
      filename: `county-${info.slug}.webp`,
      data_base64: bytes.toString('base64'),
      title: `کارت سایبری شهرستان ${info.name_fa}`,
      alt: `تصویر شاخص شهرستان ${info.name_fa} در استان ${info.province_fa}`,
      post_parent: Number(info.id),
      cover_key: `county-cyber-v1:${info.slug}`,
    });
    if (!Number.isInteger(Number(media.id)) || Number(media.id) <= 0) throw new Error(`Media endpoint returned invalid attachment id for ${info.slug}.`);
    const update = await api('POST', `/post/${info.id}`, {
      expected_modified_gmt: current.modified_gmt,
      meta: { _thumbnail_id: Number(media.id) },
      reason: 'دستور مالک: اتصال تصویر شاخص سایبری شهرستان پس از پشتیبان‌گیری',
    });
    if (!Number(update.backup_id)) throw new Error(`Post update did not report a pre-change backup for ${info.slug}.`);
    report.attached.push({ id: Number(info.id), slug: info.slug, attachment_id: Number(media.id), reused_attachment: Boolean(media.reused), backup_id: Number(update.backup_id), modified_gmt: update.modified_gmt });
    console.log(`[${index + 1}/${manifest.length}] attached ${info.slug}: media=${media.id}, backup=${update.backup_id}`);
    saveReport();
    if (sleepMs > 0) await wait(sleepMs);
  } catch (error) {
    report.failed.push({ id: listed?.id, slug: info.slug, error: String(error) });
    console.error(`Stopped at ${info.slug}; the report includes all earlier completed writes.`);
    saveReport();
    break;
  }
}
try { report.cache_purge = await api('POST', '/cache-purge', { scope: 'all' }); }
catch (error) { report.cache_purge = { error: String(error) }; report.failed.push({ phase: 'cache-purge', error: String(error) }); }
report.finished_at = new Date().toISOString();
saveReport();
console.log(JSON.stringify({ report: report.file, attached: report.attached.length, skipped_existing: report.skipped_existing.length, skipped_changed: report.skipped_changed.length, failures: report.failed.length, cache_purge: report.cache_purge }, null, 2));
if (report.failed.length) process.exitCode = 1;
