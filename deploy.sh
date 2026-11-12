#!/bin/bash
#
# Occupy Uranus - Deployment Script
# Usage: ./deploy.sh [--dry-run]
#

set -e

# =============================================================================
# CONFIGURATION
# =============================================================================

if [ ! -f "deploy.config" ]; then
    echo "❌ deploy.config not found"
    exit 1
fi

source deploy.config

if [ -z "$DEPLOY_HOST" ] || [ -z "$DEPLOY_USER" ] || [ -z "$DEPLOY_PATH" ]; then
    echo "❌ Missing DEPLOY_HOST, DEPLOY_USER, or DEPLOY_PATH in deploy.config"
    exit 1
fi

# =============================================================================
# ARGUMENTS
# =============================================================================

DRY_RUN=false
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=true
fi

# =============================================================================
# PRE-FLIGHT CHECKS
# =============================================================================

echo "Deploying to: ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"
echo ""

# Verify critical files exist
if [ ! -f "uranus_game_bot.php" ] || [ ! -f "config.php" ]; then
    echo "❌ Missing critical files"
    exit 1
fi

# Quick PHP syntax check
php -l uranus_game_bot.php > /dev/null || exit 1
php -l config.php > /dev/null || exit 1

# =============================================================================
# DEPLOY
# =============================================================================

# Build SSH command
SSH_CMD="ssh"
[ -n "$DEPLOY_PORT" ] && SSH_CMD="$SSH_CMD -p $DEPLOY_PORT"
[ -n "$DEPLOY_SSH_KEY" ] && SSH_CMD="$SSH_CMD -i $DEPLOY_SSH_KEY"

# Rsync options
OPTS=(
    -avz
    --progress
    --delete
    -e "$SSH_CMD"
)

# Add dry-run if requested
[ "$DRY_RUN" = true ] && OPTS+=(--dry-run)

# Exclusions (what NOT to deploy)
EXCLUDE=(
    --exclude='.git/'
    --exclude='.gitignore'
    --exclude='.github/'
    --exclude='.ssh/'
    --exclude='.DS_Store'
    --exclude='*.swp'
    --exclude='*.swo'
    --exclude='*~'
    --exclude='notes/'
    --exclude='docs/'
    --exclude='test_framework/'
    --exclude='.archive/'
    --exclude='logs/'
    --exclude='deploy.sh'
    --exclude='deploy.config*'
    --exclude='setup.sh'
    --exclude='local_game_tester.php'
    --exclude='telemetry_analyzer.php'
    --exclude='test_*.php'
    --exclude='test_*.sh'
    --exclude='tools/'
    --exclude='CLAUDE.md'
    --exclude='README.md'
    --exclude='DEPLOYMENT.md'
    --exclude='PRODUCTION_SETUP.md'
    --exclude='IMPLEMENTATION_BACKLOG.md'
    --exclude='DOCS_PROTOCOL.md'
    --exclude='data/backups/'
    --exclude='data/game_state*.json'
    --exclude='data/leaderboards.json'
    --exclude='media/.backup/'
)

# Execute rsync
rsync "${OPTS[@]}" "${EXCLUDE[@]}" ./ "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/"

# =============================================================================
# DONE
# =============================================================================

if [ "$DRY_RUN" = false ]; then
    echo ""
    echo "✅ Deployed successfully"
    echo ""
    echo "Test: Send /game to bot in Telegram"
    echo "Logs: ssh ${DEPLOY_USER}@${DEPLOY_HOST} tail -f ${DEPLOY_PATH}/logs/bot.log"
else
    echo ""
    echo "✅ Dry run complete (no files transferred)"
fi
