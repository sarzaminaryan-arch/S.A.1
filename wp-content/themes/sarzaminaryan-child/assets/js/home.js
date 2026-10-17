/* English terminal-style counters. SSR stays readable with JS disabled. */
(function () {
 'use strict';
 var root = document.getElementById('sa-home');
 if (!root) return;
 var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 var formatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
 function count(node) {
  if (node.dataset.counted) return;
  node.dataset.counted = '1';
  var target = Math.max(0, Number(node.dataset.n) || 0);
  var suffix = node.dataset.s || '';
  if (calm || !window.requestAnimationFrame) {
   node.textContent = formatter.format(target) + suffix;
   return;
  }
  var started;
  function frame(now) {
   if (started === undefined) started = now;
   var progress = Math.min((now - started) / 1100, 1);
   node.textContent = formatter.format(Math.round(target * (1 - Math.pow(1 - progress, 3)))) + suffix;
   if (progress < 1) window.requestAnimationFrame(frame);
  }
  window.requestAnimationFrame(frame);
 }
 var stats = root.querySelector('.sa-stats');
 if (stats && 'IntersectionObserver' in window) {
  var observer = new IntersectionObserver(function (entries) {
   entries.forEach(function (entry) {
    stats.classList.toggle('is-offscreen', !entry.isIntersecting);
    if (entry.isIntersecting) stats.querySelectorAll('[data-n]').forEach(count);
   });
  }, { threshold: 0.15 });
  observer.observe(stats);
 } else {
  root.querySelectorAll('[data-n]').forEach(count);
 }
 // Pause motion in background tabs, never pause lower sections when the hero exits.
 function visibility() { root.classList.toggle('is-paused', document.hidden); }
 document.addEventListener('visibilitychange', visibility);
 visibility();
})();
