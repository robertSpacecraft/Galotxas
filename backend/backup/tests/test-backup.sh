#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
BACKUP_SCRIPT="${BACKUP_DIR}/backup.sh"
ENV_EXAMPLE="${BACKUP_DIR}/.env.example"
MOCK_TOOL="${SCRIPT_DIR}/mock-tool.sh"
TEMP_DIR="$(mktemp -d)"
MOCK_BIN="${TEMP_DIR}/bin"

cleanup() {
  rm -rf -- "${TEMP_DIR}"
}

trap cleanup EXIT

mkdir -p "${MOCK_BIN}"
for command_name in mariadb-dump rclone restic; do
  ln -s "${MOCK_TOOL}" "${MOCK_BIN}/${command_name}"
done

pass_count=0

pass() {
  printf 'PASS: %s\n' "$1"
  pass_count=$((pass_count + 1))
}

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

assert_no_secret_output() {
  local output_file="$1"

  if grep -Eq 'db-secret-sentinel|media-secret-sentinel|gdrive-secret-sentinel|oauth-secret-sentinel|refresh-secret-sentinel|restic-secret-sentinel' \
    "${output_file}"; then
    fail "a secret was written to process output"
  fi
}

reset_mock_state() {
  : > "${TEMP_DIR}/calls.log"
  rm -f -- \
    "${TEMP_DIR}/payload-path" \
    "${TEMP_DIR}/runtime-path" \
    "${TEMP_DIR}/snapshot-created"
}

run_check_mode() {
  env \
    PATH="${MOCK_BIN}:/usr/bin:/bin" \
    HOME=/tmp \
    MOCK_CALL_LOG="${TEMP_DIR}/calls.log" \
    MOCK_PAYLOAD_PATH_LOG="${TEMP_DIR}/payload-path" \
    MOCK_RUNTIME_PATH_LOG="${TEMP_DIR}/runtime-path" \
    MOCK_SNAPSHOT_MARKER="${TEMP_DIR}/snapshot-created" \
    BACKUP_GDRIVE_CLIENT_ID=client-id \
    BACKUP_GDRIVE_CLIENT_SECRET=gdrive-secret-sentinel \
    BACKUP_GDRIVE_TOKEN='{"access_token":"oauth-secret-sentinel","refresh_token":"refresh-secret-sentinel"}' \
    BACKUP_RESTIC_PASSWORD=restic-secret-sentinel \
    BACKUP_RESTIC_REPOSITORY=rclone:gdrive-galotxes-backup:galotxes-backup-drivefile/production \
    "${BACKUP_SCRIPT}" check
}

run_backup_mode() {
  env \
    PATH="${MOCK_BIN}:/usr/bin:/bin" \
    HOME=/tmp \
    MOCK_CALL_LOG="${TEMP_DIR}/calls.log" \
    MOCK_PAYLOAD_PATH_LOG="${TEMP_DIR}/payload-path" \
    MOCK_RUNTIME_PATH_LOG="${TEMP_DIR}/runtime-path" \
    MOCK_SNAPSHOT_MARKER="${TEMP_DIR}/snapshot-created" \
    MOCK_DUMP_FAIL="${MOCK_DUMP_FAIL:-false}" \
    MOCK_MEDIA_EMPTY="${MOCK_MEDIA_EMPTY:-false}" \
    MOCK_RESTIC_BACKUP_FAIL="${MOCK_RESTIC_BACKUP_FAIL:-false}" \
    MOCK_RETENTION_FAIL="${MOCK_RETENTION_FAIL:-false}" \
    BACKUP_DB_HOST=mariadb.railway.internal \
    BACKUP_DB_PORT=3306 \
    BACKUP_DB_DATABASE=galotxas \
    BACKUP_DB_USERNAME=backup_user \
    BACKUP_DB_PASSWORD=db-secret-sentinel \
    BACKUP_MEDIA_BUCKET=media-production \
    BACKUP_MEDIA_ENDPOINT=https://storage.example.test \
    BACKUP_MEDIA_REGION=auto \
    BACKUP_MEDIA_ACCESS_KEY_ID=media-access-key \
    BACKUP_MEDIA_SECRET_ACCESS_KEY=media-secret-sentinel \
    BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT="${BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT-true}" \
    BACKUP_GDRIVE_CLIENT_ID=client-id \
    BACKUP_GDRIVE_CLIENT_SECRET=gdrive-secret-sentinel \
    BACKUP_GDRIVE_TOKEN='{"access_token":"oauth-secret-sentinel","refresh_token":"refresh-secret-sentinel"}' \
    BACKUP_RESTIC_PASSWORD=restic-secret-sentinel \
    BACKUP_RESTIC_REPOSITORY=rclone:gdrive-galotxes-backup:galotxes-backup-drivefile/production \
    "${BACKUP_SCRIPT}" backup
}

bash -n "${BACKUP_SCRIPT}" "${MOCK_TOOL}" "$0"
pass "shell syntax is valid"

