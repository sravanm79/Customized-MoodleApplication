#!/usr/bin/env bash
# Writes Mailpit's secret files from .env (run after changing them, then: docker compose up -d mailpit).
#   MAILPIT_UI_PASSWORD   password of user "admin" for the web inbox (generated on first run)
#   SMTP_RELAY_HOST       e.g. smtp.gmail.com or smtp-relay.brevo.com; empty = keep mail in Mailpit only
#   SMTP_RELAY_PORT       587
#   SMTP_RELAY_USERNAME   Gmail address / Brevo login
#   SMTP_RELAY_PASSWORD   Gmail app password / Brevo SMTP key
#   SMTP_RELAY_FROM       sender address the provider accepts (Gmail: the same Gmail address)
#   SMTP_RELAY_ONLY_TO    regular expression of recipients that get real mail (default: @iiitdwd.ac.in addresses);
#                         use . to deliver to everyone
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
mkdir -p mailpit/secrets
get() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- || true; }

pw=$(get MAILPIT_UI_PASSWORD)
if [ -z "$pw" ]; then
    pw=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
    printf '\n# Web inbox of Mailpit (https://<moodle>/mail/, user admin).\nMAILPIT_UI_PASSWORD=%s\n' "$pw" >> .env
fi
printf 'admin:%s\n' "$(openssl passwd -apr1 "$pw")" > mailpit/secrets/ui-auth

host=$(get SMTP_RELAY_HOST)
if [ -n "$host" ]; then
    port=$(get SMTP_RELAY_PORT); port=${port:-587}
    cat > mailpit/secrets/relay.yaml <<YAML
host: $host
port: ${port}
starttls: true
auth: plain
username: $(get SMTP_RELAY_USERNAME)
password: $(get SMTP_RELAY_PASSWORD)
override-from: $(get SMTP_RELAY_FROM)
YAML
    match=$(get SMTP_RELAY_ONLY_TO); match=${match:-'@iiitdwd\.ac\.in$'}
    printf '%s' "$match" > mailpit/secrets/relay-match
    echo "Relay on: emails to recipients matching $match are delivered through $host; all are kept in Mailpit."
else
    rm -f mailpit/secrets/relay.yaml mailpit/secrets/relay-match
    echo "Relay off: emails are kept in the Mailpit inbox only."
fi
chmod 644 mailpit/secrets/ui-auth; for f in mailpit/secrets/relay.yaml mailpit/secrets/relay-match; do [ -f "$f" ] && chmod 644 "$f"; done
chmod 755 mailpit/secrets
