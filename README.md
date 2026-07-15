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
```

## Releases & deployment

Deploys are driven by two workflows:

| Trigger | Workflow | Environment |
|---|---|---|
| Merge/push to `main` | [`deploy-staging.yml`](.github/workflows/deploy-staging.yml) | **staging** (unattended) |
| Push a `v*` tag | [`deploy-prod.yml`](.github/workflows/deploy-prod.yml) | **production** (waits for manual approval) |
| Manual `workflow_dispatch` | either workflow | that workflow's environment |

The production GitHub environment has a required-reviewer rule, so every
production deploy — including tag pushes — pauses at an approval button in the
Actions UI. Staging deploys run unattended.

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
  (last 5 kept). SSH in as `deploy`, copy the backup back over
  `/var/www/storywriter-prod`, then re-run the cache rebuild and restart
  PHP-FPM (`php artisan config:cache route:cache view:cache`, `sudo systemctl
  restart php8.4-fpm`). No rebuild, so it's fast — but follow up with a proper
  patch release.

**Migrations caveat:** neither path undoes database migrations — the bad
release's migrations have already run against the production database. Keep
migrations backwards-compatible (additive) so old code can run against the new
schema, and prefer rolling forward with a fix over `migrate:rollback`.