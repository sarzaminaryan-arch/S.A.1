/* Run with node. No browser packages, network, WordPress or build step needed. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/home.js'), 'utf8');
function setup({reduced = false, observer = true, raf = true, missing = false} = {}) {
 const queue = [], observed = [], listeners = {};
 function classes() { const values = new Set(); return {toggle(name, on) { if (on) values.add(name); else values.delete(name); }, contains(name) { return values.has(name); }}; }
 const nodes = [31, 483, 12345, 67].map((n, i) => ({dataset: {n: String(n), s: i === 1 ? '+' : ''}, textContent: new Intl.NumberFormat('en-US').format(n) + (i === 1 ? '+' : '')}));
 const stats = {classList: classes(), querySelectorAll() { return nodes; }};
 const root = {classList: classes(), querySelector(selector) { assert.equal(selector, '.sa-stats'); return stats; }, querySelectorAll() { return nodes; }};
 let onIntersection;
 class Observer { constructor(callback, options) { onIntersection = callback; assert.equal(options.threshold, 0.15); } observe(node) { observed.push(node); }}
 const document = {hidden: false, getElementById(id) { assert.equal(id, 'sa-home'); return missing ? null : root; }, addEventListener(name, fn) { listeners[name] = fn; }};
 const window = {matchMedia(query) { assert.equal(query, '(prefers-reduced-motion: reduce)'); return {matches: reduced}; }};
 if (observer) window.IntersectionObserver = Observer;
 if (raf) window.requestAnimationFrame = fn => queue.push(fn);
 const context = {window, document, Intl, Math, Number};
 if (observer) context.IntersectionObserver = Observer;
 vm.runInNewContext(source, context);
 return {nodes, stats, root, document, queue, observed, listeners, intersect(visible) { onIntersection([{isIntersecting: visible}]); }, flush() {
  for (const time of [0, 275, 550, 825, 1100]) { queue.splice(0).forEach(fn => fn(time)); }
 }};
}
const env = setup();
assert.deepEqual(env.observed, [env.stats], 'Only stats are observed, not the hero');
assert.equal(env.queue.length, 0, 'Counters wait for scroll visibility');
env.intersect(false);
assert.ok(env.stats.classList.contains('is-offscreen'));
env.intersect(true);
assert.ok(!env.stats.classList.contains('is-offscreen'));
env.flush();
assert.deepEqual(env.nodes.map(n => n.textContent), ['31', '483+', '12,345', '67']);
assert.equal(env.queue.length, 0, 'Animation stops at the exact totals');
env.intersect(false); env.intersect(true);
assert.equal(env.queue.length, 0, 'Reentering viewport cannot restart or double-count');
env.document.hidden = true; env.listeners.visibilitychange(); assert.ok(env.root.classList.contains('is-paused'));
env.document.hidden = false; env.listeners.visibilitychange(); assert.ok(!env.root.classList.contains('is-paused'));
assert.deepEqual(Object.keys(env.listeners), ['visibilitychange'], 'Search and archive links have no click interception');
const calm = setup({reduced: true}); calm.intersect(true);
assert.equal(calm.queue.length, 0, 'Reduced motion skips count animation');
assert.equal(calm.nodes[2].textContent, '12,345');
const fallback = setup({observer: false}); fallback.flush();
assert.equal(fallback.nodes[2].textContent, '12,345', 'No observer still yields correct English totals');
const noRAF = setup({raf: false}); noRAF.intersect(true); assert.equal(noRAF.nodes[1].textContent, '483+');
setup({missing: true});
console.log('PASS: homepage counter totals, English digits, suffix, scroll-once, visibility, reduced motion and fallbacks');
