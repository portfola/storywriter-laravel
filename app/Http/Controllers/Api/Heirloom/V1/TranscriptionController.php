<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Session;
use App\Models\Heirloom\Transcript;
use App\Services\Heirloom\TranscriptionService;
use Illuminate\Http\Request;

class TranscriptionController extends Controller
{
    public function __construct(protected TranscriptionService $transcriptionService)
    {
    }

    public function store(Request $request, Session $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'audio' => 'required|file|mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm|max:25000',
        ]);

        $transcriptText = $this->transcriptionService->transcribe($request->file('audio'));

        $transcript = Transcript::create([
            'session_id' => $session->id,
            'user_id' => $request->user()->id,
            'transcript_text' => $transcriptText,
            'status' => 'completed',
            'source' => 'audio',  // add this line
        ]);

        $session->update(['status' => 'transcribed']);

        return response()->json($transcript, 201);
    }

    public function storeManual(Request $request, Session $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'transcript_text' => 'required|string|min:50',
        ]);

        $transcript = Transcript::create([
            'session_id' => $session->id,
            'user_id' => $request->user()->id,
            'transcript_text' => $request->input('transcript_text'),
            'status' => 'completed',
            'source' => 'manual',
        ]);

        $session->update(['status' => 'transcribed']);

        return response()->json($transcript, 201);
    }

    public function show(Request $request, Session $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transcript = Transcript::where('session_id', $session->id)->first();

        if (!$transcript) {
            return response()->json(['message' => 'No transcript found'], 404);
        }

        return response()->json($transcript);
    }
}