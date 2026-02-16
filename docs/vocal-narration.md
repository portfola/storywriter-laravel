# Vocal Narration - Implementation Plan

**Feature:** Text-to-speech vocal narration for StoryWriter stories using ElevenLabs API
**Status:** In Progress
**Last Updated:** February 15, 2026

---

## Overview

Enable users to listen to AI-generated audio narration of story pages using ElevenLabs text-to-speech technology. The backend proxies requests to ElevenLabs, providing API key security, usage tracking, and cost management.

**Related Documentation:**
- [docs/elevenlabs.md](./elevenlabs.md) - Detailed ElevenLabs integration guide with code examples
- [docs/vocal-narration-backend.md](./vocal-narration-backend.md) - Original detailed specification

---

## Implementation Checklist

### Phase 1: Core Infrastructure ✅

**Status:** Already completed in production

- [x] Create `ElevenLabsController` with TTS endpoint
- [x] Add authentication middleware (`auth:sanctum`)
- [x] Configure routes under `/api/conversation/*` prefix
- [x] Add ElevenLabs API key to environment configuration
- [x] Set up AWS Parameter Store for production secrets
- [x] Implement basic error handling and logging
- [x] Deploy TTS endpoint: `POST /api/conversation/tts`
- [x] Deploy voices endpoint: `GET /api/conversation/voices`
- [x] Deploy SDK credentials endpoint: `POST /api/conversation/sdk-credentials`

### Phase 2: Optimize Performance 🔄

**Status:** In progress - model optimization needed

#### Update Default TTS Model

- [x] Update default model in `ElevenLabsController::textToSpeech()` - Completed 2026-02-15
  - Current: `eleven_multilingual_v2`
  - New: `eleven_flash_v2_5`
  - Location: `app/Http/Controllers/Api/V1/ElevenLabsController.php` line 152

- [x] Update default in `config/services.php` - Completed 2026-02-15
  - Change `default_model` from `eleven_multilingual_v2` to `eleven_flash_v2_5`

- [x] Test TTS with new model - Completed 2026-02-15
  - Created comprehensive test suite: `tests/Feature/Api/V1/ElevenLabsControllerTest.php`
  - Verified default model uses `eleven_flash_v2_5`
  - Validated voice settings pass correctly to API
  - Confirmed audio output format (audio/mpeg)
  - Tested voice consistency across multiple story pages
  - All 15 tests passing with 52 assertions


### Phase 3: Usage Tracking & Cost Management 🚧

**Status:** Not implemented - HIGH PRIORITY

#### Database Schema

- [x] Create migration for usage tracking table - Completed 2026-02-15
  ```bash
  php artisan make:migration create_elevenlabs_usage_table
  ```

- [x] Define table schema with fields - Completed 2026-02-15
  - `id` - Primary key
  - `user_id` - Foreign key to users table
  - `service_type` - 'tts' or 'conversation'
  - `character_count` - Text length processed
  - `voice_id` - ElevenLabs voice ID used
  - `model_id` - Model used (flash, multilingual, turbo)
  - `estimated_cost` - Calculated cost in USD
  - `created_at`, `updated_at` - Timestamps
  - Add indexes on `user_id` and `created_at`

- [x] Run migration - Completed 2026-02-15
  ```bash
  php artisan migrate
  ```

#### Create Usage Model

- [x] Generate Eloquent model - Completed 2026-02-15
  ```bash
  php artisan make:model ElevenLabsUsage
  ```

- [x] Define fillable fields - Completed 2026-02-15
  - `user_id`, `service_type`, `character_count`, `voice_id`, `model_id`, `estimated_cost`

- [x] Add relationship to User model - Completed 2026-02-15
  - `hasMany` relationship: `User::elevenLabsUsage()`

- [x] Create helper method `logTtsRequest()` - Completed 2026-02-15
  - Calculate cost based on character count and model
  - Create database record
  - Location: `app/Models/ElevenLabsUsage.php`

#### Implement Usage Logging

- [x] Add usage logging to `ElevenLabsController::textToSpeech()` - Completed 2026-02-15
  - Log after successful TTS request
  - Calculate cost: `strlen($text) * 0.000024` (flash model)
  - Store in database via `ElevenLabsUsage::logTtsRequest()`
  - Location: `app/Http/Controllers/Api/V1/ElevenLabsController.php` lines 147-169

