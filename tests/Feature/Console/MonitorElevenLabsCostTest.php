<?php

namespace Tests\Feature\Console;

use App\Models\ElevenLabsUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonitorElevenLabsCostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test cost monitoring command runs successfully
     */
    public function test_cost_monitoring_command_runs_successfully(): void
    {
        $this->artisan('elevenlabs:monitor-cost')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring command with period parameter
     */
    public function test_cost_monitoring_command_accepts_period_parameter(): void
    {
        $this->artisan('elevenlabs:monitor-cost --period=today')
            ->assertExitCode(0);

        $this->artisan('elevenlabs:monitor-cost --period=week')
            ->assertExitCode(0);

        $this->artisan('elevenlabs:monitor-cost --period=month')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring displays usage statistics
     */
    public function test_cost_monitoring_displays_usage_statistics(): void
    {
        $user = User::factory()->create();

        // Create usage data
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
        ]);

        $this->artisan('elevenlabs:monitor-cost --period=today')
            ->expectsOutputToContain('Checking ElevenLabs cost')
            ->expectsOutputToContain('Period')
            ->expectsOutputToContain('Total Requests')
            ->expectsOutputToContain('Total Characters')
            ->expectsOutputToContain('Total Cost')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring shows threshold warnings
     */
    public function test_cost_monitoring_shows_threshold_warnings_when_exceeded(): void
    {
        $user = User::factory()->create();

        // Create usage that exceeds daily threshold ($10)
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 500000, // ~$12 cost
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 12.00,
        ]);

        $this->artisan('elevenlabs:monitor-cost --period=today')
            ->expectsOutputToContain('⚠️  WARNING')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring with notify flag runs when threshold exceeded
     */
    public function test_cost_monitoring_with_notify_runs_when_threshold_exceeded(): void
    {
        // Create admin user
        $admin = User::factory()->create(['is_admin' => true]);

        // Create usage that exceeds threshold
        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 500000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 12.00,
        ]);

        $this->artisan('elevenlabs:monitor-cost --period=today --notify')
            ->expectsOutputToContain('Sending alert notifications')
            ->assertExitCode(1); // Command returns FAILURE when threshold exceeded
    }

    /**
     * Test cost monitoring with notify succeeds when within threshold
     */
    public function test_cost_monitoring_with_notify_succeeds_when_within_threshold(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create usage within threshold
        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
        ]);

        $this->artisan('elevenlabs:monitor-cost --period=today --notify')
            ->expectsOutputToContain('All costs within acceptable thresholds')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring shows no usage when database is empty
     */
    public function test_cost_monitoring_shows_zero_when_no_usage(): void
    {
        $this->artisan('elevenlabs:monitor-cost --period=today')
            ->expectsOutputToContain('Checking ElevenLabs cost')
            ->assertExitCode(0);
    }

    /**
     * Test cost monitoring calculates weekly stats correctly
     */
    public function test_cost_monitoring_calculates_weekly_stats_correctly(): void
    {
        $user = User::factory()->create();

        // Create usage over different days
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
            'created_at' => now()->subDays(2),
        ]);

        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 2000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.048,
            'created_at' => now(),
        ]);

        $this->artisan('elevenlabs:monitor-cost --period=week')
            ->expectsOutputToContain('2')
            ->expectsOutputToContain('3,000')
            ->assertExitCode(0);
    }
}
