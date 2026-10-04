#!/usr/bin/env bash
# Private certificate authority for the LMS and a server certificate for its fixed address.
#
#   tls/make-certs.sh 192.168.30.239            # first run: creates the CA and the server certificate
#   tls/make-certs.sh 192.168.30.239            # later runs: re-issues the server certificate (renewal / new IP)
#
# Students install tls/public/iiitdwd-lms-ca.crt once per device (download: http://<host>:9999/lms-ca.crt);
# after that browsers and Safe Exam Browser trust https://<host>. The CA key (tls/ca/) never leaves this server
# and is git-ignored: whoever holds it can impersonate any site to those devices.
set -euo pipefail
HOST="${1:?usage: $0 <server IP or hostname>}"
cd "$(dirname "$0")"
umask 077
mkdir -p ca public server

if [ ! -f ca/ca.key ]; then
    openssl genrsa -out ca/ca.key 4096
    openssl req -x509 -new -key ca/ca.key -sha256 -days 3650 -out public/iiitdwd-lms-ca.crt \
        -subj "/C=IN/O=IIIT Dharwad/OU=LMS/CN=IIIT Dharwad LMS Local CA" \
        -addext "basicConstraints=critical,CA:TRUE,pathlen:0" \
        -addext "keyUsage=critical,keyCertSign,cRLSign" \
        -addext "nameConstraints=critical,permitted;IP:192.168.0.0/255.255.0.0,permitted;IP:127.0.0.0/255.0.0.0,permitted;DNS:localhost,permitted;DNS:.local,permitted;DNS:iiitdwd.ac.in"
    chmod 644 public/iiitdwd-lms-ca.crt
fi

if [[ "$HOST" =~ ^[0-9.]+$ ]]; then SAN="IP:$HOST"; else SAN="DNS:$HOST"; fi
SAN="$SAN,DNS:localhost,IP:127.0.0.1"
openssl genrsa -out server/server.key 2048
openssl req -new -key server/server.key -subj "/O=IIIT Dharwad/CN=$HOST" -out server/server.csr
# 397 days: the longest validity Apple and Chrome accept for a server certificate.
openssl x509 -req -in server/server.csr -CA public/iiitdwd-lms-ca.crt -CAkey ca/ca.key -CAcreateserial \
    -CAserial ca/ca.srl -days 397 -sha256 -out server/server.crt -extfile <(printf '%s\n' \
    "basicConstraints=critical,CA:FALSE" "keyUsage=critical,digitalSignature,keyEncipherment" \
    "extendedKeyUsage=serverAuth" "subjectAltName=$SAN")
cat server/server.crt public/iiitdwd-lms-ca.crt > server/fullchain.crt
rm -f server/server.csr
chmod 644 server/server.crt server/fullchain.crt
chmod 600 server/server.key
openssl x509 -in server/server.crt -noout -subject -ext subjectAltName -enddate
echo "CA fingerprint (tell students, so they can check what they install):"
openssl x509 -in public/iiitdwd-lms-ca.crt -noout -fingerprint -sha256
