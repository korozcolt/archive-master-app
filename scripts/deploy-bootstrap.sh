#!/usr/bin/env bash

set -euo pipefail

cd /app

echo "[deploy-bootstrap] Starting deploy bootstrap..."

if [ ! -f artisan ]; then
  echo "[deploy-bootstrap] artisan not found in /app. Skipping bootstrap."
  exit 0
fi

MAX_ATTEMPTS="${BOOTSTRAP_MIGRATE_MAX_ATTEMPTS:-10}"
SLEEP_SECONDS="${BOOTSTRAP_MIGRATE_SLEEP_SECONDS:-5}"

attempt=1
while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
  if php artisan migrate --force --no-interaction; then
    echo "[deploy-bootstrap] Migrations completed."
    break
  fi

  if [ "$attempt" -eq "$MAX_ATTEMPTS" ]; then
    echo "[deploy-bootstrap] Migrations failed after ${MAX_ATTEMPTS} attempts."
    exit 1
  fi

  echo "[deploy-bootstrap] Migrate attempt ${attempt}/${MAX_ATTEMPTS} failed. Retrying in ${SLEEP_SECONDS}s..."
  sleep "$SLEEP_SECONDS"
  attempt=$((attempt + 1))
done

if php artisan storage:link --force --no-interaction; then
  echo "[deploy-bootstrap] storage:link completed."
else
  echo "[deploy-bootstrap] storage:link returned non-zero. Continuing."
fi

echo "[deploy-bootstrap] Bootstrap finished."
