# Terraform S3 Backend Configuration for Staging
#
# The S3 bucket must already exist before running terraform init.

terraform {
  backend "s3" {
    bucket  = "storywriter-terraform-state"
    key     = "backend-staging/terraform.tfstate"  # Use existing state path
    region  = "us-east-1"
    encrypt = true
  }
}
