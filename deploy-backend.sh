#!/usr/bin/env bash
# Builds PHP deps and uploads the backend (api/, cron/, database/migrations, composer files,
# index.php, .htaccess) to the server via lftp. Never touches htdocs/assets or htdocs/database's
# live sqlite file — those are owned by deploy-frontend.sh and the app itself, respectively.
# Setup: copy .deploy.env.sample to .deploy.env and fill in your deployment details, including
# DEPLOY_BACKEND_REMOTE_PATH (the remote webroot, e.g. /httpdocs).
# Usage: ./deploy-backend.sh [-y|--yes]   (-y skips the confirmation prompt)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.deploy.env"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "Missing $CONFIG_FILE — copy .deploy.env.sample to .deploy.env and fill in your deployment details." >&2
  exit 1
fi

# Deliberately NOT `source`d — see deploy-frontend.sh for why. Read as plain KEY=VALUE lines.
while IFS='=' read -r key value || [ -n "$key" ]; do
  case "$key" in
    ''|'#'*) continue ;;
  esac
  export "$key=$value"
done < "$CONFIG_FILE"

: "${DEPLOY_HOST:?DEPLOY_HOST is not set in .deploy.env}"
: "${DEPLOY_USER:?DEPLOY_USER is not set in .deploy.env}"
: "${DEPLOY_BACKEND_REMOTE_PATH:?DEPLOY_BACKEND_REMOTE_PATH is not set in .deploy.env}"
DEPLOY_PROTOCOL="${DEPLOY_PROTOCOL:-ftps}"
DEPLOY_PASSWORD="${DEPLOY_PASSWORD:-}"
DEPLOY_PORT="${DEPLOY_PORT:-}"
DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY:-}"
DEPLOY_VERIFY_CERT="${DEPLOY_VERIFY_CERT:-yes}"

LOCAL_DIR="$SCRIPT_DIR/htdocs"
REMOTE="${DEPLOY_BACKEND_REMOTE_PATH%/}"

echo "==> Installing PHP dependencies (production only)..."
(cd "$LOCAL_DIR" && composer install --no-dev --optimize-autoloader --no-interaction)

echo
echo "About to upload backend source to:"
echo "  local:  $LOCAL_DIR"
echo "  remote: $DEPLOY_PROTOCOL://$DEPLOY_USER@$DEPLOY_HOST${DEPLOY_PORT:+:$DEPLOY_PORT}$REMOTE"
echo
echo "Directories mirrored with deletion of stale remote files (api/, cron/, vendor/,"
echo "database/migrations/ only):"
echo "  api/  cron/  vendor/  database/migrations/"
echo "Individual files uploaded (overwritten, nothing else touched):"
echo "  composer.json  composer.lock  index.php  .htaccess"
echo
echo "NOT touched: assets/ (owned by deploy-frontend.sh) and database/*.sqlite (the live DB)."
echo

if [[ "${1:-}" != "-y" && "${1:-}" != "--yes" ]]; then
  read -r -p "Continue? [y/N] " confirm
  if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
  fi
fi

LFTP_OPTS=()
[ -n "$DEPLOY_PORT" ] && LFTP_OPTS+=(-p "$DEPLOY_PORT")

if [ "$DEPLOY_PROTOCOL" = "sftp" ] && [ -z "$DEPLOY_PASSWORD" ]; then
  SITE="sftp://${DEPLOY_USER}@${DEPLOY_HOST}"
else
  LFTP_OPTS+=(-u "$DEPLOY_USER,$DEPLOY_PASSWORD")
  if [ "$DEPLOY_PROTOCOL" = "ftps" ]; then
    SITE="ftp://$DEPLOY_HOST"
  else
    SITE="$DEPLOY_PROTOCOL://$DEPLOY_HOST"
  fi
fi

STARTUP_CMDS="set ssl:verify-certificate $DEPLOY_VERIFY_CERT;"
if [ "$DEPLOY_PROTOCOL" = "ftps" ]; then
  STARTUP_CMDS+=" set ftp:ssl-force true; set ftp:ssl-protect-data true;"
fi
if [ -n "$DEPLOY_SSH_KEY" ]; then
  STARTUP_CMDS+=" set sftp:connect-program \"ssh -a -x -i $DEPLOY_SSH_KEY\";"
fi

MIRROR_CMDS=""
for dir in api cron vendor database/migrations; do
  MIRROR_CMDS+="mirror --reverse --delete --verbose \"$LOCAL_DIR/$dir\" \"$REMOTE/$dir\"; "
done

PUT_CMDS=""
for f in composer.json composer.lock index.php .htaccess; do
  PUT_CMDS+="put -O \"$REMOTE\" \"$LOCAL_DIR/$f\"; "
done

lftp "${LFTP_OPTS[@]}" \
  -e "$STARTUP_CMDS $MIRROR_CMDS $PUT_CMDS bye" \
  "$SITE"

echo "==> Deployed."
