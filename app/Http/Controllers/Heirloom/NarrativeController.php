<?php

namespace App\Http\Controllers\Heirloom;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Narrative;

class NarrativeController extends Controller
{
    public function index()
    {
        $narratives = Narrative::where('user_id', auth()->id())
            ->with(['subject', 'session'])
            ->latest()
            ->get();

        return view('heirloom.narratives.index', compact('narratives'));
    }

    
    public function show(Narrative $narrative)
    {
        $narrative->load(['session.subject']);
        return view('heirloom.narratives.show', compact('narrative'));
    }
}
