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
  description = "ID of the security group"
  value       = module.storywriter_server.security_group_id
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
