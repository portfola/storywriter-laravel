# Terraform S3 Backend Configuration for Production
#
# The S3 bucket must already exist before running terraform init.

terraform {
  backend "s3" {
    bucket  = "storywriter-terraform-state"
    key     = "environments/prod/terraform.tfstate"
    region  = "us-east-1"
    encrypt = true
  }
}
