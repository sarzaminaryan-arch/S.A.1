(function () {
 'use strict';
 var root = document.querySelector('.sa-pc-admin');
 if (!root || !window.SAProvinceCovers) return;
 var busy = false;
 var progress = root.querySelector('[data-sa-pc-progress]');
 function jobsFor(rows, op) {
  return rows.map(function (row) { return {row: row, op: op, slug: row.dataset.slug, id: row.querySelector('[data-sa-pc-target]').value}; });
 }
 async function run(jobs) {
  if (busy || !jobs.length) return;
  if (jobs.some(function (j) { return j.id === '0'; })) { progress.textContent = 'ابتدا مقالهٔ مقصد هر مورد را انتخاب کنید.'; return; }
  if (!window.confirm('عملیات روی مقصدهای انتخاب‌شده اجرا شود؟ فایل‌های رسانهٔ قبلی حذف نمی‌شوند.')) return;
  busy = true;
  var controls = Array.from(root.querySelectorAll('button,input,select'));
  var states = controls.map(function (node) { return node.disabled; });
  controls.forEach(function (node) { node.disabled = true; });
  var passed = 0, failed = 0;
  try {
   for (var i = 0; i < jobs.length; i++) {
    var job = jobs[i], status = job.row.querySelector('[data-sa-pc-status]');
    status.textContent = 'در حال پردازش…';
    progress.textContent = 'پردازش ' + (i + 1) + ' از ' + jobs.length;
    var body = new URLSearchParams({action: 'sa_pc_run', nonce: SAProvinceCovers.nonce, op: job.op, slug: job.slug, post_id: job.id});
    var response = await fetch(SAProvinceCovers.url, {method: 'POST', credentials: 'same-origin', body: body});
    var data;
    try { data = await response.json(); } catch (error) { throw new Error('پاسخ نامعتبر بود؛ قبل از تکرار، صفحه را تازه و وضعیت مقصد را بررسی کنید.'); }
    if (!data || typeof data.success !== 'boolean') throw new Error('نتیجهٔ عملیات معلوم نیست؛ وضعیت مقصد را بررسی کنید.');
    status.textContent = data.data && data.data.message ? data.data.message : 'پاسخ دریافت شد.';
    status.classList.toggle('sa-pc-error', !data.success);
    if (data.success) passed++; else failed++;
   }
   progress.textContent = 'پایان: ' + passed + ' موفق، ' + failed + ' ناموفق. برای مشاهدهٔ تصاویر فعلی، صفحه را تازه کنید.';
  } catch (error) {
   progress.textContent = 'اجرا متوقف شد؛ نتیجهٔ آخرین درخواست ممکن است ثبت شده باشد. صفحه را تازه و وضعیت را بررسی کنید. ' + error.message;
  } finally {
   controls.forEach(function (node, index) { node.disabled = states[index]; });
   busy = false;
  }
 }
 root.querySelector('[data-sa-pc-all]').addEventListener('click', function () {
  root.querySelectorAll('[data-sa-pc-row]').forEach(function (row) {
   var check = row.querySelector('[data-sa-pc-select]');
   if (!check.disabled) check.checked = row.querySelector('[data-sa-pc-target]').value !== '0';
  });
 });
 root.querySelector('[data-sa-pc-batch]').addEventListener('click', function () {
  var rows = Array.from(root.querySelectorAll('[data-sa-pc-row]')).filter(function (row) { return row.querySelector('[data-sa-pc-select]').checked; });
  if (!rows.length) { progress.textContent = 'موردی انتخاب نشده است.'; return; }
  run(jobsFor(rows, 'apply'));
 });
 root.querySelectorAll('[data-sa-pc-op]').forEach(function (button) {
  button.addEventListener('click', function () { run(jobsFor([button.closest('[data-sa-pc-row]')], button.dataset.saPcOp)); });
 });
})();
