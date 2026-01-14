<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StoryAnalyticsService
{
    public function getOverview()
    {
        return [
            'total_requests' => $this->getTotalRequests(),
            'success_rate' => $this->getSuccessRate(),
            'avg_generation_time' => $this->getAverageGenerationTime(),
            'device_breakdown' => $this->getDeviceBreakdown(),
            'daily_activity' => $this->getDailyActivity(30),
        ];
    }

    public function getTotalRequests()
    {
        return DB::table('story_analytics')->count();
    }

    public function getSuccessRate()
    {
        $total = DB::table('story_analytics')->count();
        
        if ($total === 0) return 0;
        
        $successful = DB::table('story_analytics')
            ->whereRaw("JSON_EXTRACT(story_inputs, '$.generation_successful') = true")
            ->count();
        
        return round(($successful / $total) * 100, 2);
    }

    public function getAverageGenerationTime()
    {
        return DB::table('story_analytics')
            ->whereNotNull('generation_time_ms')
            ->avg('generation_time_ms');
    }

    public function getDeviceBreakdown()
{
    $driver = DB::connection()->getDriverName();
    
    return DB::table('story_analytics')
        ->select(
            DB::raw($this->getJsonExtractSql($driver, 'story_inputs', '$.device_type', 'device')),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('device')
        ->get()
        ->map(function($item) {
            return [
                'device' => $item->device ?? 'unknown',
                'count' => $item->count
            ];
        });
}

    public function getDailyActivity(int $days = 30)
    {
        return DB::table('story_analytics')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN JSON_EXTRACT(story_inputs, '$.generation_successful') = true THEN 1 ELSE 0 END) as successful")
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getAverageTranscriptLength()
    {
        return DB::table('story_analytics')
            ->selectRaw("AVG(JSON_EXTRACT(story_inputs, '$.transcript_length')) as avg_length")
            ->value('avg_length');
    }


    public function getAverageStoryLength()
    {
        return DB::table('story_analytics')
            ->selectRaw("AVG(JSON_EXTRACT(story_inputs, '$.story_length')) as avg_length")
            ->whereRaw("JSON_EXTRACT(story_inputs, '$.generation_successful') = true")
            ->value('avg_length');
    }

   public function getRecentActivity(int $limit = 50)
{
    $driver = DB::connection()->getDriverName();
    
    return DB::table('story_analytics')
        ->join('users', 'story_analytics.user_id', '=', 'users.id')
        ->select(
            'story_analytics.id',
            'story_analytics.user_id',
            'users.name as user_name',
            'users.email as user_email',
            'story_analytics.created_at',
            'story_analytics.generation_time_ms',
            DB::raw($this->getJsonExtractSql($driver, 'story_inputs', '$.device_type', 'device_type')),
            DB::raw($this->getJsonExtractSql($driver, 'story_inputs', '$.generation_successful', 'successful')),
            DB::raw($this->getJsonExtractSql($driver, 'story_inputs', '$.transcript_length', 'transcript_length')),
            DB::raw($this->getJsonExtractSql($driver, 'story_inputs', '$.story_length', 'story_length'))
        )
        ->orderBy('story_analytics.created_at', 'desc')
        ->limit($limit)
        ->get();
    }

    /**
 * Get database-specific JSON extraction SQL
 */
private function getJsonExtractSql(string $driver, string $column, string $path, string $alias): string
{
    return match($driver) {
        'sqlite' => "json_extract({$column}, '{$path}') as {$alias}",
        'pgsql' => "{$column}::json->>'device_type' as {$alias}", // PostgreSQL uses ->> for text extraction
        'mysql' => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')) as {$alias}",
        default => "JSON_EXTRACT({$column}, '{$path}') as {$alias}",
    };
}
}