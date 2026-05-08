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
    //     public function index(Request $request)
    // {
    //     $userId = $request->user()->id;

    //     $subjects = Subject::where('user_id', $userId)
    //         ->withCount(['sessions', 'sessions as transcribed_sessions_count' => function ($q) {
    //             $q->where('status', 'transcribed');
    //         }])
    //         ->latest()
    //         ->get();

    //     $recentActivity = Session::where('user_id', $userId)
    //         ->with(['subject:id,name', 'transcript:id,session_id,source,status', 'narratives:id,session_id,format,status'])
    //         ->latest()
    //         ->take(10)
    //         ->get()
    //         ->map(fn($session) => [
    //             'session_id'  => $session->id,
    //             'subject'     => $session->subject?->name,
    //             'status'      => $session->status,
    //             'source'      => $session->transcript?->source,
    //             'narrative'   => $session->narratives->first() ? [
    //                 'format' => $session->narratives->first()->format,
    //                 'status' => $session->narratives->first()->status,
    //             ] : null,
    //             'created_at'  => $session->created_at->toDateTimeString(),
    //         ]);

    //     return response()->json([
    //         'stats' => [
    //             'subjects'   => Subject::where('user_id', $userId)->count(),
    //             'sessions'   => Session::where('user_id', $userId)->count(),
    //             'transcripts' => Transcript::where('user_id', $userId)->count(),
    //             'narratives' => Narrative::where('user_id', $userId)->count(),
    //             'audio_sessions' => Transcript::where('user_id', $userId)->where('source', 'audio')->count(),
    //             'manual_sessions' => Transcript::where('user_id', $userId)->where('source', 'manual')->count(),
    //         ],
    //         'subjects'        => $subjects,
    //         'recent_activity' => $recentActivity,
    //     ]);
    // }

    // public function index()
    // {
    //     return response()->json(['message' => 'Heirloom dashboard']);
    // }

        public function index()
    {
        $stats = [
            'subjects'       => Subject::count(),
            'sessions'       => Session::count(),
            'transcripts'    => Transcript::count(),
            'narratives'     => Narrative::count(),
            'audio_sessions' => Transcript::where('source', 'audio')->count(),
            'manual_sessions'=> Transcript::where('source', 'manual')->count(),
        ];

        $recentActivity = Session::with(['subject', 'transcript', 'narratives'])
            ->latest()
            ->take(10)
            ->get();

        $subjects = Subject::withCount('sessions')
            ->latest()
            ->take(10)
            ->get();

        return view('heirloom.dashboard', compact('stats', 'recentActivity', 'subjects'));
    }
}