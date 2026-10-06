const fs = require('fs');
const path = require('path');
const posix = path.posix;
function ensureDir(php, dir) { try { php.mkdir(dir); } catch (e) {} }
function mkdirP(php, dir) {
  const parts = dir.split('/').filter(Boolean);
  let cur = '';
  for (const p of parts) { cur += '/' + p; ensureDir(php, cur); }
}
function copyTree(php, src, dst) {
  const st = fs.statSync(src);
  if (st.isFile()) {
    mkdirP(php, posix.dirname(dst));
    php.writeFile(dst, fs.readFileSync(src));
    return;
  }
  mkdirP(php, dst);
  for (const e of fs.readdirSync(src, { withFileTypes: true })) {
    copyTree(php, path.join(src, e.name), posix.join(dst, e.name));
  }
}
module.exports = { ensureDir, mkdirP, copyTree };
