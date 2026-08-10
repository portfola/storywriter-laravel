# Module Variables for Storywriter Server

variable "aws_region" {
  description = "AWS region to deploy into"
  type        = string
  default     = "us-east-1"
}

variable "vpc_id" {
  description = "ID of the existing VPC to deploy into"
  type        = string
}

variable "subnet_id" {
  description = "ID of the public subnet for the EC2 instance"
  type        = string
}

variable "key_pair_name" {
  description = "Name of the existing AWS key pair for SSH access"
  type        = string
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t4g.micro"
}

variable "domain_name" {
  description = "Domain name for the environment"
  type        = string
}

variable "app_name" {
  description = "Application name for resource naming"
  type        = string
}

variable "environment" {
  description = "Environment name (staging or production)"
  type        = string
  validation {
    condition     = contains(["staging", "production"], var.environment)
    error_message = "Environment must be either 'staging' or 'production'."
  }
}

variable "ssm_parameter_path" {
  description = "SSM Parameter Store path prefix (e.g., /storywriter/staging or /storywriter/production)"
  type        = string
}

variable "database_name" {
  description = "PostgreSQL database name"
  type        = string
}

variable "deploy_branch" {
  description = "Git branch for deployments"
  type        = string
}

variable "route53_zone_id" {
  description = "Route 53 hosted zone ID for DNS record creation"
  type        = string
}

variable "allowed_ssh_cidrs" {
  description = "CIDR blocks allowed to SSH into the server. Deploys do not need an entry here -- the deploy workflow opens port 22 for its own runner and closes it again -- so this should hold only the addresses real people SSH from."
  type        = list(string)
  default     = []
  validation {
    condition     = length(var.allowed_ssh_cidrs) > 0
    error_message = "You must specify at least one CIDR block for SSH access."
  }
  validation {
    condition     = !contains(var.allowed_ssh_cidrs, "0.0.0.0/0") && !contains(var.allowed_ssh_cidrs, "::/0")
    error_message = "allowed_ssh_cidrs must not open port 22 to the internet. Deploys open port 22 for their own runner and close it again, so 0.0.0.0/0 is no longer needed. Set the GitHub environment secrets AWS_DEPLOY_ROLE_ARN and DEPLOY_SECURITY_GROUP_ID before applying this, or the next deploy cannot get in."
  }
}

variable "github_deploy_repository" {
  description = "GitHub repository, as owner/repo, whose deploy workflow may open port 22 on this server's security group. Leave empty to not create the role at all."
  type        = string
  default     = ""
}

variable "github_deploy_environment" {
  description = "Name of the GitHub Environment the deploy job runs in (the `environment:` key on the job). The trust policy is scoped to it, so a job without that environment cannot assume the role. Required when github_deploy_repository is set."
  type        = string
  default     = ""
}

variable "admin_email" {
  description = "Email address for Let's Encrypt SSL certificate notifications"
  type        = string
}

variable "github_actions_public_key" {
  description = "Public SSH key for deploy user. Used by GitHub Actions and manual SSH. Corresponds to GitHub Secret: SSH_PRIVATE_KEY"
  type        = string
}

variable "app_content_bucket_name" {
  description = "Name of the S3 bucket holding story images and narration audio. Defaults to {app_name}-content. Set this only if that name is already taken -- S3 bucket names are globally unique."
  type        = string
  default     = ""
}

variable "app_content_cors_allowed_origins" {
  description = "Origins allowed to read app content from the bucket via fetch()/XHR. Only affects browsers; the signed URL is what actually controls access. No default on purpose -- each environment names its own origins so a new one cannot quietly inherit a wildcard."
  type        = list(string)
}
