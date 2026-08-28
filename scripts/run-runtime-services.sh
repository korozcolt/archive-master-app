#!/usr/bin/env bash

set -euo pipefail

cd /app

RUNTIME_ROLE="${RUNTIME_ROLE:-all}"
RUN_QUEUE_WORKER="${RUN_QUEUE_WORKER:-1}"
RUN_REVERB="${RUN_REVERB:-1}"
RUN_SCHEDULER="${RUN_SCHEDULER:-1}"
QUEUE_WORKER_QUEUES="${QUEUE_WORKER_QUEUES:-document-processing,notifications,default,ai-processing}"

# Nixpacks vuelca las variables PHP_* en php-fpm.conf, que solo gobierna a
# php-fpm: la linea de comandos se queda con el memory_limit por defecto de
# 128M. Eso deja al worker de OCR con la cuarta parte de memoria que tiene la
# web (512M) justo donde se procesan los escaneos mas largos, asi que se fija
# aqui de forma explicita.
PHP_CLI_MEMORY_LIMIT="${PHP_CLI_MEMORY_LIMIT:-512M}"
PHP_CLI="php -d memory_limit=${PHP_CLI_MEMORY_LIMIT}"

QUEUE_WORKER_CMD="${QUEUE_WORKER_CMD:-${PHP_CLI} artisan queue:work --sleep=1 --tries=3 --timeout=120 --queue=${QUEUE_WORKER_QUEUES}}"
REVERB_CMD="${REVERB_CMD:-${PHP_CLI} artisan reverb:start --host=0.0.0.0 --port=${REVERB_SERVER_PORT:-8080}}"
SCHEDULER_CMD="${SCHEDULER_CMD:-while true; do ${PHP_CLI} artisan schedule:run --no-interaction; sleep 60; done}"
PHP_FPM_CMD="${PHP_FPM_CMD:-php-fpm -F -y /assets/php-fpm.conf}"
NGINX_CMD="${NGINX_CMD:-nginx -c /nginx.conf}"

queue_pid=""
reverb_pid=""
scheduler_pid=""
php_fpm_pid=""
nginx_pid=""
stopping="0"
started_services=0

should_run_web() {
  [ "$RUNTIME_ROLE" = "all" ] || [ "$RUNTIME_ROLE" = "web" ]
}

should_run_background() {
  [ "$RUNTIME_ROLE" = "all" ] || [ "$RUNTIME_ROLE" = "worker" ]
}

start_service() {
  local service="$1"
  local cmd="$2"

  echo "[runtime] starting ${service}: ${cmd}"
  bash -lc "$cmd" &
  local pid=$!
  started_services=$((started_services + 1))

  case "$service" in
    queue)
      queue_pid="$pid"
      ;;
    reverb)
      reverb_pid="$pid"
      ;;
    scheduler)
      scheduler_pid="$pid"
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

  for pid in "$queue_pid" "$reverb_pid" "$scheduler_pid" "$php_fpm_pid" "$nginx_pid"; do
    if is_running "$pid"; then
      kill "$pid" 2>/dev/null || true
    fi
  done

  wait || true
}

case "$RUNTIME_ROLE" in
  all|web|worker)
    ;;
  *)
    echo "[runtime] unsupported RUNTIME_ROLE=${RUNTIME_ROLE}. Use all, web, or worker."
    exit 1
    ;;
esac

trap stop_all TERM INT

if should_run_web; then
  start_service "php-fpm" "$PHP_FPM_CMD"
  start_service "nginx" "$NGINX_CMD"
fi

if should_run_background && [ "$RUN_QUEUE_WORKER" = "1" ]; then
  start_service "queue" "$QUEUE_WORKER_CMD"
fi

if should_run_background && [ "$RUN_REVERB" = "1" ]; then
  start_service "reverb" "$REVERB_CMD"
fi

if should_run_background && [ "$RUN_SCHEDULER" = "1" ]; then
  start_service "scheduler" "$SCHEDULER_CMD"
fi

if [ "$started_services" -eq 0 ]; then
  echo "[runtime] no services enabled for RUNTIME_ROLE=${RUNTIME_ROLE}."
  exit 1
fi

while [ "$stopping" = "0" ]; do
  set +e
  wait -n
  wait_status=$?
  set -e

  if [ "$stopping" = "1" ]; then
    break
  fi

  # A child that exits (including being killed by a signal, e.g. OOM) should
  # always fall through to the checks below so it gets restarted. Do not
  # special-case wait_status here: the `stopping` guard above already covers
  # the real shutdown path (trap sets stopping=1 before this check runs).

  if should_run_web && ! is_running "$php_fpm_pid"; then
    echo "[runtime] php-fpm exited. Restarting..."
    start_service "php-fpm" "$PHP_FPM_CMD"
  fi

  if should_run_web && ! is_running "$nginx_pid"; then
    echo "[runtime] nginx exited. Restarting..."
    start_service "nginx" "$NGINX_CMD"
  fi

  if should_run_background && [ "$RUN_QUEUE_WORKER" = "1" ] && ! is_running "$queue_pid"; then
    echo "[runtime] queue worker exited. Restarting..."
    start_service "queue" "$QUEUE_WORKER_CMD"
  fi

  if should_run_background && [ "$RUN_REVERB" = "1" ] && ! is_running "$reverb_pid"; then
    echo "[runtime] reverb exited. Restarting..."
    start_service "reverb" "$REVERB_CMD"
  fi

  if should_run_background && [ "$RUN_SCHEDULER" = "1" ] && ! is_running "$scheduler_pid"; then
    echo "[runtime] scheduler exited. Restarting..."
    start_service "scheduler" "$SCHEDULER_CMD"
  fi
done
