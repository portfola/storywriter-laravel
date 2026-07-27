# StoryWriter

Full-stack Laravel 12 application for StoryWriter - an AI-powered platform for creating interactive children's digital storybooks.

## Features

### Web Application
- **Admin Dashboard**: Web-based admin panel for managing users and stories
- **User Authentication**: Laravel Breeze authentication with email verification
- **Analytics Dashboard**: Story generation metrics and user activity tracking
- **Story Management**: View and manage all user-generated stories

### API Backend
- **AI Story Generation**: Generate children's stories using Together AI
- **Image Generation**: Automatic cover art creation
- **Text-to-Speech**: Voice narration via ElevenLabs integration
- **Conversation AI**: ElevenLabs conversational AI proxy with signed URL authentication
- **REST API**: Versioned endpoints for story CRUD and AI services
- **API Authentication**: Laravel Sanctum token-based authentication

## Tech Stack

- Laravel 12 (PHP 8.2+)
- SQLite (local development)
- PostgreSQL (staging/production)
- Together AI (LLM & Image Generation)
- ElevenLabs (TTS & Conversational AI)
- Laravel Sanctum (API Authentication)
- Laravel Breeze (Web Authentication)
- AWS SDK (Parameter Store for secrets management)

## Quick Start

```bash
composer setup
composer dev
```

Visit `http://localhost:8000`

### Admin Access

The admin dashboard (`/dashboard`) requires authentication and the `is_admin` flag:
- Admin users have `is_admin = true` in the database
- Only admin users can access dashboard routes and analytics
- Create admin users via database seeder or manually set the flag

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- Together AI API key (for story generation)
- ElevenLabs API key (for TTS and conversation AI)

## Environment

### Local Development

Local development uses SQLite for the database (no setup required).

Copy `.env.example` to `.env` and add your API keys:

```env
DB_CONNECTION=sqlite
TOGETHER_API_KEY=your_key_here
ELEVENLABS_API_KEY=your_key_here
FILESYSTEM_DISK=public
```

`FILESYSTEM_DISK` is where generated story images and narration audio are
written. It must be `public`, not the old `local` default — the `local` disk is
private, so images written there come back 403/404 in the app. If your `.env`
predates this and still says `local`, change it by hand; copying
`.env.example` again won't touch a file you already have. Run
`php artisan storage:link` once so `public/storage` exists.

### App content storage (staging/production)

Story illustrations and narration audio are copied out of Together AI and
ElevenLabs and kept in an S3 bucket, one per environment, defined in
`terraform/modules/storywriter-server/s3.tf`. Terraform names it
`{app_name}-content` — so `storywriter-staging-content` and
`storywriter-prod-content` — and the bucket name is also a stack output
(`app_content_bucket`).

The bucket is private and stays private. Object keys look like
`stories/{storyId}/pages/{n}/image.png`, which anyone could walk by counting
upwards, and it's children's content, so it is never served straight off S3.
The app hands out a time-limited signed URL instead.

Nothing needs AWS access keys. The EC2 instance profile already grants the app
read/write on its own bucket, and Laravel's `s3` disk falls through to those
credentials when `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` are unset. Once
the bucket exists, a deployed environment only needs:

```env
FILESYSTEM_DISK=s3
AWS_BUCKET=storywriter-staging-content   # terraform output app_content_bucket
AWS_DEFAULT_REGION=us-east-1
```

None of those are secrets, so they belong in the deploy workflow's `.env` block
rather than SSM Parameter Store.

The bucket carries `prevent_destroy = true`, because the only way to recreate
its contents is to pay Together AI and ElevenLabs for them again. `terraform
destroy` will refuse until someone removes that block on purpose.

S3 bucket names are globally unique, so if `{app_name}-content` is already taken
by somebody else, `terraform plan` will say so and you can set
`app_content_bucket_name` to pick another. Do that **before the first apply**.
Afterwards, changing the name means replacing the bucket, and `prevent_destroy`
will refuse — you'd have to drop that block, apply, and copy the objects across
by hand.

#### Moving the media that was written before the bucket existed

Staging and production ran for a while with `FILESYSTEM_DISK=public` and
`php artisan storage:link`. Everything written in that time is under
`storage/app/public/stories/...`, symlinked into `public/storage`, and served by
nginx to anyone who guesses a story ID. Switching the disk to S3 only changes
where *new* files go — the old ones stay exposed until they are moved.

`media:relocate-exposed` moves them. It reads from the local `public` disk and
writes to whatever the default disk now is, deletes the exposed copy, and
repoints the row — but only for files it actually moved, so a row is never left
pointing at a path with nothing behind it.

Run it **once per environment, after the deploy that sets `FILESYSTEM_DISK=s3`**.
Before that deploy the source and destination are the same disk, and the command
says so and stops rather than pretending to have done something.

```bash
php artisan media:relocate-exposed --dry-run   # lists what would move
php artisan media:relocate-exposed
```

