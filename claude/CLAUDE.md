# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 API backend for StoryWriter - provides AI-powered services for story generation and text-to-speech functionality. The API serves a React Native/Expo mobile app for creating interactive digital storybooks.

## Development Commands

### Setup
```bash
composer install                                              # Install PHP dependencies
npm install                                                   # Install Node.js dependencies
cp .env.example .env                                         # Copy environment file
php artisan key:generate                                     # Generate application key
touch database/database.sqlite && chmod 664 database/database.sqlite  # Create SQLite database
php artisan migrate                                          # Run database migrations
```

Quick setup:
```bash
composer setup               # Runs all setup steps in one command
```

### Development
```bash
composer dev                 # Start all services (server, queue, logs, vite)
php artisan serve            # Start development server only
php artisan queue:listen     # Start queue worker
php artisan pail             # Tail logs in real-time
npm run dev                  # Start Vite for frontend assets
```

### Testing
```bash
php artisan test                    # Run all tests
php artisan test --filter=StoryTest # Run specific test class
vendor/bin/phpunit tests/Feature/Api/V1/StoryTest.php  # Run specific file
```

Tests use in-memory SQLite database (`:memory:`) automatically.

### Database
```bash
php artisan migrate          # Run migrations
php artisan migrate:fresh    # Drop all tables and re-migrate
php artisan migrate:rollback # Rollback last migration
php artisan db:seed          # Run database seeders
```

Database uses SQLite by default (`database/database.sqlite`).

### Code Quality
```bash
vendor/bin/pint              # Format code with Laravel Pint
```

### Building
```bash
npm run build                # Build frontend assets with Vite
```

## Architecture

### API Structure
- **Routes**: API routes defined in `routes/api.php` with versioning (`/api/v1/*`)
- **Controllers**: API controllers in `app/Http/Controllers/Api/V1/`
- **Models**: Eloquent models in `app/Models/`
- **Resources**: API resources for JSON transformation in `app/Http/Resources/`
- **Requests**: Form request validation in `app/Http/Requests/`
- **Policies**: Authorization policies in `app/Policies/`

### API Endpoints

**Public Routes:**
- `POST /api/auth/login` - User authentication
- `POST /api/login` - Alternative login endpoint
- `POST /api/conversation/sdk-credentials` - Generate ElevenLabs SDK credentials
- `POST /api/conversation/tts` - Text-to-speech via ElevenLabs
- `GET /api/conversation/voices` - Fetch available voices

**Protected Routes (require `auth:sanctum`):**
- `POST /api/stories/generate` - Generate story using Together AI (requires authentication)
- `POST /api/heartbeat` - Keep session alive
- `GET /api/user` - Get authenticated user

**Story CRUD (versioned):**
- `GET /api/v1/stories` - List all stories
- `GET /api/v1/stories/{id}` - Get single story
- `POST /api/v1/stories` - Create story
- `PUT /api/v1/stories/{id}` - Update story
- `DELETE /api/v1/stories/{id}` - Delete story

### External Services Integration
The API integrates with multiple AI services directly through controller methods (no separate service layer):

**Note:** All external API integrations are implemented inline in controllers using Laravel's `Http` facade. There are no separate service classes in `app/Services/`.

**ElevenLabsController** (`app/Http/Controllers/Api/V1/ElevenLabsController.php`)
- Proxies requests to ElevenLabs API for text-to-speech
- Generates temporary SDK credentials for React Native client
- Fetches available voice models
- Requires `ELEVENLABS_API_KEY` environment variable

**StoryGenerationController** (`app/Http/Controllers/Api/V1/StoryGenerationController.php`)
- Uses Together AI for LLM-based story generation AND image generation
- **Text Generation:**
  - Makes HTTP calls to `https://api.together.xyz/v1/chat/completions`
  - Currently configured to use `meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo` model
  - Accepts transcript input and generation options (maxTokens: default 2000, temperature: default 0.7)
  - System prompt: "You are a children's book author. Write a 5-page story. Separate pages with '---PAGE BREAK---'. Do NOT generate images, just text."
  - Script timeout set to 120 seconds for longer generation times
- **Image Generation:**
  - Makes HTTP calls to `https://api.together.xyz/v1/images/generations`
  - Uses `black-forest-labs/FLUX.1-schnell` model for fast, high-quality cover images
  - Generates 1024x768 images with 4 steps (optimized for speed)
  - Creates image prompt from user's transcript (first 200 chars): "Children's book illustration, cover art, cute style: [transcript]"
  - If successful, injects image at top of story as markdown: `![](url)`
  - Image generation is optional - story continues if image generation fails
- Automatically persists generated stories to database with extracted title, slug, and embedded image
- Requires `TOGETHER_API_KEY` environment variable
- Request format: `{ "transcript": "string", "options": { "maxTokens": int, "temperature": float } }`
- Response format: `{ "data": { "story": "![](image_url)\n\ngenerated text..." } }` (image URL embedded in story text)

### Testing Patterns
Tests use the following structure:
- **Arrange**: Set up test data using factories (`Story::factory()->create()`)
- **Act**: Make HTTP request (`$this->getJson()`, `$this->postJson()`)
- **Assert**: Verify response structure and data

Tests are organized:
- `tests/Feature/` - API endpoint tests
- `tests/Unit/` - Unit tests for individual classes
- Uses `RefreshDatabase` trait to reset database between tests

## Environment Configuration

Required environment variables:
```
# AI Service API Keys
ELEVENLABS_API_KEY=sk-xxx    # For text-to-speech services
TOGETHER_API_KEY=xxx         # For LLM story generation

# Database (SQLite by default)
DB_CONNECTION=sqlite

# Queue (database driver by default)
QUEUE_CONNECTION=database
```

Optional AWS configuration for future use:
```
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1
```

## Database

Uses SQLite for development (configured in `.env.example`).

Key migrations:
- `create_users_table` - User authentication
- `create_stories_table` - Story data storage with columns:
  - `id` - Primary key
  - `user_id` - Foreign key to users table
  - `name` - Story title (extracted from first line of generated text)
  - `slug` - URL-friendly slug with random suffix for uniqueness
  - `body` - Full generated story text
  - `prompt` - Original transcript/prompt sent to Together AI
  - `created_at`, `updated_at` - Timestamps
- `create_cache_table` - Cache storage
- `create_jobs_table` - Queue jobs
- `create_personal_access_tokens_table` - API tokens (Sanctum)
