#!/bin/sh
# Installs the php-wasm runner (node_modules is not persisted between turns).
set -e
cd "$(dirname "$0")"
if [ ! -d node_modules ]; then
  npm init -y >/dev/null 2>&1 || true
  npm i @php-wasm/node @php-wasm/universal
fi
echo "phpwasm ready"
