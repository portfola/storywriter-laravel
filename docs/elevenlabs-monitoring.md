# ElevenLabs API Monitoring Guide

**Last Updated:** February 15, 2026

This guide covers monitoring and troubleshooting ElevenLabs API usage in StoryWriter Laravel.

---

## Table of Contents

- [Quick Start](#quick-start)
- [Log Monitoring Command](#log-monitoring-command)
- [Common Monitoring Tasks](#common-monitoring-tasks)
- [Production Setup](#production-setup)
- [Alerting and Notifications](#alerting-and-notifications)
- [Troubleshooting](#troubleshooting)

---

## Quick Start

### Check Recent Activity

```bash
# View summary statistics
php artisan elevenlabs:monitor-logs --stats

# Check for errors
php artisan elevenlabs:monitor-logs --type=errors

# Check for rate limit issues
php artisan elevenlabs:monitor-logs --type=rate-limits

# Check for slow requests (>5s)
php artisan elevenlabs:monitor-logs --type=slow
```

### Real-time Log Monitoring

```bash
# Watch logs in real-time (local development)
php artisan pail --filter=elevenlabs

# Or use tail
tail -f storage/logs/laravel.log | grep -i elevenlabs
```

---

## Log Monitoring Command

### Command: `elevenlabs:monitor-logs`

Analyzes Laravel logs for ElevenLabs-related entries and provides insights.

**Location:** `app/Console/Commands/MonitorElevenLabsLogs.php`

### Usage

```bash
php artisan elevenlabs:monitor-logs [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--type` | `all` | Filter type: `errors`, `rate-limits`, `slow`, or `all` |
| `--lines` | `100` | Number of recent log lines to analyze |
| `--stats` | `false` | Show summary statistics instead of logs |

### Examples

**Show statistics summary:**
```bash
php artisan elevenlabs:monitor-logs --stats

# Output:
📊 ElevenLabs API Statistics:
+---------------------+-------+
| Metric              | Value |
+---------------------+-------+
| Total Logs          | 45    |
| Errors              | 0     |
| Warnings            | 2     |
| Info                | 43    |
| Rate Limit Events   | 0     |
| Slow Requests (>5s) | 1     |
| Avg Response Time   | 1234ms|
+---------------------+-------+
```

**Check for errors in last 500 lines:**
```bash
php artisan elevenlabs:monitor-logs --type=errors --lines=500
```

**Monitor rate limits:**
```bash
php artisan elevenlabs:monitor-logs --type=rate-limits
```

**Find slow requests:**
```bash
php artisan elevenlabs:monitor-logs --type=slow
```

---

## Common Monitoring Tasks

### 1. Search for API Key Issues

```bash
# Check logs for configuration errors
grep "ELEVENLABS_API_KEY" storage/logs/laravel.log

# Or use the monitor command
php artisan elevenlabs:monitor-logs --type=errors | grep "API_KEY"
```

**Common Issues:**
- `ELEVENLABS_API_KEY missing` - API key not configured in `.env`
- `ELEVENLABS_API_KEY is not configured` - Config cache needs clearing
- `401 Unauthorized` - Invalid or expired API key

**Solutions:**
1. Verify `.env` has correct key: `grep ELEVENLABS_API_KEY .env`
2. Clear config cache: `php artisan config:clear`
3. Verify key is valid at [ElevenLabs Dashboard](https://elevenlabs.io/app/settings/api-keys)

### 2. Track Response Times

```bash
# Find slow requests
php artisan elevenlabs:monitor-logs --type=slow

# Get average response time
php artisan elevenlabs:monitor-logs --stats | grep "Avg Response Time"
```

**Benchmarks:**
- **Fast:** < 2000ms (2 seconds) - Typical for `eleven_flash_v2_5`
- **Acceptable:** 2000-5000ms - Normal for longer text or multilingual models
- **Slow:** > 5000ms - Investigate network issues or switch to faster model

**Optimizations:**
- Use `eleven_flash_v2_5` model (fastest)
- Reduce text length per request
- Check network latency to ElevenLabs API
- Consider caching frequently-used audio

### 3. Monitor Rate Limits

```bash
# Check for rate limit events
php artisan elevenlabs:monitor-logs --type=rate-limits

# Analyze rate limit patterns
grep "rate limit" storage/logs/laravel.log | tail -20
```

**Rate Limit Indicators:**
- HTTP status `429` from ElevenLabs
- Log message: "ElevenLabs rate limit exceeded"
- Log context: `"rate_limited": true`

**Solutions:**
1. **Immediate:** Implement exponential backoff in client
2. **Short-term:** Check current plan limits at [ElevenLabs Pricing](https://elevenlabs.io/pricing)
3. **Long-term:** Upgrade ElevenLabs plan or implement request throttling

### 4. Analyze User Usage Patterns

```bash
# Find users hitting daily limits
grep "exceeded daily TTS limit" storage/logs/laravel.log

# Track high-volume users
php artisan tinker
>>> use App\Models\ElevenLabsUsage;
>>> ElevenLabsUsage::getTopUsers(10);
```

---

## Production Setup

### 1. Dedicated Log Channel

ElevenLabs logs can be written to a separate file for easier analysis.

**Configuration:** `config/logging.php`

```php
'channels' => [
    // ... other channels

    'elevenlabs' => [
        'driver' => 'daily',
        'path' => storage_path('logs/elevenlabs.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => env('LOG_DAILY_DAYS', 14),
        'replace_placeholders' => true,
    ],
],
```

**To use the channel in code:**
```php
Log::channel('elevenlabs')->info('TTS request completed', [
    'user_id' => $userId,
    'response_time_ms' => $responseTime,
]);
```

### 2. Automated Monitoring Scripts

Create a scheduled task to run monitoring daily:

**File:** `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('elevenlabs:monitor-logs --stats')
    ->dailyAt('06:00')
    ->emailOutputOnFailure('admin@storywriter.com');
```

### 3. CloudWatch Integration (AWS)

For production on AWS EC2:

**Install CloudWatch Agent:**
```bash
wget https://s3.amazonaws.com/amazoncloudwatch-agent/ubuntu/amd64/latest/amazon-cloudwatch-agent.deb
sudo dpkg -i amazon-cloudwatch-agent.deb
```

**Configure log streaming:**
```json
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/www/storywriter/storage/logs/laravel.log",
            "log_group_name": "/storywriter/production/laravel",
            "log_stream_name": "{instance_id}/laravel.log",
            "timestamp_format": "%Y-%m-%d %H:%M:%S"
          }
        ]
      }
    }
  }
}
```

**Create CloudWatch Metric Filter:**
```bash
aws logs put-metric-filter \
  --log-group-name /storywriter/production/laravel \
  --filter-name ElevenLabsErrors \
  --filter-pattern "[time, level=ERROR, ...msg*ElevenLabs*]" \
  --metric-transformations \
    metricName=ElevenLabsErrorCount,metricNamespace=StoryWriter,metricValue=1
```

**Create CloudWatch Alarm:**
```bash
aws cloudwatch put-metric-alarm \
  --alarm-name elevenlabs-error-rate \
  --alarm-description "Alert when ElevenLabs errors exceed threshold" \
  --metric-name ElevenLabsErrorCount \
  --namespace StoryWriter \
  --statistic Sum \
  --period 300 \
  --threshold 5 \
  --comparison-operator GreaterThanThreshold \
  --evaluation-periods 1 \
  --alarm-actions arn:aws:sns:us-east-1:ACCOUNT_ID:admin-alerts
```

### 4. Sentry Integration (Optional)

For advanced error tracking:

**Install Sentry SDK:**
```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

**Track ElevenLabs errors:**
```php
use Sentry\Laravel\Integration;

try {
    $response = Http::post($url, $payload);
    if (!$response->successful()) {
        throw new ElevenLabsException('TTS failed', $response->status());
    }
} catch (ElevenLabsException $e) {
    \Sentry\captureException($e);
    throw $e;
}
```

---

## Alerting and Notifications

### Email Alerts on Critical Errors

**File:** `app/Console/Commands/MonitorElevenLabsLogs.php`

Add notification logic:

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\ElevenLabsAlert;

if ($errorCount > 10) {
    Mail::to('admin@storywriter.com')->send(new ElevenLabsAlert([
        'error_count' => $errorCount,
        'time_period' => 'last hour',
        'logs' => $errorLogs,
    ]));
}
```

### Slack Notifications

**Install Slack notification channel:**
```bash
composer require laravel/slack-notification-channel
```

**Configure in `.env`:**
```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

**Send notification:**
```php
use Illuminate\Support\Facades\Log;

Log::channel('slack')->critical('ElevenLabs API errors exceeded threshold', [
    'error_count' => $errorCount,
    'time' => now()->toDateTimeString(),
]);
```

---

## Troubleshooting

### No Logs Appearing

**Symptoms:** `elevenlabs:monitor-logs` returns "No ElevenLabs logs found"

**Possible Causes:**
1. No recent TTS requests
2. Logs rotated out (older than 14 days)
3. Log level too high (not capturing INFO logs)

**Solutions:**
```bash
# Check log level
grep LOG_LEVEL .env

# Should be 'debug' or 'info' to capture all logs
LOG_LEVEL=debug

# Clear config cache
php artisan config:clear

# Make a test TTS request
curl -X POST http://localhost:8000/api/conversation/tts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text": "Test", "voiceId": "56AoDkrOh6qfVPDXZ7Pt"}'

# Check again
php artisan elevenlabs:monitor-logs --stats
```

### High Response Times

**Symptoms:** Average response time > 5 seconds

**Diagnostic Steps:**
```bash
# Check slow requests
php artisan elevenlabs:monitor-logs --type=slow

# Analyze patterns
grep "response_time_ms" storage/logs/laravel.log | \
  grep -oP '"response_time_ms":\K\d+' | \
  awk '{sum+=$1; count++} END {print "Avg:", sum/count, "ms"}'
```

**Common Causes:**
- Using `eleven_multilingual_v2` (slower model)
- Large text chunks (>1000 characters)
- Network latency to ElevenLabs servers
- ElevenLabs API performance issues

**Solutions:**
1. Switch to `eleven_flash_v2_5` model
2. Split large text into smaller chunks
3. Check server network latency: `ping api.elevenlabs.io`
4. Monitor [ElevenLabs Status Page](https://status.elevenlabs.io/)

### Frequent Rate Limits

**Symptoms:** Multiple `429` errors in logs

**Diagnostic:**
```bash
# Count rate limit events
php artisan elevenlabs:monitor-logs --type=rate-limits

# Check daily usage
php artisan tinker
>>> use App\Models\ElevenLabsUsage;
>>> ElevenLabsUsage::where('created_at', '>=', now()->startOfDay())->sum('character_count');
```

**Solutions:**
1. Check current plan limits: [ElevenLabs Pricing](https://elevenlabs.io/pricing)
2. Implement request queuing/throttling
3. Upgrade to higher ElevenLabs tier
4. Implement client-side caching for repeated audio

---

## Log Format Reference

### Successful TTS Request

```json
{
  "level": "INFO",
  "message": "ElevenLabs TTS request successful",
  "context": {
    "user_id": 123,
    "text_length": 456,
    "voice_id": "56AoDkrOh6qfVPDXZ7Pt",
    "model_id": "eleven_flash_v2_5",
    "response_time_ms": 1234.56,
    "audio_size_bytes": 78910,
    "success": true
  }
}
```

### Failed TTS Request

```json
{
  "level": "ERROR",
  "message": "ElevenLabs TTS request failed",
  "context": {
    "user_id": 123,
    "text_length": 456,
    "voice_id": "invalid-voice",
    "model_id": "eleven_flash_v2_5",
    "status_code": 404,
    "response_time_ms": 234.56,
    "error_details": {
      "detail": {
        "status": "voice_not_found",
        "message": "Voice does not exist"
      }
    }
  }
}
```

### Rate Limit Event

```json
{
  "level": "WARNING",
  "message": "ElevenLabs rate limit exceeded on TTS request",
  "context": {
    "user_id": 123,
    "text_length": 456,
    "voice_id": "56AoDkrOh6qfVPDXZ7Pt",
    "model_id": "eleven_flash_v2_5",
    "status_code": 429,
    "response_time_ms": 123.45,
    "error_details": {
      "detail": {
        "status": "quota_exceeded",
        "message": "You have exceeded your character quota"
      }
    },
    "rate_limited": true
  }
}
```

### Daily Limit Exceeded

```json
{
  "level": "WARNING",
  "message": "User exceeded daily TTS limit",
  "context": {
    "user_id": 123,
    "current_usage": 9800,
    "limit": 10000,
    "requested_chars": 500
  }
}
```

---

## Related Documentation

- [ElevenLabs Integration Guide](./elevenlabs.md)
- [Vocal Narration Implementation](./vocal-narration.md)
- [Cost Monitoring Command](./elevenlabs-cost-monitoring.md)

---

## Maintenance

**Daily:**
- Check monitoring command output: `php artisan elevenlabs:monitor-logs --stats`
- Review any errors: `php artisan elevenlabs:monitor-logs --type=errors`

**Weekly:**
- Analyze average response times
- Check for rate limit events
- Review top users by usage

**Monthly:**
- Audit total costs vs. budget
- Review and rotate API keys
- Check for deprecated models or API changes

---

**Need Help?**

- Check [ElevenLabs Status Page](https://status.elevenlabs.io/)
- Review [ElevenLabs Support Docs](https://help.elevenlabs.io)
- Contact StoryWriter development team
