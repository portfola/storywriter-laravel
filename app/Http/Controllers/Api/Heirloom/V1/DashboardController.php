<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Narrative;
use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\Heirloom\Transcript;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'subjects' => Subject::where('user_id', $userId)->count(),
            'sessions' => Session::where('user_id', $userId)->count(),
            'transcripts' => Transcript::where('user_id', $userId)->count(),
            'narratives' => Narrative::where('user_id', $userId)->count(),
            'audio_sessions' => Transcript::where('user_id', $userId)->where('source', 'audio')->count(),
            'manual_sessions' => Transcript::where('user_id', $userId)->where('source', 'manual')->count(),
        ];

        $recentActivity = Session::where('user_id', $userId)
            ->with(['subject', 'transcript', 'narratives'])
            ->latest()
            ->take(10)
            ->get();

        $subjects = Subject::where('user_id', $userId)
            ->withCount('sessions')
            ->latest()
            ->take(10)
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'stats' => $stats,
                'subjects' => $subjects,
                'recent_activity' => $recentActivity,
            ]);
        }

        return view('heirloom.dashboard', compact('stats', 'recentActivity', 'subjects'));
    }
}
