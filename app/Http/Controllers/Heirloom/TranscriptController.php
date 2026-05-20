<?php

namespace App\Http\Controllers\Heirloom;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Transcript;

class TranscriptController extends Controller
{
    public function index()
    {
        $transcripts = Transcript::with(['session.subject'])
            ->latest()
            ->paginate(20);

        return view('heirloom.transcripts.index', compact('transcripts'));
    }

    public function show(Transcript $transcript)
    {
        $transcript->load(['session.subject']);
        return view('heirloom.transcripts.show', compact('transcript'));
    }
}