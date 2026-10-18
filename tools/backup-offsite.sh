#!/bin/bash
# Encrypted OFF-SITE backup: MySQL dump + uploaded files -> any rclone remote (S3, R2, B2, Spaces, ...).
# Consistent without maintenance mode (single-transaction dump). Runs daily from deploy/start.sh when
# BACKUP_REMOTE is set, or by hand:  bash tools/backup-offsite.sh
#
# Required:  BACKUP_REMOTE      rclone path, e.g.  offsite:my-bucket/campus-chronicle
#            BACKUP_PASSPHRASE  long random secret used to encrypt the files (keep a copy somewhere safe;
#                               without it the backups cannot be opened)
#            DB_HOST DB_NAME DB_USER DB_PASSWORD   (already set for the app)
# Optional:  BACKUP_UPLOADS_DIR (/var/data/uploads)  BACKUP_RETENTION_DAYS (30)  DB_PORT DB_SSL DB_SSL_CA DB_SSL_VERIFY
# rclone remotes can be configured purely with environment variables, e.g. for Cloudflare R2:
#   RCLONE_CONFIG_OFFSITE_TYPE=s3  RCLONE_CONFIG_OFFSITE_PROVIDER=Cloudflare
#   RCLONE_CONFIG_OFFSITE_ACCESS_KEY_ID=...  RCLONE_CONFIG_OFFSITE_SECRET_ACCESS_KEY=...
#   RCLONE_CONFIG_OFFSITE_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
#
# Restore (see docs/DEPLOYMENT.md):
#   openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE -in db-....sql.gz.enc | gunzip | mysql ...
set -euo pipefail

: "${BACKUP_REMOTE:?Set BACKUP_REMOTE, e.g. offsite:my-bucket/campus-chronicle}"
: "${BACKUP_PASSPHRASE:?Set BACKUP_PASSPHRASE (a long random secret)}"
: "${DB_HOST:?}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD:?}"
UPLOADS="${BACKUP_UPLOADS_DIR:-/var/data/uploads}"
KEEP_DAYS="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

umask 077
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
OUT="$WORK/out"; mkdir -p "$OUT"          # only this folder is ever uploaded

DUMP="${DUMP_BIN:-$(command -v mariadb-dump || command -v mysqldump || true)}"
[ -n "$DUMP" ] || { echo "[backup] no mysqldump/mariadb-dump found" >&2; exit 1; }

# Credentials go in a private option file (never on the command line, where other processes could read them).
pw="${DB_PASSWORD//\\/\\\\}"; pw="${pw//\"/\\\"}"
CNF="$WORK/client.cnf"
{
  echo "[client]"; echo "host=$DB_HOST"; echo "port=${DB_PORT:-3306}"; echo "user=$DB_USER"; echo "password=\"$pw\""
  if [ "${DB_SSL:-0}" = "1" ]; then
    echo "ssl-ca=${DB_SSL_CA:-/etc/ssl/certs/ca-certificates.crt}"
    [ "${DB_SSL_VERIFY:-1}" = "0" ] || echo "ssl-verify-server-cert"
  fi
} > "$CNF"

echo "[backup] $STAMP dumping database"
"$DUMP" --defaults-extra-file="$CNF" --single-transaction --quick --skip-lock-tables --hex-blob \
        --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$WORK/db-$STAMP.sql.gz"
gzip -t "$WORK/db-$STAMP.sql.gz"
zcat "$WORK/db-$STAMP.sql.gz" | tail -n 5 | grep -q "Dump completed" || { echo "[backup] dump looks incomplete, aborting" >&2; exit 1; }

if [ -d "$UPLOADS" ]; then
  echo "[backup] archiving uploads"
  tar -C "$(dirname "$UPLOADS")" -czf "$WORK/uploads-$STAMP.tar.gz" "$(basename "$UPLOADS")"
fi

echo "[backup] encrypting"
for f in "$WORK"/db-*.gz "$WORK"/uploads-*.gz; do
  [ -e "$f" ] || continue
  openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE -in "$f" -out "$OUT/$(basename "$f").enc"
done

echo "[backup] uploading to $BACKUP_REMOTE/$STAMP"
rclone copy "$OUT" "$BACKUP_REMOTE/$STAMP" --transfers 2
uploaded="$(rclone lsf "$BACKUP_REMOTE/$STAMP" | wc -l)"
expected="$(ls "$OUT" | wc -l)"
[ "$uploaded" -eq "$expected" ] || { echo "[backup] upload verification failed ($uploaded of $expected files)" >&2; exit 1; }

# Retention: remove backups older than KEEP_DAYS (only after this one is safely uploaded).
rclone delete "$BACKUP_REMOTE" --min-age "${KEEP_DAYS}d" || echo "[backup] retention clean-up skipped" >&2
rclone rmdirs "$BACKUP_REMOTE" --leave-root || true
echo "[backup] done: $STAMP ($expected files)"
