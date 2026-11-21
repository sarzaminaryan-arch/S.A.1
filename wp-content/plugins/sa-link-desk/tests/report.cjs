// NODE_PATH=<directory containing jsdom@24> node tests/report.cjs
const {JSDOM} = require('jsdom');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const dom = new JSDOM(['start', 'stop', 'download', 'summary', 'progress'].map(x => `<button id="sald-${x}"></button>`).join(''), {url: 'https://test.invalid/wp-admin/', runScripts: 'outside-only'});
const w = dom.window, origin = w.location.origin, parent = `${origin}/province/a/`, county = `${origin}/city/b/`;
w.saLinkDesk = {home: origin, nonce: 'fixture'}; w.ajaxurl = '/wp-admin/admin-ajax.php';
w.fetch = async (url, args) => {
    if (url === w.ajaxurl) return {ok: true, json: async () => ({success: true, data: {last: 1, pages: [{url: parent, type: 'province', members: [county]}, {url: county, type: 'city'}, {url: `${origin}/bad/`, type: 'post'}]}})};
    assert.equal(args.credentials, 'omit'); assert.equal(args.redirect, 'manual');
    if (url.endsWith('/bad/')) return {ok: false, status: 503};
    const target = url === parent ? county : parent;
    return {ok: true, headers: {get: () => 'text/html'}, text: async () => `<html><body><nav><a data-sa-relation href="${target}">nav</a></nav><main><a href="#ref">ref</a><a href="mailto:x@y.z">other</a><a href="//fa.wikipedia.org/wiki/x">wiki</a></main></body></html>`};
};
let blob; w.URL.createObjectURL = b => { blob = b; return 'blob:report'; }; w.URL.revokeObjectURL = () => {}; w.HTMLAnchorElement.prototype.click = () => {};
w.eval(fs.readFileSync(require.resolve('../audit.js'), 'utf8'));
(async () => {
    await w.document.querySelector('#sald-start').onclick(); w.document.querySelector('#sald-download').onclick();
    const text = await new Promise(resolve => { const reader = new w.FileReader(); reader.onload = () => resolve(reader.result); reader.readAsText(blob); });
    const r = JSON.parse(text);
    assert.deepEqual(r.summary.totals, {total: 8, internal: 2, external: 2, fragment: 2, other: 2, wikipedia: 2, main: 6, marked: 2});
    assert.equal(r.summary.errors, 1); assert.equal(r.summary.geoChecked, 2); assert.equal(r.summary.geoIssues, 0); assert.equal(r.incomingInternalOccurrences[county.slice(0,-1)], 1);
    console.log('OK: rendered counts, marked breadcrumb, incoming links, anonymous requests, failed page and JSON export.'); w.close();
})().catch(e => { console.error(e); process.exit(1); });
