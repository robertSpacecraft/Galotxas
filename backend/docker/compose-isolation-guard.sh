#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
TEMP_CONFIG=""

cleanup_temp_config() {
  if [[ -n "${TEMP_CONFIG}" ]]; then
    rm -f -- "${TEMP_CONFIG}"
  fi
}

trap cleanup_temp_config EXIT

fail() {
  printf 'Compose isolation guard: %s\n' "$*" >&2
  exit 1
}

usage() {
  cat >&2 <<'EOF'
Usage:
  compose-isolation-guard.sh verify <test|e2e> <project> <compose-file>
  compose-isolation-guard.sh validate-config <test|e2e> <project> <resolved-config>
EOF
  exit 64
}

configure_expectations() {
  local mode="$1"

  case "${mode}" in
    test)
      EXPECTED_PROJECT="galotxas-test"
      EXPECTED_COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.test.yml"
      EXPECTED_APP_ENV="testing"
      EXPECTED_DATABASE="galotxas_testing"
      ;;
    e2e)
      EXPECTED_PROJECT="galotxas-e2e"
      EXPECTED_COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.e2e.yml"
      EXPECTED_APP_ENV="e2e"
      EXPECTED_DATABASE="galotxas_e2e"
      ;;
    *)
      fail "unknown environment '${mode}'."
      ;;
  esac
}

assert_project() {
  local project="$1"

  [[ "${project}" == "${EXPECTED_PROJECT}" ]] \
    || fail "expected project '${EXPECTED_PROJECT}', received '${project}'."
  [[ "${project}" != "galotxas" ]] \
    || fail "the development project cannot be used for tests."
}

config_has_environment_value() {
  local config_file="$1"
  local key="$2"
  local expected="$3"

  awk -v key="${key}" -v expected="${expected}" '
    $1 == key ":" {
      found = 1
      value = $2
      gsub(/^["\047]|["\047]$/, "", value)
      if (value != expected) {
        invalid = 1
      }
    }
    END { exit(found && !invalid ? 0 : 1) }
  ' "${config_file}"
}

validate_resolved_config() {
  local project="$1"
  local config_file="$2"

  [[ -f "${config_file}" ]] || fail "resolved configuration was not found."
  assert_project "${project}"

  awk -v expected="${project}" '
    $1 == "name:" {
      value = $2
      gsub(/^["\047]|["\047]$/, "", value)
      if (value == expected) {
        found = 1
      }
    }
    END { exit(found ? 0 : 1) }
  ' "${config_file}" \
    || fail "resolved Compose name is not '${project}'."

  config_has_environment_value "${config_file}" "APP_ENV" "${EXPECTED_APP_ENV}" \
    || fail "APP_ENV is not '${EXPECTED_APP_ENV}'."
  config_has_environment_value "${config_file}" "DB_DATABASE" "${EXPECTED_DATABASE}" \
    || fail "DB_DATABASE is not '${EXPECTED_DATABASE}'."
  config_has_environment_value "${config_file}" "MARIADB_DATABASE" "${EXPECTED_DATABASE}" \
    || fail "MARIADB_DATABASE is not '${EXPECTED_DATABASE}'."

  if grep -Eq '(^|[^[:alnum:]_-])(docker_)?galotxas_db_data([^[:alnum:]_-]|$)' "${config_file}"; then
    fail "resolved configuration references the development database volume."
  fi

  if grep -Eq '^[[:space:]]*container_name:[[:space:]]*(galotxas_app|galotxas_web|galotxas_db)([[:space:]]|$)' "${config_file}"; then
    fail "resolved configuration contains a fixed development container name."
  fi

  if grep -Eq '^[[:space:]]*container_name:' "${config_file}"; then
    fail "test configurations must not use fixed container names."
  fi

  if grep -Eq '(^|[^[:alnum:]_-])galotxas_net([^[:alnum:]_-]|$)' "${config_file}"; then
    fail "resolved configuration references the development network."
  fi

  grep -Eq '^[[:space:]]*tmpfs:' "${config_file}" \
    || fail "the isolated database must use tmpfs."
}

assert_owned_resource_names() {
  local project="$1"
  local resource_type="$2"
  local names
  local name

  case "${resource_type}" in
    container)
      names="$(docker ps -a \
        --filter "label=com.docker.compose.project=${project}" \
        --format '{{.Names}}')"
      ;;
    network)
      names="$(docker network ls \
        --filter "label=com.docker.compose.project=${project}" \
        --format '{{.Name}}')"
      ;;
    volume)
      names="$(docker volume ls \
        --filter "label=com.docker.compose.project=${project}" \
        --format '{{.Name}}')"
      ;;
    *)
      fail "unsupported resource type '${resource_type}'."
      ;;
  esac

  while IFS= read -r name; do
    [[ -z "${name}" ]] && continue

    case "${name}" in
      "${project}-"*|"${project}_"*)
        ;;
      *)
        fail "${resource_type} '${name}' is labelled for '${project}' without its project prefix."
        ;;
    esac

    case "${name}" in
      galotxas-app-1|galotxas-web-1|galotxas-db-1|galotxas_app|galotxas_web|galotxas_db|docker_galotxas_db_data|galotxas_galotxas_db_data)
        fail "development ${resource_type} '${name}' must not belong to '${project}'."
        ;;
    esac
  done <<< "${names}"
}

verify_compose() {
  local mode="$1"
  local project="$2"
  local compose_file="$3"
  local expected_file
  local actual_file
  local resolved_config

  assert_project "${project}"
  [[ -f "${compose_file}" ]] || fail "Compose file '${compose_file}' was not found."

  expected_file="$(realpath -e "${EXPECTED_COMPOSE_FILE}")"
  actual_file="$(realpath -e "${compose_file}")"
  [[ "${actual_file}" == "${expected_file}" ]] \
    || fail "expected Compose file '${expected_file}', received '${actual_file}'."

  resolved_config="$(mktemp)"
  TEMP_CONFIG="${resolved_config}"

  docker compose \
    --project-name "${project}" \
    --file "${actual_file}" \
    config > "${resolved_config}"

  validate_resolved_config "${project}" "${resolved_config}"
  assert_owned_resource_names "${project}" container
  assert_owned_resource_names "${project}" network
  assert_owned_resource_names "${project}" volume

  rm -f -- "${resolved_config}"
  TEMP_CONFIG=""
}

command_name="${1:-}"
mode="${2:-}"
project="${3:-}"
subject="${4:-}"

[[ -n "${command_name}" && -n "${mode}" && -n "${project}" && -n "${subject}" ]] || usage

configure_expectations "${mode}"

case "${command_name}" in
  verify)
    verify_compose "${mode}" "${project}" "${subject}"
    ;;
  validate-config)
    validate_resolved_config "${project}" "${subject}"
    ;;
  *)
    usage
    ;;
esac
