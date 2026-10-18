#!/bin/bash
# Runs tools/backup-offsite.sh once a day. Started by deploy/start.sh only when BACKUP_REMOTE is set.
# BACKUP_HOUR_UTC (default 21 = 02:30 India time) chooses the hour; minutes are fixed at :30.
# A failed run is logged and retried the next day; it never affects the website.
HOUR="${BACKUP_HOUR_UTC:-21}"
while true; do
  now=$(date -u +%s)
  target=$(date -u -d "today ${HOUR}:30" +%s)
  if [ "$target" -le "$now" ]; then target=$(date -u -d "tomorrow ${HOUR}:30" +%s); fi
  sleep $(( target - now ))
  bash /var/www/html/tools/backup-offsite.sh || echo "[backup] FAILED - see messages above" >&2
done
