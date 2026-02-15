# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Full-stack Laravel 12 application for StoryWriter - an AI-powered platform for creating interactive children's digital storybooks. Includes both a web-based admin dashboard and a REST API backend that serves a React Native/Expo mobile app.

**Key Components:**
- Web admin dashboard with analytics (Laravel Breeze authentication)
- REST API with Sanctum authentication
- AI story generation using Together AI
- ElevenLabs integration for TTS and conversational AI
- PostgreSQL (production) / SQLite (local development)
- AWS Parameter Store for secure secrets management

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

### Application Structure

**Web Application:**
- **Routes**: Web routes in `routes/web.php` (dashboard, profile, auth)
- **Controllers**:
  - `DashboardController` - Admin dashboard with analytics
  - `ProfileController` - User profile management
  - Auth controllers in `app/Http/Controllers/Auth/`
- **Views**: Blade templates in `resources/views/`
- **Authentication**: Laravel Breeze with email verification
- **Admin Authorization**: Uses `is_admin` flag on User model (checked via `isAdmin()` method)

**API:**
- **Routes**: API routes defined in `routes/api.php` with versioning (`/api/v1/*`)
- **Controllers**: API controllers in `app/Http/Controllers/Api/V1/`
- **Models**: Eloquent models in `app/Models/`
- **Services**: Business logic in `app/Services/`
  - `StoryAnalyticsService` - Analytics and metrics
  - `PromptBuilder` - Prompt construction utilities
- **Resources**: API resources for JSON transformation in `app/Http/Resources/`
- **Requests**: Form request validation in `app/Http/Requests/`
- **Policies**: Authorization policies in `app/Policies/`

### Web Routes

**Public:**
- `GET /` - Welcome page

**Authenticated (`auth` + `verified` middleware):**
- `GET /dashboard` - Admin dashboard (requires `is_admin = true`)
- `GET /dashboard/analytics` - Analytics dashboard (requires `is_admin = true`)
- `GET /dashboard/stories/{story}` - View story details (requires `is_admin = true`)
- `GET /stories/{story}` - View story (authenticated users)
- `GET /profile` - User profile edit
- `PATCH /profile` - Update profile
- `DELETE /profile` - Delete account

**Admin Authorization:**
Both `DashboardController::index()` and `DashboardController::analytics()` check `auth()->user()->isAdmin()` and return 403 if false. Only users with `is_admin = true` can access dashboard routes.

### API Endpoints

**Public Routes:**
- `POST /api/login` - User authentication (returns Sanctum token)

**Protected Routes (require `auth:sanctum`):**
- `GET /api/user` - Get authenticated user
- `POST /api/heartbeat` - Keep session alive
- `POST /api/stories/generate` - Generate story using Together AI
- `POST /api/generate-story` - Alternative story generation endpoint (with logging middleware)

**Story CRUD (versioned - currently no auth):**
- `GET /api/v1/stories` - List all stories
- `GET /api/v1/stories/{id}` - Get single story
- `POST /api/v1/stories` - Create story
- `PUT /api/v1/stories/{id}` - Update story
- `DELETE /api/v1/stories/{id}` - Delete story

**ElevenLabs Conversation API (require `auth:sanctum`):**
- `POST /api/conversation/sdk-credentials` - Get signed WebSocket URL for conversation (returns `signed_url`)
- `POST /api/conversation/proxy` - Proxy conversation requests to ElevenLabs
- `POST /api/conversation/tts` - Text-to-speech conversion
- `GET /api/conversation/voices` - List available voices

### External Services Integration
The API integrates with multiple AI services using a mix of inline controller code and dedicated service classes.

**ElevenLabsController** (`app/Http/Controllers/Api/V1/ElevenLabsController.php`)
- Proxies requests to ElevenLabs API for text-to-speech and conversational AI
- `sdkCredentials()` - Generates signed WebSocket URLs for secure conversation connections (returns `signed_url`)
- `conversationProxy()` - Proxies conversation requests to ElevenLabs API
- `textToSpeech()` - Converts text to speech audio
- `voices()` - Fetches available voice models
- Requires `ELEVENLABS_API_KEY` environment variable
- **Security Note:** Uses signed URLs instead of exposing API keys to the frontend

**StoryAnalyticsService** (`app/Services/StoryAnalyticsService.php`)
- Provides analytics and metrics for the admin dashboard
- Methods include:
  - `getOverview()` - Summary statistics (total users, stories, generations)
  - `getTotalRequests()` - Count of story generation requests
  - `getAverageTranscriptLength()` - Average prompt length
  - `getAverageStoryLength()` - Average generated story length
  - `getRecentActivity($limit)` - Recent story generations
  - `getDailyActivity($days)` - Daily generation trends
- Used by `DashboardController` for analytics dashboard

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

### Local Development

Required environment variables for local development:
```
# AI Service API Keys (local only - use Parameter Store in staging/production)
ELEVENLABS_API_KEY=sk-xxx    # For text-to-speech services
TOGETHER_API_KEY=xxx         # For LLM story generation

# Database (SQLite by default)
DB_CONNECTION=sqlite

# Queue (database driver by default)
QUEUE_CONNECTION=database
```

### Staging/Production

**Database:**
Production uses PostgreSQL instead of SQLite:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=storywriter_production
DB_USERNAME=storywriter_app
DB_PASSWORD=your_secure_password
```

**AWS Parameter Store:**
API keys are loaded from AWS Systems Manager Parameter Store in staging/production:
```
# Enable SSM Parameter Store
AWS_SSM_ENABLED=true
AWS_DEFAULT_REGION=us-east-1
APP_ENV=production  # or 'staging'

# SSM cache settings
AWS_SSM_CACHE_TTL=300  # Cache parameters for 5 minutes
```

**Required SSM Parameters:**
- `/storywriter/production/ELEVENLABS_API_KEY` - ElevenLabs API key (SecureString)
- `/storywriter/production/TOGETHER_API_KEY` - Together AI API key (SecureString)
- `/storywriter/staging/ELEVENLABS_API_KEY` - Staging ElevenLabs API key (SecureString)
- `/storywriter/staging/TOGETHER_API_KEY` - Staging Together AI API key (SecureString)

**IAM Permissions:**
The EC2 instance requires an IAM role with:
- `ssm:GetParameters`, `ssm:GetParameter`, `ssm:GetParametersByPath` for `/storywriter/*`
- `kms:Decrypt` for SecureString parameter decryption

See `terraform/main.tf` for IAM role configuration.

## Database

Uses SQLite for local development and PostgreSQL for staging/production.

Key migrations:
- `create_users_table` - User authentication and authorization
  - `id` - Primary key
  - `name` - User's full name
  - `email` - User email (unique)
  - `password` - Hashed password
  - `is_admin` - Boolean flag for admin access (default: false)
  - `email_verified_at` - Email verification timestamp
  - `created_at`, `updated_at` - Timestamps
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

### User Model Admin Methods

The `User` model (`app/Models/User.php`) includes helper methods for admin authorization:

```php
// Check if user is an admin
public function isAdmin(): bool
{
    return $this->is_admin === true;
}

// Scope to get only admins
User::admins()->get();

// Scope to get only non-admin users (storytellers)
User::storytellers()->get();
```

**Usage in controllers:**
```php
if (!auth()->user()->isAdmin()) {
    abort(403, 'Access Denied: Admins Only');
}
```
