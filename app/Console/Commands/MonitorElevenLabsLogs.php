<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MonitorElevenLabsLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elevenlabs:monitor-logs
                            {--type=all : Filter by log type (errors, rate-limits, slow, all)}
                            {--lines=100 : Number of recent lines to analyze}
                            {--stats : Show summary statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and analyze ElevenLabs API logs for errors, rate limits, and performance issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $lines = (int) $this->option('lines');
        $showStats = $this->option('stats');

        $this->info("🔍 Monitoring ElevenLabs Logs (last {$lines} lines)...\n");

        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            $this->error('Log file not found: '.$logPath);

            return 1;
        }

        // Read last N lines efficiently
        $logLines = $this->readLastLines($logPath, $lines);
        $elevenlabsLogs = $this->filterElevenLabsLogs($logLines);

        if (empty($elevenlabsLogs)) {
            $this->warn('No ElevenLabs logs found in the last '.$lines.' lines.');

            return 0;
        }

        // Show statistics if requested
        if ($showStats) {
            $this->displayStatistics($elevenlabsLogs);

            return 0;
        }

        // Filter by type
        switch ($type) {
            case 'errors':
                $this->displayErrors($elevenlabsLogs);
                break;
            case 'rate-limits':
                $this->displayRateLimits($elevenlabsLogs);
                break;
            case 'slow':
                $this->displaySlowRequests($elevenlabsLogs);
                break;
            default:
                $this->displayAllLogs($elevenlabsLogs);
                break;
        }

        return 0;
    }

    /**
     * Read last N lines from a file efficiently
     */
    private function readLastLines(string $path, int $lines): array
    {
        $buffer = 4096;
        $output = '';
        $file = fopen($path, 'rb');

        if (! $file) {
            return [];
        }

        fseek($file, -1, SEEK_END);

        if (fread($file, 1) != "\n") {
            $lines -= 1;
        }

        $output = '';
        $chunk = '';

        while (ftell($file) > 0 && $lines >= 0) {
            $seek = min(ftell($file), $buffer);
            fseek($file, -$seek, SEEK_CUR);
            $chunk = fread($file, $seek);
            fseek($file, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            $output = $chunk.$output;
            $lines -= substr_count($chunk, "\n");
        }

        fclose($file);

        $allLines = explode("\n", $output);

        return array_slice($allLines, -$lines);
    }

    /**
     * Filter logs to only ElevenLabs-related entries
     */
    private function filterElevenLabsLogs(array $lines): array
    {
        return array_filter($lines, function ($line) {
            return str_contains($line, 'ElevenLabs') ||
                   str_contains($line, 'elevenlabs') ||
                   str_contains($line, 'TTS');
        });
    }

    /**
     * Display error logs
     */
    private function displayErrors(array $logs): void
    {
        $this->info('🚨 ElevenLabs Errors:');
        $this->newLine();

        $errorCount = 0;
        foreach ($logs as $log) {
            if (str_contains($log, 'ERROR') || str_contains($log, 'error')) {
                $this->line("<fg=red>$log</>");
                $errorCount++;
            }
        }

        if ($errorCount === 0) {
            $this->success('✅ No errors found!');
        } else {
            $this->newLine();
            $this->warn("Found {$errorCount} error(s)");
        }
    }

    /**
     * Display rate limit logs
     */
    private function displayRateLimits(array $logs): void
    {
        $this->info('⏱️  ElevenLabs Rate Limits:');
        $this->newLine();

        $rateLimitCount = 0;
        foreach ($logs as $log) {
            if (str_contains($log, 'rate limit') || str_contains($log, '429')) {
                $this->line("<fg=yellow>$log</>");
                $rateLimitCount++;
            }
        }

        if ($rateLimitCount === 0) {
            $this->success('✅ No rate limit issues found!');
        } else {
            $this->newLine();
            $this->warn("Found {$rateLimitCount} rate limit event(s)");
            $this->comment('Consider implementing backoff strategy or upgrading ElevenLabs plan.');
        }
    }

    /**
     * Display slow requests (> 5 seconds)
     */
    private function displaySlowRequests(array $logs): void
    {
        $this->info('🐌 Slow ElevenLabs Requests (>5s):');
        $this->newLine();

        $slowCount = 0;
        foreach ($logs as $log) {
            // Look for response_time_ms > 5000
            if (preg_match('/"response_time_ms":(\d+)/', $log, $matches)) {
                $responseTime = (int) $matches[1];
                if ($responseTime > 5000) {
                    $seconds = round($responseTime / 1000, 2);
                    $this->line("<fg=yellow>$log</>");
                    $this->comment("  ⏱️  Response time: {$seconds}s");
                    $slowCount++;
                }
            }
        }

        if ($slowCount === 0) {
            $this->success('✅ No slow requests found!');
        } else {
            $this->newLine();
            $this->warn("Found {$slowCount} slow request(s)");
            $this->comment('Consider using faster models (eleven_flash_v2_5) or checking network issues.');
        }
    }

    /**
     * Display all logs
     */
    private function displayAllLogs(array $logs): void
    {
        $this->info('📋 All ElevenLabs Logs:');
        $this->newLine();

        foreach ($logs as $log) {
            // Color code by severity
            if (str_contains($log, 'ERROR')) {
                $this->line("<fg=red>$log</>");
            } elseif (str_contains($log, 'WARNING')) {
                $this->line("<fg=yellow>$log</>");
            } elseif (str_contains($log, 'INFO')) {
                $this->line("<fg=green>$log</>");
            } else {
                $this->line($log);
            }
        }

        $this->newLine();
        $this->info('Total logs: '.count($logs));
    }

    /**
     * Display summary statistics
     */
    private function displayStatistics(array $logs): void
    {
        $stats = [
            'total' => count($logs),
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
            'rate_limits' => 0,
            'slow_requests' => 0,
            'total_response_time' => 0,
            'response_count' => 0,
        ];

        foreach ($logs as $log) {
            if (str_contains($log, 'ERROR')) {
                $stats['errors']++;
            }
            if (str_contains($log, 'WARNING')) {
                $stats['warnings']++;
            }
            if (str_contains($log, 'INFO')) {
                $stats['info']++;
            }
            if (str_contains($log, 'rate limit') || str_contains($log, '429')) {
                $stats['rate_limits']++;
            }
            if (preg_match('/"response_time_ms":(\d+)/', $log, $matches)) {
                $responseTime = (int) $matches[1];
                $stats['total_response_time'] += $responseTime;
                $stats['response_count']++;
                if ($responseTime > 5000) {
                    $stats['slow_requests']++;
                }
            }
        }

        $avgResponseTime = $stats['response_count'] > 0
            ? round($stats['total_response_time'] / $stats['response_count'], 2)
            : 0;

        $this->info('📊 ElevenLabs API Statistics:');
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Logs', $stats['total']],
                ['Errors', $stats['errors'] > 0 ? "<fg=red>{$stats['errors']}</>" : '0'],
                ['Warnings', $stats['warnings'] > 0 ? "<fg=yellow>{$stats['warnings']}</>" : '0'],
                ['Info', "<fg=green>{$stats['info']}</>"],
                ['Rate Limit Events', $stats['rate_limits'] > 0 ? "<fg=yellow>{$stats['rate_limits']}</>" : '0'],
                ['Slow Requests (>5s)', $stats['slow_requests'] > 0 ? "<fg=yellow>{$stats['slow_requests']}</>" : '0'],
                ['Avg Response Time', $avgResponseTime > 0 ? "{$avgResponseTime}ms" : 'N/A'],
            ]
        );

        $this->newLine();

        if ($stats['errors'] > 0) {
            $this->warn("⚠️  {$stats['errors']} error(s) detected. Run with --type=errors for details.");
        }
        if ($stats['rate_limits'] > 0) {
            $this->warn("⚠️  {$stats['rate_limits']} rate limit event(s). Run with --type=rate-limits for details.");
        }
        if ($stats['slow_requests'] > 0) {
            $this->warn("⚠️  {$stats['slow_requests']} slow request(s). Run with --type=slow for details.");
        }
        if ($stats['errors'] === 0 && $stats['rate_limits'] === 0 && $stats['slow_requests'] === 0) {
            $this->success('✅ All systems operational!');
        }
    }
}
