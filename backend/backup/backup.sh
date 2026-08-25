#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

readonly PROGRAM_NAME="galotxas-backup"

WORK_DIR=""

log() {
  printf '%s: %s\n' "${PROGRAM_NAME}" "$1"
}

fail() {
  printf '%s: ERROR: %s\n' "${PROGRAM_NAME}" "$1" >&2
  exit 1
}

cleanup() {
  local status=$?

  trap - EXIT HUP INT TERM

  if [[ -n "${WORK_DIR}" && -d "${WORK_DIR}" ]]; then
    chmod -R u+rwX -- "${WORK_DIR}" >/dev/null 2>&1 || true
    rm -rf -- "${WORK_DIR}"
  fi

  exit "${status}"
}

trap cleanup EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

require_variable() {
  local name="$1"

  if [[ -z "${!name-}" ]]; then
    fail "Required environment variable ${name} is missing."
  fi
}

require_single_line() {
  local name="$1"
  local value="${!name-}"

  if [[ "${value}" == *$'\n'* || "${value}" == *$'\r'* ]]; then
    fail "Environment variable ${name} must contain a single line."
  fi
}

require_safe_identifier() {
  local name="$1"
  local pattern="$2"
  local value="${!name-}"

  if [[ ! "${value}" =~ ${pattern} ]]; then
    fail "Environment variable ${name} has an invalid format."
  fi
}

require_positive_integer() {
  local name="$1"
  local value="${!name-}"

  if [[ ! "${value}" =~ ^[1-9][0-9]*$ ]]; then
    fail "Environment variable ${name} must be a positive integer."
  fi
}

require_command() {
  local command_name="$1"

  if ! command -v "${command_name}" >/dev/null 2>&1; then
    fail "Required command ${command_name} is unavailable."
  fi
}

validate_common_contract() {
  local name
  local repository_prefix

  for name in \
    BACKUP_GDRIVE_CLIENT_ID \
    BACKUP_GDRIVE_CLIENT_SECRET \
    BACKUP_GDRIVE_TOKEN \
    BACKUP_RESTIC_PASSWORD \
    BACKUP_RESTIC_REPOSITORY; do
    require_variable "${name}"
    require_single_line "${name}"
  done

  BACKUP_GDRIVE_REMOTE_NAME="${BACKUP_GDRIVE_REMOTE_NAME:-gdrive-galotxes-backup}"
  BACKUP_RESTIC_HOST="${BACKUP_RESTIC_HOST:-galotxas-production-backup}"
  BACKUP_RESTIC_TAG="${BACKUP_RESTIC_TAG:-galotxas-production}"

  require_safe_identifier BACKUP_GDRIVE_REMOTE_NAME '^[A-Za-z0-9][A-Za-z0-9_-]*$'
  require_safe_identifier BACKUP_RESTIC_HOST '^[A-Za-z0-9][A-Za-z0-9._-]*$'
  require_safe_identifier BACKUP_RESTIC_TAG '^[A-Za-z0-9][A-Za-z0-9._=-]*$'

  repository_prefix="rclone:${BACKUP_GDRIVE_REMOTE_NAME}:"
  if [[ "${BACKUP_RESTIC_REPOSITORY}" != "${repository_prefix}"* \
    || "${BACKUP_RESTIC_REPOSITORY}" == "${repository_prefix}" ]]; then
    fail "BACKUP_RESTIC_REPOSITORY must use the configured Google Drive rclone remote."
  fi
}

