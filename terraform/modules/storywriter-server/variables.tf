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

variable "github_repo" {
  description = "GitHub repository URL for the application"
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
