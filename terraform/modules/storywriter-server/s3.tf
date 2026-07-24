# S3 bucket for app content: story illustrations and narration audio.
#
# The app used to hand out Together AI's own image URLs, which expire, so saved
# storybooks eventually went blank. Generated media now gets copied into this
# bucket instead (see app/Services/MediaStorageService.php).
#
# The bucket is PRIVATE. Object keys are stories/{storyId}/pages/{n}/image.png,
# which anyone could guess by counting upwards, and this is children's content,
# so a public bucket would put every kid's story and narration on the open
# internet. The app reads and writes it through the EC2 instance role below and
# hands the tablet a time-limited signed URL.

locals {
  app_content_bucket_name = var.app_content_bucket_name != "" ? var.app_content_bucket_name : "${var.app_name}-content"
}

resource "aws_s3_bucket" "app_content" {
  bucket = local.app_content_bucket_name

  # User-generated content that can only be recreated by paying Together AI and
  # ElevenLabs again. Remove this block deliberately if you ever mean to delete
  # the bucket -- terraform destroy will refuse until you do.
  lifecycle {
    prevent_destroy = true
  }

  tags = {
    Name        = local.app_content_bucket_name
    Environment = var.environment
  }
}

# No public access, by any route: no public ACLs, no public bucket policy.
resource "aws_s3_bucket_public_access_block" "app_content" {
  bucket = aws_s3_bucket.app_content.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Turn ACLs off entirely -- the bucket owner owns every object, and access is
# decided by the IAM policy below and nothing else.
resource "aws_s3_bucket_ownership_controls" "app_content" {
  bucket = aws_s3_bucket.app_content.id

  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "app_content" {
  bucket = aws_s3_bucket.app_content.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

# Failed uploads leave partial data behind that still bills. Clean it up.
resource "aws_s3_bucket_lifecycle_configuration" "app_content" {
  bucket = aws_s3_bucket.app_content.id

  rule {
    id     = "abort-incomplete-multipart-uploads"
    status = "Enabled"

    filter {}

    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}

# The web build fetches signed media URLs with fetch()/XHR, which browsers treat
# as cross-origin against the bucket host. Read-only, and the signed URL is what
# actually gates access -- CORS only decides who may read the response.
resource "aws_s3_bucket_cors_configuration" "app_content" {
  bucket = aws_s3_bucket.app_content.id

  cors_rule {
    allowed_methods = ["GET", "HEAD"]
    allowed_origins = var.app_content_cors_allowed_origins
    allowed_headers = ["*"]
    expose_headers  = ["Content-Length", "Content-Type"]
    max_age_seconds = 3600
  }
}

# Let the app server read and write this one bucket. No AWS access keys are
# needed anywhere: the instance profile already attached to the EC2 instance
# supplies credentials, and Laravel's s3 disk falls through to them when
# AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY are unset.
resource "aws_iam_policy" "app_content" {
  name        = "${var.app_name}-app-content-s3"
  description = "Read/write access to the ${var.app_name} app content bucket"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "AppContentObjectAccess"
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject"
        ]
        Resource = "${aws_s3_bucket.app_content.arn}/*"
      },
      {
        Sid    = "AppContentBucketAccess"
        Effect = "Allow"
        Action = [
          "s3:ListBucket",
          "s3:GetBucketLocation"
        ]
        Resource = aws_s3_bucket.app_content.arn
      }
    ]
  })

  tags = {
    Name        = "${var.app_name}-app-content-s3"
    Environment = var.environment
  }
}

resource "aws_iam_role_policy_attachment" "app_content" {
  role       = aws_iam_role.ec2_role.name
  policy_arn = aws_iam_policy.app_content.arn
}
