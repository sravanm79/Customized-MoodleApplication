#!/usr/bin/env bash
# Copy theme_iiitdwd into the running Moodle container, register it and purge caches.
# Runs Moodle CLI as daemon: running it as root leaves root-owned files in moodledata and the site returns 500.
set -euo pipefail

CONTAINER="${MOODLE_CONTAINER:-moodle_app}"
MOODLE_DIR="${MOODLE_DIR:-/bitnami/moodle}"
THEME_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$MOODLE_DIR/theme/iiitdwd"

# Ship only the plugin, not the dev tooling.
docker exec "$CONTAINER" rm -rf "$TARGET"
docker exec "$CONTAINER" mkdir -p "$TARGET"
tar -C "$THEME_DIR" \
    --exclude=node_modules --exclude=tools --exclude=package.json --exclude=package-lock.json \
    --exclude=.stylelintrc.json --exclude=README.md \
    -cf - . | docker exec -i "$CONTAINER" tar -C "$TARGET" -xf -
docker exec "$CONTAINER" chown -R daemon:daemon "$TARGET"
docker exec "$CONTAINER" find "$TARGET" -type d -exec chmod 755 {} +
docker exec "$CONTAINER" find "$TARGET" -type f -exec chmod 644 {} +

docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive
docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/purge_caches.php"
echo "theme_iiitdwd deployed to $CONTAINER:$TARGET"
