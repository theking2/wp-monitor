#!/usr/bin/env bash
# Builds the Vue3 frontend and uploads htdocs/assets/app to the server via lftp.
# Setup: copy .deploy.env.sample to .deploy.env and fill in your deployment details.
# Usage: ./deploy-frontend.sh [-y|--yes]   (-y skips the confirmation prompt)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.deploy.env"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "Missing $CONFIG_FILE — copy .deploy.env.sample to .deploy.env and fill in your deployment details." >&2
  exit 1
fi

# Deliberately NOT `source`d: that treats the file as shell script, so any password containing
# a space, $, backtick, quote, etc. either breaks parsing or gets shell-interpreted. Read it as
# plain KEY=VALUE lines instead — same "value taken literally, no quoting" contract as the app's
# own .env loader (htdocs/api/bootstrap.php) — so it can't misbehave on real-world passwords.
while IFS='=' read -r key value || [ -n "$key" ]; do
  case "$key" in
    ''|'#'*) continue ;;
  esac
  export "$key=$value"
done < "$CONFIG_FILE"

: "${DEPLOY_HOST:?DEPLOY_HOST is not set in .deploy.env}"
: "${DEPLOY_USER:?DEPLOY_USER is not set in .deploy.env}"
: "${DEPLOY_REMOTE_PATH:?DEPLOY_REMOTE_PATH is not set in .deploy.env}"
DEPLOY_PROTOCOL="${DEPLOY_PROTOCOL:-ftps}"
DEPLOY_PASSWORD="${DEPLOY_PASSWORD:-}"
DEPLOY_PORT="${DEPLOY_PORT:-}"
DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY:-}"
DEPLOY_VERIFY_CERT="${DEPLOY_VERIFY_CERT:-yes}"

LOCAL_DIR="$SCRIPT_DIR/htdocs/assets/app"

echo "==> Building frontend..."
(cd "$SCRIPT_DIR/frontend" && npm run build)

if [ ! -d "$LOCAL_DIR" ]; then
  echo "Build output not found at $LOCAL_DIR" >&2
  exit 1
fi

echo
echo "About to mirror (uploads changed files, DELETES anything remote not present locally):"
echo "  local:  $LOCAL_DIR"
echo "  remote: $DEPLOY_PROTOCOL://$DEPLOY_USER@$DEPLOY_HOST${DEPLOY_PORT:+:$DEPLOY_PORT}$DEPLOY_REMOTE_PATH"
echo

if [[ "${1:-}" != "-y" && "${1:-}" != "--yes" ]]; then
  read -r -p "Continue? [y/N] " confirm
  if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
  fi
fi

# Credentials go through lftp's own argv (-u user,pass), never interpolated into the -e script
# string below, so special characters in the password can't break lftp's command parsing.
LFTP_OPTS=()
[ -n "$DEPLOY_PORT" ] && LFTP_OPTS+=(-p "$DEPLOY_PORT")

if [ "$DEPLOY_PROTOCOL" = "sftp" ] && [ -z "$DEPLOY_PASSWORD" ]; then
  # No password configured: let SSH handle auth (agent/keys) instead of forcing password auth.
  SITE="sftp://${DEPLOY_USER}@${DEPLOY_HOST}"
else
  LFTP_OPTS+=(-u "$DEPLOY_USER,$DEPLOY_PASSWORD")
  SITE="$DEPLOY_PROTOCOL://$DEPLOY_HOST"
fi

STARTUP_CMDS="set ssl:verify-certificate $DEPLOY_VERIFY_CERT;"
if [ -n "$DEPLOY_SSH_KEY" ]; then
  STARTUP_CMDS+=" set sftp:connect-program \"ssh -a -x -i $DEPLOY_SSH_KEY\";"
fi

lftp "${LFTP_OPTS[@]}" \
  -e "$STARTUP_CMDS mirror --reverse --delete --verbose \"$LOCAL_DIR\" \"$DEPLOY_REMOTE_PATH\"; bye" \
  "$SITE"

echo "==> Deployed."
