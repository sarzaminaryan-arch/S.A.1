const { loadNodeRuntime } = require('@php-wasm/node');
const { PHP } = require('@php-wasm/universal');
const { copyTree, mkdirP } = require('./vfs');
const path = require('path');
(async () => {
  const script = process.argv[2];
  const id = await loadNodeRuntime(process.env.PHP_VER || '8.1', { emscriptenOptions: { processId: 100000 + (process.pid % 50000) } });
  const php = new PHP(id);
  mkdirP(php, '/ws');
  const theme = process.env.SA_THEME_DIR || path.resolve(__dirname, '../../wp-content/themes/sarzaminaryan-child');
  for (const p of ['data', 'inc']) copyTree(php, path.join(theme, p), '/ws/theme/' + p);
  copyTree(php, script, '/ws/script.php');
  const dir = path.dirname(path.resolve(script));
  for (const f of require('fs').readdirSync(dir)) {
    if (f !== path.basename(script)) copyTree(php, path.join(dir, f), '/ws/' + f);
  }
  const env = { SA_CHILD_DIR: '/ws/theme/' };
  for (const k of Object.keys(process.env)) if (/^SA_/.test(k)) env[k] = process.env[k];
  const res = await php.cli(['php', '/ws/script.php'], { env });
  const out = await res.stdoutText;
  const err = await res.stderrText;
  process.stdout.write(out);
  if (err && err.trim()) process.stderr.write('STDERR:\n' + err + '\n');
  process.exit(await res.exitCode);
})().catch((e) => { console.error('RUNNER ERROR:', e && e.message ? e.message : e); process.exit(99); });
