# Storywriter Server Module
# Creates EC2 instance with all associated resources for the storywriter application

# Data sources for existing resources
data "aws_vpc" "selected" {
  id = var.vpc_id
}

data "aws_subnet" "selected" {
  id = var.subnet_id
}

# Get current AWS account ID for least-privilege IAM policies
data "aws_caller_identity" "current" {}

# Get latest Ubuntu 24.04 ARM64 AMI
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-arm64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }

  filter {
    name   = "architecture"
    values = ["arm64"]
  }
}

# Security Group for the server
resource "aws_security_group" "server" {
  name        = "${var.app_name}-sg"
  description = "Security group for ${var.app_name}"
  vpc_id      = data.aws_vpc.selected.id

  # SSH access. This list is for the addresses people SSH from; deploys are not
  # in it. A GitHub-hosted runner's address is not knowable in advance, so the
  # deploy workflow opens port 22 for its own /32 at the start of a run and
  # revokes it at the end (see .github/workflows/deploy-*.yml).
  #
  # Those runner rules are added out of band. Because this is an inline `ingress`
  # block, Terraform treats them as drift and removes them on the next apply --
  # which cleans up after a run that died before it could revoke, but also means
  # an apply during a deploy will shut the door on it.
  ingress {
    description = "SSH from allowed_ssh_cidrs (deploys add a temporary runner rule)"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = var.allowed_ssh_cidrs
  }

  # HTTP access
  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # HTTPS access
  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # Allow all outbound traffic
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name        = "${var.app_name}-sg"
    Environment = var.environment
  }
}

# Elastic IP for stable DNS
resource "aws_eip" "server" {
  domain = "vpc"

  tags = {
    Name        = "${var.app_name}-eip"
    Environment = var.environment
  }
}

# EC2 Instance
resource "aws_instance" "server" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  key_name               = var.key_pair_name
  subnet_id              = data.aws_subnet.selected.id
  vpc_security_group_ids = [aws_security_group.server.id]
  iam_instance_profile   = aws_iam_instance_profile.ec2_profile.name

  root_block_device {
    volume_size = 20
    volume_type = "gp3"
    encrypted   = true
  }

  # Enforce IMDSv2 to prevent SSRF attacks
  metadata_options {
    http_endpoint               = "enabled"
    http_tokens                 = "required"
    http_put_response_hop_limit = 2
    instance_metadata_tags      = "enabled"
  }

  user_data = templatefile("${path.module}/user-data.sh", {
    domain_name              = var.domain_name
    app_name                 = var.app_name
    database_name            = var.database_name
    deploy_branch            = var.deploy_branch
    admin_email              = var.admin_email
    github_actions_public_key = var.github_actions_public_key
  })

  tags = {
    Name        = var.app_name
    Environment = var.environment
  }
}

# Associate Elastic IP with the instance
resource "aws_eip_association" "server" {
  instance_id   = aws_instance.server.id
  allocation_id = aws_eip.server.id
}

# Route 53 DNS A record
resource "aws_route53_record" "server" {
  zone_id = var.route53_zone_id
  name    = var.domain_name
  type    = "A"
  ttl     = 300
  records = [aws_eip.server.public_ip]
}
