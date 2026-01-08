#!/bin/bash
# First-time server setup script for staging
# Run this after Terraform provisions the EC2 instance
# Usage: ./scripts/setup-staging-server.sh

set -e

# Configuration
STAGING_HOST="${STAGING_HOST:-}"
SSH_KEY="${SSH_KEY:-~/.ssh/id_rsa}"
GITHUB_REPO="${GITHUB_REPO:-}"
DOMAIN_NAME="${DOMAIN_NAME:-staging-api.storywriter.net}"
APP_DIR="/var/www/storywriter-staging"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check required environment variables
if [ -z "$STAGING_HOST" ]; then
    log_error "STAGING_HOST environment variable is required"
    exit 1
fi

if [ -z "$GITHUB_REPO" ]; then
    log_error "GITHUB_REPO environment variable is required"
    log_info "Example: GITHUB_REPO=git@github.com:username/storywriter-laravel.git"
    exit 1
fi

log_info "Setting up staging server: $STAGING_HOST"

# Step 1: Add deploy user's SSH key for GitHub Actions
log_info "Step 1: Configure deploy user SSH key"
read -p "Paste the PUBLIC key for the deploy user (from GitHub Secrets): " DEPLOY_PUBLIC_KEY

ssh -i "$SSH_KEY" ubuntu@"$STAGING_HOST" << SETUP_SSH
    echo "$DEPLOY_PUBLIC_KEY" >> /home/deploy/.ssh/authorized_keys
    chown deploy:deploy /home/deploy/.ssh/authorized_keys
    chmod 600 /home/deploy/.ssh/authorized_keys
SETUP_SSH

log_info "Deploy user SSH key configured"

# Step 2: Clone the repository
log_info "Step 2: Cloning repository..."
ssh -i "$SSH_KEY" ubuntu@"$STAGING_HOST" << CLONE_REPO
    sudo -u deploy git clone $GITHUB_REPO $APP_DIR
    cd $APP_DIR
    sudo -u deploy git checkout develop
CLONE_REPO

# Step 3: Set up environment file
log_info "Step 3: Setting up environment file..."
log_warn "You'll need to manually create the .env file on the server"
log_info "SSH in and create: $APP_DIR/.env"

# Step 4: Initial Laravel setup
log_info "Step 4: Running initial Laravel setup..."
ssh -i "$SSH_KEY" ubuntu@"$STAGING_HOST" << LARAVEL_SETUP
    cd $APP_DIR

    # Create SQLite database
    sudo -u deploy touch database/database.sqlite

    # Set permissions
    sudo chown -R deploy:www-data storage bootstrap/cache database
    sudo chmod -R 775 storage bootstrap/cache
    sudo chmod 664 database/database.sqlite
LARAVEL_SETUP

# Step 5: SSL Certificate
log_info "Step 5: Setting up SSL certificate..."
log_info "Make sure DNS A record for $DOMAIN_NAME points to $STAGING_HOST"
read -p "Is DNS configured? (y/n): " DNS_READY

if [ "$DNS_READY" = "y" ]; then
    ssh -i "$SSH_KEY" ubuntu@"$STAGING_HOST" << SSL_SETUP
        sudo certbot --nginx -d $DOMAIN_NAME --non-interactive --agree-tos --email admin@storywriter.net
SSL_SETUP
    log_info "SSL certificate installed"
else
    log_warn "Skipping SSL setup. Run manually later:"
    log_info "sudo certbot --nginx -d $DOMAIN_NAME"
fi

log_info "Server setup completed!"
log_info ""
log_info "Next steps:"
log_info "1. SSH to server: ssh -i $SSH_KEY ubuntu@$STAGING_HOST"
log_info "2. Create .env file: sudo -u deploy nano $APP_DIR/.env"
log_info "3. Run: cd $APP_DIR && sudo -u deploy composer install"
log_info "4. Run: cd $APP_DIR && sudo -u deploy php artisan key:generate"
log_info "5. Run: cd $APP_DIR && sudo -u deploy php artisan migrate"
log_info "6. Run: cd $APP_DIR && sudo -u deploy npm ci && npm run build"
