# Storywriter Terraform Infrastructure

Multi-environment Terraform configuration for deploying the Storywriter Laravel application.

## Directory Structure

```
terraform/
├── modules/
│   └── storywriter-server/      # Reusable module for EC2 infrastructure
│       ├── main.tf              # EC2, Security Group, EIP resources
│       ├── variables.tf         # Module input variables
│       ├── outputs.tf           # Module outputs
│       ├── iam.tf               # IAM role, policy, instance profile
│       └── user-data.sh         # Server provisioning script
├── environments/
│   ├── staging/                 # Staging environment
│   │   ├── main.tf              # Provider + module call
│   │   ├── backend.tf           # S3 backend for staging state
│   │   ├── terraform.tfvars     # Staging variable values (gitignored)
│   │   └── outputs.tf           # Re-export module outputs
│   └── prod/                    # Production environment
│       ├── main.tf              # Provider + module call
│       ├── backend.tf           # S3 backend for prod state
│       ├── terraform.tfvars     # Production variable values (gitignored)
│       └── outputs.tf           # Re-export module outputs
├── .gitignore
└── README.md
```

## Environments

| Environment | Domain | Git Branch | SSM Path | Database |
|-------------|--------|------------|----------|----------|
| Staging | staging-api.storywriter.net | develop | /storywriter/staging/* | storywriter_staging |
| Production | api.storywriter.net | main | /storywriter/production/* | storywriter_production |

## Prerequisites

1. AWS CLI configured with appropriate credentials
2. Terraform >= 1.0 installed
3. S3 bucket for state storage (`storywriter-terraform-state`)
4. SSH key pairs created in AWS:
   - Staging: `storywriter-staging-ec2-tf`
   - Production: `storywriter-prod-ec2-tf`

## Quick Start

### Deploy Staging

```bash
cd terraform/environments/staging

# Copy example tfvars and customize
cp terraform.tfvars.example terraform.tfvars

# Initialize Terraform
terraform init

# Review planned changes
terraform plan

# Apply changes
terraform apply
```

### Deploy Production

```bash
cd terraform/environments/prod

# Copy example tfvars and customize
cp terraform.tfvars.example terraform.tfvars

# Initialize Terraform
terraform init

# Review planned changes
terraform plan

# Apply changes
terraform apply
```

## Configuration Variables

| Variable | Description | Default |
|----------|-------------|---------|
| aws_region | AWS region | us-east-1 |
| vpc_id | Existing VPC ID | - |
| subnet_id | Public subnet ID | - |
| key_pair_name | EC2 key pair name | - |
| instance_type | EC2 instance type | t4g.micro |
| domain_name | Domain name | - |
| app_name | Application name | - |
| github_repo | GitHub repository URL | - |
| environment | Environment (staging/production) | - |
| ssm_parameter_path | SSM path prefix | - |
| database_name | PostgreSQL database name | - |
| deploy_branch | Git branch for deployments | - |
| route53_zone_id | Route 53 hosted zone ID | - |

## State Management

Each environment has its own state file stored in S3:
- Staging: `s3://storywriter-terraform-state/backend-staging/terraform.tfstate`
- Production: `s3://storywriter-terraform-state/environments/prod/terraform.tfstate`

## Outputs

After applying, the following outputs are available:

```bash
terraform output
```

- `instance_id` - EC2 instance ID
- `elastic_ip` - Elastic IP address
- `public_dns` - Public DNS name
- `security_group_id` - Security group ID
- `ssh_command` - SSH command to connect
- `domain_dns_record` - Route 53 DNS record FQDN
- `iam_role_arn` - IAM role ARN
- `iam_instance_profile` - Instance profile name

## Creating Production SSH Key

Before deploying production, create the SSH key pair:

```bash
aws ec2 create-key-pair \
  --key-name storywriter-prod-ec2-tf \
  --query 'KeyMaterial' \
  --output text > storywriter-prod-ec2-tf.pem

chmod 400 storywriter-prod-ec2-tf.pem
```

## SSM Parameters

Secrets are stored in AWS Systems Manager Parameter Store:

- Staging: `/storywriter/staging/*`
- Production: `/storywriter/production/*`

The EC2 instances have IAM roles that grant read access only to their respective SSM paths.

## Post-Deployment Steps

1. SSH to server and run certbot for SSL:
   ```bash
   sudo certbot --nginx -d <domain>
   ```
2. Retrieve database credentials:
   ```bash
   sudo cat /root/.db_credentials
   ```
3. Deploy application code via GitHub Actions

## Destroying Infrastructure

```bash
cd terraform/environments/<env>
terraform destroy
```

**Warning**: This will destroy all resources including the EC2 instance and data.
