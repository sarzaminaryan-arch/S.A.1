(function () {
  'use strict';

  const start = document.getElementById('sa-audit-crawl-start');
  const cancel = document.getElementById('sa-audit-crawl-cancel');
  const status = document.getElementById('sa-audit-crawl-status');
  if (!start || !cancel || !status || !window.saAuditCrawler) return;

  let stopRequested = false;

  function say(message) {
    status.textContent = message;
  }

  function sleep(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  function cleanText(node) {
    return node ? (node.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function countWords(value) {
    const words = (value || '').trim().match(/\S+/gu);
    return words ? words.length : 0;
  }

  function schemaTypes(doc) {
    const found = new Set();
    doc.querySelectorAll('script[type="application/ld+json"]').forEach((script) => {
      try {
        const root = JSON.parse(script.textContent || 'null');
        const walk = (value) => {
          if (!value || typeof value !== 'object') return;
          if (value['@type']) {
            const values = Array.isArray(value['@type']) ? value['@type'] : [value['@type']];
            values.forEach((item) => found.add(String(item)));
          }
          Object.keys(value).forEach((key) => walk(value[key]));
        };
        walk(root);
      } catch (error) {
        found.add('INVALID_JSON_LD');
      }
    });
    return Array.from(found).sort();
  }

  function parseRenderedPage(html, pageUrl, response, elapsedMs, pageType) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const canonical = doc.querySelector('link[rel~="canonical"]');
    const description = doc.querySelector('meta[name="description"]');
    const robots = Array.from(doc.querySelectorAll('meta[name="robots"], meta[name="googlebot"]'))
      .map((node) => ({ name: node.getAttribute('name'), content: node.getAttribute('content') || '' }));
    const headings = {};
    ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach((tag) => {
      headings[tag] = doc.querySelectorAll(tag).length;
    });

    const pageOrigin = new URL(pageUrl).origin;
    const internalTargets = new Set();
    const internalLinkCounts = { province: 0, city: 0, attraction: 0, other: 0 };
    const linkTargets = [];
    doc.querySelectorAll('a[href]').forEach((anchor) => {
      try {
        const target = new URL(anchor.getAttribute('href'), pageUrl);
        if (target.origin !== pageOrigin || !/^https?:$/.test(target.protocol)) return;
        target.hash = '';
        const normalized = target.href.replace(/\/$/, '');
        internalTargets.add(normalized);
        const path = target.pathname;
        if (/\/province\//.test(path)) internalLinkCounts.province += 1;
        else if (/\/city\//.test(path)) internalLinkCounts.city += 1;
        else if (/\/attraction\//.test(path)) internalLinkCounts.attraction += 1;
        else internalLinkCounts.other += 1;
        if (linkTargets.length < 250) {
          linkTargets.push({ url: target.href, anchor: cleanText(anchor).slice(0, 180) });
        }
      } catch (error) {
        // Ignore invalid href values; the page-level parse is still useful.
      }
    });

    const bodyText = cleanText(doc.body);
    const resourceExcerpt = pageType === 'robots'
      ? bodyText.slice(0, 3000)
      : (pageType === 'sitemap'
        ? Array.from(doc.querySelectorAll('loc, a[href]')).slice(0, 100).map((node) => cleanText(node) || node.href || '').filter(Boolean)
        : null);
    const breadcrumbCandidates = Array.from(doc.querySelectorAll('nav[aria-label], .sa-breadcrumbs, .breadcrumbs'));
    const breadcrumbText = breadcrumbCandidates.map(cleanText).filter(Boolean).slice(0, 3);
    const contentRoot = doc.querySelector('.entry-content, .sa-entity__content, main') || doc.body;
    const contentHeadings = {};
    ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach((tag) => {
      contentHeadings[tag] = contentRoot ? contentRoot.querySelectorAll(tag).length : 0;
    });

    return {
      requested_url: pageUrl,
      final_url: response.url,
      status_code: response.status,
      ok: response.ok,
      content_type: response.headers.get('content-type') || '',
      elapsed_ms: elapsedMs,
      resource_excerpt: resourceExcerpt,
      title: cleanText(doc.querySelector('title')),
      meta_description: description ? description.getAttribute('content') || '' : '',
      canonical: canonical ? canonical.href : '',
      robots,
      language: doc.documentElement ? doc.documentElement.getAttribute('lang') || '' : '',
      dir: doc.documentElement ? doc.documentElement.getAttribute('dir') || '' : '',
      body_classes: doc.body ? String(doc.body.className || '').slice(0, 500) : '',
      h1_texts: Array.from(doc.querySelectorAll('h1')).slice(0, 10).map(cleanText),
      heading_counts_document: headings,
      heading_counts_content: contentHeadings,
      body_approx_word_count: countWords(bodyText),
      content_approx_word_count: countWords(cleanText(contentRoot)),
      breadcrumb_text: breadcrumbText,
      json_ld_types: schemaTypes(doc),
      internal_link_counts: internalLinkCounts,
      unique_internal_target_count: internalTargets.size,
      internal_targets: Array.from(internalTargets).slice(0, 250),
      link_targets: linkTargets
    };
  }

  async function getManifest() {
    const form = new URLSearchParams();
    form.set('action', 'sa_audit_exporter_crawl_manifest');
    form.set('nonce', window.saAuditCrawler.nonce);
    const response = await fetch(window.saAuditCrawler.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: form.toString(),
      cache: 'no-store'
    });
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.data || !Array.isArray(payload.data.urls)) {
      throw new Error((payload && payload.data && payload.data.message) || 'ساخت فهرست صفحات ناموفق بود.');
    }
    return payload.data;
  }

  async function crawlOne(item, expectedOrigin) {
    const url = new URL(item.url);
    if (url.origin !== expectedOrigin) {
      return { requested_url: item.url, type: item.type, label: item.label, error: 'دامنه با سایت جاری یکی نیست؛ برای امنیت درخواست نشد.' };
    }
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 15000);
    const started = performance.now();
    try {
      const response = await fetch(url.href, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'manual',
        signal: controller.signal
      });
      if (response.type === 'opaqueredirect' || response.status === 0) {
        return {
          requested_url: item.url,
          type: item.type,
          label: item.label,
          redirect_blocked: true,
          error: 'تغییرمسیر دنبال نشد تا درخواست از همان دامنه خارج نشود.'
        };
      }
      const html = await response.text();
      const row = parseRenderedPage(html, item.url, response, Math.round(performance.now() - started), item.type);
      row.type = item.type;
      row.label = item.label;
      return row;
    } catch (error) {
      return {
        requested_url: item.url,
        type: item.type,
        label: item.label,
        error: error && error.name === 'AbortError' ? 'مهلت پاسخ تمام شد.' : String(error && error.message ? error.message : error)
      };
    } finally {
      window.clearTimeout(timer);
    }
  }

  function downloadJson(payload) {
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'sa-site-rendered-audit-' + new Date().toISOString().slice(0, 10) + '.json';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30000);
  }

  start.addEventListener('click', async () => {
    if (start.disabled) return;
    stopRequested = false;
    start.disabled = true;
    cancel.disabled = false;
    const results = [];
    try {
      say('در حال آماده‌سازی فهرست نمونه‌ها…');
      const manifest = await getManifest();
      const expectedOrigin = new URL(manifest.origin).origin;
      if (window.location.origin !== expectedOrigin) {
        throw new Error('نشانی پیشخوان و دامنهٔ اصلی یکی نیستند. از همان دامنهٔ اصلی وارد پیشخوان شوید و دوباره اجرا کنید.');
      }
      const pages = manifest.urls;
      for (let index = 0; index < pages.length; index += 1) {
        if (stopRequested) break;
        const item = pages[index];
        say('در حال بررسی ' + (index + 1) + ' از ' + pages.length + ': ' + (item.label || item.url));
        results.push(await crawlOne(item, expectedOrigin));
        await sleep(250);
      }
      const payload = {
        export_format: 'sa-rendered-audit-v1',
        generated_utc: new Date().toISOString(),
        site_origin: expectedOrigin,
        sample_strategy: manifest.sample_strategy,
        requested_count: pages.length,
        completed_count: results.length,
        stopped_by_admin: stopRequested,
        notice: 'Rendered public pages fetched from this same site in the administrator browser. No page HTML is uploaded or stored by the plugin.',
        pages: results
      };
      downloadJson(payload);
      say('تمام شد: ' + results.length + ' صفحه بررسی شد. فایل JSON دانلود شد؛ آن را کنار فایل فهرست محتوا نگه دارید.');
    } catch (error) {
      say('خطا: ' + String(error && error.message ? error.message : error));
    } finally {
      start.disabled = false;
      cancel.disabled = true;
    }
  });

  cancel.addEventListener('click', () => {
    stopRequested = true;
    cancel.disabled = true;
    say('درخواست توقف ثبت شد؛ پس از پایان صفحهٔ جاری متوقف می‌شود…');
  });
})();
