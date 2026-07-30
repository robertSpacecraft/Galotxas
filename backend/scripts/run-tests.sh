#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${PROJECT_ROOT}/backend/docker/docker-compose.test.yml"
COMPOSE_PROJECT_NAME="${BACKEND_TEST_COMPOSE_PROJECT_NAME:-galotxas-test}"
GUARD="${PROJECT_ROOT}/backend/docker/compose-isolation-guard.sh"
SAFE_DOWN="${PROJECT_ROOT}/backend/docker/safe-compose-down.sh"
COMPOSE=(
  docker compose
  --project-name "${COMPOSE_PROJECT_NAME}"
  --file "${COMPOSE_FILE}"
)

cleanup() {
  local status=$?

  trap - EXIT

  if ! "${SAFE_DOWN}" test "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}"; then
    if [[ ${status} -eq 0 ]]; then
      status=1
    fi
  fi

  exit "${status}"
}

trap cleanup EXIT

"${GUARD}" verify test "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}"
"${SAFE_DOWN}" test "${COMPOSE_PROJECT_NAME}" "${COMPOSE_FILE}" >/dev/null
"${COMPOSE[@]}" run --rm --build test php artisan test "$@"
