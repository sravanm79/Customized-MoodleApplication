#!/usr/bin/env bash
# Copy plugins from plugins/ into the running Moodle container, then run the Moodle upgrade and purge caches once.
#
#   tools/deploy-plugins.sh                       # every plugin under plugins/
#   tools/deploy-plugins.sh local/helpdesk mod/attendance plagiarism/similarity
#
# Paths are relative to plugins/ and map to Moodle's layout (quiz/accessrule/x -> mod/quiz/accessrule/x).
# Moodle CLI runs as the web server user (daemon): running it as root leaves root-owned files in moodledata (HTTP 500).
set -euo pipefail

CONTAINER="${MOODLE_CONTAINER:-moodle_app}"
MOODLE_DIR="${MOODLE_DIR:-/bitnami/moodle}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

target_for() {
    case "$1" in
        quiz/accessrule/*) echo "mod/$1" ;;
        local/*|mod/*|theme/*|plagiarism/*|question/type/*|question/behaviour/*) echo "$1" ;;
        *) echo "Unknown plugin location: $1" >&2; return 1 ;;
    esac
}

if [ "$#" -eq 0 ]; then
    set -- $(cd "$ROOT/plugins" && find . -mindepth 2 -maxdepth 4 -name version.php -printf '%h\n' | sed 's|^\./||' | grep -E '^(local|mod|theme|plagiarism)/[^/]+$|^quiz/accessrule/[^/]+$|^question/(type|behaviour)/[^/]+$' | sort)
fi

for plugin in "$@"; do
    plugin="${plugin#plugins/}"
    plugin="${plugin%/}"
    src="$ROOT/plugins/$plugin"
    [ -f "$src/version.php" ] || { echo "No version.php in $src" >&2; exit 1; }
    dest="$MOODLE_DIR/$(target_for "$plugin")"
    echo "==> $plugin -> $dest"
    docker exec "$CONTAINER" rm -rf "$dest"
    docker exec "$CONTAINER" mkdir -p "$dest"
    tar -C "$src" --exclude=node_modules --exclude=tools --exclude=.git --exclude=.git-upstream \
        --exclude=package.json --exclude=package-lock.json --exclude=.stylelintrc.json -cf - . \
        | docker exec -i "$CONTAINER" tar -C "$dest" -xf -
    docker exec "$CONTAINER" chown -R daemon:daemon "$dest"
    docker exec "$CONTAINER" find "$dest" -type d -exec chmod 755 {} +
    docker exec "$CONTAINER" find "$dest" -type f -exec chmod 644 {} +
done

echo "==> Moodle upgrade"
docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive
echo "==> Purge caches"
docker exec -u daemon "$CONTAINER" php "$MOODLE_DIR/admin/cli/purge_caches.php"
echo "Done. moodledata files not owned by daemon (expect 0): $(docker exec "$CONTAINER" find /bitnami/moodledata ! -user daemon | wc -l)"
