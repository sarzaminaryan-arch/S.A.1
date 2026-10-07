const { loadNodeRuntime } = require('@php-wasm/node');
const { PHP } = require('@php-wasm/universal');
const { mkdirP } = require('./vfs');
const fs = require('fs');

(async () => {
  const files = process.argv.slice(2);
  if (!files.length) { console.log('no files'); process.exit(0); }
  const id = await loadNodeRuntime(process.env.PHP_VER || '8.1', { emscriptenOptions: { processId: 100000 + (process.pid % 50000) } });
  const php = new PHP(id);
  mkdirP(php, '/ws/files');
  files.forEach((f, i) => {
    php.writeFile('/ws/files/' + i + '.php', fs.readFileSync(f));
    php.writeFile('/ws/files/' + i + '.name', f);
  });
  php.writeFile('/ws/lint.php', `<?php
$files = glob('/ws/files/*.php');
sort($files, SORT_NATURAL);
$bad = 0;
foreach ($files as $f) {
  $name = trim((string) @file_get_contents(preg_replace('/\\.php$/', '.name', $f)));
  $code = (string) file_get_contents($f);
  try {
    token_get_all($code, TOKEN_PARSE);
    echo "OK: " . $name . "\\n";
  } catch (ParseError $e) {
    $bad++;
    echo "SYNTAX ERROR in " . $name . ": " . $e->getMessage() . "\\n";
  }
}
echo $bad ? ($bad . " FILE(S) FAILED") : ("ALL OK (" . count($files) . " files)");
`);
  const res = await php.cli(['php', '/ws/lint.php']);
  const out = await res.stdoutText;
  const err = await res.stderrText;
  process.stdout.write(out);
  if (err && err.trim()) process.stderr.write('STDERR: ' + err + '\n');
  const code = await res.exitCode;
  process.exit(/FAILED/.test(out) ? 1 : 0);
})().catch((e) => { console.error('LINT RUNNER ERROR:', e && e.message ? e.message : e); process.exit(99); });