grep -Fxq \
  'BACKUP_GDRIVE_REMOTE_NAME=gdrive-galotxes-backup' \
  "${ENV_EXAMPLE}" \
  || fail "the canonical Google Drive remote name changed"
grep -Fxq \
  'BACKUP_RESTIC_REPOSITORY=rclone:gdrive-galotxes-backup:galotxes-backup-drivefile/production' \
  "${ENV_EXAMPLE}" \
  || fail "the example does not target the drive.file repository"
if grep -Fq 'gdrive-galotxes-backup-drivefile-test' "${ENV_EXAMPLE}"; then
  fail "the temporary validation remote was versioned"
fi
pass "the example targets the new repository with the canonical remote"

reset_mock_state
set +e
env \
  PATH="${MOCK_BIN}:/usr/bin:/bin" \
  HOME=/tmp \
  BACKUP_RESTIC_PASSWORD=restic-secret-sentinel \
  "${BACKUP_SCRIPT}" check \
  > "${TEMP_DIR}/missing.out" 2>&1
missing_status=$?
set -e
[[ ${missing_status} -ne 0 ]] || fail "missing variables did not fail fast"
grep -Fq 'BACKUP_GDRIVE_CLIENT_ID' "${TEMP_DIR}/missing.out" \
  || fail "missing variable error was not actionable"
assert_no_secret_output "${TEMP_DIR}/missing.out"
pass "missing variables fail fast without leaking configured secrets"

reset_mock_state
set +e
BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT='' run_backup_mode \
  > "${TEMP_DIR}/missing-path-style.out" 2>&1
missing_path_style_status=$?
set -e
[[ ${missing_path_style_status} -ne 0 ]] \
  || fail "missing path-style contract returned success"
grep -Fq 'BACKUP_MEDIA_USE_PATH_STYLE_ENDPOINT' \
  "${TEMP_DIR}/missing-path-style.out" \
  || fail "missing path-style error was not actionable"
assert_no_secret_output "${TEMP_DIR}/missing-path-style.out"
pass "the production S3 path-style contract must be explicit"

reset_mock_state
run_check_mode > "${TEMP_DIR}/check.out" 2>&1
grep -Fxq 'restic check' "${TEMP_DIR}/calls.log" \
  || fail "check mode did not run restic check"
runtime_path="$(cat "${TEMP_DIR}/runtime-path")"
[[ ! -e "${runtime_path}" ]] || fail "check mode left its temporary secrets behind"
assert_no_secret_output "${TEMP_DIR}/check.out"
pass "check mode validates the encrypted repository without creating a backup"

reset_mock_state
run_backup_mode > "${TEMP_DIR}/backup.out" 2>&1
expected_calls=$'restic snapshots\nmariadb-dump\nrclone copy\nrestic backup\nrestic forget'
actual_calls="$(cat "${TEMP_DIR}/calls.log")"
[[ "${actual_calls}" == "${expected_calls}" ]] \
  || fail "backup operations did not run in the safe order"
payload_path="$(cat "${TEMP_DIR}/payload-path")"
[[ ! -e "${payload_path}" ]] || fail "plaintext backup payload was not removed"
runtime_path="$(cat "${TEMP_DIR}/runtime-path")"
[[ ! -e "${runtime_path}" ]] || fail "backup mode left its temporary secrets behind"
assert_no_secret_output "${TEMP_DIR}/backup.out"
pass "stable restic grouping, snapshot, retention and cleanup succeed with mocks"

reset_mock_state
MOCK_MEDIA_EMPTY=true run_backup_mode > "${TEMP_DIR}/empty-media.out" 2>&1
assert_no_secret_output "${TEMP_DIR}/empty-media.out"
pass "an empty media bucket remains a valid backup input"

reset_mock_state
set +e
MOCK_RESTIC_BACKUP_FAIL=true run_backup_mode \
  > "${TEMP_DIR}/backup-failure.out" 2>&1
backup_failure_status=$?
set -e
[[ ${backup_failure_status} -ne 0 ]] || fail "snapshot failure returned success"
if grep -Fxq 'restic forget' "${TEMP_DIR}/calls.log"; then
  fail "retention ran after a failed snapshot"
fi
assert_no_secret_output "${TEMP_DIR}/backup-failure.out"
pass "snapshot failure is observable and never starts retention"

reset_mock_state
set +e
MOCK_RETENTION_FAIL=true run_backup_mode \
  > "${TEMP_DIR}/retention-failure.out" 2>&1
retention_failure_status=$?
set -e
[[ ${retention_failure_status} -ne 0 ]] || fail "retention failure returned success"
[[ -f "${TEMP_DIR}/snapshot-created" ]] \
  || fail "retention failure lost evidence of the successful snapshot"
assert_no_secret_output "${TEMP_DIR}/retention-failure.out"
pass "retention failure is observable after preserving the new snapshot"

printf '%d backup job checks passed.\n' "${pass_count}"
