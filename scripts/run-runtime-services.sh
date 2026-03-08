#!/usr/bin/env bash

set -euo pipefail

cd /app

RUN_QUEUE_WORKER="${RUN_QUEUE_WORKER:-1}"
RUN_REVERB="${RUN_REVERB:-1}"
QUEUE_WORKER_QUEUES="${QUEUE_WORKER_QUEUES:-document-processing,notifications,default,ai-processing}"

QUEUE_WORKER_CMD="${QUEUE_WORKER_CMD:-php artisan queue:work --sleep=1 --tries=3 --timeout=120 --queue=${QUEUE_WORKER_QUEUES}}"
REVERB_CMD="${REVERB_CMD:-php artisan reverb:start --host=0.0.0.0 --port=${REVERB_SERVER_PORT:-8080}}"
PHP_FPM_CMD="${PHP_FPM_CMD:-php-fpm -y /assets/php-fpm.conf}"
NGINX_CMD="${NGINX_CMD:-nginx -c /nginx.conf}"

queue_pid=""
reverb_pid=""
php_fpm_pid=""
nginx_pid=""
stopping="0"

start_service() {
  local service="$1"
  local cmd="$2"

  echo "[runtime] starting ${service}: ${cmd}"
  bash -lc "$cmd" &
  local pid=$!

  case "$service" in
    queue)
      queue_pid="$pid"
      ;;
    reverb)
      reverb_pid="$pid"
      ;;
    php-fpm)
      php_fpm_pid="$pid"
      ;;
    nginx)
      nginx_pid="$pid"
      ;;
  esac
}

is_running() {
  local pid="${1:-}"

  if [ -z "$pid" ]; then
    return 1
  fi

  kill -0 "$pid" 2>/dev/null
}

stop_all() {
  if [ "$stopping" = "1" ]; then
    return
  fi

  stopping="1"
  echo "[runtime] stopping services..."

  for pid in "$queue_pid" "$reverb_pid" "$php_fpm_pid" "$nginx_pid"; do
    if is_running "$pid"; then
      kill "$pid" 2>/dev/null || true
    fi
  done

  wait || true
}

trap stop_all TERM INT

start_service "php-fpm" "$PHP_FPM_CMD"
start_service "nginx" "$NGINX_CMD"

if [ "$RUN_QUEUE_WORKER" = "1" ]; then
  start_service "queue" "$QUEUE_WORKER_CMD"
fi

if [ "$RUN_REVERB" = "1" ]; then
  start_service "reverb" "$REVERB_CMD"
fi

while [ "$stopping" = "0" ]; do
  set +e
  wait -n
  wait_status=$?
  set -e

  if [ "$stopping" = "1" ]; then
    break
  fi

  if [ "$wait_status" -gt 128 ]; then
    # Interrupted by signal; continue monitoring loop.
    continue
  fi

  if ! is_running "$php_fpm_pid"; then
    echo "[runtime] php-fpm exited. Restarting..."
    start_service "php-fpm" "$PHP_FPM_CMD"
  fi

  if ! is_running "$nginx_pid"; then
    echo "[runtime] nginx exited. Restarting..."
    start_service "nginx" "$NGINX_CMD"
  fi

  if [ "$RUN_QUEUE_WORKER" = "1" ] && ! is_running "$queue_pid"; then
    echo "[runtime] queue worker exited. Restarting..."
    start_service "queue" "$QUEUE_WORKER_CMD"
  fi

  if [ "$RUN_REVERB" = "1" ] && ! is_running "$reverb_pid"; then
    echo "[runtime] reverb exited. Restarting..."
    start_service "reverb" "$REVERB_CMD"
  fi
done
