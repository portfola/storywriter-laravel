# Just-in-time SSH access for the deploy workflow
#
# Port 22 is closed to everyone except `allowed_ssh_cidrs`, which lists the
# addresses people SSH from. A deploy runs on a GitHub-hosted runner whose
# address is picked at run time out of thousands, so it cannot be in that list.
# Instead the deploy workflow opens port 22 for its own /32, deploys, and
# revokes the rule again -- leaving the port shut for all but the couple of
# minutes a deploy takes.
#
# This role is the only thing that lets it do that, and it can do nothing else:
# two EC2 actions, on this one security group.
#
# Set github_deploy_repository to create it. Feed the outputs to the repo's
# GitHub environment as AWS_DEPLOY_ROLE_ARN and DEPLOY_SECURITY_GROUP_ID; until
# both are set the workflow skips the open/close steps and relies on port 22
# already allowing the runner.

locals {
  create_github_deploy_role = var.github_deploy_repository != ""

  # The `sub` claim GitHub puts in the OIDC token for a job that declares
  # `environment: <name>`. Scoping to the environment (rather than to the repo,
  # or to a branch) means a workflow added on any branch still cannot assume
  # this role unless a reviewer approves the environment.
  github_deploy_subject = "repo:${var.github_deploy_repository}:environment:${var.github_deploy_environment}"
}

# The provider is account-wide and is created once, outside this module -- other
# stacks in this account already federate GitHub through it. Look it up rather
# than declaring it, or a second stack fails with EntityAlreadyExists.
data "aws_iam_openid_connect_provider" "github" {
  count = local.create_github_deploy_role ? 1 : 0
  url   = "https://token.actions.githubusercontent.com"
}

resource "aws_iam_role" "github_deploy" {
  count = local.create_github_deploy_role ? 1 : 0

  name        = "${var.app_name}-github-deploy"
  description = "Lets the ${var.github_deploy_repository} deploy workflow open port 22 on ${var.app_name}-sg for the duration of a deploy"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Federated = data.aws_iam_openid_connect_provider.github[0].arn
        }
        Action = "sts:AssumeRoleWithWebIdentity"
        Condition = {
          StringEquals = {
            "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
            "token.actions.githubusercontent.com:sub" = local.github_deploy_subject
          }
        }
      }
    ]
  })

  lifecycle {
    precondition {
      condition     = var.github_deploy_environment != ""
      error_message = "github_deploy_environment must be set when github_deploy_repository is set, or the trust policy would match no job at all."
    }
  }

  tags = {
    Name        = "${var.app_name}-github-deploy"
    Environment = var.environment
  }
}

resource "aws_iam_role_policy" "github_deploy_ssh_ingress" {
  count = local.create_github_deploy_role ? 1 : 0

  name = "${var.app_name}-github-deploy-ssh-ingress"
  role = aws_iam_role.github_deploy[0].id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "TemporarySshIngressOnThisServerOnly"
        Effect = "Allow"
        Action = [
          "ec2:AuthorizeSecurityGroupIngress",
          "ec2:RevokeSecurityGroupIngress"
        ]
        Resource = "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:security-group/${aws_security_group.server.id}"
      }
    ]
  })
}
