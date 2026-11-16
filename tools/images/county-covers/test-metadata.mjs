import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const data = JSON.parse(fs.readFileSync(path.join(here, 'metadata.json'), 'utf8'));
assert.equal(data.provinces.length, 31, '31 province records');
assert.equal(data.counties.length, 485, '485 published live city records');
assert.equal(new Set(data.provinces.map((row) => row.slug)).size, 31, 'province slugs are unique');
assert(data.provinces.every((row) => /^[A-Z]{2}$/.test(row.code)), 'province codes are exactly two Latin letters');
assert(data.counties.every((row) => Number.isInteger(row.id) && row.status === 'publish'), 'all county records include live ids and publish status');
assert(data.counties.every((row) => /^[A-Z]{3}$/.test(row.county_code)), 'county codes are exactly three Latin letters');
assert(data.counties.every((row) => !/^\s*(شهرستان|شهر)\s/u.test(row.name_fa)), 'Persian display names have administrative prefixes removed');
assert(data.counties.every((row) => !/(?:-county|-city)$/i.test(row.name_en)), 'English display names omit city/county slug suffixes');
const codes = new Set();
for (const row of data.counties) {
  const key = `${row.province_slug}:${row.county_code}`;
  assert(!codes.has(key), `duplicate county badge code ${key}`);
  codes.add(key);
  assert(row.name_fa.length > 0 && row.name_en.length > 0 && row.slogan.length > 0, `${row.slug} has complete card text`);
}
const longName = data.counties.find((row) => row.name_fa === 'شاهین‌شهر و میمه');
assert(longName, 'longest Persian display name is covered by the registry');
assert.equal(longName.slug, 'shahin-shahr-meymeh');
const alborz = data.counties.find((row) => row.slug === 'alborz-qazvin');
assert.equal(alborz?.name_en, 'Alborz', 'province slug disambiguator is omitted from display name');
const bushehr = data.counties.find((row) => row.slug === 'bushehr-county');
assert.equal(bushehr?.name_en, 'Bushehr', 'county suffix disambiguator is omitted from display name');
console.log('PASS: 31 provinces, 485 live counties, unique badge codes, clean names, neutral slogans.');