validate_backup_contract() {
  local name

  for name in \
    BACKUP_DB_HOST \
    BACKUP_DB_DATABASE \
    BACKUP_DB_USERNAME \
    BACKUP_DB_PASSWORD \
    BACKUP_MEDIA_BUCKET \
    BACKUP_MEDIA_ENDPOINT \
    BACKUP_MEDIA_REGION \
    BACKUP_MEDIA_ACCESS_KEY_ID \
    BACKUP_MEDIA_SECRET_ACCESS_KEY \
    BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT; do
    require_variable "${name}"
    require_single_line "${name}"
  done

  BACKUP_DB_PORT="${BACKUP_DB_PORT:-3306}"
  BACKUP_MEDIA_REMOTE_NAME="${BACKUP_MEDIA_REMOTE_NAME:-media-production-source}"
  BACKUP_RETENTION_DAILY="${BACKUP_RETENTION_DAILY:-14}"
  BACKUP_RETENTION_WEEKLY="${BACKUP_RETENTION_WEEKLY:-8}"
  BACKUP_RETENTION_MONTHLY="${BACKUP_RETENTION_MONTHLY:-12}"

  require_safe_identifier BACKUP_DB_HOST '^[A-Za-z0-9][A-Za-z0-9._-]*$'
  require_safe_identifier BACKUP_DB_DATABASE '^[A-Za-z0-9_]+$'
  require_safe_identifier BACKUP_DB_USERNAME '^[A-Za-z0-9][A-Za-z0-9_.@-]*$'
  require_safe_identifier BACKUP_MEDIA_BUCKET '^[A-Za-z0-9][A-Za-z0-9._-]*$'
  require_safe_identifier BACKUP_MEDIA_REGION '^[A-Za-z0-9][A-Za-z0-9._-]*$'
  require_safe_identifier BACKUP_MEDIA_REMOTE_NAME '^[A-Za-z0-9][A-Za-z0-9_-]*$'
  require_positive_integer BACKUP_DB_PORT
  require_positive_integer BACKUP_RETENTION_DAILY
  require_positive_integer BACKUP_RETENTION_WEEKLY
  require_positive_integer BACKUP_RETENTION_MONTHLY

  if [[ "${BACKUP_MEDIA_REMOTE_NAME}" == "${BACKUP_GDRIVE_REMOTE_NAME}" ]]; then
    fail "The media and Google Drive rclone remote names must be different."
  fi

  if [[ "${BACKUP_MEDIA_ENDPOINT}" != https://* || "${BACKUP_MEDIA_ENDPOINT}" == *"@"* ]]; then
    fail "BACKUP_MEDIA_ENDPOINT must be an HTTPS endpoint without embedded credentials."
  fi

  if [[ "${BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT}" != "true" \
    && "${BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT}" != "false" ]]; then
    fail "BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT must be true or false."
  fi
}

validate_runtime() {
  local mode="$1"
  local command_name

  for command_name in bash chmod mkdir mktemp restic rclone rm; do
    require_command "${command_name}"
  done

  if [[ "${mode}" == "backup" ]]; then
    for command_name in awk find mariadb-dump sha256sum stat tr wc; do
      require_command "${command_name}"
    done
  fi
}

prepare_runtime_files() {
  umask 077

  WORK_DIR="$(mktemp -d /tmp/galotxas-backup.XXXXXX)"
  chmod 0700 "${WORK_DIR}"

  export RCLONE_CONFIG="${WORK_DIR}/rclone.conf"
  export RESTIC_PASSWORD_FILE="${WORK_DIR}/restic-password"
  export RESTIC_CACHE_DIR="${WORK_DIR}/restic-cache"
  export RESTIC_REPOSITORY="${BACKUP_RESTIC_REPOSITORY}"

  mkdir -p "${RESTIC_CACHE_DIR}"
  printf '%s' "${BACKUP_RESTIC_PASSWORD}" > "${RESTIC_PASSWORD_FILE}"

  {
    printf '[%s]\n' "${BACKUP_GDRIVE_REMOTE_NAME}"
    printf 'type = drive\n'
    printf 'client_id = %s\n' "${BACKUP_GDRIVE_CLIENT_ID}"
    printf 'client_secret = %s\n' "${BACKUP_GDRIVE_CLIENT_SECRET}"
    printf 'token = %s\n' "${BACKUP_GDRIVE_TOKEN}"
    printf 'scope = drive\n'
  } > "${RCLONE_CONFIG}"

  chmod 0600 "${RCLONE_CONFIG}" "${RESTIC_PASSWORD_FILE}"
}

append_media_remote() {
  {
    printf '\n[%s]\n' "${BACKUP_MEDIA_REMOTE_NAME}"
    printf 'type = s3\n'
    printf 'provider = Other\n'
    printf 'env_auth = false\n'
    printf 'access_key_id = %s\n' "${BACKUP_MEDIA_ACCESS_KEY_ID}"
    printf 'secret_access_key = %s\n' "${BACKUP_MEDIA_SECRET_ACCESS_KEY}"
    printf 'endpoint = %s\n' "${BACKUP_MEDIA_ENDPOINT}"
    printf 'region = %s\n' "${BACKUP_MEDIA_REGION}"
    printf 'force_path_style = %s\n' "${BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT}"
  } >> "${RCLONE_CONFIG}"

  chmod 0600 "${RCLONE_CONFIG}"
}

escape_mariadb_option_value() {
  local value="$1"

  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '%s' "${value}"
}

create_database_client_config() {
  local config_path="$1"
  local escaped_password

  escaped_password="$(escape_mariadb_option_value "${BACKUP_DB_PASSWORD}")"

  {
    printf '[client]\n'
    printf 'host=%s\n' "${BACKUP_DB_HOST}"
    printf 'port=%s\n' "${BACKUP_DB_PORT}"
    printf 'user=%s\n' "${BACKUP_DB_USERNAME}"
    printf 'password="%s"\n' "${escaped_password}"
    printf 'protocol=tcp\n'
  } > "${config_path}"

  chmod 0600 "${config_path}"
}

verify_repository_access() {
  if ! restic snapshots --latest 1 \
    > "${WORK_DIR}/restic-snapshots.out" \
    2> "${WORK_DIR}/restic-snapshots.err"; then
    fail "The restic repository is unavailable or invalid."
  fi
}

write_manifest() {
  local manifest_path="$1"
  local dump_path="$2"
  local media_path="$3"
  local created_at dump_size dump_sha media_count media_size

  created_at="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  dump_size="$(stat -c '%s' "${dump_path}")"
  dump_sha="$(sha256sum "${dump_path}" | awk '{print $1}')"
  media_count="$(find "${media_path}" -type f -printf '.' | wc -c | tr -d '[:space:]')"
  media_size="$(find "${media_path}" -type f -printf '%s\n' \
    | awk '{ total += $1 } END { printf "%.0f", total + 0 }')"

  {
    printf '{\n'
    printf '  "schema_version": 1,\n'
    printf '  "created_at_utc": "%s",\n' "${created_at}"
    printf '  "environment": "production",\n'
    printf '  "database": "%s",\n' "${BACKUP_DB_DATABASE}"
    printf '  "database_dump_bytes": %s,\n' "${dump_size}"
    printf '  "database_dump_sha256": "%s",\n' "${dump_sha}"
    printf '  "media_file_count": %s,\n' "${media_count}"
    printf '  "media_total_bytes": %s\n' "${media_size}"
    printf '}\n'
  } > "${manifest_path}"
}

run_check() {
  prepare_runtime_files

  if ! restic check \
    > "${WORK_DIR}/restic-check.out" \
    2> "${WORK_DIR}/restic-check.err"; then
    fail "The restic repository check failed."
  fi

  log "Backup repository check completed successfully."
}

run_backup() {
  local payload_path database_path media_path dump_path database_config

  prepare_runtime_files
  append_media_remote
  verify_repository_access

  payload_path="${WORK_DIR}/payload"
  database_path="${payload_path}/database"
  media_path="${payload_path}/media"
  dump_path="${database_path}/${BACKUP_DB_DATABASE}.sql"
  database_config="${WORK_DIR}/mariadb-client.cnf"

  mkdir -p "${database_path}" "${media_path}"
  create_database_client_config "${database_config}"

  if ! mariadb-dump \
    "--defaults-extra-file=${database_config}" \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --skip-comments \
    --default-character-set=utf8mb4 \
    --databases "${BACKUP_DB_DATABASE}" \
    > "${dump_path}" \
    2> "${WORK_DIR}/mariadb-dump.err"; then
    fail "The MariaDB logical dump failed."
  fi

  if [[ ! -s "${dump_path}" ]]; then
    fail "The MariaDB logical dump is empty."
  fi

  if ! rclone copy \
    "${BACKUP_MEDIA_REMOTE_NAME}:${BACKUP_MEDIA_BUCKET}" \
    "${media_path}" \
    --config "${RCLONE_CONFIG}" \
    --checkers 8 \
    --transfers 4 \
    --retries 3 \
    --low-level-retries 10 \
    --quiet \
    > "${WORK_DIR}/rclone-media.out" \
    2> "${WORK_DIR}/rclone-media.err"; then
    fail "The media bucket copy failed."
  fi

  write_manifest "${payload_path}/manifest.json" "${dump_path}" "${media_path}"

  if ! restic backup \
    --host "${BACKUP_RESTIC_HOST}" \
    --tag "${BACKUP_RESTIC_TAG}" \
    --group-by host,tags \
    "${payload_path}" \
    > "${WORK_DIR}/restic-backup.out" \
    2> "${WORK_DIR}/restic-backup.err"; then
    fail "The encrypted restic snapshot failed."
  fi

  if ! restic forget \
    --host "${BACKUP_RESTIC_HOST}" \
    --tag "${BACKUP_RESTIC_TAG}" \
    --group-by host,tags \
    --keep-daily "${BACKUP_RETENTION_DAILY}" \
    --keep-weekly "${BACKUP_RETENTION_WEEKLY}" \
    --keep-monthly "${BACKUP_RETENTION_MONTHLY}" \
    --prune \
    > "${WORK_DIR}/restic-forget.out" \
    2> "${WORK_DIR}/restic-forget.err"; then
    fail "The restic retention or prune operation failed after snapshot creation."
  fi

  log "Encrypted database and media backup completed successfully."
}

main() {
  local mode="${1:-backup}"

  if (( $# > 1 )); then
    fail "Usage: ${PROGRAM_NAME} [backup|check]."
  fi

  if [[ "${mode}" != "backup" && "${mode}" != "check" ]]; then
    fail "Usage: ${PROGRAM_NAME} [backup|check]."
  fi

  validate_common_contract
  if [[ "${mode}" == "backup" ]]; then
    validate_backup_contract
  fi
  validate_runtime "${mode}"

  case "${mode}" in
    backup)
      run_backup
      ;;
    check)
      run_check
      ;;
  esac
}

main "$@"
