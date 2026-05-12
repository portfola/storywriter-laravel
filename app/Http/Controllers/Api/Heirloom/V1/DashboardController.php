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
    $stats = [
        'subjects'        => Subject::count(),
        'sessions'        => Session::count(),
        'transcripts'     => Transcript::count(),
        'narratives'      => Narrative::count(),
        'audio_sessions'  => Transcript::where('source', 'audio')->count(),
        'manual_sessions' => Transcript::where('source', 'manual')->count(),
    ];

    $recentActivity = Session::with(['subject', 'transcript', 'narratives'])
        ->latest()
        ->take(10)
        ->get();

    $subjects = Subject::withCount('sessions')
        ->latest()
        ->take(10)
        ->get();

    if ($request->expectsJson()) {
        return response()->json([
            'stats'           => $stats,
            'subjects'        => $subjects,
            'recent_activity' => $recentActivity,
        ]);
    }

    return view('heirloom.dashboard', compact('stats', 'recentActivity', 'subjects'));
}
}