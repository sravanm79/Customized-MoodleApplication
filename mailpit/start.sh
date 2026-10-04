#!/bin/sh
# Mailpit: Moodle's outgoing mail server. Every email is kept in the web inbox (https://<moodle>/mail/); when
# mailpit/secrets/relay.yaml exists (mailpit/configure.sh, SMTP_RELAY_* in .env) every email is also delivered
# through that external SMTP account, but only to recipients matching mailpit/secrets/relay-match (default: addresses
# @iiitdwd.ac.in), so test accounts with made-up or other people's addresses never receive real mail.
set -eu
# Webroot, inbox password file, database and retention come from MP_* variables in docker-compose.yaml.
set -- /mailpit
if [ -s /secrets/relay.yaml ]; then
    set -- "$@" --smtp-relay-config /secrets/relay.yaml --smtp-relay-matching "$(cat /secrets/relay-match)"
fi
exec "$@"
