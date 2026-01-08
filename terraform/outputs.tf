output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.staging.id
}

output "elastic_ip" {
  description = "Elastic IP address of the staging server"
  value       = aws_eip.staging.public_ip
}

output "public_dns" {
  description = "Public DNS name of the staging server"
  value       = aws_eip.staging.public_dns
}

output "security_group_id" {
  description = "ID of the security group"
  value       = aws_security_group.staging.id
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh ubuntu@${aws_eip.staging.public_ip}"
}

output "domain_dns_record" {
  description = "Create an A record pointing to this IP"
  value       = "A record: ${var.domain_name} -> ${aws_eip.staging.public_ip}"
}
