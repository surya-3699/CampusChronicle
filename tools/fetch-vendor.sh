#!/bin/sh
# Downloads pinned copies of Bootstrap and Bootstrap Icons into public/vendor/ so pages do not depend on a
# third-party CDN at runtime. Runs during the Docker build; safe to run by hand.
#   sh tools/fetch-vendor.sh [destination]          (default: public/vendor)
# All files are fetched into a temporary folder first and installed only if EVERY download succeeds and
# looks right, so the icon stylesheet can never end up installed without its fonts. If anything fails the
# script exits non-zero and installs nothing; the pages then use the CDN automatically.
set -eu
BASE="${VENDOR_BASE:-https://cdn.jsdelivr.net/npm}"
DEST="${1:-public/vendor}"
BS="bootstrap@5.3.3"
ICONS="bootstrap-icons@1.11.3"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/bootstrap" "$TMP/bootstrap-icons/fonts"
get() { curl -fsSL --retry 3 --connect-timeout 10 --max-time 90 -o "$2" "$1" && [ -s "$2" ]; }

get "$BASE/$BS/dist/css/bootstrap.min.css"                  "$TMP/bootstrap/bootstrap.min.css"
get "$BASE/$ICONS/font/bootstrap-icons.min.css"             "$TMP/bootstrap-icons/bootstrap-icons.min.css"
get "$BASE/$ICONS/font/fonts/bootstrap-icons.woff2"         "$TMP/bootstrap-icons/fonts/bootstrap-icons.woff2"
get "$BASE/$ICONS/font/fonts/bootstrap-icons.woff"          "$TMP/bootstrap-icons/fonts/bootstrap-icons.woff"

# Sanity checks: right kind of file, not an error page.
grep -q "Bootstrap" "$TMP/bootstrap/bootstrap.min.css"
grep -q "bootstrap-icons" "$TMP/bootstrap-icons/bootstrap-icons.min.css"
[ "$(head -c 4 "$TMP/bootstrap-icons/fonts/bootstrap-icons.woff2")" = "wOF2" ]
[ "$(head -c 4 "$TMP/bootstrap-icons/fonts/bootstrap-icons.woff")" = "wOFF" ]

rm -rf "$DEST/bootstrap" "$DEST/bootstrap-icons"
mkdir -p "$DEST"
cp -r "$TMP/bootstrap" "$TMP/bootstrap-icons" "$DEST/"
echo "Installed $BS and $ICONS into $DEST"
