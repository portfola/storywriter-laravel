<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Narrative;
use App\Models\Heirloom\Subject;
use App\Services\Heirloom\NarrativeService;
use Illuminate\Http\Request;

class NarrativeController extends Controller
{
    public function __construct(protected NarrativeService $narrativeService)
    {
    }

    public function store(Request $request, Subject $subject)
    {
        if ($subject->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'format' => 'nullable|in:memoir,letter,timeline',
        ]);

        $format = $request->input('format', 'memoir');

        $narrativeText = $this->narrativeService->synthesise($subject, $format);

        $narrative = Narrative::create([
            'user_id' => $request->user()->id,
            'subject_id' => $subject->id,
            'narrative_text' => $narrativeText,
            'format' => $format,
            'status' => 'completed',
        ]);

        return response()->json($narrative, 201);
    }


    public function index()
    {
        $narratives = Narrative::where('user_id', auth()->id())
            ->with(['subject', 'session'])
            ->latest()
            ->get();

        return view('heirloom.narratives.index', compact('narratives'));
    }


    public function show(Request $request, Narrative $narrative)
    {
        if ($narrative->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($narrative);
    }


    public function showByToken(string $token)
    {
        $narrative = Narrative::where('share_token', $token)->firstOrFail();
        return response()->json($narrative);
    }

    public function destroy(Request $request, Narrative $narrative)
    {
        if ($narrative->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $sessionId = $narrative->session_id;
        $narrative->delete();

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('heirloom.sessions.show', $sessionId)
            ->with('status', 'Narrative deleted.');
    }
}