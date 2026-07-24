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
  description = "List of CIDR blocks allowed to SSH into the server. Use specific IPs/ranges instead of 0.0.0.0/0 for security."
  type        = list(string)
  default     = []
  validation {
    condition     = length(var.allowed_ssh_cidrs) > 0
    error_message = "You must specify at least one CIDR block for SSH access. Do not use 0.0.0.0/0 in production."
  }
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
  description = "Origins allowed to read app content from the bucket via fetch()/XHR. Only affects browsers; the signed URL is what actually controls access."
  type        = list(string)
  default     = ["*"]
}
