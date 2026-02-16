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

- [ ] Update frontend to use `eleven_flash_v2_5` by default
  - Check mobile app TTS requests
  - Update any hardcoded model references

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

- [ ] Create admin usage overview page
  - Total TTS requests today/week/month
  - Total characters processed
  - Total estimated cost
  - Top users by usage

- [ ] Add usage charts
  - Daily character usage trend (last 30 days)
  - Cost breakdown by model (flash vs. multilingual)
  - Per-user usage statistics

- [ ] Create route and view
  - `GET /dashboard/elevenlabs-usage`
  - Require admin authentication
  - Location: `DashboardController` or new `ElevenLabsUsageController`

#### Cost Alerting

- [ ] Set up cost threshold alerting
  - Send email/Slack when daily cost exceeds $10
  - Alert when approaching monthly budget
  - Notify admin of unusual usage spikes

- [ ] Create scheduled task
  ```bash
  php artisan make:command MonitorElevenLabsCost
  ```

- [ ] Register in `app/Console/Kernel.php`
  - Run hourly or daily
  - Check cost against thresholds
  - Send alerts if exceeded

#### Logging Improvements

- [ ] Add structured logging for TTS requests
  - User ID, text length, voice ID, model ID
  - Response time, success/failure
  - Use Laravel's logging channels

- [ ] Log rate limit events
  - When user hits daily limit
  - When ElevenLabs returns 429
  - Track for pattern analysis

- [ ] Set up log monitoring
  - Search for ElevenLabs errors
  - Track average response times
  - Monitor for API key issues

### Phase 5: Testing & Quality Assurance ✅

**Status:** Partially complete - expand coverage

#### Unit Tests

- [ ] Test usage tracking model
  - `ElevenLabsUsage::logTtsRequest()`
  - Cost calculation accuracy
  - Relationship to User model

- [ ] Test daily limit logic
  - User within limit (should succeed)
  - User exceeds limit (should fail with 429)
  - Limit reset on new day

#### Integration Tests

- [ ] Update existing `ElevenLabsControllerTest`
  - Add test for default model (`eleven_flash_v2_5`)
  - Test usage tracking after successful request
  - Test daily limit enforcement

- [ ] Test error scenarios
  - Invalid API key
  - Rate limit from ElevenLabs (429)
  - Network timeout
  - Invalid voice ID

- [ ] Test authentication
  - Unauthenticated request returns 401
  - Valid token returns audio

#### Manual Testing

- [ ] Test TTS endpoint with cURL
  - Verify audio file is valid MP3
  - Check audio quality with sample stories
  - Test different voices (Cassidy, Rachel, etc.)

- [ ] Test on mobile app
  - React Native integration works
  - Audio plays correctly
  - Error handling shows user-friendly messages

- [ ] Test usage tracking
  - Verify database records created
  - Check cost calculations
  - Confirm daily limits work

### Phase 6: Documentation 📝

**Status:** Complete

- [x] Create `docs/elevenlabs.md` - Technical integration guide
- [x] Create `docs/vocal-narration.md` - Implementation checklist (this file)
- [x] Update `docs/CLAUDE.md` - Add ElevenLabs overview
- [ ] Document usage tracking schema in database docs
- [ ] Add API endpoint documentation
  - OpenAPI/Swagger spec (optional)
  - README with example requests

### Phase 7: Deployment & Rollout 🚀

**Status:** Not started

#### Pre-Deployment Checklist

- [ ] All Phase 2-4 tasks complete
- [ ] Tests passing (unit + integration)
- [ ] Usage tracking verified in staging
- [ ] Daily limits tested in staging
- [ ] Admin dashboard functional
- [ ] Cost alerting configured

#### Staging Deployment

- [ ] Deploy to staging environment
  - Run migrations for `elevenlabs_usage` table
  - Verify AWS Parameter Store has API key
  - Update environment config

- [ ] Smoke test in staging
  - Test TTS endpoint
  - Verify usage tracking
  - Test daily limits
  - Check admin dashboard

- [ ] Load testing (optional)
  - Simulate multiple concurrent TTS requests
  - Verify database performance
  - Check for memory leaks

#### Production Deployment

- [ ] Run database migrations
  ```bash
  php artisan migrate --force
  ```

- [ ] Clear config cache
  ```bash
  php artisan config:clear
  php artisan cache:clear
  ```

- [ ] Verify environment variables
  - `ELEVENLABS_API_KEY` loaded from Parameter Store
  - `ELEVENLABS_DEFAULT_MODEL=eleven_flash_v2_5`

- [ ] Monitor deployment
  - Watch Laravel logs for errors
  - Check first TTS requests succeed
  - Verify usage tracking working

- [ ] Enable for users
  - Roll out to 10% of users first (optional)
  - Monitor cost and usage
  - Full rollout if no issues

#### Post-Deployment

- [ ] Monitor daily costs for first week
  - Check dashboard daily
  - Verify costs align with estimates
  - Adjust limits if needed

- [ ] Gather user feedback
  - Audio quality acceptable?
  - Narration speed appropriate?
  - Any errors or issues?

- [ ] Document lessons learned
  - Update docs with production insights
  - Note any configuration tweaks needed
  - Plan optimization improvements

---

## Cost Estimates

### Expected Usage

**Assumptions:**
- 100 active users/day
- Each user listens to 5 story pages/day
- Average page: 500 characters

**Calculation:**
- Daily characters: 100 users × 5 pages × 500 chars = 250,000 chars
- Daily cost: 250,000 × $0.000024 = **$6.00/day**
- Monthly cost: $6 × 30 = **$180/month**

### Cost Controls

1. **Daily user limits** - Max 50,000 chars/user = $1.20/user max
2. **Global daily limit** - Set ceiling at $20/day if needed
3. **Cost alerts** - Email when daily cost > $10
4. **Model optimization** - Flash v2.5 is cheapest option

---

## Success Criteria

### Performance Metrics

- [ ] TTS response time < 5 seconds for 500-character page
- [ ] 99.5% uptime for TTS endpoint
- [ ] < 1% error rate for valid requests

### Cost Metrics

- [ ] Daily cost stays within budget ($10/day target)
- [ ] No unexpected cost spikes
- [ ] Usage tracking 100% accurate

### User Experience

- [ ] Audio quality rated 4/5 or higher by users
- [ ] No user complaints about narration speed
- [ ] Error messages clear and helpful

---

## Rollback Plan

If issues arise post-deployment:

1. **Disable feature flag** (if implemented)
   - Frontend stops calling TTS endpoint
   - Prevents further cost accumulation

2. **Emergency rate limiting**
   - Reduce daily limits to 5,000 chars/user
   - Throttle endpoint to 10 req/min globally

3. **Database rollback**
   - Rollback migration if table causes issues
   - Usage tracking optional for core functionality

4. **Revert to multilingual model**
   - If flash v2.5 has quality issues
   - Change config back to `eleven_multilingual_v2`



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

**Pending:**
- ❓ Daily limit: 10k or 50k characters for free users?
- ❓ Should we cache audio at backend or frontend?
- ❓ Need user preference for narration voice?
- ❓ Implement global daily cost ceiling?

---