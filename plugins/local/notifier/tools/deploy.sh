#!/usr/bin/env bash
# Deploy local_notifier into the running Moodle container, run DB upgrade and purge caches.
set -euo pipefail

CONTAINER="${MOODLE_CONTAINER:-moodle_app}"
MOODLE_DIR="${MOODLE_DIR:-/bitnami/moodle}"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$MOODLE_DIR/local/notifier"

docker exec "$CONTAINER" rm -rf "$TARGET"
docker exec "$CONTAINER" mkdir -p "$TARGET"
tar -C "$PLUGIN_DIR" --exclude=tools -cf - . \
    | docker exec -i "$CONTAINER" tar -C "$TARGET" -xf -
docker exec "$CONTAINER" chown -R daemon:daemon "$TARGET"
docker exec "$CONTAINER" find "$TARGET" -type d -exec chmod 755 {} +
docker exec "$CONTAINER" find "$TARGET" -type f -exec chmod 644 {} +

docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive
docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/purge_caches.php"
echo "local_notifier deployed to $CONTAINER:$TARGET"
