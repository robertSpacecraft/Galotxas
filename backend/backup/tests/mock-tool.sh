#!/usr/bin/env bash

set -euo pipefail

tool_name="${0##*/}"
call_log="${MOCK_CALL_LOG:?}"

assert_argument() {
  local expected="$1"

  shift
  local argument
  for argument in "$@"; do
    if [[ "${argument}" == "${expected}" ]]; then
      return 0
    fi
  done

  printf 'Mock %s did not receive required argument %s.\n' "${tool_name}" "${expected}" >&2
  exit 91
}

assert_argument_pair() {
  local expected_name="$1"
  local expected_value="$2"

  shift 2
  while (( $# > 0 )); do
    if [[ "$1" == "${expected_name}" ]]; then
      [[ "${2:-}" == "${expected_value}" ]] && return 0
      break
    fi
    shift
  done

  printf 'Mock %s did not receive required argument pair %s %s.\n' \
    "${tool_name}" "${expected_name}" "${expected_value}" >&2
  exit 95
}

assert_secret_files() {
  [[ -f "${RCLONE_CONFIG:?}" ]]
  [[ -f "${RESTIC_PASSWORD_FILE:?}" ]]
  [[ "$(stat -c '%a' "${RCLONE_CONFIG}")" == "600" ]]
  [[ "$(stat -c '%a' "${RESTIC_PASSWORD_FILE}")" == "600" ]]
  grep -Fxq 'scope = drive.file' "${RCLONE_CONFIG}"

  if grep -Fxq 'scope = drive' "${RCLONE_CONFIG}"; then
    printf 'Generated rclone config widened the Google Drive scope.\n' >&2
    exit 96
  fi
}

assert_no_secret_arguments() {
  if [[ "$*" == *secret-sentinel* ]]; then
    printf 'Mock %s received a secret as a process argument.\n' "${tool_name}" >&2
    exit 90
  fi
}

assert_no_secret_arguments "$@"

case "${tool_name}" in
  mariadb-dump)
    printf 'mariadb-dump\n' >> "${call_log}"
    [[ "$1" == --defaults-extra-file=* ]]
    database_config="${1#--defaults-extra-file=}"
    [[ -f "${database_config}" ]]
    [[ "$(stat -c '%a' "${database_config}")" == "600" ]]
    assert_argument --single-transaction "$@"
    assert_argument --routines "$@"
    assert_argument --triggers "$@"
    assert_argument --events "$@"
    assert_argument --hex-blob "$@"

    if [[ "${MOCK_DUMP_FAIL:-false}" == "true" ]]; then
      printf 'mock dump failure\n' >&2
      exit 21
    fi

    printf 'CREATE DATABASE IF NOT EXISTS `galotxas`;\nUSE `galotxas`;\nSELECT 1;\n'
    ;;
  rclone)
    printf 'rclone %s\n' "${1:-missing}" >> "${call_log}"
    assert_secret_files

    [[ "${1:-}" == "copy" ]]
    media_destination="${3:?}"
    mkdir -p "${media_destination}"

    if [[ "${MOCK_MEDIA_EMPTY:-false}" != "true" ]]; then
      printf 'mock-media-content' > "${media_destination}/object.bin"
    fi
    ;;
  restic)
    subcommand="${1:-missing}"
    printf 'restic %s\n' "${subcommand}" >> "${call_log}"
    assert_secret_files
    printf '%s' "${RCLONE_CONFIG}" > "${MOCK_RUNTIME_PATH_LOG:?}"

    case "${subcommand}" in
      snapshots|check)
        ;;
      backup)
        assert_argument_pair --host galotxas-production-backup "$@"
        assert_argument_pair --tag galotxas-production "$@"
        assert_argument_pair --group-by host,tags "$@"

        if [[ "${MOCK_RESTIC_BACKUP_FAIL:-false}" == "true" ]]; then
          printf 'mock backup failure\n' >&2
          exit 22
        fi

        payload_path="${!#}"
        [[ -s "${payload_path}/database/galotxas.sql" ]]
        [[ -f "${payload_path}/manifest.json" ]]
        if grep -Eq 'secret-sentinel|oauth-secret-sentinel|refresh-secret-sentinel' \
          "${payload_path}/manifest.json"; then
          printf 'Backup manifest contains a secret.\n' >&2
          exit 94
        fi
        grep -Eq '"database_dump_sha256": "[0-9a-f]{64}"' \
          "${payload_path}/manifest.json"
        grep -Fq '"media_file_count":' "${payload_path}/manifest.json"
        if [[ "${MOCK_MEDIA_EMPTY:-false}" == "true" ]]; then
          grep -Fq '"media_file_count": 0' "${payload_path}/manifest.json"
        else
          grep -Fq '"media_file_count": 1' "${payload_path}/manifest.json"
        fi
        printf '%s' "${payload_path}" > "${MOCK_PAYLOAD_PATH_LOG:?}"
        : > "${MOCK_SNAPSHOT_MARKER:?}"
        ;;
      forget)
        [[ -f "${MOCK_SNAPSHOT_MARKER:?}" ]]
        assert_argument_pair --host galotxas-production-backup "$@"
        assert_argument_pair --tag galotxas-production "$@"
        assert_argument_pair --group-by host,tags "$@"
        assert_argument --keep-daily "$@"
        assert_argument 14 "$@"
        assert_argument --keep-weekly "$@"
        assert_argument 8 "$@"
        assert_argument --keep-monthly "$@"
        assert_argument 12 "$@"
        assert_argument --prune "$@"

        if [[ "${MOCK_RETENTION_FAIL:-false}" == "true" ]]; then
          printf 'mock retention failure\n' >&2
          exit 23
        fi
        ;;
      *)
        printf 'Unexpected restic subcommand.\n' >&2
        exit 92
        ;;
    esac
    ;;
  *)
    printf 'Unexpected mock tool %s.\n' "${tool_name}" >&2
    exit 93
    ;;
esac
