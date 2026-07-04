#!/bin/bash

set -euo pipefail

if command -v pkill >/dev/null 2>&1; then
  pkill -f 'artisan serve' >/dev/null 2>&1 || true
  pkill -f 'vite' >/dev/null 2>&1 || true
  pkill -f 'reverb:start' >/dev/null 2>&1 || true
fi

rm -f public/hot
php artisan optimize:clear
npm run build
php artisan reverb:start --host=0.0.0.0 --port=8080 &
reverb_pid=$!

cleanup() {
  kill "$reverb_pid" >/dev/null 2>&1 || true
}

trap cleanup EXIT INT TERM
exec php artisan serve --host=0.0.0.0 --port=8000
