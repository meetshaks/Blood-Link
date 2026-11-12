#!/usr/bin/env sh
set -eu

: "${BLOODLINK_DB_NAME:?Set BLOODLINK_DB_NAME}"
: "${BLOODLINK_DB_USER:?Set BLOODLINK_DB_USER}"
: "${BLOODLINK_BACKUP_DIR:?Set BLOODLINK_BACKUP_DIR}"

umask 077
mkdir -p "$BLOODLINK_BACKUP_DIR"
timestamp=$(date -u +%Y%m%dT%H%M%SZ)
output="$BLOODLINK_BACKUP_DIR/${BLOODLINK_DB_NAME}-${timestamp}.sql.gz"
dump=$(mktemp "${TMPDIR:-/tmp}/bloodlink-dump.XXXXXX")
trap 'rm -f "$dump"' EXIT

if [ -n "${BLOODLINK_DB_HOST:-}" ]; then
    mysqldump --single-transaction --triggers --default-character-set=utf8mb4 \
      -h "$BLOODLINK_DB_HOST" -u "$BLOODLINK_DB_USER" "$BLOODLINK_DB_NAME" \
      > "$dump"
else
    mysqldump --single-transaction --triggers --default-character-set=utf8mb4 \
      -u "$BLOODLINK_DB_USER" "$BLOODLINK_DB_NAME" \
      > "$dump"
fi
gzip -c "$dump" > "$output"

find "$BLOODLINK_BACKUP_DIR" -type f -name "${BLOODLINK_DB_NAME}-*.sql.gz" -mtime +30 -delete
printf 'Created backup: %s\n' "$output"
