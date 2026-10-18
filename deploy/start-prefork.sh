#!/bin/sh
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
exec apache2-foreground