- [x] Add usage logging to conversation endpoints - Completed 2026-02-15
  - Added `ElevenLabsUsage::logConversationRequest()` method
  - Logs character counts for conversation messages when action='message'
  - Location: `app/Http/Controllers/Api/V1/ElevenLabsController.php` lines 102-113
  - Location: `app/Models/ElevenLabsUsage.php` lines 82-100

- [x] Test usage logging - Completed 2026-02-15
  - Created comprehensive test suite with 6 tests
  - Verifies usage records are created after successful TTS requests
  - Validates cost calculations for flash and multilingual models
  - Tests multi-user tracking and per-user isolation
  - Confirms no logging on failed requests
  - Location: `tests/Feature/Api/V1/ElevenLabsControllerTest.php` lines 448-611

#### Daily Usage Limits

- [x] Define daily character limits - Completed 2026-02-15
  - Free tier: 10,000 characters/day (~10 pages)
  - Paid tier: 50,000 characters/day (~50 pages)
  - Stored in `config/services.php` under `elevenlabs.daily_limit_free` and `elevenlabs.daily_limit_paid`

- [x] Implement limit check in `textToSpeech()` - Completed 2026-02-15
  - Query today's usage for authenticated user via `ElevenLabsUsage::getTodayUsage()`
  - Compare against limit via `ElevenLabsUsage::wouldExceedLimit()`
  - Return 429 error if exceeded
  - Include usage info in error response with `limit_info` object

- [x] Add user-friendly error message - Completed 2026-02-15
  - "Daily narration limit reached. Please try again tomorrow."
  - Shows characters_used, daily_limit, and requested_characters
  - Location: `app/Http/Controllers/Api/V1/ElevenLabsController.php` lines 160-174

- [x] Test limit enforcement - Completed 2026-02-15
  - Created 8 comprehensive test cases for daily limits
  - Tests cover: exceeding limit, within limit, exact limit, reset on new day, multi-user isolation
  - All tests passing (28 total ElevenLabs tests)
  - Location: `tests/Feature/Api/V1/ElevenLabsControllerTest.php` lines 654-1000

### Phase 4: Monitoring & Observability 📊

**Status:** Not implemented - MEDIUM PRIORITY

#### Admin Dashboard

- [x] Create admin usage overview page - Completed 2026-02-15
  - Total TTS requests today/week/month
  - Total characters processed
  - Total estimated cost
  - Top users by usage

- [x] Add usage charts - Completed 2026-02-15
  - Daily character usage trend (last 30 days)
  - Cost breakdown by model (flash vs. multilingual)
  - Per-user usage statistics

- [x] Create route and view - Completed 2026-02-15
  - `GET /dashboard/elevenlabs-usage`
  - Require admin authentication
  - Location: `DashboardController::elevenLabsUsage()`

#### Cost Alerting

- [x] Set up cost threshold alerting - Completed 2026-02-15
  - Send email when daily cost exceeds $10
  - Alert when approaching monthly budget ($180)
  - Notify admin of unusual usage spikes (2x threshold)

- [x] Create scheduled task - Completed 2026-02-15
  - Created `app/Console/Commands/MonitorElevenLabsCost.php`
  - Command: `php artisan elevenlabs:monitor-cost`
  - Options: `--period=today|week|month` and `--notify`

- [x] Register in `routes/console.php` - Completed 2026-02-15
  - Runs daily at 6 PM with notifications enabled
  - Checks costs against defined thresholds
  - Sends email alerts to admin users if exceeded

#### Logging Improvements

- [x] Add structured logging for TTS requests - Completed 2026-02-15
  - User ID, text length, voice ID, model ID
  - Response time, success/failure
  - Use Laravel's logging channels
  - Logs request initiation, successful completion with metrics, and failures with error details

- [x] Log rate limit events - Completed 2026-02-15
  - When user hits daily limit (already logged with warning level)
  - When ElevenLabs returns 429 (added logging for all endpoints)
  - Track for pattern analysis
  - Added `rate_limited` flag to log context for easy filtering

- [x] Set up log monitoring - Completed 2026-02-15
  - Created `elevenlabs:monitor-logs` Artisan command with multiple modes (errors, rate-limits, slow, stats)
  - Added dedicated `elevenlabs` log channel in `config/logging.php`
  - Created comprehensive documentation: `docs/elevenlabs-monitoring.md`
  - Features: Search for errors, track response times, monitor API key issues, summary statistics
  - Location: `app/Console/Commands/MonitorElevenLabsLogs.php`

### Phase 5: Testing & Quality Assurance ✅

