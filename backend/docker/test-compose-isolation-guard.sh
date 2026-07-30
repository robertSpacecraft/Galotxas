#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"
GUARD="${SCRIPT_DIR}/compose-isolation-guard.sh"
SAFE_DOWN="${SCRIPT_DIR}/safe-compose-down.sh"
E2E_COMPOSE="${SCRIPT_DIR}/docker-compose.e2e.yml"
TEST_COMPOSE="${SCRIPT_DIR}/docker-compose.test.yml"
E2E_RUNNER="${PROJECT_ROOT}/frontend/scripts/run-e2e.sh"
TEST_RUNNER="${PROJECT_ROOT}/backend/scripts/run-tests.sh"
TEMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf -- "${TEMP_DIR}"
}

trap cleanup EXIT

pass_count=0

expect_pass() {
  local description="$1"

  shift
  if ! "$@" >/dev/null 2>&1; then
    printf 'FAIL: %s\n' "${description}" >&2
    exit 1
  fi

  printf 'PASS: %s\n' "${description}"
  pass_count=$((pass_count + 1))
}

expect_fail() {
  local description="$1"

  shift
  if "$@" >/dev/null 2>&1; then
    printf 'FAIL: %s\n' "${description}" >&2
    exit 1
  fi

  printf 'PASS: %s\n' "${description}"
  pass_count=$((pass_count + 1))
}

docker compose \
  --project-name galotxas-e2e \
  --file "${E2E_COMPOSE}" \
  config > "${TEMP_DIR}/e2e.yml"
docker compose \
  --project-name galotxas-test \
  --file "${TEST_COMPOSE}" \
  config > "${TEMP_DIR}/test.yml"

expect_pass \
  "the resolved E2E configuration is isolated" \
  "${GUARD}" validate-config e2e galotxas-e2e "${TEMP_DIR}/e2e.yml"
expect_pass \
  "the resolved backend-test configuration is isolated" \
  "${GUARD}" validate-config test galotxas-test "${TEMP_DIR}/test.yml"

expect_fail \
  "E2E rejects the development project name" \
  "${GUARD}" validate-config e2e galotxas "${TEMP_DIR}/e2e.yml"

cp "${TEMP_DIR}/e2e.yml" "${TEMP_DIR}/development-volume.yml"
printf '\nunsafe_reference: docker_galotxas_db_data\n' >> "${TEMP_DIR}/development-volume.yml"
expect_fail \
  "E2E rejects a development-volume reference" \
  "${GUARD}" validate-config e2e galotxas-e2e "${TEMP_DIR}/development-volume.yml"

sed 's/DB_DATABASE: galotxas_e2e/DB_DATABASE: galotxas/' \
  "${TEMP_DIR}/e2e.yml" > "${TEMP_DIR}/development-database.yml"
expect_fail \
  "E2E rejects a non-E2E database" \
  "${GUARD}" validate-config e2e galotxas-e2e "${TEMP_DIR}/development-database.yml"

sed 's/APP_ENV: e2e/APP_ENV: local/' \
  "${TEMP_DIR}/e2e.yml" > "${TEMP_DIR}/development-environment.yml"
expect_fail \
  "E2E rejects a non-E2E application environment" \
  "${GUARD}" validate-config e2e galotxas-e2e "${TEMP_DIR}/development-environment.yml"

cp "${TEMP_DIR}/e2e.yml" "${TEMP_DIR}/fixed-development-container.yml"
printf '\ncontainer_name: galotxas_app\n' >> "${TEMP_DIR}/fixed-development-container.yml"
expect_fail \
  "E2E rejects fixed development container names" \
  "${GUARD}" validate-config e2e galotxas-e2e "${TEMP_DIR}/fixed-development-container.yml"

expect_fail \
  "cleanup rejects a missing explicit project" \
  "${SAFE_DOWN}" e2e
expect_fail \
  "E2E rejects an unexpected Compose file" \
  "${GUARD}" verify e2e galotxas-e2e "${TEST_COMPOSE}"

if grep -Eq '(^|[[:space:]])(docker compose|"?\\$\\{?COMPOSE[^[:space:]]*)[^#]*[[:space:]]down([[:space:]]|$)' \
  "${E2E_RUNNER}" "${TEST_RUNNER}"; then
  printf 'FAIL: a runner bypasses safe-compose-down.sh.\n' >&2
  exit 1
fi
printf 'PASS: runners do not invoke Compose down directly\n'
pass_count=$((pass_count + 1))

grep -Fq -- '--project-name "${PROJECT_NAME}"' "${SAFE_DOWN}" \
  || {
    printf 'FAIL: safe cleanup does not pass an explicit project.\n' >&2
    exit 1
  }
printf 'PASS: safe cleanup always passes an explicit project\n'
pass_count=$((pass_count + 1))

printf '%d isolation guard checks passed.\n' "${pass_count}"
