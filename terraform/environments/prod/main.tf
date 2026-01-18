# Storywriter Production Environment

terraform {
  required_version = ">= 1.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Variables
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
  description = "Domain name for the production environment"
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
  description = "Environment name"
  type        = string
  default     = "production"
}

variable "ssm_parameter_path" {
  description = "SSM Parameter Store path prefix"
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

# Module call
module "storywriter_server" {
  source = "../../modules/storywriter-server"

  aws_region         = var.aws_region
  vpc_id             = var.vpc_id
  subnet_id          = var.subnet_id
  key_pair_name      = var.key_pair_name
  instance_type      = var.instance_type
  domain_name        = var.domain_name
  app_name           = var.app_name
  github_repo        = var.github_repo
  environment        = var.environment
  ssm_parameter_path = var.ssm_parameter_path
  database_name      = var.database_name
  deploy_branch      = var.deploy_branch
  route53_zone_id    = var.route53_zone_id
}
