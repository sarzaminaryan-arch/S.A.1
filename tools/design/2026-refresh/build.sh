#!/usr/bin/env bash
# Build the 2026 design-system CSS for the Sarzamin Aryan child theme.
#
#   bash tools/design/2026-refresh/build.sh
#
# The compiled file (assets/css/sa-2026.css) is committed, so production never
# needs Node. Run this after editing any Tailwind class in the 2026 templates.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME="$HERE/../../../wp-content/themes/sarzaminaryan-child"
OUT="$THEME/assets/css/sa-2026.css"

cd "$HERE"

if [ ! -x node_modules/.bin/tailwindcss ]; then
  echo "→ installing tailwindcss (build-time only, never shipped)…"
  npm install --no-fund --no-audit
fi

echo "→ compiling $OUT"
npx tailwindcss \
  --config "$HERE/tailwind.config.js" \
  --input "$HERE/src/theme.css" \
  --output "$OUT" \
  --minify

echo "→ done: $(wc -c < "$OUT") bytes"