**Status:** Partially complete - expand coverage

#### Unit Tests

- [x] Test usage tracking model - Completed 2026-02-15
  - `ElevenLabsUsage::logTtsRequest()` - ✅ Tests cost calculation for all models (flash, multilingual, turbo)
  - Cost calculation accuracy - ✅ Tests accurate cost per character for each model
  - Relationship to User model - ✅ Tests belongsTo and hasMany relationships
  - Created comprehensive test suite: `tests/Unit/ElevenLabsUsageTest.php`
  - 23 tests covering all model methods and edge cases
  - Includes tests for:
    - `logTtsRequest()` - all models and cost calculations
    - `logConversationRequest()` - conversation tracking
    - `getTodayUsage()` - daily usage tracking with date isolation
    - `getDailyLimit()` - limit retrieval
    - `wouldExceedLimit()` - limit validation logic
    - `getTotalRequests()`, `getTotalCharacters()`, `getTotalCost()` - analytics methods
    - `getTopUsers()`, `getCostByModel()` - aggregation methods
    - Type casting for character_count (integer) and estimated_cost (decimal)

- [x] Test daily limit logic - Completed 2026-02-15
  - User within limit (should succeed) ✅ Tested in integration tests
  - User exceeds limit (should fail with 429) ✅ Tested in integration tests
  - Limit reset on new day ✅ Tested in integration tests
  - Note: Implemented at integration level in ElevenLabsControllerTest lines 654-949

#### Integration Tests

- [x] Update existing `ElevenLabsControllerTest` - Completed 2026-02-15
  - Add test for default model (`eleven_flash_v2_5`)
  - Test usage tracking after successful request
  - Test daily limit enforcement

- [x] Test error scenarios - Completed 2026-02-15
  - Invalid API key ✅
  - Rate limit from ElevenLabs (429) ✅
  - Network timeout ✅
  - Invalid voice ID ✅

- [x] Test authentication - Completed 2026-02-15
  - Unauthenticated request returns 401 ✅
  - Valid token returns audio ✅


### Phase 6: Documentation 📝

**Status:** Complete

- [x] Create `docs/elevenlabs.md` - Technical integration guide
- [x] Create `docs/vocal-narration.md` - Implementation checklist (this file)
- [x] Update `docs/CLAUDE.md` - Add ElevenLabs overview
- [x] Document usage tracking schema in database docs - Completed 2026-02-15
  - Added `create_elevenlabs_usage_table` to database migration list in CLAUDE.md
  - Documented all table columns, indexes, and purpose
  - Added comprehensive ElevenLabsUsage model methods documentation
  - Includes logging, daily limits, analytics, cost calculation, and relationships
- [x] Add API endpoint documentation - Completed 2026-02-15
  - Created comprehensive `docs/api-elevenlabs.md` with all endpoints
  - Includes authentication examples, request/response formats, error handling
  - Added curl examples, JavaScript/React Native integration examples
  - Documented rate limits, usage tracking, and cost calculations
  - Covers all 4 endpoints: TTS, voices, SDK credentials, conversation proxy

### Phase 7: Deployment & Rollout 🚀

**Status:** Not started

#### Pre-Deployment Checklist

- [ ] All Phase 2-4 tasks complete
- [ ] Tests passing (unit + integration)
- [ ] Usage tracking verified in staging
- [ ] Daily limits tested in staging
- [ ] Admin dashboard functional
- [ ] Cost alerting configured

---

## Notes

- **API Key Security:** Never expose `ELEVENLABS_API_KEY` to frontend
- **Rate Limiting:** Current Laravel throttle: 60 req/min per user (adequate for now)
- **Voice ID:** Default is Cassidy (`56AoDkrOh6qfVPDXZ7Pt`) - child-friendly voice
- **SDK Credentials:** Actively used for conversational AI agent (NOT deprecated)
- **Backward Compatibility:** Keep `/api/conversation/*` structure for existing clients

---

## Questions & Decisions

**Resolved:**
- ✅ Use `/api/conversation/*` routes (existing structure)
- ✅ Default model: `eleven_flash_v2_5` (performance over quality)
- ✅ No service class refactoring (out of scope)
- ✅ SDK credentials endpoint is active (not deprecated)
- ✅ Daily limit: 10k characters for free users
- ✅ No need for user preference for narration voice

**Pending:**
- ❓ Should we cache audio at backend or frontend?
- ❓ Implement global daily cost ceiling? 

---