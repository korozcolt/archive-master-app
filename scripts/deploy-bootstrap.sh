#!/usr/bin/env bash

set -euo pipefail

cd /app

echo "[deploy-bootstrap] Starting deploy bootstrap..."

# ============================================================
# PHP Upload Limits — applied on every deploy via php-fpm.conf
# ============================================================
echo "[deploy-bootstrap] Applying PHP upload limits via php-fpm..."
# Write limits into fpm pool config (safe: does not touch extension ini files)
PHP_FPM_CONF="/assets/php-fpm.conf"
if [ -f "$PHP_FPM_CONF" ]; then
  # Remove previously injected php_admin_value lines to avoid duplicates
  sed -i '/php_admin_value\[upload_max_filesize\]/d' "$PHP_FPM_CONF"
  sed -i '/php_admin_value\[post_max_size\]/d' "$PHP_FPM_CONF"
  sed -i '/php_admin_value\[memory_limit\]/d' "$PHP_FPM_CONF"
  sed -i '/php_admin_value\[max_file_uploads\]/d' "$PHP_FPM_CONF"
  sed -i '/php_admin_value\[max_execution_time\]/d' "$PHP_FPM_CONF"
  sed -i '/php_admin_value\[max_input_time\]/d' "$PHP_FPM_CONF"
  # Append limits at end of [www] pool
  cat >> "$PHP_FPM_CONF" << 'FPMEOF'
php_admin_value[upload_max_filesize] = 1024M
php_admin_value[post_max_size] = 2048M
php_admin_value[memory_limit] = 512M
php_admin_value[max_file_uploads] = 200
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
FPMEOF
  echo "[deploy-bootstrap] PHP-FPM limits injected into ${PHP_FPM_CONF}"
else
  echo "[deploy-bootstrap] WARNING: php-fpm.conf not found at ${PHP_FPM_CONF}"
fi

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
