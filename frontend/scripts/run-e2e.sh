#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${PROJECT_ROOT}/backend/docker/docker-compose.e2e.yml"
COMPOSE_PROJECT_NAME="${E2E_COMPOSE_PROJECT_NAME:-galotxas-e2e}"
GUARD="${PROJECT_ROOT}/backend/docker/compose-isolation-guard.sh"
SAFE_DOWN="${PROJECT_ROOT}/backend/docker/safe-compose-down.sh"
COMPOSE=(docker compose --project-name "${COMPOSE_PROJECT_NAME}" --file "${COMPOSE_FILE}")
ARTIFACT_DIRS=(
  "${PROJECT_ROOT}/frontend/test-results"
  "${PROJECT_ROOT}/frontend/playwright-report"
  "${PROJECT_ROOT}/frontend/blob-report"
)

cleanup() {
  local status=$?

  trap - EXIT

  if ! "${SAFE_DOWN}" e2e "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}"; then
    if [[ ${status} -eq 0 ]]; then
      status=1
    fi
  fi

  if [[ ${status} -eq 0 ]]; then
    rm -rf -- "${ARTIFACT_DIRS[@]}"
  fi

  exit "${status}"
}

trap cleanup EXIT

"${GUARD}" verify e2e "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}"
"${SAFE_DOWN}" e2e "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}" >/dev/null
"${COMPOSE[@]}" up --detach --build --wait
"${COMPOSE[@]}" exec --no-TTY app php artisan migrate --force
"${COMPOSE[@]}" exec --no-TTY app php artisan db:seed --class=E2ESmokeSeeder --force

cd "${PROJECT_ROOT}/frontend"
"${COMPOSE[@]}" run --rm --no-deps --no-TTY runner npx playwright test "$@"
