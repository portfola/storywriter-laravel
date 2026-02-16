<?php

namespace App\Console\Commands;

use App\Models\ElevenLabsUsage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorElevenLabsCost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elevenlabs:monitor-cost
                            {--period=today : Time period to check (today, week, month)}
                            {--notify : Send email notifications if thresholds are exceeded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor ElevenLabs API usage costs and alert on threshold violations';

    /**
     * Cost thresholds for alerting (in USD).
     */
    protected array $thresholds = [
        'daily' => 10.00,
        'weekly' => 50.00,
        'monthly' => 180.00,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period');
        $shouldNotify = $this->option('notify');

        $this->info("Checking ElevenLabs cost for period: {$period}");

        // Get cost metrics
        $totalCost = ElevenLabsUsage::getTotalCost($period);
        $totalRequests = ElevenLabsUsage::getTotalRequests($period);
        $totalCharacters = ElevenLabsUsage::getTotalCharacters($period);

        // Display metrics
        $this->displayMetrics($period, $totalCost, $totalRequests, $totalCharacters);

        // Check thresholds
        $violations = $this->checkThresholds($period, $totalCost);

        if (! empty($violations)) {
            $this->warn('⚠️  WARNING: Cost threshold violations detected!');
            foreach ($violations as $violation) {
                $this->warn("  • {$violation}");
            }

            // Send notifications if enabled
            if ($shouldNotify) {
                $this->sendAlerts($period, $totalCost, $violations);
            }

            Log::warning('ElevenLabs cost threshold exceeded', [
                'period' => $period,
                'total_cost' => $totalCost,
                'violations' => $violations,
            ]);

            // Return failure when notify flag is set (for automated monitoring)
            // Return success otherwise (for manual monitoring/reporting)
            return $shouldNotify ? self::FAILURE : self::SUCCESS;
        }

        $this->info('✓ All costs within acceptable thresholds');

        return self::SUCCESS;
    }

    /**
     * Display cost metrics in a formatted table.
     */
    protected function displayMetrics(string $period, float $totalCost, int $totalRequests, int $totalCharacters): void
    {
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Period', ucfirst($period)],
                ['Total Cost', '$'.number_format($totalCost, 2)],
                ['Total Requests', number_format($totalRequests)],
                ['Total Characters', number_format($totalCharacters)],
                ['Avg Cost/Request', $totalRequests > 0 ? '$'.number_format($totalCost / $totalRequests, 4) : 'N/A'],
            ]
        );

        // Show threshold for current period
        $threshold = $this->getThresholdForPeriod($period);
        if ($threshold) {
            $percentage = ($totalCost / $threshold) * 100;
            $this->info(sprintf(
                'Threshold: $%.2f (%.1f%% used)',
                $threshold,
                $percentage
            ));
        }

        $this->newLine();
    }

    /**
     * Check if costs exceed defined thresholds.
     */
    protected function checkThresholds(string $period, float $totalCost): array
    {
        $violations = [];
        $threshold = $this->getThresholdForPeriod($period);

        if ($threshold && $totalCost > $threshold) {
            $overage = $totalCost - $threshold;
            $percentage = (($totalCost - $threshold) / $threshold) * 100;

            $violations[] = sprintf(
                '%s cost $%.2f exceeds threshold $%.2f by $%.2f (%.1f%% over)',
                ucfirst($period),
                $totalCost,
                $threshold,
                $overage,
                $percentage
            );
        }

        // Check for unusual spikes (cost > 2x threshold)
        if ($threshold && $totalCost > ($threshold * 2)) {
            $violations[] = sprintf(
                'CRITICAL: %s cost is more than 2x the threshold! Possible abuse or runaway usage.',
                ucfirst($period)
            );
        }

        return $violations;
    }

    /**
     * Get the cost threshold for a given period.
     */
    protected function getThresholdForPeriod(string $period): ?float
    {
        return match ($period) {
            'today' => $this->thresholds['daily'],
            'week' => $this->thresholds['weekly'],
            'month' => $this->thresholds['monthly'],
            default => null,
        };
    }

    /**
     * Send alert notifications to admins.
     */
    protected function sendAlerts(string $period, float $totalCost, array $violations): void
    {
        $this->info('Sending alert notifications...');

        // Get all admin users
        $admins = User::admins()->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found to notify');

            return;
        }

        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    $this->buildAlertMessage($period, $totalCost, $violations),
                    function ($message) use ($admin, $period) {
                        $message->to($admin->email)
                            ->subject("⚠️ ElevenLabs Cost Alert: {$period} threshold exceeded");
                    }
                );

                $this->info("  ✓ Alert sent to {$admin->email}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to send alert to {$admin->email}: {$e->getMessage()}");
                Log::error('Failed to send ElevenLabs cost alert', [
                    'admin_email' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build the alert email message.
     */
    protected function buildAlertMessage(string $period, float $totalCost, array $violations): string
    {
        $threshold = $this->getThresholdForPeriod($period);
        $topUsers = ElevenLabsUsage::getTopUsers(5, $period);
        $costByModel = ElevenLabsUsage::getCostByModel($period);

        $message = "ElevenLabs Cost Alert - StoryWriter\n";
        $message .= str_repeat('=', 50)."\n\n";

        $message .= 'Period: '.ucfirst($period)."\n";
        $message .= 'Total Cost: $'.number_format($totalCost, 2)."\n";
        $message .= 'Threshold: $'.number_format($threshold, 2)."\n";
        $message .= "Status: EXCEEDED\n\n";

        $message .= "Violations:\n";
        foreach ($violations as $violation) {
            $message .= "  • {$violation}\n";
        }
        $message .= "\n";

        $message .= "Top Users by Cost:\n";
        foreach ($topUsers as $index => $userUsage) {
            $message .= sprintf(
                "  %d. %s - $%.2f (%s requests, %s chars)\n",
                $index + 1,
                $userUsage->user->name ?? 'Unknown',
                $userUsage->total_cost,
                number_format($userUsage->request_count),
                number_format($userUsage->total_characters)
            );
        }
        $message .= "\n";

        $message .= "Cost by Model:\n";
        foreach ($costByModel as $model) {
            $message .= sprintf(
                "  • %s - $%.2f (%s requests)\n",
                $model->model_id,
                $model->total_cost,
                number_format($model->request_count)
            );
        }
        $message .= "\n";

        $message .= "Action Required:\n";
        $message .= "  1. Review usage patterns in the admin dashboard\n";
        $message .= "  2. Check for potential abuse or runaway processes\n";
        $message .= "  3. Consider adjusting daily user limits if needed\n";
        $message .= "  4. Monitor costs closely over the next 24 hours\n\n";

        $message .= 'Dashboard: '.url('/dashboard/elevenlabs-usage')."\n";

        return $message;
    }
}
