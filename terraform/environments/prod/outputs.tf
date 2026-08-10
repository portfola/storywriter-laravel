# Production Environment Outputs
# Re-export module outputs for production environment

output "instance_id" {
  description = "ID of the EC2 instance"
  value       = module.storywriter_server.instance_id
}

output "elastic_ip" {
  description = "Elastic IP address of the production server"
  value       = module.storywriter_server.elastic_ip
}

output "public_dns" {
  description = "Public DNS name of the production server"
  value       = module.storywriter_server.public_dns
}

output "security_group_id" {
  description = "ID of the security group. This is the value for the deploy workflow's DEPLOY_SECURITY_GROUP_ID secret."
  value       = module.storywriter_server.security_group_id
}

output "github_deploy_role_arn" {
  description = "ARN of the role the deploy workflow assumes to open port 22 for its runner. This is the value for the deploy workflow's AWS_DEPLOY_ROLE_ARN secret."
  value       = module.storywriter_server.github_deploy_role_arn
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = module.storywriter_server.ssh_command
}

output "domain_dns_record" {
  description = "Create an A record pointing to this IP"
  value       = module.storywriter_server.domain_dns_record
}

output "iam_role_arn" {
  description = "ARN of the IAM role attached to the EC2 instance"
  value       = module.storywriter_server.iam_role_arn
}

output "iam_instance_profile" {
  description = "Name of the IAM instance profile"
  value       = module.storywriter_server.iam_instance_profile
}

output "app_content_bucket" {
  description = "Name of the S3 bucket for story images and narration audio (AWS_BUCKET in the app's .env)"
  value       = module.storywriter_server.app_content_bucket
}

output "app_content_bucket_arn" {
  description = "ARN of the app content bucket"
  value       = module.storywriter_server.app_content_bucket_arn
}

output "app_content_bucket_regional_domain_name" {
  description = "Regional domain name of the app content bucket"
  value       = module.storywriter_server.app_content_bucket_regional_domain_name
}
