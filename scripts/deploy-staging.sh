#!/bin/bash
# Manual deployment script for staging environment
# Usage: ./scripts/deploy-staging.sh

set -e

# Configuration
STAGING_HOST="${STAGING_HOST:-}"
SSH_KEY="${SSH_KEY:-~/.ssh/id_rsa}"
APP_DIR="/var/www/storywriter-staging"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check required environment variables
if [ -z "$STAGING_HOST" ]; then
    log_error "STAGING_HOST environment variable is required"
    log_info "Usage: STAGING_HOST=1.2.3.4 ./scripts/deploy-staging.sh"
    exit 1
fi

# Check SSH key exists
if [ ! -f "$SSH_KEY" ]; then
    log_error "SSH key not found: $SSH_KEY"
    log_info "Set SSH_KEY environment variable to your private key path"
    exit 1
fi

log_info "Deploying to staging: $STAGING_HOST"

ssh -i "$SSH_KEY" deploy@"$STAGING_HOST" << 'DEPLOY_SCRIPT'
    set -e
    cd /var/www/storywriter-staging

    echo "==> Pulling latest code..."
    git fetch origin develop
    git reset --hard origin/develop

    echo "==> Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction

    echo "==> Running database migrations..."
    php artisan migrate --force

    echo "==> Clearing and rebuilding caches..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "==> Installing npm dependencies and building assets..."
    npm ci --production=false
    npm run build

    echo "==> Setting permissions..."
    chmod -R 775 storage bootstrap/cache

    echo "==> Restarting PHP-FPM..."
    sudo /bin/systemctl reload php8.4-fpm

    echo "==> Deployment completed successfully!"
DEPLOY_SCRIPT

log_info "Deployment completed!"
