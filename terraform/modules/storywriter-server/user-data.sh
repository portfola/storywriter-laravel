#!/bin/bash
set -e

# Log all output
exec > >(tee /var/log/user-data.log) 2>&1
echo "Starting provisioning at $(date)"

# Variables from Terraform
DOMAIN_NAME="${domain_name}"
APP_NAME="${app_name}"
GITHUB_REPO="${github_repo}"
DATABASE_NAME="${database_name}"
DEPLOY_BRANCH="${deploy_branch}"
APP_DIR="/var/www/$APP_NAME"

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

# Add PHP 8.4 repository
add-apt-repository -y ppa:ondrej/php
apt-get update

# Install PHP 8.4 and extensions
apt-get install -y \
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
CREATE DATABASE $DATABASE_NAME;
CREATE USER storywriter_app WITH ENCRYPTED PASSWORD '$POSTGRES_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE $DATABASE_NAME TO storywriter_app;
\c $DATABASE_NAME
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

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

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
cat >> /home/deploy/.ssh/authorized_keys << 'EOF'
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIA32ZEbjiM/z/gsaPOGrLzBTjz9G1K7cBj3lz7R+Nt+s github-actions-deploy
EOF
chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh

# Allow deploy user to restart PHP-FPM without password
echo "deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.4-fpm, /bin/systemctl restart php8.4-fpm" > /etc/sudoers.d/deploy
chmod 440 /etc/sudoers.d/deploy

# Set proper permissions for application directory
chown -R deploy:www-data $APP_DIR
chown -R deploy:www-data /var/www/releases
chmod -R 775 $APP_DIR
chmod 755 /var/www/releases

# Create storage directories that Laravel needs
mkdir -p $APP_DIR/storage/app/public
mkdir -p $APP_DIR/storage/framework/cache
mkdir -p $APP_DIR/storage/framework/sessions
mkdir -p $APP_DIR/storage/framework/views
mkdir -p $APP_DIR/storage/logs
mkdir -p $APP_DIR/bootstrap/cache

# Set storage permissions
chown -R deploy:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache 2>/dev/null || true
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache 2>/dev/null || true

# Enable and start services
systemctl enable php8.4-fpm
systemctl enable nginx
systemctl restart php8.4-fpm
systemctl restart nginx

echo "Provisioning completed at $(date)"
echo "Environment: $APP_NAME"
echo "Deploy Branch: $DEPLOY_BRANCH"
echo "Next steps:"
echo "1. Point DNS A record for $DOMAIN_NAME to this server's IP"
echo "2. Run: sudo certbot --nginx -d $DOMAIN_NAME"
echo "3. Retrieve database credentials from: sudo cat /root/.db_credentials"
echo "4. Deploy your application code from the '$DEPLOY_BRANCH' branch"
