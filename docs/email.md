# Email

Moodle sends all mail (password resets, assignment and forum notifications, messages) to **Mailpit**
(`moodle_mailpit`, SMTP `mailpit:1025` inside Docker; Moodle setting *Site administration › Server › Email ›
Outgoing mail configuration › SMTP hosts* = `mailpit:1025`). Mailpit is free and open source.

## Web inbox

`https://192.168.30.239/mail/`: user `admin`, password `MAILPIT_UI_PASSWORD` in `.env`. Every email the LMS sends
appears here (kept 30 days, at most 5000), whether or not it is also delivered for real. Use it to test password
resets and notifications without real mailboxes, and to check what was sent to whom.

The inbox contains password-reset links: keep its password private.

## Real delivery (optional)

Real inboxes need a sending account; any free SMTP account works:

| Provider | Free limit | What to put in `.env` |
| --- | --- | --- |
| **Gmail** (a dedicated account, e.g. `iiitdwd.lms@gmail.com`) | ~500 recipients/day | Turn on 2-Step Verification, create an **App password** (Google Account › Security › App passwords). `SMTP_RELAY_HOST=smtp.gmail.com`, `SMTP_RELAY_PORT=587`, `SMTP_RELAY_USERNAME=<gmail address>`, `SMTP_RELAY_PASSWORD=<16-character app password>`, `SMTP_RELAY_FROM=<gmail address>` |
| **Brevo** (brevo.com) | 300 emails/day | Sign up, *SMTP & API › SMTP*: `SMTP_RELAY_HOST=smtp-relay.brevo.com`, `SMTP_RELAY_PORT=587`, `SMTP_RELAY_USERNAME=<SMTP login>`, `SMTP_RELAY_PASSWORD=<SMTP key>`, `SMTP_RELAY_FROM=<verified sender address>` |
| Institute mail server | per IT policy | Ask IT for host, port 587 and an account. |

Then:

```bash
mailpit/configure.sh            # writes mailpit/secrets/relay.yaml (git-ignored)
docker compose up -d --force-recreate mailpit
docker logs moodle_mailpit | grep relay   # "auto-relaying new messages to recipients matching ..."
```

**Only addresses matching `SMTP_RELAY_ONLY_TO` receive real mail** (default `@iiitdwd\.ac\.in$`). Test accounts
such as `teacher@gmail.com` or `s1@gmail.com` may belong to real strangers; they keep getting mail only in the
Mailpit inbox. Set `SMTP_RELAY_ONLY_TO=.` to deliver to everyone once all accounts have real addresses.

Moodle sends everything from `noreply@iiitdwd.ac.in` (*Email › No-reply address*, and *Only send from the no-reply
address* is on, so mail never pretends to come from a user's own address). With Gmail or Brevo the provider shows the
`SMTP_RELAY_FROM` address as sender.

## Check

*Site administration › Server › Email › Test outgoing mail configuration* → send to any address → it appears in the
Mailpit inbox (and, with relay on and a matching address, in the real inbox).
