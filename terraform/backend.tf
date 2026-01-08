# Terraform S3 Backend Configuration
#
# Before running terraform init, update the bucket and region values below.
# The S3 bucket must already exist.
#
# Optional: Enable DynamoDB state locking by uncommenting the dynamodb_table line
# and creating a DynamoDB table with a primary key named "LockID" (String type).

terraform {
  backend "s3" {
    bucket = "storywriter-terraform-state"
    key    = "backend-staging/terraform.tfstate"
    region = "us-east-1"
    # dynamodb_table = "terraform-state-lock"
    encrypt = true
  }
}
