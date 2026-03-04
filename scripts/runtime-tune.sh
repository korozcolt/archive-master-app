#!/usr/bin/env bash
set -euo pipefail

PHP_UPLOAD_MAX_FILESIZE="${PHP_UPLOAD_MAX_FILESIZE:-1024M}"
PHP_POST_MAX_SIZE="${PHP_POST_MAX_SIZE:-2048M}"
PHP_MAX_FILE_UPLOADS="${PHP_MAX_FILE_UPLOADS:-200}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-1024M}"
NGINX_CLIENT_MAX_BODY_SIZE="${NGINX_CLIENT_MAX_BODY_SIZE:-0}"

echo "[runtime-tune] Configurando límites de PHP y Nginx..."

configure_php_limits() {
  local php_scan_dir
  php_scan_dir="$(php --ini 2>/dev/null | awk -F': ' '/Scan for additional \.ini files in/ {print $2}' | tr -d '[:space:]')"

  if [[ -n "${php_scan_dir}" && -d "${php_scan_dir}" && -w "${php_scan_dir}" ]]; then
    cat > "${php_scan_dir}/99-uploads.ini" <<EOF
upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}
post_max_size=${PHP_POST_MAX_SIZE}
max_file_uploads=${PHP_MAX_FILE_UPLOADS}
memory_limit=${PHP_MEMORY_LIMIT}
EOF
    echo "[runtime-tune] PHP INI aplicado en ${php_scan_dir}/99-uploads.ini"
    return 0
  fi

  if [[ -d "/app/public" && -w "/app/public" ]]; then
    cat > "/app/public/.user.ini" <<EOF
upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}
post_max_size=${PHP_POST_MAX_SIZE}
max_file_uploads=${PHP_MAX_FILE_UPLOADS}
memory_limit=${PHP_MEMORY_LIMIT}
EOF
    echo "[runtime-tune] PHP fallback aplicado en /app/public/.user.ini"
    return 0
  fi

  echo "[runtime-tune] No se pudo escribir configuración PHP (sin permisos)."
  return 1
}

configure_nginx_limits() {
  if ! command -v nginx >/dev/null 2>&1; then
    echo "[runtime-tune] Nginx no encontrado, se omite ajuste."
    return 0
  fi

  if [[ -d "/etc/nginx/conf.d" && -w "/etc/nginx/conf.d" ]]; then
    cat > /etc/nginx/conf.d/99-upload-limits.conf <<EOF
client_max_body_size ${NGINX_CLIENT_MAX_BODY_SIZE};
client_body_timeout 300s;
send_timeout 300s;
EOF
    echo "[runtime-tune] Nginx config aplicada en /etc/nginx/conf.d/99-upload-limits.conf"
    nginx -s reload >/dev/null 2>&1 || true
    return 0
  fi

  echo "[runtime-tune] No se pudo escribir /etc/nginx/conf.d/99-upload-limits.conf (sin permisos)."
  return 1
}

configure_php_limits || true
configure_nginx_limits || true

echo "[runtime-tune] Finalizado."
