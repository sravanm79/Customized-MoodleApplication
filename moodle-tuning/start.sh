#!/bin/bash
# Mounted over /opt/bitnami/scripts/moodle/run.sh, so the Bitnami entrypoint still runs its normal setup first.
# Switches Apache from prefork+mod_php to event+PHP-FPM, starts PHP-FPM, then does what the original run.sh
# does: start cron and hand over to Apache. Runs on every container start (idempotent).
set -o errexit -o nounset -o pipefail

. /opt/bitnami/scripts/moodle-env.sh
. /opt/bitnami/scripts/libos.sh
. /opt/bitnami/scripts/liblog.sh
. /opt/bitnami/scripts/libservice.sh
. /opt/bitnami/scripts/libwebserver.sh

CONF=/opt/bitnami/apache/conf/httpd.conf
sed -i -E \
    -e 's|^LoadModule mpm_prefork_module|#LoadModule mpm_prefork_module|' \
    -e 's|^#LoadModule mpm_event_module|LoadModule mpm_event_module|' \
    -e 's|^LoadModule php_module|#LoadModule php_module|' \
    -e 's|^#LoadModule proxy_module|LoadModule proxy_module|' \
    -e 's|^#LoadModule proxy_fcgi_module|LoadModule proxy_fcgi_module|' \
    "$CONF"
grep -q 'moodle-tuning/apache-event.conf' "$CONF" \
    || echo 'Include "/opt/moodle-tuning/apache-event.conf"' >> "$CONF"

cp /opt/moodle-tuning/php-fpm-www.conf /opt/bitnami/php/etc/php-fpm.d/www.conf
mkdir -p /opt/bitnami/php/var/run /opt/bitnami/php/logs
info "** Starting PHP-FPM **"
/opt/bitnami/php/sbin/php-fpm --fpm-config /opt/bitnami/php/etc/php-fpm.conf --daemonize

_forwardTerm() {
    warn "Caught signal SIGTERM, passing it to child processes..."
    pgrep -P $$ | xargs kill -TERM 2>/dev/null
    wait
    exit $?
}
trap _forwardTerm TERM

if am_i_root; then
    info "** Starting cron **"
    cron_start
fi
exec "/opt/bitnami/scripts/$(web_server_type)/run.sh"
