# IAM Resources for Storywriter Server

# IAM Role for EC2 to access SSM Parameter Store
resource "aws_iam_role" "ec2_role" {
  name = "${var.app_name}-ec2-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name        = "${var.app_name}-ec2-role"
    Environment = var.environment
  }
}

# IAM Policy for SSM Parameter Store access
resource "aws_iam_policy" "ssm_parameter_store" {
  name        = "${var.app_name}-ssm-parameter-store"
  description = "Allow read access to SSM Parameter Store for ${var.app_name}"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "SSMParameterStoreRead"
        Effect = "Allow"
        Action = [
          "ssm:GetParameters",
          "ssm:GetParameter",
          "ssm:GetParametersByPath"
        ]
        Resource = "arn:aws:ssm:${var.aws_region}:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_parameter_path}/*"
      },
      {
        Sid    = "KMSDecryptForSSMOnly"
        Effect = "Allow"
        Action = [
          "kms:Decrypt"
        ]
        Resource = "arn:aws:kms:${var.aws_region}:${data.aws_caller_identity.current.account_id}:key/*"
        Condition = {
          StringEquals = {
            "kms:ViaService" = "ssm.${var.aws_region}.amazonaws.com"
          }
          StringLike = {
            "kms:EncryptionContext:PARAMETER_ARN" = "arn:aws:ssm:${var.aws_region}:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_parameter_path}/*"
          }
        }
      }
    ]
  })

  tags = {
    Name        = "${var.app_name}-ssm-parameter-store"
    Environment = var.environment
  }
}

# Attach SSM policy to EC2 role
resource "aws_iam_role_policy_attachment" "ssm_parameter_store" {
  role       = aws_iam_role.ec2_role.name
  policy_arn = aws_iam_policy.ssm_parameter_store.arn
}

# IAM Instance Profile for EC2
resource "aws_iam_instance_profile" "ec2_profile" {
  name = "${var.app_name}-ec2-profile"
  role = aws_iam_role.ec2_role.name

  tags = {
    Name        = "${var.app_name}-ec2-profile"
    Environment = var.environment
  }
}
