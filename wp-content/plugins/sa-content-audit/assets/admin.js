(function ($) {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function tone(score) {
    return score >= 80 ? 'good' : (score >= 60 ? 'warn' : 'bad');
  }

  function issueTone(level) {
    return level === 'red' ? 'bad' : (level === 'yellow' ? 'warn' : (level === 'green' ? 'good' : 'info'));
  }

  function metricLabel(key) {
    return {
      words: 'واژه', characters: 'نویسه', paragraphs: 'پاراگراف کامل', headings: 'زیرعنوان H2',
      density: 'تراکم عبارت هدف', internal: 'پیوند داخلی', external: 'پیوند بیرونی', sources: 'دامنهٔ منبع',
      images: 'مجموع تصاویر', missing_alt: 'تصویر بدون alt', avg_sentence: 'میانگین واژه در جمله', meta_length: 'طول توضیحات',
      broken_internal: 'پیوند داخلی به پیش‌نویس', indexability: 'وضعیت خزش', canonical: 'canonical', overlap_count: 'تطابق داخلی محدود'
    }[key] || key;
  }

  function renderResult(result) {
    var percent = Number(result.score || 0);
    var old = result.previous_score;
    var delta = old === null || typeof old === 'undefined' ? '' : '<span class="sa-audit-delta ' + (percent >= Number(old) ? 'is-up' : 'is-down') + '">' + (percent >= Number(old) ? '↑ ' : '↓ ') + Math.abs(percent - Number(old)) + ' نسبت به آخرین ذخیره</span>';
    if (result.saved_previous_score !== null && typeof result.saved_previous_score !== 'undefined' && old !== null && typeof old !== 'undefined') {
      var savedDelta = Number(old) - Number(result.saved_previous_score);
      delta += '<span class="sa-audit-delta ' + (savedDelta >= 0 ? 'is-up' : 'is-down') + '">آخرین ذخیره: ' + (savedDelta > 0 ? '+' : '') + savedDelta + ' نسبت به ذخیرهٔ قبل‌تر</span>';
    }
    var html = '<section class="sa-audit-report">' +
      '<div class="sa-audit-score sa-audit-score--' + tone(percent) + '"><div class="sa-audit-score-number">' + esc(percent) + '<small>/۱۰۰</small></div><div><strong>شاخص کیفیت تحریریه</strong><span>راهنمای داخلی · نه امتیاز گوگل</span>' + delta + '</div></div>' +
      '<div class="sa-audit-metrics">';
    Object.keys(result.metrics || {}).forEach(function (key) {
      var val = result.metrics[key];
      if (key === 'keyword') val = val || 'تنظیم نشده';
      if (key === 'density') val = val + '%';
      html += '<div class="sa-audit-metric"><span>' + esc(metricLabel(key)) + '</span><strong>' + esc(val) + '</strong></div>';
    });
    html += '</div><h3 class="sa-audit-section-title">امتیازهای بخش‌ها</h3><div class="sa-audit-sections">';
    Object.keys(result.sections || {}).forEach(function (key) {
      var section = result.sections[key];
      var ratio = Math.round(100 * Number(section.score) / Number(section.weight || 1));
      html += '<div class="sa-audit-section"><div class="sa-audit-section-head"><span>' + esc(section.label) + '</span><strong>' + esc(section.score) + ' / ' + esc(section.weight) + ' (' + ratio + '٪)</strong></div><div class="sa-audit-bar"><span class="sa-audit-bar--' + tone(ratio) + '" style="width:' + Math.max(0, Math.min(100, ratio)) + '%"></span></div></div>';
    });
    html += '</div><h3 class="sa-audit-section-title">اقدام‌های پیشنهادی</h3>';
    if (result.issues && result.issues.length) {
      html += '<div class="sa-audit-issues">';
      result.issues.forEach(function (item) {
        html += '<article class="sa-audit-issue sa-audit-issue--' + issueTone(item.level) + '"><div><span class="sa-audit-issue-area">' + esc(item.area) + '</span><strong>' + esc(item.title) + '</strong><p>' + esc(item.detail) + '</p></div></article>';
      });
      html += '</div>';
    } else {
      html += '<div class="sa-audit-all-clear">در این بررسی مورد پرخطری پیدا نشد؛ بازبینی انسانی و صحت‌سنجی اطلاعات همچنان لازم است.</div>';
    }
    if (result.internal_suggestions && result.internal_suggestions.length) {
      var typeNames = { province: 'استان', city: 'شهر', attraction: 'جاذبه', local_food: 'غذای محلی', souvenir: 'سوغات', travel_route: 'مسیر سفر' };
      html += '<div class="sa-audit-suggestions"><strong>صفحه‌های مرتبط پیشنهادی برای پیوند داخلی</strong><ul>';
      result.internal_suggestions.forEach(function (item) {
        html += '<li><span>' + esc(typeNames[item.type] || item.type) + ':</span> <a href="' + esc(item.url) + '" target="_blank" rel="noopener noreferrer">' + esc(item.title) + '</a></li>';
      });
      html += '</ul><small>پیوندی درج نشده؛ ارتباط واقعی هر مورد را پیش از استفاده بررسی کنید.</small></div>';
    }
    if (result.overlaps && result.overlaps.length) {
      html += '<details class="sa-audit-overlaps"><summary>تطابق‌های داخلی احتمالی (اثبات کپی نیست)</summary><ul>';
      result.overlaps.forEach(function (item) { html += '<li>«' + esc(item.sentence) + '» — مشابه در «' + esc(item.title) + '»</li>'; });
      html += '</ul></details>';
    }
    if (result.suggestions && result.suggestions.length) {
      html += '<div class="sa-audit-suggestions"><strong>ایده‌های عبارت از خود متن</strong><div>' + result.suggestions.map(function (word) { return '<span>' + esc(word) + '</span>'; }).join('') + '</div><small>حجم جست‌وجو و رقابت سنجیده نشده است.</small></div>';
    }
    html += '<details class="sa-audit-limits"><summary>روش و محدودیت‌ها</summary><ul>' + (result.notes || []).map(function (note) { return '<li>' + esc(note) + '</li>'; }).join('') + '</ul></details></section>';
    return html;
  }

  function currentEditorData($box) {
    var content = '';
    if (window.wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
      var editor = wp.data.select('core/editor');
      content = editor.getEditedPostContent ? editor.getEditedPostContent() : '';
      return {
        title: editor.getEditedPostAttribute('title') || '',
        content: content,
        excerpt: editor.getEditedPostAttribute('excerpt') || '',
        post_type: editor.getCurrentPostType ? editor.getCurrentPostType() : '',
        focus_keyword: $('#sa_focus_keyword').val() || '',
        seo_title: $('#sa_seo_title').val() || '',
        seo_description: $('#sa_seo_description').val() || ''
      };
    }
    if (window.tinyMCE && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
      content = tinyMCE.get('content').getContent();
    } else {
      content = $('#content').val() || '';
    }
    return {
      title: $('#title').val() || '', content: content,
      excerpt: $('#excerpt').val() || $('#postexcerpt textarea').val() || '',
      post_type: $('#post_type').val() || '',
      focus_keyword: $('#sa_focus_keyword').val() || '',
      seo_title: $('#sa_seo_title').val() || '',
      seo_description: $('#sa_seo_description').val() || ''
    };
  }

  $(document).on('click', '.sa-audit-run', function () {
    var $button = $(this);
    var $box = $button.closest('.sa-audit-editor');
    var $status = $box.find('.sa-audit-loading');
    $button.prop('disabled', true);
    $status.text(SAAudit.labels.working);
    var data = currentEditorData($box);
    data.action = 'sa_audit_analyze';
    data.nonce = SAAudit.editorNonce;
    data.post_id = $box.data('post-id');
    $.post(SAAudit.ajaxUrl, data).done(function (response) {
      if (response && response.success) {
        $box.find('.sa-audit-result').html(renderResult(response.data));
        $status.text('تحلیل تازه شد.');
      } else {
        $status.text(response && response.data && response.data.message ? response.data.message : SAAudit.labels.error);
      }
    }).fail(function () { $status.text(SAAudit.labels.error); }).always(function () { $button.prop('disabled', false); });
  });

  function statusFa(status) {
    return { publish: 'منتشرشده', draft: 'پیش‌نویس', pending: 'در انتظار', future: 'زمان‌بندی‌شده', private: 'خصوصی' }[status] || status;
  }

  function renderRow(row) {
    var t = tone(Number(row.score));
    var typeNames = { post: 'مقاله', page: 'برگه', province: 'استان', city: 'شهر', attraction: 'جاذبه', local_food: 'غذای محلی', souvenir: 'سوغات', travel_route: 'مسیر سفر' };
    return '<tr><td><a href="' + esc(row.edit_url) + '">' + esc(row.title || '(بدون عنوان)') + '</a></td><td>' + esc(typeNames[row.type] || row.type) + '</td><td>' + esc(statusFa(row.status)) + '</td><td>' + esc(row.words) + '</td><td><span class="sa-audit-pill sa-audit-pill--' + t + '">' + esc(row.score) + ' / ۱۰۰</span></td><td>' + esc(row.issues) + '</td></tr>';
  }

  $(document).on('click', '.sa-audit-scan-all', function () {
    var $button = $(this);
    var $root = $button.closest('.sa-audit-dashboard');
    var $progress = $root.find('.sa-audit-progress');
    var $bar = $progress.find('.sa-audit-progress-track span');
    var $tbody = $root.find('.sa-audit-table tbody').empty();
    var offset = 0;
    var total = null;
    var aggregate = { count: 0, score: 0, words: 0, red: 0 };
    $button.prop('disabled', true).text('در حال بررسی…');
    $progress.prop('hidden', false);
    $root.find('.sa-audit-summary').prop('hidden', true);
    function batch() {
      $.post(SAAudit.ajaxUrl, { action: 'sa_audit_batch', nonce: SAAudit.nonce, offset: offset }).done(function (response) {
        if (!response || !response.success) { finish(true); return; }
        var data = response.data;
        total = data.total;
        (data.rows || []).forEach(function (row) {
          $tbody.append(renderRow(row));
          aggregate.count++;
          aggregate.score += Number(row.score);
          aggregate.words += Number(row.words);
          aggregate.red += Number(row.issues);
        });
        offset = Number(data.offset || 0);
        var pct = total ? Math.min(100, Math.round(100 * offset / total)) : 100;
        $bar.css('width', pct + '%');
        $progress.find('.sa-audit-progress-label').text('بررسی ' + offset + ' از ' + total + ' محتوا · ' + pct + '٪');
        if (!data.done) {
          batch();
        } else {
          finish();
        }
      }).fail(function () { finish(true); });
    }
    function finish(failed) {
      if (failed) $progress.find('.sa-audit-progress-label').text('خطا در دریافت بخش بعدی؛ صفحه را تازه کنید و دوباره اجرا کنید.');
      else if (!aggregate.count) $tbody.html('<tr><td colspan="6">' + esc(SAAudit.labels.empty) + '</td></tr>');
      else {
        var avg = Math.round(aggregate.score / aggregate.count);
        $root.find('.sa-audit-summary').html(
          '<div class="sa-audit-summary-card"><span>موارد بررسی‌شده</span><strong>' + aggregate.count + '</strong></div>' +
          '<div class="sa-audit-summary-card sa-audit-summary-card--' + tone(avg) + '"><span>میانگین شاخص داخلی</span><strong>' + avg + '٪</strong></div>' +
          '<div class="sa-audit-summary-card"><span>مجموع واژه‌ها</span><strong>' + aggregate.words.toLocaleString('fa-IR') + '</strong></div>' +
          '<div class="sa-audit-summary-card"><span>پیشنهادهای قابل بازبینی</span><strong>' + aggregate.red + '</strong></div>'
        ).prop('hidden', false);
      }
      $button.prop('disabled', false).text('شروع بررسی کل محتوا');
    }
    batch();
  });
})(jQuery);
