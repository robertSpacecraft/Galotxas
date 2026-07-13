#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${PROJECT_ROOT}/backend/docker/docker-compose.e2e.yml"
COMPOSE_PROJECT_NAME="${E2E_COMPOSE_PROJECT_NAME:-galotxas-e2e}"
COMPOSE=(docker compose --project-name "${COMPOSE_PROJECT_NAME}" --file "${COMPOSE_FILE}")

cleanup() {
  local status=$?

  trap - EXIT

  if ! "${COMPOSE[@]}" down --volumes --remove-orphans; then
    if [[ ${status} -eq 0 ]]; then
      status=1
    fi
  fi

  exit "${status}"
}

trap cleanup EXIT

"${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
"${COMPOSE[@]}" up --detach --build --wait
"${COMPOSE[@]}" exec --no-TTY app php artisan migrate --force
"${COMPOSE[@]}" exec --no-TTY app php artisan db:seed --class=E2ESmokeSeeder --force

cd "${PROJECT_ROOT}/frontend"
"${COMPOSE[@]}" run --rm --no-deps --no-TTY runner npx playwright test "$@"
