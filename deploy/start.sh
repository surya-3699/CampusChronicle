#!/bin/bash
# Entry point: starts PHP-FPM and Apache (event MPM) and keeps both supervised.
set -eu
[ "${RENDER:-}" = "true" ] || { echo "This entrypoint requires Render's HTTPS edge." >&2; exit 1; }
mountpoint -q /var/data || { echo "Attach a persistent disk at /var/data before starting." >&2; exit 1; }
[ "${PORT:-10000}" = "10000" ] || { echo "Configure PORT=10000." >&2; exit 1; }
mkdir -p /var/data/uploads /var/data/storage /var/data/sessions
# Seed bundled originals only once; never overwrite existing uploaded files.
if [ ! -f /var/data/.campus-seeded ]; then
    cp -an /opt/campus-upload-seed/. /var/data/uploads/
    touch /var/data/.campus-seeded
fi
# Always apply the release's upload execution protection.
cp /opt/campus-upload-seed/.htaccess /var/data/uploads/.htaccess
chown -R www-data:www-data /var/data/uploads /var/data/storage /var/data/sessions
chmod 700 /var/data/storage /var/data/sessions
export CC_STORAGE_PATH=/var/data/storage

# ---- Size the worker pool from the memory this container actually has ----
# (override with FPM_MAX_CHILDREN / GATEWAY_MAX_ACTIVE in the Render dashboard)
limit_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
for f in /sys/fs/cgroup/memory.max /sys/fs/cgroup/memory/memory.limit_in_bytes; do
    if [ -r "$f" ]; then
        v=$(cat "$f")
        case "$v" in
            ''|*[!0-9]*) ;;
            *) m=$((v / 1024 / 1024)); if [ "$m" -lt "$limit_mb" ]; then limit_mb=$m; fi ;;
        esac
    fi
done
if [ -z "${FPM_MAX_CHILDREN:-}" ]; then
    c=$(( (limit_mb - 200) / 40 ))        # ~40 MB per PHP worker, 200 MB for Apache + OS
    if [ "$c" -lt 4 ]; then c=4; fi
    if [ "$c" -gt 64 ]; then c=64; fi
    export FPM_MAX_CHILDREN=$c
fi
if [ -z "${GATEWAY_MAX_ACTIVE:-}" ]; then
    spare=$(( FPM_MAX_CHILDREN / 8 ))     # a few workers stay free to shed/queue the overflow quickly
    if [ "$spare" -lt 1 ]; then spare=1; fi
    export GATEWAY_MAX_ACTIVE=$(( FPM_MAX_CHILDREN - spare ))
fi
echo "[campus] memory=${limit_mb}MB fpm_children=${FPM_MAX_CHILDREN} gateway_active=${GATEWAY_MAX_ACTIVE}"

# ---- PHP-FPM ----
mkdir -p /run/php-fpm
chown www-data:www-data /run/php-fpm
php-fpm --nodaemonize &
FPM_PID=$!

# ---- Apache (event MPM) ----
set +u
. /etc/apache2/envvars
set -u
rm -f "${APACHE_PID_FILE:-/var/run/apache2/apache2.pid}"
mkdir -p "${APACHE_RUN_DIR:-/var/run/apache2}" "${APACHE_LOCK_DIR:-/var/lock/apache2}" "${APACHE_LOG_DIR:-/var/log/apache2}"
apache2 -D FOREGROUND &
APACHE_PID=$!

# Scheduled maintenance (certificates, galleries, token clean-up) every 10 minutes.
bash /var/www/html/deploy/maintenance-loop.sh &

# Optional daily encrypted off-site backup (only when BACKUP_REMOTE is configured).
if [ -n "${BACKUP_REMOTE:-}" ]; then
    bash /var/www/html/deploy/backup-loop.sh &
    echo "[campus] off-site backups enabled (daily to ${BACKUP_REMOTE%%:*})"
fi

stopping=0
trap 'stopping=1; kill -TERM "$FPM_PID" "$APACHE_PID" 2>/dev/null || true' TERM INT

# If either process dies the container exits, so the platform restarts it.
code=0
wait -n || code=$?
kill -TERM "$FPM_PID" "$APACHE_PID" 2>/dev/null || true
wait || true
if [ "$stopping" = "1" ]; then exit 0; fi
echo "[campus] a server process exited unexpectedly (status $code)" >&2
exit 1
