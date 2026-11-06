#!/usr/bin/env bash
# Create Flip ERP's .env (repo root, next to docker-compose.yml) with fresh,
# independent secrets. Run ON THE DROPLET from /root/flip-erp:
#   bash deploy/digitalocean/generate-env.sh ghcr.io/kembe699/fliperp:deploy-digitalocean
# Never overwrites an existing .env. Prints key NAMES only. Mode 600.
set -euo pipefail
IMAGE="${1:?usage: generate-env.sh <ghcr image ref>}"
ENV_FILE="$(cd "$(dirname "$0")/../.." && pwd)/.env"
[ -e "$ENV_FILE" ] && { echo "Refusing to overwrite existing $ENV_FILE" >&2; exit 1; }

PUBLIC_IP="$(curl -fsS --max-time 3 http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address || true)"
[ -n "$PUBLIC_IP" ] || read -r -p "Droplet public IP: " PUBLIC_IP

secret()   { openssl rand -base64 48 | tr -d '\n=' | tr '+/' '-_'; }
password() { openssl rand -base64 24 | tr -d '\n=' | tr '+/' '-_'; }

umask 077
cat > "$ENV_FILE" <<EOF
# Flip ERP — DigitalOcean TEST deployment. Generated $(date -u +%Y-%m-%dT%H:%M:%SZ).
# Unique to this deployment. Never commit or share this file.
IMAGE=${IMAGE}
APP_PUBLISH=8080
APP_URL=http://${PUBLIC_IP}:8080
APP_KEY=base64:$(openssl rand -base64 32 | tr -d '\n')

DB_DATABASE=fliperp_db
DB_USERNAME=fliperp_app
DB_PASSWORD=$(secret)
DB_ADMIN_PASSWORD=$(secret)

PLATFORM_ADMIN_PASSWORD=$(password)
EOF
chmod 600 "$ENV_FILE"
echo "Wrote $ENV_FILE (mode 600) with keys:"
grep -oE '^[A-Z_]+=' "$ENV_FILE" | tr -d '=' | sed 's/^/  - /'
echo "Platform admin: platform-admin@nilehc.tech / PLATFORM_ADMIN_PASSWORD (grep it from .env)."
