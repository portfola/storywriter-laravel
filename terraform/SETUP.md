# Staging Infrastructure Setup Guide

## Prerequisites

1. AWS CLI configured with appropriate credentials
2. Terraform >= 1.0 installed
3. An existing AWS VPC with a public subnet
4. An existing AWS key pair for SSH access
5. An S3 bucket for Terraform state storage

## Initial Setup

### 1. Configure Terraform Backend

Edit `backend.tf` and replace `YOUR_S3_BUCKET_NAME` with your S3 bucket:

```hcl
terraform {
  backend "s3" {
    bucket = "your-actual-bucket-name"
    key    = "storywriter-staging/terraform.tfstate"
    region = "us-east-1"
    encrypt = true
  }
}
```

### 2. Create terraform.tfvars

Copy the example file and fill in your values:

```bash
cp terraform.tfvars.example terraform.tfvars
```

Edit `terraform.tfvars`:

```hcl
aws_region    = "us-east-1"
vpc_id        = "vpc-abc123..."
subnet_id     = "subnet-def456..."
key_pair_name = "your-key-pair"
```

### 3. Initialize and Apply Terraform

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### 4. Note the Outputs

After apply, note the Elastic IP from the output. You'll need this for:
- DNS A record configuration
- GitHub Secrets (`STAGING_HOST`)

## GitHub Repository Setup

### Required Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions

Create these secrets:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `AWS_ACCESS_KEY_ID` | AWS access key for Terraform | `AKIA...` |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key for Terraform | `wJalrXUtnFEMI...` |
| `AWS_VPC_ID` | VPC ID for Terraform | `vpc-abc123...` |
| `AWS_SUBNET_ID` | Subnet ID for Terraform | `subnet-def456...` |
| `AWS_KEY_PAIR_NAME` | Key pair name for EC2 | `my-key-pair` |
| `SSH_PRIVATE_KEY` | Private key for deploy user | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `STAGING_HOST` | EC2 Elastic IP address | `1.2.3.4` |

### Create a Deploy Key Pair

Generate a new SSH key pair specifically for deployments:

```bash
ssh-keygen -t ed25519 -f deploy_key -N "" -C "github-actions-deploy"
```

- Add the **private key** (`deploy_key`) to GitHub Secrets as `SSH_PRIVATE_KEY`
- Add the **public key** (`deploy_key.pub`) to the server's deploy user

### Environment Setup

Create a GitHub Environment called `staging`:
1. Go to Settings → Environments → New environment
2. Name it `staging`
3. Optionally add protection rules (required reviewers, etc.)

## Server First-Time Setup

After Terraform provisions the EC2 instance:

### 1. DNS Configuration

Create an A record pointing your domain to the Elastic IP:

```
staging-api.storywriter.net → 1.2.3.4
```

### 2. Add Deploy User's SSH Key

SSH into the server and add the deploy public key:

```bash
ssh -i ~/.ssh/your-key.pem ubuntu@<elastic-ip>
sudo bash -c 'echo "ssh-ed25519 AAAA... github-actions-deploy" >> /home/deploy/.ssh/authorized_keys'
```

### 3. Clone the Repository

```bash
sudo -u deploy git clone https://github.com/your-username/storywriter-laravel.git /var/www/storywriter-staging
cd /var/www/storywriter-staging
sudo -u deploy git checkout develop
```

### 4. Create Environment File

```bash
sudo -u deploy cp .env.example .env
sudo -u deploy nano .env
```

Update these values in `.env`:

```env
APP_NAME="Storywriter Staging"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging-api.storywriter.net

DB_CONNECTION=sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

### 5. Initial Application Setup

```bash
cd /var/www/storywriter-staging
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy php artisan key:generate
sudo -u deploy php artisan migrate --force
sudo -u deploy npm ci
sudo -u deploy npm run build
```

### 6. SSL Certificate

```bash
sudo certbot --nginx -d staging-api.storywriter.net
```

### 7. Verify Setup

Visit https://staging-api.storywriter.net to verify the application is running.

## Deployment Workflow

Once setup is complete, deployments happen automatically:

1. Push code to the `develop` branch
2. GitHub Actions triggers the `deploy-staging.yml` workflow
3. The workflow SSHs into the server and:
   - Pulls the latest code
   - Runs `composer install`
   - Runs migrations
   - Builds assets
   - Clears caches
   - Restarts PHP-FPM

## Manual Deployment

For manual deployments, use the provided script:

```bash
STAGING_HOST=1.2.3.4 SSH_KEY=~/.ssh/deploy_key ./scripts/deploy-staging.sh
```

## Troubleshooting

### Check Server Logs

```bash
# Nginx errors
sudo tail -f /var/log/nginx/error.log

# PHP-FPM errors
sudo tail -f /var/log/php8.4-fpm.log

# Laravel logs
tail -f /var/www/storywriter-staging/storage/logs/laravel.log

# User-data script log (provisioning)
sudo cat /var/log/user-data.log
```

### Permission Issues

```bash
cd /var/www/storywriter-staging
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Restart Services

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

## Destroying Infrastructure

To tear down the staging environment:

```bash
cd terraform
terraform destroy
```

Or via GitHub Actions: Run the "Terraform Staging Infrastructure" workflow with action `destroy`.
