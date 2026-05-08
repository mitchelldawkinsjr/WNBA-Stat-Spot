#!/usr/bin/env bash
# One-time host prep for docker-compose.prod.yml (run on the VPS with sudo as needed).
set -euo pipefail

NET="${DOCKER_NETWORK:-360ws-network}"
DEPLOY_ROOT="${DEPLOY_ROOT:-/opt/360ws/clients/docker-app}"
APP_DIR="${APP_DIR:-${DEPLOY_ROOT}/wnba-stat-spot}"
REPO_URL="${REPO_URL:-https://github.com/mitchelldawkinsjr/WNBA-Stat-Spot.git}"

if ! docker network inspect "$NET" >/dev/null 2>&1; then
  echo "Creating Docker network: $NET"
  docker network create "$NET"
else
  echo "Docker network exists: $NET"
fi

mkdir -p "$DEPLOY_ROOT"
echo "Deploy root ready: $DEPLOY_ROOT"

if [[ ! -d "$APP_DIR/.git" ]]; then
  echo "Clone the repo (SSH URL if HTTPS is blocked):"
  echo "  git clone \"$REPO_URL\" \"$APP_DIR\""
  echo "Then:"
  echo "  cd \"$APP_DIR\""
  echo "  cp deploy/env.production.example .env   # edit APP_KEY, DB_PASSWORD, APP_URL, …"
  echo "  docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --force"
  echo "  docker compose -f docker-compose.prod.yml up -d --build"
else
  echo "Repo already present: $APP_DIR"
fi