It is safe to re-run: anything already moved is simply not found on the public
disk the second time. A file it can't move is logged and skipped rather than
stopping the run, so a partial run can be finished by running it again.

Once every environment is done, the `storage:link` step and the public disk have
no story media left on them.

ACLs are left enabled on the bucket (`BucketOwnerPreferred`) even though
disabling them is the more modern choice. Laravel's S3 driver names an ACL on
every upload, and a bucket with ACLs disabled rejects those outright. The public
access block is what actually keeps the bucket private. There's a longer note in
`s3.tf` next to the setting.

## Releases & deployment

Deploys are driven by two workflows:

| Trigger | Workflow | Environment |
|---|---|---|
| Merge/push to `main` | [`deploy-staging.yml`](.github/workflows/deploy-staging.yml) | **staging** (unattended) |
| Push a `vX.Y.Z` tag | [`deploy-prod.yml`](.github/workflows/deploy-prod.yml) | **production** (waits for manual approval) |
| Manual `workflow_dispatch` | either workflow | that workflow's environment |

The production GitHub environment has a required-reviewer rule, so every
production deploy — including tag pushes — pauses at an approval button in the
Actions UI. Staging deploys run unattended.

The test suite also runs in CI on every PR and push to `main`
([`tests.yml`](.github/workflows/tests.yml)) — only tag commits with a green
run.

**Nothing reaches production except from a release tag.** Three things enforce
that, and it's worth knowing all three, because hitting one of them looks like
a different failure each time:

- The push trigger only matches semver-shaped tags (`v[0-9]*.[0-9]*.[0-9]*`),
  so a stray tag like `v9` or `v2-broken` simply doesn't start a run.
- The *Run workflow* ref picker defaults to `main`, so the production workflow
  fails fast on its **Require a release tag** step unless you pick a `v*` tag
  in the ref dropdown. A branch is refused, and so is a non-release tag like
  `baseline`.
- The `production` GitHub environment restricts deployments to tags matching
  `v*.*.*`, which holds even if someone edits the workflow. A run that trips
  this one is rejected at the environment gate rather than by a workflow step.

Staging is deliberately unrestricted — dispatch it from any branch you like.

Unlike the frontend, this repo has no version fields to keep in sync — the
`vX.Y.Z` git tag **is** the version. Tags follow semver and are shared with
the frontend's numbering only in spirit; the two repos version independently.

### Cutting a release

1. On an up-to-date `main` with a clean working tree, make sure the suite
   passes:

   ```bash
   php artisan test
   ```

2. Tag and push:

   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin vX.Y.Z
   ```

3. In the Actions UI, approve the production deploy on the tag's run of
   **Deploy to Production**.

4. Verify the API responds at https://api.storywriter.net (the workflow's
   health check must also pass for the run to go green).

### Hotfixes

Default path — `main` is releasable: commit the fix to `main` (via PR as
usual), then cut a patch release from it (tag `vX.Y.(Z+1)`, push, approve).

Only when `main` carries unreleased work you don't want to ship yet, branch
from the last release tag instead:

```bash
git checkout -b hotfix/vX.Y.(Z+1) vX.Y.Z   # branch from the last released tag
# commit the fix (or cherry-pick it from main)
php artisan test
git tag -a vX.Y.(Z+1) -m "vX.Y.(Z+1)"
git push origin HEAD --follow-tags          # the v* tag triggers the production deploy
```

Approve the production deploy as usual, then **merge the hotfix branch back
into `main`** so the fix isn't lost.

### Rollback

Two options, depending on how fast you need to be:

- **Clean path — redeploy a known-good tag.** Actions → **Deploy to
  Production** → *Run workflow* → pick the previous release tag as the ref.
  This rebuilds that code from scratch and redeploys it; goes through the
  normal approval gate.

- **Fast path — restore the on-server backup.** Every deploy first copies the
  current release to `/var/www/releases/backup_<timestamp>/` on the server
  (last 5 kept). SSH in as `deploy` and restore it (`--delete` removes any
  files the bad release added), then rebuild the caches and restart PHP-FPM:

  ```bash
  rsync -a --delete --exclude='storage/app/public/' \
    /var/www/releases/backup_<timestamp>/ /var/www/storywriter-prod/
  cd /var/www/storywriter-prod
  php artisan optimize:clear
  php artisan config:cache && php artisan route:cache && php artisan view:cache
  sudo systemctl restart php8.4-fpm
  ```

  No rebuild, so it's fast — but follow up with a proper patch release.

  The `--exclude` matters: backups don't contain `storage/app/public` (that's
  generated story images and audio, not code), so without it `--delete` would
  wipe every stored image and leave saved storybooks blank.

**Migrations caveat:** neither path undoes database migrations — the bad
release's migrations have already run against the production database. Keep
migrations backwards-compatible (additive) so old code can run against the new
schema, and prefer rolling forward with a fix over `migrate:rollback`.
