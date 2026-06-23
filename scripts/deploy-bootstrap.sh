#!/usr/bin/env bash

set -euo pipefail

cd /app

echo "[deploy-bootstrap] Starting deploy bootstrap..."

# ============================================================
# PHP Upload Limits — applied on every deploy
# ============================================================
echo "[deploy-bootstrap] Applying PHP upload limits..."
PHP_INI_SCAN=$(php -r "echo php_ini_scanned_files();" 2>/dev/null | head -1 | tr ',' '\n' | head -1 | xargs dirname 2>/dev/null || true)
if [ -z "$PHP_INI_SCAN" ]; then
  # Fallback: write to all known php.ini scan dirs in nix store
  PHP_INI_SCAN=$(find /nix/store -name 'php.ini' -path '*/lib/php.ini' 2>/dev/null | head -1 | xargs dirname 2>/dev/null || echo '/etc')
fi
cat > "${PHP_INI_SCAN}/php.ini" << 'PHPEOF'
upload_max_filesize = 1024M
post_max_size = 2048M
memory_limit = 512M
max_file_uploads = 200
max_execution_time = 300
max_input_time = 300
PHPEOF
echo "[deploy-bootstrap] PHP limits written to ${PHP_INI_SCAN}/php.ini"
php -r "echo '[deploy-bootstrap] upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;" 2>/dev/null || true

# ============================================================
# Nginx client_max_body_size — applied on every deploy
# ============================================================
echo "[deploy-bootstrap] Patching nginx client_max_body_size..."
if [ -f /nginx.conf ]; then
  if ! grep -q 'client_max_body_size' /nginx.conf; then
    sed -i 's/sendfile     on;/client_max_body_size 2048M;\n    sendfile     on;/' /nginx.conf
    echo "[deploy-bootstrap] Nginx client_max_body_size 2048M applied."
  else
    sed -i 's/client_max_body_size [^;]*/client_max_body_size 2048M/' /nginx.conf
    echo "[deploy-bootstrap] Nginx client_max_body_size updated to 2048M."
  fi
fi

if [ "${RUN_DEPLOY_BOOTSTRAP:-1}" != "1" ]; then
  echo "[deploy-bootstrap] RUN_DEPLOY_BOOTSTRAP disabled. Skipping bootstrap."
  exit 0
fi

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
