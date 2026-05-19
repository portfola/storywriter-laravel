#!/bin/bash
set -e

# Log all output
exec > >(tee /var/log/user-data.log) 2>&1
echo "Starting provisioning at $(date)"

# Mark failure on any unexpected exit so a post-apply check can detect it.
trap 'echo "PROVISIONING FAILED at $(date) (exit $?, line $LINENO)"; touch /var/lib/storywriter-provisioning-failed' ERR

# Retry helper for flaky network operations (e.g. external apt repos).
retry() {
    local n=1
    local max=10
    local delay=30
    while true; do
        "$@" && return 0
        if [ $n -ge $max ]; then
            echo "Command failed after $max attempts: $*"
            return 1
        fi
        n=$((n + 1))
        echo "Command failed, retrying ($n/$max) in $delay seconds: $*"
        sleep $delay
    done
}

# Variables from Terraform
DOMAIN_NAME="${domain_name}"
APP_NAME="${app_name}"
DATABASE_NAME="${database_name}"
DEPLOY_BRANCH="${deploy_branch}"
APP_DIR="/var/www/$APP_NAME"

# Make apt resilient to flaky mirrors (ppa.launchpadcontent.net in particular has
# load-balanced backends that intermittently RST/timeout — apt's internal retries
# re-resolve DNS and reopen TCP, often landing on a healthy backend).
cat > /etc/apt/apt.conf.d/99-retries << 'APT_RETRIES_EOF'
Acquire::Retries "15";
Acquire::Retries::Delay::Maximum "30";
Acquire::http::Timeout "30";
Acquire::https::Timeout "30";
APT_RETRIES_EOF

# Update system
apt-get update
apt-get upgrade -y

# Install required packages
apt-get install -y \
    software-properties-common \
    curl \
    git \
    unzip \
    acl \
    nginx \
    certbot \
    python3-certbot-nginx \
    postgresql \
    postgresql-contrib

# Add PHP 8.4 repository (PPA fetches are flaky — retry)
retry add-apt-repository -y ppa:ondrej/php
retry apt-get update
# apt-get update returns success even if a single repo's InRelease fetch failed,
# which would silently leave us with no php8.4 candidates. Verify before continuing.
retry sh -c "apt-cache policy php8.4-fpm | grep -q 'Candidate:'"

# Install PHP 8.4 and extensions
retry apt-get install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-curl \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-pgsql \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-readline

# Configure PostgreSQL
systemctl enable postgresql
systemctl start postgresql

# Create application database and user
POSTGRES_PASSWORD=$(openssl rand -base64 32)
sudo -u postgres psql << POSTGRES_SETUP
CREATE DATABASE "$DATABASE_NAME";
CREATE USER storywriter_app WITH ENCRYPTED PASSWORD '$POSTGRES_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE "$DATABASE_NAME" TO storywriter_app;
\c "$DATABASE_NAME"
GRANT ALL ON SCHEMA public TO storywriter_app;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO storywriter_app;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO storywriter_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO storywriter_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO storywriter_app;
POSTGRES_SETUP

# Store database credentials for retrieval
cat > /root/.db_credentials << DB_CREDS
Database: $DATABASE_NAME
Username: storywriter_app
Password: $POSTGRES_PASSWORD
Host: localhost
Port: 5432
Deploy Branch: $DEPLOY_BRANCH
DB_CREDS
chmod 600 /root/.db_credentials

echo "PostgreSQL credentials stored in /root/.db_credentials"

# Install Composer (set HOME to avoid warnings)
export HOME=/root
EXPECTED_CHECKSUM="$(curl -sS https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo "ERROR: Invalid Composer installer checksum"
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
echo "Composer installed successfully"

# Install Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# Configure PHP-FPM
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.4/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 64M/' /etc/php/8.4/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 64M/' /etc/php/8.4/fpm/php.ini

# Create application directory
mkdir -p $APP_DIR
mkdir -p /var/www/releases
chown -R www-data:www-data /var/www

# Configure Nginx
cat > /etc/nginx/sites-available/$APP_NAME << NGINX_EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${domain_name};
    root /var/www/${app_name}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_EOF

# Enable the site
ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload Nginx
nginx -t
systemctl reload nginx

# Create deploy user for GitHub Actions
useradd -m -s /bin/bash deploy || true
usermod -aG www-data deploy
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh

# Add the deploy SSH key (used by both GitHub Actions and manual SSH)
echo "${github_actions_public_key}" > /home/deploy/.ssh/authorized_keys

chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh

# Allow deploy user to restart PHP-FPM, read DB credentials, and manage app permissions without password (least privilege)
cat > /etc/sudoers.d/deploy << 'SUDOERS_EOF'
deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.4-fpm
deploy ALL=(root) NOPASSWD: /bin/systemctl restart php8.4-fpm
deploy ALL=(root) NOPASSWD: /bin/grep * /root/.db_credentials
deploy ALL=(root) NOPASSWD: /usr/bin/chown -R deploy\:www-data /var/www/*
deploy ALL=(root) NOPASSWD: /usr/bin/chmod -R [0-9][0-9][0-9] /var/www/*/storage*
deploy ALL=(root) NOPASSWD: /usr/bin/chmod -R [0-9][0-9][0-9] /var/www/*/bootstrap/cache*
SUDOERS_EOF
chmod 440 /etc/sudoers.d/deploy

# Set up SSL certificate with Let's Encrypt
echo "==> Setting up SSL certificate for $DOMAIN_NAME..."
certbot --nginx \
  -d $DOMAIN_NAME \
  --non-interactive \
  --agree-tos \
  --email ${admin_email} \
  --redirect \
  || echo "WARNING: Certbot failed - SSL can be configured manually later"

echo "SSL certificate setup attempted"

# Create storage directories that Laravel needs
mkdir -p $APP_DIR/storage/app/public
mkdir -p $APP_DIR/storage/framework/cache
mkdir -p $APP_DIR/storage/framework/sessions
mkdir -p $APP_DIR/storage/framework/views
mkdir -p $APP_DIR/storage/logs
mkdir -p $APP_DIR/bootstrap/cache

# Set proper permissions for application directory (after creating directories)
chown -R deploy:www-data $APP_DIR
chown -R deploy:www-data /var/www/releases
chmod -R 775 $APP_DIR
chmod 755 /var/www/releases

# Enable and start services
systemctl enable php8.4-fpm
systemctl enable nginx
systemctl restart php8.4-fpm
systemctl restart nginx

echo "Provisioning completed at $(date)"
echo "Environment: $APP_NAME"
echo "Deploy Branch: $DEPLOY_BRANCH"

# Final success marker — absence of this file means provisioning did not finish.
touch /var/lib/storywriter-provisioned
