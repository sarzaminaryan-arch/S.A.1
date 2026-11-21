/* Runs only on Tools > Link statistics. Public requests carry no admin credentials. */
(() => {
    'use strict';
    const el = name => document.getElementById(`sald-${name}`);
    const start = el('start'), stop = el('stop'), download = el('download');
    const origin = new URL(saLinkDesk.home).origin;
    const key = href => href.replace(/\/$/, '');
    const counts = () => ({total: 0, internal: 0, external: 0, fragment: 0, other: 0, wikipedia: 0, main: 0, marked: 0});
    let report, halted, active;

    function inspect(html, source, expected) {
        // A detached template is inert: images, iframes and scripts never execute or load.
        const template = document.createElement('template');
        template.innerHTML = html;
        const root = template.content, baseTag = root.querySelector('base[href]');
        const base = baseTag ? new URL(baseTag.getAttribute('href'), source).href : source;
        const byURL = new Map(), geo = new Map(), article = new Map(), totals = counts();
        for (const a of root.querySelectorAll('a[href]')) {
            const raw = a.getAttribute('href').trim();
            let url;
            try { url = new URL(raw, base); } catch (_) { /* malformed URL */ }
            const kind = !raw || !url || !/^https?:$/.test(url.protocol) ? 'other' :
                url.origin !== origin ? 'external' : raw.startsWith('#') || (url.hash && url.href.split('#')[0] === source) ? 'fragment' : 'internal';
            const href = url ? url.href : raw;
            const main = !!a.closest('main'), marked = a.hasAttribute('data-sa-relation');
            const bucket = `${kind}:${href}`;
            const row = byURL.get(bucket) || {href, kind, count: 0, main: 0, marked: 0};
            row.count++; row.main += +main; row.marked += +marked; byURL.set(bucket, row);
            totals.total++; totals[kind]++; totals.main += +main; totals.marked += +marked;
            if (url && /(^|\.)wikipedia\.org\.?$/i.test(url.hostname) && kind === 'external') totals.wikipedia++;
            if (expected && url && (main || marked) && expected.has(key(href))) {
                const dest = key(href); article.set(dest, (article.get(dest) || 0) + 1);
            }
            if (marked && url && /\/(city|province)\/[^/]+\/?$/.test(url.pathname) && !/\/attraction\//.test(new URL(source).pathname)) {
                const dest = key(href); geo.set(dest, (geo.get(dest) || 0) + 1);
            }
        }
        const issues = [];
        if (expected) {
            for (const dest of new Set([...expected, ...geo.keys()])) {
                const want = +expected.has(dest), found = geo.get(dest) || 0;
                const inArticle = article.get(dest) || 0;
                if (want !== found || (want && inArticle !== 1)) issues.push({dest, expected: want, marked: found, inArticle});
            }
        }
        return {totals, destinations: Array.from(byURL.values()), geoIssues: expected ? issues : null};
    }

    function summarize() {
        const totals = counts(), incoming = new Map(), unique = new Set();
        let ok = 0, errors = 0, geoIssues = 0, geoChecked = 0;
        for (const p of report.pages) {
            if (p.error) { errors++; continue; }
            ok++;
            for (const k of Object.keys(totals)) totals[k] += p.totals[k];
            if (p.geoIssues !== null) geoChecked++;
            geoIssues += (p.geoIssues || []).length;
            for (const d of p.destinations) {
                unique.add(d.href);
                if (d.kind !== 'internal') continue;
                const u = new URL(d.href); u.hash = ''; const dest = key(u.href);
                incoming.set(dest, (incoming.get(dest) || 0) + d.count);
            }
        }
        report.summary = {ok, errors, unscanned: report.inventory - report.pages.length, totals, uniqueDestinations: unique.size, geoChecked, geoIssues};
        report.incomingInternalOccurrences = Object.fromEntries(incoming);
        el('summary').textContent = `موفق: ${ok} | خطا: ${errors} | بررسی‌نشده: ${report.summary.unscanned}\nکل پیوندها: ${totals.total} | داخلی: ${totals.internal} | خارجی: ${totals.external} (ویکی‌پدیا: ${totals.wikipedia}) | پرش در صفحه: ${totals.fragment} | سایر: ${totals.other}\nمقصد یکتا: ${unique.size} | در بخش اصلی: ${totals.main} | اختلاف پیوندهای جغرافیایی در ${geoChecked} صفحهٔ قابل‌تطبیق: ${geoIssues}`;
    }

    start.onclick = async () => {
        halted = false; start.disabled = download.disabled = true; stop.disabled = false;
        el('summary').textContent = ''; el('progress').textContent = 'در حال دریافت فهرست صفحه‌ها…';
        report = {version: 1, site: origin, started: new Date().toISOString(), inventory: 0, inventoryComplete: false, pages: []};
        try {
            if (origin !== location.origin) throw Error('دامنهٔ پیشخوان و سایت یکسان نیست.');
            const inventory = new Map(), graph = new Map();
            for (let page = 1; !halted; page++) {
                const r = await fetch(ajaxurl, {method: 'POST', body: new URLSearchParams({action: 'sa_link_inventory', _ajax_nonce: saLinkDesk.nonce, page})});
                if (!r.ok) throw Error(`inventory: HTTP ${r.status}`);
                const result = await r.json(); if (!result.success) throw Error('inventory');
                for (const p of result.data.pages) {
                    inventory.set(p.url, p.type);
                    if (!Array.isArray(p.members)) continue;
                    const members = p.members.map(key), parent = key(p.url);
                    graph.set(parent, new Set(members));
                    for (const m of members) graph.set(m, new Set([parent, ...members.filter(x => x !== m)]));
                }
                report.inventory = inventory.size;
                if (page >= result.data.last) { report.inventoryComplete = true; break; }
            }
            for (const [url, type] of inventory) {
                if (halted) break;
                el('progress').textContent = `${report.pages.length + 1} / ${inventory.size} — ${url}`;
                active = new AbortController(); const timer = setTimeout(() => active.abort(), 20000);
                try {
                    if (new URL(url).origin !== origin) throw Error('نشانی خارج از سایت');
                    const r = await fetch(url, {credentials: 'omit', redirect: 'manual', cache: 'no-store', referrerPolicy: 'no-referrer', signal: active.signal});
                    if (!r.ok) throw Error(r.type === 'opaqueredirect' ? 'redirect' : `HTTP ${r.status}`);
                    if (!/text\/html/i.test(r.headers.get('Content-Type') || '')) throw Error('not HTML');
                    const html = await r.text();
                    if (!/<\/html\s*>/i.test(html)) throw Error('incomplete HTML');
                    report.pages.push({url, type, ...inspect(html, url, graph.get(key(url)))});
                } catch (e) { if (!halted) report.pages.push({url, type, error: String(e)}); }
                finally { clearTimeout(timer); active = null; }
                if (!halted) await new Promise(resolve => setTimeout(resolve, 250));
            }
            el('progress').textContent = halted ? 'متوقف شد؛ گزارش ناقص است.' : 'پایان پیمایش. صفحه‌های خطادار در گزارش مشخص‌اند.';
        } catch (e) { report.error = String(e); el('progress').textContent = 'گزارش ناقص: ' + e; }
        finally { report.finished = new Date().toISOString(); summarize(); start.disabled = download.disabled = false; stop.disabled = true; }
    };
    stop.onclick = () => { halted = true; if (active) active.abort(); };
    download.onclick = () => {
        const obj = URL.createObjectURL(new Blob([JSON.stringify(report)], {type: 'application/json'}));
        const a = document.createElement('a'); a.href = obj; a.download = `sa-links-${report.started.slice(0, 10)}.json`; a.click();
        setTimeout(() => URL.revokeObjectURL(obj), 1000);
    };
})();
