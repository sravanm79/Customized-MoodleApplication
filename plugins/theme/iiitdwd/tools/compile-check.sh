#!/usr/bin/env bash
# Compile the theme's SCSS with Moodle's own compiler inside the container, without installing anything.
# Fails (non-zero exit) on any SCSS error.
set -euo pipefail

CONTAINER="${MOODLE_CONTAINER:-moodle_app}"
MOODLE_DIR="${MOODLE_DIR:-/bitnami/moodle}"
THEME_DIR="$(cd "$(dirname "$0")/.." && pwd)"
# Staged under theme/ because Moodle ignores @import of files outside it; the dot name is not a valid plugin name,
# so Moodle does not treat it as a theme.
TMP="$MOODLE_DIR/theme/.iiitdwd-compilecheck"

docker exec "$CONTAINER" rm -rf "$TMP"
docker exec "$CONTAINER" mkdir -p "$TMP"
tar -C "$THEME_DIR" -cf - scss tools/compile-check.php | docker exec -i "$CONTAINER" tar -C "$TMP" -xf -
docker exec "$CONTAINER" chown -R daemon:daemon "$TMP"
status=0
docker exec -u daemon "$CONTAINER" php "$TMP/tools/compile-check.php" "$MOODLE_DIR" "$TMP" || status=$?
docker exec "$CONTAINER" rm -rf "$TMP"
exit $status
