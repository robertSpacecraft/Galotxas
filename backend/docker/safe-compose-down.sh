#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
MODE="${1:-}"
PROJECT_NAME="${2:-}"
COMPOSE_FILE="${3:-}"

if [[ -z "${MODE}" || -z "${PROJECT_NAME}" || -z "${COMPOSE_FILE}" ]]; then
  printf 'Safe Compose cleanup requires an environment, explicit project and explicit Compose file.\n' >&2
  exit 64
fi

"${SCRIPT_DIR}/compose-isolation-guard.sh" \
  verify \
  "${MODE}" \
  "${PROJECT_NAME}" \
  "${COMPOSE_FILE}"

docker compose \
  --project-name "${PROJECT_NAME}" \
  --file "${COMPOSE_FILE}" \
  down \
  --volumes \
  --remove-orphans
