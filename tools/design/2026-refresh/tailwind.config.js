/**
 * Tailwind build for the 2026 refresh of Sarzamin Aryan.
 *
 * The four approved mockups (home / province / county / attraction-treasure)
 * share one utility vocabulary but three different brand palettes. Instead of
 * hard-coding a palette, every brand colour resolves to a CSS custom property
 * triplet (``rgb(var(--sa-x) / <alpha-value>)``) and each page picks its
 * palette through a body class (`sa-palette--home`, `sa-palette--treasure`,
 * default = the teal palette used by province/county).
 *
 * Build:  bash tools/design/2026-refresh/build.sh
 */
const path = require('path');

const THEME = path.resolve(__dirname, '../../../wp-content/themes/sarzaminaryan-child');

/** Colour token → `rgb(var(--sa-…) / <alpha-value>)`. */
const token = (name) => `rgb(var(--sa-${name}) / <alpha-value>)`;

module.exports = {
  content: [
    /* The approved mockups are kept in docs/design and scanned too, so every
     * class that appears in a mockup exists in the compiled stylesheet — the
     * owner can copy a block from the mockup into a template without a rebuild. */
    path.resolve(__dirname, '../../../docs/design/2026-11-refresh/html/*.html'),
    path.join(THEME, 'header.php'),
    path.join(THEME, 'footer.php'),
    path.join(THEME, 'front-page.php'),
    path.join(THEME, 'single-*.php'),
    path.join(THEME, 'template-parts/2026/**/*.php'),
    path.join(THEME, 'assets/js/sa-2026.js'),
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Vazirmatn', 'Tahoma', 'system-ui', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      colors: {
        /* home mockup vocabulary */
        petrol: token('brand'),
        petrolDark: token('brand-dark'),
        petrolSoft: token('brand-soft'),
        ochre: token('accent'),
        ochreDark: token('accent-dark'),
        ochreSoft: token('accent-soft'),
        surface: token('surface'),
        canvas: token('canvas'),
        sand: token('sand'),
        ink: token('ink'),
        muted: token('muted'),
        line: token('line'),
        /* province / county + treasure mockup vocabulary */
        brand: {
          teal: token('brand'),
          tealLight: token('brand-light'),
          tealSoft: token('brand-soft'),
          copper: token('accent'),
          copperSoft: token('accent-soft'),
          forest: token('brand'),
          forestLight: token('brand-light'),
          forestSoft: token('brand-soft'),
          terracotta: token('accent'),
          terracottaSoft: token('accent-soft'),
          cream: token('canvas'),
          ink: token('ink'),
          muted: token('muted'),
          border: token('line'),
        },
      },
      boxShadow: {
        '2xs': '0 1px 1px 0 rgb(var(--sa-ink) / 0.04)',
        xs: '0 1px 2px 0 rgb(var(--sa-ink) / 0.05)',
        card: '0 4px 20px -6px rgb(var(--sa-ink) / 0.06)',
        floating: '0 14px 34px -10px rgb(var(--sa-brand) / 0.16)',
      },
      backdropBlur: {
        xs: '2px',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(6px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        fadeIn: 'fadeIn 0.18s ease-out',
      },
    },
  },
  /* The legacy theme CSS owns `.container`; Tailwind must not redefine it. */
  corePlugins: {
    container: false,
  },
  plugins: [],
};
