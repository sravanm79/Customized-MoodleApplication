# Site address, HTTPS and student devices

The LMS has **one fixed address: `https://192.168.30.239`** (`$CFG->wwwroot` in `/bitnami/moodle/config.php`).

| Address | What happens |
| --- | --- |
| `https://192.168.30.239` | The LMS. |
| `https://192.168.30.239/jupyter/` | JupyterHub (opened from Jupyter activities, not directly). |
| `https://192.168.30.239/mail/` | Mailpit web inbox of all mail the LMS sends (user `admin`, password `MAILPIT_UI_PASSWORD` in `.env`). |
| `http://192.168.30.239:9999/lms-ca.crt` | Download of the LMS certificate authority (plain http on purpose, see below). |
| `http://localhost:9999`, `http://192.168.30.239:9999/...` | Old addresses: redirected to `https://192.168.30.239/`. |

Why fixed and why HTTPS:

- Safe Exam Browser config keys are cached per quiz for one address. A per-request address (the old setup) gave
  students on another hostname "config key" errors.
- Browsers and Safe Exam Browser only allow the webcam on `https://` (or on the machine's own `localhost`).
- A Jupyter iframe on an `https://` page must also be `https://` (mixed content is blocked), so JupyterHub is served
  through Moodle's Apache under `/jupyter/`.

## The certificate

The server is only reachable on the campus LAN, so no public certificate authority (Let's Encrypt, etc.) can issue
it a certificate. Instead the LMS has its own small certificate authority, **"IIIT Dharwad LMS Local CA"**:

- `tls/make-certs.sh 192.168.30.239` created the CA (10 years) and the server certificate (397 days, the longest
  browsers accept). The CA is *name-constrained*: it can only vouch for `192.168.x.x`, `127.x`, `localhost`, `*.local`
  and `iiitdwd.ac.in` names, so even a leaked CA key could not impersonate other websites.
- `tls/ca/` (CA private key) and `tls/server/` (server key) are git-ignored and stay on this server. Only
  `tls/public/iiitdwd-lms-ca.crt` is public.
- SHA-256 fingerprint of the CA, for students to compare:
  `28:72:CA:48:45:ED:47:6E:F0:A5:B2:03:E2:2B:EA:51:AD:32:A6:B4:39:EA:27:8E:1A:7D:F3:98:2A:66:0E:51`

**Renew** before the server certificate expires (5 Nov 2027), or **change the address** (new IP or a DNS name):

```bash
tls/make-certs.sh 192.168.30.239          # or the new IP / hostname; reuses the same CA
docker compose restart moodle
# New address only: update $CFG->wwwroot in /bitnami/moodle/config.php, MOODLE_ORIGIN (jupyterhub) and
# MOODLE_SITE_URL in docker-compose.yaml, mod_jupyter | hubpublicurl, then:
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php --all --purge --url=https://<new address>
```

Students do not need to reinstall anything after a renewal (same CA).

**Later, with a real DNS name** (e.g. `lms.iiitdwd.ac.in` from the institute's IT, with a certificate from their CA or
Let's Encrypt): put that certificate in `tls/server/fullchain.crt` / `server.key`, change the address as above, and
students no longer need the LMS CA at all.

## Installing the LMS certificate on a student device (once)

1. Download `http://192.168.30.239:9999/lms-ca.crt` (must be on the campus network).
2. Install it as a trusted **root** certificate authority:

| Device | Steps |
| --- | --- |
| Windows (Chrome, Edge, **Safe Exam Browser**) | Double-click the file → *Install Certificate* → *Current User* → *Place all certificates in the following store* → *Browse* → **Trusted Root Certification Authorities** → *Finish* → *Yes*. |
| macOS (Safari, Chrome, **Safe Exam Browser**) | Double-click → Keychain Access opens → add to **login** keychain → double-click "IIIT Dharwad LMS Local CA" → *Trust* → *When using this certificate*: **Always Trust** → close and enter your password. |
| Firefox (any OS) | Settings → Privacy & Security → *Certificates* → *View Certificates* → *Authorities* → *Import* → tick *Trust this CA to identify websites*. (Or set `security.enterprise_roots.enabled` to use the system store.) |
| Linux (Chrome) | `sudo cp lms-ca.crt /usr/local/share/ca-certificates/iiitdwd-lms-ca.crt && sudo update-ca-certificates`; for Chrome also: Settings → Privacy and security → Security → Manage certificates → Authorities → Import. |
| Android | Settings → Security → *Encryption & credentials* → *Install a certificate* → **CA certificate** → pick the file. |
| iPhone / iPad | Open the file in Safari → *Allow* → Settings → *Profile Downloaded* → *Install*; then Settings → General → About → **Certificate Trust Settings** → enable "IIIT Dharwad LMS Local CA". |

3. Open `https://192.168.30.239`: the browser shows a normal padlock, no warning. Safe Exam Browser uses the same
   system store on Windows and macOS, so it trusts the LMS too.

Students who skip this see a certificate warning in the browser and **cannot use Safe Exam Browser or the webcam**.
The quiz page's **Check my camera and exam browser** link tests all of this on their device.
