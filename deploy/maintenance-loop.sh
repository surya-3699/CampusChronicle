#!/bin/bash
# Runs the scheduled maintenance task every 10 minutes: publishes galleries for finished events, issues
# participation certificates, and removes expired password-reset tokens. A separate cron service cannot
# mount this container's disk, so the task runs here. tools/maintenance.php takes a database advisory lock,
# so overlapping runs (or several instances) are harmless.
# A failure is logged and retried on the next cycle; it never affects the website.
sleep 120   # let PHP-FPM and the database settle after a deploy
while true; do
  runuser -u www-data -- php /var/www/html/tools/maintenance.php || echo "[maintenance] run failed - see messages above" >&2
  sleep 600
done
