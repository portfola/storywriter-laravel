# Module Outputs for Storywriter Server

output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.server.id
}

output "elastic_ip" {
  description = "Elastic IP address of the server"
  value       = aws_eip.server.public_ip
}

output "public_dns" {
  description = "Public DNS name of the server"
  value       = aws_eip.server.public_dns
}

output "security_group_id" {
  description = "ID of the security group"
  value       = aws_security_group.server.id
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh ubuntu@${aws_eip.server.public_ip}"
}

output "domain_dns_record" {
  description = "Route 53 DNS A record FQDN"
  value       = aws_route53_record.server.fqdn
}

output "iam_role_arn" {
  description = "ARN of the IAM role attached to the EC2 instance"
  value       = aws_iam_role.ec2_role.arn
}

output "iam_instance_profile" {
  description = "Name of the IAM instance profile"
  value       = aws_iam_instance_profile.ec2_profile.name
}
