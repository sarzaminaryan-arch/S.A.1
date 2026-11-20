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
  'template-experience.php',
  'template-parts/2026/experience-page.php',
  'comments.php',
  'page.php',
  'inc/content-2026.php',
  'inc/experience-form.php',
  'inc/comments-2026.php',
  'inc/toc.php',
  'template-parts/content-single-post.php',
  'template-guide.php',
  'category.php',
  'legacy/category.php',
  'template-parts/2026/guide-hub.php',
  'inc/guide-hub.php',
  'inc/guide-cpt.php',
  'inc/guide-migrate.php',
  'inc/education.php',
  'inc/seo.php',
  'inc/schema.php',
  'inc/breadcrumbs.php',
  'inc/template-tags.php',
  'inc/meta-fields.php',
  'archive-guide.php',
  'single-guide.php',
  'tests/test-guide-hub.php',
  'tests/test-guide-cpt.php',
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
