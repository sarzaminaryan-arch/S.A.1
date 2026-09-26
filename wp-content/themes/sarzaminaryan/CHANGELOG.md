# Changelog — Sarzamin Aryan (parent theme)

## 1.1.0 — 2026-09-26 (review of the uploaded quantum-pedia.zip → sarzaminaryan)

Renamed: Theme Name **Sarzamin Aryan**, slug `sarzaminaryan`, prefix `sarzaminaryan_`, constant
`SARZAMINARYAN_VERSION`, Text Domain `sarzaminaryan`, Author محمدرضا لک.

| # | Finding in 1.0.0 (quantum-pedia) | Standard | Fix in 1.1.0 |
|---|---|---|---|
| F1 | `fonts.css` referenced 8 font files that did not exist (Vazir/Roboto) → 404s, FOIT | Performance / Theme Check (no missing assets) | Bundled Vazirmatn Regular/Medium/Bold woff2 (OFL) + `font-display: swap`; Roboto removed |
| F2 | No `.screen-reader-text` CSS → skip link & SR labels rendered visibly | Accessibility-ready (WCAG 2.4.1) | Added standard SR-text + `:focus` reveal |
| F3 | `.menu-toggle__icon` had no CSS → invisible hamburger on mobile | Mobile usability (Google) | 3-bar icon with animated open state |
| F4 | Mobile menu `position:absolute` but header not `position:relative` | Layout | `.site-header{position:relative}` + `inset-inline:0` |
| F5 | `rtl.css` used `flex-direction:row-reverse` on brand/menu (double-flip in RTL) | RTL best practice | Logical properties; rtl file reduced to 2 rules, appended via `wp_style_add_data(…,'rtl',true)` (`'replace'` would drop main.css on RTL sites — caught in the live WP 7.1 test) |
| F6 | `header.php` echoed `$description` unescaped | Security (escape output) | `esc_html()` + prefixed variable |
| F7 | Search icon was a `⌕` glyph (font-dependent) | UX / consistency | Inline SVG, `aria-label` on button |
| F8 | Customizer `footer_text` setting existed but footer never used it | Correctness | Footer prints `get_theme_mod('sarzaminaryan_footer_text')` |
| F9 | `set_post_thumbnail_size` and `add_image_size('featured')` identical 1200×675 | Performance (duplicate thumbnails) | One hero size + `sarzaminaryan-card` 600×338 for loops |
| F10 | No `$content_width` | Theme Review requirement | Set to 1200 |
| F11 | No `screenshot.png`, `readme.txt`, `languages/` | Theme Review requirement | Added all three (fa_IR .po/.mo compiled) |
| F12 | No focus styles | WCAG 2.4.7 | `:focus-visible` outline + ring |
| F13 | Sidebar had no layout (stacked below content) | Layout | `.site-content` grid wrapper in all templates (≥992px two columns) |
| F14 | No SEO/OG/JSON-LD/breadcrumbs | Google | Deliberately left to the child theme (`inc/seo.php`, `inc/schema.php`) |
| F15 | Pingbacks/XML-RPC untouched | Security | Child theme hardening (`inc/security.php`) |
| F16 | Archive H1 prefixed "Category:" | SEO | Child theme strips prefixes via `get_the_archive_title_prefix` |
| F17 | 4 CSS requests + emoji scripts | Performance | 3 CSS (fonts, main, style.css) + emoji disabled in child |
| F18 | "Tested up to: 6.5" | Theme Review | 7.1 (live-tested on WordPress 7.1.2 / PHP 8.4); Requires at least 6.4; Requires PHP 7.4 |
| F19 | `fonts.css` set `body{font-family}` overriding the `main.css` variable | CSS hygiene | fonts.css only declares `@font-face`; variable used everywhere |
| F20 | No `customize-selective-refresh-widgets` | Theme Review recommendation | Added; script enqueued with `defer` strategy |

Also: `wp-block-styles` support, editor style loads fonts, Escape key closes menu/search,
aria-label on sidebar, comment-reply enqueued only when needed, consolidated the four scattered
changelog `.txt` notes into this file.

## 1.0.0 — original Quantum Pedia release (M.r.lak, A-Del, R.LOR)
