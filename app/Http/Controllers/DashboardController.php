<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\User;
use App\Services\StoryAnalyticsService;

class DashboardController extends Controller
{
    public function __construct(
        private StoryAnalyticsService $analytics
    ) {}

    public function index()
    {

        // Security Check
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access Denied: Admins Only');
        }

        // Fetch basic user and story data for landing page
        $users = User::with('tokens')
            ->withCount('stories')
            ->latest()
            ->latest()->paginate(5, ['*'], 'users_page');

        $stories = Story::with('user')
            ->select('id', 'user_id', 'name', 'slug', 'created_at')
            ->latest()
            ->paginate(5, ['*'], 'stories_page');

        // Get quick stats for dashboard cards
        $quickStats = [
            'total_users' => User::count(),
            'total_stories' => Story::count(),
            'stories_today' => Story::whereDate('created_at', today())->count(),
            'total_generations' => $this->analytics->getTotalRequests(),
        ];

        // 3. Return View: Send the data to the dashboard page
        return view('dashboard', [
            'users' => $users,
            'stories' => $stories,
            'quickStats' => $quickStats,
        ]);

    }

    public function analytics()
    {
        // Security Check
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access Denied: Admins Only');
        }

        // Get comprehensive analytics data
        $data = [
            'overview' => $this->analytics->getOverview(),
            'avg_transcript_length' => round($this->analytics->getAverageTranscriptLength()),
            'avg_story_length' => round($this->analytics->getAverageStoryLength()),
            'recent_activity' => $this->analytics->getRecentActivity(50),
            'generation_trends' => $this->analytics->getDailyActivity(30),
        ];

        return view('dashboard.analytics', compact('data'));
    }

    public function show(Story $story)
    {
        return view('web.stories.show', compact('story'));
    }
}
