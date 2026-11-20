/**
 * Syntax-check the PHP templates of the 2026 refresh (php-parser, dev only).
 *
 *   node tools/design/2026-refresh/lint-php.js
 */
const fs = require('fs');
const path = require('path');
const engine = require('php-parser');

const THEME = path.resolve(__dirname, '../../../wp-content/themes/sarzaminaryan-child');
const targets = [
  'header.php',
  'footer.php',
  'front-page.php',
  'single-city.php',
  'single-province.php',
  'single-attraction.php',
  'inc/design-2026.php',
  'template-parts/2026/experience-modal.php',
];

const parser = new engine({ parser: { extractDoc: true }, ast: { withPositions: true } });
let failed = 0;

for (const relative of targets) {
  const file = path.join(THEME, relative);
  try {
    parser.parseCode(fs.readFileSync(file, 'utf8'), file);
    console.log('OK   ' + relative);
  } catch (error) {
    failed += 1;
    console.log('FAIL ' + relative + ' → ' + error.message);
  }
}

process.exit(failed ? 1 : 0);
