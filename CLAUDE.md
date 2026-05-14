# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies, generate keys, run migrations
composer setup

# Start dev environment (Laravel server, queue worker, logs, Vite in parallel)
composer dev

# Run tests
composer test

# Run a single test
php artisan test --filter TestClassName

# Format code (Laravel Pint)
./vendor/bin/pint

# Check formatting without modifying
./vendor/bin/pint --test
```

## Architecture

**StoryWriter** is a dual-purpose Laravel 12 application:
1. **StoryWriter** — AI-powered children's story generation from conversation transcripts
2. **Heirloom** — Life story capture and AI-synthesized narrative preservation

### API Structure

Routes are versioned under `/api/v1/` (stories) and `/api/heirloom/v1/` (heirloom). Authentication uses Laravel Sanctum (token-based). The login endpoint (`POST /api/auth/login`) creates a user if one doesn't exist — email is the only required field.

- `routes/api.php` — Core story/auth/ElevenLabs API routes
- `routes/heirloom_v1.php` — Heirloom API routes
- `routes/web.php` — Admin dashboard (requires `is_admin=true`) and Heirloom web views

### Story Generation Pipeline

`StoryGenerationController` orchestrates: validate → PostHog tracking → `PromptBuilder::buildStoryPrompt()` → Together AI (text, DeepSeek-V3.1) → `PromptBuilder::parseStoryOutput()` → `PromptBuilder::buildImagePrompt()` → Together AI (image, FLUX.1-schnell) → persist `Story` + `StoryPage` records → return structured response.

The LLM output format is defined in `config/prompts.php`: title line, `[CHARACTERS]` block, pages separated by `---PAGE BREAK---`, `[ILLUSTRATION: ...]` directives per page.

### Key Services

- `app/Services/PromptBuilder.php` — Builds and parses LLM prompts for story generation
- `app/Services/StoryAnalyticsService.php` — Dashboard analytics with DB-agnostic JSON extraction (SQLite/PostgreSQL/MySQL)
- `app/Services/Heirloom/NarrativeService.php` — Synthesises interview transcripts into narratives via Together AI; supports `memoir`, `letter`, and `timeline` formats
- `app/Services/Heirloom/TranscriptionService.php` — Audio-to-text transcription

### External Integrations

All third-party config lives in `config/services.php`:

| Service | Purpose | Key config |
|---|---|---|
| Together AI | Text + image generation | `TOGETHER_API_KEY`; text model DeepSeek-V3.1, image model FLUX.1-schnell |
| ElevenLabs | TTS, conversation AI | `ELEVENLABS_API_KEY`; daily limits tracked in `ElevenLabsUsage` model |
| PostHog | Analytics/event tracking | `POSTHOG_API_KEY`; events on login, story generation start/success/failure |
| AWS SSM | Secrets (staging/prod only) | `AWS_SSM_ENABLED=true`; path pattern `/storywriter/{environment}/{KEY}` |

### Secret Management

- **Local**: `.env` file, SQLite database
- **Staging/Production**: AWS SSM Parameter Store (`config/aws-ssm.php`), cached 5 minutes, EC2 IAM role for access. Never store secrets in `.env` on deployed environments.

### Models

- `User` — has `is_admin` boolean; relations to `stories()` and `elevenLabsUsage()`
- `Story` → `StoryPage` (HasMany) — core story content with illustration prompts and image URLs
- `ElevenLabsUsage` — tracks TTS/conversation usage per user for rate limiting
- `Heirloom/Subject` → `Heirloom/Session` → `Heirloom/Transcript` → `Heirloom/Narrative` — life story capture chain; `Narrative` has a `share_token` for public sharing

### Frontend

Tailwind CSS + Alpine.js, bundled with Vite. Run `npm run dev` (started automatically by `composer dev`).
